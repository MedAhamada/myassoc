# MyAssoc

Application de gestion dématérialisée des adhérents du **Collectif des Jeunes de Ouellah Mitsamiouli à Marseille (CJOMM)** : fiches adhérents, cycle de vie des adhésions, import Excel/CSV, cartes d'adhérent PDF avec QR code et page publique de vérification.

## Stack

| Composant   | Techno                                                              |
| ----------- | -------------------------------------------------------------------- |
| Backend     | Symfony 7.4 (PHP 8.4), API REST en contrôleurs attribués, JWT (Lexik) |
| Frontend    | Next.js 16 (App Router, TypeScript, Tailwind CSS v4)                 |
| Base de données | MySQL 8                                                           |
| PDF / QR    | Dompdf + Endroid QR Code                                             |
| Import      | PhpSpreadsheet (.csv / .xlsx)                                        |
| Dev env     | Docker Compose (mysql, backend PHP-FPM, nginx, frontend)             |

## Structure du repository

```
.
├── backend/           # API Symfony (entités, contrôleurs, migrations)
├── frontend/          # Application Next.js (dashboard admin + page publique /verify)
├── docker/            # Dockerfile PHP-FPM, config nginx
├── docs/              # Spécification, logo, exemple d'import CSV
└── docker-compose.yml
```

## Démarrage (Docker)

```bash
cp .env.example .env          # identifiants MySQL pour docker-compose
docker compose up -d mysql backend nginx frontend

# première installation uniquement :
docker compose exec backend composer install
docker compose exec backend php bin/console lexik:jwt:generate-keypair --no-interaction
docker compose exec backend php bin/console doctrine:database:create
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

# créer le premier compte administrateur (le CRUD /api/admins nécessite déjà d'être authentifié)
docker compose exec backend php bin/console app:admin:create admin@myassoc.local "MotDePasse123!" Doe John
```

- Backend (API) : http://localhost:8000/api
- Frontend : http://localhost:3000
- Page publique de vérification : http://localhost:3000/verify/{uuid}

## Backend

### Modèle de données

Trois entités Doctrine (`backend/src/Entity`), migration initiale dans `backend/migrations/` :

- **Admin** — comptes administrateurs (UUID, email unique, mot de passe haché via `auto` — Argon2id/Bcrypt selon la plateforme, rôles JSON).
- **Adherant** — fiche adhérent. `id` (UUID interne) et `uuid` (identifiant public séparé, encodé dans le QR code / route `/verify/{uuid}`) sont distincts par conception.
- **Adhesion** — une période d'adhésion (`date_debut`/`date_fin`), rattachée à un `Adherant` et à l'`Admin` qui l'a créée.

Le statut de l'adhérent (`ACTIF`/`EXPIRE`/`RESILIE`) est dérivé et maintenu par `App\Service\AdhesionService` lors du renouvellement/résiliation. Une commande console dédiée fait passer les adhésions `EN_COURS` en `EXPIREE` une fois `date_fin` dépassée :

```bash
php bin/console app:memberships:sync-status
```

À exécuter quotidiennement via cron en production (voir le commentaire dans `src/Command/SyncMembershipStatusesCommand.php`).

### Authentification

`POST /api/auth/login` (email + mot de passe JSON) renvoie un JWT (Lexik JWT Bundle). Toutes les routes `/api/*` exigent `ROLE_ADMIN` via ce token, à l'exception de `/api/auth/login` et `/api/public/*`.

### Endpoints

| Méthode | Route                                | Description                                  |
| ------- | ------------------------------------- | --------------------------------------------- |
| POST    | `/api/auth/login`                     | Authentification, renvoie un JWT              |
| GET     | `/api/admins`                         | Liste des administrateurs                     |
| POST    | `/api/admins`                         | Créer un administrateur                       |
| DELETE  | `/api/admins/{id}`                    | Supprimer un administrateur                   |
| GET     | `/api/members`                        | Liste/recherche/filtre des adhérents (`q`, `statut`, `page`, `limit`) |
| POST    | `/api/members`                        | Créer un adhérent                             |
| GET     | `/api/members/{id}`                   | Fiche détaillée + historique des adhésions    |
| POST    | `/api/members/{id}/renew`             | Renouveler l'adhésion (`dateDebut`/`dateFin` optionnels, défaut = aujourd'hui → +1 an) |
| POST    | `/api/members/{id}/cancel`            | Résilier l'adhésion en cours                  |
| POST    | `/api/members/import`                 | Import `.csv`/`.xlsx` (champ `file`)          |
| GET     | `/api/members/{id}/card/download`     | Carte d'adhérent PDF                          |
| POST    | `/api/members/{id}/card/send-email`   | Envoi de la carte par e-mail                  |
| GET     | `/api/public/verify/{uuid}`           | Vérification publique (sans authentification) |

Toutes les erreurs `/api/*` renvoient un JSON `{ error, status, violations? }` (`App\EventListener\ApiExceptionListener`).

## Frontend

Next.js App Router, JWT stocké côté client (`localStorage`) et transmis en `Authorization: Bearer`. Pages principales :

- `/login` — authentification
- `/members`, `/members/new`, `/members/[id]`, `/members/import` — gestion des adhérents
- `/admins` — CRUD administrateurs
- `/verify/[uuid]` — page publique (rendue côté serveur, aucune authentification)

Variables d'environnement (`frontend/.env.local`, voir `.env.local.example`) :

- `NEXT_PUBLIC_API_URL` — URL de l'API côté navigateur (ex. `http://localhost:8000/api`)
- `INTERNAL_API_URL` — URL interne utilisée uniquement par le rendu serveur de `/verify/[uuid]` à l'intérieur du réseau Docker (`http://nginx/api`), car `localhost` y désigne le conteneur frontend lui-même.

La palette (bleu marine `#0E2A47`, or `#F2B705`, vert `#1F7A3F`) s'inspire du logo `docs/cjomm-logo.jpeg`.

## Import Excel/CSV

`App\Service\AdherantImportService` associe les colonnes du fichier par en-tête normalisé (accents/casse/ponctuation ignorés), compatible avec le format `docs/adherents.csv` (Nom, Prénom, E-mail, Téléphone, Adresse, Date de naissance, Profession / Études). Les lignes sans nom/prénom, les e-mails invalides et les doublons (par e-mail, dans le fichier ou déjà en base) sont ignorés et rapportés dans la réponse (`imported`, `skippedDuplicates`, `errors[]`) plutôt que de bloquer tout l'import.

## Prochaines étapes suggérées

- Endpoint `PATCH /api/members/{id}` pour éditer une fiche existante.
- Tests automatisés (PHPUnit côté backend, Playwright/Vitest côté frontend).
- Passage du JWT en cookie httpOnly + Server Actions Next.js pour éviter le `localStorage`.
- CI (lint, tests, `doctrine:schema:validate`) avant déploiement.
