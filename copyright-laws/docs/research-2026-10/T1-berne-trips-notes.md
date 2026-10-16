# T1 notes: Berne Convention party / WTO member (TRIPS), as of 2026-10-08

Scope: 109 codes in codes-copyright.csv (all except EU, US, GB, DE, FR, ES, IT, JP, CN, IN, BR, CA, AU, MX, RU).
Method: WebSearch only (standard mode), 42 calls used (cap 45). Output: T1-berne-trips.csv (one row per code; `conf` is the
LOWER of the two items' confidences; `source` lists up to two URLs, Berne first then WTO; `note` has per-item remarks).

## How the lists were obtained
- Neither a complete WIPO Berne table nor a complete WTO list could be retrieved: the search tool returns summaries, and the
  WIPO table excerpts were cut off alphabetically (the first query only reached "Estonia"). So NO complete list was obtained for
  either item. Instead I ran country-batch queries (10-16 country names per query, alphabetical) restricted to wipo.int (Berne)
  and wto.org + en.wikipedia.org (WTO), plus targeted queries for the doubtful countries. Every in-scope country was covered.
- Berne: summaries quote the WIPO "Berne Convention ... Status on ..." table (versions dated 2019-04-15, 2025-08-22, and, per
  the summaries, 2026-08-04), WIPO Lex notifications (TREATY/BERNE/nnn) and WIPO Lex "parties" pages.
  Main URLs: https://www.wipo.int/wipolex/en/treaties/parties/15 , https://www.wipo.int/edocs/pubdocs/en/wipo_pub_423.pdf ,
  https://www.wipo.int/documents/d/treaties/docs-en-berne.pdf , notifications https://www.wipo.int/wipolex/en/treaties/notifications/details/treaty_berne_<n>.
  WIPO Lex lists 182 members (latest accession seen: Maldives, in force 2025-11-22; no 2026 accession found).
- WTO: summaries quote the WTO member list (members_brief_e.doc, 2013), the 1995 press release (pr028_e.htm), the 1999
  ministerial page (21accs_e.htm), WTO country pages and the Wikipedia "Member states of the WTO" table. Membership count
  found: 166 (Comoros 2024-08-21, Timor-Leste 2024-08-30). No 167th member found as of Oct 2026.
  Main URLs: https://www.wto.org/english/thewto_e/acc_e/members_brief_e.doc , https://www.wto.org/english/news_e/pres95_e/pr028_e.htm ,
  https://www.wto.org/english/thewto_e/minist_e/min99_e/english/about_e/21accs_e.htm , https://en.wikipedia.org/wiki/Member_states_of_the_World_Trade_Organization .

## Results in one paragraph
- Berne yes: 103 codes. Berne no: AO, ET, IQ, IR, MM, TW. (HK and MO are coded yes but see below.)
- WTO yes: 99 codes. WTO no (observers, not members): AZ, BA, BY, DZ, ET, IQ, IR, LB, RS, UZ.
- Both no: ET, IQ, IR. Berne no / WTO yes: AO, MM, TW.

## Special cases and caveats
- Hong Kong (HK) and Macao (MO): coded berne=yes, but they are NOT separate Berne contracting parties; Berne applies through
  China's accession, extended by WIPO notifications 186 (HKSAR from 1997-07-01) and 211 (Macao SAR from 1999-12-20). Both are
  separate WTO members ("Hong Kong, China", "Macao, China", from 1995-01-01). Re-code to `no` if you need strict "contracting party".
- Taiwan (TW): WTO member as "Chinese Taipei" (separate customs territory, 2002-01-01). Berne = no (medium): AIT/TIPO sources say
  Taiwan is not in Berne (cannot join WIPO treaties as a non-UN state); protection of foreign works is via TRIPS/bilateral
  reciprocity. Not seen in any WIPO list.
- Berne "no" rows rest on absence plus secondary statements, not on a positive "not a party" statement from WIPO:
  - AO (medium): absent from Feb 2026 WIPO Berne Union Assembly list (Andorra, then Antigua and Barbuda) and from an Oct 2008
    list of Berne parties (Andorra, then Argentina); older WIPO table shows Angola in the Paris Union only.
  - IQ (medium): WIPO Lex Iraq profile shows Paris/PCT/Marrakesh etc. but no Berne in the entries seen (listing partial);
    older WIPO table: Paris only; Wikimedia Commons guide (US Copyright Office list) says non-participant; no 2025-26 accession found.
  - ET (medium): WIPO Lex Ethiopia profile shows Paris (in force 2025-08-15), Marrakesh, Nairobi, WIPO Convention but no Berne;
    undated Ethiopian law-firm brief and Mondaq African overview say not a member.
  - IR (medium): Al-Monitor 2016 (Iran among ~25 states outside Berne) and a 2026 Iranian academic study still treating accession
    as a future step.
  - MM (medium): Tilleke 2019 and 2023 articles (also on Mondaq/Conventus): Myanmar not yet a Berne contracting party. A Tilleke
    page with a Sept 2026 sidebar repeats it, but the main text is older.
- WTO "no" rows are WTO observers/accession applicants: Wikipedia observer list (snapshot c. 2024-25: Algeria, Azerbaijan, Belarus,
  Bosnia and Herzegovina, Ethiopia, Iran, Iraq, Lebanon, Serbia, Uzbekistan among others) plus the WTO accession status table
  (https://www.wto.org/english/thewto_e/acc_e/status_e.htm, undated, entries to c. 2022). Recent status: Uzbekistan (13th Working
  Party, UzDaily 2026-07-31, target end-2026, not complete), Ethiopia (7th Working Party 2026-04-22/23), Azerbaijan (workshop
  2026-02), Serbia (Mar 2026 paper still "observer"), Bosnia (latest item Nov 2024 -> BA kept at medium). MC14 (Yaounde, Mar 2026)
  produced no accession found. No report of any of these becoming member up to the last search (Oct 2026). Re-check UZ and ET first.

## Disagreements / weak points
- Dates quoted by the search summaries are inconsistent between queries (e.g. Romania, Senegal, Mongolia, Kazakhstan, Cameroon,
  El Salvador had two different "Berne dates" in different summaries, probably mixing WIPO-membership or Act columns with Berne
  columns). I therefore trust only the yes/no status (consistent across all summaries), and the notes carry dates only as
  indicative. Do not use the dates in the CSV for anything precise.
- Bolivia WTO date 1995-09-12 (WTO list) vs 13 Sep (1995 press release); Zimbabwe 1995-03-05 vs 03-03; Rwanda Berne in-force
  1984-03-01 vs 1984-02-03; Kazakhstan Berne 1999-01-12 (accession) vs 1999-04-12 (in force). No effect on yes/no.
- Berne rows with `medium`: SI, SK, SV (only the 2019 WIPO table seen; also SV listed as party in a 2008 list), plus the Berne-no rows above.
  Czech Republic (CZ) is high on a WIPO declaration that Berne continues to apply from 1993-01-01 (succession), the original
  accession being Czechoslovakia's. Latvia first joined 1937 (Rome Act); the table date is 1995.
- Several Berne yes rows come from older WIPO notifications/tables (e.g. Cameroon, Cyprus, Chile, Kenya, Kazakhstan, Mongolia,
  Senegal) later corroborated by a second WIPO source (2008 comparison table or the 2026 listing); marked high only when two
  sources agreed or a current official listing was quoted.
- The "2026-08-04" WIPO status table could only be seen through summaries, never directly; if exact currency matters, re-open
  https://www.wipo.int/wipolex/en/treaties/parties/15 and https://www.wto.org/english/thewto_e/whatis_e/tif_e/org6_e.htm.

## Partial lists
- Neither the WIPO Berne table nor the WTO member list could be read in full. The WTO observer list is from a Wikipedia summary
  (23 observers); only the 10 in-scope observers matter here, and each is also in the WTO accession-applicant table.
