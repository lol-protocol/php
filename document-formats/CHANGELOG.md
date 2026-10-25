# Changelog

What changed in `document-formats/`, newest first. Each entry names the pull request that
carries the details. Dates are those of the merge.

## Unreleased

- Demo: `index.html` keeps no inline CSS or JavaScript (now `app/demo.css` and `app/demo.js`), the
  Paper Types categories are translated (`paperCategories.*`), the comparison says when it cuts the
  list at 100 rows, **Reverse order** next to **Sort by**, and the converter lists the five closest
  formats to the one chosen. 13 new strings per language, 182 in all.
- `docs/frontend_implementation_guide.md` no longer defines its own `FormatConverter`: it uses the
  library's, and a test stops a guide from redeclaring a library class.

- Demo: searching ignores case and Latin accents (`normalizeText()`, also used by `FormatSearcher`),
  the Browser tab's search also looks in country, category and region, the category and region
  filters and the card badges are translated (24 new strings per language: `regions.international`,
  17 more `categories.*`, and the sort labels), and a **Sort by** menu (name, width, height, area)
  orders the cards and is kept in the address. 169 strings per language now.

- The 24 languages that translated only the title, the tabs and the units now have all 145 UI
  strings (Portuguese, Russian, Arabic, Korean, Italian, Dutch, Turkish, Hindi, Thai, Vietnamese,
  Polish, Swedish, Norwegian, Danish, Finnish, Greek, Czech, Hungarian, Romanian, Bulgarian,
  Serbian, Croatian, Slovak, Ukrainian). The tab names and subtitle of those files, which were
  English text, are translated too. None has been reviewed by a native speaker.
- The name of the downloaded CSV may use any letters, not only ASCII: Chromium saves non-ASCII
  names correctly once the system has a UTF-8 locale (the `download` name seen before came from a
  container without one). Japanese and Chinese now use their own names.

## 2026-10-22

- Quick wins: the About tab and the footer of the demo are translated (10 new strings, 145
  per language); the page has no inline `style` attributes, a print stylesheet and
  `description` / `color-scheme` meta tags; `LocalizationManager.isRtl()` replaces the lists of
  right-to-left languages; `COUNTRIES_FILE` names the countries file once; statistics of an
  empty catalog are zeros instead of `NaN`; `FormatLoader` no longer writes to the console.
  `.nvmrc` pins Node 22, the version CI uses.

## 2026-10-21

- Demo: `Tabs` class (`app/tabs.js`), state in the address (`app/url-state.js`), a button that
  downloads the formats on screen as CSV, `lang` marks on what JavaScript draws (#190).
- Library: book margins and per-format DPI come from the CSV files, `search()` breaks ties
  deterministically, the `v_products_with_paper` view (#189).
- Standards references verified; MariaDB 10.11 is exercised by CI (#187).

## 2026-10-20

- Importer without repeated loops, API examples generated from the CSV files
  (`generate_api_examples.js`), tests for the code in the documentation (#180).
- Demo accessible (landmarks, tabs pattern, labels, contrast, RTL) and available in 30
  languages, with the paper type names translated (#148).
- Finish vocabulary, standard paper weights, product specifications and coherence tests (#147).

## 2026-10-17 and earlier

- Names of the 50 paper types in 28 more languages (#116).
- Catalog of 50 paper types with color and texture (#99).
- Data corrections and coherence tests for devices, formats and the README (2026-10-14).
