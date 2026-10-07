-- BENS Middleware — DDL PostgreSQL (US 1.2)
-- Dérivation MCD → MLD : PK UUID, FK, contraintes d'unicité
-- Extension requise : pgcrypto ou gen_random_uuid() (PostgreSQL ≥ 13)

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ---------------------------------------------------------------------------
-- 1. MERCHANTS  (MCD: Merchant)
-- ---------------------------------------------------------------------------
CREATE TABLE merchants (
    id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name                 VARCHAR(255) NOT NULL,
    api_key_hash         VARCHAR(255) NOT NULL,
    webhook_secret_hash  VARCHAR(255) NOT NULL,
    created_at           TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_merchants_api_key_hash UNIQUE (api_key_hash)
);

-- ---------------------------------------------------------------------------
-- 2. COLLABORATORS  (MCD: Collaborator)
-- ---------------------------------------------------------------------------
CREATE TABLE collaborators (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    merchant_id    UUID         NOT NULL,
    email          VARCHAR(255) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(50)  NOT NULL,
    is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_collaborators_merchant
        FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT uq_collaborators_email UNIQUE (email),
    CONSTRAINT ck_collaborators_role
        CHECK (role IN ('ADMIN', 'CLIENT_SUCCESS', 'READ_ONLY'))
);

CREATE INDEX idx_collaborators_merchant_id ON collaborators (merchant_id);

-- ---------------------------------------------------------------------------
-- 3. BLACKLISTED_IPS  (MCD: BlacklistedIp)
-- ---------------------------------------------------------------------------
CREATE TABLE blacklisted_ips (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    merchant_id  UUID         NOT NULL,
    ip_address   INET         NOT NULL,
    reason       VARCHAR(512),
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_blacklisted_ips_merchant
        FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE CASCADE,
    CONSTRAINT uq_blacklisted_ips_merchant_ip UNIQUE (merchant_id, ip_address)
);

CREATE INDEX idx_blacklisted_ips_merchant_id ON blacklisted_ips (merchant_id);

-- ---------------------------------------------------------------------------
-- 4. EVENT_BRIDGES  (MCD: EventBridge)
-- Bridge d'événement + adresse SC stockée par Bens Contracts après déploiement.
-- ---------------------------------------------------------------------------
CREATE TABLE event_bridges (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    merchant_id             UUID         NOT NULL,
    external_event_id       VARCHAR(255) NOT NULL,
    smart_contract_address  VARCHAR(255),
    status                  VARCHAR(32)  NOT NULL DEFAULT 'DRAFT',
    created_at              TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_bridges_merchant
        FOREIGN KEY (merchant_id) REFERENCES merchants (id) ON DELETE RESTRICT,
    CONSTRAINT uq_event_bridges_merchant_event
        UNIQUE (merchant_id, external_event_id),
    CONSTRAINT ck_event_bridges_status
        CHECK (status IN ('DRAFT', 'ACTIVE', 'PAUSED', 'ARCHIVED'))
);

CREATE INDEX idx_event_bridges_merchant_id ON event_bridges (merchant_id);

-- ---------------------------------------------------------------------------
-- 5. EVENT_SMART_RULES  (MCD: EventSmartRules — relation 1:1 avec EVENT_BRIDGES)
-- Règles ancrées dans le SC : captation de valeur (royalty, plafond, SBT).
-- ---------------------------------------------------------------------------
CREATE TABLE event_smart_rules (
    id                       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_bridge_id          UUID           NOT NULL,
    token_type               VARCHAR(32)    NOT NULL DEFAULT 'NFT',
    allow_secondary_market   BOOLEAN        NOT NULL DEFAULT TRUE,
    max_resale_price_percent DECIMAL(5, 2)  NOT NULL DEFAULT 100.00,
    royalty_fee_percent      DECIMAL(5, 2)  NOT NULL DEFAULT 10.00,
    max_tickets_per_user     INTEGER        NOT NULL DEFAULT 4,
    created_at               TIMESTAMPTZ    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_smart_rules_bridge
        FOREIGN KEY (event_bridge_id) REFERENCES event_bridges (id) ON DELETE CASCADE,
    CONSTRAINT uq_event_smart_rules_bridge UNIQUE (event_bridge_id),
    CONSTRAINT ck_event_smart_rules_token_type
        CHECK (token_type IN ('NFT', 'SBT')),
    CONSTRAINT ck_event_smart_rules_max_resale
        CHECK (max_resale_price_percent >= 0 AND max_resale_price_percent <= 200),
    CONSTRAINT ck_event_smart_rules_royalty
        CHECK (royalty_fee_percent >= 0 AND royalty_fee_percent <= 50),
    CONSTRAINT ck_event_smart_rules_sbt_no_resale
        CHECK (
            token_type <> 'SBT'
            OR allow_secondary_market = FALSE
        )
);

-- ---------------------------------------------------------------------------
-- 6. TRANSACTION_AUDIT_LOGS  (MCD: TransactionAuditLog)
-- Audit paiement € → mint Web3 (idempotence NFR-05, replay NFR-04).
-- ---------------------------------------------------------------------------
CREATE TABLE transaction_audit_logs (
    id                        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_bridge_id           UUID         NOT NULL,
    external_seat_id          VARCHAR(255) NOT NULL,
    customer_email            VARCHAR(255) NOT NULL,
    payment_provider_ref       VARCHAR(255) NOT NULL,
    status                    VARCHAR(32)  NOT NULL,
    blockchain_tx_hash        VARCHAR(255),
    created_at                TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaction_audit_logs_bridge
        FOREIGN KEY (event_bridge_id) REFERENCES event_bridges (id) ON DELETE RESTRICT,
    CONSTRAINT uq_transaction_audit_logs_payment_ref UNIQUE (payment_provider_ref),
    CONSTRAINT ck_transaction_audit_logs_status
        CHECK (status IN ('PENDING_MINT', 'MINTED', 'FAILED', 'REFUNDED'))
);

CREATE INDEX idx_transaction_audit_logs_bridge_id
    ON transaction_audit_logs (event_bridge_id);
CREATE INDEX idx_transaction_audit_logs_status
    ON transaction_audit_logs (status);

-- ---------------------------------------------------------------------------
-- 7. SECONDARY_SALES  (MCD: SecondarySale — PREUVE DE CAPTATION DE VALEUR)
-- Reventes on-chain détectées par Bens Bridge. Aucun email de contrepartie
-- (discipline middleware). Le dashboard organisateur agrège royalty_amount.
-- ---------------------------------------------------------------------------
CREATE TABLE secondary_sales (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_bridge_id   UUID          NOT NULL,
    external_seat_id  VARCHAR(255)  NOT NULL,
    resale_price      DECIMAL(10,2) NOT NULL,
    royalty_amount    DECIMAL(10,2) NOT NULL,
    blockchain_tx_hash VARCHAR(255) NOT NULL,
    created_at        TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_secondary_sales_bridge
        FOREIGN KEY (event_bridge_id) REFERENCES event_bridges (id) ON DELETE RESTRICT,
    CONSTRAINT uq_secondary_sales_tx_hash UNIQUE (blockchain_tx_hash),
    CONSTRAINT ck_secondary_sales_resale_price
        CHECK (resale_price >= 0),
    CONSTRAINT ck_secondary_sales_royalty
        CHECK (royalty_amount >= 0)
);

CREATE INDEX idx_secondary_sales_bridge_id ON secondary_sales (event_bridge_id);
CREATE INDEX idx_secondary_sales_created_at ON secondary_sales (created_at);

-- ---------------------------------------------------------------------------
-- 8. WEBHOOK_DISPATCHES  (table technique — cahier des charges / NFR)
-- File + retries des webhooks sortants de réconciliation vers le partenaire.
-- ---------------------------------------------------------------------------
CREATE TABLE webhook_dispatches (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id  UUID         NOT NULL,
    target_url      VARCHAR(512) NOT NULL,
    payload         JSONB        NOT NULL,
    status          VARCHAR(32)  NOT NULL,
    retry_count     INTEGER      NOT NULL DEFAULT 0,
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_webhook_dispatches_transaction
        FOREIGN KEY (transaction_id)
        REFERENCES transaction_audit_logs (id) ON DELETE CASCADE,
    CONSTRAINT ck_webhook_dispatches_status
        CHECK (status IN ('PENDING', 'DELIVERED', 'FAILED'))
);

CREATE INDEX idx_webhook_dispatches_transaction_id ON webhook_dispatches (transaction_id);
CREATE INDEX idx_webhook_dispatches_status ON webhook_dispatches (status);
