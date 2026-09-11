# Skola — VPS Deployment Spec

Target: a single Ubuntu VPS running the Laravel API + the built React admin, behind Nginx, with PostgreSQL. Matches the PRD spec (2 vCPU / 2 GB RAM / 40 GB NVMe).

## 1. Server

| Item | Value |
|------|-------|
| OS | Ubuntu Server 24.04 LTS |
| Size | 2 vCPU · 2 GB RAM · 40 GB NVMe (PRD baseline) |
| Why it fits 2 GB | The face CNN runs **on the tablet**, not the server. The VPS only does API + vector search, which is light. |
| Swap | Add 2 GB swap file — cheap insurance on a 2 GB box during composer/npm builds. |

## 2. Services (all on the one box for v1)

| Service | Role | Notes |
|---------|------|-------|
| **Nginx** | Reverse proxy + static host | Serves the SPA and proxies `/api` to PHP-FPM. |
| **PHP 8.4-FPM** | Runs Laravel | Match the dev version. |
| **PostgreSQL 16 + pgvector** | Database | `pgvector` needed for face matching (v1 feature). Bind to localhost only. |
| **Node 22** | Build only | Builds the SPA to static files; not run at runtime. |
| Queue worker | Deferred | Only needed when WhatsApp/payments land (v2). Use the `database` queue driver first; add Redis + Supervisor then. |

## 3. Single-domain layout (avoids CORS)

Serve **both** the SPA and the API from one hostname, e.g. `sekolah.example.com`:

- `/api/*` → PHP-FPM (Laravel)
- everything else → the SPA's `dist/` (with SPA fallback to `index.html`)

Because the SPA and API share an origin, there is **no CORS to configure**, and Sanctum bearer-token auth stays simple. The gate kiosk and parent app call the same `https://sekolah.example.com/api`.

## 4. Install (one-time)

```bash
# base
sudo apt update && sudo apt install -y nginx postgresql postgresql-16-pgvector \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  unzip git curl
# composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# node 22 (build tool)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

## 5. Database

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE skola;
CREATE USER skola WITH PASSWORD 'CHANGE_ME';
GRANT ALL PRIVILEGES ON DATABASE skola TO skola;
\c skola
CREATE EXTENSION IF NOT EXISTS vector;   -- pgvector
SQL
```

Keep PostgreSQL bound to `localhost` (default). Do **not** expose 5432 to the internet.

## 6. Backend (Laravel)

```bash
cd /var/www/skola/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env — see below
php artisan migrate --force
php artisan config:cache && php artisan route:cache
```

`.env` (production):
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sekolah.example.com
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=skola
DB_USERNAME=skola
DB_PASSWORD=CHANGE_ME
QUEUE_CONNECTION=database
LOG_CHANNEL=daily         # rolling logs; cannot fill the disk (PRD concern)
LOG_DAILY_DAYS=14
```

Permissions: `storage/` and `bootstrap/cache/` must be writable by `www-data`.

> Note on face embeddings: the frame stores `face_embeddings.embedding` as JSON (portable). When the face-match feature is built, switch that column to a pgvector `vector` type + an index — that's why pgvector is installed now.

## 7. Frontend (built static)

```bash
cd /var/www/skola/frontend-admin
npm ci && npm run build       # outputs dist/
```

Point Nginx at `frontend-admin/dist`. Rebuild on each release; no Node process runs in production.

## 8. Nginx site

```nginx
server {
    listen 80;
    server_name sekolah.example.com;
    root /var/www/skola/frontend-admin/dist;
    index index.html;

    # SPA
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API -> Laravel public/ via PHP-FPM
    location /api {
        root /var/www/skola/backend/public;
        try_files $uri /index.php?$query_string;
    }
    location ~ \.php$ {
        root /var/www/skola/backend/public;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Then HTTPS:
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d sekolah.example.com
```

## 9. Scheduler & queue (wire when needed)

Laravel scheduler (harmless to add now):
```bash
* * * * * cd /var/www/skola/backend && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker — only once WhatsApp/payments (v2) exist. Then add a Supervisor program running `php artisan queue:work` and (recommended) Redis.

## 10. Hardening

- **Firewall:** `ufw allow OpenSSH; ufw allow 'Nginx Full'; ufw enable` — expose only 22/80/443.
- **SSH:** key-only, disable password login.
- **fail2ban** on SSH.
- **Backups:** nightly `pg_dump` to off-box storage (cron).
- **Log rotation:** Laravel `daily` channel (above) + system `logrotate` on Nginx logs — directly addresses the PRD's "logs must not fill memory" requirement.

## 11. Deploy checklist per release

1. `git pull`
2. Backend: `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan config:cache route:cache`
3. Frontend: `npm ci && npm run build`
4. `sudo systemctl reload php8.4-fpm nginx`

## Not covered here (separate targets)

- **Gate kiosk app** (Flutter/RN + ML Kit + TFLite) — ships to Android tablets, not the VPS.
- **Parent/student mobile app** — app stores, not the VPS.
- **Payment / WhatsApp** — external gateways, wired in v2.
