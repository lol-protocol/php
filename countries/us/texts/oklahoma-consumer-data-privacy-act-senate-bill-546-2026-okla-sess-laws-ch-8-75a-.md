---
source: "https://secureprivacy.ai/blog/oklahoma-privacy-law-2027-guide"
law: "Oklahoma Consumer Data Privacy Act (Senate Bill 546, 2026 Okla. Sess. Laws ch. 8; 75A O.S. ss. 300-320)"
title: "Oklahoma Privacy Law 2027: SB 546 Compliance Guide"
fetched: "2026-11-25T11:24:04Z"
---

Back to Blog

Oklahoma's Consumer Data Privacy Act (Senate Bill 546) takes effect on January 1, 2027, which is 81 days from today. It applies to businesses that handle the personal data of 100,000 or more Oklahoma residents in a calendar year, or 25,000 or more if they also earn over half their revenue from selling personal data. Only the Oklahoma Attorney General can enforce it, with penalties of up to $7,500 per violation after a 30-day chance to cure.

Governor Kevin Stitt signed the bill on March 20, 2026, and sources count Oklahoma as the 20th or 21st state with a comprehensive privacy law. It is one of the lighter ones: no rulemaking, no universal opt-out requirement, no private lawsuits. But the core duties still land on day one, including opt-in consent for sensitive data and written data protection assessments.

## Who the Oklahoma law covers

The Act applies to a controller or processor that does business in Oklahoma, or produces a product or service targeted to Oklahoma residents, and that meets one of two thresholds during a calendar year (Section 15 of the enrolled bill):

- It controls or processes personal data of at least 100,000 consumers, or
- It controls or processes personal data of at least 25,000 consumers and derives more than 50% of gross revenue from the sale of personal data.

There is no revenue-only trigger and no separate small-business carve-out. The thresholds do that work. A company with no Oklahoma office can still be covered if enough Oklahoma residents are in its data.

A "consumer" is an Oklahoma resident acting only in an individual or household context. People acting in a commercial or employment context are excluded, so most B2B contact data and employee data fall outside the Act. The law separately exempts data about job applicants, employees, and contractors when it is collected and used within that role.

## What counts as an exemption

Some organizations are exempt entirely. Others are only exempt for certain data. The difference matters.

Entity-level exemptions (the whole organization is outside the Act):

- State agencies and political subdivisions, and their service providers when processing for them
- Financial institutions and data subject to Title V of the Gramm-Leach-Bliley Act (GLBA)
- HIPAA covered entities and business associates
- Nonprofit organizations
- Institutions of higher education, public and private

Data-level exemptions (the organization is covered, but this data is not):

- Protected health information and health records
- Data regulated by the Fair Credit Reporting Act (FCRA), to the extent that activity is regulated and authorized under it
- Data governed by the Family Educational Rights and Privacy Act, the Driver's Privacy Protection Act, and the Farm Credit Act
- Employee, applicant, and contractor data used within that role, plus emergency-contact data

A retailer that runs background checks through an FCRA-regulated vendor shows why this matters. The FCRA exemption covers that background-check activity, not the retailer's customer data. Everything else the retailer holds about Oklahoma shoppers still falls under the Act.

## Consumer rights

Oklahoma residents can ask a controller to confirm whether it processes their data and to give them access. They can also correct inaccuracies, delete data provided by or obtained about them, and get a portable copy of data they previously provided. They can opt out of three things: targeted advertising, the sale of personal data, and profiling used for decisions with legal or similarly significant effects.

The mechanics are specific:

- Controllers must respond within 45 days, and can extend once by another 45 days if they explain the reason within the first 45.
- If a controller declines a request, it must say why and explain how to appeal. It then has 60 days to answer the appeal in writing.
- Controllers must offer at least two request methods, and cannot require a new account. A controller that operates only online and has a direct relationship with the consumer can offer just an email address.
- Requests are free up to twice a year, except where they are manifestly unfounded, excessive, or repetitive.

The law also has no right to withdraw consent and no authorized-agent provision. Neither appears in the enrolled text.

## What businesses must do

Get opt-in consent for sensitive data. Sensitive data covers data revealing racial or ethnic origin, religious beliefs, a mental or physical health diagnosis, sexual orientation, or citizenship or immigration status. It also covers genetic or biometric data processed to uniquely identify a person, precise geolocation within 1,750 feet, and personal data from a known child, meaning under 13. For a known child's data, the controller must follow the Children's Online Privacy Protection Act instead. Our guide to personal data versus sensitive data explains how to sort what you hold.

Write data protection assessments. Before processing, controllers must document an assessment for targeted advertising, the sale of personal data, sensitive data, and certain risky profiling. A catch-all covers any other activity that poses a heightened risk of harm. Each assessment weighs the benefits of the processing against the risks to consumers. An assessment done for another law can count if its scope and effect are reasonably comparable. The requirement is not retroactive: it applies to processing that begins on or after January 1, 2027. The Attorney General can request assessments in a civil investigation, and they are confidential under the state's Open Records Act.

Update your privacy notice. It must be reasonably accessible and clear. It must list the categories of personal data processed (including sensitive data), the purposes, how to exercise rights and appeal, and, where applicable, the categories of data shared with third parties and the categories of those third parties. If you sell data or run targeted advertising, the notice must say so clearly and conspicuously, and explain how to opt out.

Limit and secure data. Collection must be adequate, relevant, and reasonably necessary for the disclosed purposes. Using data for an incompatible new purpose needs consent. Controllers also must keep reasonable security practices and cannot discriminate against consumers who exercise their rights, though bona fide loyalty programs are allowed.

Tighten processor contracts. Processors must follow controller instructions and help with rights requests, security and breach notification, and assessments. The contract must set out the instructions, nature and purpose of processing, data type, duration, and both parties' obligations. It must also require confidentiality, deletion or return of data at the end of the service, proof of compliance on request, cooperation with assessments, and written flow-down terms for subcontractors.

## What this means for cookie banners and consent flows

Oklahoma is mostly an opt-out regime, with two exceptions: sensitive data requires opt-in, and the consent definition is strict. The Act says consent is not valid if it comes from accepting broad terms of use, hovering over, muting, pausing, or closing content, or from a dark pattern. A banner that treats a closed pop-up as agreement will not meet that bar for sensitive data.

Three practical points follow for teams that manage consent:

1. Keep the opt-out for targeted advertising even though "sale" is narrow. The Act defines a sale as an exchange of personal data for monetary consideration, which is narrower than some states. Targeted advertising has its own opt-out right, so ad-tech cookies still need a working opt-out and a disclosure in your notice.
2. Treat precise location and biometric features as consent triggers. A store locator, mobile SDK, or identity-verification tool may collect sensitive data without your marketing team knowing.
3. Log choices. Oklahoma has no rule that you recognize browser signals, but a consent record helps if the Attorney General asks you to show how a violation was cured.

## No universal opt-out signal and no rulemaking

The enrolled bill does not require controllers to honor opt-out preference signals such as Global Privacy Control (GPC). It also does not authorize the Attorney General to write rules. That means the statute's text is the compliance target, and it will not shift through regulation the way obligations have in California and Colorado. Law firm commentary calls this a low-lift add-on for companies already compliant elsewhere.

If you already serve California, Colorado, or Louisiana visitors, you are probably honoring signals for them. Extending that behavior to Oklahoma is optional, but one consistent rule is often simpler than geo-fencing it. Our Global Privacy Control guide covers how the signal works.

## Enforcement: the Attorney General only, with a cure period

The Attorney General has exclusive enforcement authority, and the Act says nothing in it creates a private right of action. Before suing, the Attorney General must send written notice naming the specific provisions allegedly violated, at least 30 days before the action. No action can follow if, within that period, the business cures the violation and gives the Attorney General a written statement. The statement must say the violation was cured, include supporting documentation, and promise no further violations.

A civil penalty of up to $7,500 per violation applies to violations after the cure period, or to a breach of that written statement. The court may also award attorney fees and investigation expenses, and the Attorney General can seek an injunction.

The enrolled text contains no sunset date for the cure right. That differs from Louisiana, where the cure period ends on July 31, 2027.

## How Oklahoma compares with Louisiana

Louisiana's Data Privacy Act (SB 386) was signed on May 29, 2026, and starts the same day as Oklahoma's. The two laws differ in ways that matter if you operate in both states.

| Feature | Oklahoma (SB 546) | Louisiana (SB 386) |
| --- | --- | --- |
| Signed | March 20, 2026 | May 29, 2026 |
| Effective date | January 1, 2027 | January 1, 2027 |
| Who is covered | 100,000 consumers, or 25,000 plus over 50% of revenue from data sales | $25 million revenue, 75,000 consumers, households, or devices, or 50% of revenue from data sales |
| Universal opt-out signals | Not required | Required to be recognized, per law firm summaries |
| Cure period | 30 days, no sunset date in the text | 30 days, ends July 31, 2027 |
| Private right of action | None | None |

Louisiana's lower thresholds mean smaller companies can be covered there but not in Oklahoma. A business under 100,000 Oklahoma consumers may still need a Louisiana program. Do not assume one program covers both states.

Oklahoma's structure follows the Virginia and Texas models, according to law firm commentary. If you already comply with those laws, see our guides to the Virginia Consumer Data Protection Act and the Texas Data Privacy and Security Act, and compare it with Colorado, which layers regulations on its statute. Our US state privacy law tracker follows the full list.

## Compliance checklist

▢ Count Oklahoma consumers and check revenue from data sales against both thresholds

▢ Map which data is exempt (FCRA, HIPAA, employee) and which is not, instead of assuming a business-wide exemption

▢ Update your privacy notice with the required categories, appeal process, and sale or targeted advertising disclosure

▢ Add opt-in consent for sensitive data, with no bundled terms or dark patterns

▢ Document data protection assessments for targeted advertising, sales, sensitive data, and risky profiling

▢ Build request intake with two methods, 45-day tracking, and a 60-day appeal workflow

▢ Review processor contracts for the required terms

▢ Decide whether to honor opt-out signals for Oklahoma visitors

▢ Set up a record of consent and rights requests that can support a cure statement

## FAQ

### When does Oklahoma's privacy law take effect?

January 1, 2027. Governor Stitt signed SB 546 on March 20, 2026.

### Who has to comply with the Oklahoma Consumer Data Privacy Act?

Businesses that operate in Oklahoma or target its residents and handle data on at least 100,000 consumers, or at least 25,000 while earning over 50% of gross revenue from selling data. Nonprofits, higher education institutions, GLBA financial institutions, and HIPAA covered entities and business associates are exempt.

### Does Oklahoma require honoring Global Privacy Control?

No. The enrolled bill has no requirement to recognize universal opt-out signals. Louisiana, by contrast, requires them according to law firm summaries.

### Can consumers sue under the Oklahoma privacy law?

No. The Attorney General has exclusive enforcement authority, and the Act says it creates no private right of action.

### Does the cure period expire?

The enrolled text has no end date for the 30-day cure right. Louisiana's cure period, by contrast, ends July 31, 2027.

### Do employee and B2B data count?

No. The Act excludes people acting in a commercial or employment context, and exempts applicant, employee, and contractor data used in that role.

### If I already comply with Virginia's law, am I covered in Oklahoma?

Largely, since the structure is similar. Check your sensitive-data consent flows and assessments against the Oklahoma text, because the sensitive data list and consent definition are specific to it.

Tracking threshold differences across a growing list of state laws is a moving target. Our consent management platform guide explains how teams automate it. Book a demo to see how Secure Privacy helps you extend compliance to new states like Oklahoma.

Daniel de Almeida Afonso

Privacy & Compliance Lead

RELATED CONTENT

## Continue Reading

Explore more privacy compliance insights and best practices

Privacy Governance TrustArc Alternatives for Privacy Compliance in 2026 TrustArc publishes no prices. Compare OneTrust, Osano and Secure Privacy on cost, mobile coverage and fit, with dated sources and a switching checklist. By Daniel de Almeida Afonso Nov 17, 2026 12 min read Read

Privacy Governance Rhode Island Privacy Law (RIDTPPA): 2026 Compliance Guide Rhode Island's privacy law (RIDTPPA) has been in force since 1 January 2026, with no cure period and no GPC mandate. Scope, exemptions and penalties explained. By Daniel de Almeida Afonso Nov 9, 2026 22 min read Read

Privacy Governance Video Privacy Protection Act (VPPA) Guide for 2026 The Video Privacy Protection Act (VPPA) bars sharing what people watch without written consent, and the Supreme Court is deciding who can sue under it. By Daniel de Almeida Afonso Nov 4, 2026 13 min read Read

View All Articles
