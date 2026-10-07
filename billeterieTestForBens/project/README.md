# PulseTicket — Billetterie en ligne

Site e-commerce de billetterie **côté visiteur** (festivals, concerts, événements
sportifs), écrit en **PHP procédural + SQLite**. C'est une billeterie Web2
classique (type Ticketmaster / Digitick / VEL) : réservation de places,
panier avec verrouillage temporisé, paiement par carte et génération de
billets avec QR code.

## Stack

| Côté            | Techno                                            |
|-----------------|---------------------------------------------------|
| Back-end        | PHP 8.1+ (PDO-SQLite)                             |
| Base de données | SQLite (`database/ticketing.db`, auto-créée)      |
| Front-end       | HTML + Tailwind CSS (Play CDN) + JS vanilla        |
| QR codes        | `qrcodejs` via CDN jsDelivr                       |

Aucune dépendance Composer ni build front n'est requise pour démarrer.

---

## Démarrage rapide

```bash
php -S localhost:8000 project/public/index.php
# puis ouvrir http://localhost:8000
```

Au premier lancement, `database/ticketing.db` est créée automatiquement et
seedée avec 4 événements (festival, concert, match, et un événement
« rush sale » dans un stade de 50 000 places) + ~30 % de sièges déjà vendus
pour les événements assis.

### Pré-requis

- PHP **8.1 minimum** avec les extensions : `pdo_sqlite`, `mbstring`.
  Vérifier : `php -m | grep -i sqlite`

---

## Parcours visiteur

1. **Accueil** (`/`) — événement à la une + grille des événements à venir.
2. **Détail événement** (`/event/{slug}`) — description, galerie, catégories de prix.
3. **Achat** :
   - **Événements assis** (`/event/{slug}/seats`) — plan de sièges interactif :
     clic → `POST /api/seats/lock` (verrou 10 min), panier + compte à rebours,
     polling `/api/seats/status/{eventId}` pour refléter les achats des autres.
   - **Grands stades / fosse** (`/event/{slug}/buy`) — achat **par quota** :
     on choisit une quantité par catégorie, `POST /api/inventory/lock`
     décrémente atomiquement le compteur de la catégorie.
4. **Paiement** (`/checkout`) — formulaire client + carte bancaire factice
   (validation JS, formatage du numéro). `POST /checkout/process` crée la
   commande, simule le paiement, génère les billets + QR codes.
5. **Confirmation** (`/confirmation/{reference}`) — billets avec QR code.
6. **Compte** (`/account`) — historique des commandes + billets (onglets).

### Comptes

Inscription/connexion optionnelles mais permettent de retrouver ses billets
dans « Mon compte ». Mots de passe stockés hashés (`password_hash`).

---

## Structure du projet

```
project/
├─ app/
│  ├─ bootstrap.php            # constantes + autoload + Session + Database::init()
│  ├─ Database.php             # PDO SQLite + migration + seed (4 événements)
│  ├─ Router.php               # routeur minimaliste (GET/POST + params {slug})
│  ├─ Session.php / Auth.php   # session, CSRF, auth
│  ├─ helpers.php              # e(), view(), layout(), url(), format_price()...
│  ├─ models/                  # Event, Seat, Order, User, Inventory
│  ├─ controllers/             # Home, Event, Seat, Inventory, Checkout, Account, Auth
│  └─ views/                   # templates PHP (layout/main.php + pages)
├─ public/                     # ← racine web (docroot du serveur intégré)
│  ├─ index.php                # front controller + serveur de fichiers statiques
│  └─ assets/
│     ├─ css/app.css           # styles custom (keyframes, scrollbar, QR…)
│     └─ js/
│        ├─ app.js             # navbar, menu mobile, flash toasts
│        ├─ seatmap.js         # verrouillage sièges, panier, timer, polling
│        ├─ buy.js             # achat par quota, panier, timer, polling
│        ├─ checkout.js        # formatage carte, anti double-submit
│        ├─ qr.js              # rendu QR codes (qrcodejs)
│        └─ account.js         # onglets compte
├─ database/                   # créé à la volée (ticketing.db)
├─ tailwind.config.js          # config Tailwind (scan aussi les vues PHP)
└─ src/                        # scaffold Vite/React d'origine (inutilisé par le PHP)
```

---

## Deux modèles de vente

| Modèle          | Événements           | Verrouillage                          |
|-----------------|----------------------|---------------------------------------|
| **Sièges numérotés** | festival, concert, sport | un verrou par siège (`seats` table)   |
| **Quota**       | rush (stade 50 000)  | décrément atomique d'un compteur par catégorie (`seat_categories.quota_*`) |

Le modèle « quota » est celui des très grandes salles (fosse, stade) où l'on
ne numérote pas les places : on vend N billets d'une catégorie. Le compteur
est mis à jour par un `UPDATE ... WHERE disponible >= q` atomique.

---

## Données de test

| Événement                  | Slug                         | Type      | Catégories (€)         |
|----------------------------|------------------------------|-----------|------------------------|
| ECLIPSE — World Tour 2026   | `eclipse-world-tour-lyon`    | Rush sale | 350 / 120 / 75 (50 000 places) |
| Neon Pulse Festival 2026    | `neon-pulse-festival-2026`   | Festival  | 189 / 145 / 299        |
| Inferno Metal Night         | `inferno-metal-night`        | Concert   | 45 / 65 / 85           |
| Olympique Derby             | `olympique-derby-stade`       | Sport     | 25 / 45 / 120         |

Carte de test (factice) : `4242 4242 4242 4242` · `12/34` · `123`.

---

## Réinitialiser la base

```bash
rm -f project/database/ticketing.db
# puis relancer le serveur : la base est recréée et reseedée
```

---

## (Optionnel) Build Tailwind réel au lieu du Play CDN

Le Play CDN (`cdn.tailwindcss.com`) génère les utilitaires à l'exécution —
pratique pour tester, mais à éviter en production. Pour compiler un CSS
statique :

```bash
cd project
npx tailwindcss -i ./src/index.css -o ./public/assets/css/tailwind.css --minify
```

Puis, dans `app/views/layout/main.php`, remplacer la balise Play CDN par :

```html
<link rel="stylesheet" href="<?= asset('css/tailwind.css') ?>">
```

`tailwind.config.js` scanne déjà `app/views/**/*.php` et `public/assets/js/**`.
