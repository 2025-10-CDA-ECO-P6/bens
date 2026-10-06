![BENS LOGO](../../benslogo.png)

# BENS : Blockchain Event Network System

> **Vision produit** — Document de présentation externe et soutenance CDA.
> Source de vérité technique : [`cahier-des-charges.md`](./cahier-des-charges.md).

Middleware de billetterie forte charge : **Anti-Scalping**, **Anti-Crash** et **Gouvernance**.

## Présentation

**Bens** est une solution middleware conçue pour s'intégrer entre les
plateformes de billetterie traditionnelles (Digitick, Ticketmaster, etc.) et
l'infrastructure blockchain MultiversX. Le système permet de déporter la
charge transactionnelle lors des pics de vente, de certifier le billet sur
chaîne et de sécuriser le marché secondaire.

> **Discipline middleware** : BENS est un *transmetteur de messages* et un
> *forgeur de NFT/SBT*. Il **n'est jamais une billetterie bis** : catalogue,
> plan de salle, panier, checkout et paiement € restent chez le partenaire
> Web2. BENS ne transporte que des identifiants opaques (`external_event_id`,
> `external_seat_id`) et des ordres de mint.

### Objectifs principaux

- **Disponibilité (Anti-Crash)** : verrouillage des sièges **en mémoire Redis**
  (scripts Lua atomiques) pour absorber les pics de requêtes sans solliciter
  la base SQL du partenaire. Le mint est asynchrone et non bloquant pour
  l'achat en euros.
- **Équité (Anti-Scalp)** : règles de revente ancrées dans le smart contract
  (plafond de prix, royalties, limite par utilisateur) et billet
  **non transférable** (Soulbound Token) optionnel, au choix de l'organisateur.
- **Contrôle (Gouvernance)** : gestion du cycle de vie du billet NFT via un
  dashboard organisateur (RBAC) qui configure le *bridge* d'événement et
  déploie le smart contract sur MultiversX.

---

## Architecture et Services

### Bens Core (Java 25 / Spring Boot 4)

Le centre de commande du middleware, qui pilote l'interaction avec le
partenaire et orchestre la certification.

- **API B2B partenaire** : surface contractée et volontairement minimale
  (`POST /seats/lock`, `POST /seats/unlock`, `GET /transactions/{id}`).
  Sécurisée par un `CustomAuthFilter` (clés API, IP whitelist, rate limiting).
- **Redis Layer** : gère les verrous atomiques par siège via un script Lua
  (`SET NX PX 600000`) pour garantir l'unicité des réservations en temps réel
  avec une latence sub-2 ms. **Aucune topographie de salle** n'est stockée :
  seule la clé opaque `lock:seat:{org_id}:{event_id}:{external_seat_id}` est
  posée.
- **Payment Webhook Receiver** : reçoit la confirmation de paiement € du
  partenaire (HMAC), écrit l'audit `TRANSACTION_AUDIT_LOGS` (`PENDING_MINT`),
  puis **orchestre** le mint en appelant Bens Contracts.
- **Dashboard organisateur (Angular 18+)** : configuration des bridges,
  déploiement du smart contract, suivi des transactions, RBAC
  (Merchant ↔ `ORGANIZATIONS`, Collaborator ↔ `USERS`).
- **QR Code statique** (scope POC SF-07) : billet certifié consultable côté
  acheteur final.

### Bens Contracts (Rust / MultiversX)

La logique métier immuable exécutée sur la blockchain, et la couche
d'abstraction Web3.

- **Déploiement des Smart Contracts** : crée et déploie le SC d'événement
  sur MultiversX, stocke les IDs/adresses en base (`EVENT_BRIDGES`).
- **Paymaster & Signer** : service de méta-transactions qui signe et paie
  les frais réseau sur MultiversX (Account Abstraction), offrant une
  expérience transparente — pas de fenêtre MetaMask, pas de manipulation de
  cryptomonnaies pour le fan.
- **Minting NFT/SBT** : génère les billets numériques en invoquant `mint()`
  sur MultiversX. Les métadonnées du token ne contiennent que l'identifiant
  opaque `external_seat_id` fourni par le partenaire — **jamais** `section`,
  `row` ou `seat_number` (discipline middleware).
- **Smart Governance** : application automatique des règles de revente
  définies par l'organisateur (`max_resale_price_percent`,
  `royalty_fee_percent`, `max_tickets_per_user`, `allow_secondary_market`).
- **Smart Wallet acheteur** : création de wallet via email/téléphone (xPortal),
  masquée derrière le Paymaster.

### Bens Bridge (Java 25 / Spring Boot 4) & RabbitMQ

Le cœur de la **réconciliation** Web2 ↔ Web3 — et uniquement cela.

- **Écouteur blockchain** : détecte les événements MultiversX (Mint,
  Transfer) et rapproche l'état on-chain avec les `TRANSACTION_AUDIT_LOGS`.
- **RabbitMQ Message Broker** : les succès de transactions MultiversX sont
  empilés dans la queue `bens.webhook.outbox` (exchange topic `bens.mint`,
  routing key `mint.{org_id}.{event_id}`, DLQ persistante).
- **Worker de Synchronisation** : consomme ces messages pour notifier le
  partenaire via **webhooks sortants signés HMAC**.
- **WebhookDispatcher + WebhookSigner** : signature HMAC sortante, retries
  exponentiels (1s, 5s, 30s, 5min) puis DLQ après 4 tentatives.
- **Lissage & Batching** : les webhooks sortants vers le partenaire sont
  lissés (rate limiting ~200 confirmations/s) ou regroupés par lots,
  protégeant ainsi leur infrastructure SQL de toute surcharge.

> **Pas de RabbitMQ pour le mint** : l'ordre de mint est transmis
> synchronement de Bens Core à Bens Contracts. RabbitMQ n'est utilisé que
> pour les **webhooks sortants** côté Bens Bridge.

---

## Flux de Fonctionnement

### 1. Configuration (Setup)

L'organisateur initialise le *bridge* d'événement depuis le dashboard Bens.
Il saisit son `external_event_id` opaque (jamais le catalogue complet) et
configure les règles du smart contract
(`EVENT_CONTRACT_CONFIGS` : `token_type`, `max_resale_price_percent`,
`royalty_fee_percent`, `max_tickets_per_user`, `allow_secondary_market`).
Bens Core ordonne alors à Bens Contracts de **déployer le SC** sur MultiversX
et génère les clés API + l'URL de webhook de callback. États du bridge :
`DRAFT → ACTIVE → PAUSED → ARCHIVED`.

### 2. Phase de Vente (Rush)

Le fan sélectionne sa place sur l'interface partenaire. Le partenaire appelle
`POST /seats/lock` avec son `external_seat_id` opaque. Bens Core pose un
verrou temporaire dans Redis via le script Lua atomique (`SET NX PX 600000`,
TTL 10 min). Si le verrou est obtenu → `200 OK` + TTL ; sinon → `409 Conflict`.
**0 écriture** en base SQL partenaire ou PostgreSQL pendant cette phase (NFR-03).

Le fan paie en euros sur le checkout du partenaire (Stripe/PayPlug). Le
partenaire envoie un **webhook paiement signé HMAC** à Bens Core, qui écrit
`TRANSACTION_AUDIT_LOGS` (`PENDING_MINT`) puis **ordonne à Bens Contracts de
minter**. Bens Contracts soumet la transaction via le Paymaster ; la charge
de validation et d'écriture est supportée par le réseau décentralisé.

### 3. Réconciliation (Sync)

Bens Bridge détecte la transaction validée on-chain, rapproche
`blockchain_tx_hash` avec l'audit (`status = MINTED`), puis publie un message
dans RabbitMQ (`bens.webhook.outbox`). Le worker de synchronisation traite
ces messages pour confirmer la vente au partenaire via webhook HMAC, à un
rythme contrôlé (~200 confirmations/s), garantissant une mise à jour stable
et fluide de leur base de données.

---

## Sécurité et Résilience

- **Protection Anti-Doublon** : le Smart Contract sur MultiversX possède le
  registre final immuable. Même en cas d'incident sur le cache Redis, il est
  physiquement impossible de générer deux NFT pour le même siège. L'idempotence
  est garantie par un **UUID de transaction unique** propagé du webhook
  paiement jusqu'au mint et au webhook sortant
  (`TRANSACTION_AUDIT_LOGS.payment_provider_ref` est `UNIQUE`).
- **Tolérance aux pannes** : en cas d'indisponibilité du système partenaire,
  RabbitMQ conserve les messages de confirmation de manière persistante. La
  synchronisation reprend automatiquement dès le rétablissement du service.
  Un ReplayWorker rejoue les transactions bloquées via
  `transaction_audit_logs` (NFR-04).
- **Indisponibilité Web3 non bloquante** : l'achat en euros n'est **jamais**
  bloqué par une panne MultiversX — le mint est asynchrone (NFR-06).
- **Anti-bot** : `max_tickets_per_user` ancré dans le SC, `CustomAuthFilter`
  côté Bens Core (clés API, IP whitelist, rate limiting, `BLACKLISTED_IPS`).

---

## Pourquoi MultiversX ?

Le choix de l'infrastructure MultiversX est déterminant pour répondre aux
exigences de performance d'une billetterie mondiale. Contrairement aux
blockchains traditionnelles, MultiversX offre des spécificités techniques qui
servent directement les objectifs de Bens.

### Scalabilité et Performance (Adaptive State Sharding)

Lors d'une mise en vente majeure (type Stade de France), le flux de
transactions peut saturer n'importe quelle base de données SQL ou blockchain
classique.

- **Capacité** : grâce au sharding horizontal, MultiversX peut traiter plus
  de 100 000 transactions par seconde (TPS).
- **Élimination des goulots d'étranglement** : les transactions de Bens sont
  réparties sur plusieurs shards, garantissant que la montée en charge d'un
  événement n'impacte pas la fluidité du réseau global.

### Coûts et Transparence du Gaz

Pour un middleware de billetterie, les frais de transaction (Gas) doivent être
négligeables et prévisibles.

- **Frais minimes** : les coûts par transaction sur MultiversX sont dérisoires
  (quelques centimes), ce qui permet à Bens d'absorber ces frais via son
  service de Paymaster.
- **Transparence totale** : l'utilisateur final ne voit jamais de fenêtre
  "Metamask" lui demandant des frais de gaz complexes. Bens utilise
  l'abstraction de compte pour rendre l'expérience identique à un achat
  e-commerce classique.

### Standard ESDT (Native NFTs)

MultiversX s'appuie sur la norme ESDT (Elrond Standard Digital Token) pour
l'émission des billets.

- **Performance native** : contrairement aux NFT sur d'autres chaînes qui
  reposent sur des Smart Contracts lourds (ERC-721), les NFT sur MultiversX
  sont natifs au protocole. Cela réduit la charge de calcul et augmente la
  sécurité.
- **Métadonnées intégrées** : l'identifiant opaque `external_seat_id` est
  inscrit directement dans le token, facilitant la vérification ultra-rapide
  aux entrées des stades — sans exposer le plan de salle du partenaire.

### Expérience Utilisateur et Abstraction (Portal X)

L'adoption massive nécessite de cacher la complexité technique de la
blockchain.

- **Visualisation intuitive** : grâce à des outils comme Portal X,
  l'utilisateur lambda peut visualiser son billet NFT, ses avantages exclusifs
  ou l'historique de sa place de manière ergonomique, sans avoir besoin de
  connaissances techniques en Web3.
- **Accessibilité** : le couplage entre Bens et l'écosystème MultiversX permet
  de créer des wallets via un simple email ou numéro de téléphone (xPortal),
  garantissant que le "vrai fan" n'est jamais freiné par la technologie.

---

## Stack Technique

| Couche | Technologie |
| :--- | :--- |
| Backend | **Java 25**, **Spring Boot 4** (Virtual Threads / Project Loom) |
| Frontend dashboard | **Angular 18+** |
| Blockchain | **MultiversX** (Smart Contracts en **Rust**) |
| Cache & Concurrency | **Redis** (scripts Lua atomiques) |
| Message Broker | **RabbitMQ** (exchange topic + DLQ) |
| Base relationnelle | **PostgreSQL** (audit, config bridges, webhooks) |
| Intégration | SDK MultiversX Java, Webhooks JSON API, HMAC |

> Stack **normative** pour le dépôt. Toute divergence doit être justifiée et
> mise à jour dans [`cahier-des-charges.md`](./cahier-des-charges.md) §1.2.

---

## Ressources liées

- Cahier des charges : [`cahier-des-charges.md`](./cahier-des-charges.md)
- Expression de besoin : [`expression-besoin.md`](./expression-besoin.md)
- MLD : [`../03-conception/mcd/mld.md`](../03-conception/mcd/mld.md)
- Planning : [`../02-gestion-projet/planning.md`](../02-gestion-projet/planning.md)
