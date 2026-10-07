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

Public (no login): `/` · `/search` · `/company/{state}/{slug}-{filing-key}` · `/new` · `/new/{state}` · `/states` · `/pricing` · `/opt-out` · `/legal/privacy` · `/legal/terms` · `/coming-soon` · `/sitemap.xml` · `/robots.txt`
Forms (AJAX, CSRF-protected): `POST /api/waitlist`, `POST /api/opt-out`

**Portal** (`modules/portal`, AdminLTE, login required): `/portal` dashboard · `/portal/account` profile + password ·
`/portal/auth/login` · `/portal/auth/register` · `/portal/auth/logout` (POST). Admin users (`php yii seed/admin`) also see waitlist and opt-out counters.
Not built yet: saved searches, CSV export, API keys / API v1, billing (pricing page is static, Stripe is a placeholder), admin queues.

## Configuration (`.env` or real environment variables; real env wins)

| Variable | Meaning |
|---|---|
| `YII_ENV`, `YII_DEBUG` | `prod` / `false` in production (default is prod) |
| `DB_DSN`, `DB_USER`, `DB_PASS` | MySQL connection |
| `COOKIE_VALIDATION_KEY` | long random string |
| `SITE_URL` | canonical URL used in sitemaps, canonical links, JSON-LD |
| `SITE_MODE` | `live`, or `coming_soon` to show only the waitlist page (opt-out, legal pages and the form APIs stay reachable) |

Prices and plan copy live in `config/params.php` (`tiers`).

## Layout

```
config/            web.php, console.php, params.php, env.php (.env loader), dev-router.php
controllers/       public controllers (Site, Search, Company, New, States, Pricing, OptOut, Legal, Forms, Sitemap)
modules/portal/    logged-in area: controllers, models (login/register/account forms), AdminLTE views + layouts
models/            ActiveRecord: Entity, Person, Filing, RegisteredAgent, Source, User, Waitlist, OptOutRequest
services/          EntityRepository: search, daily lists, per-state stats, related companies
helpers/           Fmt (dates, URLs, badges), States (51 jurisdictions)
migrations/        MySQL schema (replaces the old Postgres db/schema.sql)
commands/          SeedController (seed/states, seed/sample, seed/all, seed/admin)
views/, web/       Bootstrap views and assets (css/site.css, js/site.js)
data/              states.json, sample/entities.json
tests/run.php      integration tests
```

## Tests

`tests/run.php` boots the app on PHP's built-in server against a **throwaway** database (it runs `migrate/fresh`, which drops every table there) and checks
public pages, search, redirects, sitemaps, both forms (incl. CSRF and honeypot), portal register/login/logout/password change/lockout, admin dashboard and the coming-soon gate.

```bash
TEST_DB_DSN="mysql:host=127.0.0.1;dbname=entityindex_test" TEST_DB_USER=root TEST_DB_PASS= composer test
```

CI (`.github/workflows/ci.yml`) runs the same script against MySQL 8.0.

## Differences from the Next.js version

- **Search:** Postgres trigram matching became MySQL `FULLTEXT` (word-prefix matching: "roof" finds "Roofing LLC", but not "Waterproofing"). Words shorter than 3 characters and InnoDB stop-words fall back to `LIKE`.
- **Caching:** ISR (`revalidate`) became Yii data cache (5 minutes for counters and per-state stats). Run `php yii cache/flush-all` after bulk loads.
- **Dates:** stored as UTC `DATETIME`; shown in America/New_York where a time is displayed.
- **Assets:** jQuery, Bootstrap and AdminLTE come from CDNs (jsDelivr, cdnjs, code.jquery.com). `composer.json` therefore replaces the `bower-asset/*` packages so Composer does not need asset-packagist.
- **Sitemaps:** `/sitemap.xml` is a sitemap index pointing at `/sitemap-company-{n}.xml` (50,000 URLs each; file 0 also holds the static and state pages).
