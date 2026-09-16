# Kabataan — Hostinger production deployment

Production domain: `https://kabataan.skoneportal.com`

PHP requirement: **8.2 or newer** (Laravel 12).

Do **not** run `composer run setup` on Hostinger. That script generates a new `APP_KEY` and runs migrations automatically.

Do **not** set `APP_DEBUG=true` on production.

Do **not** rotate `APP_KEY` if one already exists.

---

## 1. Hostinger settings

1. Point the domain document root to `Kabataan/public`.
2. If Hostinger only allows the project root, keep the existing project-root `.htaccess` (it rewrites into `public/` and blocks `.env`).
3. PHP 8.2+
4. Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `json`, `ctype`, `fileinfo`, `xml`, `curl`, `zip`, `gd` or `imagick`, `bcmath`
5. Cloudflare Turnstile hostname: `kabataan.skoneportal.com`
6. Force HTTPS

---

## 2. Upload / pull

Upload or git-pull the `Kabataan` project. Include `public/build` after a frontend build. Do not upload a local `.env`.

---

## 3. Composer

```bash
composer install --no-dev --optimize-autoloader
```

---

## 4. Configure `.env`

```env
APP_NAME=Kabataan
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kabataan.skoneportal.com
APP_PUBLIC_URL=https://kabataan.skoneportal.com
KABATAAN_APP_URL=https://kabataan.skoneportal.com
SK_OFFICIALS_APP_URL=https://skofficials.skoneportal.com
SK_FED_APP_URL=https://skfederations.skoneportal.com

APP_KEY=base64:...existing production key...

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Kabataan"

TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

OCR_API_ENABLED=false
TESSERACT_PATH=
```

`DB_HOST=localhost` is normal on Hostinger. Do not copy local SQLite settings.
Do not copy `TESSERACT_PATH=C:\Program Files\...` to Linux.

---

## 5. Verify APP_KEY

```bash
php artisan about
```

If the encryption key is missing, paste the **existing** production `APP_KEY`. Do not run `php artisan key:generate` on a live site that already has users.

---

## 6. Verify database

```bash
php artisan db:show
```

Open `https://kabataan.skoneportal.com/health`.

---

## 7. Build frontend assets

```bash
npm install
npm run build
```

Laravel will 500 on `/sign-in` if `public/build/manifest.json` is missing.

---

## 8. Storage

```bash
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

Create the public storage link only if it does not already exist:

```bash
php artisan storage:link
```

Do not use `chmod 777`.

---

## 9. Clear cache, then cache only after config is confirmed

```bash
php artisan optimize:clear
```

Confirm `.env` (no localhost, no `hostingersite.com`, `APP_DEBUG=false`). Then:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If caching fails:

```bash
php artisan optimize:clear
```

---

## 10. Safe migrations (only when required)

```bash
php artisan migrate --force
```

Never run `migrate:fresh`, `migrate:reset`, `db:wipe`, or `db:seed` unless explicitly requested.

---

## 11. Test

1. `https://kabataan.skoneportal.com` redirects to homepage
2. `/sign-in`, logout, dashboard, profile
3. KK Profiling, password setup, forgot password
4. Emails use `https://kabataan.skoneportal.com`
5. Communications / chat
6. Programs / forms / uploads
7. Turnstile validation errors, not 500s
8. No localhost or `hostingersite.com` in page source
9. `storage/logs/laravel.log` has the real exception if anything fails

---

## 12. Check logs

```bash
tail -n 200 storage/logs/laravel.log
```
