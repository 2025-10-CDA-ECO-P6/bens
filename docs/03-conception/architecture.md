# Architecture technique — Projet BENS

> **Référence US 1.7** — Document d'architecture distribuée  
> **Source de vérité métier** : [`../01-contexte/cahier-des-charges.md`](../01-contexte/cahier-des-charges.md)  
> **Principe** : middleware transmetteur de messages + forgeur de NFT/SBT —
> **jamais une billetterie bis**.

---

## 1. Objectif d'architecture

BENS s'intercale entre une billetterie Web2 partenaire et MultiversX pour :

1. **Capter la valeur du marché secondaire** (royalties ancrées dans le SC —
   argument produit n°1).
2. **Certifier** chaque billet vendu (NFT/SBT) sans exposer le Web3 au fan.
3. **Absorber les pics de charge** (verrous Redis Lua) sans saturer le SQL
   partenaire — enableur technique.

L'architecture est **API-First**, **stateless**, **event-driven**, découpée
en **3 modules** qui partagent un PostgreSQL et s'isolent par responsabilité.

---

## 2. Vue d'ensemble — 3 modules

```text
┌──────────────────────┐      HTTP       ┌──────────────────────┐
│      BensCore        │ ──────────────────► │    BensContract      │
│  Java 25 / SB 4      │   (mint / deploy)   │  Rust off-chain      │
│                      │                     │  + SC MultiversX     │
│  • API lock/unlock   │                     │  • Déploiement SC    │
│  • Redis Lua         │                     │  • Mint NFT/SBT      │
│  • Webhook paiement  │                     │  • Paymaster (AA)    │
│  • Audit PENDING→MINTED                    │  • Smart Wallet      │
│  • MintOrchestrator  │                     │  • Web3 Gateway      │
│  • Dashboard Angular │                     └──────────▲───────────┘
│  • QR statique       │                                │
└──────────▲───────────┘                                │ on-chain
           │                                            │ events
           │ API B2B                                    │
           │                                            │
    ┌──────┴──────┐                          ┌──────────┴───────────┐
    │ Partenaire  │                          │     BensBridge       │
    │ Web2        │ ◄── webhooks ──────      │  Java 25 / SB 4      │
    │ (VEL…)      │     (sortants)           │                      │
    └─────────────┘                          │  • Écouteur chain    │
                                             │  • Réconciliation    │
                                             │  • RabbitMQ outbox   │
                                             │  • WebhookDispatcher │
                                             │  • Retry / DLQ       │
                                             └──────────────────────┘
```

| Module | Rôle | Ne fait **pas** |
| :--- | :--- | :--- |
| **BensCore** | API partenaire, verrous Redis, réception paiement, audit, orchestration mint, dashboard, QR | Mint on-chain, écoute blockchain |
| **BensContract** | Définir/déployer SC, stocker adresses en BDD, mint, Paymaster, Smart Wallet, gateway MultiversX | Verrous Redis, webhooks partenaire |
| **BensBridge** | Réconciliation Web2 ↔ Web3 uniquement : écoute on-chain, outbox RabbitMQ, webhooks sortants HMAC | Mint, écriture initiale de l'audit |

### Décisions d'architecture actées

| Décision | Conséquence |
| :--- | :--- |
| Webhook paiement reçu par **BensCore** | BensCore écrit `TRANSACTION_AUDIT_LOGS` (`PENDING_MINT`) |
| Mint orchestré par **BensCore** → **BensContract** | Appel synchrone/RPC ; **pas de RabbitMQ pour le mint** |
| Paymaster dans **BensContract** | Account Abstraction côté Rust / MultiversX |
| BensBridge = **réconciliation seule** | RabbitMQ = queue `bens.webhook.outbox` (sortants) |
| PostgreSQL **partagé** | `schema.sql` chargé par BensCore ; les 3 modules s'y connectent |
| Pas de Flyway | `CREATE TABLE IF NOT EXISTS` + Spring Boot `spring.sql.init` |

---

## 3. Stack technique

| Couche | Technologie | Contrainte |
| :--- | :--- | :--- |
| Backend Core / Bridge | Java **25**, Spring Boot **4+**, Virtual Threads | LTS / Loom |
| Backend Contracts | **Rust** (off-chain) + SC MultiversX | Workspace Cargo |
| Dashboard | Angular **18+** | RBAC organisateur |
| Cache / verrous | **Redis** + scripts Lua | TTL 10 min |
| Broker | **RabbitMQ** (topic + DLQ) | Webhooks sortants uniquement |
| BDD | **PostgreSQL** (UUID PK) | Schéma middleware only |
| Intégration | Webhooks JSON + HMAC, SDK MultiversX | DX partenaire |

---

## 4. Diagramme de composants

```mermaid
flowchart TB
  subgraph Partenaire["Partenaire Web2"]
    VEL[Billetterie / Checkout €]
  end

  subgraph Core["BensCore"]
    API[API B2B<br/>lock / unlock / statut]
    Auth[CustomAuthFilter<br/>API Key · IP · rate limit]
    RedisLua[Redis Lua<br/>verrou siège]
    PayWH[PaymentWebhookController]
    Audit[TransactionAuditService]
    Orch[MintOrchestrator]
    Dash[Dashboard Angular<br/>bridges · règles · royalties]
    QR[QR statique]
  end

  subgraph Contract["BensContract"]
    Deploy[DeployService]
    MintSvc[MintService]
    Paymaster[Paymaster / AA]
    Wallet[Smart Wallet]
    GW[Web3 Gateway]
    SC[Smart Contract<br/>NFT/SBT · royalties · plafond]
  end

  subgraph Bridge["BensBridge"]
    Listener[Écouteur blockchain]
    Recon[Réconciliation audit]
    MQ[RabbitMQ<br/>bens.webhook.outbox]
    Disp[WebhookDispatcher + Signer]
    Retry[RetryPolicy / DLQ]
  end

  subgraph Infra["Infra partagée"]
    PG[(PostgreSQL)]
    RD[(Redis)]
    MX[MultiversX]
  end

  VEL -->|POST /seats/lock| Auth --> API --> RedisLua --> RD
  VEL -->|POST /webhooks/payment HMAC| PayWH --> Audit --> PG
  Audit --> Orch --> MintSvc
  MintSvc --> Paymaster --> GW --> MX
  GW --> SC
  Deploy --> GW
  Deploy --> PG
  Dash --> PG
  Orch --> Audit
  Listener --> MX
  Listener --> Recon --> PG
  Recon --> MQ --> Disp --> VEL
  Disp --> Retry
  Disp --> PG
  QR --> Dash
```

### Responsabilités par composant (détail)

#### BensCore

| Composant | Responsabilité |
| :--- | :--- |
| `CustomAuthFilter` | Clé API, IP whitelist, rate limiting, isolation multi-tenant |
| API lock/unlock | Verrou Redis Lua atomique (`SET NX PX 600000`) |
| `PaymentWebhookController` | Réception HMAC paiement € → `202 Accepted` |
| `TransactionAuditService` | INSERT `PENDING_MINT` / UPDATE `MINTED` + `tx_hash` |
| `MintOrchestrator` | Appelle BensContract, propage UUID transaction (idempotence) |
| Dashboard | CRUD `EVENT_BRIDGES` / `EVENT_SMART_RULES`, suivi `SECONDARY_SALES` |
| QR | Billet certifié (statique — scope POC SF-07) |

#### BensContract

| Composant | Responsabilité |
| :--- | :--- |
| DeployService | Déploie le SC, écrit `EVENT_BRIDGES.smart_contract_address` |
| MintService | Invoque `mint()` on-chain, retourne `tx_hash` à BensCore |
| Paymaster | Paie les gas fees (Account Abstraction) |
| Smart Wallet | Wallet acheteur créé sans action manuelle |
| Web3 Gateway | Point unique MultiversX (API + WebSocket) |
| Smart Contract | Royalties, plafond, SBT, `max_tickets_per_user` |

#### BensBridge

| Composant | Responsabilité |
| :--- | :--- |
| Écouteur blockchain | Capte Mint / Transfer / Resale |
| Réconciliation | Rapproche on-chain ↔ `TRANSACTION_AUDIT_LOGS` / `SECONDARY_SALES` |
| RabbitMQ outbox | Publie les succès à notifier au partenaire |
| WebhookDispatcher + Signer | Livraison HMAC sortante |
| RetryPolicy / DLQ | Retries 1s → 5s → 30s → 5min, puis DLQ |
| ReplayWorker | À définir (NFR-04) |

---

## 5. Diagramme de séquence — parcours acheteur (paiement → mint → réconciliation)

> Aligné sur les décisions actées (différent de l'ancien schéma du cahier des
> charges où Bridge recevait le paiement et mintait via RabbitMQ).

```mermaid
sequenceDiagram
    autonumber
    actor Acheteur
    participant Web2 as Partenaire Web2
    participant Core as BensCore
    participant Redis as Redis (Lua)
    participant PG as PostgreSQL
    participant Contract as BensContract<br/>(Paymaster)
    participant MX as MultiversX
    participant Bridge as BensBridge
    participant MQ as RabbitMQ

    Acheteur->>Web2: Sélection siège
    Web2->>Core: POST /seats/lock<br/>(event_id, external_seat_id, session_id)
    Core->>Redis: EVAL lock_seat.lua (SET NX PX 600000)
    alt Siège libre
        Redis-->>Core: 1
        Core-->>Web2: 200 OK + TTL 10 min
    else Déjà verrouillé
        Redis-->>Core: 0
        Core-->>Web2: 409 Conflict
    end

    Acheteur->>Web2: Paiement € (Stripe / PayPlug)
    Web2->>Core: POST /webhooks/payment (HMAC)
    Core->>PG: INSERT TRANSACTION_AUDIT_LOGS<br/>(PENDING_MINT, UUID tx)
    Core-->>Web2: 202 Accepted + transaction_id

    Core->>Contract: MintOrchestrator.mint(tx_id, …)
    Contract->>MX: mint() via Paymaster
    MX-->>Contract: blockchain_tx_hash
    Contract-->>Core: tx_hash
    Core->>PG: UPDATE status=MINTED + tx_hash

    Bridge->>MX: Écoute événement Mint
    Bridge->>PG: Réconciliation audit
    Bridge->>MQ: Publish bens.webhook.outbox
    MQ->>Bridge: Consume
    Bridge->>Web2: Webhook réconciliation (HMAC)
    Bridge->>PG: LOG WEBHOOK_DISPATCHES

    Note over Core,Acheteur: QR statique disponible (dashboard / billet certifié)
```

### Mapping des 10 étapes

| # | Étape | Composant | Écriture |
| ---: | :--- | :--- | :--- |
| 1 | Sélection du siège | Partenaire Web2 | — |
| 2 | `POST /seats/lock` | BensCore → Redis Lua | — (in-memory) |
| 3 | Verrou 200 OK + TTL 10 min | BensCore | — |
| 4 | Paiement € | Partenaire Web2 | — |
| 5 | Webhook paiement | **BensCore** | `TRANSACTION_AUDIT_LOGS` (`PENDING_MINT`) |
| 6 | Écriture audit | **BensCore** | idem |
| 7 | Ordre de mint | **BensCore → BensContract** | — (appel direct) |
| 8 | Mint NFT/SBT | BensContract → MultiversX | `TRANSACTION_AUDIT_LOGS` (`MINTED` + `tx_hash`) via BensCore |
| 9 | Webhook réconciliation | **BensBridge** (RabbitMQ outbox) | `WEBHOOK_DISPATCHES` |
| 10 | Billet + QR | BensCore | — |

### Parcours organisateur (configuration du bridge)

```text
1. Connexion Dashboard BENS (RBAC)
2. Création d'un EVENT_BRIDGES (status = DRAFT)
3. Saisie de external_event_id (opaque)
4. Configuration EVENT_SMART_RULES (1:1) :
   • token_type = NFT | SBT
   • max_resale_price_percent (BigDecimal)
   • royalty_fee_percent (BigDecimal)
   • max_tickets_per_user
   • allow_secondary_market
5. « Déployer & Activer »
6. BensCore orchestre → BensContract déploie le SC sur MultiversX
7. BensContract écrit smart_contract_address dans EVENT_BRIDGES
8. status → ACTIVE ; clés API + URL webhook déjà disponibles
   États : DRAFT → ACTIVE → PAUSED → ARCHIVED
```

### Captation de valeur (revente)

```mermaid
sequenceDiagram
    participant Buyer as Revendeur
    participant MX as MultiversX SC
    participant Bridge as BensBridge
    participant PG as PostgreSQL
    participant Dash as Dashboard organisateur

    Buyer->>MX: Transfer / Resale (prix ≤ plafond)
    MX->>MX: Calcule royalty + crédite organisateur
    Bridge->>MX: Détecte événement Resale
    Bridge->>PG: INSERT SECONDARY_SALES<br/>(resale_price, royalty_amount, tx_hash)
    Dash->>PG: Agrège royalties perçues
```

---

## 6. Persistance et flux de données

### 6.1. PostgreSQL partagé (middleware only)

| Table | Écrit par | Lu par | Rôle |
| :--- | :--- | :--- | :--- |
| `MERCHANTS` | BensCore | BensCore | Partenaires B2B |
| `COLLABORATORS` | BensCore | BensCore | RBAC dashboard |
| `BLACKLISTED_IPS` | BensCore | BensCore | Ban IP |
| `EVENT_BRIDGES` | BensCore + **BensContract** (adresse SC) | Tous | Bridge événement |
| `EVENT_SMART_RULES` | BensCore | BensCore / BensContract | Royalties, plafond, SBT |
| `TRANSACTION_AUDIT_LOGS` | **BensCore** | BensCore / BensBridge | Audit mint |
| `SECONDARY_SALES` | **BensBridge** | BensCore (dashboard) | Preuve captation de valeur |
| `WEBHOOK_DISPATCHES` | **BensBridge** | BensBridge | Retries webhooks sortants |

Détail MLD : [`mcd/mld.md`](./mcd/mld.md) · DDL : [`mcd/schema.sql`](./mcd/schema.sql)

> **Aucune** table `events`, `seats`, `categories`, `prices`, `orders`.  
> **Aucun** email de contrepartie dans `SECONDARY_SALES`.

### 6.2. Redis (verrous uniquement)

| Clé | TTL | Rôle |
| :--- | :--- | :--- |
| `lock:seat:{org_id}:{event_id}:{external_seat_id}` | 10 min | Verrou atomique |
| `lock:session:{org_id}:{session_id}` | 10 min | Index cleanup session |

- `org_id` / `event_id` = **UUID BENS** (jamais de slug partenaire).
- `external_seat_id` = VARCHAR opaque, non interprété.
- **0 écriture** PostgreSQL pendant le lock (NFR-03).

### 6.3. RabbitMQ (webhooks sortants uniquement)

| Élément | Valeur |
| :--- | :--- |
| Exchange | `bens.webhooks` (topic) |
| Queue | `bens.webhook.outbox` |
| DLQ | `bens.webhook.dlq` |
| Routing | `webhook.{org_id}.{event_id}` |
| Retry | 1s, 5s, 30s, 5min → DLQ après 4 tentatives |

> **Pas de queue de mint.** L'ordre de mint est un appel BensCore → BensContract.

---

## 7. Surface d'API partenaire (DX)

Surface volontairement contractée :

| Endpoint | Module | Rôle |
| :--- | :--- | :--- |
| `POST /seats/lock` | BensCore | Verrouiller → `200` + TTL ou `409` |
| `POST /seats/unlock` | BensCore | Libérer (compare-and-delete) |
| `POST /webhooks/payment` | **BensCore** | Paiement confirmé (HMAC) → `202` |
| `GET /transactions/{id}` | BensCore | Statut d'audit |
| Dashboard | BensCore (Angular) | Bridges, règles, royalties |

Codes d'erreur : `SEAT_ALREADY_LOCKED`, `SEAT_SOLD`, `MAX_TICKETS_REACHED`,
`INVALID_SIGNATURE`, `INVALID_API_KEY`, `IP_NOT_ALLOWED`, `RATE_LIMIT_EXCEEDED`.

Contrats détaillés : [`specifications.md`](./specifications.md) (US 1.7).

---

## 8. Diagramme de déploiement (cible POC — Sprint 6)

```mermaid
flowchart LR
  subgraph DockerCompose["docker-compose (POC)"]
    CoreC[bens-core:8080]
    ContractC[bens-contract:8081]
    BridgeC[bens-bridge:8082]
    PGC[(postgres:5432)]
    RDC[(redis:6379)]
    MQC[(rabbitmq:5672)]
  end

  Partner[Partenaire Web2] -->|HTTPS API / webhooks| CoreC
  Partner -->|HTTPS webhooks sortants| BridgeC
  CoreC --> PGC
  CoreC --> RDC
  CoreC -->|HTTP mint/deploy| ContractC
  ContractC --> PGC
  ContractC -->|RPC| MXNet[MultiversX network]
  BridgeC --> PGC
  BridgeC --> MQC
  BridgeC -->|WS/API| MXNet
```

| Service | Image / runtime | Ports | Dépendances |
| :--- | :--- | :--- | :--- |
| `bens-core` | JVM Spring Boot 4 | 8080 | PostgreSQL, Redis, BensContract |
| `bens-contract` | Rust binary | 8081 | PostgreSQL, MultiversX |
| `bens-bridge` | JVM Spring Boot 4 | 8082 | PostgreSQL, RabbitMQ, MultiversX |
| `postgres` | PostgreSQL 16 | 5432 | Volume `pgdata` |
| `redis` | Redis 7 | 6379 | — |
| `rabbitmq` | RabbitMQ 3 + management | 5672 / 15672 | — |

> Dockerisation livrée en **US 5.7** (Sprint 6). En développement local, chaque
> module tourne hors conteneur avec les mêmes dépendances.

---

## 9. Sécurité transverse

| Couche | Mécanisme |
| :--- | :--- |
| API B2B | `X-BENS-Api-Key` hashée, IP whitelist, rate limiting |
| Webhooks entrants / sortants | Signature HMAC |
| Dashboard | JWT + RBAC (`ADMIN`, `CLIENT_SUCCESS`, `READ_ONLY`) |
| Secrets | Jamais en clair en BDD / logs (clés API, secrets webhook, clés chain) |
| Idempotence | UUID transaction + `UNIQUE(payment_provider_ref)` + `UNIQUE(blockchain_tx_hash)` sur reventes |
| Isolation multi-tenant | `organization_id` / `merchant_id` déduit de la clé API |

---

## 10. Qualité non fonctionnelle (rappel)

| ID | Cible | Composant |
| :--- | :--- | :--- |
| NFR-01 | Verrou < 2 ms | BensCore / Redis |
| NFR-02 | ≥ 10 000 req/s | BensCore |
| NFR-03 | 0 écriture SQL pendant le lock | BensCore |
| NFR-04 | Replay / tolérance aux pannes | BensBridge |
| NFR-05 | Idempotence UUID | BensCore / BensBridge |
| NFR-06 | Indisponibilité Web3 non bloquante pour l'achat € | BensCore (mint après `202`) |

---

## 11. Traçabilité US 1.7

| Critère d'acceptation | Couverture |
| :--- | :--- |
| 3 modules + responsabilités | § 2 |
| PostgreSQL partagé, Redis, RabbitMQ sortants | §§ 2, 6 |
| Appel BensCore → BensContract pour le mint | §§ 2, 5 |
| Diagramme de composants | § 4 |
| Diagramme de séquence paiement → mint → webhook | § 5 |
| Diagramme de déploiement | § 8 |
| Alignement décisions actées | § 2 (tableau) |

---

## 12. Ressources liées

- Vision : [`../01-contexte/vision.md`](../01-contexte/vision.md)
- Expression de besoin : [`../01-contexte/expression-besoin.md`](../01-contexte/expression-besoin.md)
- Cahier des charges : [`../01-contexte/cahier-des-charges.md`](../01-contexte/cahier-des-charges.md)
- Spécifications : [`specifications.md`](./specifications.md)
- MCD / MLD : [`mcd/mcd.md`](./mcd/mcd.md) · [`mcd/mld.md`](./mcd/mld.md)
- Planning : [`../02-gestion-projet/planning.md`](../02-gestion-projet/planning.md)
- Ticket : [US 1.7 #39](https://github.com/2025-10-CDA-ECO-P6/bens/issues/39)
