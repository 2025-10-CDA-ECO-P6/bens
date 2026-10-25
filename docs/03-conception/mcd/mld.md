# Modèle Logique de Données (MLD) — BENS Middleware

> **Référence US 1.2** — Dérivation du MCD en tables relationnelles
> Clés primaires **UUID**, clés étrangères, contraintes d'**unicité**.
> SGBD cible : **PostgreSQL 17**.
> **Argument n°1** : la table `SECONDARY_SALES` trace les royalties perçues
> sur le marché secondaire (captation de valeur).

---

## 0. Mapping MCD → MLD

| Entité MCD | Table MLD | Rôle |
| :--- | :--- | :--- |
| Merchant | `MERCHANTS` | Partenaires B2B |
| Collaborator | `COLLABORATORS` | Accès dashboard (RBAC) |
| BlacklistedIp | `BLACKLISTED_IPS` | Ban IP multi-tenant |
| EventBridge | `EVENT_BRIDGES` | Bridge événement + adresse SC (écrite par BensCore) |
| EventSmartRules | `EVENT_SMART_RULES` | Règles ancrées dans le SC (1:1 avec `EVENT_BRIDGES`) |
| TransactionAuditLog | `TRANSACTION_AUDIT_LOGS` | Audit paiement → mint |
| SecondarySale | `SECONDARY_SALES` | **Preuve de captation de valeur** (royalties perçues) |

**Table technique complémentaire** (hors liste US 1.2, requise par le cahier
des charges / NFR webhooks) :

| Table | Rôle |
| :--- | :--- |
| `WEBHOOK_DISPATCHES` | File + retries des webhooks sortants de réconciliation |

> **Vocabulaire** : `MERCHANTS` ↔ `ORGANIZATIONS`, `COLLABORATORS` ↔ `USERS`
> (aligné sur les décisions actées : Merchant ↔ Organisations,
> Collaborator ↔ Users).

---

## 1. `MERCHANTS`

Partenaires B2B utilisant le middleware BENS.

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant marchand |
| `name` | `VARCHAR(255)` | `NOT NULL` | Nom commercial |
| `api_key_hash` | `VARCHAR(255)` | `NOT NULL`, **UNIQUE** | Hash de la clé API |
| `webhook_secret_hash` | `VARCHAR(255)` | `NOT NULL` | Hash du secret HMAC webhooks |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date de création |

---

## 2. `COLLABORATORS`

Accès Dashboard BENS (RBAC), rattachés à un marchand.

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant collaborateur |
| `merchant_id` | `UUID` | **FK** → `MERCHANTS(id)` `ON DELETE CASCADE`, `NOT NULL` | Marchand propriétaire |
| `email` | `VARCHAR(255)` | `NOT NULL`, **UNIQUE** | Email de login |
| `password_hash` | `VARCHAR(255)` | `NOT NULL` | Mot de passe haché |
| `role` | `VARCHAR(50)` | `NOT NULL` | `ADMIN` \| `CLIENT_SUCCESS` \| `READ_ONLY` |
| `is_active` | `BOOLEAN` | `NOT NULL`, `DEFAULT TRUE` | Compte actif |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date de création |

---

## 3. `BLACKLISTED_IPS`

IP interdites par marchand (sécurité API / Dashboard).

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant ban |
| `merchant_id` | `UUID` | **FK** → `MERCHANTS(id)` `ON DELETE CASCADE`, `NOT NULL` | Marchand concerné |
| `ip_address` | `INET` | `NOT NULL` | Adresse IPv4/IPv6 (ou préfixe) |
| `reason` | `VARCHAR(512)` | `NULLABLE` | Motif du ban |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date du ban |

> **Unicité :** `UNIQUE (merchant_id, ip_address)` — une même IP ne peut être blacklistée qu'une fois par marchand.

---

## 4. `EVENT_BRIDGES`

Bridge d'événement : liaison entre l'événement Web2 (identifiant opaque) et
le smart contract MultiversX. **Bens Contracts y stocke l'adresse du SC**
après déploiement on-chain.

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant bridge |
| `merchant_id` | `UUID` | **FK** → `MERCHANTS(id)` `ON DELETE RESTRICT`, `NOT NULL` | Marchand propriétaire |
| `external_event_id` | `VARCHAR(255)` | `NOT NULL` | ID événement opaque Web2 |
| `smart_contract_address` | `VARCHAR(255)` | `NULLABLE` | Adresse SC MultiversX (null tant que non déployé) |
| `status` | `VARCHAR(32)` | `NOT NULL`, `DEFAULT 'DRAFT'` | `DRAFT` \| `ACTIVE` \| `PAUSED` \| `ARCHIVED` |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date de création |

> **Unicité :** `UNIQUE (merchant_id, external_event_id)`
> **Check :** `status IN ('DRAFT','ACTIVE','PAUSED','ARCHIVED')`

---

## 5. `EVENT_SMART_RULES`

Règles ancrées dans le smart contract (relation **1:1** avec `EVENT_BRIDGES`).
C'est ici que se configure la **captation de valeur** (royalty, plafond, SBT).

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant règles |
| `event_bridge_id` | `UUID` | **FK** → `EVENT_BRIDGES(id)` `ON DELETE CASCADE`, `NOT NULL`, **UNIQUE** | Bridge propriétaire (1:1) |
| `token_type` | `VARCHAR(32)` | `NOT NULL`, `DEFAULT 'NFT'` | `NFT` \| `SBT` |
| `allow_secondary_market` | `BOOLEAN` | `NOT NULL`, `DEFAULT TRUE` | Revente autorisée |
| `max_resale_price_percent` | `DECIMAL(5,2)` | `NOT NULL`, `DEFAULT 100.00` | Plafond revente (%) — BigDecimal |
| `royalty_fee_percent` | `DECIMAL(5,2)` | `NOT NULL`, `DEFAULT 10.00` | Royalties (%) — BigDecimal |
| `max_tickets_per_user` | `INTEGER` | `NOT NULL`, `DEFAULT 4` | Limite anti-bot |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date de création |

> **Checks recommandés :**
> - `max_resale_price_percent BETWEEN 0 AND 200`
> - `royalty_fee_percent BETWEEN 0 AND 50`
> - `token_type IN ('NFT','SBT')`
> - si `token_type = 'SBT'` alors `allow_secondary_market = FALSE`

---

## 6. `TRANSACTION_AUDIT_LOGS`

Journal d'audit paiement → mint (idempotence NFR-05, replay NFR-04).

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | UUID de transaction (propagé webhooks / mint) |
| `event_bridge_id` | `UUID` | **FK** → `EVENT_BRIDGES(id)` `ON DELETE RESTRICT`, `NOT NULL` | Bridge concerné |
| `external_seat_id` | `VARCHAR(255)` | `NOT NULL` | ID siège opaque partenaire |
| `customer_email` | `VARCHAR(255)` | `NOT NULL` | Email acheteur (transport, mint initial) |
| `payment_provider_ref` | `VARCHAR(255)` | `NOT NULL`, **UNIQUE** | Réf. paiement (dédoublonnage) |
| `status` | `VARCHAR(32)` | `NOT NULL` | `PENDING_MINT` \| `MINTED` \| `FAILED` \| `REFUNDED` |
| `blockchain_tx_hash` | `VARCHAR(255)` | `NULLABLE` | Hash MultiversX (`NULL` à l'INSERT paiement ; rempli après soumission mint ; `MINTED` seulement après conf Bridge→Core) |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Réception webhook paiement |

> **Cycle de vie** (écrit **uniquement par BensCore**) :
> 1. Webhook paiement → `INSERT` `PENDING_MINT`, `tx_hash NULL`
> 2. Retour Contract → `UPDATE` `tx_hash`, statut reste `PENDING_MINT`
> 3. Consume confirmation Bridge → `UPDATE` `MINTED`

---

## 7. `SECONDARY_SALES` (preuve de captation de valeur)

**Table centrale pour l'argument n°1** : chaque revente on-chain détectée par
BensBridge (publiée en RabbitMQ) est **persistée par BensCore**. Le dashboard
organisateur agrège ces lignes pour afficher les royalties perçues.
**Aucun email** de contrepartie n'est stocké (discipline middleware).

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant revente |
| `event_bridge_id` | `UUID` | **FK** → `EVENT_BRIDGES(id)` `ON DELETE RESTRICT`, `NOT NULL` | Bridge concerné |
| `external_seat_id` | `VARCHAR(255)` | `NOT NULL` | ID siège opaque revendu |
| `resale_price` | `DECIMAL(10,2)` | `NOT NULL` | Prix de revente on-chain (BigDecimal) |
| `royalty_amount` | `DECIMAL(10,2)` | `NOT NULL` | Royalty captée par l'organisateur (BigDecimal) |
| `blockchain_tx_hash` | `VARCHAR(255)` | `NOT NULL`, **UNIQUE** | Hash MultiversX de la revente (preuve on-chain) |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Date de détection on-chain |

> **Unicité :** `UNIQUE (blockchain_tx_hash)` — une revente on-chain = une ligne d'audit (idempotence).

---

## 8. `WEBHOOK_DISPATCHES` (table technique)

Dispatch asynchrone des webhooks de réconciliation vers le partenaire
(**écrit et lu par BensCore uniquement**).

| Colonne | Type SQL | Contraintes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | **PK**, `DEFAULT gen_random_uuid()` | Identifiant dispatch |
| `transaction_id` | `UUID` | **FK** → `TRANSACTION_AUDIT_LOGS(id)` `ON DELETE CASCADE`, `NOT NULL` | Transaction source |
| `target_url` | `VARCHAR(512)` | `NOT NULL` | URL callback partenaire |
| `payload` | `JSONB` | `NOT NULL` | Corps de notification |
| `status` | `VARCHAR(32)` | `NOT NULL` | `PENDING` \| `DELIVERED` \| `FAILED` |
| `retry_count` | `INTEGER` | `NOT NULL`, `DEFAULT 0` | Tentatives |
| `updated_at` | `TIMESTAMPTZ` | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP` | Dernière tentative |

---

## 9. Schéma relationnel MLD (Mermaid)

```mermaid
erDiagram
    MERCHANTS ||--o{ COLLABORATORS : "merchant_id"
    MERCHANTS ||--o{ BLACKLISTED_IPS : "merchant_id"
    MERCHANTS ||--o{ EVENT_BRIDGES : "merchant_id"
    EVENT_BRIDGES ||--|| EVENT_SMART_RULES : "event_bridge_id (1:1)"
    EVENT_BRIDGES ||--o{ TRANSACTION_AUDIT_LOGS : "event_bridge_id"
    EVENT_BRIDGES ||--o{ SECONDARY_SALES : "event_bridge_id"
    TRANSACTION_AUDIT_LOGS ||--o{ WEBHOOK_DISPATCHES : "transaction_id"

    MERCHANTS {
        uuid id PK
        varchar name
        varchar api_key_hash UK
        varchar webhook_secret_hash
        timestamptz created_at
    }

    COLLABORATORS {
        uuid id PK
        uuid merchant_id FK
        varchar email UK
        varchar password_hash
        varchar role
        boolean is_active
        timestamptz created_at
    }

    BLACKLISTED_IPS {
        uuid id PK
        uuid merchant_id FK
        inet ip_address
        varchar reason
        timestamptz created_at
    }

    EVENT_BRIDGES {
        uuid id PK
        uuid merchant_id FK
        varchar external_event_id
        varchar smart_contract_address
        varchar status
        timestamptz created_at
    }

    EVENT_SMART_RULES {
        uuid id PK
        uuid event_bridge_id FK_UK
        varchar token_type
        boolean allow_secondary_market
        decimal max_resale_price_percent
        decimal royalty_fee_percent
        int max_tickets_per_user
        timestamptz created_at
    }

    TRANSACTION_AUDIT_LOGS {
        uuid id PK
        uuid event_bridge_id FK
        varchar external_seat_id
        varchar customer_email
        varchar payment_provider_ref UK
        varchar status
        varchar blockchain_tx_hash
        timestamptz created_at
    }

    SECONDARY_SALES {
        uuid id PK
        uuid event_bridge_id FK
        varchar external_seat_id
        decimal resale_price
        decimal royalty_amount
        varchar blockchain_tx_hash UK
        timestamptz created_at
    }

    WEBHOOK_DISPATCHES {
        uuid id PK
        uuid transaction_id FK
        varchar target_url
        jsonb payload
        varchar status
        int retry_count
        timestamptz updated_at
    }
```

---

## 10. Synthèse des contraintes

| Contrainte | Table | Colonnes |
| :--- | :--- | :--- |
| PK UUID | toutes | `id` |
| FK | `COLLABORATORS` | `merchant_id` → `MERCHANTS` |
| FK | `BLACKLISTED_IPS` | `merchant_id` → `MERCHANTS` |
| FK | `EVENT_BRIDGES` | `merchant_id` → `MERCHANTS` |
| FK | `EVENT_SMART_RULES` | `event_bridge_id` → `EVENT_BRIDGES` (UNIQUE, 1:1) |
| FK | `TRANSACTION_AUDIT_LOGS` | `event_bridge_id` → `EVENT_BRIDGES` |
| FK | `SECONDARY_SALES` | `event_bridge_id` → `EVENT_BRIDGES` |
| FK | `WEBHOOK_DISPATCHES` | `transaction_id` → `TRANSACTION_AUDIT_LOGS` |
| UNIQUE | `MERCHANTS` | `api_key_hash` |
| UNIQUE | `COLLABORATORS` | `email` |
| UNIQUE | `BLACKLISTED_IPS` | `(merchant_id, ip_address)` |
| UNIQUE | `EVENT_BRIDGES` | `(merchant_id, external_event_id)` |
| UNIQUE | `EVENT_SMART_RULES` | `event_bridge_id` (1:1) |
| UNIQUE | `TRANSACTION_AUDIT_LOGS` | `payment_provider_ref` |
| UNIQUE | `SECONDARY_SALES` | `blockchain_tx_hash` |

---

## 11. Exports

| Fichier | Description |
| :--- | :--- |
| `mld.md` | Présent document |
| `MLD.png` | Export graphique du MLD |
| `mld.dot` | Source Graphviz |
| `schema.sql` | DDL PostgreSQL source (créable / versionnable) |

---

## 12. Traçabilité US 1.2

| Critère | Couverture |
| :--- | :--- |
| Dérivation MLD + PK UUID | §§ 1–8 |
| Clés étrangères | § 10 |
| Contraintes d'unicité | § 10 |
| Captation de valeur modélisée (`SECONDARY_SALES`) | § 7 |
| Export schémas graphiques + fichiers source | § 11 |
