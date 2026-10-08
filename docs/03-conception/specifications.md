# Spécifications techniques — Projet BENS

> **Architecture** : `[architecture.md](./architecture.md)`  
> **Cahier des charges** : `[../01-contexte/cahier-des-charges.md](../01-contexte/cahier-des-charges.md)`  
> **Discipline** : middleware uniquement — pas de catalogue, plan de salle, panier, prix.

---

## 1. Périmètre du document

Ce document formalise :

1. Les **contrats d'API partenaire** (payloads, HTTP, codes d'erreur).
2. Le **webhook sortant** de réconciliation (BensCore → partenaire).
3. Les **conventions Redis** et **RabbitMQ**.
4. Le **mapping SF/NFR → composants**.
5. Les **règles marché secondaire** (captation de valeur — argument n°1).

---

## 2. Conventions transverses

### 2.1. Authentification API B2B


| Élément    | Valeur                                                            |
| ---------- | ----------------------------------------------------------------- |
| Header     | `X-BENS-Api-Key: <clé en clair>`                                  |
| Stockage   | `MERCHANTS.api_key_hash` (jamais en clair)                        |
| Isolation  | `merchant_id` / `org_id` déduit de la clé → contexte multi-tenant |
| IP         | Whitelist par marchand ; hors whitelist → `403`                   |
| Rate limit | Seuil configurable par org → `429`                                |


### 2.2. Identifiants


| Identifiant              | Type                           | Règle                                    |
| ------------------------ | ------------------------------ | ---------------------------------------- |
| `org_id` / `merchant_id` | UUID BENS                      | Jamais un slug partenaire                |
| `event_id`               | UUID BENS (`EVENT_BRIDGES.id`) | Référence bridge                         |
| `external_event_id`      | VARCHAR opaque                 | Fourni par le partenaire, non interprété |
| `external_seat_id`       | VARCHAR opaque                 | Fourni par le partenaire, non interprété |
| `transaction_id`         | UUID                           | PK audit, propagé mint + webhooks        |
| `payment_provider_ref`   | VARCHAR                        | UNIQUE — idempotence paiement            |
| `session_id`             | VARCHAR                        | Propriétaire du verrou Redis             |


### 2.3. Format d'erreur commun

```json
{
  "code": "SEAT_ALREADY_LOCKED",
  "message": "Seat is already locked by another session",
  "trace_id": "a1b2c3d4-..."
}
```


| Code                  | HTTP | Signification                                |
| --------------------- | ---- | -------------------------------------------- |
| `INVALID_API_KEY`     | 401  | Clé API absente ou invalide                  |
| `IP_NOT_ALLOWED`      | 403  | IP hors whitelist                            |
| `RATE_LIMIT_EXCEEDED` | 429  | Trop de requêtes (`retry_after` en secondes) |
| `SEAT_ALREADY_LOCKED` | 409  | Siège déjà verrouillé                        |
| `SEAT_SOLD`           | 409  | Siège déjà minté / vendu                     |
| `MAX_TICKETS_REACHED` | 409  | Quota `max_tickets_per_user` atteint         |
| `INVALID_SIGNATURE`   | 401  | HMAC webhook invalide                        |
| `BRIDGE_NOT_ACTIVE`   | 409  | Bridge pas en statut `ACTIVE`                |
| `NOT_FOUND`           | 404  | Ressource inconnue                           |
| `VALIDATION_ERROR`    | 400  | Payload invalide                             |


---

## 3. Contrats d'API partenaire (BensCore)

Tous les endpoints ci-dessous (sauf mention contraire) exigent `X-BENS-Api-Key`
et l'IP whitelist.

### 3.1. `POST /seats/lock`

Verrouille un siège en mémoire Redis (SF-01, SF-02, SF-03, NFR-01, NFR-03).

**Request**

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "session_id": "sess_abc123"
}
```


| Champ              | Obligatoire | Description                         |
| ------------------ | ----------- | ----------------------------------- |
| `event_id`         | oui         | UUID du bridge (`EVENT_BRIDGES.id`) |
| `external_seat_id` | oui         | ID opaque partenaire                |
| `session_id`       | oui         | Session acheteur côté partenaire    |


**Responses**


| HTTP  | Corps                                                         | Condition                         |
| ----- | ------------------------------------------------------------- | --------------------------------- |
| `200` | `{ "locked": true, "ttl_seconds": 600, "expires_at": "..." }` | Verrou obtenu                     |
| `409` | `{ "code": "SEAT_ALREADY_LOCKED" }`                           | Clé Redis déjà présente           |
| `409` | `{ "code": "SEAT_SOLD" }`                                     | Audit déjà `MINTED` pour ce siège |
| `409` | `{ "code": "BRIDGE_NOT_ACTIVE" }`                             | Bridge non actif                  |


**Comportement Redis** : `EVAL lock_seat.lua` → `SET NX PX 600000` sur
`lock:seat:{org_id}:{event_id}:{external_seat_id}`. **0 écriture** PostgreSQL.

---

### 3.2. `POST /seats/unlock`

Libère un verrou (compare-and-delete : seul le `session_id` propriétaire).

**Request**

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "session_id": "sess_abc123"
}
```

**Responses**


| HTTP  | Corps                               | Condition                              |
| ----- | ----------------------------------- | -------------------------------------- |
| `200` | `{ "unlocked": true }`              | Verrou libéré                          |
| `409` | `{ "code": "SEAT_ALREADY_LOCKED" }` | Session non propriétaire / clé absente |


---

### 3.3. `POST /webhooks/payment`

Confirmation de paiement € (SF-05, NFR-05, NFR-06). Reçu par **BensCore**.

**Headers**


| Header             | Description                                          |
| ------------------ | ---------------------------------------------------- |
| `X-BENS-Signature` | HMAC-SHA256 du body brut (secret = `webhook_secret`) |
| `Content-Type`     | `application/json`                                   |
| `X-BENS-Api-Key`   | Clé API partenaire                                   |


**Request**

```json
{
  "payment_provider_ref": "pi_3OxYz2...",
  "event_bridge_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "customer_email": "fan@example.com"
}
```


| Champ                  | Obligatoire | Description                       |
| ---------------------- | ----------- | --------------------------------- |
| `payment_provider_ref` | oui         | Réf. Stripe/PayPlug — **UNIQUE**  |
| `event_bridge_id`      | oui         | UUID bridge                       |
| `external_seat_id`     | oui         | Siège opaque                      |
| `customer_email`       | oui         | Transport mint initial uniquement |


**Responses**


| HTTP  | Corps                                                      | Condition                                                              |
| ----- | ---------------------------------------------------------- | ---------------------------------------------------------------------- |
| `202` | `{ "transaction_id": "<uuid>", "status": "PENDING_MINT" }` | Accepté ; mint différé                                                 |
| `401` | `{ "code": "INVALID_SIGNATURE" }`                          | HMAC invalide                                                          |
| `200` | `{ "transaction_id": "<uuid>", "status": "..." }`          | Rejeu idempotent (même `payment_provider_ref`) — pas de nouvelle ligne |


**Effets de bord (BensCore)** :

1. INSERT `TRANSACTION_AUDIT_LOGS` (`status = PENDING_MINT`) si nouveau `payment_provider_ref`.
2. Appel `MintOrchestrator` → BensContract (asynchrone par rapport à la réponse HTTP).
3. Sur succès mint : UPDATE `status = MINTED`, `blockchain_tx_hash = <hash>`.

---

### 3.4. `GET /transactions/{id}`

Statut d'une transaction d'audit.

**Response `200`**

```json
{
  "transaction_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "event_bridge_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "status": "MINTED",
  "blockchain_tx_hash": "0xabc...",
  "payment_provider_ref": "pi_3OxYz2...",
  "created_at": "2026-10-07T14:00:00Z"
}
```


| `status`       | Signification                                 |
| -------------- | --------------------------------------------- |
| `PENDING_MINT` | Paiement accepté, mint en cours / à lancer    |
| `MINTED`       | NFT/SBT forgé, `blockchain_tx_hash` renseigné |
| `FAILED`       | Échec mint (retry / replay)                   |
| `REFUNDED`     | Remboursement côté partenaire                 |


`404` si `id` inconnu ou hors tenant.

---

## 4. Webhook sortant de réconciliation (BensCore → partenaire)

Envoyé par **BensCore** après consommation d'une confirmation Bridge
(`bens.mint.confirmations`) et passage de l'audit à `MINTED`.

**Headers**


| Header               | Description                     |
| -------------------- | ------------------------------- |
| `X-BENS-Signature`   | HMAC-SHA256 du body             |
| `X-BENS-Delivery-Id` | UUID du `WEBHOOK_DISPATCHES.id` |
| `Content-Type`       | `application/json`              |


**Payload (mint confirmé)**

```json
{
  "event": "ticket.minted",
  "transaction_id": "7c9e6679-7425-40de-944b-e07fc1f90ae7",
  "event_bridge_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "blockchain_tx_hash": "0xabc...",
  "status": "MINTED",
  "occurred_at": "2026-10-07T14:00:05Z"
}
```

**Payload (revente / captation)**

```json
{
  "event": "ticket.resold",
  "event_bridge_id": "550e8400-e29b-41d4-a716-446655440000",
  "external_seat_id": "A-12-08",
  "resale_price": "120.00",
  "royalty_amount": "12.00",
  "blockchain_tx_hash": "0xdef...",
  "occurred_at": "2026-10-08T10:00:00Z"
}
```

> Aucun email de contrepartie dans le payload revente (discipline middleware).

**Attentes partenaire** : répondre `2xx` rapidement. Sinon retries **BensCore**
(1s, 5s, 30s, 5min) puis DLQ + `WEBHOOK_DISPATCHES.status = FAILED`.

---

## 5. Conventions Redis


| Clé                                                | TTL        | Opération     |
| -------------------------------------------------- | ---------- | ------------- |
| `lock:seat:{org_id}:{event_id}:{external_seat_id}` | 600 000 ms | Lock / unlock |
| `lock:session:{org_id}:{session_id}`               | 600 000 ms | Index cleanup |


**Script Lua lock** (`SET NX PX 600000`) :

```lua
-- KEYS[1] = lock:seat:{org_id}:{event_id}:{external_seat_id}
-- ARGV[1] = session_id
-- ARGV[2] = ttl_ms (600000)
if redis.call('EXISTS', KEYS[1]) == 1 then
  return 0  -- → 409
end
redis.call('SET', KEYS[1], ARGV[1], 'PX', ARGV[2])
return 1    -- → 200
```

**Unlock** : compare-and-delete atomique (valeur = `session_id`).

**Interdits** : slug / nom d'événement dans la clé ; topographie de salle en Redis.

---

## 6. Conventions RabbitMQ


| Élément      | Valeur                                            |
| ------------ | ------------------------------------------------- |
| Exchange     | `bens.confirmations` (topic)                      |
| Queue        | `bens.mint.confirmations`                         |
| DLQ          | `bens.mint.confirmations.dlq`                     |
| Routing key  | `confirm.{org_id}.{event_id}`                     |
| Retry        | 1s → 5s → 30s → 5min → DLQ (après 4 tentatives)   |
| Producteur   | **BensBridge**                                    |
| Consommateur | **BensCore** (rate-limité ~200 conf/s)            |


**Interdit** : queue pour **soumettre** le mint. Le mint est un appel HTTP
BensCore → BensContract. RabbitMQ porte uniquement les **confirmations**
on-chain (et reventes) Bridge → Core.

---

## 7. Marché secondaire — captation de valeur (SF-09, SF-10)

### 7.1. Paramètres (`EVENT_SMART_RULES`)


| Champ                      | Type         | Domaine       | Rôle                             |
| -------------------------- | ------------ | ------------- | -------------------------------- |
| `token_type`               | VARCHAR      | `NFT` | `SBT` | Transférable ou Soulbound        |
| `allow_secondary_market`   | BOOLEAN      | —             | Forcé `false` si SBT             |
| `max_resale_price_percent` | DECIMAL(5,2) | 0–200         | Plafond (% du facial)            |
| `royalty_fee_percent`      | DECIMAL(5,2) | 0–50          | Royalty organisateur (% revente) |
| `max_tickets_per_user`     | INTEGER      | défaut 4      | Anti-bot                         |


### 7.2. Formules (`BigDecimal`, scale 2, `HALF_UP`)

```text
plafond_revente = prix_facial × (max_resale_price_percent / 100)
royalty         = prix_revente × (royalty_fee_percent / 100)
contrainte      : prix_revente ≤ plafond_revente
```

### 7.3. Preuve de captation (`SECONDARY_SALES`)


| Champ                | Description                      |
| -------------------- | -------------------------------- |
| `resale_price`       | Prix de revente on-chain         |
| `royalty_amount`     | Montant capté par l'organisateur |
| `blockchain_tx_hash` | Preuve on-chain (**UNIQUE**)     |
| `external_seat_id`   | Siège id                         |
| `event_bridge_id`    | Bridge concerné                  |


Écrit par **BensCore** après consommation d'une confirmation Resale publiée
par BensBridge. Agrégé par le dashboard organisateur.

---

## 8. Mapping SF / NFR → composants

### 8.1. Spécifications fonctionnelles


| ID        | Intitulé                   | Composant                                | Preuve / livrable                       |
| --------- | -------------------------- | ---------------------------------------- | --------------------------------------- |
| **SF-01** | Verrou Redis Lua < 2 ms    | BensCore                                 | `POST /seats/lock` + script Lua         |
| **SF-02** | TTL 10 min                 | BensCore                                 | `PX 600000`                             |
| **SF-03** | Anti-overbooking 200 / 409 | BensCore                                 | Concurrent lock                         |
| **SF-05** | Mint asynchrone            | BensCore → BensContract                  | `202` puis mint                         |
| **SF-06** | Paymaster / AA             | BensContract                             | Gas fees masqués                        |
| **SF-07** | QR statique (POC)          | BensCore                                 | Billet certifié                         |
| **SF-09** | Plafond + royalties        | BensContract (SC) + BensCore (dashboard) | `EVENT_SMART_RULES` / `SECONDARY_SALES` |
| **SF-10** | SBT optionnel              | BensContract (SC)                        | `token_type = SBT`                      |


### 8.2. Spécifications non fonctionnelles


| ID         | Intitulé             | Composant             | Critère                     |
| ---------- | -------------------- | --------------------- | --------------------------- |
| **NFR-01** | Latence verrou       | BensCore / Redis      | p99 < 2 ms                  |
| **NFR-02** | Débit                | BensCore              | ≥ 10 000 req/s              |
| **NFR-03** | Isolation BDD        | BensCore              | 0 écriture SQL pendant lock |
| **NFR-04** | Tolérance aux pannes | BensCore              | Replay + DLQ                |
| **NFR-05** | Idempotence          | BensCore              | UUID + UNIQUE refs          |
| **NFR-06** | Web3 non bloquant    | BensCore              | Achat € → `202` immédiat    |
| **NFR-07** | Immuabilité règles   | BensContract          | Royalties/plafond ancrés SC |


### 8.3. Recette (rappel)


| Scénario         | Outil                      | Attendu                                    |
| ---------------- | -------------------------- | ------------------------------------------ |
| Bench charge     | k6 5 000 VU / 1 000 sièges | 1 000 × 200, 4 000 × 409, 0 overbooking    |
| Crash-test Redis | Interruption Redis         | Aucune tx € corrompue                      |
| Revente          | Transfer on-chain          | Royalty créditée + ligne `SECONDARY_SALES` |


---

## 9. Appels internes (hors surface partenaire)


| Appel                                    | De → Vers               | Rôle                                       |
| ---------------------------------------- | ----------------------- | ------------------------------------------ |
| `DeployService.deploy(bridge_id, rules)` | BensCore → BensContract | Déploie SC, **retourne** adresse → Core écrit BDD |
| `MintService.mint(transaction_id, …)`    | BensCore → BensContract | Mint NFT/SBT, retourne `tx_hash` (pending OK)     |
| Écoute Mint / Transfer / Resale          | MultiversX → BensBridge | Publish `bens.mint.confirmations`                 |
| Consume confirmation                     | RabbitMQ → BensCore     | `MINTED` / `SECONDARY_SALES` + webhook HMAC       |




---

## 10. Traçabilité 


| Critère                                       | Couverture |
| --------------------------------------------- | ---------- |
| Contrats API lock / unlock / payment / statut | §§ 3.1–3.4 |
| Payloads, HTTP, codes d'erreur                | §§ 2.3, 3  |
| Mapping SF/NFR → composants                   | § 8        |
| Conventions Redis / RabbitMQ                  | §§ 5–6     |
| Règles marché secondaire + captation          | § 7        |
| Webhook sortant HMAC                          | § 4        |


---

## 11. Ressources liées

- Architecture : `[architecture.md](./architecture.md)`
- Cahier des charges : `[../01-contexte/cahier-des-charges.md](../01-contexte/cahier-des-charges.md)`
- MLD / DDL : `[mcd/mld.md](./mcd/mld.md)` · `[mcd/schema.sql](./mcd/schema.sql)`
- Planning : `[../02-gestion-projet/planning.md](../02-gestion-projet/planning.md)`

