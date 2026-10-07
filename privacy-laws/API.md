# Privacy Laws API Documentation

## Base URL
```
/privacy-laws/api.php
```

## Endpoints

### List All Countries
```
GET /privacy-laws/api.php?action=countries
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

### Get Country Laws (JSON)
```
GET /privacy-laws/api.php?action=country&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter country code in lowercase (e.g., `us`, `eu`, `br`)

**Response:**
```json
{
  "data": [
    {
      "country_code": "US",
      "country_name": "United States",
      "law_name": "California Consumer Privacy Act (CCPA)",
      "enactment_date": "2018-06-28",
      "effective_date": "2020-01-01",
      "enforcement_authority": "California Attorney General",
      "scope": "California residents",
      "applies_to": "For-profit businesses",
      "key_requirements": "Right to know, delete, opt-out",
      "penalties_range": "$2,500 - $7,500 per violation"
    }
  ]
}
```

### Get Country Info
```
GET /privacy-laws/api.php?action=country_info&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter country code in lowercase

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

### Get Country Laws (CSV)
```
GET /privacy-laws/api.php?action=country_csv&tld={tld}
```

**Parameters:**
- `tld` (required): 2-letter country code in lowercase

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
  "error": "Country data not found for TLD: xx"
}
```
Status: 404

## Usage Examples

### JavaScript
```javascript
// Load all countries
const response = await fetch('/privacy-laws/api.php?action=countries');
const { data: countries } = await response.json();

// Load laws for US
const laws = await fetch('/privacy-laws/api.php?action=country&tld=us');
const { data } = await laws.json();

// Get country info
const info = await fetch('/privacy-laws/api.php?action=country_info&tld=us');
const { data: countryInfo } = await info.json();
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
- sv (El Salvador)
- and more...

## CORS

The API supports Cross-Origin Resource Sharing (CORS) for use from web frontends.
