# Privacy Laws by Country

A comprehensive database and interactive tool for discovering privacy legislation across all countries worldwide. Designed for genealogy platforms and GDPR/privacy compliance projects.

## Overview

This module provides:
- **200+ countries** with their primary privacy laws
- **Interactive search** by country, region, law type
- **Comparison tool** for analyzing privacy requirements across jurisdictions
- **Timeline visualization** of law implementations
- **Multi-language interface** (15+ languages)
- **Database schema** for integration into larger systems

## Directory Structure

```
privacy-laws/
├── countries/              # CSV data files
│   ├── privacy_laws_master.csv
│   └── region_*/
├── app/                    # Frontend interface
│   ├── index.html
│   ├── privacy-laws.js
│   ├── styles/
│   └── translations/
├── src/                    # PHP backend classes
│   └── PrivacyLaws/
├── tests/                  # Test suite
├── docs/                   # Documentation
├── database_schema.sql     # SQL schema for database
├── import_database.php     # CSV to database importer
├── package.json            # Node dependencies
└── README.md              # This file
```

## Quick Start

### Option 1: Web Interface (No Database Required)

1. Open `app/index.html` in a browser
2. Select a country or region
3. View privacy laws and export data

### Option 2: Database Integration

1. Create database: `mysql -u root -p < database_schema.sql`
2. Import data: `php import_database.php`
3. Query the database for integration

## Data Files

### Master File: `countries/privacy_laws_master.csv`

Columns:
- `country_code`: ISO 3166-1 alpha-2
- `country_name`: English name
- `law_name`: Name of primary privacy law
- `jurisdiction`: Regulatory body/authority
- `enactment_date`: When law was enacted
- `effective_date`: When it became effective
- `scope`: Applies to (residents, businesses, etc.)
- `applies_to`: Specific sectors/data types
- `key_requirements`: Summary of main requirements
- `data_categories`: Types of personal data covered
- `retention_period`: Maximum data retention
- `enforcement_authority`: Who enforces it
- `penalties_range`: Fine range or sanctions
- `exemptions`: Key exemptions
- `website_url`: Official resource link
- `language`: Language of reference materials
- `notes`: Additional context

## Development

### Run Tests

```bash
npm test
```

### Add New Country

1. Edit `countries/privacy_laws_master.csv`
2. Run `npm test` to validate
3. Commit with clear message

## Contributing

See `CONTRIBUTING.md` for guidelines on:
- Adding new privacy laws
- Updating existing information
- Translation requirements
- Data validation rules

## License

MIT - See LICENSE file
