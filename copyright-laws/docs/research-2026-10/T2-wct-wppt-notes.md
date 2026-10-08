# T2 notes: WCT / WPPT contracting parties

Date of research: 2026-10-08. Method: WebSearch only (standard mode), 45 searches used (hard cap 45). WebFetch/curl not used.
Output: `T2-wct-wppt.csv` (124 codes: 92 WCT yes, 10 no, 22 unknown; 86 WPPT yes, 8 no, 30 unknown).
`conf` applies to the non-unknown values in the row. `unknown` means no result established the status; it is not a `no`.

## How the lists were obtained
No complete list of contracting parties could be read in one piece. WebSearch returns summarised excerpts, so the full WIPO tables were only seen as fragments.
- WIPO status tables (official): `https://www.wipo.int/documents/d/treaties/docs-en-wct.pdf` (WCT, status 2025-01-09, 118 parties) and `https://www.wipo.int/documents/d/treaties/docs-en-wppt.pdf` (WPPT, status 2025-03-31, 114 parties). The line-format queries ("... Status on January 9, 2025 Contracting Parties <country names>") returned useful line fragments for most letters of the alphabet. This was the main source for `yes` values.
- WIPO Lex party page `https://www.wipo.int/wipolex/en/treaties/parties/16` (WCT, 119 members incl. Bahamas acc. 2026-07-11, in force 2026-10-11; not in our list) and WIPO notifications (`treaty_wct_NN`, `treaty_wppt_NN`) for individual accessions.
- Dutch treaty database (`verdragenbank.overheid.nl` / `treatydatabase.overheid.nl`, treaty 007931 WPPT, 007936 WCT): gave WPPT party list with declarations (Australia, Belgium, Canada, Chile, China, Denmark, Finland, France, Germany, India, Japan, New Zealand, North Macedonia, Rep. of Korea, Russia, Singapore, Sweden, Switzerland, UK, Vietnam) and some WCT rows (Uganda, Kenya, South Africa).
- EU group: WIPO notification WCT/76 (EU plus 16 member states deposited on 2009-12-14, in force 2010-03-14) and the IRIS/European Audiovisual Observatory note `https://merlin.obs.coe.int/article/5166` (EU and 16 member states ratified both treaties together; "the remaining Member States had already completed ratification at an earlier stage").
- Older snapshots: 2002 WIPO annex (`pcipd_3_9-annex1.doc`), USTR Special 301 annexes (2006, 2009), IIPA 2010 scorecard chart. Used only to support `yes` (parties rarely leave); noted as old.
- Negative evidence: USTR/IIPA Special 301 filings (2021, 2023, 2024, 2026), the 2026 US IP snapshot for Brazil, and adjacency in WIPO table excerpts (e.g. WCT list runs Bahrain to Barbados, Algeria to Argentina).
- Totals from USTR Special 301: 112 WPPT / 116 WCT parties (March 2024); 114 WPPT / 118 WCT (March 2026). Wikipedia: WPPT 115 (July 2026), WCT 119 (Aug 2026).

## Partial or weak areas
- The WIPO PDF tables were never seen in full. Every WIPO-table `yes` rests on an excerpt line. `no` values from adjacency (AO, BD for WCT; BD, KH for WPPT) rest on the excerpt being contiguous, hence only medium or low.
- EU members: WCT is directly sourced (WIPO table and/or notification 76) for nearly all. For WPPT the Dec-2009 batch (AT DK EE FI FR DE GR IE IT LU NL PT ES SE GB MT) rests on the IRIS statement that the two treaties were ratified together (PT, NL, IT, BE, PL, SK, SI, UA etc. also seen in the WPPT table or Dutch db). LV, LT, RO, CZ (WCT), SI, SK (WCT) and similar rely on the IRIS statement and/or the 2002 annex. Confidence is medium for these.
- GB: kept `yes`; post-Brexit status not checked separately (WIPO notification WPPT/114 is a UK declaration).
- Hong Kong and Macao: WCT extension known from WIPO table footnotes (HK from 2008-10-01, Macao from 2013-11-06). WPPT extension not seen, so `unknown`.
- Taiwan: no result; left `unknown`.

## Disagreements and oddities
- Azerbaijan: USTR 2006 gives 2006-04-11 for both treaties; a Copyright Office circular table showed 2009-11-25. Party status agrees (yes/yes); the date does not.
- Cyprus: summaries gave different dates for the two treaties (WCT 2003, WPPT 2005); both treaties are listed, which is all the CSV records.
- Indonesia: WIPO notice says ratification deposited 1997-06-05; WIPO table shows 2002-03-06 (date of effect). Both consistent with party status.
- Jordan: WCT in force 2004-04-27 (WIPO table); WPPT in force 2004-05-24 (Dutch db); agip says both in force in 2004.
- Uruguay: one summary read the USTR annex "Aug 2008" as WCT; the WIPO notice summary gives a WCT deposit on 2009-03-05 (in force 2009-06-05) and USTR gives WPPT Aug 2008. Both `yes`.
- Paraguay: a Copyright Office circular table showed WPPT 2010-03-14 (the EU-batch date), probably a layout error; WPPT left `unknown`.
- Ghana: IIPA 2010 chart showed 2006-11-18 without saying which treaty; WIPO notice shows WCT ratification effective 2006-11-18 and WIPO WPPT table shows 2013-02-16.
- Nigeria: USTR 2023 says Nigeria ratified in 2017; WIPO tables show 2018-01-04 for both. Consistent with deposit late 2017 and effect early 2018.
- Cameroon: Dutch db labels the WPPT entry "ratification (A)"; treated as accession effective 2025-04-09 as for the WCT.
- Tunisia: an early query summary found only WPPT; the later USTR 2023 text says Tunisia acceded to both WCT and WPPT, so both are `yes`.

## `no` values and why they are low-confidence
- BR (medium): 2026 US government IP snapshot lists WCT and WPPT as "No".
- BD, AO (medium), KH (WPPT only, low): adjacency in WIPO table excerpts.
- KE (medium): IIPA 2024 says Kenya has yet to ratify; Dutch table shows signature only.
- BO (WCT only): WIPO Lex shows signature date only.
- ZM (WCT only): 2026 statement to WIPO Assemblies says cabinet approved joining WCT, not completed.
- EG, LB (low): IIPA January 2021 list of markets not yet acceded; may have changed since 2021.
- NO, IS (low): MPA-APAC 2018 and WIPO SCCR/33/6 (2016) list them as non-parties; not seen in 2025 WIPO table fragments. Possible later accession not excluded. Treat as the least certain `no` values.

## Not established (`unknown` for both treaties)
CI, CU, ET, IL, IQ, IR, KW, LA, LK, MM, MU, MW, NP, PK, RW, SA, TW, TZ, VE, ZA, ZW.
Notes: IL, VE, ZA appear only with signature dates. SA was not a party as of IIPA 2021 and USTR 2023 described planned accession (may have joined since). KW was not seen in WIPO WCT fragments but those were incomplete.
Partly unknown (one treaty established, the other `unknown`): WPPT unknown for AM, AO, BO, DZ, HK, MO, PY, TH, ZM; WCT unknown for KH.

## Suggested follow-up if a full-text source becomes available
Open the two WIPO status PDFs directly (or WIPO Lex `ShowResults?search_what=C&treaty_id=16` for WCT and `treaty_id=20` for WPPT) and diff against the CSV; priority checks: NO, IS, SA, EG, LB, KW, TH (WPPT), AM (WPPT), HK/MO (WPPT), PY (WPPT), and the IRIS-based EU WPPT values.
