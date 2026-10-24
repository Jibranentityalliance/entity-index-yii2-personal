# EntityIndex (Yii2)

Searchable index of new US business filings (LLCs, corporations) from all 50 states and DC.
This is the PHP rewrite of the original Next.js prototype (`developer-entity/entityindex`).

| Layer | Stack |
|---|---|
| Frontend | HTML, CSS, JavaScript, jQuery 3.7, Bootstrap 5.3 (public site), AdminLTE 3 / Bootstrap 4 (portal) — all loaded from CDNs |
| Backend | PHP 8.2+, Yii2 **basic** template (2.0.5x) |
| Database | MySQL 8 (MariaDB 10.6+ also works; developed against 10.11) |
| Hosting | AWS Lightsail LAMP instance (see [docs/DEPLOY.md](docs/DEPLOY.md)) |

## Quick start (local)

```bash
composer install
cp .env.example .env              # then set DB_* and COOKIE_VALIDATION_KEY
mysql -e "CREATE DATABASE entityindex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php yii migrate --interactive=0   # create tables
php yii seed/all                  # 51 jurisdictions + ~6,000 FAKE sample companies
php yii seed/admin you@example.com 'a-long-password'
php yii serve --router=config/dev-router.php   # http://localhost:8080
```

`seed/sample` loads **generated sample data** (`data/sample/entities.json`), not real filings. Use `seed/states` alone in production.
The real ingestion pipeline that fills `entities`, `people`, `filings`, … is a separate system and is not part of this repo.

## Pages

**Public** (Bootstrap 5, no login): `/` landing · `/features` · `/pricing` · `/faq` · `/about` · `/contact` · `/legal/terms` · `/legal/privacy` · `/opt-out` · `/coming-soon` · `/sitemap.xml` · `/robots.txt`
Forms (AJAX, CSRF-protected, honeypot): `POST /api/waitlist`, `POST /api/opt-out`, `POST /api/contact`

**Portal** (`modules/portal`, AdminLTE 3, login required for everything except the auth pages):

| Route | Page |
|---|---|
| `/portal` | dashboard: last-7-days cards (companies, states, structured addresses) and the top 10 states |
| `/portal/companies` | every company, newest first, 50 per page; keyword, state, type and filing-date-range filters (`?state=FL` is the per-state list) |
| `/portal/company/{state}/{slug}-{filing-key}` | company record (officers and addresses are visible on paid plans and to admins, locked on Free) |
| `/portal/states` | States: coverage and freshness per jurisdiction (last 24h, last 7 days, median lag, status), filterable by name, source, median lag, filing dates and status; a state's name opens Companies filtered to it |
| `/portal/account` | profile and password |
| `/portal/auth/login`, `/portal/auth/register`, `/portal/auth/logout` (POST) | authentication |

The portal UI uses stock AdminLTE 3 / Bootstrap 4 components (info-box, card, callout, btn, table, progress…) in AdminLTE's `dark-mode`, themed by `web/css/portal.css`: near-black canvas, monochrome surfaces separated by 1px borders (no shadows), pill buttons and tags, 16px cards, 8px inputs, Inter, and a single green reserved for primary actions, the logo and the active nav item. A sun/moon button in the top bar (and on the sign-in pages) switches to a light variant of the same theme; the choice is kept in the `ei_theme` cookie and the layouts render it server-side, so there is no flash on load. Reach for a stock component before writing CSS, and style with the theme variables at the top of `portal.css` so both themes stay in step.

`php yii seed/admin you@example.com 'password'` creates an admin. Paid plans are set on the `user.plan` column (`free|pro|business|bulk`) until billing exists.
Not built yet: saved searches, CSV export, API keys / API v1, billing (Stripe is a placeholder), usage limits, admin queues for contact / opt-out / waitlist.

## Configuration (`.env` or real environment variables; real env wins)

| Variable | Meaning |
|---|---|
| `YII_ENV`, `YII_DEBUG` | `prod` / `false` in production (default is prod) |
| `DB_DSN`, `DB_USER` | **required**: MySQL connection |
| `DB_PASS` | MySQL password (may be empty) |
| `COOKIE_VALIDATION_KEY` | **required**: long random string |
| `SITE_URL` | **required**: canonical URL used in sitemaps, canonical links, JSON-LD |
| `ENV_FILE` | optional (real environment only): path of the env file, default `./.env` |
| `SITE_NOINDEX` | `true` (default): meta robots tag + `X-Robots-Tag: noindex, nofollow` on every response. Set `false` at launch |
| `SITE_MODE` | `live`, or `coming_soon` to show only the waitlist page (opt-out, legal pages and the form APIs stay reachable) |

The site will not start if a required setting is missing: it shows a "Configuration error" page, logs the cause to `runtime/logs/config-error.log`, and `php yii env/check` lists what is missing (never printing values). `.env` must be readable by the web server user (`chmod 640`, group `www-data`).

Prices and plan copy live in `config/params.php` (`tiers`).

## Layout

```
config/            web.php, console.php, params.php, faq.php, env.php (.env loader), dev-router.php
controllers/       public controllers (Site, Pricing, OptOut, Legal, Forms, Sitemap)
modules/portal/    logged-in product: controllers (Default, Search, Company, New, States, Account, Auth), forms, AdminLTE views + layouts
models/            ActiveRecord: Entity, Person, Filing, RegisteredAgent, Source, User, Waitlist, OptOutRequest, ContactMessage
services/          EntityRepository: search, daily lists, per-state stats, related companies
helpers/           Fmt (dates, URLs, badges), States (51 jurisdictions)
migrations/        MySQL schema (replaces the old Postgres db/schema.sql)
commands/          SeedController (seed/states, seed/sample, seed/all, seed/admin)
views/, web/       Bootstrap views and assets (css/site.css, js/site.js)
data/              states.json, sample/entities.json
tests/run.php      integration tests
```

## Deployment

Pushes to `main` deploy automatically over SFTP after the tests pass: see **Automatic deploys** in [docs/DEPLOY.md](docs/DEPLOY.md). The deploy never touches `.env`, `config/db.php`, `runtime/` or `web/assets/` on the server.

## Tests

`tests/run.php` boots the app on PHP's built-in server against a **throwaway** database (it runs `migrate/fresh`, which drops every table there) and checks
public pages, the login wall in front of every product route, search, redirects, plan-based gating on company pages, noindex tags and headers, the sitemap, both forms (incl. CSRF and honeypot), portal register/login/logout/password change/lockout, admin dashboard and the coming-soon gate.

```bash
TEST_DB_DSN="mysql:host=127.0.0.1;dbname=entityindex_test" TEST_DB_USER=root TEST_DB_PASS= composer test
```

CI (`.github/workflows/ci.yml`) runs the same script against MySQL 8.0.

## Differences from the Next.js version

- **Search:** Postgres trigram matching became MySQL `FULLTEXT` (word-prefix matching: "roof" finds "Roofing LLC", but not "Waterproofing"). Words shorter than 3 characters and InnoDB stop-words fall back to `LIKE`.
- **Caching:** ISR (`revalidate`) became Yii data cache (5 minutes for counters and per-state stats). Run `php yii cache/flush-all` after bulk loads.
- **Dates:** stored as UTC `DATETIME`; shown in America/New_York where a time is displayed.
- **Assets:** jQuery, Bootstrap and AdminLTE come from CDNs (jsDelivr, cdnjs, code.jquery.com). `composer.json` therefore replaces the `bower-asset/*` packages so Composer does not need asset-packagist.
- **Access:** the product (search, company pages, daily feed, coverage) is behind login in the portal; only marketing and legal pages are public, so `/sitemap.xml` lists just those.
