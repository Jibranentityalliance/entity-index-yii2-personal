# Deploying to an AWS Lightsail LAMP instance

Written for the Lightsail "LAMP" blueprint, which is Bitnami-based (Apache in `/opt/bitnami/apache`, PHP in `/opt/bitnami/php`,
database in `/opt/bitnami/mariadb` or `/opt/bitnami/mysql`, web user `daemon`). On a plain Ubuntu/Debian LAMP stack the paths differ
(`/etc/apache2`, user `www-data`) but the steps are the same. **Check what you actually have first:**

```bash
php -v                              # need 8.2 or newer (8.3 preferred)
php -m | grep -E "mbstring|pdo_mysql|ctype|json"   # all must be present
mysql --version                     # MySQL 8.0+ or MariaDB 10.6+
apachectl -M | grep rewrite         # rewrite_module must be loaded
```

If the instance ships MariaDB, that is fine: the schema and queries were developed against MariaDB 10.11 and CI runs MySQL 8.0.

## 1. Code and dependencies

```bash
cd /opt/bitnami                      # or /var/www
git clone https://github.com/developer-entity/entityindex-yii2.git entityindex     # private repo: use a deploy key or token
cd entityindex
composer install --no-dev --optimize-autoloader     # install composer first if it is missing
```

## 2. Database

```sql
CREATE DATABASE entityindex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'entityindex'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON entityindex.* TO 'entityindex'@'localhost';
```

## 3. Environment

```bash
cp .env.example .env
nano .env
```

```
YII_ENV=prod
YII_DEBUG=false
DB_DSN="mysql:host=127.0.0.1;dbname=entityindex"
DB_USER=entityindex
DB_PASS=<strong password>
COOKIE_VALIDATION_KEY=<output of: php -r 'echo bin2hex(random_bytes(32));'>
SITE_URL=https://entityindex.com
SITE_MODE=coming_soon          # switch to live at launch
SITE_NOINDEX=true              # keep search engines out until launch (default); set false to allow indexing
```

`chmod 600 .env`. Never commit it. (Apache `SetEnv` / php-fpm `env[]` also work and take precedence over `.env`.)

## 4. Schema, reference data, first admin

```bash
php yii migrate --interactive=0        # includes the contact_messages table
php yii seed/states                      # the 51 jurisdictions. Do NOT run seed/sample in production: it is fake data and truncates entities.
php yii seed/admin you@entityindex.com '<long password>'
```

## 5. Permissions

```bash
sudo chown -R daemon:daemon runtime web/assets      # www-data on Debian/Ubuntu
sudo chmod -R u+rwX,g+rX runtime web/assets
```

## 6. Apache virtual host

The document root **must be the `web/` directory**, not the project root (so `.env`, `config/`, `vendor/` are never served).
Bitnami: create `/opt/bitnami/apache/conf/vhosts/entityindex-vhost.conf` (and an `-https-vhost.conf` twin on port 443 once you have a certificate):

```apache
<VirtualHost 127.0.0.1:80 _default_:80>
  ServerName entityindex.com
  ServerAlias www.entityindex.com
  DocumentRoot /opt/bitnami/entityindex/web
  <Directory /opt/bitnami/entityindex/web>
    Require all granted
    AllowOverride All          # lets web/.htaccess handle pretty URLs
    Options -Indexes
  </Directory>
</VirtualHost>
```

If you prefer `AllowOverride None`, paste the three `Rewrite*` lines from `web/.htaccess` into the `<Directory>` block instead.

```bash
sudo /opt/bitnami/ctlscript.sh restart apache
```

HTTPS: `sudo /opt/bitnami/bncert-tool` (Let's Encrypt, sets up the redirect) or `certbot --apache` on Ubuntu.

## 7. Lightsail networking and DNS

- Instance firewall: allow 80 and 443 from anywhere; restrict 22 to your IP; **do not open 3306**.
- Attach a static IP, point the domain's `A` record (and `www`) at it.
- Turn on automatic snapshots (Lightsail) and/or schedule `mysqldump entityindex | gzip > backup.sql.gz` via cron.

## 8. Launch checklist

1. `SITE_MODE=coming_soon` until the real data pipeline is loading; check `/coming-soon`, `/opt-out`, `/legal/terms`.
2. Switch to `SITE_MODE=live`, **and `SITE_NOINDEX=false` when you want search engines in** (until then every page has `<meta name="robots" content="noindex,nofollow">` and an `X-Robots-Tag` header), then `php yii cache/flush-all`.
3. Add the `Sitemap:` line back to `web/robots.txt` and submit `https://entityindex.com/sitemap.xml` in Google Search Console. `web/robots.txt` hard-codes the sitemap URL for entityindex.com; edit it if the domain differs.
4. Replace the placeholder text on `/legal/privacy` and `/legal/terms` (they are draft outlines).
5. Configure real outgoing mail (`components.mailer` in `config/web.php`) before adding features that send email.

## Automatic deploys (GitHub Actions → SFTP)

Every push to `main` runs the tests (`ci.yml`, job `test`) and, if they pass, the `deploy` job uploads the site over SFTP with a private key
(`scripts/deploy-sftp.sh`, built on `lftp`). Pull requests only run the tests.

**What the deploy does**
- Uploads the application code and **deletes files on the server that no longer exist in the repo** (inside the uploaded folders).
- Uploads `vendor/` only when `composer.lock` changed (the hash is kept in `.deploy-vendor-hash` on the server).
- **Never touches** `.env*`, `config/db.php`, `runtime/` and `web/assets/`: those belong to the server. `config/db.php` is not deployed at all;
  put it on the server once by hand (copy the repo's file, it reads `DB_*` from `.env`) and change it there if you ever need to.
- Does not upload `tests/`, `docs/`, `scripts/`, `.git*`, `.github/`, `data/sample/`.
- **Cannot run commands** (SFTP only). After a release that adds a migration, SSH in and run `php yii migrate --interactive=0 && php yii cache/flush-all`.

**One-time server setup (Debian)**

```bash
# 1. deploy user + key (on your laptop)
ssh-keygen -t ed25519 -f entityindex_deploy -N "" -C "github-actions-deploy"

# 2. on the server
sudo adduser --disabled-password --gecos "" deploy
sudo -u deploy mkdir -p /home/deploy/.ssh && sudo -u deploy chmod 700 /home/deploy/.ssh
cat entityindex_deploy.pub | sudo -u deploy tee -a /home/deploy/.ssh/authorized_keys   # then chmod 600 the file
sudo mkdir -p /var/www/entityindex && sudo chown deploy:www-data /var/www/entityindex
sudo chmod 2775 /var/www/entityindex                      # new files inherit the www-data group
# first deploy creates runtime/ and web/assets/ as the deploy user; let Apache write to them:
sudo chmod -R g+rwX /var/www/entityindex/runtime /var/www/entityindex/web/assets   # after the first deploy
# put .env and config/db.php in place (see sections 3 and 4 above)
```

Apache (Debian): `DocumentRoot /var/www/entityindex/web`, `AllowOverride All`, `a2enmod rewrite headers`, run Apache as `www-data`. Make sure the
`deploy` user's umask gives group write (`umask 002` in its `~/.profile`) so files uploaded later stay readable by Apache.

**GitHub setup** — repo *Settings → Environments → New environment* named `production` (optionally add required reviewers), then add these *environment secrets*:

| Secret | Value |
|---|---|
| `SFTP_HOST` | server hostname or Elastic IP |
| `SFTP_PORT` | SSH port (leave empty for 22) |
| `SFTP_USERNAME` | `deploy` |
| `SFTP_PRIVATE_KEY` | full contents of `entityindex_deploy` (the private key, including the BEGIN/END lines) |
| `SFTP_KNOWN_HOSTS` | output of `ssh-keyscan -p 22 your.host` run from a machine you trust (pins the server's host key; the deploy fails if it changes) |
| `SFTP_REMOTE_PATH` | `/var/www/entityindex` (absolute project root; its `web/` is the DocumentRoot) |

The AWS security group must allow SSH (22) from GitHub's runners, which have no fixed IPs. Either allow 22 from anywhere and rely on key-only login
(`PasswordAuthentication no`), or allow GitHub's published ranges (`https://api.github.com/meta` → `actions`).

**Try it safely first:** run the script by hand with `DEPLOY_DRY_RUN=1` (lists changes, uploads nothing):
`composer install --no-dev -o && SFTP_HOST=… SFTP_USERNAME=deploy SFTP_KEY_FILE=~/.ssh/entityindex_deploy SFTP_KNOWN_HOSTS_FILE=./known_hosts SFTP_REMOTE_PATH=/var/www/entityindex DEPLOY_DRY_RUN=1 bash scripts/deploy-sftp.sh`

## Updating

```bash
cd /opt/bitnami/entityindex
git pull
composer install --no-dev --optimize-autoloader
php yii migrate --interactive=0
php yii cache/flush-all
```

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Home page works, other URLs 404 | `mod_rewrite` off or `AllowOverride None` without the rewrite rules in the vhost |
| 500 error, nothing on screen | check `runtime/logs/app.log` and the Apache error log; usually `.env` unreadable or `runtime/` not writable |
| "Unable to verify your data submission" | cookies blocked, or `COOKIE_VALIDATION_KEY` changed since the form was loaded |
| Search returns nothing for a short word | words under 3 characters use `LIKE`; longer words match at the start of a word (FULLTEXT prefix) |
| Pages show no styling | the server cannot reach the CDNs (jsDelivr, cdnjs, code.jquery.com) |
