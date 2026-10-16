# Research notes — October 2026 additions

Everything here was gathered with web search only (no direct access to official pages), on 2026-10-08.
Treat it as a to-do list for verification, not as verified data.

| File | What it is |
|---|---|
| `frameworks-membership.csv` | Per-country answers (`yes` / `no` / `unknown`) for GDPR, EU adequacy, Convention 108, APEC CBPR, with a confidence level and a note. Only the `yes` values were copied into the `frameworks` column of the master CSV. |
| `M-privacy-notes.md` | How each membership list was obtained, disagreements between sources, URLs. |
| `P1-eu-eea-notes.md`, `P4-asia-pacific-notes.md`, `P5-mena-notes.md` | Sources and confidence for the new law rows (EU/EEA, Asia-Pacific, Middle East). |

The batches for wider Europe/Central Asia, the Americas and Africa have no per-row notes file: those rows carry their caveats in the `notes` column of the master CSV.

## Authority names (14 Oct 2026)

`authorities-verified.csv`: the regulator / copyright body names of the original rows, checked with web search (source URL and confidence per row). Vague names were replaced by official ones and wrong ones corrected (for example Argentina: AAIP, not Spain's AEPD; copyright handled by culture or justice ministries rather than patent offices). Only the name was checked, so those rows keep `data_status=curated`.

## Currency sweep of the original rows (14 Oct 2026)

`sweep-P1.csv`, `sweep-P2.csv`: every original row checked by web search for replaced or amended laws and wrong titles or dates (`module,code,status,field,value,conf,source,note`). Fixes were applied to the master CSV; changed rows become `data_status=researched` with the weakest confidence applied. Low-confidence dates were not applied; low-confidence text carries "(unconfirmed)". Notable: Chile's Law 19.628 is replaced by Law 21.719 (in force 1 Dec 2026); California's row now covers the CCPA as amended by the CPRA, enforced by the CPPA. Pending bills (Canada's new privacy bill, Australia's second tranche, the EU digital omnibus, the BDSG reform) are not law and were not applied.

## Confidence check and remaining gaps (15 Oct 2026)

- `noconf-check.csv`: the 30 researched rows that had no confidence level (wider Europe, Americas, Africa batches) were re-checked: a confidence per row (8 high, 12 medium, 10 low) and corrections where a source supported them (Georgia's authority is now the State Audit Office; Uzbekistan's the Personalization Agency; Botswana and Uganda effective dates; Panama penalties). Low-confidence corrections were not applied.
- `privacy-gaps-r.csv`: retention periods, exemptions and key requirements that said "Not specified": 35 filled (3 low-confidence, marked "(unconfirmed)"); 2 unknown and 2 low-confidence "no rule" answers left as gaps.
- From this date, applied values with a source are listed in `countries/field_sources.csv`, and every change to a row is in `countries/changes.csv`.
