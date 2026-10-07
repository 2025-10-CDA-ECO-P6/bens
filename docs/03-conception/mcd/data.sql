-- BENS Middleware — seed de test (US 1.3)
-- Prérequis : schema.sql déjà appliqué.
-- Idempotent : ON CONFLICT DO NOTHING (rejouable sans doublon).
--
-- Secrets en clair (DEV UNIQUEMENT — jamais en production) :
--   API key        : bens_dev_api_key_test_merchant_001
--   Webhook secret : bens_dev_webhook_secret_test_001
--   Mot de passe   : AdminBens2026!
-- Les empreintes ci-dessous sont des hashs BCrypt ($2b$10$...).

-- ---------------------------------------------------------------------------
-- UUID v4 fixes (reproductibilité des tests / Postman / intégration)
-- ---------------------------------------------------------------------------
-- Merchant        : 4c2318ee-4aeb-4094-a404-6a01864ee6a4
-- Collaborator    : d8216e37-c524-4de6-ba22-47946b673760
-- Event bridge    : e7e0d0e2-3a0b-4e08-9b38-c542ba63c94d
-- Smart rules     : 771955a1-2a74-4731-ad75-823fa577cc73

-- ---------------------------------------------------------------------------
-- 1. Marchand de test
-- ---------------------------------------------------------------------------
INSERT INTO merchants (id, name, api_key_hash, webhook_secret_hash)
VALUES (
    '4c2318ee-4aeb-4094-a404-6a01864ee6a4'::uuid,
    'BilletWeb Test (DEV)',
    '$2b$10$d7iva8P.KyLA/fEPDHbt0e66LOoPjO1OgExkuSTDyY/y.6Jz5zTbC',
    '$2b$10$Zv6xLln2ujGs31iDZKw2WuEjYQqQopCg/eGMBMP/VSZWOW7bMx7zW'
)
ON CONFLICT (id) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 2. Collaborateur ADMIN actif (dashboard)
-- ---------------------------------------------------------------------------
INSERT INTO collaborators (id, merchant_id, email, password_hash, role, is_active)
VALUES (
    'd8216e37-c524-4de6-ba22-47946b673760'::uuid,
    '4c2318ee-4aeb-4094-a404-6a01864ee6a4'::uuid,
    'admin@billetweb-test.dev',
    '$2b$10$gIA7lEdHipMPBkTCw5TZGuspy57dthv8sXHsUrMOUxpAPrT2VuuCa',
    'ADMIN',
    TRUE
)
ON CONFLICT (id) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 3. Bridge d'événement ACTIF + adresse SC MultiversX (erd1…)
--    Adresse de forme smart-contract (préfixe erd1qqqqqqqqqqqqq).
-- ---------------------------------------------------------------------------
INSERT INTO event_bridges (
    id,
    merchant_id,
    external_event_id,
    smart_contract_address,
    status
)
VALUES (
    'e7e0d0e2-3a0b-4e08-9b38-c542ba63c94d'::uuid,
    '4c2318ee-4aeb-4094-a404-6a01864ee6a4'::uuid,
    'evt-festival-demo-2026',
    'erd1qqqqqqqqqqqqqpgqhe8t5jewej70zupmh44jurgn29psua5l2jps3ntjj3',
    'ACTIVE'
)
ON CONFLICT (id) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 4. Règles smart contract (1:1) — captation de valeur POC
-- ---------------------------------------------------------------------------
INSERT INTO event_smart_rules (
    id,
    event_bridge_id,
    token_type,
    allow_secondary_market,
    max_resale_price_percent,
    royalty_fee_percent,
    max_tickets_per_user
)
VALUES (
    '771955a1-2a74-4731-ad75-823fa577cc73'::uuid,
    'e7e0d0e2-3a0b-4e08-9b38-c542ba63c94d'::uuid,
    'NFT',
    TRUE,
    120.00,
    10.00,
    4
)
ON CONFLICT (id) DO NOTHING;
