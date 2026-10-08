# Notes: C-mena-africa.csv (batches C5 MENA + C6 Africa)

25 rows written, 0 skipped. 54 of 55 WebSearch calls used. `treaties_signatory` left empty on every row (as instructed).

General caveats that apply to many rows:
- Most term facts come from WIPO Lex metadata, Wikimedia Commons "Copyright rules by territory" pages (which quote article numbers) and law-firm summaries (AGIP, Mondaq, Adams & Adams). No statute text could be opened, so every term is "summary-confirmed", not "text-confirmed".
- Unconfirmed descriptive fields (orphan_works, digital_protection, fair_use_exceptions, and often moral_rights) are "Not specified". They are not "no provision".
- `registration_required` must be `No`/`Yes`. Where "No" is given without a parenthetical, it rests on a secondary statement that protection is automatic, or on the Berne no-formalities principle. Treat those as medium/low.
- The linked_resources URL is the statute's WIPO Lex page where I was confident which ID is the statute. Exceptions are listed per row.

## Middle East / North Africa

| Code | Law | Term | Confidence | URL relied on |
|---|---|---|---|---|
| QA | Law No. 7 of 2002 (Protection of Copyright and Related Rights) | life+50 (Art. 15, Commons) | medium-high | https://www.wipo.int/wipolex/en/legislation/details/3567 |
| KW | Law No. 75 of 2019 (Copyright and Related Rights) | life+50 | medium | https://www.wipo.int/wipolex/en/legislation/details/19908 |
| BH | Law No. 22 of 2006 as amended to Law No. 5 of 2014 | life+70 | medium-high | https://www.wipo.int/wipolex/en/legislation/details/19867 |
| OM | Royal Decree No. 65/2008 (Copyright and Neighboring Rights Law) | life+70 | medium | https://www.wipo.int/wipolex/en/details.jsp?id=5832 |
| JO | Copyright Law No. 22 of 1992 (as amended) | life+50 | medium | https://www.wipo.int/wipolex/en/details.jsp?id=2594 |
| LB | Law No. 75 of 1999 | life+50 | high | https://www.wipo.int/wipolex/en/details.jsp?id=2786 |
| IQ | Law No. 3 of 1971 as amended by CPA Order 83 (2004) | life+50 | medium-low | https://www.wipo.int/wipolex/en/legislation/details/10345 |
| IR | 1970 Law for the Protection of the Rights of Authors, Composers and Artists, amended 2010 | life+50 | medium-low | https://www.wipo.int/wipolex/en/details.jsp?id=7708 |
| MA | Law No. 2-00 as amended by Laws 34-05 and 79-12 | life+70 | high | https://wipo.int/wipolex/en/legislation/details/5058 |
| TN | Law No. 94-36 of 1994 as amended by Law No. 2009-33 | life+50 | high | https://www.wipo.int/wipolex/en/legislation/details/6161 |
| DZ | Ordinance No. 03-05 of 19 July 2003 | life+50 | high | https://www.wipo.int/wipolex/en/details.jsp?id=1194 |

Doubts, MENA:
- QA: enforcement body (MoCI Intellectual Property Protection Department) comes from WIPO's competent-administration listing. Deposit is described as voluntary only by an unofficial source. One site mentions a "Law No. 10 of 2021" amendment that I could not corroborate.
- KW: the sources for the 2019 law's text were thin. life+50 is consistent across the 1999 law, the 2016 law and law-firm commentary, but I never saw Law 75/2019's own article. Note that WIPO Lex marks Law No. 22 of 2016 as repealed. Enforcement body is the National Library (previously the Ministry of Information); the registration wording is inferred.
- BH: AGIP says "20 to 70 years" and Generis says life+50 (probably the superseded 1993 regime). Mondaq and Commons both say life+70. Enforcement body "Copyright Protection Office, Ministry of Information" comes from an older commercial summary; the office may now sit under the Ministry of Industry, Commerce and Tourism. LOW confidence on that field.
- OM: AGIP says life+50; Commons and Wikipedia say life+70 (Art. 26, tied to the US FTA). I went with 70. Amendment Royal Decree 132/2008 text not seen. The ministry name (MOCIIP) comes from Chambers 2026.
- JO: Mondaq (older) says life+30; every other source says life+50. Law No. 23 of 2014 amended Arts. 8 and 17 (moral rights, exceptions), and I could not see the amended wording. WIPO IDs 15109 and 22010 may be newer consolidated versions than 2594. Enforcement body inferred from the statute's National Library depositary clause.
- LB: Wamda/AUB/Commons agree. Mondaq 2000 says deposit is a condition of protection (older regime). I treated deposit as optional.
- IQ: life+50 appears only in Commons and AGIP; AGIP also says the law "is still not implemented". The original 1971 term was life+25 (min. 50 years from publication). Registration and enforcement body (Ministry of Culture deposit) rest on one AGIP sentence, so the registration field is deliberately hedged.
- IR: WIPO Lex still shows the 1970 text with a 30-year post-mortem term. Commons, a blog and Wikipedia say Art. 12 was amended in 2010 to life+50 (works still protected on 22 Aug 2010). I used 50 but the amended text was not seen. Iran is outside Berne and foreign works are largely unprotected. Works for hire and legal-person works run 30 years from publication.
- MA: registration and "deposit" status could not be confirmed (Art. 17 deposit exists, BMDA is the office). The registration field is low confidence. Moral rights from Art. 9 as quoted by ICT Policy Africa.
- TN: the OTDAV deposit certificate is described by a secondary wiki summary only.
- DZ: sources disagree on whether ONDA deposit is mandatory; one blog says optional. The Law No. 10-05 of 2010 amendment effect is unknown.

## Africa

| Code | Law | Term | Confidence | URL relied on |
|---|---|---|---|---|
| GH | Copyright Act, 2005 (Act 690) | life+70 | medium-high | https://www.wipo.int/wipolex/fr/legislation/details/1789 |
| SN | Law No. 2008-09 of 25 Jan 2008 | life+70 | medium-high | https://www.wipo.int/wipolex/en/text/498404 |
| CI | Law No. 2016-555 of 26 July 2016 | life+70 | medium | https://www.wipo.int/wipolex/en/legislation/details/16840 |
| UG | Copyright and Neighbouring Rights Act, 2006 (Act 19 of 2006) | life+50 | medium | https://www.wipo.int/wipolex/en/text/585337 |
| RW | Law No. 055/2024 of 20/06/2024 on the Protection of IP | life+50 | medium | https://www.wipo.int/wipolex/es/legislation/details/22672 |
| TZ | Copyright and Neighbouring Rights Act, 1999 (Cap. 218) | life+50 | medium-high | https://tanzlii.org/en/akn/tz/act/1999/7/eng@2023-12-31 |
| ZM | Copyright and Performance Rights Act, 1994 (Cap. 406) | life+50 | medium-high | https://info.pacra.org.zm/how-long-does-my-copyright-protection-last |
| ZW | Copyright and Neighbouring Rights Act [Chapter 26:05] | life+50 | medium | https://aripo.org/storage/resources-member-state-laws/1730129699_zw020en-compressed.pdf |
| MU | Copyright Act 2014 (Act No. 2 of 2014) | life+50 | medium-high | https://www.wipo.int/wipolex/en/text/539950 |
| BW | Copyright and Neighbouring Rights Act, 2000 (Cap. 68:02) | life+50 | medium | https://www.wipo.int/wipolex/fr/legislation/details/548 |
| AO | Law No. 15/14 of 31 July 2014 | life+70 | medium | https://www.wipo.int/wipolex/en/legislation/details/18642 |
| ET | Proclamation No. 410/2004 (amended by 872/2014) | life+50 | medium | https://www.wipo.int/wipolex/en/details.jsp?id=5306 |
| MW | Copyright Act, 2016 (Act No. 26 of 2016) | life+50 | medium | https://www.wipo.int/wipolex/en/legislation/details/17267 |
| CM | Law No. 2000/011 of 19 Dec 2000 | life+50 | medium-high | https://www.wipo.int/wipolex/es/text/243144 |

Doubts, Africa:
- GH: the Copyright Office is a department of the Ministry of Justice (WIPO profile); the leadership listing is dated. The 1985 law was life+50 and is superseded.
- SN: only Commons and Global Law Experts for the 70-year term. Moral rights, registration and exceptions were not retrieved. "No" for registration rests on the Berne principle.
- CI: all article-level facts come from a Commons summary. The 1996 law had 99 years. The WIPO ID 16840 was inferred to be the 2016 law from where it appeared in results (the consolidated civlii copy is at https://civlii.laws.africa/en/akn/ci/act/2016/555/fra@2023-06-30/source, which is certainly the 2016 law). BURIDA under the Ministry of Culture and Francophonie comes from a business directory; BURIDA's role under the 2016 text is unconfirmed. Moral-right perpetuity is only from a generic site, so I left moral_rights unspecified.
- UG: URSB registration is voluntary (URSB page). Moral rights and exceptions not retrieved.
- RW: the 2009 law (Law No. 31/2009, amended by 50/2018) was replaced by Law 055/2024 (in force 31 July 2024); both give life+50. WIPO ID 22672 was inferred to be the 2024 law. The enforcement body is the Office of the Registrar General at RDB per WIPO's profile, and the new law may have created a separate IP office. A blog claims the Rwanda Society of Authors was dissolved in 2025; I could not verify that and it is not in the CSV.
- TZ: the Act's Zanzibar counterpart is separate and not covered. The 2019 COSOTA report mentions pending amendments. I used the TanzLII consolidated Act because I could not identify which WIPO ID is the 1999 Act (5791 appeared but may be another text).
- ZM: a Copyright and Related Rights Bill (public comment opened 12 Jan 2026) would repeal Cap. 406. Its status in Oct 2026 is unknown and the term may change. WIPO's consolidated text omits the 2010 amending Act (No. 25 of 2010).
- ZW: the ARIPO-hosted PDF was used because I could not tell which WIPO ID is Chapter 26:05. Enforcement body from the 2009 WIPO profile (CIPZ) and may be dated. A mirrored page also mentions ZIPO, which I did not use. Moral rights note comes from the 2009 WIPO profile (possibly referring to the repealed Act).
- MU: the Act was amended in 2017 (Copyright (Amendment) Act 2017); I did not check whether section 15 changed. Registration: sources conflict (legal directory says none; a 2010 questionnaire named MASA under the old Act). The enforcement body field is the competent ministry per WIPO profile.
- BW: term confirmed only through Commons (s. 10(1), as amended 2006). The Copyright Office sits within the Companies and Intellectual Property Authority (CIPA). Registration status unknown; "No" is Berne-based inference.
- AO: Commons plus a lawyer's article (Ver Angola) both say life+70; Lawzana says +50 but cites nothing. Commons also says photographs and applied art are life+45. I left special terms out. Registration voluntary per Ver Angola.
- ET: Ethiopia is outside Berne. Registration is governed by Regulation 305/2014 and I could not tell whether it is a condition of protection, so the registration field is hedged. Proclamation 872/2014 may have changed provisions.
- MW: term only from Commons (s. 35); WIPO notes the Act entered into force on 13 March 2017 except Part III. Retroactivity unclear. COSOMA acts as Copyright Office per its own description.
- CM: Commons, Adams & Adams and a Bangui/OAPI summary all give life+50. Applied art is 25 years under Adams and 50 years (creation or publication) under Commons; I used the Commons wording only for the categories it covers. Registration not found, so "No" is inferred.

## Skipped
None. No jurisdiction was dropped. Every row has a confirmed statute title and general term from at least two summaries, except where noted above as single-source (BW, MW, SN, UG, ZW: Commons plus corroboration of the title only).

## Biggest risks for the reviewer (summary)
1. IR term (WIPO 1970 text says life+30; the 2010 amendment to life+50 is secondary-sourced).
2. OM/BH term of 70 years and the conflicting AGIP figures; Kuwait 2019 law's text not seen.
3. Enforcement-body fields for BH, CI, RW and ZW (dated or unconfirmed listings).
