# Privacy Laws by Country

A database and small web tool for looking up privacy legislation by country. Meant as a starting point for genealogy platforms and privacy-compliance projects.

> **Informational only, not legal advice.** The data was compiled without review by a lawyer. Laws change, penalty amounts are adjusted over time, and some entries may be incomplete or out of date. Before relying on any row, check it against the official source (see the `website_url` column and the regulator named in `enforcement_authority`).

## Overview

- **53 laws in 52 jurisdictions** (the European Union counts as one; the United States has two entries).
- **Search** by country name or code, filtered by region and law type.
- **Comparison** of selected laws side by side.
- **Timeline** of when the laws were enacted and came into force.
- **Interface in 5 languages** (English, Spanish, French, German, Portuguese). The data itself is in English.
- **JSON API** (`api.php`, see `API.md`) and a **MySQL schema** with an importer.

The numbers above are checked by `npm test` against `countries/privacy_laws_master.csv`, so they cannot drift from the data unnoticed.

## Directory Structure

```
privacy-laws/
├── countries/
│   ├── privacy_laws_master.csv     # Source of truth: one row per law
│   └── <tld>/                      # One folder per jurisdiction (eu, us, br, ...), generated from the master
│       ├── info.json
│       ├── laws.csv
│       └── laws.json
├── app/                            # Web interface (ES modules, no build step)
│   ├── index.html
│   ├── privacy-laws.js
│   ├── csv.js                      # CSV reader used by the interface
│   ├── metadata-display.js
│   ├── timeline.js
│   ├── styles/
│   └── translations/
├── scripts/
│   └── reorganize-by-country.js    # Regenerates countries/<tld>/ from the master
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

The page uses ES modules and `fetch`, so it must be served over HTTP; opening `app/index.html` straight from disk does not work. Serve the **repository root**, so the module lives at `/privacy-laws/` (the interface calls `/privacy-laws/api.php`):

```bash
php -S localhost:8000          # run from the repository root
# then open http://localhost:8000/privacy-laws/app/index.html
```

Click **Search** to list the laws (with no filters, all 53).

### Option 2: MySQL

```bash
php import_database.php --all           # run from this folder
```

`--all` creates the schema, imports the master CSV and validates the result. **It drops and recreates the `privacy_laws_db` database first**, so do not point it at a database you want to keep. It connects to `localhost` as `root` without a password; change the constructor defaults in `import_database.php` if yours differ. `--create-schema`, `--import` and `--validate` run each step on its own.

## Data Files

### Master file: `countries/privacy_laws_master.csv`

Columns:
- `country_code`: ISO 3166-1 alpha-2 (`EU` for the European Union)
- `country_name`: English name
- `law_name`: Name of the law
- `jurisdiction`: Level or body that issued it (federal, state, regulator...)
- `enactment_date`: When the law was enacted
- `effective_date`: When it came into force
- `scope`: Who is protected (residents, children...)
- `applies_to`: Who has to comply
- `key_requirements`: Summary of the main requirements
- `data_categories`: Types of personal data covered
- `retention_period`: Data retention rule
- `enforcement_authority`: Who enforces it
- `penalties_range`: Fines or sanctions
- `exemptions`: Key exemptions
- `website_url`: Official resource link
- `language`: Language of the reference materials
- `notes`: Additional context

**Fields that contain a comma must be wrapped in double quotes** (`"Up to $7,500 per violation"`), and a literal quote is written `""`. A row with a different number of fields than the header is an error: `npm test` reports its line, and the tools fail instead of shifting values into the wrong columns.

### Per-country files: `countries/<tld>/`

`info.json`, `laws.csv` and `laws.json` are generated from the master; do not edit them by hand. See below for how to regenerate them.

## Development

Requires Node 22.

```bash
npm ci
npm test
```

### Add or change a law

1. Edit `countries/privacy_laws_master.csv` (mind the quoting rule above).
2. Regenerate the per-country files: `node scripts/reorganize-by-country.js`. A new country gets a folder named after its lowercase code; the script keeps the existing `createdAt` of folders that already exist, so only the files whose data changed show up in `git status`.
3. Run `npm test`. It fails if the per-country files no longer match the master.

## Known limitations

- The **region filter** only knows the region of 20 countries (the ones listed in `getRegion` in `app/privacy-laws.js` and `regionMap` in the script); the other jurisdictions show up only with "All Regions".
- The **law type filter** looks for GDPR, CCPA, LGPD or "General" inside the law name; it is not a classification of the laws.
- Penalty amounts, thresholds and similar figures are a snapshot and are not updated automatically.

## License

MIT, as declared in `package.json`.
