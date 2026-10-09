# Copyright Laws API

Read-only HTTP API in [`api.php`](api.php). It serves the generated files in `jurisdictions/`; there is no database involved.

```
GET /copyright-laws/api.php?action=<action>[&tld=<jurisdiction>][&slug=<text>]
```

| `action` | Parameters | Returns |
|---|---|---|
| `jurisdictions` | – | `{ "data": [{ code, name, tld, region, lawCount }], "meta": { version, generatedAt } }` |
| `bundle` | – | The whole dataset in one JSON file (what the web app loads) |
| `jurisdiction` | `tld` | `{ "data": [ …laws of that jurisdiction… ] }` |
| `jurisdiction_info` | `tld` | `{ "data": { code, name, tld, region, lawCount } }` |
| `jurisdiction_csv` | `tld` | The jurisdiction's laws as CSV (`text/csv`) |
| `texts` | `tld` | `{ "data": [ …cached reference texts… ] }` (empty list if none) |
| `text` | `tld`, `slug` | One cached reference text: Markdown (`text/markdown`) or plain text |

`tld` is the jurisdiction's 2-letter folder code (`us`, `eu`, `gb`, `br`…), case-insensitive. `slug` comes from `texts` (lowercase letters, digits, hyphens).

## Examples

```bash
curl 'http://localhost:8080/copyright-laws/api.php?action=jurisdictions'
curl 'http://localhost:8080/copyright-laws/api.php?action=jurisdiction&tld=gb'
curl 'http://localhost:8080/copyright-laws/api.php?action=jurisdiction_csv&tld=us' -o us.csv
curl 'http://localhost:8080/copyright-laws/api.php?action=text&tld=gb&slug=copyright-designs-and-patents-act-1988'
```

```js
const { data: jurisdictions, meta } = await (await fetch("/copyright-laws/api.php?action=jurisdictions")).json();
const { data: laws } = await (await fetch("/copyright-laws/api.php?action=jurisdiction&tld=br")).json();
```

A law object has the columns of `copyright_laws_master.csv` (see the README): `country_code`, `country_name`, `region`, `law_name`, `protection_type`, `term_of_protection`, `author_rights`, `moral_rights`, `orphan_works`, `digital_protection`, `fair_use_exceptions`, `registration_required`, `enforcement_body`, `treaties_signatory`, `treaties_not_party`, `linked_resources`, `notes`.

To filter by treaty or term, download the `bundle` once and filter locally (the web app does exactly that); there is no server-side search.

## Caching

- Every response has a weak `ETag` and `Last-Modified`, plus `Cache-Control: public, max-age=300`. Send `If-None-Match` / `If-Modified-Since` and you get an empty `304` while the data has not changed.
- Responses are gzip-compressed when the client sends `Accept-Encoding: gzip` (the full dataset goes from ~30 KB to ~4 KB).
- `meta.version` (and `version` inside the bundle) is a content hash: it only changes when the data changes, so it is a cheap way to know whether to refresh a local copy.

## Errors

Errors are JSON: `{ "error": "message" }`.

| Status | When |
|---|---|
| 400 | Missing or malformed `tld` / `slug`, or unknown `action` |
| 404 | Unknown jurisdiction, no cached text for that slug, or the dataset has not been built (`npm run build`) |
| 405 | Any method other than `GET`, `HEAD`, `OPTIONS` |

## Notes

- `tld` and `slug` are validated against fixed patterns (`^[a-z]{2}$`, `^[a-z0-9][a-z0-9-]{0,99}$`) before they touch the file system; nothing else from the request is used in a path.
- CORS is open (`Access-Control-Allow-Origin: *`) because the data is public and read-only.
- Cached texts exist only after running `npm run cache:texts` (see the README).
