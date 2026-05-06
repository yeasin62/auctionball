# AuctionBall Production Deployment

Complete checklist for deploying AuctionBall to a production server (Ubuntu 22.04+ assumed).

---

## 1. System packages

```bash
sudo apt update && sudo apt install -y \
    php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-opcache \
    nginx mysql-server redis-server supervisor \
    nodejs npm composer git unzip
```

---

## 2. Project files

```bash
sudo mkdir -p /var/www/auctionball
sudo chown -R $USER:www-data /var/www/auctionball
cd /var/www/auctionball
git clone <your-repo-url> .

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

cp .env.example .env
php artisan key:generate
# edit .env — see "Required env vars" below
php artisan migrate --force
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 3. Why MySQL, not SQLite, in production

Live auctions generate concurrent writes the moment bidding starts: every bid is 3 writes (`bids` row insert, `auction_states` update, broadcast event). SQLite serializes writes at the database level — under load this throws `SQLITE_BUSY` errors, missed bids, and broken auction state. Multi-tenant traffic (multiple orgs auctioning at once) compounds the problem.

The codebase is fully DB-agnostic — date bucketing happens in PHP, no SQLite-only SQL. Switching is just a config + migration.

### Prepare the MySQL database

On your DB server (or the same VPS in single-server deploys):

```bash
# 1. Run the setup script as root — creates database, user, grants
mysql -u root -p < deploy/mysql/setup.sql

# 2. Edit deploy/mysql/setup.sql FIRST to replace the placeholder password,
#    or run manually and set your own:
#    CREATE USER 'auctionball_app'@'localhost' IDENTIFIED BY 'yourpassword';

# 3. Verify
mysql -u auctionball_app -p auctionball_prod -e "SELECT 1"
```

### Required env vars (production overrides)

Copy `.env.production.example` to `.env` on the server and fill in. Minimum:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://auctionball.com

# Database — MySQL/MariaDB. Production will log a warning if sqlite is used.
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=auctionball_prod
DB_USERNAME=auctionball_app
DB_PASSWORD=<strong-password>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Redis — used by cache/session/queue
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Use Redis for everything that talks to a backing store
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

# Broadcasting — Reverb runs on its own supervisor process (see step 5)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<random>
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
REVERB_HOST=ws.auctionball.com    # behind nginx proxy on 443
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# File storage — S3 if multi-instance, local if single VPS
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=auctionball-uploads

# Mail — SMTP, SES, Postmark, etc.
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@auctionball.com

LOG_LEVEL=warning
```

After editing, **rebuild client assets** (Vite reads `VITE_*` at build time):

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 4. OPcache (the single biggest free perf win)

```bash
sudo cp deploy/php/opcache.ini /etc/php/8.3/fpm/conf.d/99-opcache-prod.ini
sudo systemctl reload php8.3-fpm

# Verify
php -r "var_dump(opcache_get_status()['opcache_enabled']);"   # bool(true)
```

**On every deploy** clear the cache so the new code is picked up:

```bash
sudo systemctl reload php8.3-fpm
# OR if you have a route helper:
php artisan opcache:clear
```

Tunables in `opcache.ini`:
- `memory_consumption=256` — raise if `opcache_get_status()` shows >85% used
- `max_accelerated_files=20000` — should exceed `find . -name "*.php" | wc -l`
- `validate_timestamps=0` — production only; reload FPM after each deploy

---

## 5. Supervisor — keeps queue / reverb / scheduler alive

```bash
sudo mkdir -p /var/log/auctionball
sudo chown www-data:www-data /var/log/auctionball

sudo cp deploy/supervisor/auctionball-queue.conf     /etc/supervisor/conf.d/
sudo cp deploy/supervisor/auctionball-reverb.conf    /etc/supervisor/conf.d/
sudo cp deploy/supervisor/auctionball-scheduler.conf /etc/supervisor/conf.d/

# Edit each file — change `directory`, `user`, paths if your layout differs.

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start auctionball-queue:* auctionball-reverb:* auctionball-scheduler:*

sudo supervisorctl status
```

You should see all three programs `RUNNING`. Logs are at `/var/log/auctionball/{queue,reverb,scheduler}.log`.

**On every deploy** (so workers pick up new code):

```bash
sudo supervisorctl restart auctionball-queue:* auctionball-reverb:*
```

The scheduler doesn't need restarting — it picks up new schedule definitions naturally on its next tick.

---

## 6. Nginx

Sample server block at the bottom of this file. Highlights:

- App server: `https://auctionball.com` → PHP-FPM via FastCGI
- WebSocket server: `https://ws.auctionball.com` → reverse-proxy to `127.0.0.1:8080` (Reverb)
- Both behind Let's Encrypt TLS via certbot

---

## 7. Deploy checklist (run this on every push to prod)

```bash
cd /var/www/auctionball
git pull --ff-only

composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Verify DB connectivity BEFORE running migrations — catches wrong creds /
# unreachable host immediately instead of mid-migration with partial schema.
php artisan db:show
# (alternative: php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';")

php artisan migrate --force

# Recompile cached config / routes / views with new code
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Reload opcache (drops all cached bytecode)
sudo systemctl reload php8.3-fpm

# Restart workers so they boot with the new code. Note the trailing :* on each
# program — supervisor's group syntax. Without :* you'll restart only one of
# the queue workers (numprocs=2 in the conf).
sudo supervisorctl restart auctionball-queue:* auctionball-reverb:* auctionball-scheduler:*
```

---

## 8. Verification — is everything healthy?

```bash
# OPcache
php -r 'print_r(opcache_get_status()["memory_usage"]);'

# Redis reachable
redis-cli ping   # PONG

# Queue draining
php artisan queue:monitor redis:default --max=1000   # alert if backlog > 1000

# Reverb listening
curl -sI http://127.0.0.1:8080
sudo supervisorctl status auctionball-reverb        # RUNNING

# Scheduler active
sudo supervisorctl status auctionball-scheduler     # RUNNING
tail -n 20 /var/log/auctionball/scheduler.log
```

---

## 9. Sample nginx server blocks

```nginx
# /etc/nginx/sites-available/auctionball

server {
    listen 443 ssl http2;
    server_name auctionball.com;

    root /var/www/auctionball/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/auctionball.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/auctionball.com/privkey.pem;

    client_max_body_size 20M;     # photo uploads; raise if needed

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

# Reverb websocket — separate subdomain, proxied to local Reverb on 8080
server {
    listen 443 ssl http2;
    server_name ws.auctionball.com;

    ssl_certificate     /etc/letsencrypt/live/ws.auctionball.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ws.auctionball.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }
}

server {
    listen 80;
    server_name auctionball.com ws.auctionball.com;
    return 301 https://$host$request_uri;
}
```

---

## 10. Optional: Laravel Octane (skip on first deploy)

Octane keeps the app in memory between requests for 5-10x throughput. Powerful but unforgiving:
any state leak in singletons / static properties / container bindings can corrupt subsequent
requests. Recommended path: deploy without Octane first, watch logs for a month, *then* migrate
once you have confidence the code is leak-free.

When ready:

```bash
composer require laravel/octane
php artisan octane:install --server=frankenphp     # or roadrunner
# add an octane supervisor program; replace nginx → php-fpm with nginx → octane
```

Don't add Octane the same week you go live. Get redis + opcache + queue stable first.

---

## 11. Backups — run this from day one

### Daily MySQL dump (cron)

Create `/etc/cron.d/auctionball-backup`:

```cron
# Daily DB dump at 02:00 server time, kept compressed for a week
0 2 * * * www-data /usr/local/bin/auctionball-backup.sh >> /var/log/auctionball/backup.log 2>&1
```

`/usr/local/bin/auctionball-backup.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
TS=$(date +%F-%H%M)
DEST=/backups/auctionball
mkdir -p "$DEST"

# DB dump — credentials from a my.cnf so the password isn't in `ps`/cron logs
mysqldump --defaults-extra-file=/etc/mysql/auctionball-backup.cnf \
    --single-transaction --quick --triggers --routines \
    auctionball_prod | gzip > "$DEST/db-$TS.sql.gz"

# Storage uploads (only if not already on S3 — skip when FILESYSTEM_DISK=s3)
if [ -d /var/www/auctionball/storage/app/public ]; then
    tar -czf "$DEST/uploads-$TS.tar.gz" -C /var/www/auctionball/storage/app public
fi

# Prune local backups older than 7 days (S3 keeps the long-term archive)
find "$DEST" -type f -mtime +7 -delete
```

`/etc/mysql/auctionball-backup.cnf` (chmod 600, owned by www-data):

```ini
[mysqldump]
user=auctionball_app
password=YOUR_DB_PASSWORD
host=127.0.0.1
```

### Push to S3 for long-term retention

Add a second cron entry — runs an hour after the dump so it can finish first:

```cron
0 3 * * * www-data aws s3 sync /backups/auctionball/ s3://your-bucket/db-backups/ --storage-class STANDARD_IA
```

S3 lifecycle rule: transition to Glacier after 30 days, expire after 365 days.

### Restore drill

Test once a quarter — backups you've never restored aren't backups, they're a wish:

```bash
gunzip < /backups/auctionball/db-2026-05-06-0200.sql.gz | \
    mysql -u root -p auctionball_restore_test
```

---

## 12. Common production mistakes to avoid

- ❌ `APP_DEBUG=true` in `.env` on prod (leaks stack traces, source paths, env vars to attackers)
- ❌ `DB_CONNECTION=sqlite` (the AppServiceProvider hard-stops on this in prod, but don't try)
- ❌ `BROADCAST_CONNECTION=log` (no live updates — auction will feel broken)
- ❌ `MAIL_MAILER=log` (invitation/renewal/registration emails silently disappear)
- ❌ Empty `DB_PASSWORD` in `.env` (the setup.sql refuses, but `.env` doesn't)
- ❌ Forgetting `:*` after `auctionball-queue` in supervisorctl (only restarts process 0; numprocs=2 means worker 1 keeps stale code)
- ❌ Running `php artisan migrate --force` before `php artisan db:show` (no early signal if creds are wrong)
- ❌ Forgetting `npm run build` after a `.env` change to `VITE_*` vars (client uses stale config)
- ❌ Running `composer install` instead of `composer install --no-dev` (dev deps in prod)
- ❌ Not setting `validate_timestamps=0` in OPcache (huge perf left on the table)
- ❌ Skipping backup setup until "later" (incident always happens before "later" arrives)
