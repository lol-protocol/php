# Document Formats Database

Paper, document, book and screen sizes, organized by country and by type, as
plain CSV files. Includes production specs (binding, folding, color spaces,
accessibility), a SQL schema, a REST API reference, a browser demo (in
English) and a JavaScript library whose interface strings come in 30
languages (six fully translated; the rest show English for what isn't
translated yet).

## Folder structure

```
document-formats/
├── countries/     Formats grouped by country or region
├── formats/       Formats grouped by document type
├── devices/       Screen sizes and resolutions
├── specs/         Standards, production and accessibility specs
├── app/           Interactive demo (index.html), JS library (document-formats.js), tabs.js, url-state.js, translations/
├── docs/          Tutorials, frontend guide, localization guide
├── tests/         Node tests (library, CSV data) and the database check
├── database_schema.sql
├── import_database.php   Loads the CSV files into database_schema.sql
├── api_reference.json
└── generate_api_examples.js   Keeps the API examples equal to the CSV rows they show
```

File names use mixed case (`Tutorials.md`, `Localization.md`), except the
standard convention names the rest of the repo also uses as-is (`README.md`,
`LICENSE`, `CHANGELOG.md`). This has flipped back and forth across a few
PRs — it's a deliberate choice, not an oversight, so please keep it this way.

## Documentation

Which guide to open:

| Guide | Read it to |
|---|---|
| This README | Know what the files are, load them from JavaScript or SQL, run the demo and the tests |
| `docs/Tutorials.md` | Build something small step by step: a format selector, a comparison tool, a PHP endpoint, Python analysis |
| `docs/frontend_implementation_guide.md` | Reuse ready-made HTML and JavaScript components and the UI patterns around them |
| `docs/Localization.md` | Translate the interface, add a language, use `LocalizationManager` |
| `api_reference.json` | See the REST endpoints the database would serve (the examples are generated from the CSV files) |

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
| `all_formats_master.csv` | 36 | Representative sample of the ISO, USA, Mexico, Japan and China catalogs and of the book, stationery, legal document and specialty paper files (the other regional files, labels and photo prints have no row here), with `format_id` and `type` |
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
| `smartphones.csv` | 20 | Screen size, size of the device, resolution, PPI, panel type |
| `tablets.csv` | 20 | Same columns as smartphones |
| `ereaders.csv` | 18 | Same columns plus manufacturer and refresh rate (a nominal value, not measured) |
| `pixel_density_guide.csv` | 23 | Recommended PPI and viewing distance per device and use |
| `video_resolutions.csv` | 24 | QVGA to 8K, DCI 4K, ultrawide, with bitrate recommendations |

In the phone, tablet and e-reader files `width_inches` and `height_inches` are
the size of the whole device (its body; for a foldable, unfolded), in the same
orientation as the resolution. In `monitors.csv` they are the size of the
screen itself. The figures follow the makers' published specifications (some
come from retailer spec sheets); `release_year` and `refresh_hz` were not
re-checked. The tests check that `ppi`, `aspect_ratio` and the sizes agree
with the resolution and the screen size.

### `specs/`

| File | Rows | Contents |
|---|---|---|
| `iso_216_series.csv` | 33 | ISO A0–A10 and B0–B10 (ISO 216) and C0–C10 (envelopes, ISO 269) |
| `standards_reference.csv` | 33 | ISO, ANSI, JIS, GB/T and DIN standard numbers per format |
| `technical_specifications.csv` | 11 | Paper weight (gsm), recommended DPI and finish for each sheet size that has one |
| `product_specifications.csv` | 22 | The same for printed products that are not a sheet size (Brochure, Receipt, Sticker…), each linked to its usual paper type when one is clear |
| `format_equivalence_matrix.csv` | 34 | How close two formats are, and whether one folds, trims or fits into the other (see below) |
| `fold_compatibility.csv` | 20 | Which formats fold into which (A4 → A5, Letter → half-letter…) |
| `binding_styles.csv` | 15 | Binding methods with cost factor, durability and page range |
| `color_spaces.csv` | 20 | sRGB, Adobe RGB, DCI-P3, Rec. 2020, CMYK… with gamut coverage |
| `minimum_font_sizes.csv` | 29 | Minimum and recommended font sizes per format and device |
| `wcag_contrast.csv` | 29 | WCAG AA/AAA contrast ratios per UI element |
| `regional_compatibility.csv` | 29 | Primary, secondary and legal standards per region |
| `paper_types.csv` | 50 | Named paper stock — color, texture, finish and weight — independent of sheet size (see below) |
| `finish_types.csv` | 13 | The finish vocabulary (Uncoated, Matte, Glossy, Thermal…) every `finish` and `finish_type` column draws from |
| `paper_weight_standards.csv` | 12 | Standard paper weights: gsm with its bond, text and cover pound equivalents |
| `paper_type_translations.csv` | 50 | `paper_types.csv` names in the 28 languages the demo doesn't cover with `type_name`/`type_name_en` |

## Columns

Every format catalog (the files under `countries/` and `formats/` with sizes, plus `specs/iso_216_series.csv`) has these columns:

- `format_name` — e.g. "A4", "Letter", "Oficio"
- `width_mm`, `height_mm`, `width_inches`, `height_inches`
- `aspect_ratio` — e.g. "1:1.414" for the A series
- `country` — country or region, or "International"
- `category` — Standard, Envelope, Card, Book, Legal, Brochure…
- `description`, `common_use` (empty in `specs/iso_216_series.csv`, whose
  `description` says what each size is for)

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
  the target. It is `No` when the source is an envelope that holds the
  target (C4 → A4), which is `Contains` instead.
- `compatibility_level` — `Contains` (an envelope that holds the target),
  `Folded`, `Direct` (95% or more), `Similar` (85–94%) or `Different`.

### Paper types

`specs/paper_types.csv` is a separate catalog of named paper stock — not a
sheet size, so it's not in `FORMAT_CATALOG_FILES` and doesn't get a region.
Any of the ~300 formats can in principle be printed on any of its 50 rows;
the demo's "Paper Types" tab lets you pick a format and see it listed
against every paper type. Columns: `type_name` (Spanish, including regional
terms like "Papel roneo" and "Papel chambril" that aren't used everywhere
Spanish is spoken), `type_name_en`, `category`, `color`, `texture`,
`finish`, `weight_gsm_range`, `common_use`, `notes`. Weight ranges are
typical, not a spec any mill is bound to — paper names and grammages vary
by manufacturer and region more than ISO sheet sizes do.

`finish` holds one term of `finish_types.csv`; what makes a particular
paper special ("coated one side", "with watermark") goes in `notes`. The
`finish_type` of `technical_specifications.csv` and
`product_specifications.csv` uses the same terms, and `A/B` means either
(`Matte/Glossy`). `paper_weight_standards.csv` converts between gsm and the
US basis weights: the pound column that names the row ("20 lb Bond") is the
nominal figure, the other two are computed from the gsm (bond 17×22", text
25×38", cover 20×26"). A test checks all of this, so a new paper type
can't use a finish that isn't in the list.

`specs/paper_type_translations.csv` adds the same 50 names in the other 28
languages the demo's `app/translations/` covers (one column per language
code, joined on `type_name`). The demo reads it to show each paper type
in the language chosen in its selector (see "Demo" below); it isn't in the
database. Unlike the sheet-size
names, which are real international/national standards, most of these
paper names are translated by machine, not by a native speaker of each
language, and two (`Papel roneo`, `Papel chambril`) are Latin American
trade terms with no standard equivalent abroad, so those two columns carry
a loanword plus a short gloss instead of a false "foreign" name. Treat
this file the same way as the machine-translated taglines in
`app/translations/`: useful as a starting point, not as a verified
translation.

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
`FormatValidator`, `FormatStatistics` (counts by category and country),
`FormatBatchProcessor` (filters formats and exports them as CSV, HTML or
JSON), `DocumentGenerator` (HTML and print CSS for a given page size),
`ScreenDeviceManager`, `BookMarginManager`, `SpecificationManager` and
`LocalizationManager`. Their default paths assume
the page is served from `app/`.

```javascript
const loader = new FormatLoader('../formats/all_formats_master.csv');
await loader.load();

const converter = new FormatConverter(loader.formats);
converter.findEquivalents('A4', 90);
```

`DocumentFormats.load()` does the wiring in one call: it loads every catalog and
returns an object with `formats`, `searcher`, `converter`, `generator`, `validator`,
`statistics`, `batch`, `specs`, `margins` and `devices` already connected
(`DocumentFormats.load({only: ['specs']})` skips what you don't need).

```javascript
const docs = await DocumentFormats.load();
docs.converter.convert('Letter', 'A4');
docs.specs.getPaperType('Papel bond');
```

Conventions the classes follow:

- **One thing that isn't there is `null`** (`getMargins`, `getDevice`, `getPaperType`, `convert`);
  **a list with nothing in it is `[]`**.
- **`DocumentGenerator` throws `UnknownFormatError`** for a format that isn't in the catalog; its
  `suggestions` holds the three closest names (`A44` suggests `A4`). `FormatValidator.validateForPrinting`
  reports the same suggestions in its result instead of throwing.
- **A file that can't be read** (404, server error) is reported with its path and status, not read as rows.
- **Margins and print limits follow the data when it is loaded.** `DocumentFormats.load()` gives the generator
  `formats/book_margins.csv` (`getRecommendedMargins('Trade Paperback')` uses its row) and the validator
  `specs/technical_specifications.csv` (`validateForPrinting` judges a format by its own `recommended_dpi`:
  the low end is the recommended DPI and half of it the minimum). Without them, the general 20 mm margins and
  300/150 DPI apply.
- **`search()` breaks ties** by exact name first, then alphabetically, so the order doesn't depend on the CSV files.
- **Rows from `SpecificationManager` are frozen**: they are shared by every caller.
- **Units**: `MM_PER_INCH`, `mmToInches()` and `inchesToMm()` are the one place for the factor;
  reports keep their fixed decimals as text (`"21.0"`), through `fixed()`.

See `docs/Tutorials.md` for step-by-step examples and
`docs/frontend_implementation_guide.md` for UI patterns.

### As a database

`database_schema.sql` defines 16 MySQL/MariaDB tables and four views
(`v_formats_detailed`, `v_formats_by_measurement`, `v_format_equivalents`,
`v_products_with_paper`, which pairs each product with its usual paper stock).
`import_database.php` creates them and loads the CSV files:

```bash
export DB_DSN='mysql:host=127.0.0.1;dbname=formats;charset=utf8mb4' DB_USER=... DB_PASSWORD=...
php document-formats/import_database.php --create-schema   # on an empty database
php document-formats/import_database.php                   # later: reloads the data
```

It fills countries (plus two regions, International `XI` and Europe `XE`,
since every format needs a country), categories, `document_formats`, the
conversions from the equivalence matrix, technical specifications, book
margins, per-country compatibility and standard references. It also fills
the tables that have no format or country behind them: `paper_types`,
`finish_types`, `paper_weight_standards` and `product_specifications`
(which points at `paper_types` when a product has a usual paper). Book
margin rows that name a genre ("Poetry") instead of a format are skipped
and listed. The `format_suppliers`, `format_prices` and `format_variants`
tables are reserved: no CSV feeds them, so they stay empty on purpose.
Tested on MySQL 8.0 and MariaDB 10.11 (the CI imports into both).

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

The demo reads the CSV files, so it needs to be served over HTTP (browsers
block a page opened from disk from reading other local files):

```bash
cd document-formats
python3 -m http.server
# then open http://localhost:8000/app/
```

It loads every catalog listed in `FORMAT_CATALOG_FILES` (about 300 formats)
and offers a browser with search and country/category filters, a
comparison table, a converter that shows how similar two sizes are, and a
Paper Types tab (the 50 rows of `specs/paper_types.csv`) that can combine
a chosen size with every paper type at once.

The language selector in the corner of the page offers the 30 languages of
`app/translations/` and starts in the browser's language when the demo has
it; the choice is remembered. It changes the tabs, labels, filters, card
and table captions and messages, the `lang` and text direction of the page
(Arabic reads right to left), and the name of every paper type, which comes
from `specs/paper_type_translations.csv`. What stays in English is data
(colors, textures, categories, notes, country names) and the body of the
About tab; those texts are marked `lang="en"`, and the names of the paper
types carry the language they are shown in.

German, English, Spanish, French, Japanese and Chinese have every UI string;
the other 24 translate the title, the tabs and the unit names and show
English for the rest, marked `lang="en"` so a screen reader doesn't read it
with the wrong voice (the strings written in the HTML, the captions of the
cards JavaScript draws, and the count announced when you filter). See
`docs/Localization.md`.

The address keeps what you are looking at, so you can share it:
`app/index.html#tab=converter&from=A4&to=Letter`. It holds the tab, the
language (unless English), the search boxes and the filters (`url-state.js`
lists the keys). The **Download CSV** button saves the formats on screen, with
the same columns as the catalogs plus `region`, in UTF-8 with a byte order mark so a
spreadsheet reads accents correctly.

The page follows the WAI-ARIA tabs pattern (arrow keys, Home and End move
between tabs; in a right-to-left language the arrows are reversed), every
field has a label, the page has `header`, `main` and `footer`, and the result
count is announced when you filter. Colors meet WCAG AA contrast in light
and dark mode, and animations stop under `prefers-reduced-motion`. It was
checked with `axe-core` in Chromium on all five tabs, in light and dark mode
and in six languages, with no violations; `tests/demo.test.js` guards the
parts that can be checked without a browser (labels, tabs, translation keys);
`tests/tabs.test.js` covers the tab keys (`tabs.js`) and the address state.

## Tests

```bash
node --test 'document-formats/tests/*.test.js'
```

Run from the repository root with Node 22; CI runs the same command, and
also imports everything into MySQL 8 and checks it with
`tests/database_check.php`. The Node tests cover
the CSV parser, HTML escaping, the loaders and converters in `document-formats.js`,
and checks every CSV file for rows with the wrong number of fields (usually
an unquoted comma), duplicate entries, and mm/inch values that disagree.

Three more tests keep the pieces in step:

- `tests/coherence.test.js` checks that the importer reads every column of every CSV it loads
  (the ones it leaves out are listed with a reason), that its `INSERT`s only name columns of
  `database_schema.sql`, and that the examples in `api_reference.json` equal their CSV rows.
  If a CSV changes, run `node generate_api_examples.js` to refresh them.
- `tests/docs.test.js` checks that every JavaScript example in this README and in `docs/` is valid
  JavaScript and only calls methods the library has. It doesn't run them.

## Notes on the standards

- **ISO 216** (A and B series; the C series for envelopes is ISO 269) is used in most countries. A4 is 210×297 mm;
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
