# PaperTrail Dokploy Deployment

This project is prepared for Dokploy deployment, but nothing has been deployed or pushed.

## Detected Stack

- Framework: Laravel 10.50.2
- Runtime: PHP 8.1 or newer required; Docker uses PHP 8.2 with Apache
- Frontend build: Vite 5 with npm
- Database: MySQL compatible database, configured as MySQL 8.0 in Compose
- Queue: `sync` by default; no queue worker service is required
- Scheduler: no active scheduled tasks were found
- WebSockets: broadcasting provider is disabled by default; no WebSocket service is required
- Optional side service: `../papertrail_ocr_service` is a standalone FastAPI OCR prototype and is not required by the current Laravel runtime

## Dokploy Project Settings

If the repository root contains both `papertrail/` and `papertrail_ocr_service/`, set the Dokploy root directory/build context to:

```text
papertrail
```

Use the Compose configuration:

```text
compose.yaml
```

Dokploy/Traefik should route HTTP traffic to the `app` service on container port:

```text
8080
```

Do not expose the `database` service publicly.

Adminer is available as an optional database UI service:

```text
Service: adminer
Container port: 8080
Default server: database
```

If you route Adminer through Dokploy, use a separate protected domain or temporary route, then disable/remove the route when you are done checking the database.

## Domains

Configure these domains in Dokploy for the `app` service:

```text
papertrail-to.online
www.papertrail-to.online
```

The application accepts both hosts. It does not force a redirect between `www` and non-`www`; Dokploy can add that later if a canonical host is desired.

If you want browser access to Adminer, add a separate Dokploy domain such as:

```text
adminer.papertrail-to.online
```

Route it to the `adminer` service on port `8080`, and protect it in Dokploy because it is a database login page.

## DNS Records

Create these records in Hostinger after the VPS IP is ready:

```text
A
Name: @
Value: MY_VPS_IP

CNAME
Name: www
Value: papertrail-to.online
```

Optional Adminer DNS, only if you choose to expose the Adminer UI through Dokploy:

```text
CNAME
Name: adminer
Value: papertrail-to.online
```

SSL certificates should be issued by Dokploy/Traefik, not by the application container.

## Required Environment Variables

Use `.env.example` as the Dokploy environment template. Replace every `CHANGE_ME` value and generate a real Laravel key before production use.

Minimum required values:

```text
APP_ENV=production
APP_KEY=base64:GENERATE_A_REAL_KEY
APP_DEBUG=false
APP_URL=https://papertrail-to.online

DB_CONNECTION=mysql
DB_HOST=database
DB_PORT=3306
DB_DATABASE=papertrail
DB_USERNAME=papertrail
DB_PASSWORD=CHANGE_ME
DB_ROOT_PASSWORD=CHANGE_ME_ROOT

SESSION_DOMAIN=.papertrail-to.online
SESSION_SECURE_COOKIE=true
TRUSTED_HOSTS=^papertrail-to\.online$,^www\.papertrail-to\.online$
CORS_ALLOWED_ORIGINS=https://papertrail-to.online,https://www.papertrail-to.online

LOG_CHANNEL=stderr
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

Set SMTP variables if email notifications are needed:

```text
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@papertrail-to.online
```

Set `OPENAI_API_KEY` only if AI features should call OpenAI in production.

## Persistent Volumes

The Compose file defines:

```text
papertrail_storage -> /var/www/html/storage
papertrail_database -> /var/lib/mysql
```

`papertrail_storage` is required because PaperTrail stores uploads, profile photos, signatures, imported templates, generated office documents, file sessions, cache files, and logs under Laravel `storage/`.

Adminer does not require persistent storage.

## Build And Runtime Commands

Production asset build is handled inside the Dockerfile:

```text
npm ci
npm run build
composer install --no-dev --prefer-dist --no-interaction --no-progress
```

Container start command:

```text
apache2-foreground
```

The entrypoint prepares writable Laravel directories, creates the public storage symlink, and caches config/views. It intentionally does not run `route:cache` because the application currently has closure routes.

## Migrations

Run migrations manually after the first successful deployment and before opening the app to users:

```text
php artisan migrate --force
```

In Dokploy Compose this is typically:

```text
docker compose exec app php artisan migrate --force
```

Do not run destructive commands such as `migrate:fresh` or database resets in production.

## Adminer Login

Use these values in Adminer:

```text
System: MySQL
Server: database
Username: DB_USERNAME value from Dokploy
Password: DB_PASSWORD value from Dokploy
Database: DB_DATABASE value from Dokploy
```

With the simple test env example:

```text
Server: database
Username: papertrail
Password: papertrail123
Database: papertrail
```

## Health Check

The application exposes:

```text
GET /health
```

The Docker health check calls:

```text
curl -H "Host: papertrail-to.online" http://127.0.0.1:8080/health
```

## Notes Before Deployment

- The current workspace has an empty top-level `.git` directory, so `git status` and `git diff` are unavailable here.
- Dokploy deployment from Git will require a real repository and branch.
- Generate a real `APP_KEY` with `php artisan key:generate --show` and store it in Dokploy environment variables.
- The database hostname must stay `database` inside Compose, not `localhost`.
- No VPS IP address is hardcoded in the application or Docker configuration.
