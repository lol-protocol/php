# Copyright Laws by Jurisdiction

A database and small web tool for looking up copyright legislation by jurisdiction. Meant as a starting point for digital libraries, text repositories and PDF documentation projects.

> **Informational only, not legal advice.** The data was compiled without review by a lawyer. Copyright terms and exceptions change, and some entries may be incomplete or out of date. Before relying on any row, check it against the official source (see the `linked_resources` column and the body named in `enforcement_body`).

## Overview

- **50 laws, one per jurisdiction** (the European Union counts as one).
- **Search** by country name or code, filtered by region.
- **Comparison** of selected laws side by side.
- **Timeline** of when the laws were enacted and how long protection lasts.
- **Treaty membership** per jurisdiction (Berne, TRIPS, WCT).
- **Interface in 5 languages** (English, Spanish, French, German, Portuguese). The data itself is in English.
- **JSON API** (`api.php`, see `API.md`) and a **MySQL schema** with an importer.

The numbers above are checked by `npm test` against `jurisdictions/copyright_laws_master.csv`, so they cannot drift from the data unnoticed.

## Directory Structure

```
copyright-laws/
├── jurisdictions/
│   ├── copyright_laws_master.csv   # Source of truth: one row per law
│   └── <tld>/                      # One folder per jurisdiction (eu, us, br, ...), generated from the master
│       ├── info.json
│       ├── laws.csv
│       └── laws.json
├── app/                            # Web interface (ES modules, no build step)
│   ├── index.html
│   ├── copyright-laws.js
│   ├── csv.js                      # CSV reader used by the interface
│   ├── metadata-display.js
│   ├── timeline.js
│   ├── styles/
│   └── translations/
├── scripts/
│   └── reorganize-by-jurisdiction.js   # Regenerates jurisdictions/<tld>/ from the master
├── tests/                          # npm test
├── api.php                         # JSON API
├── API.md
├── database_schema.sql             # MySQL schema
├── import_database.php             # CSV -> MySQL importer
├── package.json
└── package-lock.json
```

## Quick Start

### Option 1: Web interface (no database)

The page uses ES modules and `fetch`, so it must be served over HTTP; opening `app/index.html` straight from disk does not work. Serve the **repository root**, so the module lives at `/copyright-laws/` (the interface calls `/copyright-laws/api.php`):

```bash
php -S localhost:8000          # run from the repository root
# then open http://localhost:8000/copyright-laws/app/index.html
```

Click **Search** to list the laws (with no filters, all 50).

### Option 2: MySQL

```bash
php import_database.php --all           # run from this folder
```

`--all` creates the schema, imports the master CSV and validates the result. **It drops and recreates the `copyright_laws_db` database first**, so do not point it at a database you want to keep. It connects to `localhost` as `root` without a password; change the constructor defaults in `import_database.php` if yours differ. `--create-schema`, `--import` and `--validate` run each step on its own.

## Data Files

### Master file: `jurisdictions/copyright_laws_master.csv`

Columns:
- `country_code`: ISO 3166-1 alpha-2 (`EU` for the European Union)
- `country_name`: English name
- `law_name`: Name of the copyright law
- `protection_type`: Kind of protection (the tests accept Copyright, Related rights, Database rights, Moral rights)
- `term_of_protection`: Duration (e.g. "Author's life + 70 years")
- `author_rights`: Economic rights included
- `moral_rights`: Moral rights protection
- `orphan_works`: How orphan works are handled
- `digital_protection`: DMCA/DRM protection level
- `fair_use_exceptions`: Permitted exceptions / fair dealing
- `registration_required`: `Yes` or `No`, optionally followed by a note (e.g. `No (but beneficial)`)
- `enforcement_body`: Enforcement agency
- `treaties_signatory`: Treaty memberships separated by `/` (Berne, TRIPS, WCT)
- `linked_resources`: Official documentation link

**Fields that contain a comma must be wrapped in double quotes** (`"Copyright Act (R.S.C., 1985)"`), and a literal quote is written `""`. A row with a different number of fields than the header is an error: `npm test` reports its line, and the tools fail instead of shifting values into the wrong columns.

### Per-jurisdiction files: `jurisdictions/<tld>/`

`info.json`, `laws.csv` and `laws.json` are generated from the master; do not edit them by hand. See below for how to regenerate them.

## Development

Requires Node 22.

```bash
npm ci
npm test
```

### Add or change a law

1. Edit `jurisdictions/copyright_laws_master.csv` (mind the quoting rule above).
2. Regenerate the per-jurisdiction files: `node scripts/reorganize-by-jurisdiction.js`. A new jurisdiction gets a folder named after its lowercase code; the script keeps the existing `createdAt` of folders that already exist, so only the files whose data changed show up in `git status`.
3. Run `npm test`. It fails if the per-jurisdiction files no longer match the master.

## Known limitations

- The **region filter** only knows the region of 19 jurisdictions (the ones listed in `getRegion` in `app/copyright-laws.js` and `regionMap` in the script); the others show up only with "All Regions".
- The **material filter** (Books, Texts, Compilations, Derivative Works) compares against `protection_type`, which is `Copyright` in every row, so choosing any material returns no results. There is no per-material data yet.
- Terms such as "Author's life + 70 years" are free text; the timeline only reads the number of years from them.
- Figures and terms are a snapshot and are not updated automatically.

## License

MIT, as declared in `package.json`.
