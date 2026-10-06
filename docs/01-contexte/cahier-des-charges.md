# Cahier des Charges Fonctionnel et Technique — Projet BENS

> **Référence US 1.1** — Documenter l'Expression du Besoin et l'Architecture distribuée  
> **Principe transverse** : BENS est un *middleware* transmetteur de messages et forgeur de NFT/SBT — **jamais une billetterie bis**. La donnée métier billetterie (catalogue, sièges, prix, panier, checkout) reste chez le partenaire Web2.

---

## 0. Expression du besoin

### 0.1. Contexte

Les billetteries B2B partenaires (Ticketmaster, Digitick, Fnac Spectacles, etc.) gèrent déjà le catalogue d'événements, le plan de salle, le panier et le paiement en euros. Elles ont besoin d'une couche d'interopérabilité pour :

1. **Capter la valeur du marché secondaire** : ancrer des royalties de revente dans un smart contract MultiversX afin que l'organisateur capte automatiquement une part de chaque revente, à perpétuité — valeur structurellement inaccessible en Web2 (part aujourd'hui aux scalpers et plateformes de revente).
2. **Gouverner le marché secondaire** : plafond de prix, SBT non transférable optionnel, limite par utilisateur, anti-bot — l'organisateur reprend le contrôle.
3. **Certifier le billet** sur blockchain (MultiversX) de façon transparente pour l'acheteur final.
4. **Absorber les pics de charge** (drops) sans saturer leur BDD SQL — enableur technique, non l'argument produit principal.

### 0.2. Objectif produit

BENS (Blockchain Event Network System) est un **middleware API-First, stateless, event-driven** qui :

- ancre les règles de revente (plafond, royalties, SBT) dans un smart contract MultiversX déployé via Bens Contracts, et paie les gas fees via Paymaster (Account Abstraction) ;
- verrouille des sièges **en mémoire** (Redis + Lua) à partir d'identifiants opaques fournis par le partenaire ;
- reçoit la confirmation de paiement € via webhook, l'audite, et ordonne le mint asynchrone ;
- forge un NFT/SBT sur MultiversX via Bens Contracts (Paymaster) ;
- réconcilie le partenaire via webhook sortant signé HMAC ;
- expose un dashboard organisateur pour configurer un *bridge* d'événement et déployer le smart contract.

### 0.3. Hors scope (discipline middleware)

BENS **ne doit pas** :

| Interdit | Raison |
| :--- | :--- |
| Stocker le catalogue d'événements / catégories / descriptions | Donnée partenaire Web2 |
| Stocker plans de sièges, labels (`section`, `row`, `seat_number`) | Seul `external_seat_id` opaque est transporté |
| Gérer panier, checkout, paiement € | Responsabilité partenaire (Stripe/PayPlug) |
| Calculer un total ou un prix facial | Seul `payment_provider_ref` est audité |
| Exposer un `GET /events` catalogue | Dérive billetterie bis |
| Demander au partenaire de connaître MultiversX | DX : intégration en quelques heures |

**Règle de décision (3 questions)** avant toute nouvelle table / endpoint / entité :

1. Cette donnée existe-t-elle déjà chez le partenaire Web2 ? → Si oui, BENS ne la stocke pas (identifiant opaque uniquement).
2. Sert-elle à transmettre un message ou à forger un NFT ? → Sinon, hors scope.
3. Sans elle, BENS peut-il encore certifier le billet ? → Si oui, c'est du métier billetterie → laisser au partenaire.

---

## 1. Architecture distribuée — 3 microservices

### 1.1. Vue d'ensemble

```text
┌─────────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│     BENS Core       │     │   BENS Contracts     │     │    BENS Bridge      │
│  (orchestration     │     │  (smart contracts    │     │  (réconciliation    │
│   API + Redis +     │◄───►│   MultiversX +       │◄───►│   Web2 ↔ Web3       │
│   Dashboard Angular)│     │   Paymaster)         │     │   RabbitMQ + worker)│
└──────────▲──────────┘     └──────────────────────┘     └──────────▲──────────┘
           │                                                         │
           │  lock / unlock / QR / config                            │  webhooks
           │                                                         │
    ┌──────┴──────┐                                           ┌──────┴──────┐
    │ Partenaire  │                                           │ Partenaire  │
    │ Web2 (API)  │                                           │ Web2 (WH)   │
    └─────────────┘                                           └─────────────┘
```

| Microservice | Responsabilité | Persistance |
| :--- | :--- | :--- |
| **BENS Core** | API publique partenaire (`/seats/lock`, `/seats/unlock`, statut), verrou Redis Lua, QR dynamique, dashboard organisateur (RBAC), configuration des bridges | PostgreSQL (config middleware) + Redis (verrous) |
| **BENS Contracts** | Déploiement et exécution des smart contracts MultiversX, Paymaster (gas fees), règles ancrées (plafond, royalties, SBT) | Blockchain MultiversX |
| **BENS Bridge** | Webhooks entrants (paiement), audit logs, publication RabbitMQ, worker de mint, webhooks sortants de réconciliation, replay | PostgreSQL (`TRANSACTION_AUDIT_LOGS`, `WEBHOOK_DISPATCHES`) + RabbitMQ |

### 1.2. Stack technique complète

| Couche | Technologie | Version / contrainte |
| :--- | :--- | :--- |
| Langage backend | **Java** | **25** (LTS) — Virtual Threads (Project Loom) |
| Framework backend | **Spring Boot** | **4+** |
| Frontend dashboard | **Angular** | **18+** |
| Blockchain | **MultiversX** / **Rust** | Smart Contracts + Account Abstraction (Paymaster) |
| Cache / verrous | **Redis** | Cluster + scripts Lua atomiques |
| Broker de messages | **RabbitMQ** | Exchange topic + DLQ |
| Base relationnelle | **PostgreSQL** | Audit, config bridges, webhooks (UUID PK) |
| Style | Middleware API-First, **stateless**, Event-Driven (EDA) | — |

> Cette stack est **normative** pour le dépôt. Toute divergence doit être justifiée et mise à jour ici.

### 1.3. Surface d'API partenaire (DX)

Surface volontairement contractée — **pas plus** :

| Endpoint | Rôle | Composant |
| :--- | :--- | :--- |
| `POST /seats/lock` | Verrouiller un siège (`event_id`, `seat_id`, `session_id`) → `200` + TTL ou `409` | BENS Core |
| `POST /seats/unlock` | Libérer un siège (compare-and-delete) | BENS Core |
| `POST /webhooks/payment` | Webhook paiement confirmé (signé HMAC) → `202 Accepted` | BENS Bridge |
| `GET /transactions/{id}` | Statut d'une transaction d'audit | BENS Bridge / Core |
| Dashboard organisateur | Configurer bridge + déployer SC | BENS Core (Angular) |

Codes d'erreur explicites : `SEAT_ALREADY_LOCKED`, `SEAT_SOLD`, `MAX_TICKETS_REACHED`, `INVALID_SIGNATURE`, etc.

Identifiants :

- `org_id`, `event_id` : **UUID BENS** (jamais un slug partenaire dans Redis).
- `external_event_id`, `external_seat_id` : **VARCHAR opaques** fournis par le partenaire, non interprétés.

---

## 2. Diagramme de séquence inter-services

### 2.1. Achat + certification (Core ↔ Bridge ↔ Contracts)

```mermaid
sequenceDiagram
    autonumber
    actor Acheteur
    participant Web2 as Partenaire Web2
    participant Core as BENS Core
    participant Redis as Redis (Lua)
    participant Bridge as BENS Bridge
    participant MQ as RabbitMQ
    participant SC as BENS Contracts<br/>(MultiversX + Paymaster)
    participant PG as PostgreSQL

    Acheteur->>Web2: Sélection siège
    Web2->>Core: POST /seats/lock<br/>(event_id, seat_id, session_id)
    Core->>Redis: EVAL lock_seat.lua (SET NX PX 600000)
    alt Siège libre
        Redis-->>Core: 1
        Core-->>Web2: 200 OK + TTL 10 min
    else Siège déjà verrouillé
        Redis-->>Core: 0
        Core-->>Web2: 409 Conflict
    end

    Acheteur->>Web2: Paiement € (Stripe / PayPlug)
    Web2->>Bridge: POST /webhooks/payment (HMAC)
    Bridge->>PG: INSERT TRANSACTION_AUDIT_LOGS<br/>(PENDING_MINT, UUID tx)
    Bridge-->>Web2: 202 Accepted
    Bridge->>MQ: Publish mint.{org_id}.{event_id}

    MQ->>Bridge: Consumes bens.mint.requests
    Bridge->>SC: Mint NFT/SBT via Paymaster
    SC-->>Bridge: blockchain_tx_hash
    Bridge->>PG: UPDATE status=MINTED + tx_hash
    Bridge->>Web2: Webhook réconciliation (HMAC)<br/>+ log WEBHOOK_DISPATCHES
    Bridge->>Core: Billet certifié disponible
    Core-->>Acheteur: QR Code dynamique
```

### 2.2. Mapping des 10 étapes du parcours acheteur

| # | Étape | Composant | Écriture MLD |
| ---: | :--- | :--- | :--- |
| 1 | Sélection du siège | Partenaire Web2 | — |
| 2 | `POST /seats/lock` | BENS Core (Redis Lua) | — (in-memory) |
| 3 | Verrou 200 OK + TTL 10 min | BENS Core | — |
| 4 | Paiement € | Partenaire Web2 | — |
| 5 | Webhook paiement → BENS | BENS Bridge | `TRANSACTION_AUDIT_LOGS` (`PENDING_MINT`) |
| 6 | Écriture audit log | BENS Bridge | `TRANSACTION_AUDIT_LOGS` |
| 7 | Publication ordre de mint | BENS Bridge → RabbitMQ | — |
| 8 | Worker : mint NFT/SBT | BENS Bridge → BENS Contracts | `TRANSACTION_AUDIT_LOGS` (`MINTED` + `tx_hash`) |
| 9 | Webhook réconciliation → partenaire | BENS Bridge | `WEBHOOK_DISPATCHES` |
| 10 | Billet + QR dynamique | BENS Core | — |

### 2.3. Parcours organisateur (configuration du bridge)

```text
1. Connexion Dashboard BENS (RBAC)
2. Création d'un Bridge d'événement
3. Saisie de external_event_id (opaque)
4. Configuration EventContractConfig (`EVENT_CONTRACT_CONFIGS`) :
   • token_type = NFT | SBT
   • max_resale_price_percent (BigDecimal)
   • royalty_fee_percent (BigDecimal)
   • max_tickets_per_user
   • allow_secondary_market
5. « Déployer & Activer »
6. BENS Contracts déploie le SC sur MultiversX
7. Génération API keys + URL webhook de callback
   États bridge : DRAFT → ACTIVE → PAUSED → ARCHIVED
```

---

## 3. Spécifications Fonctionnelles (SF)

### 3.1. Module de réservation in-memory (Drop & High-Concurrency)

| ID | Intitulé | Critère mesurable | Composant | Table MLD |
| :--- | :--- | :--- | :--- | :--- |
| **SF-01** | Verrouillage de siège (Redis Lua) | Verrou en mémoire **< 2 ms**, script Lua atomique | BENS Core (Redis) | — (in-memory) |
| **SF-02** | Expiration automatique (TTL) | TTL **10 min** exactes, libération sans altération de données | BENS Core (Redis) | — (in-memory) |
| **SF-03** | Anti-overbooking | 1er → **200 OK**, 2e → **409 Conflict** | BENS Core (API) | — |

#### 3.1.1. Spécification du flux de réservation atomique (Lock Redis)

**Convention de clés** (aucun slug / nom d'événement devinable) :

| Clé Redis | Rôle |
| :--- | :--- |
| `lock:seat:{org_id}:{event_id}:{external_seat_id}` | Verrou d'un siège |
| `lock:session:{org_id}:{session_id}` | Index de cleanup par session |
| `queue:event:{org_id}:{event_id}` | File d'attente drop (optionnel) |

`org_id` et `event_id` sont des **UUID BENS**. `external_seat_id` est transporté tel quel (pas de table pivot).

**Script Lua de verrouillage** (`SET NX PX 600000`) :

```lua
-- KEYS[1] = lock:seat:{org_id}:{event_id}:{external_seat_id}
-- ARGV[1] = session_id
-- ARGV[2] = ttl_ms (600000)
if redis.call('EXISTS', KEYS[1]) == 1 then
  return 0  -- → 409 Conflict
end
redis.call('SET', KEYS[1], ARGV[1], 'PX', ARGV[2])
return 1    -- → 200 OK
```

**Unlock** : compare-and-delete atomique (seul le propriétaire `session_id` peut libérer).

**Isolation BDD (NFR-03)** : **0 écriture** PostgreSQL / BDD partenaire pendant sélection et verrouillage.

### 3.2. Module de certification et Smart Contracts (Web3 masqué)

| ID | Intitulé | Critère mesurable | Composant | Table MLD |
| :--- | :--- | :--- | :--- | :--- |
| **SF-05** | Mint asynchrone | Ordre de mint publié après paiement €, **non bloquant** | BENS Bridge (RabbitMQ) | `TRANSACTION_AUDIT_LOGS` |
| **SF-06** | Masquage blockchain (Paymaster) | Smart Wallet + gas fees payés par BENS | BENS Contracts (Paymaster) | — |
| **SF-07** | QR Code dynamique | Rafraîchissement périodique, anti-capture écran | BENS Core (QR) | — |

**Topologie RabbitMQ** :

- Exchange : `bens.mint` (topic)
- Queue : `bens.mint.requests` + DLQ `bens.mint.dlq`
- Routing key : `mint.{org_id}.{event_id}`
- Retry exponentiel : 1s, 5s, 30s, 5min → DLQ après 4 tentatives

### 3.3. Module Dashboard Organisateur & Marché Secondaire

| ID | Intitulé | Critère mesurable | Composant | Table MLD |
| :--- | :--- | :--- | :--- | :--- |
| **SF-09** | Règles de revente | Plafond prix + royalties configurables | BENS Contracts (SC) | `EVENT_CONTRACT_CONFIGS` |
| **SF-10** | SBT intransférables (optionnel) | Forçage SBT possible par organisateur | BENS Contracts (SC) | `EVENT_CONTRACT_CONFIGS` |

#### 3.3.1. Règles de calcul des royalties B2B (`BigDecimal`)

Tous les pourcentages monétaires sont des **`DECIMAL(5,2)` / `BigDecimal`** (jamais `float` / `double`) pour éviter les erreurs d'arrondi.

| Colonne MLD (`EVENT_CONTRACT_CONFIGS`) | Type | Domaine | Rôle |
| :--- | :--- | :--- | :--- |
| `max_resale_price_percent` | `DECIMAL(5,2)` | `0.00` → `200.00` | Plafond de revente (% du prix facial) |
| `royalty_fee_percent` | `DECIMAL(5,2)` | `0.00` → `50.00` | Royalties organisateur (% du prix de revente) |
| `token_type` | `VARCHAR` | `NFT` \| `SBT` | Transférable ou Soulbound |
| `allow_secondary_market` | `BOOLEAN` | — | Forcé `false` si `token_type = SBT` |
| `max_tickets_per_user` | `INTEGER` | défaut `4` | Anti-bot |

**Formules (arithmétique exacte `BigDecimal`)** :

```text
plafond_revente  = prix_facial × (max_resale_price_percent / 100)
                   avec scale = 2, RoundingMode.HALF_UP
                   contrainte : prix_revente ≤ plafond_revente

royalty          = prix_revente × (royalty_fee_percent / 100)
                   avec scale = 2, RoundingMode.HALF_UP
                   créditée automatiquement à l'organisateur via le SC
```

**Exemples** :

| Prix facial | Plafond % | Revente max | Royalty % | Royalty sur revente 100 € |
| ---: | ---: | ---: | ---: | ---: |
| 100,00 € | 100,00 | 100,00 € | 10,00 | 10,00 € |
| 80,00 € | 150,00 | 120,00 € | 5,00 | 6,00 € (si revente à 120 €) |

Flux de revente : vérification plafond → paiement → réémission billet (burn+mint ou transfer) → royalties SC → webhook réconciliation → QR régénéré (SF-07).

---

## 4. Spécifications Non-Fonctionnelles (NFR)

### 4.1. Performance & Scalabilité

| ID | Intitulé | Critère mesurable | Composant |
| :--- | :--- | :--- | :--- |
| **NFR-01** | Latence clic | Réponse API verrouillage **< 2 ms** à charge nominale | BENS Core (Redis) |
| **NFR-02** | Débit | **≥ 10 000 req/s** sur instance standard sans saturation CPU/RAM | BENS Core (API) |
| **NFR-03** | Isolation BDD | **0 écriture** BDD SQL partenaire pendant sélection/verrouillage | BENS Core (Redis) |

### 4.2. Résilience, idempotence & disponibilité

| ID | Intitulé | Critère mesurable | Composant |
| :--- | :--- | :--- | :--- |
| **NFR-04** | Tolérance aux pannes | Worker de secours rejoue via `transaction_audit_logs` | BENS Bridge (replay) |
| **NFR-05** | Idempotence | Webhooks + ordres de mint idempotents (**UUID transaction unique**) | BENS Bridge |
| **NFR-06** | Disponibilité Web3 | Indisponibilité Web3 **ne bloque jamais** l'achat € | BENS Bridge (mint async) |

**Idempotence** :

- UUID de transaction généré à la réception du webhook paiement, propagé jusqu'au mint et au webhook sortant.
- `TRANSACTION_AUDIT_LOGS.payment_provider_ref` est **UNIQUE**.
- Avant mint : si `blockchain_tx_hash` déjà présent → pas de re-mint, rejeu du webhook uniquement.

---

## 5. Persistance middleware (aperçu MLD)

Tables **propres au middleware** uniquement (détail : `docs/03-conception/mcd/mld.md`) :

| Table MLD | Entité MCD | Rôle |
| :--- | :--- | :--- |
| `MERCHANTS` | Merchant | Partenaires B2B |
| `COLLABORATORS` | Collaborator | Accès dashboard (RBAC) |
| `BLACKLISTED_IPS` | BlacklistedIp | Ban IP multi-tenant |
| `EVENT_CONTRACT_CONFIGS` | EventContractConfig | Bridge événement + règles SC |
| `TRANSACTION_AUDIT_LOGS` | TransactionAuditLog | Audit paiement → mint |
| `WEBHOOK_DISPATCHES` | *(technique)* | File + retries webhooks sortants |

**Aucune** table `events`, `seats`, `categories`, `prices`, `orders` côté BENS.

---

## 6. Critères d'acceptation et recette (dossier CDA)

| Fonctionnalité | Test de recette | Résultat attendu | Specs |
| :--- | :--- | :--- | :--- |
| **Bench de charge** | k6 : 5 000 clics simultanés sur 1 000 sièges | 1 000 × `200 OK`, 4 000 × `409`, **0 overbooking**, p99 < 2 ms | SF-03, NFR-01, NFR-02 |
| **Crash-test Redis** | Interruption Redis pendant un drop | Aucune transaction financière corrompue ; annulation propre des paniers non payés | NFR-04 |
| **Revente marché secondaire** | Revente entre deux utilisateurs | Billet réémis, royalties créditées à l'organisateur | SF-09, SF-10 |

### Checklist de conformité (avant clôture d'un ticket)

- [ ] Verrouillage < 2 ms (NFR-01)
- [ ] 0 écriture BDD partenaire pendant le verrou (NFR-03)
- [ ] Webhooks idempotents via UUID (NFR-05)
- [ ] Mint asynchrone, achat € non bloqué (NFR-06)
- [ ] Replay possible via `transaction_audit_logs` (NFR-04)
- [ ] TTL verrou = 10 minutes (SF-02)
- [ ] Concurrent → 200 / 409 (SF-03)
- [ ] Plafond + royalties ancrés dans le SC en `BigDecimal` (SF-09)
- [ ] SBT configurable (SF-10)
- [ ] QR rafraîchi périodiquement (SF-07)
- [ ] Aucun concept billetterie (catalogue, sièges, panier, prix) stocké côté BENS

### Anti-patterns à proscrire

- ❌ Écriture synchrone PostgreSQL pendant le verrouillage
- ❌ Mint synchrone bloquant l'achat €
- ❌ Webhook sortant sans UUID de transaction
- ❌ Slug / nom d'événement dans une clé Redis
- ❌ Secret / clé privée blockchain en clair (BDD, logs)
- ❌ Réémission sans vérifier plafond et royalties
- ❌ Recréer catalogue, plan de sièges, panier, checkout côté BENS

---

## 7. Traçabilité US 1.1

| Critère d'acceptation US 1.1 | Couverture dans ce document |
| :--- | :--- |
| Cahier des charges + diagramme de séquence Core ↔ Contracts ↔ Bridge | §§ 0–2 (expression du besoin, architecture 3 MS, Mermaid § 2.1) |
| Spec flux réservation atomique (Lock Redis) + royalties B2B (`BigDecimal`) | §§ 3.1.1 et 3.3.1 |
| Stack complète Java 21, Spring Boot 3.4+, Angular 18+, MultiversX, Redis, RabbitMQ | § 1.2 |
| Documentation validée dans `/docs` | `docs/01-contexte/cahier-des-charges.md` |

---

## 8. Ressources liées

- MLD : `docs/03-conception/mcd/mld.md`
- Planning : `docs/02-gestion-projet/planning.md`
- Skills projet : `bens-conformite-cahier-des-charges`, `bens-middleware-discipline`, `bens-redis-verrou-lua`, `bens-bridge-web2-web3`, `bens-marche-secondaire`
