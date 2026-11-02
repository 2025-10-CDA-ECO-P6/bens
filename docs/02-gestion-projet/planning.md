# Planning — Projet BENS (POC)

## Contexte

Aligné sur le board GitHub
[`2025-10-CDA-ECO-P6/bens`](https://github.com/2025-10-CDA-ECO-P6/bens/issues).

### Modules (3)

- **`BensCore`** (Spring Boot 4.1, Java 25 LTS Virtual Threads) : verrouillage Redis
  (Lua), API B2B (CustomAuthFilter : clés API, IP whitelist, rate limiting),
  **réception webhook paiement**, **seul accès PostgreSQL**,
  **écriture/maj `TRANSACTION_AUDIT_LOGS`** (`PENDING_MINT` → hash → `MINTED`),
  **orchestration du mint** (appelle BensContract), **consommation RabbitMQ**
  (rythme contrôlé), **webhooks sortants HMAC**, Dashboard, QR statique,
  socle générique, MapStruct.
- **`BensContract`** (Rust + SDK MultiversX) : **crée/définit les SC**,
  **déploie** sur MultiversX, **retourne** adresse SC à Core (pas de BDD),
  **lance le mint**, **Paymaster**, Smart Wallet, Web3 Gateway.
- **`BensBridge`** (Spring Boot) : **écoute on-chain uniquement**
  (Mint/Transfer/Resale), **publie** les confirmations dans RabbitMQ.
  Pas de PostgreSQL, pas de webhook partenaire.

> BensCore appelle BensContract pour minter. Bridge publie les confirmations
> on-chain ; Core consomme → `MINTED` + notifie. RabbitMQ = Bridge → Core
> (confirmations), pas pour soumettre le mint. PostgreSQL = Core seul.

### Scope POC

| Spec | Statut | Composant |
| --- | --- | --- |
| SF-01 Verrou Redis Lua < 2 ms | inclus | BensCore |
| SF-02 TTL 10 min | inclus | BensCore |
| SF-03 Anti-overbooking 200/409 | inclus | BensCore |
| SF-05 Mint asynchrone | inclus | BensCore (orchestre) → BensContract (mint) |
| SF-06 Paymaster / Account Abstraction | inclus | BensContract |
| SF-07 QR code | partiel (statique) | BensCore |
| SF-09 Règles de revente | inclus | BensContract (SC) + BensCore (dashboard) |
| SF-10 SBT intransférable | inclus | BensContract |
| NFR-01 à NFR-06 | inclus | BensCore / BensBridge |

### Discipline middleware

BENS = middleware transmetteur de messages + forgeur de NFT, jamais billetterie
bis. Aucune table `events/seats/categories/prices/orders` côté BENS.

### Stack

Java 25 (LTS) / Spring Boot 4.1 (Virtual Threads) / Angular 21 (LTS) / Redis /
RabbitMQ / PostgreSQL 17 / MultiversX (Rust SC). Monorepo Maven 3 modules.
Pas de Flyway (`schema.sql` + Spring Boot). Dockerisation en Sprint 6.

---

## Conventions

```
US X.Y — Titre
En tant que <rôle>, je veux <action>, afin de <bénéfice>.

Critères d'acceptation :
- critère 1
- critère 2

Specs : SF-xx, NFR-xx
Composant : <module>
Estimation : <points>
Ticket GitHub : #<numéro> | à créer
```

### Rôles

- **Acheteur** : utilisateur final côté billetterie Web2 partenaire
- **Développeur partenaire** : intègre l'API BENS dans sa billetterie Web2
- **Organisateur** : configure un bridge et déploie un SC via le dashboard
- **Admin BENS** : gère les organisations et utilisateurs (RBAC)
- **Système** : composant interne (worker, gateway, SC)

---

## Vue d'ensemble des milestones

| Milestone | Sprint(s) | Période | Module(s) | US | Tickets |
| --- | --- | --- | --- | --- | --- |
| M1 — Cadrage Métier & Sécurité B2B | S1 & S2 | 01/09 — 12/10/2026 | BensCore | US 1.1 à 1.8 | #1 à #6, #39, #40 |
| M2 — Performance & Anti-Crash | S3 | 13/10 — 02/11/2026 | BensCore | US 2.1 à 2.4 | #7 à #10 |
| M3 — Cœur Métier Web3 & Royalties | S4 | 03/11 — 23/11/2026 | BensContract | US 3.1 à 3.6 | #11, #12 + 4 à créer |
| M4 — Mint, Audit & Notifications Asynchrones | S5 | 24/11 — 14/12/2026 | BensCore + BensBridge | US 4.1 à 4.10 | 10 à créer |
| M5 — Marché secondaire, Recette & Clôture CDA | S6 | 15/12/2026 — 15/01/2027 | Tous | US 5.1 à 5.8 | 8 à créer |

**Total : 36 US** (14 créées sur GitHub côté M1/M2, reste à synchroniser pour M3–M5).

---

# M1 — Cadrage Métier & Sécurité B2B [BENS CORE]

Sprints 1 & 2 (01/09 — 12/10/2026). Poser l'architecture, la BDD, le socle
générique, les entités métier et les contrôles d'accès B2B.

## US 1.1 — Documenter l'Expression du Besoin et l'Architecture distribuée

En tant que **développeur**, je veux documenter l'expression de besoin et
cadrer le projet (cahier des charges), afin de préparer la certification CDA.

Critères d'acceptation :
- `expression-besoin.md` : contexte métier, rôles, parcours acheteur 10 étapes
- `vision.md` : pitch produit (captation de valeur marché secondaire)
- `cahier-des-charges.md` : SF/NFR, stack, discipline middleware
- Note : `architecture.md` et `specifications.md` sont repris dans **US 1.7**

Specs : —
Composant : docs
Estimation : 5
Ticket GitHub : #1

## US 1.2 — Réaliser la modélisation conceptuelle et logique de la BDD (MCD/MLD)

En tant que **développeur**, je veux produire le MCD et le MLD des 6 tables BENS,
afin de définir le schéma PostgreSQL (accès BensCore uniquement).

Critères d'acceptation :
- MCD : `ORGANIZATIONS`, `USERS`, `EVENT_BRIDGES`, `EVENT_SMART_RULES`,
  `TRANSACTION_AUDIT_LOGS`, `WEBHOOK_DISPATCHES` + relations
- MLD : PK/FK/UNIQUE, dont `UNIQUE(organization_id, external_event_id)` sur
  `EVENT_BRIDGES` et `UNIQUE(payment_provider_ref)` sur `TRANSACTION_AUDIT_LOGS`
- Vocabulaire : Merchant ↔ `ORGANIZATIONS`, Collaborator ↔ `USERS`
- Aucune table `events/seats/categories/prices/orders`

Specs : —
Composant : docs / BensCore
Estimation : 3
Ticket GitHub : #2

## US 1.3 — Créer les scripts de seed (test)

En tant que **développeur**, je veux des scripts de seed, afin de disposer de
jeux de données reproductibles.

Critères d'acceptation :
- `schema.sql` chargé par **BensCore** au démarrage
  (`spring.sql.init.mode=always`, `CREATE TABLE IF NOT EXISTS` idempotent)
- `data.sql` : organisations, utilisateurs, bridges, règles de test
- BensCore est responsable du `schema.sql` ; BensContract et BensBridge
  **n'ont pas** d'accès PostgreSQL
- Scripts idempotents

Specs : —
Composant : BensCore
Estimation : 3
Ticket GitHub : #3

## US 1.4 — Mettre en place le socle générique réutilisable (BaseEntity, BaseRepository, BaseController)

En tant que **développeur**, je veux un socle générique + monorepo Maven 3
modules, afin de partager le build et isoler les responsabilités.

Critères d'acceptation :
- 3 modules compilent avec `./mvnw clean install`
- `pom.xml` parent (Spring Boot 4.1, Java 25 LTS) ; `BensContract` = workspace Cargo
- `BaseEntity` (id UUID, created_at, updated_at), `BaseRepository`,
  `BaseController` (CRUD générique) réutilisables
- `.gitignore` (target/, IDE, .env)

Specs : —
Composant : racine / BensCore
Estimation : 5
Ticket GitHub : #4

## US 1.5 — Développer les entités Merchant et Collaborator, leurs DTOs et le mapper MapStruct

En tant qu'**admin BENS**, je veux des entités Merchant et Collaborator avec
DTOs et mapper MapStruct, afin de gérer les organisations et utilisateurs (RBAC).

Critères d'acceptation :
- `ORGANIZATIONS` (Merchant) : id, name, api_key_hash, webhook_url,
  ip_whitelist, is_active, created_at
- `USERS` (Collaborator) : id, organization_id, email, password_hash (BCrypt),
  role ∈ {ADMIN, CLIENT_SUCCESS, READ_ONLY}, is_active
- DTOs création/lecture + mapper MapStruct
- Auth retourne JWT ; `is_active = false` bloque l'accès

Specs : —
Composant : BensCore
Estimation : 5
Ticket GitHub : #5

## US 1.6 — Implémenter le filtre de sécurité B2B CustomAuthFilter

En tant que **développeur partenaire**, je veux m'authentifier via API Key et
être filtré par IP whitelist, afin d'appeler les endpoints en toute sécurité.

Critères d'acceptation :
- Header `X-BENS-Api-Key` vérifié contre `ORGANIZATIONS.api_key_hash` ; sinon
  `401` + `{ "code": "INVALID_API_KEY" }`
- `api_key` générée, hash stocké (jamais en clair), affichée une fois
- `webhook_url` de callback exposée au partenaire
- IP whitelist par organisation ; hors whitelist `403` +
  `{ "code": "IP_NOT_ALLOWED" }`
- `organization_id` déduit de la clé → contexte requête (isolation
  multi-tenant)
- Filtrage sur `/api/`* et `/webhooks/*`

Specs : —
Composant : BensCore
Estimation : 5
Ticket GitHub : #6

## US 1.7 — Rédiger architecture.md et specifications.md

En tant que **développeur / apprenant CDA**, je veux documenter l'architecture
technique et les spécifications détaillées, afin de compléter le livrable de
conception oublié (`architecture.md`) et formaliser le contrat technique
(`specifications.md`).

Critères d'acceptation :
- `architecture.md` : 3 modules, PostgreSQL Core seul, Redis, RabbitMQ
  (confirmations Bridge → Core), appel BensCore → BensContract pour le mint
- Diagrammes : composants, séquence (paiement → mint → conf → webhook),
  déploiement
- `specifications.md` : contrats API partenaire (lock/unlock/payment/statut),
  payloads, codes d'erreur, mapping SF/NFR, conventions Redis/RabbitMQ
- Règles marché secondaire (plafond, royalties `BigDecimal`, SBT) documentées
- Aligné sur les décisions actées (Paymaster dans BensContract, Bridge =
  écoute + publish MQ, Core = BDD + webhooks)

Specs : —
Composant : docs
Estimation : 5
Ticket GitHub : #39

## US 1.8 — Réaliser les wireframes du dashboard organisateur

En tant qu'**organisateur**, je veux disposer de wireframes du dashboard BENS,
afin de valider l'UX avant le développement Angular (CRUD bridges, règles SC,
suivi des royalties).

Critères d'acceptation :
- Wireframes dans `docs/03-conception/maquettes/` : vue d'ensemble, liste
  bridges, config bridge (brouillon existant `screen.png`), webhooks & logs,
  membres & rôles, clés API, marché secondaire / royalties perçues
- Zones d'interaction visibles (NFT/SBT, plafond, royalties, quota)
- Cohérence MLD (`EVENT_BRIDGES`, `EVENT_SMART_RULES`, `SECONDARY_SALES`)
- Aucun écran catalogue / plan de salle / panier (discipline middleware)
- Index `maquettes/README.md`

Specs : SF-09, SF-10
Composant : docs / BensCore (dashboard)
Estimation : 3
Ticket GitHub : #40

---

# M2 — Performance & Anti-Crash [BENS CORE]

Sprint 3 (13/10 — 02/11/2026). Virtual Threads, Redis, verrous Lua, rate limiting.

## US 2.1 — Activer les Threads Virtuels Java 25 dans Spring Boot 4.1

En tant que **développeur**, je veux activer les Virtual Threads et connecter
Redis, afin de préparer la haute concurrence.

Critères d'acceptation :
- `java.threads.virtual=true` (Project Loom)
- Pool Redis configuré (Lettuce/Jedis)
- Healthcheck Redis via Actuator
- Test de charge basique : montée en threads sans saturation

Specs : NFR-02
Composant : BensCore
Estimation : 3
Ticket GitHub : #7

## US 2.2 — Configurer Spring Data Redis et l'indexation de topographie

En tant que **système**, je veux toutes les clés Redis préfixées par `org_id`,
afin d'isoler les données entre organisations.

Critères d'acceptation :
- `lock:seat:{org_A_uuid}:{event_A_uuid}:{external_seat_id}` ≠
  `lock:seat:{org_B_uuid}:{event_B_uuid}:{external_seat_id}`
- Aucun UUID devinable (slugs/`external_event_id` jamais dans Redis)
- `lock:session:{org_id}:{session_id}` (cleanup par session)
- `queue:event:{org_id}:{event_id}` (file d'attente)
- Spring Data Redis + serializers adaptés

Specs : NFR-03
Composant : BensCore
Estimation : 3
Ticket GitHub : #8

## US 2.3 — Implémenter le verrou distribué et le contrôle de débit avec scripts Lua atomiques dans Redis

En tant que **développeur partenaire**, je veux verrouiller/libérer un siège
et consulter les statuts, afin de réserver une place le temps du paiement.

Critères d'acceptation :
- `POST /seats/lock` (org_id, event_id, external_seat_id, session_id) :
  si libre → Lua `SET NX PX 600000` → `200` + `{ "ttl": 600, "status": "locked" }`
  ; si verrouillé → `409` + `{ "code": "SEAT_ALREADY_LOCKED" }` ; si vendu →
  `409` + `{ "code": "SEAT_SOLD" }`
- `POST /seats/unlock` : `unlock_seat.lua` compare-and-delete atomique
  (session_id doit matcher)
- `GET /seats/status/{eventId}` : map
  `{ external_seat_id: "locked" | "available" | "sold" }`
- Verrous expirés vus comme `available`
- Clé `lock:seat:{org_id}:{event_id}:{external_seat_id}` ; **0 écriture PostgreSQL**
- Rate limiting : compteur `ratelimit:{org_id}:{window}` via Lua ; au-delà du
  seuil → `429` + `{ "code": "RATE_LIMIT_EXCEEDED", "retry_after": <s> }` ;
  seuil configurable par org

Specs : SF-01, SF-02, SF-03, NFR-01, NFR-02, NFR-03
Composant : BensCore
Estimation : 8
Ticket GitHub : #9

## US 2.4 — Créer l'annotation @RateLimited et l'Aspect de protection B2B

En tant qu'**admin BENS**, je veux une annotation `@RateLimited` et un Aspect,
afin de protéger l'API contre les abus de manière déclarative.

Critères d'acceptation :
- Annotation `@RateLimited` posée sur les endpoints sensibles
- Aspect AOP lit le seuil (par défaut ou par org) et délègue au compteur Lua
  de US 2.3
- Au-delà du seuil → `429` + `{ "code": "RATE_LIMIT_EXCEEDED", "retry_after": <s> }`
- Scénario de concurrence : 1 000 sièges, 5 000 utilisateurs virtuels
  simultanés → exactement 1 000 `200`, 4 000 `409`, **0 overbooking**,
  p99 < 2 ms, débit ≥ 10 000 req/s

Specs : NFR-01, NFR-02, SF-03
Composant : BensCore
Estimation : 5
Ticket GitHub : #10

---

# M3 — Cœur Métier Web3 & Royalties [BENS CONTRACTS]

Sprint 4 (03/11 — 23/11/2026). SC Rust, déploiement, mint, Paymaster, Web3
Gateway. Toutes ces US sont dans BensContract.

## US 3.1 — Écrire et compiler le Smart Contract Rust pour les Royalties ESDT

En tant que **système**, je veux un SC Rust (multiversx-sc) représentant un
billet NFT/SBT avec mint, transfer et guards royalties, afin de forger des
billets certifiés on-chain.

Critères d'acceptation :
- Struct billet : token_id, event_bridge_id, external_seat_id,
  customer_email, minted_at, metadata_uri
- `token_type` (NFT ou SBT) fixé au déploiement
- `mint(to, token_id, metadata_uri)` crée le token, l'attribue à `to`, émet
  l'événement `Mint`, retourne un `tx_hash`
- `transfer(from, to, token_id)` réussit pour un NFT, rejeté pour un SBT
  (`ERR_SBT_NON_TRANSFERABLE`)
- Guards revente : `prix_revente ≤ prix_facial × max_resale_price_percent / 100`
  (sinon `ERR_PRICE_ABOVE_CAP`) ; royalty crédité à l'organisateur ;
  `max_tickets_per_user` vérifié (sinon `ERR_MAX_TICKETS_REACHED`)
- SC compile en WASM (`multiversx-sc build`)
- Tests mandos : mint, transfer NFT, transfer SBT rejeté, plafond, royalties,
  max_tickets

Specs : SF-05, SF-09, SF-10
Composant : BensContract
Estimation : 8
Ticket GitHub : #11

## US 3.2 — Développer le microservice d'orchestration et déploiement de contrats

En tant qu'**organisateur**, je veux déployer un SC sur MultiversX depuis
BensContract, afin d'activer un bridge sans toucher à la blockchain.

Critères d'acceptation :
- `SmartContractDeployer` déploie le SC WASM avec les règles configurées
- BensContract **retourne** l'adresse SC à BensCore (pas d'accès PostgreSQL)
- BensCore persiste `EVENT_BRIDGES.smart_contract_address` et passe
  `status` à `ACTIVE`

Specs : SF-05
Composant : BensContract (+ persistance BensCore)
Estimation : 8
Ticket GitHub : #12

## US 3.3 — Développer le service de mint MultiversX (BensContract)

En tant que **système**, je veux que BensContract lance le mint en invoquant
`mint()` sur MultiversX, afin de forger le billet NFT/SBT on-chain.

Critères d'acceptation :
- `MintService` (BensContract) reçoit la demande de mint de BensCore
- Invoque `mint()` sur le SC déployé via Web3 Gateway
- Récupère le `tx_hash` et le retourne à BensCore
- Gestion d'erreur : timeout, rejet SC → code d'erreur explicite

Specs : SF-05, NFR-06
Composant : BensContract
Estimation : 5
Ticket GitHub : à créer

## US 3.4 — Implémenter le Paymaster (gas fees + Account Abstraction)

En tant que **système**, je veux un Paymaster qui paie les gas fees via Account
Abstraction, afin que l'acheteur n'ait pas à gérer de crypto.

Critères d'acceptation :
- Le Paymaster paie les gas fees du mint (Account Abstraction)
- Aucune clé privée exposée à l'acheteur
- Échec du Paymaster loggé + retry (NFR-06)

Specs : SF-06, NFR-06
Composant : BensContract
Estimation : 8
Ticket GitHub : à créer

## US 3.5 — Implémenter le Smart Wallet acheteur

En tant que **acheteur**, je veux un Smart Wallet créé automatiquement, afin de
recevoir mon billet NFT/SBT sans gérer de portefeuille crypto.

Critères d'acceptation :
- Smart Wallet acheteur créé automatiquement à la demande
- Le billet est attribué au Smart Wallet
- Aucune action manuelle côté acheteur

Specs : SF-06
Composant : BensContract
Estimation : 5
Ticket GitHub : à créer

## US 3.6 — Configurer la Web3 Gateway MultiversX

En tant que **développeur**, je veux une Web3 Gateway dans BensContract, afin de
communiquer avec le provider MultiversX (déploiement, mint, écoute).

Critères d'acceptation :
- Connexion au provider MultiversX (API + WebSocket)
- Soumission des transactions (déploiement, mint)
- Récupération des `tx_hash` et statuts on-chain

Specs : SF-05
Composant : BensContract
Estimation : 5
Ticket GitHub : à créer

---

# M4 — Mint, Audit & Notifications Asynchrones [BENS CORE + BENS BRIDGE]

Sprint 5 (24/11 — 14/12/2026). Réception webhook paiement, écriture audit,
orchestration du mint, consommation confirmations + webhooks sortants
(BensCore) ; écouteur blockchain + publish RabbitMQ (BensBridge).

## US 4.1 — PaymentWebhookController + HMAC + Idempotence (BensCore)

En tant que **développeur partenaire**, je veux notifier BENS d'un paiement
confirmé via `POST /webhooks/payment`, afin de déclencher le mint du billet.

Critères d'acceptation :
- Endpoint reçoit payment_provider_ref, event_bridge_id, external_seat_id,
  customer_email
- Signature HMAC (header `X-BENS-Signature`) vérifiée ; si invalide, `401` +
  `{ "code": "INVALID_SIGNATURE" }`
- Un même `payment_provider_ref` rejoué ne crée aucune nouvelle ligne
  `TRANSACTION_AUDIT_LOGS` (contrainte `UNIQUE`)
- `202 Accepted` immédiat (mint différé, NFR-06) + `transaction_id` (UUID)

Specs : SF-05, NFR-05, NFR-06
Composant : BensCore/inbound
Estimation : 5
Ticket GitHub : à créer

## US 4.2 — TransactionAuditService : écriture PENDING_MINT (BensCore)

En tant que **système**, je veux que BensCore écrive chaque paiement confirmé
dans `TRANSACTION_AUDIT_LOGS` au statut `PENDING_MINT`, afin de tracer la
transaction avant mint.

Critères d'acceptation :
- Ligne créée avec `status = 'PENDING_MINT'`, `blockchain_tx_hash = NULL`,
  `created_at = now()`
- Champs event_bridge_id, external_seat_id, customer_email,
  payment_provider_ref renseignés

Specs : SF-05, NFR-04
Composant : BensCore/audit
Estimation : 3
Ticket GitHub : à créer

## US 4.3 — MintOrchestrator : appel BensContract + persistance tx_hash (BensCore)

En tant que **système**, je veux que BensCore orchestre le mint en appelant
BensContract et persiste le `tx_hash`, sans marquer `MINTED` (finalité = chaîne).

Critères d'acceptation :
- `MintOrchestrator` (BensCore) appelle `BensContract.MintService.mint(...)`
- Récupère le `tx_hash` retourné par BensContract (souvent pending explorateur)
- `TRANSACTION_AUDIT_LOGS` mis à jour : `blockchain_tx_hash = <hash>`,
  **`status` reste `PENDING_MINT`**
- Idempotence : pas de mint si `blockchain_tx_hash` déjà non NULL pour ce
  `transaction_id`

Specs : SF-05, NFR-04, NFR-05
Composant : BensCore/orchestration
Estimation : 5
Ticket GitHub : à créer

## US 4.4 — CRUD EVENT_BRIDGES (dashboard organisateur)

En tant qu'**organisateur**, je veux créer un bridge d'événement, afin de mapper
un événement Web2 vers une instance Web3.

Critères d'acceptation :
- Création avec `external_event_id` (ex: `FNAC-EVT-992`)
- Ligne `EVENT_BRIDGES` créée avec `status = 'DRAFT'`,
  `smart_contract_address` NULL
- `UNIQUE(organization_id, external_event_id)` empêche les doublons
- Transitions autorisées : `DRAFT → ACTIVE → PAUSED → ARCHIVED`

Specs : —
Composant : BensCore/dashboard
Estimation : 3
Ticket GitHub : à créer

## US 4.5 — CRUD EVENT_SMART_RULES (dashboard organisateur)

En tant qu'**organisateur**, je veux configurer les règles de revente d'un
bridge, afin d'ancrer le comportement du smart contract.

Critères d'acceptation :
- Saisie de token_type, allow_secondary_market, max_resale_price_percent,
  royalty_fee_percent, max_tickets_per_user
- Ligne `EVENT_SMART_RULES` créée (relation 1:1 avec `EVENT_BRIDGES`)
- Si `token_type = 'SBT'`, `allow_secondary_market` forcé à `false`
- `max_resale_price_percent` ∈ [0, 200], `royalty_fee_percent` ∈ [0, 50]

Specs : SF-09, SF-10
Composant : BensCore/dashboard
Estimation : 3
Ticket GitHub : à créer

## US 4.6 — Écouteur d'événements blockchain (BensBridge)

En tant que **système**, je veux que BensBridge écoute les événements `Mint`,
`Transfer` et `Resale` confirmés on-chain, afin d'alimenter RabbitMQ
(source de vérité = chaîne).

Critères d'acceptation :
- Écouteur (WebSocket/polling provider MultiversX) capte les événements des
  SC déployés
- Aucun accès PostgreSQL depuis BensBridge
- Chaque événement confirmé est publié dans RabbitMQ (payload avec
  `transaction_id` / `tx_hash` / type d'événement)
- Pas d'envoi de webhook partenaire depuis Bridge

Specs : SF-05, NFR-04
Composant : BensBridge/listener
Estimation : 8
Ticket GitHub : à créer

## US 4.7 — Configuration RabbitMQ confirmations (Bridge → Core)

En tant que **système**, je veux une topologie RabbitMQ pour les confirmations
on-chain, afin que BensCore les consomme à rythme contrôlé.

Critères d'acceptation :
- Exchange `bens.confirmations` (topic) créé au démarrage
- Queue `bens.mint.confirmations` créée avec binding
- DLQ `bens.mint.confirmations.dlq` créée
- Routing key `confirm.{org_id}.{event_id}`
- Producteur = BensBridge ; consommateur = BensCore
- Pas de RabbitMQ pour soumettre le mint (appel Core → Contract)

Specs : SF-05
Composant : BensBridge/messaging + BensCore/messaging
Estimation : 3
Ticket GitHub : à créer

## US 4.8 — WebhookDispatcher + WebhookSigner sortant (BensCore)

En tant que **système**, je veux que BensCore dispatcher un webhook de
réconciliation signé au partenaire après mint confirmé, afin qu'il mette à
jour sa billetterie Web2.

Critères d'acceptation :
- Après passage à `status = 'MINTED'`, `WebhookDispatcher` envoie le webhook
  à `target_url`
- Payload : transaction_id, event_bridge_id, external_seat_id, token_id,
  blockchain_tx_hash
- Header `X-BENS-Signature: sha256=<hmac>` (clé de l'org, jamais loggée)
- Ligne `WEBHOOK_DISPATCHES` créée avec `status = 'PENDING'`
- Sur `2xx` reçu, `status = 'DELIVERED'`

Specs : SF-05, NFR-05
Composant : BensCore/outbound
Estimation : 5
Ticket GitHub : à créer

## US 4.9 — ConfirmationConsumer RabbitMQ → MINTED + webhook (BensCore)

En tant que **système**, je veux un consumer Core qui traite
`bens.mint.confirmations` à rythme contrôlé, afin de finaliser l'audit et
notifier le partenaire sans saturer ses serveurs.

Critères d'acceptation :
- Le consumer consomme `bens.mint.confirmations` avec rate limiting
  (~200 conf/s configurable)
- Sur confirmation Mint : `UPDATE status = 'MINTED'` puis appel
  `WebhookDispatcher`
- Sur confirmation Resale : `INSERT SECONDARY_SALES`
- Si l'envoi webhook échoue : retry (1s, 5s, 30s, 5min) puis DLQ /
  `WEBHOOK_DISPATCHES.status = FAILED`
- L'achat € côté utilisateur n'est jamais bloqué (NFR-06)

Specs : SF-05, NFR-05, NFR-06
Composant : BensCore/worker
Estimation : 5
Ticket GitHub : à créer

## US 4.10 — RetryPolicy + ReplayWorker (à définir) (BensCore)

En tant que **système**, je veux une politique de retry et un worker de replay,
afin de garantir qu'aucune transaction payée ne reste non mintée ni non
notifiée.

Critères d'acceptation :
- Sur échec de livraison webhook avec `retry_count < 3`, le webhook est
  replanifié (1s, 10s, 60s) ; après 3 échecs, `status = 'FAILED'` + DLQ
- `ReplayWorker` (à définir) : resoumission mint pour `PENDING_MINT` sans
  `tx_hash` trop anciens ; resoumission webhooks échoués
- Idempotence garantie (pas de double mint ni double webhook)

Specs : NFR-04, NFR-05
Composant : BensCore/outbound + worker
Estimation : 5
Ticket GitHub : à créer

---

# M5 — Marché secondaire, Recette & Clôture CDA [TOUS]

Sprint 6 (15/12/2026 — 15/01/2027). Marché secondaire (BensContract SC +
BensCore dashboard), QR statique, recette de charge / performance,
Dockerisation, preuves CDA.

## US 5.1 — Appliquer les règles de marché secondaire on-chain (BensContract)

En tant que **système**, je veux que le SC applique royalties, plafond de prix,
SBT intransférable et limite par utilisateur, afin de garantir la conformité
des reventes on-chain.

Critères d'acceptation :
- Sur revente : `prix_revente ≤ prix_facial × max_resale_price_percent / 100`
  (sinon `ERR_PRICE_ABOVE_CAP`)
- Royalty crédité à l'organisateur (`prix_revente × royalty_fee_percent / 100`)
- SBT intransférable (`ERR_SBT_NON_TRANSFERABLE`)
- Limite `max_tickets_per_user` par acheteur (sinon `ERR_MAX_TICKETS_REACHED`)
- Tests mandos complets

Specs : SF-09, SF-10
Composant : BensContract
Estimation : 5
Ticket GitHub : à créer

## US 5.2 — Dashboard organisateur du marché secondaire (BensCore)

En tant qu'**organisateur**, je veux un dashboard pour configurer et suivre le
marché secondaire, afin de piloter les règles de revente.

Critères d'acceptation :
- Vue des bridges avec règles (royalties, plafond, SBT, max_tickets)
- Vue des transactions (PENDING_MINT, MINTED, FAILED)
- Configuration des règles via le CRUD EVENT_SMART_RULES (US 4.5)

Specs : SF-09, SF-10
Composant : BensCore/dashboard
Estimation : 5
Ticket GitHub : à créer

## US 5.3 — Génération du QR code statique (BensCore)

En tant qu'**acheteur**, je veux un QR code statique lié à mon billet NFT,
afin de valider mon entrée à l'événement.

Critères d'acceptation :
- QR code statique généré à partir du token_id + tx_hash
- Aucun rafraîchissement (simplifié POC, SF-07)
- QR consultable via un endpoint BensCore

Specs : SF-07
Composant : BensCore
Estimation : 3
Ticket GitHub : à créer

## US 5.4 — Recette de charge : bench performance (anti-overbooking)

En tant que **développeur**, je veux un scénario de concurrence / campagne de
charge, afin de valider l'anti-overbooking et la latence cible.

Critères d'acceptation :
- 1 000 sièges pour un `event_id`
- 5 000 utilisateurs virtuels tentent de verrouiller simultanément
- Exactement 1 000 verrous accordés (`200`), 4 000 refus (`409`)
- **0 overbooking**, p99 < 2 ms, débit ≥ 10 000 req/s

Specs : SF-03, NFR-01, NFR-02
Composant : BensCore
Estimation : 5
Ticket GitHub : à créer

## US 5.5 — Recette de résilience : crash-test (mint + webhooks)

En tant que **développeur**, je veux un scénario de crash-test / résilience,
afin de valider la résilience du mint et des webhooks sortants.

Critères d'acceptation :
- Simulation d'indisponibilité VEL (webhook sortant) → retry puis DLQ
- Simulation d'indisponibilité MultiversX → retry du mint
- Aucune transaction payée perdue (NFR-04, NFR-05)
- L'achat € côté utilisateur n'est jamais bloqué (NFR-06)

Specs : SF-05, NFR-04, NFR-05, NFR-06
Composant : BensCore + BensBridge
Estimation : 8
Ticket GitHub : à créer

## US 5.6 — Recette de charge : scénario de revente (marché secondaire)

En tant que **développeur**, je veux un scénario de charge / non-régression
revente, afin de valider les règles on-chain (plafond, royalties, SBT,
max_tickets).

Critères d'acceptation :
- Revente au-dessus du plafond rejetée
- Royalty crédité à l'organisateur
- SBT intransférable rejeté
- Limite max_tickets par utilisateur appliquée

Specs : SF-09, SF-10
Composant : BensContract + BensCore
Estimation : 5
Ticket GitHub : à créer

## US 5.7 — Dockerisation des 3 modules + infra

En tant que **développeur**, je veux un `docker-compose` pour les 3 modules +
PostgreSQL + Redis + RabbitMQ, afin d'avoir un environnement reproductible.

Critères d'acceptation :
- `docker-compose.yml` : BensCore, BensContract, BensBridge, PostgreSQL,
  Redis, RabbitMQ
- Volume PostgreSQL (accès BensCore uniquement)
- Healthchecks sur chaque service
- Démarrage en une commande `docker compose up`

Specs : NFR-06
Composant : racine
Estimation : 5
Ticket GitHub : à créer

## US 5.8 — Production des preuves CDA + slides de soutenance

En tant qu'**apprenant**, je veux produire les livrables et preuves de la
certification CDA, afin de valider les blocs de compétences.

Critères d'acceptation :
- Dossier `preuves/` organisé par bloc de compétence CDA
- Mapping compétence CDA → livrable BENS documenté
- Slides de soutenance préparés
- Checklist de complétude validée (skill `bens-preuves-cda`)

Specs : —
Composant : docs / preuves
Estimation : 5
Ticket GitHub : à créer



