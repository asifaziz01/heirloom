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

Demo logins (seeded, local only):
- `owner@heirloom.test` / `password` (owner)
- `zara@heirloom.test` / `password` (contributor; her edits go to the review queue)

`.env` settings:

| Key | Purpose |
|---|---|
| `ANTHROPIC_API_KEY` | Enables AI interview extraction, biographies, photo/document analysis, question suggestions. Everything else works without it. |
| `ANTHROPIC_MODEL` | Defaults to `claude-opus-5-5`. |
| `HERITAGE_ALL_FEATURES` | `true` unlocks every plan feature/limit (handy in dev). Set `false` to test plan gating. |

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
| §31 Pricing | `config/plans.php`: Free / Family / Heritage / Legacy limits and feature gates, with usage meters. |
| §35 Shareable tree | A 3–6 generation ancestor chart that downloads as SVG with subtle "Created with Heirloom" branding. |
| §37 Risk 2: low engagement | Dashboard shows upcoming birthdays, anniversaries and remembrance days, "on this day", open family questions, "help complete the archive" (profile completeness), and recent activity. |
| §37 Risk 4: trust | Private by default. Files are streamed only to members, and admins can make profiles admin-only. A "hide living relatives from viewers" setting and a full audit log with field-level diffs are included. |

## Features added beyond the report

- **"How are we related?" calculator.** It handles cousins *n* times removed, half-relations, great-/grand- prefixes, step-parents, in-laws and "aunt by marriage", and shows the connecting path.
- **Family questions.** Anyone can post a question ("How did Dada and Dadi meet?"), and answers become stories.
- **Profile completeness score.** Shows what each person is missing, to prompt relatives to fill gaps.
- **Partial and approximate dates everywhere.** "1942", "Mar 1942", "abt. 1940", before/after.
- **Descendant chart.** Children are grouped by each partnership, with zoom and selectable root and depth.

## Not built yet (next steps)

- **Stripe billing.** Plan switching is direct; Checkout and webhooks are still needed.
- **Email delivery for invitations.** Links are copy/share for now, e.g. via WhatsApp.
- **Password reset and email verification.**
- **Server-side transcription.** Browsers without the Web Speech API (Firefox, some Safari versions) can record but not transcribe live; a speech-to-text service would close this gap.
- **Duplicate detection and merging**, especially after a GEDCOM import.
- **Printed-book fulfilment and poster ordering** (revenue add-ons from §33).
- **UI translation.** Names and stories are multilingual; the interface is English only.
- **Validation work from §43–§47.** Landing-page A/B test, customer interviews, review mining. `resources/views/landing.blade.php` is a starting point for Test B.
