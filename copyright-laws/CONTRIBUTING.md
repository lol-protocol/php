# Contributing to copyright-laws

The data lives in one file, `jurisdictions/copyright_laws_master.csv`. Everything else in `jurisdictions/` is generated or recorded by scripts. This page is the whole workflow.

## 1. Edit the master CSV

- One row per law. Fields containing a comma must be quoted. Dates are `YYYY-MM-DD`.
- Never invent a value. If you cannot find it, leave the cell as it is (`Not specified`) or empty, and say so in `notes`.
- Rows you did not check against the official text are `data_status=researched` with a `confidence` (`high`, `medium`, `low`) and `last_checked`. Only mark a row `verified` after reading the official text. Mark low-confidence text values with `(unconfirmed)` at the end.
- A known upcoming change (bill passed, law not yet in force) goes in `pending_change`, with `pending_date` when the date is known. Do not change `law_name` or the main fields until the change is in force.

## 2. Record where a value comes from

For each value you changed, add or update a line in `jurisdictions/field_sources.csv`:

```
country_code,law_name,field,url,confidence,checked
```

- `law_name` may be empty when the jurisdiction has a single law; it is required otherwise.
- `url` is a public page you actually opened. `checked` is the day you looked at it.
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

## What not to do

- Do not edit `jurisdictions/{tld}/*` or `jurisdictions/index.json` by hand: the next build overwrites them.
- Do not copy code between this module and `privacy-laws/`: they are independent on purpose.
- Do not mark rows `verified` from search results or summaries.

See [README.md](README.md) for the columns, the API and the web app.
