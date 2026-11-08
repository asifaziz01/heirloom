# Heirloom: private family heritage platform

> **Preserve your family's story for generations.**

This is a working MVP of the product described in *Family Heritage SaaS — Market & Gap Analysis* (Oct 2026). It is positioned as a family-owned digital archive, not as another genealogy database. The core thesis from §45 of the report is built in: **complex relationship engine + private family archive + AI memory capture + family collaboration + family legacy outputs.**

Stack: Laravel 13 · PHP 8.3 · MySQL 8 (MariaDB/Postgres/SQLite also work) · Blade + Tailwind 4 + Alpine · Anthropic PHP SDK.

For families using the app, see the plain-language [User Guide](docs/USER_GUIDE.md).

## Run it

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed      # loads the demo "Rahman Family"
php artisan serve
```

Demo logins (seeded, local only, password is `password` for all):

| Email or mobile | Role in the Rahman family | Use it to test |
|---|---|---|
| `owner@heirloom.test` or `+1 408 555 0101` | owner (Arman) | everything; also a viewer in the Thompson family (family switching) |
| `imran@heirloom.test` or `+1 408 555 0103` | admin | settings, members, export, activity log |
| `sana@heirloom.test` or `+91 98765 50104` | editor | approving the review queue, preferring facts |
| `zara@heirloom.test` or `+1 416 555 0102` | contributor | her structural edits go to the review queue |
| `oliver@heirloom.test` or `+44 7700 900105` | viewer | read-only; admin of the Thompson family |
| `claire@heirloom.test` or `+44 7700 900106` | viewer | owner of the second, smaller Thompson family |

`DemoFamilySeeder` creates the core Rahman family; `SampleDataSeeder` (also run by `migrate --seed`) adds a fourth generation, albums, documents with transcriptions, an audio recording, interviews in draft/extracted/applied states, answered questions, invitations (pending, expired, accepted), a review queue with history, an audit trail, more conflicting facts, and the second family. To add it to an existing database that only has the demo: `php artisan db:seed --class=SampleDataSeeder`.

`.env` settings:

| Key | Purpose |
|---|---|
| `ANTHROPIC_API_KEY` | Enables AI interview extraction, biographies, photo/document analysis, question suggestions. Everything else works without it. |
| `ANTHROPIC_MODEL` | Defaults to `claude-opus-5-5`. |
| `BILLING_ENABLED` | Default `false`: no pricing, plans or limits anywhere, and every feature is available. Set `true` to turn on plan limits, feature gates, the Plan page and landing-page pricing. |

Tests: `php vendor/bin/phpunit` (35 tests: page smoke test, governance, permissions, privacy, relationship labels, GEDCOM round-trip, archive export, plan limits, sign-in and rate limiting, password reset, invitation email, family deletion, queued AI and export jobs).

## Deploy it

The app is a standard Laravel 13 site: any host that runs PHP 8.3 with the `gd`, `zip`, `intl` and `pdo` extensions works (Forge, Ploi, Fly.io, Render, a plain VPS with nginx + php-fpm). Point the web root at `public/`.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate --force      # first deploy only
php artisan migrate --force           # never --seed in production (see below)
php artisan optimize                  # caches config, routes, views and events
```

**Queue worker (required).** AI extraction, photo/document analysis, biography drafts, question suggestions and the ZIP archive export run as queued jobs on the `database` queue (the `jobs` table is created by the migrations), so no web request waits on the Anthropic API or on zipping gigabytes of photos. Something has to run those jobs:

- A server you control: `php artisan queue:work --tries=2 --max-time=3600` under Supervisor or systemd, restarted on each deploy (`php artisan queue:restart`).
- Shared hosting, or anywhere without a long-lived process: a cron entry that runs the worker once a minute and lets it exit when the queue is empty (see *Shared hosting*, step 7). Jobs then start within a minute of being requested; the pages show "working on it" and poll until the result is ready.
- No cron at all: `QUEUE_CONNECTION=sync` runs each job inside the request that triggered it, as the app did before. It works, but long AI calls or big archives will hit PHP and proxy time limits.

Production `.env`:

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false`. In production every generated URL is forced to `https`. |
| `APP_URL` | The public `https://` address. |
| `SESSION_SECURE_COOKIE` | `true`, so the session and remember-me cookies are never sent over plain HTTP. |
| `TRUSTED_PROXIES` | `*` behind a managed load balancer or Cloudflare, or a comma-separated list of proxy IPs/CIDRs. Without it the app sees the proxy's address: secure cookies, `https` detection and per-address rate limiting all key on the wrong client. |
| `DB_CONNECTION` + `DB_*` | `mysql` (the default) with the host, port, database name and credentials of a MySQL 8 / MariaDB database created with `utf8mb4`. The schema has no engine-specific features, so Postgres or SQLite work too. |
| `FILESYSTEM_DISK` | Uploaded files live on the `local` disk under `storage/app/private`, so a single server (or a persistent volume) is required. Keep that directory in backups together with the database. |
| `LOG_LEVEL` | `warning` or `error`. |
| `MAIL_MAILER` + `MAIL_*` | `mailgun` with `MAILGUN_DOMAIN`, `MAILGUN_SECRET` and, for EU accounts, `MAILGUN_ENDPOINT=api.eu.mailgun.net` (see *Email* below); or `smtp` with the host's own mail server (see *Shared hosting*). Used for password-reset links and emailed invitations; with `log` those emails only go to the log file. |
| `QUEUE_CONNECTION` | `database` (the default), with a worker or the cron entry above. `DB_QUEUE_RETRY_AFTER=600` keeps a slow job from being handed to a second worker while the first is still on it; the jobs themselves give up after 540 s and two attempts. `sync` only for a host with no cron. |
| `ANTHROPIC_API_KEY` | Optional; see above. |

### Shared hosting (cPanel, Plesk, Hostinger and similar)

Heirloom runs fine on a shared PHP host. The built CSS/JS in `public/build` is committed, so the server needs no Node; and the `local` disk needs no extra services beyond the host's MySQL/MariaDB database.

1. **PHP.** In the host's PHP selector choose 8.3 with the `gd`, `zip`, `intl`, `fileinfo`, `mbstring`, `pdo_mysql` extensions. In the PHP options (MultiPHP INI editor) set the limits from the block above.
2. **Files.** Upload the whole project *outside* the web root, e.g. `~/heirloom`, and run `composer install --no-dev --optimize-autoloader` there (over SSH, or run it locally and upload the `vendor/` folder). Make `storage/` and `bootstrap/cache/` writable.
3. **Web root.** Point the domain's document root at `~/heirloom/public` (cPanel: *Domains* → edit the document root). If the host forces `public_html`, copy the contents of `public/` into it and change the two `require` paths in `public_html/index.php` to `__DIR__.'/../heirloom/vendor/autoload.php'` and `__DIR__.'/../heirloom/bootstrap/app.php'`. The bundled `.htaccess` handles the rewrites on Apache/LiteSpeed.
4. **Email.** Either send through Mailgun (works on any host, no mailbox needed) or through the host's own mail server.

   *Mailgun.* In the Mailgun dashboard add a sending domain such as `mg.yourdomain.com`, add the DNS records it shows (SPF, DKIM, and the MX records if you want replies and bounces handled) and wait until the domain is verified. Then create a **Sending key** for that domain (*Sending* → *Domain settings* → *Sending API keys*) and in `.env`:

   ```ini
   MAIL_MAILER=mailgun
   MAILGUN_DOMAIN=mg.yourdomain.com
   MAILGUN_SECRET=<sending key>
   MAILGUN_ENDPOINT=api.mailgun.net   ; api.eu.mailgun.net for an EU-region account
   MAIL_FROM_ADDRESS=no-reply@mg.yourdomain.com
   MAIL_FROM_NAME="Heirloom"
   ```

   The from-address must be on the Mailgun domain. A free Mailgun account on the sandbox domain can only email addresses you add as *Authorized Recipients*, so verify a real domain before inviting relatives.

   *The host's mail server.* Create a mailbox such as `no-reply@yourdomain.com` in the hosting panel, then in `.env`:

   ```ini
   MAIL_MAILER=smtp
   MAIL_HOST=mail.yourdomain.com     ; the host's outgoing server, shown on the panel's "Connect devices" / "Mail client" page
   MAIL_PORT=465
   MAIL_SCHEME=smtps                 ; or MAIL_PORT=587 and MAIL_SCHEME=smtp for STARTTLS
   MAIL_USERNAME=no-reply@yourdomain.com
   MAIL_PASSWORD=<mailbox password>
   MAIL_FROM_ADDRESS=no-reply@yourdomain.com
   MAIL_FROM_NAME="Heirloom"
   ```

   Either way, test it with `php artisan tinker --execute="Mail::raw('Heirloom test', fn (\$m) => \$m->to('you@example.com')->subject('Heirloom'));"` and check the inbox (and spam). Most shared hosts require the from-address to be a real mailbox on the same domain. Mailgun's *Sending* → *Logs* page shows every accepted, delivered and bounced message if nothing arrives.
5. **Database and app key.** `php artisan key:generate --force` once, then `php artisan migrate --force`. SQLite lives at `database/database.sqlite`; back it up together with `storage/app/private`.
6. **Settings.** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain.com`, `SESSION_SECURE_COOKIE=true`, `QUEUE_CONNECTION=database`, `DB_QUEUE_RETRY_AFTER=600`. `TRUSTED_PROXIES` is only needed if the site sits behind Cloudflare or a similar proxy. Finish with `php artisan optimize`; run it again after every `.env` change.
7. **Cron (required).** Shared hosts cannot keep a queue worker alive, so a cron job starts one every minute; it processes whatever is waiting and exits when the queue is empty or after 50 seconds (a job already running finishes first). In cPanel's *Cron Jobs* add:

   ```
   * * * * * cd ~/heirloom && php artisan queue:work --stop-when-empty --max-time=50 > /dev/null 2>&1
   ```

   Use the full path to the PHP 8.3 binary if the host's default `php` is older (the PHP selector page shows it, e.g. `/usr/local/bin/ea-php83`). AI results and archive downloads then appear within about a minute; while a job is waiting or running the page shows a progress note and refreshes itself when the result is ready. Failed jobs land in the `failed_jobs` table (`php artisan queue:failed`); the page shows the reason, and the user can simply try again. Finished archives are kept under `storage/app/private/families/<id>/exports/` until downloaded or for one day.

**Demo data.** `DatabaseSeeder` refuses to run when `APP_ENV=production` because the demo accounts all use the password `password`. Set `SEED_DEMO_DATA=true` for a throwaway demo server only.

**PHP limits.** Uploads allow up to 200 MB per file and 50 files per request, which is far above PHP's defaults. In `php.ini` (and the equivalent `client_max_body_size` in nginx) set at least:

```ini
upload_max_filesize = 200M
post_max_size = 1G
memory_limit = 512M      ; thumbnails decode the whole image; larger scans are served at full size instead
max_execution_time = 120 ; uploads of many files; AI calls and the archive export run on the queue, not in the request
```

**Rate limits.** Sign-in allows 5 attempts per minute per account from one address and 20 per minute per address; registration allows 10 per hour per address (`AppServiceProvider::configureRateLimiting`). The default `CACHE_STORE=database` is enough; Redis is faster at scale.

**Health check.** `GET /up` returns 200 when the app boots.

**Known gaps for a public launch** (see *Not built yet*): no email/SMS verification, and no password reset for accounts that have only a mobile number.

## How the report maps to the code

| Report section | Implementation |
|---|---|
| §13, §41 Relationship-first model | No `father_id`/`mother_id`. `partnerships` (any number per person, with type, status and dates) and `parent_child` (biological / adoptive / step / foster / guardian / unknown, optionally tied to a partnership). All writes go through `app/Services/FamilyGraph.php`, which blocks cycles. |
| Multiple marriages, divorce, widowhood, half-siblings, unknown parents | Profile page groups children under each partnership. Siblings are classified as full, half, step or adoptive. Children with an unknown other parent are shown separately. |
| §14 Family-owned data model | `families` → `family_members` (role and an optional "this is me" person). The subscription/plan belongs to the family. |
| §19 Timeline | `app/Services/Timeline.php`: family timeline by decade, plus a per-person life timeline with age at each event and relatives' births and deaths. |
| §20 Governance | Roles owner > admin > editor > contributor > viewer (`ResolveFamily` middleware). Contributors' structural changes become `change_requests` that are replayed on approval (`app/Services/Governance.php`). |
| §21 Source & confidence tracking | `facts` + `sources`: several conflicting values per field, each with a source, confidence and status (verified / unverified / disputed). One value is marked preferred and drives the profile. There is also a sources/evidence register. |
| §22 Data ownership & export | GEDCOM 5.5.1 import/export (`app/Services/Gedcom`). Custom `_FREL/_MREL` tags preserve step/guardian links. A complete ZIP archive holds GEDCOM, a full JSON copy, Markdown stories, original files and a README. |
| §23 Multilingual | `person_names` (language, type, RTL-aware display) for names like मोहम्मद अब्दुल रहमान / محمد عبد الرحمن. Search covers every script. |
| §24 Diaspora | Places with coordinates, migration events, and a Leaflet map with migration routes and a country breakdown. |
| §17 AI family interview | In-browser recording (MediaRecorder), live transcription in the interview language via the Web Speech API, and a question guide. Claude extracts people (matched to existing records), places, events, a story draft and follow-up questions. **Every item is confirmed by a person** before it is saved, and new people still go through governance. |
| §18 AI photo/document processing | Claude vision describes photos, estimates the era, and reads certificates and letters. It never identifies faces; results are suggestions only. |
| §25 Mobile-first contribution | Responsive UI, phone camera upload, tagging with a searchable people picker, rough dates ("1958"). |
| §26 Family book | A printable book (save as PDF) with chapters per generation, biographies, a pedigree chart, stories and a timeline. |
| §31 Pricing | `config/plans.php`: Free / Family / Heritage / Legacy limits and feature gates, with usage meters. Dormant until `BILLING_ENABLED=true`. |
| §35 Shareable tree | A 3–6 generation ancestor chart that downloads as SVG with subtle "Created with Heirloom" branding. |
| §37 Risk 2: low engagement | Dashboard shows upcoming birthdays, anniversaries and remembrance days, "on this day", open family questions, "help complete the archive" (profile completeness), and recent activity. |
| §37 Risk 4: trust | Private by default. Files are streamed only to members, and admins can make profiles admin-only. A "hide living relatives from viewers" setting and a full audit log with field-level diffs are included. |
| Open archive | An admin can tick **Open the archive to visitors** in Settings (`families.is_public`). Visitors without an account then browse read-only as a guest viewer (`FamilyMember::guest()` in `ResolveFamily`); every add/edit route stays behind `auth` plus a role, and a visitor who reaches one is sent to sign in. With a single open archive the front page redirects visitors to it. |

## Install it on a phone

Heirloom is a Progressive Web App. Served over HTTPS (or from `localhost`), it can be added to the home screen and opens full-screen like a native app:

- **Android (Chrome, Edge, Samsung Internet):** an **Install app** button appears in the sidebar; or use the browser menu → *Install app* / *Add to Home screen*.
- **iPhone / iPad (Safari):** tap **Share** → **Add to Home Screen**. The sidebar button shows these steps.
- Home-screen shortcuts (long-press the icon on Android) jump straight to *Upload a photo*, *Record an elder* and *Write a story*.
- A service worker (`public/sw.js`) caches the built assets and recently opened pages, and shows an offline page when there is no connection. Private family files are never cached.

Files: `public/manifest.webmanifest`, `public/icons/`, `public/sw.js`, `resources/views/offline.blade.php`. Publishing to the Apple App Store or Google Play would need a native wrapper such as Capacitor around this same site.

## Features added beyond the report

- **"How are we related?" calculator.** It handles cousins *n* times removed, half-relations, great-/grand- prefixes, step-parents, in-laws and "aunt by marriage", and shows the connecting path.
- **Family questions.** Anyone can post a question ("How did Dada and Dadi meet?"), and answers become stories.
- **Profile completeness score.** Shows what each person is missing, to prompt relatives to fill gaps.
- **Partial and approximate dates everywhere.** "1942", "Mar 1942", "abt. 1940", before/after.
- **Descendant chart.** Children are grouped by each partnership, with zoom and selectable root and depth.

## Not built yet (next steps)

- **Billing (deferred).** Plans and limits exist but are switched off (`BILLING_ENABLED=false`). Turning them on later needs Stripe Checkout and webhooks; plan switching is currently direct.
- **Email/SMS verification.** Accounts can be created with an email address, a mobile number, or both, and either one signs you in; neither is verified yet. Password reset works by email (*Forgot it?* on the sign-in page); accounts with only a mobile number cannot reset yet because there is no SMS provider. Invitations are emailed when an address is given and can always be shared as a link. Sessions do not expire: users stay signed in until they sign out.
- **Server-side transcription.** Browsers without the Web Speech API (Firefox, some Safari versions) can record but not transcribe live; a speech-to-text service would close this gap.
- **Duplicate detection and merging**, especially after a GEDCOM import.
- **Printed-book fulfilment and poster ordering** (revenue add-ons from §33).
- **UI translation.** Names and stories are multilingual; the interface is English only.
- **Validation work from §43–§47.** Landing-page A/B test, customer interviews, review mining. `resources/views/landing.blade.php` is a starting point for Test B.
