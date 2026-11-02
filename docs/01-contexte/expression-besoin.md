# Expression de Besoin — Projet BENS

## 1. Contexte et problématique

### 1.1. La valeur perdue du marché secondaire

Le marché secondaire de la billetterie (reventes) représente une valeur
économique considérable. Une part significative des billets des grands
événements — concerts, festivals, sport — finit revendue à plusieurs fois
leur prix facial sur des plateformes comme Viagogo, StubHub ou Ticketmaster
Resale. Les estimations du secteur parlent de **plusieurs milliards d'euros
par an** au niveau mondial.

**Le problème central** : sur chaque revente, l'organisateur qui a produit
l'événement ne capte **rien**. Toute la plus-value part au revendeur (scalper
ou particulier) et à la plateforme de revente (qui prélève sa commission de
10 à 25 %). L'organisateur, qui porte le risque économique de l'événement et
la valeur de la marque, est **structurellement exclu** de la captation de
cette valeur.

C'est une limite fondamentale du billet Web2 : une fois vendu, le billet est
un bien "libre", transférable hors de tout registre. L'organisateur n'a ni
visibilité sur les reventes, ni levier pour en capter une part. Le marché
secondaire vit sans lui.

### 1.2. Les problèmes secondaires

À côté de ce problème central, les plateformes de billetterie B2B
(Ticketmaster, Digitick, Fnac Spectacles, etc.) font face à deux autres
douleurs récurrentes :

1. **Crash lors des drops** — lors des mises en vente très attendues
   (Stade de France, concert de stars), le flux de requêtes simultanées
   sature la base SQL relationnelle. Le site ralentit, plante, ou laisse
   des fans avec un panier vide alors qu'ils ont cliqué en premier. C'est un
   problème d'image, mais **secondaire** : il ne crée pas de valeur, il
   évite seulement une perte.

2. **Scalping et billets contrefaits** — les revendeurs professionnels
   achètent en masse (bots) puis revendent à prix d'or. Aux entrées des
   stades, des billets falsifiés sont présentés. Il n'existe pas de registre
   fiable et immuable permettant de vérifier l'authenticité d'un billet en
   temps réel. Ces problèmes découlent en fait du problème central : pas de
   registre traçable → pas de gouvernance → pas de captation.

### 1.3. L'opportunité blockchain

La blockchain MultiversX offre une couche d'écriture **immuable**,
performante (sharding, > 100 000 TPS) et à coût marginal. Utilisée comme
couche de certification du billet, elle permet pour la première fois :

- d'**ancrer des royalties de revente dans un smart contract** — chaque
  transfer du token déclenche automatiquement le paiement de la royalty à
  l'organisateur, à perpétuité, sans action manuelle ;
- d'**ancrer un plafond de prix** — la revente au-delà du plafond est
  physiquement refusée par le SC ;
- d'**ancrer un mode SBT** (Soulbound Token) — le billet devient non
  transférable, le marché secondaire est supprimé pour cet événement ;
- un **registre anti-falsification** (1 siège = 1 NFT unique, vérifiable à
  l'entrée).

Mais intégrer une blockchain directement dans une billetterie Web2
existante est coûteux : il faut des compétences Web3, gérer les wallets, les
clés, le gas, les retries, l'idempotence. C'est là que **BENS** intervient,
en tant que middleware.

---

## 2. Objectifs du projet

### 2.1. Objectif global

Concevoir un **middleware API-First, stateless, event-driven** qui s'intercale
entre les billetteries Web2 partenaires et la blockchain MultiversX, afin de
**permettre aux organisateurs de capter la valeur du marché secondaire** en
ancrant des royalties de revente dans un smart contract immuable — et, en
bonus, d'absorber les pics de charge des drops et certifier les billets sur
chaîne, **sans exposer la complexité Web3** aux équipes partenaires.

### 2.2. Objectifs fonctionnels

| ID | Objectif | Bénéfice métier |
| :--- | :--- | :--- |
| **OF-1** | Ancrer des royalties de revente dans un smart contract MultiversX, créditées automatiquement à l'organisateur à chaque revente | **Captation de valeur** : le marché secondaire, aujourd'hui valeur perdue, devient un revenu récurrent à perpétuité |
| **OF-2** | Ancrer un plafond de prix de revente dans le SC | Anti-scalp : la revente au-delà du plafond est physiquement refusée |
| **OF-3** | Offrir un mode billet SBT (non transférable) optionnel | L'organisateur peut supprimer le marché secondaire pour un événement sensible |
| **OF-4** | Offrir un dashboard organisateur pour configurer le *bridge* et suivre les royalties perçues | Autonomie de l'organisateur + visibilité sur la valeur captée |
| **OF-5** | Certifier chaque billet vendu par un NFT/SBT sur MultiversX | Anti-falsification ; registre immuable vérifiable à l'entrée |
| **OF-6** | Réconcilier le partenaire Web2 via webhook signé HMAC | Le partenaire garde sa base SQL à jour, à rythme contrôlé |
| **OF-7** | Masquer la blockchain au fan (Paymaster, Account Abstraction) | Expérience identique à un achat e-commerce classique |
| **OF-8** | Verrouiller un siège en mémoire (Redis) en < 2 ms | **Enableur Anti-Crash** : plus de crash lors des drops ; équité d'accès au panier |

> Les objectifs OF-1 à OF-4 portent la **captation de valeur et la
> gouvernance** (cœur du pitch). OF-5 à OF-7 portent la **certification et
> l'UX**. OF-8 est l'**enableur technique** Anti-Crash — un plus, pas
> l'argument n°1.

### 2.3. Objectifs non fonctionnels

| ID | Objectif | Mesure |
| :--- | :--- | :--- |
| **ONF-1** | Performance verrouillage | Latence < 2 ms à charge nominale |
| **ONF-2** | Débit | ≥ 10 000 req/s sur instance standard |
| **ONF-3** | Isolation BDD partenaire | 0 écriture SQL pendant la sélection/verrouillage |
| **ONF-4** | Tolérance aux pannes | Replay possible via `transaction_audit_logs` |
| **ONF-5** | Idempotence | Webhooks + ordres de mint idempotents (UUID transaction unique) |
| **ONF-6** | Disponibilité Web3 | Indisponibilité MultiversX ne bloque jamais l'achat € |
| **ONF-7** | Immuabilité des règles | Royalties et plafond ancrés dans le SC, non contournables une fois déployés |

---

## 3. Parties prenantes

### 3.1. Acteurs du système

| Acteur | Rôle | Relation avec BENS |
| :--- | :--- | :--- |
| **Organisateur** (Merchant) | Exploitant d'événements (festival, stade, concert) | Configure un *bridge* d'événement via le dashboard Bens ; définit le taux de royalty et le plafond de revente ; **reçoit les royalties à chaque revente** |
| **Collaborateur** (Collaborator) | Membre de l'équipe organisateur | Accède au dashboard selon RBAC ; gère les événements, clés API, IP whitelist |
| **Partenaire Web2** (Billetterie) | Plateforme B2B (Ticketmaster, Digitick…) | Intègre l'API BENS via clés API ; envoie le webhook paiement € ; reçoit les webhooks de réconciliation |
| **Fan / Acheteur final** | Spectateur | Achète son billet sur le site du partenaire ; reçoit un billet certifié NFT/SBT sans manipuler de cryptomonnaies ; peut revendre dans les limites du SC |
| **Revendeur secondaire** | Acheteur qui revend | Ne peut plus contourner les règles : le SC applique plafond et royalty à chaque transfer |
| **Opérateur BENS** | Équipe qui opère le middleware | Supervise la plateforme, gère les blacklist IP, surveille les retries/DLQ |

### 3.2. Bénéficiaires et gains

| Bénéficiaire | Gain attendu |
| :--- | :--- |
| **Organisateur** | **Nouveau revenu récurrent** : royalties sur chaque revente, à perpétuité. Contrôle du marché secondaire (plafond, SBT). Image de marque protégée. |
| Partenaire Web2 | Plus de crash lors des drops, intégration Web3 en quelques heures (clés API), pas de compétence blockchain requise, nouvelle valeur ajoutée pour ses clients organisateurs |
| Fan | Accès équitable aux drops, billet authentifié, revente à prix juste et encadrée, expérience sans friction Web3 |
| Opérateur BENS | Plateforme observable, résiliente, replayable, sans intervention manuelle sur les transactions |

---

## 4. Périmètre

### 4.1. In scope (POC)

- Ancrage des règles de revente dans le smart contract : **royalties**,
  **plafond de prix**, `max_tickets_per_user`, SBT optionnel (SF-09, SF-10).
- Configuration d'un *bridge* d'événement (dashboard organisateur, RBAC) avec
  choix du taux de royalty et suivi des royalties perçues.
- Déploiement du smart contract d'événement (Bens Contracts).
- Verrouillage de sièges en mémoire Redis (script Lua atomique, TTL 10 min).
- Réception des webhooks paiement € signés HMAC.
- Audit transactionnel (`TRANSACTION_AUDIT_LOGS` : `PENDING_MINT` → hash →
  `MINTED` après confirmation on-chain ; écriture **BensCore seule**).
- Mint asynchrone de billets NFT/SBT sur MultiversX via Bens Contracts (Rust/SDK).
- Réconciliation Web2 ↔ Web3 : BensBridge écoute la chaîne et publie dans
  RabbitMQ ; BensCore consomme à rythme contrôlé, passe à `MINTED` et envoie
  les webhooks sortants HMAC (dont reporting reventes au dashboard).
- QR statique (scope POC SF-07).
- Recette de charge (5 000 clics simultanés, crash-test Redis, revente).

### 4.2. Hors scope (discipline middleware)

BENS **ne doit pas** :

| Interdit | Raison |
| :--- | :--- |
| Stocker le catalogue d'événements / catégories / descriptions | Donnée partenaire Web2 |
| Stocker plans de sièges, labels (`section`, `row`, `seat_number`) | Seul `external_seat_id` opaque est transporté |
| Gérer panier, checkout, paiement € | Responsabilité partenaire (Stripe/PayPlug) |
| Calculer un total ou un prix facial | Seul `payment_provider_ref` est audité |
| Exposer un `GET /events` catalogue | Dérive billetterie bis |
| Demander au partenaire de connaître MultiversX | DX : intégration en quelques heures |

> **Règle de décision (3 questions)** avant toute nouvelle table / endpoint /
> entité :
> 1. Cette donnée existe-t-elle déjà chez le partenaire Web2 ? → Si oui, BENS
>    ne la stocke pas (identifiant opaque uniquement).
> 2. Sert-elle à transmettre un message ou à forger un NFT ? → Sinon, hors
>    scope.
> 3. Sans elle, BENS peut-il encore certifier le billet ou capter la royalty ?
>    → Si non, c'est du métier billetterie → laisser au partenaire.

### 4.3. Hors scope POC (reporté / non livré)

- QR dynamique avec rafraîchissement périodique (seul le QR statique est
  livré au POC).
- Marketplace de revente grand public avec UI complète (les règles sont
  ancrées et la royalty est captée automatiquement, mais la UI de revente
  côté fan n'est pas livrée au POC — la revente se fait via un portail
  MultiversX comme Portal X).
- Multi-tenant avancé avec facturation à l'usage.

---

## 5. Contraintes

### 5.1. Contraintes techniques

- **Stack imposée** : Java 25 (LTS) / Spring Boot 4.1 / Angular 21 (LTS) /
  MultiversX (Rust) / Redis / RabbitMQ / PostgreSQL 17. Voir
  `cahier-des-charges.md` §1.2.
- **Pas de Flyway** : schéma géré via `schema.sql` + Spring Boot.
- **Pas de cryptomonnaie côté fan** : Account Abstraction + Paymaster obligatoires.
- **Pas de slug / nom d'événement dans Redis** : clés Redis au format
  `lock:seat:{org_id}:{event_id}:{external_seat_id}` (UUIDs BENS).

### 5.2. Contraintes réglementaires / sécurité

- Aucun secret / clé privée blockchain en clair en BDD ou dans les logs.
- Webhooks entrants et sortants signés (HMAC).
- Rate limiting + IP whitelist + blacklist IP multi-tenant.
- Mots de passe hashés (`password_hash`).

### 5.3. Contraintes projet (CDA)

- Projet de **certification CDA** (Concevoir et Développer une Application).
- POC livré sur 6 sprints (01/09/2026 → 15/01/2027), voir
  [`planning.md`](../02-gestion-projet/planning.md).
- Recette de charge / performance obligatoire (campagne de charge, crash-test, revente).
- Dockerisation au Sprint 6.
- Dossier de preuves CDA à constituer, voir skill `bens-preuves-cda`.

---

## 6. Critères de succès et attendus

### 6.1. Critères de recette (POC)

| Fonctionnalité | Test | Résultat attendu |
| :--- | :--- | :--- |
| **Captation de royalties** | Revente entre deux utilisateurs via le SC | Royalty calculée en `BigDecimal`, créditée automatiquement à l'organisateur, plafond respecté |
| Bench de charge | Campagne de charge : 5 000 clics simultanés sur 1 000 sièges | 1 000 × `200 OK`, 4 000 × `409`, **0 overbooking**, p99 < 2 ms |
| Crash-test Redis | Interruption Redis pendant un drop | Aucune transaction financière corrompue ; annulation propre des paniers non payés |
| Revente marché secondaire | Revente entre deux utilisateurs | Billet réémis, royalties créditées à l'organisateur, plafond respecté |

### 6.2. Attendus livrables

- 3 microservices dockerisés (Bens Core, Bens Contracts, Bens Bridge).
- Documentation : vision, expression de besoin, cahier des charges, MCD/MLD,
  planning, sécurité, tests, déploiement, écoconception.
- Recette de charge / performance exécutée et rapportée.
- Dossier de preuves CDA constitué.

---

## 7. Glossaire

| Terme | Définition |
| :--- | :--- |
| **Middleware** | Couche logicielle d'interopérabilité entre deux systèmes hétérogènes (ici Web2 ↔ Web3) |
| **Marché secondaire** | Reventes de billets après la vente initiale par l'organisateur |
| **Royalty de revente** | Pourcentage du prix de revente crédité automatiquement à l'organisateur via le smart contract |
| **Plafond de revente** | Prix maximum autorisé pour une revente, ancré dans le SC (ex. 120 % du prix facial) |
| **Drop** | Mise en vente d'un événement très attendu, générant un pic de charge |
| **Bridge** | Configuration d'interconnexion entre un événement partenaire et un smart contract MultiversX |
| **NFT** | Non-Fungible Token — token unique représentant un billet transférable |
| **SBT** | Soulbound Token — token non transférable, utilisé ici pour un billet non revendable (SF-10) |
| **Paymaster** | Service qui paie les frais de gaz à la place de l'utilisateur (Account Abstraction) |
| **Mint** | Acte de créer (forger) un token sur la blockchain |
| **HMAC** | Hash-based Message Authentication Code — signature d'intégrité d'un webhook |
| **DLQ** | Dead Letter Queue — file d'attente des messages en échec après retries |
| **Idempotence** | Propriété d'une opération qui produit le même effet si elle est rejouée |
| **External seat ID** | Identifiant opaque fourni par le partenaire, non interprété par BENS |
| **Org ID / Event ID** | UUIDs générés par BENS, jamais un slug partenaire |

---

## 8. Ressources liées

- Vision produit : [`vision.md`](./vision.md)
- Cahier des charges (specs détaillées) : [`cahier-des-charges.md`](./cahier-des-charges.md)
- MCD / MLD : [`../03-conception/mcd/mcd.md`](../03-conception/mcd/mcd.md)
- Planning projet : [`../02-gestion-projet/planning.md`](../02-gestion-projet/planning.md)
- Organisation : [`../02-gestion-projet/organisation.md`](../02-gestion-projet/organisation.md)
