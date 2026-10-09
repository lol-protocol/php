# Privacy Laws API

Read-only HTTP API in [`api.php`](api.php). It serves the generated files in `countries/`; there is no database involved.

```
GET /privacy-laws/api.php?action=<action>[&tld=<country>][&slug=<text>]
```

| `action` | Parameters | Returns |
|---|---|---|
| `countries` | – | `{ "data": [{ code, name, tld, region, lawCount }], "meta": { version, generatedAt } }` |
| `bundle` | – | The whole dataset in one JSON file (what the web app loads) |
| `country` | `tld` | `{ "data": [ …laws of that country… ] }` |
| `country_info` | `tld` | `{ "data": { code, name, tld, region, lawCount } }` |
| `country_csv` | `tld` | The country's laws as CSV (`text/csv`) |
| `texts` | `tld` | `{ "data": [ …cached reference texts… ] }` (empty list if none) |
| `text` | `tld`, `slug` | One cached reference text: Markdown (`text/markdown`) or plain text |

`tld` is the country's 2-letter folder code (`us`, `eu`, `gb`, `br`…), case-insensitive. `slug` comes from `texts` (lowercase letters, digits, hyphens).

## Examples

```bash
curl 'http://localhost:8080/privacy-laws/api.php?action=countries'
curl 'http://localhost:8080/privacy-laws/api.php?action=country&tld=us'
curl 'http://localhost:8080/privacy-laws/api.php?action=country_csv&tld=eu' -o eu.csv
curl 'http://localhost:8080/privacy-laws/api.php?action=text&tld=us&slug=california-consumer-privacy-act-ccpa'
```

```js
const { data: countries, meta } = await (await fetch("/privacy-laws/api.php?action=countries")).json();
const { data: laws } = await (await fetch("/privacy-laws/api.php?action=country&tld=br")).json();
```

A law object has the columns of `privacy_laws_master.csv` (see the README): `country_code`, `country_name`, `region`, `law_name`, `jurisdiction`, `enactment_date`, `effective_date`, `scope`, `applies_to`, `key_requirements`, `data_categories`, `retention_period`, `enforcement_authority`, `penalties_range`, `exemptions`, `website_url`, `language`, `frameworks` (empty, or `/`-separated values from `GDPR`, `EU-Adequacy`, `CoE-108`, `APEC-CBPR`), `frameworks_not` (same values, confirmed **not** joined; one in neither list is unconfirmed), `notes`.

## Caching

- Every response has a weak `ETag` and `Last-Modified`, plus `Cache-Control: public, max-age=300`. Send `If-None-Match` / `If-Modified-Since` and you get an empty `304` while the data has not changed.
- Responses are gzip-compressed when the client sends `Accept-Encoding: gzip` (the full dataset goes from ~34 KB to ~6 KB).
- `meta.version` (and `version` inside the bundle) is a content hash: it only changes when the data changes, so it is a cheap way to know whether to refresh a local copy.

## Errors

Errors are JSON: `{ "error": "message" }`.

| Status | When |
|---|---|
| 400 | Missing or malformed `tld` / `slug`, or unknown `action` |
| 404 | Unknown country, no cached text for that slug, or the dataset has not been built (`npm run build`) |
| 405 | Any method other than `GET`, `HEAD`, `OPTIONS` |

## Notes

- `tld` and `slug` are validated against fixed patterns (`^[a-z]{2}$`, `^[a-z0-9][a-z0-9-]{0,99}$`) before they touch the file system; nothing else from the request is used in a path.
- CORS is open (`Access-Control-Allow-Origin: *`) because the data is public and read-only.
- Cached texts exist only after running `npm run cache:texts` (see the README).
