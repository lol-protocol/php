# Contributing to copyright-laws

The data lives in one file, `jurisdictions/copyright_laws_master.csv`. Everything else in `jurisdictions/` is generated or recorded by scripts. This page is the whole workflow.

## 1. Edit the master CSV

- One row per law. Fields containing a comma must be quoted. Dates are `YYYY-MM-DD`.
- Never invent a value. If you cannot find it, leave the cell as it is (`Not specified`) or empty, and say so in `notes`.
- Rows you did not check against the official text are `data_status=researched` with a `confidence` (`high`, `medium`, `low`) and `last_checked`. Only mark a row `verified` after reading the official text. Mark low-confidence text values with `(unconfirmed)` at the end.
- A known upcoming change (bill passed, law not yet in force) goes in `pending_change`, with `pending_date` when the date is known. Do not change `law_name` or the main fields until the change is in force.

## 2. Record where a value comes from

For each value you changed, record its source with `npm run sources -- add <CODE> <field> <url> [--confidence=high|medium|low] [--law="law name"]`. It writes one line in `jurisdictions/field_sources.csv` (which points at its URL by `source_id`) and, if the URL is new, one in `jurisdictions/source_urls.csv` (each URL once; the id is the hash of the URL):

```
country_code,law_name,field,source_id,confidence,checked
```

- `law_name` may be empty when the jurisdiction has a single law; it is required otherwise.
- The URL is a public page you actually opened. `checked` is the day you looked at it.
- One line per law and field. Replace the old line when you change the value.

## 3. Record the change

```bash
npm run changes          # appends what differs from the last commit to jurisdictions/changes.csv
```

Use `-- --since=origin/master` to compare with another revision. A renamed law keeps its history and sources. CI runs `npm run changes -- --check --since=origin/<base>` on pull requests and fails when an edit is missing from `changes.csv`.

## 4. Validate, build, test

```bash
npm run validate         # strict checks; warnings are things to review, not blockers
npm run build            # regenerates jurisdictions/{tld}/ and jurisdictions/index.json
npm test
npm run test:e2e         # needs Playwright's Chromium (see the README)
```

Commit the generated files together with the CSV: `npm run build:check` must pass.

## When a reference link breaks

The weekly link check (`.github/workflows/laws-links.yml`) keeps one open issue, "Enlaces de referencia rotos (leyes)", with every broken URL and the laws that rely on it. To fix one:

1. Find the page that replaces it, preferably an official one (the law's text or the authority's own page), and open it. The URL in `source_urls.csv` or `linked_resources` must be a page you actually read.
2. Replace the URL in every place it appears. Each URL is stored once in `source_urls.csv`, so `npm run replace-url` (step 3) does it all: that file and, if it is a law's own link, `linked_resources` in the master CSV.
3. (A dead link: `npm run replace-url -- <old URL> <new URL>` swaps it in the master CSV and in `source_urls.csv` at once.) Run `npm run changes` and `npm run build` (an edit of `linked_resources` is a recorded change), then `npm run validate`, `npm run build:check` and `npm test`.
4. Run the "Leyes - enlaces de referencia" workflow by hand (`workflow_dispatch`) on your branch: from some machines the sites are unreachable, and this is the check that tells you the new URL answers. The issue closes itself on the next run where every link answers.

A 401, 403, 429, a 5xx that persists, a timeout or a redirect loop is only a warning, not a broken link: leave it unless the page is really gone.

## What not to do

- Do not edit `jurisdictions/{tld}/*` or `jurisdictions/index.json` by hand: the next build overwrites them.
- Do not copy code between this module and `privacy-laws/`: they are independent on purpose.
- Do not mark rows `verified` from search results or summaries.

See [README.md](README.md) for the columns, the API and the web app.

## Research files

Findings collected by searching go in as a CSV with the header `code,field,value,conf,source` (`conf` is `high`, `medium` or `low`; one URL you saw per row; `UNKNOWN` for what was not found; `CODE,status,NOLAW,conf,url` when there is no law). `npm run apply-research -- findings.csv --batch=batch.txt` turns it into new rows (the batch file has lines `CODE | name | region`) and patched cells with their field sources, marks low-confidence values `(unconfirmed)` and refuses anything the validation would reject; use `--dry` to see what it would do. Then run `npm run build` and `npm run changes`.

## Check unconfirmed values against their sources

Values marked "(unconfirmed)" can be checked against the text of the pages that back them. The repeatable part is `npm run verify`; reading the texts stays with a person or an agent.

1. `npm run verify -- plan --out=/tmp/verify --limit=25 --texts=/tmp/textos` writes `tasks/<code>.json` (the law, the field and the current value of each unconfirmed value), `PROMPT.md` (the instructions for whoever reads the texts) and prints the list of countries for step 2. `--only=` and `--skip=` choose countries.
2. Run the workflow "Leyes - textos de las fuentes" (`laws-textos-oficiales.yml`) by hand with that list and `campos` ticked: it publishes the source pages as text on a data branch (`datos/leyes-textos-<module>`), which a session without access to those sites reads with `git fetch` and `git worktree add`. The branch is rewritten on every run: copy what you need before the next one.
3. Read each country's pages against its task file (`PROMPT.md` says how) and write `out/<code>.csv` (`code,field,value,conf,source`, only what a text supports).
4. `npm run verify -- apply --from=/tmp/verify` applies the findings, one law at a time, with confidence medium or high (`--min=high` is stricter, `--dry` only reports). A value a text confirms loses its mark and gets its source.
5. `npm run translations -- --missing=spa` (and fra, deu, por) lists what is new to translate; then `npm run build`, `npm run changes` and a PR.

The pages are third-party guides and aggregators, not the official text: a value they support is at most medium confidence.
