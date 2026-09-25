# Document Formats Database

Paper, document, book and screen sizes, organized by country and by type, as
plain CSV files. Includes production specs (binding, folding, color spaces,
accessibility), a SQL schema, a REST API reference, and a browser demo
translated into 30 languages.

## Folder structure

```
document-formats/
├── countries/     Formats grouped by country or region
├── formats/       Formats grouped by document type
├── devices/       Screen sizes and resolutions
├── specs/         Standards, production and accessibility specs
├── app/           Interactive demo (index.html), JS library (document-formats.js), translations/
├── docs/          Tutorials, frontend guide, localization guide
├── tests/         Node tests for the JS library and the CSV data
├── database_schema.sql
└── api_reference.json
```

## Data files

### `countries/`

| File | Rows | Contents |
|---|---|---|
| `usa_formats.csv` | 13 | Letter, Legal, Tabloid, Ledger, Executive, envelopes, index and business cards |
| `mexico_formats.csv` | 10 | Carta, Oficio, Oficio Extendido, Media Carta, A4/A3 |
| `europe_formats.csv` | 14 | ISO A, B and C series, DL and E4 envelopes |
| `japan_formats.csv` | 14 | JIS B series, Hagaki, Oufuku Hagaki, Meishi |
| `china_formats.csv` | 12 | 16K, 8K, 32K, ISO A3/A4, invoices |
| `asia_pacific_formats.csv` | 27 | India, Thailand, Vietnam, Philippines, Singapore, Hong Kong, Malaysia, Indonesia, Pakistan, Bangladesh |
| `middle_east_africa_formats.csv` | 28 | Turkey, UAE, Saudi Arabia, Israel, Egypt, South Africa, Nigeria, Kenya, Morocco, Tanzania |
| `other_countries_formats.csv` | 40 | Canada, Australia, India, Brazil, UK, Russia |
| `countries_200_complete.csv` | 197 | Primary format, measurement system, region, language and currency for 197 countries and territories |
| `country_compatibility_guide.csv` | 32 | Per-country compatibility notes and adoption challenges |

### `formats/`

| File | Rows | Contents |
|---|---|---|
| `all_formats_master.csv` | 36 | Representative sample of every other file, with `format_id` and `type` |
| `book_formats.csv` | 15 | Mass Market, Trade Paperback, Digest, Royal, Demy, Crown, hardcover, coffee table |
| `book_margins.csv` | 17 | Typical top/bottom/inner/outer margins per book format |
| `legal_documents.csv` | 12 | Pleading paper, deeds, contracts, wills, affidavits, powers of attorney |
| `corporate_stationery_formats.csv` | 27 | Letterhead, business cards, envelopes, folders, memos, certificates |
| `labels_stickers_formats.csv` | 25 | Rectangular, round and barcode labels, bumper stickers |
| `photo_print_formats.csv` | 22 | 4x6 to large prints, instant film, slides |
| `specialty_papers.csv` | 17 | Cards, booklets, brochures, flyers, posters, banners |

### `devices/`

| File | Rows | Contents |
|---|---|---|
| `monitors.csv` | 19 | Size, physical width/height, resolution and PPI |
| `smartphones.csv` | 20 | Screen size, resolution, PPI, panel type |
| `tablets.csv` | 20 | Same columns as smartphones |
| `ereaders.csv` | 20 | Same columns plus manufacturer and refresh rate |
| `pixel_density_guide.csv` | 23 | Recommended PPI and viewing distance per device and use |
| `video_resolutions.csv` | 24 | QVGA to 8K, DCI 4K, ultrawide, with bitrate recommendations |

### `specs/`

| File | Rows | Contents |
|---|---|---|
| `iso_216_series.csv` | 33 | ISO 216 A0–A10, B0–B10, C0–C10 |
| `standards_reference.csv` | 33 | ISO, ANSI, JIS, GB/T and DIN standard numbers per format |
| `technical_specifications.csv` | 33 | Paper weight (gsm), recommended DPI, finishes |
| `format_equivalence_matrix.csv` | 34 | How close two formats are, and whether one folds, trims or fits into the other (see below) |
| `fold_compatibility.csv` | 20 | Which formats fold into which (A4 → A5, Letter → half-letter…) |
| `binding_styles.csv` | 15 | Binding methods with cost factor, durability and page range |
| `color_spaces.csv` | 20 | sRGB, Adobe RGB, DCI-P3, Rec. 2020, CMYK… with gamut coverage |
| `minimum_font_sizes.csv` | 29 | Minimum and recommended font sizes per format and device |
| `wcag_contrast.csv` | 29 | WCAG AA/AAA contrast ratios per UI element |
| `regional_compatibility.csv` | 29 | Primary, secondary and legal standards per region |

## Columns

Most format files share these columns:

- `format_name` — e.g. "A4", "Letter", "Oficio"
- `width_mm`, `height_mm`, `width_inches`, `height_inches`
- `aspect_ratio` — e.g. "1:1.414" for the A series
- `country` — country or region, or "International"
- `category` — Standard, Envelope, Card, Book, Legal, Brochure…
- `description`, `common_use`

`all_formats_master.csv` adds `format_id` and `type` (Paper, Card, Book,
Document, Envelope…); every one of its rows is a copy of a row in another
file. Files are UTF-8; any value containing a comma is wrapped in double
quotes.

### The equivalence matrix

Each row of `specs/format_equivalence_matrix.csv` pairs two formats from the
catalogs above:

- `similarity_percent` — how alike the two sheet sizes are, 0–100: the
  average percentage difference of their short sides and of their long
  sides, ignoring orientation. It's the same number `FormatConverter`
  computes, so Ledger and Tabloid score 100 and A4 → A5 scores 71.
- `can_rotate` — one is portrait and the other landscape.
- `can_fold` — folding the source (in half, or in thirds for a DL envelope)
  gives the target.
- `can_trim` — the source is larger on both sides, so it can be cut down to
  the target.
- `compatibility_level` — `Contains` (an envelope that holds the target),
  `Folded`, `Direct` (95% or more), `Similar` (85–94%) or `Different`.

## Using the data

### Straight from the CSV files

```bash
# All USA formats
grep "USA" countries/usa_formats.csv

# ISO formats close to A4
awk -F',' '$2>200 && $2<220 && $3>270 && $3<310' specs/iso_216_series.csv
```

```python
import pandas as pd

formats = pd.read_csv('formats/all_formats_master.csv')
books = formats[formats['type'] == 'Book']
near_a4 = formats[formats['height_mm'].between(277, 317)]
```

### From JavaScript

`app/document-formats.js` has loaders and helpers for every file: `FormatLoader`,
`FormatSearcher`, `FormatConverter` (similarity between two formats),
`FormatValidator`, `DocumentGenerator` (HTML and print CSS for a given
page size), `ScreenDeviceManager`, `BookMarginManager`,
`SpecificationManager` and `LocalizationManager`. Their default paths assume
the page is served from `app/`.

```javascript
const loader = new FormatLoader('../formats/all_formats_master.csv');
await loader.load();

const converter = new FormatConverter(loader.formats);
converter.findEquivalents('A4', 90);
```

See `docs/Tutorials.md` for step-by-step examples and
`docs/frontend_implementation_guide.md` for UI patterns.

### As a database

`database_schema.sql` defines 14 MySQL/MariaDB tables (countries, formats,
technical specs, conversions, compatibility, paper weights, finishes,
variants, margins, standards, suppliers, prices…) and three views:
`v_formats_detailed`, `v_formats_by_measurement` and `v_format_equivalents`.
The CSV files fill the format and specification tables; the supplier and
price tables are schema only, with no data shipped.

```sql
SELECT df1.format_name AS from_fmt, df2.format_name AS to_fmt, fc.equivalence_percentage
FROM format_conversions fc
JOIN document_formats df1 ON fc.from_format_id = df1.format_id
JOIN document_formats df2 ON fc.to_format_id = df2.format_id
WHERE df1.format_name = 'Letter';
```

`api_reference.json` describes a REST API over that schema (`/formats`,
`/formats/search`, `/formats/{id}/conversions`, `/countries/{code}/formats`,
`/formats/by-dimensions`, `/paper-weights`…). It is a specification; no
server is included.

## Demo

Open `app/index.html` in a browser: format browser with search and filters,
side-by-side comparison and a converter showing how close two formats are.
It runs on five built-in sample formats rather than loading the CSV files.

`app/translations/` holds UI strings in 30 languages for
`LocalizationManager` in `document-formats.js`; see `docs/Localization.md`.

## Tests

```bash
node --test 'document-formats/tests/*.test.js'
```

Run from the repository root with Node 22; CI runs the same command. Covers
the CSV parser, HTML escaping, the loaders and converters in `document-formats.js`,
and checks every CSV file for rows with the wrong number of fields (usually
an unquoted comma), duplicate entries, and mm/inch values that disagree.

## Notes on the standards

- **ISO 216** (A, B, C series) is used in most countries. A4 is 210×297 mm;
  every A size has a 1:√2 aspect ratio, so halving one gives the next.
- **USA** uses Letter (8.5×11") and Legal (8.5×14"); envelopes are numbered
  (#10, #6¾…).
- **Japan's JIS B series** differs slightly from ISO B: JIS B5 is 182×257 mm,
  ISO B5 is 176×250 mm.
- **China** uses ISO sizes alongside the traditional 8K, 16K and 32K.
- **Mexico** mixes ISO with Carta (Letter) and Oficio (216×340 mm, shorter
  than US Legal), common for legal documents.

1 inch = 25.4 mm.

## Adding formats

Follow the column layout of the file you're adding to, give both mm and
inches, and set `country`, `category`, `description` and `common_use`. If the
format is representative, add it to `formats/all_formats_master.csv` too. The
tests will flag mm/inch mismatches and duplicate names.
