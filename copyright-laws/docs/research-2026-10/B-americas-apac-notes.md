# Notes: B-americas-apac.csv (batches C3 Americas + C4 Asia-Pacific)

23 rows written, 1 skipped (CU). 50 WebSearch calls used (limit 55). CSV validated: 15 columns on every row, no duplicate URLs, treaties_signatory empty on every row (left for the membership check).

## Conventions and general caveats
- All term figures come from search results (WIPO Lex entries, Wikimedia Commons "Copyright rules by territory" summaries, law-firm guides such as Chambers, Legal 500 and GTAI, and national office pages). No statute text was opened, since WebFetch is unavailable. Article numbers are secondary-source claims.
- `moral_rights` (`Paternity & integrity`) and `fair_use_exceptions` (`Statutory limitations & exceptions`) are generic. They rest on the Berne Convention art. 6bis and the usual structure of these statutes, not on a retrieved article, except where noted. Treat them as medium or low confidence everywhere.
- `orphan_works` and `digital_protection` are `Not specified` except TW (anti-circumvention, art. 80-2, from background knowledge, not in the results).
- `registration_required` uses `No (voluntary registration available)` only where a registry or voluntary registration appeared in the results. Elsewhere it is plain `No` (Berne forbids formalities, so this is safe). In the results: VE, UY (registry under the Consejo), NP, MM, MN, PK (registry). Taken from the general statutory structure without a source in the results: PY, CR, BO, PA, GT, EC.
- Several WIPO Lex links are non-English variants or CDN hosts, used exactly as they appeared in results: VE (`/zh/`), CR (`/es/`), MM (`/es/`), LK (`/es/text/`), LA (`cdn.nestjs.wipolex...`).

## Americas (C3)

### UY Uruguay - confidence high (term), medium (office)
- Law No. 9.739 (1937), consolidated up to Law 19.857 of 2019, which raised the term to life + 70.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/21441
- Also seen: https://www.gub.uy/ministerio-educacion-cultura/politicas-y-gestion/consejo-derechos-autor-uruguay (Consejo de Derechos de Autor, which supervises the Registro de Derechos de Autor).
- Doubt: the 70-year wording itself was not quoted, only the WIPO Lex amendment title. The Consejo sits under the Ministry of Education and Culture.

### EC Ecuador - confidence medium
- Statute: Código Orgánico de la Economía Social de los Conocimientos, Creatividad e Innovación (COESCCI, "Código Ingenios"), Registro Oficial Suplemento 899, 9 Dec 2016. It replaced the 1998/2006 IP Law.
- Term life + 70: Legal 500 guide ("entire life and seventy years after his death"); Commons and igerent (these describe the old 2006 law, same figure). The article (114) was not verified.
- URL used: https://www.gob.ec/regulaciones/codigo-organico-economia-social-conocimientos-creatividad-innovacion (official portal, marked "referential" by gob.ec itself).
- Also seen: https://www.wipo.int/wipolex/zh/legislation/details/16990 (probably the COESCCI, not confirmed).
- SENADI named as the claims body (Lawyer Monthly).

### VE Venezuela - confidence high
- Ley sobre el Derecho de Autor, Gaceta Oficial 4.638 Extraordinario, 1 Oct 1993; art. 25 life + 60. Anonymous/pseudonymous and audiovisual/software terms per arts. 26-27 (one Commons-style summary).
- URL used: https://www.wipo.int/wipolex/zh/legislation/details/3989 (the only WIPO URL that appeared; it is the Chinese-language interface).
- SAPI confirmed as the competent office. Registration not a formality (confirmed).

### BO Bolivia - confidence medium-high
- Ley No. 1322 of 13 Apr 1992 (Derecho de Autor y Derechos Conexos). Life + 50 from Pixilegal and Commons, whose articles were not retrieved.
- Doubt: Senate bill P.L. 014/2023-2024 (https://web.senado.gob.bo/sites/default/files/P.L.%20N%C2%B0%20014-2023-2024%20C.S..pdf) would raise art. 18 to life + 75. Two searches found no evidence it was enacted. Re-check before relying on this row.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/494
- SENAPI confirmed via DS 25159 and DS 27938.

### PY Paraguay - confidence medium-high
- Ley No. 1328/98 de Derecho de Autor y Derechos Conexos, art. 47: life + 70 (Commons summary, igerent, WIPO Lex consolidation through Law 4.046/2010).
- URL used: https://www.wipo.int/wipolex/en/legislation/details/3557 (original 1998 text, marked obsolete on WIPO Lex, which points to a newer version). Newer entries that appeared: https://www.wipo.int/wipolex/es/legislation/details/21437 and https://www.wipo.int/wipolex/en/legislation/details/21436.
- Anonymous (70 from publication) and simple-photograph (50 from creation) terms were omitted: single source.
- DINAPI was named from background knowledge, not from the results.

### CR Costa Rica - confidence high (term), medium (office)
- Ley No. 6683 (1982), amended up to Law 9957 of 2021 per WIPO Lex. Art. 58: life + 70 (Commons, a lawyer directory, euagenda.eu).
- URL used: https://www.wipo.int/wipolex/es/legislation/details/21963
- Registro Nacional as the office comes from background knowledge. I did not check whether the 2021 amendment touched the term.

### PA Panama - confidence medium
- Ley No. 64 of 10 Oct 2012 (Gaceta Oficial 27139-B), life + 70 (Alemán Cordero Galindo & Lee blog, press on Bill 510 which moved the term from 50 to 70). The article text was not retrieved.
- URL used (official gazette PDF): https://s3-legispan.asamblea.gob.pa/legispan/NORMAS/2010/2012/LEY/Administrador%20Legispan_27139-B_2012_10_10_ASAMBLEA%20NACIONAL_64.pdf
- Also seen: https://www.wipo.int/wipolex/en/legislation/details/15426 (likely this law, not confirmed).
- Art. 194 transitional 80-year rule for authors who died before Law 15 of 1994 (probably expired by now). Not included in the row.
- Authority: Dirección General del Derecho de Autor (DIGEDA) per the law's definition of "competent authority".

### DO Dominican Republic - confidence medium-low (term)
- Ley No. 65-00 sobre Derecho de Autor (21 Aug 2000). WIPO Lex lists it as amended with no consolidation. The original art. 21 gave 50 years post mortem.
- The 70-year figure rests on: a Dominican law firm (Arthur & Castillo, 2024), the 2012 National IP Strategy listed on WIPO Lex, a WIPO 2004 report saying the law must move from 50 to 70 for DR-CAFTA, and Law 424-06 (DR-CAFTA implementation), which amended 65-00. The amended article 21 was never seen. Commons still shows 50, but it appears to reflect the original text.
- Biggest doubt in the Americas batch: check Law 424-06 / Gaceta Oficial 10393 before use.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/1191
- ONDA is under the Ministry of Culture (confirmed).

### JM Jamaica - confidence high
- Copyright Act 1993, amended by the Copyright (Amendment) Act 2015, which raised the term from life + 50 to life + 95 (WIPO news item, JIPO page, Commons). Unusual, but three sources agree.
- URL used: https://www.jipo.gov.jm/node/25
- Also seen: https://www.wipo.int/wipolex/en/details.jsp?id=2586 (old 1993 text); https://www.wipo.int/wipolex/en/legislation/details/20264 (WIPO news item on the 2015 amendment, probably).
- A pending Copyright (Amendment) Bill was mentioned by one commercial source and is unconfirmed. Sound recordings and films: 95 years from first making available (not in the row).

### CU Cuba - SKIPPED
- The current statute is Ley No. 154 de los Derechos del Autor y del Artista Intérprete (2022, Gaceta Oficial Ordinaria 122; WIPO Lex https://www.wipo.int/wipolex/es/legislation/details/21621). It replaced Law 14 of 1977 (life + 25 → life + 50 via Decreto-Ley 156 of 1994).
- Four searches never surfaced the duration article of Ley 154, so the general term is unconfirmed. Skipped rather than carrying over the repealed 50-year rule.

### SV El Salvador - confidence medium-low (term)
- Ley de Propiedad Intelectual, Legislative Decree No. 66 (published 15 Aug 2024, in force about Feb 2025), which replaced Decree 604 of 1993. The new text's duration article was never seen.
- Life + 70 rests on: the CNR information sheet (undated, probably pre-2024), Commons for the law consolidated to 2017 (art. 86), and the fact that the DR-CAFTA minimum is 70. One commercial site says 75; I treated it as unreliable.
- Re-verify the term in the 2024 decree.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/22675 (title confirmed "Intellectual Property Law, El Salvador, WIPO Lex").
- ISPI (as a dependency of the CNR) is the new body per law-firm and LexLatin pieces. Whether ISPI is operating yet was not checked.

### TT Trinidad and Tobago - confidence medium
- Copyright Act, Chap. 82:80. The Copyright (Amendment) Act 2026 (Act No. 3 of 2026, assent 11 Feb 2026, Gazette 12 Feb 2026) changes "fifty" to "seventy" in s. 19(1)-(2) per Parliament records and the explanatory note. Earlier term life + 50 (Commons).
- Doubts: no proclamation or commencement date was seen. No transitional or revival provisions were seen. A blog says broadcast protection stayed at 50.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/6639 (old-style id 6639 appeared as "Trinidad and Tobago"; it is probably the Copyright Act entry, not confirmed).
- Also seen: https://tradeind.gov.tt:443/wp-content/uploads/2016/02/Copyright-Act-82.80.pdf (pre-amendment text) and https://www.printery.gov.tt/e-gazette/2026/Acts/Act%20No.%203%20of%202026%20-%20The%20Copyright%20(Amendment)%20Act,%202026.pdf (amending act).

### GT Guatemala - confidence high
- Decreto 33-98, Ley de Derecho de Autor y Derechos Conexos, with reforms through Decreto 11-2006 (WIPO Lex). Art. 43: life + 75 (Commons summary and a mirror). Computer programs and collective works: 75 years from first publication (art. 44).
- URL used: https://www.wipo.int/wipolex/en/legislation/details/16159
- Also seen: https://www.wipo.int/wipolex/fr/text/585503 (Spanish text on WIPO Lex).
- Registro de la Propiedad Intelectual (Ministry of Economy) comes from background knowledge. No later term reforms were seen, but this was not exhaustively checked.

## Asia-Pacific (C4)

### TW Taiwan - confidence high
- Copyright Act, art. 30: life + 50; 50 years from public release for anonymous, pseudonymous, photographic and audiovisual works (art. 30-34, MOJ database text; amended 15 Jun 2022). Also a special 10-year term for first release in years 40-50 after death (not in the row).
- URL used: https://law.moj.gov.tw/ENG/LawClass/LawAll.aspx?pcode=J0070017
- Also seen: https://www.tipo.gov.tw/en/tipo2/393-2382.html (TIPO FAQ).
- Digital-protection and fair-use cells are from background knowledge (art. 80-2 anti-circumvention, art. 65 fair use).

### PK Pakistan - confidence high (term), medium (office)
- Copyright Ordinance, 1962 (amended 2000): life + 50 from the start of the next calendar year (s. 18). Sources: WIPO Lex text, Commons, Wikipedia, AGIP.
- URL used: https://www.wipo.int/wipolex/en/text/129350
- IPO-Pakistan is named by a legal-info site only. Photograph terms conflict between sources (not in the row). A 2012 amendment was mentioned and not corroborated.

### BD Bangladesh - confidence medium-high
- The Copyright Act, 2023 (Act No. XXXIV of 2023, assent and publication 18 Sep 2023) repealed the Copyright Act 2000. Life + 60 from GTAI (art. 22) and Chambers 2026. The 2000 Act also had 60. The Act text was not retrieved, and the calendar-year counting rule was not confirmed.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/23028 (title confirmed "Copyright Act, 2023, Bangladesh, WIPO Lex").
- Also seen: https://copyrightoffice.gov.bd/ and http://bdlaws.minlaw.gov.bd/act-1452.html (Bengali text).
- Copyright Office is under the Ministry of Cultural Affairs. Whether 2023 keeps voluntary registration was not confirmed.

### LK Sri Lanka - confidence high
- Intellectual Property Act No. 36 of 2003, s. 13: life + 70; collective and audiovisual works 70 years from first publication; applied art 25 years (Commons and Chambers disagree only on the start of the applied-art clock). The 1979 Act was life + 50.
- URL used: https://www.wipo.int/wipolex/es/text/597541 (assumed to be this Act's text on WIPO Lex, per the search summary).
- NIPO as the office is from background knowledge.

### NP Nepal - confidence high
- Copyright Act, 2059 (2002), s. 14: life + 50 counted from the year of death; photographs and applied art 25 years (ssrana FAQ and Commons). WIPO Lex English version is amended only to 2006.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/7230
- The Copyright Registrar's Office is confirmed. Its parent ministry is reported differently by different sources (Culture/Tourism/Civil Aviation vs a ministry in a WIPO questionnaire), so the cell names only the Registrar's Office. Site: www.nepalcopyright.gov.np. Registration is voluntary (confirmed).

### MN Mongolia - confidence medium
- Law of Mongolia on Copyright and Related Rights, promulgated 6 May 2021 (WIPO Lex), replacing the 2006 revised text. Life + 50 counted from 31 Dec of the year after death (Commons, art. 14.3). The article text was not retrieved. Applied-art start date conflicts between sources (not in the row).
- URL used: https://www.wipo.int/wipolex/en/legislation/details/22459
- Also seen: https://legalinfo.mn/en/edtl/16531498267681 (unofficial translation).
- IPOM confirmed as the copyright registering body (WIPO questionnaire, voluntary registration).

### MO Macao - confidence medium
- Regime of Copyright and Related Rights, Decree-Law No. 43/99/M as amended by Law No. 5/2012 (in force 1 Jun 2012; WIPO Lex has the amending law at https://www.wipo.int/wipolex/en/legislation/details/13134). Life + 50 from the start of the following year; the term was apparently unchanged by the 2012 amendment (Commons only; consistent with the TRIPS minimum).
- URL used: https://www.wipo.int/wipolex/en/legislation/details/3052 (marked "superseded", pointing to a newer version).
- DSEDT (renamed from the Economic Services in 2021) confirmed, with a Patent and Copyright Division. No copyright-work registration service found (vLex: no registration).

### KH Cambodia - confidence high (term)
- Law on Copyright and Related Rights (NS/RKM/0303/008, signed 5 Mar 2003). Art. 30: life + 50 (Commons). Anonymous works 75 years from publication (single source, not in the row).
- URL used: https://www.wipo.int/wipolex/en/legislation/details/5782
- The Ministry of Culture and Fine Arts as office is from background knowledge. The ministry hosts a 2020 English version, so check for amendments.

### LA Laos - confidence medium
- Law on Intellectual Property No. 50/NA (20 Nov 2023; in force 24 Jan 2024; published Mar 2024), replacing No. 38/NA of 2017 and No. 08/NA of 2007. Life + 50 was confirmed for the 2007 and 2017 laws (s. 93, Commons talk page). The 2023 text was not seen; summaries mention only applied art and pictures moving from 25 to 30 years.
- URL used: https://cdn.nestjs.wipolex.wji.prd.web1.wipo.int/wipolex/en/legislation/details/22624 (CDN-host variant, probably the LA046 entry).
- Also seen: https://www.wipo.int/wipolex/fr/legislation/details/18024 (2017 law, probably).
- Verify the general term in the 2023 text. DIP's parent ministry has changed over the years, so the cell names DIP only.

### MM Myanmar - confidence medium-high
- Copyright Law 2019 (Pyidaungsu Hluttaw Law No. 15/2019, enacted 24 May 2019; in force 31 Oct 2023 per Wikipedia and Rouse), repealing the Copyright Act 1914. Life + 50 (Lawplus, Tilleke, Rouse). Dates conflict across sources. Start-point wording is unclear in one table (not in the row). Registration is optional, and the IPD has accepted voluntary registration since Feb 2024. Moral rights are perpetual (Lawplus).
- URL used: https://www.wipo.int/wipolex/es/legislation/details/22939
- IPD under the Ministry of Commerce (one source). Sources are law-firm summaries of an unofficial translation.

### BN Brunei - confidence medium-high
- Emergency (Copyright) Order, 1999 (issued 18 Dec 1999, in force 1 May 2000), amended by the Copyright (Amendment) Order, 2013 (BN039). Section 14: 50 years from the end of the year of the author's death (Gazette text, AGC page, WIPO overview, Commons). The 2013 order's table of contents does not list s. 14. I did not read its operative text.
- URL used: https://www.wipo.int/wipolex/en/legislation/details/481
- Also seen: https://www.agc.gov.bn/AGC%20Site%20Pages/Copyright.aspx (AGC copyright page).
- AGC International Affairs Division was inferred from the leaflet's URL. CPTPP's life + 70 was not seen implemented. Moral rights: false-attribution right 20 years after death (AGC leaflet).

## Skipped
- CU Cuba: see above (Ley 154 of 2022 identified, general term not confirmed).

## Three biggest doubts
1. DO: life + 70 is inferred from secondary sources plus the DR-CAFTA amendments (Law 424-06). The amended art. 21 was not seen.
2. SV (and LA): the term in the new 2024 (SV) and 2023 (LA) statutes is carried over from earlier versions. The new texts were not seen.
3. BO: life + 50 stands unless Senate bill P.L. 014/2023-2024 (life + 75) was enacted. The searches could not show either way. TT's 2026 life + 70 amendment has no commencement date.
