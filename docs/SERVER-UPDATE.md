# Server update: changes after c5dcb99

For whoever administers the production server over SSH. Every step below runs on the server.

The production server runs commit `c5dcb99` (email with password reset and opt-out notifications). This is everything the server needs
for the work after it: customer webhooks, daily and weekly new-company bulk files, saved-search alerts, and real outgoing mail.
Steps that were already set up before `c5dcb99` (database, Apache, `.env` basics, the mirror, stats, EIN sync and full bulk-file jobs) are not repeated;
[DEPLOY.md](DEPLOY.md) covers them.

Paths assume Debian/Ubuntu (`/var/www/entityindex`, web user `www-data`). On the Lightsail Bitnami image use `/opt/bitnami/entityindex` and the user `daemon`.

## 1. Deploy the code

Push to `main`. The SFTP deploy uploads the new and changed files, including `config/url.php` and the new `config/mail.php`.
It cannot run commands, so the rest is done over SSH.

## 2. Migrations

```bash
cd /var/www/entityindex
sudo -u www-data php yii migrate --interactive=0
sudo -u www-data php yii cache/flush-all
```

Four new migrations:

| Migration | Adds |
|---|---|
| `m261125_000001_create_webhooks` | `webhook_endpoint`, `webhook_event`, `webhook_delivery` |
| `m261126_000001_add_bulk_file_kind` | `bulk_file.kind` (full, daily, weekly) and a new unique index |
| `m261127_000001_create_saved_search` | `saved_search` |
| `m261128_000001_add_entities_query_indexes` | Query indexes on `entities`: state and entity type, state and county by filing date, state and publish time, filing number (`url_key`), and publish time with indexed time. Replaces two narrower indexes. Built online, without locking the table: several minutes on 6 million rows, during which the site keeps working. Check free disk space first; the new indexes take roughly 1 GB. |

## 3. `.env`

| Setting | Value | Notes |
|---|---|---|
| `MAIL_DSN` | `"smtp://USERNAME:PASSWORD@smtp.provider.com:587"` | New. Without it mail is still written to `runtime/mail` and nobody receives password resets, opt-out confirmations or alerts. |
| `ADMIN_EMAIL` | an address on the domain verified with the mail provider | Already existed; it is the sender of every email. |
| `WEBHOOKS_ALLOW_PRIVATE` | leave unset | New, local testing only. Setting it lets customers point webhooks at internal addresses. |

In `MAIL_DSN`, characters in the user or password other than letters, digits, `-`, `_` and `.` must be URL-encoded (`@` is `%40`, `/` is `%2F`).
Use port 587: Lightsail and most VPS hosts block outgoing port 25.

After editing: `sudo -u www-data php yii cache/flush-all`, then `sudo -u www-data php yii env/check`, which now also lists `MAIL_DSN`, `BULK_DIR` and `BULK_SENDFILE`.

## 4. Mail provider and DNS

- [ ] An account with a transactional email provider that gives SMTP credentials (Postmark, Amazon SES, Mailgun, SendGrid, Brevo or similar).
- [ ] The sending domain verified with it, and its DNS records added: SPF (`TXT`), DKIM (the records the provider gives), and DMARC
  (`_dmarc` `TXT`, start with `v=DMARC1; p=none; rua=mailto:<address>`). Without these, mail lands in spam.
- [ ] Use Forgot your password on the live site and confirm the email arrives in the inbox.

## 5. Cron

Cron jobs that are new or changed since `c5dcb99`, for the web user's crontab (`sudo crontab -u www-data -e`). Add `webhooks/run` and `alerts/send`.
`stats/refresh` and `bulk/build` should already be there with these exact lines; they are listed because they now do more. Leave every other existing job as it is.

```cron
* * * * * cd /var/www/entityindex && php yii webhooks/run >> runtime/logs/webhooks.log 2>&1
*/5 * * * * cd /var/www/entityindex && php yii stats/refresh >> runtime/logs/stats.log 2>&1
30 3 * * * cd /var/www/entityindex && php yii bulk/build >> runtime/logs/bulk.log 2>&1
CRON_TZ=America/New_York
0 7 * * * cd /var/www/entityindex && php yii alerts/send >> runtime/logs/alerts.log 2>&1
```

| Job | Schedule | Since `c5dcb99` | What it does |
|---|---|---|---|
| `webhooks/run` | every minute | **new** | Sends newly published companies to customer webhook endpoints and retries failed deliveries. |
| `stats/refresh` | every 5 minutes | existing, does more | Now also rebuilds the portal dashboard and States page numbers ahead of time, so no visitor waits for them. Must run as the web user, because it writes to the same cache. |
| `bulk/build` | 03:30 UTC daily | existing, does more | Full bulk files, and now also the daily (last 7 days) and weekly (last 4 weeks) new-company files, which adds seconds. Run it when the site is quiet. |
| `alerts/send` | 07:00 New York time daily | **new** | Emails each saved search's new matches. A second run the same day sends nothing. |

Keep `CRON_TZ` and `alerts/send` at the end: `CRON_TZ` changes the time zone of every line below it, and the other jobs are meant to run in server time (UTC).
Every job skips a run if the previous one is still going.

Run each once by hand to check them:

```bash
sudo -u www-data php yii webhooks/run
sudo -u www-data php yii bulk/build-new
sudo -u www-data php yii alerts/send
```

`bulk/build-new` builds only the new-company files, so the Data license page has them before the next nightly build.

## 6. Network

- [ ] Outbound 443 open, for customer webhook endpoints.
- [ ] Outbound 587 open, for mail.

## 7. Bulk downloads through Apache (X-Sendfile)

Bulk downloads work without this (`BULK_SENDFILE=php`): PHP streams each file, which ties up one PHP worker per running download.
If it is not set up yet, it lets Apache send the files instead. If it is already set up, nothing changes: the new-company files live under
the same `runtime/bulk` folder (`new-daily/`, `new-weekly/`), which `XSendFilePath` already covers.

Debian/Ubuntu:

```bash
sudo apt install libapache2-mod-xsendfile
sudo a2enmod xsendfile
```

Bitnami (no package):

```bash
sudo apt install build-essential unzip curl
curl -L -o mod_xsendfile.zip https://github.com/nmaier/mod_xsendfile/archive/refs/heads/master.zip
unzip mod_xsendfile.zip && cd mod_xsendfile-master
sudo /opt/bitnami/apache/bin/apxs -cia mod_xsendfile.c
```

In both the port 80 and port 443 virtual hosts, next to `DocumentRoot`:

```apache
XSendFile On
XSendFilePath /var/www/entityindex/runtime/bulk
```

Use the real absolute path of `BULK_DIR` if it is set. Restart Apache, set `BULK_SENDFILE=xsendfile` in `.env`, and run `php yii cache/flush-all`.
An empty or 404 download means the path is wrong or `www-data` cannot read the folder; a download named like the page means the module
is not loaded (`apachectl -M | grep xsendfile`). `BULK_SENDFILE=php` always works as a fallback.

## 8. Values in code to confirm

New settings in `config/params.php`, changed through a commit and deploy, not `.env`:

- [ ] `webhooks`: endpoints per account (5), batch size, timeout, retry schedule, failures before an endpoint is turned off (50), history days (30).
- [ ] `alerts`: saved searches per account (10), companies listed per email (25).
- [ ] `bulk`: `newDays` (7) and `newWeeks` (4) of new-company files.

Still open with the client: whether webhook deliveries count toward the API cap.
