# Heirloom: private family heritage platform

> **Preserve your family's story for generations.**

This is a working MVP of the product described in *Family Heritage SaaS — Market & Gap Analysis* (Oct 2026). It is positioned as a family-owned digital archive, not as another genealogy database. The core thesis from §45 of the report is built in: **complex relationship engine + private family archive + AI memory capture + family collaboration + family legacy outputs.**

Stack: Laravel 13 · PHP 8.3 · SQLite (any Laravel DB works) · Blade + Tailwind 4 + Alpine · Anthropic PHP SDK.

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

Tests: `php vendor/bin/phpunit` (16 tests: page smoke test, governance, permissions, privacy, relationship labels, GEDCOM round-trip, archive export, plan limits).

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
- **Email delivery for invitations.** Links are copy/share for now, e.g. via WhatsApp.
- **Password reset and email/SMS verification.** Accounts can be created with an email address, a mobile number, or both, and either one signs you in; neither is verified yet. Sessions do not expire: users stay signed in until they sign out.
- **Server-side transcription.** Browsers without the Web Speech API (Firefox, some Safari versions) can record but not transcribe live; a speech-to-text service would close this gap.
- **Duplicate detection and merging**, especially after a GEDCOM import.
- **Printed-book fulfilment and poster ordering** (revenue add-ons from §33).
- **UI translation.** Names and stories are multilingual; the interface is English only.
- **Validation work from §43–§47.** Landing-page A/B test, customer interviews, review mining. `resources/views/landing.blade.php` is a starting point for Test B.
