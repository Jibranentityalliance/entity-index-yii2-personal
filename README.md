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
PHP_CLI_SERVER_WORKERS=4 php yii serve --router=config/dev-router.php   # http://localhost:8080
```

`php yii serve` runs PHP's built-in server, which handles one request at a time: while a large download (Bulk data) is running, every other page waits. `PHP_CLI_SERVER_WORKERS=4` starts four worker processes (PHP 7.4+). Apache with php-fpm has no such limit.

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
| `/portal/auth/forgot`, `/portal/auth/reset?token=…` | forgot password and choose a new one |

The portal UI uses stock AdminLTE 3 / Bootstrap 4 components (info-box, card, callout, btn, table, progress…) themed by `web/css/portal.css` to match the public site: Inter, 8px cards, buttons and inputs, navy primary buttons, pill tags, and one blue accent for links, icons, focus and the active nav item. Light uses the site's grey canvas with white cards; dark (AdminLTE's `dark-mode`) is a near-black, faintly navy canvas. The user menu (the initial avatar, top right) has Account settings, a Light / Dark / System theme choice and Sign out; the sign-in pages keep a sun/moon button. The choice is kept in the `ei_theme` cookie and the layouts render it server-side; System renders dark and `web/js/portal-theme.js` (loaded in `<head>`) switches to light before the page paints when the device prefers it, and follows the device when it changes. Reach for a stock component before writing CSS, and style with the theme variables at the top of `portal.css` so both themes stay in step.

The public site (`views/`, `web/css/site.css`) is Bootstrap 5 with a restrained theme set by the variables at the top of `site.css`: white and cool grey surfaces, one navy for buttons and the call-to-action band, Source Serif 4 for headings and Inter for text, 4px corners, hairline borders and no shadows, gradients or decorative imagery. Data is shown in ruled tables (`table.ledger` inside a `.panel`) instead of cards. Reuse those pieces and the tokens for new public pages.

`php yii seed/admin you@example.com 'password'` creates an admin. Paid plans are set on the `user.plan` column (`free|pro|business|bulk`) until billing exists.
API keys and the read-only REST API are documented in the **API** section below.

## Email and password reset

Mail goes through `Yii::$app->mailer` (`config/mail.php`, shared by web and console) and `app\services\Mail`, which renders an HTML and a text part from `mail/` with the layouts in `mail/layouts`. **Until `MAIL_DSN` is set** the mailer uses file transport, so every message is written to `runtime/mail/*.eml` instead of being sent; open one in a mail client or text editor to read it. Setting `MAIL_DSN` (an SMTP DSN such as `smtp://user:pass@smtp.example.com:587`) sends real mail; nothing else changes. The sender is `ADMIN_EMAIL` with the name EntityIndex.

**Password reset.** Sign in has a Forgot your password? link to `/portal/auth/forgot`. The page always answers the same way whether or not the email has an account, sends at most one link a minute per account, and allows 5 requests per IP per 15 minutes. The link (`/portal/auth/reset?token=…`) works once, for an hour (`User::RESET_TTL`); only a SHA-256 hash of the token is stored (`user.password_reset_hash`, `password_reset_expires_at`), and the page sends no referrer. Choosing a new password clears the token, rotates `auth_key` (signing the account out everywhere else, remember-me cookies included) and mails a password-changed notice. Changing the password in Account settings does the same. Disabled accounts get no link.

**Opt-out emails** are described under Admin module, Opt-outs.

## Admin module

`/admin` (module `app\modules\admin`, same layout as the portal) is for accounts with `role = admin`; guests go to the sign-in page and other users get a 403. Admins see a Database item in the sidebar. `/admin/database` reads only the portal's own tables, never `MIRROR_DB`. It shows how many companies are published, in cooldown (`publish_at` in the future) and held (`publish_at` NULL), when the next one goes live and how many go live within 1 and 24 hours, plus the import state (last company converted, backfill position, queued changes). Below, a paged live list of the companies in cooldown, soonest first, with the time each goes live and a countdown, a tab for held companies and a tab for published ones, all searchable by company name (starts with) or filing number. **Hold and release:** tick companies and press Release now (sets `publish_at` to now) or Hold (clears it, so the company disappears from the portal, search, API, CSV export and bulk files). The choice is stored in `entities.publish_override` (`hold` or `release`, with who and when) and a later sync from the source never changes `publish_at` for a company that has one, so a manual hold stays held until an admin releases it. Rows with a manual choice show a badge. Up to 500 companies per action. The totals are cached for 5 minutes (`Refresh now` bypasses it); the lists are not. `/admin/users` lists accounts with a search box and plan, role and status filters, and each account has a Manage page: name, plan, role (customer or admin), status (active or disabled; a disabled account is signed out at its next request and cannot sign in), **limits for this account**, current usage next to each limit, and a button that resets this month's usage and today's lookups. Limits are stored per account in `user.limit_overrides` (JSON) and replace the plan defaults from `config/params.php` for that account only: API calls, API companies and calls a minute, CSV export rows a month (also `unlimited`) and rows per file, free-plan lookups a day and details window, and bulk downloads a day. An empty field means "use the plan default", so changing a default in `config/params.php` still reaches everyone without an override. Limits only apply where the plan includes the feature; granting API or export access is done by changing the plan. Admins cannot change their own role or disable themselves, and there is no delete or password reset here yet (disable the account instead). New admin pages go in `modules/admin/controllers` and extend `AdminController`.

**Opt-outs.** The public form at `/opt-out` stores a pending request in `opt_out_requests`. `/admin/opt-outs` is the queue (Pending, Done, Rejected, All): a request page shows who asked and what they typed, finds the company from the pasted page URL or a state plus filing number (and can look up another reference), and lets an admin approve, reject or reopen it. **Approving hides every person on that company**: it sets `entities.people_hidden`, and from then on the officers, the registered agent and the principal and mailing addresses are left out of the company page, the officer and agent name search, the API, the CSV export and the bulk files; the company, its name, state, type, status and dates stay. The flag survives re-syncs from the source, and because the request keeps the company's state and filing key, a company that disappears from the source and comes back is hidden again (the mirror sync checks this). Reopening a request makes the people visible again unless another approved request covers the same company. **Emails** (see Email and password reset; written to `runtime/mail` until a provider is configured): submitting the form emails the requester a confirmation with the reference number and emails `ADMIN_EMAIL` the request with a link to it; approving emails the requester which company was cleared; rejecting emails a short explanation and a link to send a new request. Reopening sends nothing, and the admin's note is internal and never emailed. If a send fails the admin sees a warning to reply by hand. The public form takes at most 10 requests per IP an hour. Hiding is per company, not per person, because the form cannot reliably tell which person asked.

Not built yet: saved searches, billing (Stripe is a placeholder), admin queues for contact messages and the waitlist, and email confirmations.

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

## Bulk data

Data license (`bulk`) and admin accounts get a **Bulk data** page at `/portal/bulk`. `php yii bulk/build` (cron, once a day) writes one gzipped UTF-8 CSV with every published company and one per state (the same columns as the CSV export, rows in import order, held companies left out), records each file's row count, size and SHA-256 in `bulk_file`, and keeps the last `keepDays` daily builds (7) before deleting older files. The same run also writes **new-company files**: only the companies whose `publish_at` falls in a period, national and per state, one set per UTC day for the last `newDays` days (7) and one per Monday-to-Sunday week for the last `newWeeks` full weeks (4). These are small (about 5,000 companies a day), so every run rebuilds all of them, which drops companies held or people removed since. `php yii bulk/build-new` builds only these. `bulk_file.kind` is `full`, `daily` or `weekly`; for weekly files `build_date` is the Monday. The page has tabs for the full copy and the daily and weekly new-company files, lists each by date with a Download button per file; the download goes through `/portal/bulk/download?id=...`, which checks the plan and the daily download cap (`downloadsPerDay`, 20, admins unlimited; a resumed download does not count again), writes a `bulk_download` row and then hands the file over. Files live outside the web root (`BULK_DIR`, default `runtime/bulk`), never at a public URL.

Settings are in `config/params.php` under `bulk` and are not final: `keepDays`, `newDays`, `newWeeks`, `downloadsPerDay`, plus `dir` and `sendfile` from the environment. Delivery has two modes. `php` (default) streams the file from PHP and holds one PHP worker per download, which is fine for a few customers and bad for many. `xsendfile` makes PHP only send an `X-Sendfile` header after the checks and lets Apache's `mod_xsendfile` send the file itself, with resume support; set it up as described in docs/DEPLOY.md section 7d. Storage sits behind the `BulkStore` interface (`services/bulk`), so moving to S3 or R2 means writing a second store that uploads in `put()` and redirects to a signed URL in `send()`. Files are stored on the server itself for now and are CSV only.

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

## Saved-search alerts

Pro, Business, Data license and admin accounts can save up to 10 searches (`maxSearches`) from the Companies page (**Save search**: keywords, state, county, entity type and status; the filing date range is left out) and manage them at `/portal/alerts`: rename, turn emails on or off, delete. A search is stored once per account (`saved_search.params_hash`). Free accounts see the Alerts page with an upgrade message.

`php yii alerts/send` (cron, once each morning) emails every saved search with emails on whose owner is active and on a paid plan. Each search keeps a `cursor_at`: the email lists the companies whose `publish_at` falls after it (so held companies are never mailed early), newest filing first, up to `emailRows` (25) with a link to the full search, and the cursor then moves to the run time. No matches, no email. A search is handled once per `reportTz` day (`last_run_on`), so running the command again the same day sends nothing; `--force` ignores that. Turning emails back on moves the cursor to now, so there is no backlog. Every email has a link (`/portal/alerts/unsubscribe?token=…`, also sent as `List-Unsubscribe`) that stops emails for that one search without signing in; the page asks for a click first, so mail scanners opening the link change nothing. Settings are in `config/params.php` under `alerts`.

## Webhooks

Business, Data license and admin accounts can add up to 5 endpoints at `/portal/webhooks` (`maxEndpoints`). Each endpoint has a URL, an optional description, optional state and entity-type filters, and a signing secret (`whsec_…`, shown on the endpoint page, replaceable). Customer documentation (payload, headers, signature check in Python) is the Webhooks card on `/portal/api-docs`.

`php yii webhooks/run` (cron, every minute, guarded by a mutex) does four things:

1. **Detect.** Every company whose `publish_at` passed in the last `eventWindowHours` (72) and has no row in `webhook_event` gets one. Events are driven by `publish_at`, so held companies are never sent, a company released by an admin is sent when it is released, and each company is sent once per endpoint.
2. **Queue.** Each active endpoint keeps a cursor on `webhook_event.id` (set to the newest event when the endpoint is created, so there is no backfill). New events that match its filters and are still public become `webhook_delivery` rows of up to `batchSize` (100) companies, event `entities.published`.
3. **Deliver.** Due deliveries for active endpoints are POSTed as JSON (`{id, event, created_at, count, data}`; `data` uses the API's entity shape, so `people_hidden` and opt-outs apply, and companies that stopped being public are dropped). Headers: `X-EntityIndex-Event`, `X-EntityIndex-Delivery` (same id on every retry), `X-EntityIndex-Signature: t=…,v1=hex(HMAC-SHA256(secret, t + "." + body))`. A 2xx within `timeout` (10 s) is success; redirects are not followed. Failures retry on `retryAfter` (1 m, 5 m, 30 m, 2 h, 6 h, 12 h, 24 h), then the delivery is marked failed and can be sent again from the endpoint page. After `disableAfterFailures` (50) failed attempts in a row the endpoint is turned off; paused or turned-off endpoints keep their place and catch up when resumed.
4. **Prune.** Deliveries and events older than `keepDays` (30) are deleted, but never pending deliveries or events an endpoint has not reached yet.

Only https URLs on public addresses are accepted; the host is resolved at send time and the connection is pinned to the checked address. `WEBHOOKS_ALLOW_PRIVATE=true` lifts that (http and private addresses) for local testing; never set it in production. Deliveries do not count toward the API's monthly row cap. The endpoint page sends a test event (a fixed example company, not retried) and lists the last 50 deliveries with status, attempts and the last answer. Settings are in `config/params.php` under `webhooks`.

## Configuration (`.env` or real environment variables; real env wins)

| Variable | Meaning |
|---|---|
| `YII_ENV`, `YII_DEBUG` | `prod` / `false` in production (default is prod) |
| `DB_DSN`, `DB_USER` | **required**: MySQL connection |
| `DB_PASS` | MySQL password (may be empty) |
| `COOKIE_VALIDATION_KEY` | **required**: long random string |
| `SITE_URL` | **required**: canonical URL used in sitemaps, canonical links, JSON-LD |
| `ADMIN_EMAIL` | sender of every email and recipient of contact messages |
| `MAIL_DSN` | SMTP DSN for outgoing mail, e.g. `smtp://user:pass@smtp.example.com:587`; unset writes mail to `runtime/mail` |
| `ENV_FILE` | optional (real environment only): path of the env file, default `./.env` |
| `SITE_NOINDEX` | `true` (default): meta robots tag + `X-Robots-Tag: noindex, nofollow` on every response. Set `false` at launch |
| `BULK_DIR` | optional: folder for the bulk data files, default `runtime/bulk`; keep it outside the web root |
| `BULK_SENDFILE` | `php` (default: PHP streams the file) or `xsendfile` (Apache `mod_xsendfile` sends it; see docs/DEPLOY.md 7d) |
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

**Updating the server:** [docs/SERVER-UPDATE.md](docs/SERVER-UPDATE.md) lists what the production server needs for the work after commit `c5dcb99`: migrations, `.env` settings, new cron jobs, mail and X-Sendfile.

Pushes to `main` deploy automatically over SFTP after the tests pass: see **Automatic deploys** in [docs/DEPLOY.md](docs/DEPLOY.md). The deploy never touches `.env`, `config/db.php`, `runtime/` or `web/assets/` on the server.

## Tests

`tests/run.php` boots the app on PHP's built-in server against a **throwaway** database (it runs `migrate/fresh`, which drops every table there) and checks
public pages, the login wall in front of every product route, search, redirects, plan-based gating on company pages, noindex tags and headers, the sitemap, both forms (incl. CSRF and honeypot), portal register/login/logout/password change/lockout, forgot and reset password and the opt-out emails (mail read from `runtime/mail`), admin dashboard and the coming-soon gate.

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
