# Deploying to a Hostinger VPS

Target: `hrms.esyncsoftware.my`, one origin serving both the API and the Vue
SPA. Laravel lives **outside** the web root; only its front controller and the
built assets sit in `public_html`.

Final layout:

```
/home/user/web/hrms.esyncsoftware.my/
├── private/
│   └── hrms/                 ← the whole Laravel app (NOT web-accessible)
│       ├── app/ bootstrap/ config/ database/ routes/ src/ vendor/ …
│       └── resources/spa/index.html   ← built Vue shell, served by web.php
└── public_html/              ← DOCROOT (what the web server serves)
    ├── index.php             ← edited to point at ../private/hrms
    ├── .htaccess             ← Laravel's
    └── assets/               ← built Vue JS/CSS (hashed filenames)
```

Why this split: if the app sat in `public_html`, anyone could fetch
`/.env`, `/composer.json`, or your source. Keeping it in `private/` makes that
impossible — the web server can only see `public_html`.

---

## 1. System packages (once)

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
  unzip git mysql-server

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 20 (to build the frontend on the server)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

php -v && composer -V && node -v && mysql --version
```

## 2. Database

```bash
sudo mysql <<'SQL'
CREATE DATABASE hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hrms'@'127.0.0.1' IDENTIFIED BY 'PUT_A_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON hrms.* TO 'hrms'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

## 3. Get the code into `private/hrms`

Upload `payroll-portal.zip` (from your machine) then unzip:

```bash
# from your local machine:
scp payroll-portal.zip root@YOUR_SERVER_IP:/home/user/web/hrms.esyncsoftware.my/private/

# back on the server:
cd /home/user/web/hrms.esyncsoftware.my/private
unzip payroll-portal.zip
mv payroll-portal hrms
rm payroll-portal.zip
cd hrms
```

## 4. Backend install + configure

```bash
composer install --no-dev --optimize-autoloader

cp .env.production.example .env
php artisan key:generate

# Edit .env — set DB_PASSWORD to the password from step 2, confirm APP_URL.
nano .env

php artisan migrate --force
php artisan db:seed --force        # ONCE only — creates the demo staff
```

## 5. Build the frontend

```bash
cd frontend
npm ci
npm run build                       # outputs to frontend/dist
cd ..

# Put the SPA shell where web.php serves it, and the assets under the web root.
mkdir -p resources/spa
cp frontend/dist/index.html resources/spa/index.html
```

## 6. Wire up `public_html` (the web root)

```bash
cd /home/user/web/hrms.esyncsoftware.my

# Laravel's front controller + .htaccess become the docroot.
cp -r private/hrms/public/. public_html/

# The built Vue assets.
cp -r private/hrms/frontend/dist/assets public_html/assets

# Point the front controller at the app in private/.
sed -i "s#__DIR__.'/../vendor/autoload.php'#__DIR__.'/../private/hrms/vendor/autoload.php'#" public_html/index.php
sed -i "s#__DIR__.'/../bootstrap/app.php'#__DIR__.'/../private/hrms/bootstrap/app.php'#" public_html/index.php
```

Verify `public_html/index.php` now requires `../private/hrms/vendor/autoload.php`
and `../private/hrms/bootstrap/app.php`.

## 7. Permissions

The web server user (usually `www-data`) needs to write `storage` and
`bootstrap/cache` and read the rest.

```bash
cd /home/user/web/hrms.esyncsoftware.my
sudo chown -R www-data:www-data private/hrms/storage private/hrms/bootstrap/cache
sudo find private/hrms/storage -type d -exec chmod 775 {} \;
sudo find private/hrms/storage -type f -exec chmod 664 {} \;
```

## 8. Cache for production

```bash
cd private/hrms
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run these three after any `.env` or code change.

## 9. Queue worker (payslip PDFs)

Payslip PDFs render on a queue. Without a worker they are generated on demand at
download time instead — slower, but not broken. To pre-render them, run a worker
under systemd:

```bash
sudo tee /etc/systemd/system/hrms-worker.service >/dev/null <<'UNIT'
[Unit]
Description=HRMS queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
WorkingDirectory=/home/user/web/hrms.esyncsoftware.my/private/hrms
ExecStart=/usr/bin/php artisan queue:work --queue=documents,default --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
UNIT

sudo systemctl daemon-reload
sudo systemctl enable --now hrms-worker
sudo systemctl status hrms-worker --no-pager
```

## 10. HTTPS

If the domain is managed in Hostinger's panel, issue the SSL certificate there.
On a bare VPS, use certbot:

```bash
sudo apt install -y certbot python3-certbot-apache   # or -nginx
sudo certbot --apache -d hrms.esyncsoftware.my
```

---

## First sign-in

- HR: `farhanna@esync.com.my`
- Manager: `sanand@esync.com.my`
- All demo accounts use the password `password`.

**Change these immediately.** Sign in as HR, reset the passwords from the
Employees page, and delete or repurpose any demo accounts you don't need. In
production `APP_DEBUG` is already `false`, so errors won't leak details.

## Redeploying later

```bash
cd /home/user/web/hrms.esyncsoftware.my/private/hrms
# upload new code, then:
composer install --no-dev --optimize-autoloader
php artisan migrate --force
cd frontend && npm ci && npm run build && cd ..
cp frontend/dist/index.html resources/spa/index.html
cp -r frontend/dist/assets/. ../../public_html/assets/
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart hrms-worker
```
