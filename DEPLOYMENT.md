# Backend deployment

## Docker

Run the app with Docker Compose (app, nginx, queue, scheduler, mysql, redis).

**Build and run**

```bash
cp .env.example .env
# Edit .env: set APP_KEY (e.g. php artisan key:generate), DB_DATABASE, DB_USERNAME, DB_PASSWORD
docker compose up -d
```

**Required env for Docker:** `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Compose sets `DB_HOST=mysql` and `REDIS_HOST=redis`. Cache, queue, session, and broadcasting use Redis by default; ensure Redis is running (included in compose).

**First run**

```bash
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan config:cache
```

**Dev:** Default compose mounts the app code (`.:/var/www/html`), so changes apply without rebuild.

**Prod:** Use `docker-compose.prod.yml` (or an override) that omits the app volume and sets `APP_ENV=production` so the image’s copied code is used.

**Production checklist**

- **Secrets:** Never commit `.env`. Set `APP_KEY` and `DB_PASSWORD` (and other secrets) in the environment or a secure secret store. Do not rely on compose defaults (`-secret`) in production.
- **HTTPS:** The stack exposes port 80 only. Put a reverse proxy (Traefik, Caddy, or host Nginx) in front with TLS, or add an SSL server block and certs to `docker/nginx/default.conf`.
- **Resource limits:** `docker-compose.prod.yml` sets `deploy.resources.limits`. With standalone Docker Compose (no Swarm), run with compatibility so limits apply:  
  `docker compose --compatibility -f docker-compose.yml -f docker-compose.prod.yml up -d`
- **Restart policy:** All services use `restart: unless-stopped` so containers restart after crash or reboot.
- **Redis persistence:** Prod override mounts a `redis_data` volume so Redis state survives restarts when used for cache/queue/session.
- **Logging:** Default is json-file. Configure a log driver or rotation in the daemon or compose if you need to avoid filling disk.

---

## Nginx (non-Docker)

Use [nginx.conf](nginx.conf) to serve this Laravel app behind Nginx and PHP-FPM.

## On DigitalOcean

Create an Ubuntu droplet, add your SSH key, and point your domain’s A record to the droplet IP. SSH in and follow the sections below. Install stack: `sudo apt update && sudo apt install -y nginx php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl unzip`. Deploy the app (e.g. clone repo or copy files) into `/var/www/mazeloot-backend`, then apply Nginx, SSL, Environment, and Post-deploy.

## Nginx

1. Set `root` to the absolute path of this project’s `public` directory.
2. Set `server_name` to your API host (e.g. `api.example.com`).
3. Set `fastcgi_pass` to your PHP-FPM socket (e.g. `unix:/run/php/php8.2-fpm.sock`) or `127.0.0.1:9000`.
4. Copy or symlink the config into Nginx (e.g. `/etc/nginx/sites-available/`), enable it, run `nginx -t && systemctl reload nginx`.
5. Ensure `storage` and `bootstrap/cache` are writable by the PHP-FPM user: `chown -R www-data:www-data storage bootstrap/cache`.

## SSL

Config includes HTTPS (port 443) with Let’s Encrypt paths. Get a cert: `certbot --nginx -d api.example.com`, or temporarily use HTTP-only by commenting out the `return 301` block and the `listen 443` server block.

## Environment

Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL` (e.g. `https://api.example.com`), DB and cache/queue credentials.

## Post-deploy

```bash
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

Run queue workers (e.g. `php artisan queue:work` or `queue:listen`) via Supervisor or systemd so jobs run in production.

`.htaccess` is kept for Apache; Nginx ignores it.
