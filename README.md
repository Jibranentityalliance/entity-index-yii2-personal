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
| `/portal/account` | profile and password (Account settings in the user menu) |
| `/portal/auth/login`, `/portal/auth/register`, `/portal/auth/logout` (POST) | authentication |

The portal UI uses stock AdminLTE 3 / Bootstrap 4 components (info-box, card, callout, btn, table, progress…) in AdminLTE's `dark-mode`, themed by `web/css/portal.css`: near-black canvas, monochrome surfaces separated by 1px borders (no shadows), pill buttons and tags, 16px cards, 8px inputs, Inter, and a single green reserved for primary actions, the logo and the active nav item. The user menu (the initial avatar, top right) has Account settings, a Light / Dark / System theme choice and Sign out; the sign-in pages keep a sun/moon button. The choice is kept in the `ei_theme` cookie and the layouts render it server-side; System renders dark and `web/js/portal-theme.js` (loaded in `<head>`) switches to light before the page paints when the device prefers it, and follows the device when it changes. Reach for a stock component before writing CSS, and style with the theme variables at the top of `portal.css` so both themes stay in step.

`php yii seed/admin you@example.com 'password'` creates an admin. Paid plans are set on the `user.plan` column (`free|pro|business|bulk`) until billing exists.
API keys and the read-only REST API are documented in the **API** section below.

## Admin module

`/admin` (module `app\modules\admin`, same layout as the portal) is for accounts with `role = admin`; guests go to the sign-in page and other users get a 403. Admins see a Database item in the sidebar. `/admin/database` reads only the portal's own tables, never `MIRROR_DB`. It shows how many companies are published, in cooldown (`publish_at` in the future) and held (`publish_at` NULL), when the next one goes live and how many go live within 1 and 24 hours, plus the import state (last company converted, backfill position, queued changes). Below, a paged live list of the companies in cooldown, soonest first, with the time each goes live and a countdown, a tab for held companies and a tab for published ones, all searchable by company name (starts with) or filing number. **Hold and release:** tick companies and press Release now (sets `publish_at` to now) or Hold (clears it, so the company disappears from the portal, search, API, CSV export and bulk files). The choice is stored in `entities.publish_override` (`hold` or `release`, with who and when) and a later sync from the source never changes `publish_at` for a company that has one, so a manual hold stays held until an admin releases it. Rows with a manual choice show a badge. Up to 500 companies per action. The totals are cached for 5 minutes (`Refresh now` bypasses it); the lists are not. New admin pages go in `modules/admin/controllers` and extend `AdminController`.

Not built yet: saved searches, billing (Stripe is a placeholder), admin queues for contact / opt-out / waitlist.

## Publishing rules (held companies)

Every company has a `publish_at` time (`entities.publish_at`, UTC) and is invisible everywhere (portal, search, company pages, counts, API, CSV export) until that time has passed; there is no job, reads simply filter on `publish_at <= now`. The mirror sync (`MirrorConverter::publishAt`) sets it from the source row's `qualified` column and `date_added`: `qualified = 0` is published on arrival, `qualified = 1` is held for `publishHoldHours` (48, in `config/params.php`; not final), and `qualified` NULL is held until the source sets it. The newest source row for a company wins, so a row that flips to 0 publishes the company the next time it is synced. More factors (skip-trace result, service status, an admin hold or release) change only that one function. The migration sets `publish_at = indexed_at` for companies that already exist; run `php yii mirror/backfill --restart` once after deploying to recompute them from the source. The landing-page numbers (`MirrorStats`) still count source rows, held ones included.

## Free plan limits

Free accounts are limited in `config/params.php` under `freeLimits`; **the numbers are not final, change them there and nothing else**: `historyDays` (7) and `lookupsPerDay` (10). Paid plans and admins have neither limit. The pricing page text reads these values.

- **Search everything, open the last 7 days.** Search, filters and the States page cover every filing, so free users can see what exists. Company pages only open for filings from the last `historyDays` days, counted back from the latest filing day (the same window the dashboard uses); older companies show a lock icon in the list and a locked page with an upgrade prompt. Free keyword search matches company names, registered agents and filing numbers, not officer names.
- **Company pages.** Free accounts see name, state, type, filing date, status and the registered agent (name and address; the phone is locked). Filing number, effective date, industry, county, source link, officers, addresses and phones need a paid plan.
- **Lookups.** Opening a company page is a lookup. Free accounts get `lookupsPerDay` different companies per New York calendar day (`reportTz`); reopening one already opened that day is free, and at the limit new companies return 429 with an upgrade prompt. Lookups are stored in `company_lookup` (user, day, company).

## CSV export

Paid plans and admins get an **Export CSV** button on `/portal/companies`; it downloads the current filters (keywords, state, type, filing dates) as a UTF-8 CSV, newest filing first, with officers, registered agent, addresses and phones. Free accounts see a locked button. The Companies page shows a "CSV export this month" usage card with a progress bar, and the button opens a confirmation dialog listing the filters and the number of rows; after the file downloads the page reloads so the usage card is current. The download is a POST (`/portal/export/companies`, CSRF-protected) because it uses up quota. Cells that start with `=`, `+`, `-` or `@` are prefixed with a quote so spreadsheets do not run them as formulas.

**Export limits are not final and are meant to be changed.** They live in `config/params.php` under `exportLimits`, per plan (`pro`, `business`, `bulk`, `admin`): `monthlyRows` (rows exported a calendar month, UTC, counted separately from the API row cap; `null` is unlimited) and `perFile` (most rows in one download). A plan missing from the list cannot export. Current placeholders: Pro 5,000 rows a month, Business 50,000, Data license 1,000,000, admins unlimited, with files capped at the monthly amount (100,000 for Data license and admins). The pricing page text for Pro and Business reads these numbers. A file is cut short when fewer rows are left than it would contain, and the button is disabled once none are left. Usage is kept in `api_usage` under the `x:YYYY-MM` bucket.

## API (v1)

Customer-facing documentation is the portal page `/portal/api-docs` (`modules/portal/views/api-docs/index.php`); update it when endpoints or parameters change.

Business, Data license and admin accounts can create up to 5 keys at `/portal/api-keys`. Send a key as `Authorization: Bearer eik_...` (or `X-API-Key`). Responses are JSON, GET only. Officers, addresses and phone numbers are always included, since the API is paid-only.

| Endpoint | Returns |
|---|---|
| `GET /api/v1/me` | the key's account and plan |
| `GET /api/v1/entities` | companies, newest first, with cursor paging |
| `GET /api/v1/entities/{id}` | one company with its filings |
| `GET /api/v1/entities/{state}/{filing_number}` | the same, by state filing number |

List filters: `q`, `state` (2 letters), `type`, `status`, `county`, `filed_after` and `filed_before` (dates, both inclusive), `order` (`desc` default, `asc` for incremental syncs), `limit` (1 to 1000, default 100), `cursor`. Take `next_cursor` from a response and send it as `cursor` for the next page until `has_more` is false. Paging walks company ids, so it stays fast and stable on any number of rows, and new companies never shift pages already read. **API limits are not final and are meant to be changed.** They live in one place, `config/params.php`, under `apiLimits`, per plan (`business`, `bulk`; admins are unlimited): `monthlyCalls` (requests a calendar month, UTC), `monthlyRows` (companies returned a calendar month, the "export rows" cap from the pricing page) and `perMinute` (requests a minute). Edit the numbers there and nothing else; the keys page, the API headers and the docs page all read them. Current placeholders: Business 25,000 calls, 50,000 rows and 120 a minute; Data license 500,000 calls, 5,000,000 rows and 600 a minute. Every response carries `X-RateLimit-*` (per minute), `X-Quota-*` (calls in the month) and `X-Rows-*` (rows in the month) headers. The row cap is hard: a list request is cut short when fewer rows are left than it asked for, and once none are left every call returns `429` with `Retry-After`. Going over any limit returns `429`; calls rejected by the per-minute limit are not counted. Usage is shown on the API keys page and in `/api/v1/me`. The key is only stored as a hash, so a lost key cannot be recovered.

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
