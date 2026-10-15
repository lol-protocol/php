# P1-eu-eea research notes

Batch: 12 countries (BG, HR, CY, EE, LV, LT, MT, SK, SI, NO, IS, LI). 11 rows written, 1 skipped (BG).

Method and limits
- Two or more differently worded WebSearch queries per country (law/dates, then authority/penalties).
- The WebSearch budget ran out partway through the follow-up round. The last follow-up queries (regulator-domain pages for BG, HR, CY, EE, LV, and a second source for the Bulgarian adoption day) returned nothing. Regulator home pages were therefore not located for several countries, and `website_url` is often a reputable legal-information page or the official gazette.
- Every `website_url` appeared in the search results. None was constructed.
- No national statute text was read directly. All penalties rest on secondary sources saying the national act points to GDPR Art. 83 (up to EUR 20M or 4% of worldwide turnover). I did not read any act's own fine provisions. Where a source gave a different national figure it is noted below.
- `retention_period` is "No longer than necessary" for every row. None of these acts sets a fixed period; the rule comes from the GDPR storage-limitation principle.
- Dates are the date the law bears: parliamentary adoption for HR, EE, LV, LT, SK, SI and LI; the Gazette or assent date for CY, MT, NO and IS. Each date is flagged below where that choice matters.

---

## HR - Croatia - confidence: high (dates), medium (URL specificity)
- Act on the Implementation of the General Data Protection Regulation (Zakon o provedbi Opće uredbe o zaštiti podataka), NN 42/2018. Short form AIGDPR.
- Enacted 2018-04-27 (Parliament), in force 2018-05-25. Linklaters, IAPP and the search summary agree. One source gave publication as 3 May 2018, which I could not confirm. It is not used in the CSV.
- Authority: Croatian Personal Data Protection Agency (AZOP).
- Relied on:
  - https://iapp.org/news/a/croatian-gdpr-implementation-law-main-features-and-unanswered-questions (used as `website_url`)
  - https://www.linklaters.com/en/insights/data-protected/data-protected---croatia
  - https://www.whitecase.com/publications/article/gdpr-guide-national-implementation-croatia
  - https://www.dlapiperdataprotection.com/?c=HR&t=law
- Doubt: no regulator or Narodne novine page surfaced. The act's own penalty provisions were not seen. AZOP fines of EUR 2.2M to 5.47M were reported (Wolf Theiss, DataGuidance).

## CY - Cyprus - confidence: medium
- Law 125(I)/2018, "providing for the protection of natural persons with regard to the processing of personal data and for the free movement of such data". There is no official English short title in the results. The long title is used as given by the firms.
- Enactment and effective date are both 2018-07-31. DLA Piper, Harris Kyriakides (published in the Government Gazette on 31 July 2018) and Linklaters agree on 31 July 2018.
- Doubt: the House of Representatives may have voted earlier than 31 July. I could not confirm a vote date, so the Gazette publication date is used as the date of the law. One search summary called 31 July the "vote" date. I treat that as unreliable.
- It repealed Law 138(I)/2001.
- Authority: Office of the Commissioner for Personal Data Protection (dataprotection.gov.cy was mentioned but never returned as a result link).
- Relied on:
  - https://www.dlapiperdataprotection.com/?c=CY&t=law (used as `website_url`)
  - https://www.harriskyriakides.law/insights/news/law-125-i-2018-officially-published-in-the-cyprus-government-gazette
  - https://www.linklaters.com/en/insights/data-protected/data-protected---cyprus
  - https://www.cut.ac.cy/digitalAssets/472/472580_1law125_i_2018.pdf (text of the law; language not confirmed, so not used)
- Penalties: no source gave the national statutory cap. GDPR levels assumed. Actual fines are small (largest corporate fine about EUR 70k).

## EE - Estonia - confidence: high (dates), medium (penalties)
- Personal Data Protection Act (Isikuandmete kaitse seadus). The Riigi Teataja consolidated text lists: adopted 12.12.2018, RT I, 04.01.2019, 11, in force 15.01.2019. DLA Piper and Linklaters agree on both dates.
- Outlier ignored: Triniti gives 29 January 2019 for entry into force, which contradicts the official text.
- Companion Implementation Act (adopted 2019-02-20, in force 2019-03-15 per DLA Piper) was not chosen as the main law.
- Authority: Data Protection Inspectorate (Andmekaitse Inspektsioon, AKI).
- Penalties: Estonia has no purely administrative fines. Sanctions go through misdemeanour proceedings. Sources (Sorainen, other law-firm pieces, and a CaptainCompliance article) say a Penal Code amendment in force 1 Nov 2023 removed the old cap, so fines can now reach EUR 20M or 4% of turnover. I could not confirm this against the Penal Code. AKI reportedly fined Allium UPI EUR 3M in Sept 2025.
- Relied on:
  - https://www.riigiteataja.ee/akt/112072025014.pdf (used as `website_url`)
  - https://www.dlapiperdataprotection.com/index.html?c=EE&t=law
  - https://www.linklaters.com/en/insights/data-protected/data-protected---estonia
  - https://www.privacylaws.com/reports-gateway/articles/int175/int175estonia/
  - https://www.sorainen.com/?p=61527
- Doubt: the `website_url` is a Riigi Teataja PDF of the Estonian-language text, a version-specific consolidated file. That is why `language` is Estonian. The English consolidated page was not located (budget).

## LV - Latvia - confidence: medium-high
- Personal Data Processing Law (Fizisko personu datu apstrādes likums). The English acronym varies (PDL / DPL). PDL is used.
- Adopted 2018-06-21, in force 2018-07-05. The in-force date is given by DLA Piper and by another summary. The adoption date was seen in fewer sources, but "of 21 June 2018" is part of the law's usual citation.
- It replaced the earlier Personal Data Protection Law.
- Authority: Data State Inspectorate (Datu valsts inspekcija, DVI), a direct administration body under the Justice Minister that acts as the independent supervisory authority.
- Notes: a May 2021 amendment made officials personally liable (fines up to 200 fine units, about EUR 1,000), per lvportals.lv.
- Relied on:
  - https://www.dlapiperdataprotection.com/index.html?c=LV&t=law (used as `website_url`)
  - https://linklaters.com/insights/data-protected/data-protected---latvia
  - https://caseguard.com/articles/data-protection-and-personal-privacy-law-in-latvia
  - https://www.coe.int/web/data-protection/latvia
  - https://lvportals.lv/dienaskartiba/327848-likuma-precize-amatpersonu-atbildibu-par-parkapumiem-datu-aizsardzibas-joma-2021
- Doubt: likumi.lv and dvi.gov.lv pages did not come back as links, so no official page is cited.

## LT - Lithuania - confidence: high
- Law on Legal Protection of Personal Data, No. XIII-1426. Dated 2018-06-30, published in TAR 11 July 2018, in force 2018-07-16 (IAPP; the VIKO non-official translation; the e-Seimas consolidated text).
- It recast the 1996 law I-1374. Companion Law XIII-1435 covers criminal-justice and national-security processing.
- Authority: State Data Protection Inspectorate (VDAI). The Inspector of Journalist Ethics supervises journalistic processing.
- Penalties: GDPR levels assumed. Largest fine so far is EUR 2,385,276 (Vinted, July 2024).
- Relied on:
  - https://vdai.lrv.lt/en/legislation/ (used as `website_url`)
  - https://iapp.org/news/a/lithuania-adopts-new-law-on-the-legal-protection-of-personal-data
  - https://en.viko.lt/wp-content/uploads/sites/9/2022/09/Republic-of-Lithuania-Law-on-legal-protection-of-personal-data-2018-Non-Official-Translation1.pdf
  - https://e-seimas.lrs.lt/rs/legalact/TAD/3e1ba58238c711edbf47f0036855e731/
  - https://www.linklaters.com/Insights/Data-Protected/Data-Protected---Lithuania
  - https://vdai.lrv.lt/en/news/activities-of-the-state-data-protection-inspectorate-in-2024/
- Doubt: the VDAI legislation page was not read, only returned in results. The English translation is non-official.

## MT - Malta - confidence: high (dates), medium (fine caps)
- Data Protection Act, Chapter 586 of the Laws of Malta, Act XX of 2018. Dated 28 May 2018, commenced 28 May 2018 (several sources). Amended by Act XII of 2021. It repealed Cap. 440. The Law Enforcement Directive is transposed by S.L. 586.08.
- Authority: Information and Data Protection Commissioner (IDPC).
- Penalties: GDPR levels. Sources conflict on the cap for public bodies (EUR 25,000 plus a daily EUR 25, versus tiers of 25k/50k). The CSV gives only the GDPR top tier.
- Relied on:
  - https://idpc.org.mt/our-office/legislation/ (used as `website_url`)
  - https://www.dlapiperdataprotection.com/index.html?c=MT&t=law
  - https://mondaq.com/data-protection/725458/beyond-gdpr-new-data-protection-act-and-subsidiary-laws
  - https://www.privacylaws.com/reports-gateway/articles/int162/int162malta/
  - https://www.linklaters.com/insights/data-protected/data-protected---malta
  - https://legislationline.org/taxonomy/term/27000
- Doubt: one AI-assisted aggregator (theartofservice.com) mentions a 2020 amendment that I could not corroborate. Ignored.

## SK - Slovakia - confidence: medium-high
- Act No. 18/2018 Coll. on Personal Data Protection and on Amending and Supplementing Certain Acts (zákon č. 18/2018 Z. z.). Approved 2017-11-29, promulgated 2018-01-30, in force 2018-05-25.
- Dates: the Slov-Lex metadata quoted in one search summary and offlist.me give 29.11.2017. Linklaters gives the 30 Jan 2018 promulgation. Linklaters and UNIZA give 25 May 2018. A Košice paper dates the act to 2016, which I consider wrong.
- It replaced Act 122/2013 and transposes the Law Enforcement Directive.
- Authority: Office for Personal Data Protection of the Slovak Republic (ÚOOÚ).
- Penalties: s.104 caps are EUR 10M or 2% and EUR 20M or 4% (lewik.org mirror of the statute and a Slovak law-firm summary). The CSV gives the top tier.
- Relied on:
  - https://dataprotection.gov.sk/files/personal-data-sample/2019_10_03_act_18_2018_on_personal_data_protection_and_amending_and_supplementing_certain_acts.pdf (used as `website_url`, the regulator's English version; it is stated to be not legally binding)
  - https://static.slov-lex.sk/static/SK/ZZ/2018/18/20180525.html
  - https://linklaters.com/insights/data-protected/data-protected---slovakia
  - https://www.offlist.me/what-is-slovakia-act-18-2018
  - https://www.lewik.org/term/23926/pokuty-104-zakon-o-ochrane-osobnych-udajov-18-2018/
- Doubt: the 29 Nov 2017 adoption date rests on the Slov-Lex-derived quote and offlist.me. The PDF linked in `website_url` is dated 2019 and may show a later consolidated text.

## SI - Slovenia - confidence: high
- Personal Data Protection Act (Zakon o varstvu osebnih podatkov, ZVOP-2). National Assembly adopted it on 15 December 2022 (gov.si, and the President's proclamation signed 23 Dec 2022). Published in Official Gazette RS 163/2022 on 27 Dec 2022. In force 2023-01-26. It replaced ZVOP-1 (2004).
- Authority: Information Commissioner (Informacijski pooblaščenec, IP-RS). Under the Minor Offences Act it can impose fines in a fast-track procedure. State bodies cannot be liable for minor offences (CMS Enforcement Tracker).
- Relied on:
  - https://www.uradni-list.si/glasilo-uradni-list-rs/vsebina/2022-01-4187/zakon-o-varstvu-osebnih-podatkov-zvop-2 (used as `website_url`, Slovenian)
  - https://www.gov.si/novice/2022-12-15-drzavni-zbor-sprejel-zakon-o-varstvu-osebnih-podatkov/
  - https://www.uradni-list.si/novice/pogled/zvop-2-je-zacel-veljati-26--januarja-2023---kaksne-pomembne-novosti-prinasa
  - https://www.k-p.si/en/zvop-2-eng/
  - https://ey.com/en_si/ey-slovenia-law-news/law-news-january-2023
  - https://ip-rs.si/en/legislation/personal-data-protection-act/
  - https://www.dlapiperdataprotection.com/index.html?c=SI&t=law
- Doubt: `enactment_date` is the parliamentary adoption date. The promulgation date would be 2022-12-23 and the gazette date 2022-12-27. The regulator's English-language page exists (ip-rs.si) but I did not confirm what language it carries, so the gazette is linked. One law-firm site says the 2026 amendment ZP-1L removed some provisions from application in Feb 2026; unverified and not used.

## NO - Norway - confidence: high
- Act of 15 June 2018 no. 38 relating to the processing of personal data (Personal Data Act, personopplysningsloven; LOV-2018-06-15-38). It incorporates the GDPR via the EEA Agreement (EEA Joint Committee Decision 154/2018 of 6 July 2018).
- In force 2018-07-20. The cabinet tied commencement to the day the EEA decision took effect, which was delayed until Liechtenstein lifted its constitutional reservation on 19 July. Sources: Linklaters, L&E Global, DLA Piper, DataGuidance.
- It replaced the 2000 Act. Consent age for information society services is 13.
- Authority: Norwegian Data Protection Authority (Datatilsynet).
- Penalties: GDPR level. Examples reported: Grindr NOK 65M (2021), Elkjøp NOK 20M (2026).
- Relied on:
  - https://www.linklaters.com/en/insights/data-protected/data-protected---norway (used as `website_url`)
  - https://leglobal.law/2018/08/24/norway-enforcement-of-new-law-on-personal-data/
  - https://www.dlapiperdataprotection.com/guide.pdf?c=NO
  - https://www.dataguidance.com/notes/norway-data-protection-overview
- Doubt: no Lovdata or Datatilsynet page surfaced. The Linklaters English translation of the Act is unofficial.

## IS - Iceland - confidence: high (effective date), medium (enactment date, penalties)
- Act No. 90/2018 on Data Protection and the Processing of Personal Data (lög um persónuvernd og vinnslu persónuupplýsinga). In force 2018-07-15 (Persónuvernd English page, Lexmundi, Linklaters). It replaced Act 77/2000. The GDPR applies through Annex XI of the EEA Agreement.
- Enactment 2018-06-27 (Cambridge CIPIL and the Legislationline copy). Clym gives 13 July 2018, which conflicts with the statute's own date and is rejected. The Althingi passed the bill earlier in June, but I did not confirm the day. 27 June is the date the act bears.
- Authority: Persónuvernd (Icelandic Data Protection Authority).
- Penalties: a cookie-consent vendor page (ConsentStack) claims a 2% cap and ISK ranges. Other sites give the GDPR EUR 20M or 4%. I could not verify the Act's own provisions and used the GDPR figure. This column is low confidence.
- Relied on:
  - https://personuvernd.is/information-in-english/greinar/nr/437 (used as `website_url`)
  - https://www.cipil.law.cam.ac.uk/node/294862
  - https://legislationline.org/taxonomy/term/24861
  - https://www.althingi.is/lagas/nuna/2018090.html
  - https://www.lexmundi.com/guides/data-privacy-guide/jurisdictions/europe/iceland/
  - https://www.linklaters.com/en/insights/data-protected/data-protected---iceland
  - https://www.wipo.int/wipolex/zh/legislation/details/18498
- Doubt: a blog post claims fines were revoked by the Supreme Court in March 2026. Unverified; irrelevant to the law's dates.

## LI - Liechtenstein - confidence: high (dates), low (penalties)
- Data Protection Act (Datenschutzgesetz, DSG) of 4 October 2018, LGBl. 2018 Nr. 272 (published 7 Dec 2018 per Legislationline). It is a total revision of the previous law. In force 2019-01-01 (White & Case, Linklaters, datenrecht.ch, LLB annual report).
- The GDPR itself applies in Liechtenstein from 2018-07-20, when the EEA Joint Committee decision took effect. The national act is the DSG.
- Later amendments: LGBl. 2020 Nr. 389, 2025 Nr. 87 and 2026 Nr. 9. Not used.
- Authority: Data Protection Authority (Datenschutzstelle, DSS). Linklaters gives its website as datenschutzstelle.li.
- Penalties: sources conflict. One site gives EUR 20M or 4%. Another gives CHF 11M or 2% and CHF 22M or 4%. A third repeats the CHF 22M figure. The CHF numbers look like a conversion error, so the CSV uses the GDPR euro figure. This column is low confidence.
- Relied on:
  - https://www.linklaters.com/insights/data-protected/data-protected---liechtenstein (used as `website_url`)
  - https://www.whitecase.com/insight-our-thinking/gdpr-guide-national-implementation-liechtenstein
  - https://datenrecht.ch/en/liechtenstein-neues-datenschutzgesetz-per-1-januar-2019-in-kraft/
  - https://d10.legislationline.org/taxonomy/term/25026
  - https://archive.llb.li/2019/annual-report/operations/finance-and-risk-management/protection-of-data.html
  - https://gpg-pdf.chambers.com/Doing-Business-In-2026/590/
- Doubt: the gesetze.li links in the results were the Ordinance (DSV) and amendments, not the original DSG text.

---

## Skipped

### BG - Bulgaria
- Reason: only one source gave the exact adoption day. The spec allows an exact day only if two sources give it.
- What is known:
  - The main law is the Personal Data Protection Act (Закон за защита на личните данни), promulgated in State Gazette No. 1 of 4 January 2002, in force 2002-01-01 (several sources, including trudipravo.bg and kik-info.com).
  - One source says it was "enacted on 21 December 2001". I have not confirmed this with a second source.
  - The effective date precedes the promulgation date by three days, which is unusual. The Bulgarian-language summary flagged this.
  - The act was heavily amended in SG No. 17 of 26 February 2019 (in force 2 March 2019 per PrivacyLaws, 1 March per Linklaters) to align it with the GDPR and transpose Directive 2016/680. Later amendment history runs to SG 70/2024. The CPDP English text may lag the Bulgarian original.
  - Authority: Commission for Personal Data Protection (CPDP, Комисия за защита на личните данни). Fines are issued under the Administrative Violations and Penalties Act.
- Candidate row, ready to add if the 2001-12-21 date is confirmed:
  - `BG,Bulgaria,europe,Personal Data Protection Act (PDPA),Commission for Personal Data Protection,2001-12-21,2002-01-01,Individuals in Bulgaria,Public & private organizations,...,Commission for Personal Data Protection (CPDP),Up to €20M or 4% revenue,...,https://www.dlapiperdataprotection.com/?t=law&c=BG,English,...`
- Relied on:
  - https://www.dlapiperdataprotection.com/?t=law&c=BG
  - https://www.privacylaws.com/news/bulgaria-s-new-data-protection-law-enters-into-force/
  - https://www.linklaters.com/en/insights/data-protected/data-protected---bulgaria
  - https://cms.law/en/bgr/legal-updates/Bulgaria-implements-GDPR-into-Personal-Data-Protection-Act
  - https://www.aip-bg.org/en/privacy/Legislation/106708/
  - https://trudipravo.bg/znanie-za-vas/zakon-za-zashtita-na-lichnite-danni/
  - https://kik-info.com/normativna-baza/zakoni/zzld/
- If included, the decision for the parent is whether the "main law" is the 2002 act as amended (recommended, per the spec's "not its amendments" rule) or the 2019 amendment.

No other country was skipped.
