# Research notes — October 2026 additions

Everything here was gathered with web search only (no direct access to WIPO, WTO or the statutes), on 2026-10-08.
Treat it as a to-do list for verification, not as verified data.

| File | What it is |
|---|---|
| `A-europe-notes.md`, `B-americas-apac-notes.md`, `C-mena-africa-notes.md` | Sources, confidence and doubts for each new law row (73 jurisdictions). |
| `membership-berne-trips.csv` + `T1-berne-trips-notes.md` | Berne Convention party / WTO member (TRIPS) per jurisdiction, with source URL and confidence. |
| `membership-wct-wppt.csv` + `T2-wct-wppt-notes.md` | WCT / WPPT contracting party per jurisdiction. |
| `membership-first-pass.csv` | An earlier, partial pass over the same four treaties (cut short by the search budget). |

How the data was used: only `yes` answers were copied into `treaties_signatory`. `no` and `unknown` answers were **not** written
(a weak `no`, e.g. Norway or Iceland for WCT/WPPT from 2016–18 sources, would be worse than a gap). No source contradicted another
on a `yes`/`no` pair. Jurisdictions still without any confirmed treaty: Iraq, Iran, Ethiopia.

Weak spots to verify first: Hong Kong and Macao are listed through China's accession; Uzbekistan and Ethiopia are WTO accession
candidates; the six Berne "no" results (Angola, Ethiopia, Iraq, Iran, Myanmar, Taiwan) rest on absence from lists.

## Authority names (14 Oct 2026)

`authorities-verified.csv`: the regulator / copyright body names of the original rows, checked with web search (source URL and confidence per row). Vague names were replaced by official ones and wrong ones corrected (for example Argentina: AAIP, not Spain's AEPD; copyright handled by culture or justice ministries rather than patent offices). Only the name was checked, so those rows keep `data_status=curated`.

## Rights fields (14 Oct 2026)

`fields-europe.csv`, `fields-americas-apac.csv`, `fields-mena-africa.csv`: moral rights, orphan works, digital protection and exceptions for non-EU jurisdictions, researched by web search (value, confidence, source per cell). `UNKNOWN` cells stay "Not specified"; low-confidence values carry "(unconfirmed)". Where nothing was found for a WCT party, `digital_protection` says the treaty requires anti-circumvention protection without claiming the national rule.

## Currency sweep of the original rows (14 Oct 2026)

`sweep-C1.csv`, `sweep-C2.csv`: every original row checked by web search for replaced or amended laws and wrong titles or dates. Fixes were applied to the master CSV; changed rows become `data_status=researched`. Notable: Saudi Arabia (Copyright Law 2026, Royal Decree M/169; term unconfirmed), UAE (Federal Decree-Law 38/2021), Nigeria (Copyright Act 2022), Russia (Civil Code Part IV), Belgium (Code of Economic Law Book XI), Spain (RDL 1/1996); Canada, Singapore, Chile and Israel now life + 70. Caveat: in `sweep-C2.csv` the rows for FI, IE, GR, AT, CZ, HU, RO, CO and CN were marked "current" without a search result behind them; they are unverified and stay `curated`.
