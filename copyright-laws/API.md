# Copyright Laws API Documentation

## Base URL
```
/copyright-laws/api.php
```

## Endpoints

### List All Jurisdictions
```
GET /copyright-laws/api.php?action=jurisdictions
```

**Response:**
```json
{
  "data": [
    {
      "code": "US",
      "name": "United States",
      "tld": "us",
      "lawCount": 1
    },
    {
      "code": "EU",
      "name": "European Union",
      "tld": "eu",
      "lawCount": 1
    }
  ]
}
```

### Get Jurisdiction Laws (JSON)
```
GET /copyright-laws/api.php?action=jurisdiction&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter jurisdiction code in lowercase (e.g., `us`, `eu`, `br`)

**Response:**
```json
{
  "data": [
    {
      "country_code": "US",
      "country_name": "United States",
      "law_name": "Copyright Act (17 USC)",
      "protection_type": "Copyright",
      "term_of_protection": "Author's life + 70 years or 95 years (works for hire)",
      "author_rights": "Full economic rights",
      "moral_rights": "Not explicitly protected",
      "orphan_works": "Compulsory licensing allowed",
      "digital_protection": "DMCA anti-circumvention",
      "fair_use_exceptions": "Fair use doctrine",
      "registration_required": "No (but beneficial)",
      "enforcement_body": "U.S. Copyright Office",
      "treaties_signatory": "Berne/TRIPS/WCT",
      "linked_resources": "https://www.copyright.gov/"
    }
  ]
}
```

### Get Jurisdiction Info
```
GET /copyright-laws/api.php?action=jurisdiction_info&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter jurisdiction code in lowercase

**Response:**
```json
{
  "data": {
    "code": "US",
    "name": "United States",
    "tld": "us",
    "region": "americas",
    "lawCount": 1,
    "createdAt": "2026-10-07T12:30:00.000Z"
  }
}
```

### Get Jurisdiction Laws (CSV)
```
GET /copyright-laws/api.php?action=jurisdiction_csv&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter jurisdiction code in lowercase

**Response:** CSV file with headers and law data

## Error Responses

### Missing Parameter
```json
{
  "error": "Missing TLD parameter"
}
```
Status: 400

### Not Found
```json
{
  "error": "Jurisdiction data not found for TLD: xx"
}
```
Status: 404

## Usage Examples

### JavaScript
```javascript
// Load all jurisdictions
const response = await fetch('/copyright-laws/api.php?action=jurisdictions');
const { data: jurisdictions } = await response.json();

// Load laws for US
const laws = await fetch('/copyright-laws/api.php?action=jurisdiction&tld=us');
const { data } = await laws.json();

// Get jurisdiction info
const info = await fetch('/copyright-laws/api.php?action=jurisdiction_info&tld=us');
const { data: jurisdictionInfo } = await info.json();
```

## Supported TLDs

All 2-letter ISO 3166-1 alpha-2 country codes are supported:
- eu (European Union)
- us (United States)
- gb (United Kingdom)
- ca (Canada)
- br (Brazil)
- jp (Japan)
- au (Australia)
- in (India)
- za (South Africa)
- mx (Mexico)
- ch (Switzerland)
- sg (Singapore)
- nz (New Zealand)
- kr (South Korea)
- and more...

## CORS

The API supports Cross-Origin Resource Sharing (CORS) for use from web frontends.

## Caching Strategy

The API uses in-memory caching for better performance:
- File-based data (laws.json) is cached after first access
- Re-requests for the same TLD return cached data
- No distributed caching is implemented (add if needed for multi-server deployment)
