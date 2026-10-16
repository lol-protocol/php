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

## Second pass on the remaining gaps (15 Oct 2026)

`gaps-r0.csv` … `gaps-r3.csv`: another web-search pass on the cells still "Not specified" (and on the "WCT party … national rule not checked" placeholders), with value, confidence and source per cell. 46 cells filled (7 low-confidence, marked "(unconfirmed)"). Not applied: 22 `UNKNOWN` cells and 31 low-confidence "no provision found" answers, since no source states the absence. The Nicaragua privacy penalties found in the same pass (`NI,penalties_range` in `gaps-r1.csv`) were applied to the privacy dataset. No full statute text could be opened (search excerpts only).

From 15 Oct 2026, applied values with a source are listed in `jurisdictions/field_sources.csv` (compiled from these files: only values that ended up in the master CSV), and every change to a row is in `jurisdictions/changes.csv`.

## Second batch of jurisdictions (16 Oct 2026)

`new-rows-raw/*.csv`: what the research agents reported (`code,field,value,conf,source`) for 36 jurisdictions. `new-rows-2026-10-16.md` says which became rows, which have no identifiable law (not added) and which still lack a required fact (the term of protection) or were not researched. Rules used: nothing from memory; treaty memberships only at medium or high confidence (the rest stay "not confirmed"); a low-confidence "no provision" answer stays "Not specified"; low-confidence text ends in "(unconfirmed)"; the row's confidence is the weakest of its law name, term and enforcement body. The session's search budget was shared by all agents, so several rows are thin.
