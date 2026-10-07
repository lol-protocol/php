# Copyright Laws by Jurisdiction

A comprehensive database and interactive tool for discovering copyright and intellectual property legislation across all countries worldwide. Designed for digital libraries, text repositories, and PDF documentation projects.

## Overview

This module provides:
- **200+ jurisdictions** with their copyright legislation
- **Material-specific protection** info (books, texts, compilations, derivatives)
- **Interactive search** by country, region, material type
- **Term calculator** for copyright duration by jurisdiction
- **Treaty affiliation tracking** (Berne, TRIPS, WCT, etc.)
- **Multi-language interface** (15+ languages)
- **Database schema** for library management systems

## Directory Structure

```
copyright-laws/
├── jurisdictions/         # CSV data files
│   ├── copyright_laws_master.csv
│   ├── material_types/
│   └── region_*/
├── app/                   # Frontend interface
│   ├── index.html
│   ├── copyright-laws.js
│   ├── styles/
│   └── translations/
├── src/                   # PHP backend classes
│   └── CopyrightLaws/
├── tests/                 # Test suite
├── docs/                  # Documentation
├── database_schema.sql    # SQL schema for database
├── import_database.php    # CSV to database importer
├── package.json           # Node dependencies
└── README.md             # This file
```

## Quick Start

### Option 1: Web Interface (No Database Required)

1. Open `app/index.html` in a browser
2. Select a country/jurisdiction
3. Choose material type (book, text, PDF, etc.)
4. View protection terms and export data

### Option 2: Database Integration

1. Create database: `mysql -u root -p < database_schema.sql`
2. Import data: `php import_database.php`
3. Query the database for library systems

## Data Files

### Master File: `jurisdictions/copyright_laws_master.csv`

Columns:
- `country_code`: ISO 3166-1 alpha-2
- `country_name`: English name
- `law_name`: Name of copyright law
- `protection_type`: Type (copyright, related rights, database rights)
- `term_of_protection`: Duration (e.g., "author's life + 70 years")
- `author_rights`: Economic & moral rights included
- `moral_rights`: Specific moral rights protection
- `orphan_works`: How orphan works are handled
- `digital_protection`: DMCA/DRM protection level
- `fair_use_exceptions`: Permitted exceptions/fair dealing
- `registration_required`: If registration is mandatory
- `enforcement_body`: Enforcement agency
- `treaties_signatory`: Treaty memberships (Berne, TRIPS, WCT)
- `linked_resources`: Official documentation links

### Material-Specific Files: `jurisdictions/material_types/`

- `book_protection.csv`: Books and literary works
- `text_document_protection.csv`: Text documents and articles
- `compiled_works_protection.csv`: Compilations and collections
- `derivative_works_protection.csv`: Translations, adaptations

## Development

### Run Tests

```bash
npm test
```

### Add New Jurisdiction

1. Edit `jurisdictions/copyright_laws_master.csv`
2. Update material-specific files as needed
3. Run `npm test` to validate
4. Commit with clear message

## Contributing

See `CONTRIBUTING.md` for guidelines on:
- Adding new copyright laws
- Updating protection terms
- Treaty affiliation changes
- Material type specifications
- Data validation requirements

## License

MIT - See LICENSE file
