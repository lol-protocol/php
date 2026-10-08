# M-privacy notes (as of 2026-10-08)

## Method and hard limits
- Only WebSearch was used (per SPEC); WebFetch/curl were not used. All lists are third-party search summaries, not the official pages themselves.
- The shared WebSearch budget ran out mid-task (limit of 200 per turn, shared by all agents). About 14 queries of mine succeeded. Planned follow-up queries on Burkina Faso, Ghana, other 108 invitations and the full Global CBPR roster were NOT run.
- Where an answer rests on my background knowledge or arithmetic rather than a retrieved source, the row note or this file says so.
- `conf` is the weakest link in the row: high = all four items supported by 2+ sources, an official list or hard inference; medium = at least one item is a "no" by absence or a single dated source; low = at least one item is `unknown`.
- For EU/EEA states `eu_adequacy` is `no` with note "adequacy n/a" (no decision is needed or exists).

## Item 1 - GDPR (complete, high)
- EU 27: AT BE BG HR CY CZ DK EE FI FR DE GR HU IE IT LV LT LU MT NL PL PT RO SK SI ES SE. EEA: IS LI NO. `EU` = yes. GB, CH = no.
- Cross-check: Commission adequacy page summary ("EU and Norway, Liechtenstein and Iceland") and EPO-adequacy sources; EU membership itself is background fact.

## Item 2 - EU adequacy (complete, high)
Combined list from several summaries (Commission page snippet, Dastra, recordinglaw.com, Hunton Jan 2024 review, Katten):
Andorra, Argentina, Brazil, Canada (commercial only), Faroe Islands, Guernsey, Israel, Isle of Man, Japan (private sector only), Jersey, New Zealand, South Korea, Switzerland, UK, Uruguay, US (only DPF-certified companies), plus the European Patent Organisation (international organisation, not a country).
Rows marked yes in the CSV: AR CA IL JP KR NZ CH GB US UY BR (the rest of the list - AD, FO, GG, IM, JE - is not in codes-privacy.csv).

- **Brazil: formally adopted.** Commission Implementing Decision (EU) 2026/179 of 26 Jan 2026 (C(2026) 373), published OJ L 28.1.2026, in force 28 Jan 2026 (Digital Policy Alert, EUR-Lex entry, White & Case, Kennedys, Baker McKenzie, Mayer Brown all agree). EDPB Opinion 28/2025 of 4 Nov 2025; draft published 5 Sep 2025. Reciprocal Brazilian act: ANPD Resolution CD/ANPD No. 32/2026 (dated 26 Jan, announced 27 Jan). Excludes transfers solely for public security/law enforcement. Review after 4 years. One summary mentioned "10 February 2026" for the mutual decision; this looks like a secondary-source slip, the 26-28 Jan dates have far more support.
- **Other adequacy events 2025-2026:**
  - European Patent Organisation: Implementing Decision (EU) 2025/1382 of 15 Jul 2025 (first international organisation).
  - UK: two 2021 decisions (GDPR + LED) were first extended (to 27 Dec 2025), then renewed on 19 Dec 2025, valid to 27 Dec 2031 (Hunton, Freeths, eucrim, dig.watch).
  - South Korea: Commission's first review concluded around 23 Jul 2026 with continued adequacy (Digital Policy Alert; techtimes 25 Jul 2026). Single-ish source.
  - Jan 2024: review reaffirmed the 11 older decisions (AD, AR, CA, FO, GG, IL, IM, JE, NZ, CH, UY).
  - A tracker "verified 20 Aug 2026" reported no adequacy decision added, suspended or revoked since Brazil (Jan 2026). I found nothing for Sep 2026 but could not search the Commission page itself.
  - Pending, not adopted: Kenya (dialogue launched 7 May 2024; EU-Kenya Digital Dialogue 18 Mar 2026; no draft decision found). No draft found for India, Chile, Georgia or Colombia.
- **US risk (still `yes`):** DPF remains legally in force. Trump v. Slaughter (29 Jun 2026) triggered noyb's 30 Jun letter asking for a managed exit, and an EDPB letter of 31 Jul 2026 asks the Commission to look at it; Latombe appeal C-703/25 P pending. Reports differ on whether noyb has already filed suit. Nothing found showing suspension.
- Doubt: Japan's decision might have been extended beyond the private sector; I did not check it, so the row says private sector only.

## Item 3 - Convention 108 Parties (reconstructed, not an official chart)
The Council of Europe chart for ETS 108 could not be read. Reconstruction:
- CoE statement (Jan 2021) and CNIL (2023): **55 Parties**; CoE release: Morocco = 55th Party (in force 1 Sep 2019). 55 = 47 CoE members (as of 2019, incl. Russia) + 8 non-members: Uruguay (2013), Senegal, Mauritius, Tunisia (51st, in force 1 Nov 2017), Cabo Verde (52nd, 1 Oct 2018), Mexico (53rd, 1 Oct 2018), Argentina (in force 1 Jun 2019), Morocco (1 Sep 2019). So every CoE member, incl. Turkey and Ukraine, is a Party by arithmetic (my background knowledge: Turkey ratified 2016, Ukraine 2010; not directly retrieved).
- Rows `yes`: EU 27, IS LI NO, AL AM AZ BA GE MD ME MK RS CH GB, TR UA, RU, AR MX UY MA TN SN MU (51 in total). Moldova: Party since 2008.
- **Russia:** signed 7 Nov 2001, ratified 15 May 2013 (CoE country profile). Left the CoE 16 Mar 2022. ALRUD, activemind.legal and the EDPB statement say leaving the CoE does not end 108 participation (open to non-members), and the 2023 bill terminating 21 CoE treaties did not list 108. Russia has denounced other CoE treaties (e.g. anti-torture convention, notified 30 Oct 2025). Marked `yes`, medium; I did not see a post-2022 official chart. Russia only signed 108+ (10 Oct 2018).
- **Turkey / Ukraine:** `yes` by inference (see above), medium.
- **Burkina Faso: `unknown`.** Invited to accede 22-23 Mar 2017; CoE report (T-PD 2021) says a new data law was promulgated and ratification steps were pending; DataGuidance says not yet ratified; the Party count stayed 55 in 2021 and 2023. No deposit found, BUT the task brief lists it as a Party. Needs the CoE chart.
- **Ghana: `unknown`.** An undated headline ("Ghana to join Europe's data protection council - Veep") suggests intent to join; no accession record found.
- Gabon: stated intention to accede to 108+ at the Nov 2025 T-PD plenary (not in the list). Observer-status requests in 2025 from Ecuador, Colombia DPA, a Chilean association, the Network of African DPAs - none is a Party. I found no non-member invitation or accession in 2025-2026, but my search could have missed one.
- Israel, Belarus, Kazakhstan, Brazil, Chile, Kenya etc.: `no` (not among the 55; no accession news).
- `EU` itself: `no` (the EU is not a Party; the 1999 amendments allowing the European Communities to accede are, to my knowledge, not in force - not verified).
- Convention 108+ (CETS 223) is NOT in force: 46 signatures, 34 ratifications (Moldova 34th on 15 May 2026; no change since June 2026; Bureau 17 Sep 2026); 38 ratifications are needed. Mauritius ratified 108+ (2020, first African Party). Argentina passed a law (27.699) approving 108+. Uruguay, Tunisia, Argentina signed. I did not build a per-country 108+ list (not requested).

## Item 4 - APEC CBPR / Global CBPR Forum (partial)
- No complete roster was retrievable. From the Forum's 2025-2026 annual report and news listing: Members include Japan and Singapore; Associates: UK (first, 3 Jun 2023), Bermuda, DIFC (Dubai International Financial Centre), Mauritius.
- Full participants used for `yes`: Australia, Canada, Japan, Korea, Mexico, Philippines, Singapore, Chinese Taipei (TW), USA. Only JP and SG were confirmed by a retrieved source; the remaining seven come from the task brief plus my training knowledge of the APEC CBPR participant list (all medium).
- Doubts: (a) Mexico - the privacy authority INAI that handled its participation was dissolved in 2025 (background knowledge, not verified), so its current status is uncertain; (b) the Global CBPR Forum and the APEC CBPR system are now separate; I treated participation in either as `yes`; (c) a 2025-2026 new member would have escaped me - `no` for APEC economies (BN CL CN HK ID MY NZ PE TH VN) is "not in the roster I know".
- GB and MU are associates only (`no`); AE is `no` because only DIFC is an Associate.

## Main URLs relied on (from search results)
- https://commission.europa.eu/law/law-topic/data-protection/international-dimension-data-protection/adequacy-decisions_en
- https://eur-lex.europa.eu/eli/dec_impl/2026/179/oj
- https://digitalpolicyalert.org/event/37366-commission-implementing-decision-on-the-adequate-level-of-protection-of-personal-data-by-brazil-enters-into-force
- https://www.whitecase.com/insight-alert/mutual-adequacy-between-eu-and-brazil-new-era-transatlantic-data-transfers
- https://www.kennedyslaw.com/en/thought-leadership/article/2026/eu-brazil-adequacy-decisions-practical-implications-for-international-data-transfers
- https://www.hunton.com/privacy-and-cybersecurity-law-blog/european-commission-renews-uk-data-adequacy-decisions
- https://www.freeths.co.uk/insights-events/legal-articles/2026/european-commission-renews-uk-adequacy-decisions-until-27-december-2031/
- https://www.globalpolicywatch.com/2025/07/adequacy-decision-for-the-european-patent-organisation/
- https://hunton.com/privacy-and-information-security-law/european-commission-reviews-and-reaffirms-adequacy-decisions-for-11-jurisdictions
- https://www.recordinglaw.com/world-laws/world-data-privacy-laws/eu-adequacy-decisions/ and https://www.dastra.eu/en/blog/the-list-of-countries-deemed-adequate-for-gdpr-transfers-table/59031
- https://digitalpolicyalert.org/event/42065-european-commission-concluded-first-review-of-south-korea-adequacy-decision-finding-continued-adequate-level-of-protection
- https://ppc.land/supreme-court-ftc-ruling-sinks-eu-us-data-deal-noyb-says/ (DPF risk)
- https://www.privacylaws.com/news/eu-commission-and-kenya-close-to-agreeing-a-mutual-adequacy-agreement/ (Kenya)
- https://www.coe.int/web/data-protection/convention108/parties (page found, content not rendered)
- https://www.coe.int/en/web/data-protection/-/welcome-to-morocco-55th-state-party-to-convention-108-
- https://www.coe.int/web/portal/-/cabo-verde-joins-the-data-protection-and-cybercrime-conventions
- https://www.coe.int/en/web/portal/-/mexico-joins-the-data-protection-convention
- https://www.coe.int/en/web/data-protection/-/republic-of-moldova-becomes-the-34th-state-to-ratify-the-convention-108-
- https://rm.coe.int/t-pd-bur-2026-66rapabr-en/48802dc789 and https://rm.coe.int/t-pd-2026-50rapabr-en/48802c2217 (T-PD 2026)
- https://rm.coe.int/t-pd-2021-41rap-en/1680a302b9 (Burkina Faso, T-PD 2021)
- https://coe.int/web/data-protection/russia, https://www.activemind.legal/guides/data-transfers-russia/, https://www.alrud.com/upload/Информационные письма/ALRUD_Newsletter_Cross-border_transfer_of_personal_data_in_Russia.pdf
- https://www.globalcbpr.org/wp-content/uploads/GlobalCBPRForum-Annual-Report-2025-2026.pdf and https://globalcbpr.org/tag/associate

## Biggest doubts
1. Convention 108: no official chart; Burkina Faso and Ghana left `unknown`; Russia/Turkey/Ukraine rest on inference or non-official sources.
2. Global CBPR: seven of nine `yes` rows not source-confirmed this session; no 2026 roster; Mexico's status after INAI's dissolution.
3. A new adequacy decision or 108 accession after Aug 2026 could be missing, as could a US DPF suspension.
