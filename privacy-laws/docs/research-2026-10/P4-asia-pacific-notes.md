# P4-asia-pacific research notes

Method: WebSearch only (per spec). The shared per-turn WebSearch budget (200 calls across all agents) ran out partway
through the batch, so research stopped after Taiwan, Sri Lanka, Nepal and the first Mongolia query. Mongolia was left
without a confirmed enactment day, and the six countries after it were never searched. Nothing was filled in from memory.

Rows written: 3 (TW, LK, NP). Skipped: 7 (see bottom).

## Taiwan (TW) - confidence: medium-high
Law: Personal Data Protection Act (PDPA). Row uses the 2010 rewrite/rename of the 1995 Computer-Processed Personal Data
Protection Act (promulgated 1995-08-11), because the "Personal Data Protection Act" title and the current regime date from
that rewrite.
- enactment_date 2010-05-26: promulgation of the renamed Act (Legislative Yuan passed it 2010-04-27). Two sources agree: a
  search summary of law-firm/commentary sources and the MOJ English page titled "Personal Information Protection Act"
  (mojlaw.moj.gov.tw/NewsContentE.aspx?id=29, date 2010.05.26).
- effective_date 2012-10-01: Executive Yuan set date (Arts. 6 and 54 initially excluded; sensitive-data and indirect-collection
  notice provisions only came into force later). Agreed by two sources (commentary summary; Japan PPC "offshore report Taiwan").
- Later amendments (not the main law): 2015-12-30 (eff. 2016-03-15), 2023-05-31 promulgated, 2025-11-11 promulgated (passed
  2025-10-17). The 2025 amendment creates the Personal Data Protection Commission (PDPC) and raises fines (e.g. NT$20k-2M
  immediately, NT$150k-15M for uncorrected/material breaches). Its effective date is still to be set by the Executive Yuan.
  Latest source found (July 2026 law-firm update, Chambers 2026 guide, DLA Piper March 2026) shows no start date and the PDPC
  still only a Preparatory Office. One tracker suggested 2026-12-31 as expected; not confirmed, so not used.
- Enforcement today: sectoral central competent authorities (the PDPC is not formally established), hence the wording in the
  jurisdiction/enforcement_authority fields. Re-check before release: if the 2025 amendment has taken effect, update
  enforcement_authority to the PDPC and penalties_range to the new figures.
- penalties_range: criminal up to 5 years and NT$1M (Hunton, ConsentStack, MOJ text snippet agree). Administrative fines of
  NT$20k-500k per violation come from a DLA Piper/Hunton-type summary (older); treat as medium confidence.
- exemptions (personal/household activities, statutory duties) and retention "Not specified": not directly confirmed by the
  searches; exemptions reflect PDPA Art. 51 as generally described. Low-medium confidence on those two columns.
URLs relied on:
- https://law.moj.gov.tw/Eng/LawClass/LawAll.aspx?PCode=I0050021 (row URL; MOJ Laws & Regulations Database, English)
- https://mojlaw.moj.gov.tw/NewsContentE.aspx?id=29
- https://www.ppc.go.jp/enforcement/infoprovision/laws/offshore_report_taiwan
- https://www.bakermckenzie.com/en/insight/publications/alerts/2025/10/taiwan-amendment-to-personal-data-protection-act
- https://www.klgates.com/New-Developments-in-the-Taiwan-Personal-Data-Protection-Act-1-13-2026
- https://www.dlapiperdataprotection.com/?t=law&c=TW
- https://www.chambers.com (Chambers Data Protection & Privacy 2026 - Taiwan): https://practiceguides.chambers.com/practice-guides/data-protection-privacy-2026/taiwan
- https://www.hunton.com/privacy-and-cybersecurity-law-blog/taiwan-amends-personal-data-protection-law

## Sri Lanka (LK) - confidence: medium-high on dates, medium on content columns
Law: Personal Data Protection Act, No. 9 of 2022 (PDPA).
- enactment_date 2022-03-19: Speaker's certificate / passage date given by the DPA background page, DLA Piper and
  DataGuidance. One firm gives 2022-03-18; the majority date is used.
- effective_date 2027-01-01 (FUTURE fixed date): Extraordinary Gazette No. 2498/16 (published 2026-07-22 per DPA; PDF signing
  block reads 13 July 2026) brings Part I (processing) and Part III (controllers/processors), plus sections 2 and 3, into
  operation on 1 January 2027. Agreed by FT.lk, Daily Mirror, concentric.ai, Sunday Times, the gazette PDF and a legal analysis.
  This is the date the main obligations begin, as the spec asks for phased laws.
- Earlier phased steps: Part V (establishes the DPA) in force 2023-07-17 (gazette order issued 2023-07-21; sources differ on
  the day); Parts VI, VIII, IX, X in force 2023-12-01. The planned 2025-03-18 start for Parts I, II, III, VII was revoked by
  Gazette 2427/34 (2025-03-14). The Amendment Act No. 22 of 2025 (certified 2025-10-30) lets the Minister fix remaining
  commencement dates by gazette order.
- NOT yet commenced as of the latest sources: Part II (data subject rights) and Part VII (penalties). So the DPA cannot impose
  administrative penalties from 2027-01-01 unless a further order is made. The ministry has said enforcement should begin
  within a year of Jan 2027. Part IV (solicited messages) has its own 24-48 month clock.
- penalties_range: up to LKR 10M per non-compliance, doubling for repeats (WilmerHale 2022; Section 38 analysis). One analysis
  ties the cap specifically to non-compliance with a DPA directive under s.35; wording in the row is the broad version.
- Stale source: the DPA's own Background page still shows the old 2025-03-18 date; it is used as the row URL as the
  regulator page, but the dates in the row come from the newer gazette coverage.
- exemptions, data_categories ("special categories") and retention "Not specified" were not directly confirmed in the
  searches. Low-medium confidence on those columns.
URLs relied on:
- https://dpa.gov.lk/Background.php (row URL; regulator)
- https://dpa.gov.lk/est.php
- https://www.ft.lk/front-page/Data-protection-compliance-regime-takes-effect-on-1-Jan-2027/44-795776
- https://documents.gov.lk/view/egz/2026/7/2498-16_E.pdf
- https://www.dailymirror.lk/breaking-news/Legal-experts-flag-unique-compliance-landscape-in-staggered-PDPA-rollout/108-346685
- https://concentric.ai/sri-lankas-pdpa-takes-effect-january-1-2027-what-global-enterprises-need-to-know/
- https://www.sundaytimes.lk/260809/business-times/action-for-compliance-as-dpa-enforces-next-year-651378.html
- https://www.wilmerhale.com/en/insights/blogs/wilmerhale-privacy-and-cybersecurity-law/20220330-sri-lanka-becomes-the-first-south-asian-country-to-pass-comprehensive-privacy-legislation
- https://www.dataguidance.com/notes/sri-lanka-data-protection-overview
- https://dlapiperdataprotection.com/index.html?c=LK&t=law
- https://www.media.gov.lk/media-gallery/latest-news/3491-speaker-endorses-the-certificate-on-personal-data-protection-amendment-bill

## Nepal (NP) - confidence: medium
Law: Privacy Act, 2075 (2018), also commonly cited as the Individual Privacy Act, 2018. Act No. 14 of 2075.
- enactment_date and effective_date 2018-09-18 (2075-06-02 BS): Law Commission data (authentication 2075.6.2), Pioneer Law and a
  Japanese law-firm summary agree; section 1 says the Act takes effect immediately. One machine-translated database lists
  2018-09-20; ignored.
- It is Nepal's only general privacy statute (covers personal information, data, communications, etc.) and not a sectoral rule,
  but it is not a GDPR-style data-protection code. Included at medium confidence on that basis; reviewer may prefer to drop it.
- No data protection authority exists (DLA Piper, DataGuidance, academic assessment). Complaints go to district court or police.
- penalties_range: up to 3 years prison and/or NPR 30,000 fine (two sources). DLA Piper alone cites NPR 500,000 for failing to
  destroy data within 30 days of purpose; unresolved conflict, so the lower consistent figure is used.
- Individual Privacy Regulation 2020 (2077) reported by one source; another page says no rules issued (likely older). Mentioned
  only in notes column.
- The linked Law Commission page is the official source but is headed in Nepali; whether it embeds an English text was not
  confirmed, so language = Nepali. An English copy exists at https://lpr.adb.org/resource/privacy-act-2075-2018-nepal.
- Key requirements, data categories and exemptions are generic descriptions; exemptions left as "Not specified". Low-medium
  confidence on those columns.
- Related but not used: Data Act 2079 (2022); pending IT and Cybersecurity Bill (tabled 2025-08-14, status unknown).
URLs relied on:
- https://lawcommission.gov.np/content/12261/12261-the-privacy-act-2075/ (row URL)
- https://lawcommission.gov.np/content/12261/the-privacy-act-2075/
- https://pioneerlaw.com/individual-privacy-act-2018-2075
- https://lpr.adb.org/resource/privacy-act-2075-2018-nepal
- https://www.dlapiperdataprotection.com/?c=NP&t=law
- https://www.dataguidance.com/jurisdiction/nepal
- https://monolith.law/corporate/nepal-data-privacy-law

## Skipped
- Mongolia (MN): law identified but not written. The Law on Personal Data Protection (title varies: "Protection of Personal
  Information" in some sources) took effect 2022-05-01 (PwC Mongolia, DataGuidance agree) and repealed the 1995 Personal Secrets
  Law and 2011 Data Transparency law. The exact adoption day (December 2021) was not confirmed by two sources before the
  search budget ended, and the regulator is disputed (National Human Rights Commission vs an "Authority for the Protection of
  Personal Information" per KS&K; NHRCM page says NHRCM oversees with the Ministry of Digital Development and Communications).
  Penalty (MNT 500,000-20,000,000) is from one vendor page only. Leads: https://www.pwc.com/mn/en/tax_alerts/tax_alert_02_2022.html,
  https://www.dataguidance.com/jurisdiction/mongolia, https://gratanet.com/publications/legal-alert-new-law-on-protection-of-personal-data-in-mongolia,
  https://nhrcm.gov.mn/en/page/40, https://legalinfo.mn/en/edtl/16389888573371. Needs one more search for the adoption date
  and regulator, then it can be added.
- Macao (MO): not researched (search budget exhausted). Law 8/2005 Personal Data Protection Act is the target; needs enactment
  and effective dates plus Gabinete para a Protecao de Dados Pessoais (GPDP) page confirmed.
- Bangladesh (BD): not researched. Must first confirm whether a Personal Data Protection Ordinance/Act has actually been
  enacted (only drafts were known); skip if not.
- Laos (LA): not researched. Law on Data Protection (2017) is the candidate; dates unconfirmed.
- Brunei (BN): not researched. Brunei has no general law known to be in force; verify the Personal Data Protection Order and
  its commencement before including.
- Pakistan (PK): not researched. Only bills/drafts known to me; include only if an enacted law's dates are confirmed.
- Cambodia (KH): not researched. Same as Pakistan; likely draft only.

A follow-up run with a fresh WebSearch budget is needed for MN, MO, BD, LA, BN, PK, KH.
