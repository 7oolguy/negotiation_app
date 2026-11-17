# BOMTRATO — PROJECT CONTEXT FOR LOCAL CODING AGENT

> **Purpose:** Persistent working context for an AI coding agent operating in the BomTrato repository.
> **Status:** Early development / local prototype. Last context update: 2026-10-27.
> **Important:** This file describes intended architecture and business requirements, **not proof that features have been implemented**. Inspect the repository before making assumptions.

## 0. Instructions to the coding agent

1. **Read this file first**, then inspect actual repository state (`composer.json`, `.env.example`, `routes`, `app`, `database/migrations`, `app/Filament`, tests). Trust the repository over assumptions in this document.
2. **Work incrementally**. Propose or implement the smallest complete slice, explain decisions briefly, and run relevant checks/tests. Do not scaffold the entire product at once.
3. **Use the actual technology stack:** PHP, Laravel 13 (user reported `Laravel Framework 13.39.0`), Filament 5 (user reported having installed Filament and opened admin), Livewire/Blade, **SQLite for local development**. The earlier plan for FastAPI/Next.js/PostgreSQL is **superseded**. PostgreSQL may be considered later but is **not** the current database.
4. Respect framework conventions and installed version. Before using commands/APIs, check locally available versions and package documentation. Avoid copying incompatible Filament 3/4 code into Filament 5.
5. **Do not claim code or tests exist without checking.** Ask before applying destructive migrations or changing an existing schema that contains data. Avoid `migrate:fresh` unless explicitly approved.
6. Security is foundational: authorization must be enforced server-side, school-facing access is restricted, audit trails are essential, financial operations must be idempotent and transactional.
7. Never connect live payment credentials or issue real charges during development without explicit authorization. Prefer provider sandbox and fictional personal data.
8. **Maintain a task/progress log** in the repository (e.g. `docs/PROGRESS.md`) stating completed, pending, and blocked work, based on actual inspection, not the plan alone.
9. Default communication with developer can be Portuguese or English; the product is intended for **Brazilian users** and UI/business labels should use Portuguese (pt-BR), BRL currency, Brazilian date formatting.
10. When implementing commission rules, **never guess commercial contract values**. Unresolved terms must be explicit configurable fields and/or blocked pending approval.

## 1. Identity and objective

- **Product name:** **BomTrato** (final product name). Do not use earlier prototype names such as *Clarivo*.
- **Product category:** B2B platform for outsourced recovery/collection of **overdue school tuition and other authorized school receivables** in Brazil.
- **Business:** Developer's father and a group of lawyers are forming/operating a business to manage school receivable collection. BomTrato is the technology supporting this business, potentially growing into a SaaS offering.
- **Separate branding:** BomTrato is the **software/product name**, not necessarily the name of the developer's future technology company; that company brand is undecided.
- **Primary customers:** Schools (and potentially school maintaining entities, *mantenedoras*) contracting the collection company.
- **Final goal:** A production-ready, secure, auditable platform in which schools entrust overdue debts to the collection company; its collectors, managers, legal and finance staff operate the entire case lifecycle; schools monitor progress with **read-only** access; financial guardians can pay via **Pix/boleto** with funds destined for the school's authorized receiving account; BomTrato reconciles recoveries, proves attribution to authorized collection work, calculates contractual success fees, and bills schools monthly.
- Product ethos: Professional, clear, human and respectful collection (not intimidation); traceable decisions and transparent fee calculations.

## 2. Source requirements supplied by the business

The developer's father supplied a Markdown document titled **"Sistema de cobrança escolar — Processos e fluxogramas para o programador"**. This is the original functional source. Its main modules:

1. Initial dashboard: tasks, alerts, promised payments, late agreements, Serasa items, notices and judicial deadlines; visibility restricted by user.
2. Schools: school and maintaining entity information, authorized discounts/installments, commission/fee contracts, receipts/repasses, history.
3. Students and financial guardians: one guardian can support multiple students; keep each student's contract/debts distinct; duplicate checking and search.
4. Collections: installments, overdue amounts, charges and fees, contact channels and results; suspend collection if disputed or reported paid while investigating.
5. Negotiations/agreements: record original amounts, fees, authorized discounts, entry payments, future installments, due dates, acceptance and documents; distinguish **promise**, **signed agreement**, and **confirmed payment**.
6. Payments: manual or imported, reconcile amount/identity/duplicates; partial payments and authorized refunds; proof of payment alone is not confirmation.
7. Serasa: analysis, supporting evidence, approval, recording of requests/responses/inclusions/exclusions or rationale for not using Serasa.
8. Extrajudicial: verify documentation and authorizations, notice preparation/review/delivery, deadlines and responses.
9. Judicial: distinguish case review from actual court filing; lawyer, court case details, deadlines, expenses, hearings, decisions and closure.
10. Client/school financials: recovered/negotiated/open amounts, fees, expenses, transfers, and who received funds.
11. Office financials: office revenue, commissions, salaries, rent, taxes, payables/receivables; keep school money separate from office revenue.
12. Reports: collections by channel, overdue installments, agreements, receipts, Serasa/extrajudicial/judicial, productivity and fees; distinguish *charged*, *negotiated* and *received* values.
13. Import/export: preview, validation, duplicate/error correction, CSV/Excel/PDF where applicable, operation audit.
14. Settings: users and roles, schools, rules, templates, imports, backups and recovery.

**Business operational order:** Collection → Serasa review → Extrajudicial → Judicial review/proceedings. Negotiation/payment can occur in any phase. Escalation is subject to appropriate documentation, authorization and applicable legal requirements. Not every debt goes through every stage.

The father's document recommends building permissions → schools/students/guardians → contracts/debts/import → collection/agenda → agreements → payments → Serasa → extrajudicial → judicial → finances → reports. Integrations may be manual in V1.

## 3. Firm decisions agreed with product owner

### 3.1 School portal versus operations

- **School users can TRACK assigned debts**: status, progress, agreements, confirmed receipts, permitted history, summary reports and fee statements.
- **School users cannot interfere directly** with collections: they must not alter debt status, approve deals, send collection notices, approve commissions, or mark payments confirmed.
- The operations team performs actual collection activities. Where school consultation or approval is legally/contractually necessary, provide a controlled request/review workflow without granting broad write access to collection records.
- Internal case notes and privileged legal notes must not be exposed to school users.

### 3.2 Payment custody and beneficiary

- **Option A selected:** Create Pix/boleto payments **through BomTrato**, but money goes **directly to the school's legally authorized beneficiary/provider account**, not the collection company's general account.
- Payment provider and onboarding arrangement **not yet finalized**. Past exploration mentioned Asaas and Efí as possible options; neither is confirmed. Verify capabilities, costs, account ownership, settlement and webhook APIs before implementation.
- Provider webhook events and reconciliation must distinguish charge created, payment confirmed, funds available, provider settlement, refunds and reversals.
- The office normally charges the school separately for success fees.
- Direct payments received outside BomTrato must also be recordable/reconcilable.

### 3.3 Attribution-based success fee: exact approved policy

- **Commission is due only on recoveries attributable to documented collection efforts under the school-specific commercial contract.** Payment via BomTrato alone does not automatically establish attribution.
- **Attribution window:** CONTRACT-SPECIFIC.
- **Eligible recovery components** (principal/interest/penalties etc.): CONTRACT-SPECIFIC.
- **Payment-provider fees treatment:** CONTRACT-SPECIFIC.
- **Disputed commissions:** HOLD UNTIL RESOLVED, do not invoice them.
- **Commission billing:** MONTHLY.
- **Direct school payments:** MANUAL APPROVAL REQUIRED before an attribution decision can become commissionable.
- Both direct and platform payments need verified funds, matched obligations and audit evidence. An agreement or promise without actual recovery earns no commission.
- Attribution decisions reference immutable/versioned contractual rules, evidence, reviewer, timestamps and reason.

### 3.4 Outstanding commercial/legal terms (do not invent)

- Commission rate(s), fixed/tiered structures, caps/minimums and rounding.
- Evidence that counts as a qualifying collection action; whether a mere message delivery is sufficient (do NOT assume).
- Window duration; whether later qualifying contacts renew it; agreements extending attribution.
- Post-termination commission tail and rules for reassignment/multiple collection companies.
- Amount components eligible for commission; discounts and payment-provider cost treatment.
- Legal costs, extraordinary expenses and taxes.
- Closing day, invoice due date and document/tax format.
- Approval roles and review deadlines for direct school payments and disputed commissions.
- Who is legal payment beneficiary when multiple campuses share a maintaining entity.
- Treatment of school-paid external amounts and proof/reconciliation requirements.
- Contract clauses and regulatory compliance are subject to Brazilian legal/accounting review.

Do not turn examples such as **15% commission**, **30-day attribution**, or specific fictitious school figures into fixed business rules.

### 3.5 Visual Identity & Design System (Approved)

- **Brand philosophy:** Minimalist, clean, high-contrast black and white aesthetic ("We handle difficult financial situations with clarity and respect").
- **Font:** Inter for applications, Manrope for headings.
- **Brand Palette (Minimalist Black & White):**
  - Primary: `#09090B` (dark mode: `#FAFAFA`)
  - Secondary: `#52525B` (dark mode: `#A1A1AA`)
  - Accent: `#F4F4F5` (dark mode: `#27272A`)
  - Background: `#FFFFFF` (dark mode: `#09090B`)
  - Surface: `#FAFAFA` (dark mode: `#18181B`)
  - Text Primary: `#09090B` (dark mode: `#FAFAFA`)
  - Text Secondary: `#71717A` (dark mode: `#A1A1AA`)
  - Border: `#E4E4E7` (dark mode: `#27272A`)
- **Filament Panel Tokens:**
  - `primary`, `secondary`, `gray`, `info`: `Color::Neutral`
  - Semantic status badges (`success`, `warning`, `danger`): `Color::Green`, `Color::Amber`, `Color::Red` (used sparingly for operational status clarity).


## 4. Roles and boundaries

Recommended app roles (exact implementation may evolve):

- **Superadmin / platform operator:** manages platform provisioning, infrastructure configuration, technical monitoring and support using documented audited processes. Ownership of the software does **not** imply unrestricted access to all confidential school/guardian data.
- **Office administrator / manager:** oversees schools, portfolios, agents, approvals, productivity and operational policies.
- **Collector (Cobrador):** assigned case portfolio, contacts, evidence, tasks, negotiations and agreement requests.
- **Legal (Jurídico):** supporting documents, Serasa review, extrajudicial notices, judicial analysis and case progress.
- **Financial (Financeiro):** reconciles receipts, verifies external payments, attribution approvals where authorized, commission ledger, monthly statements and invoice/payment tracking.
- **School viewer (Escola):** read-only school-scoped authorized visibility.
- **Financial guardian / payer:** limited secure access to specific payment instructions, never administrative data.

Implement server-side `Policies`/permissions, per-school scoping, separation of duties for high-risk financial approvals, and audit trails. Prefer Laravel/Filament authorization conventions. Multi-panel Filament structure may be appropriate but need not be six independent applications or duplicated model logic.

## 5. Proposed domain model (target, not necessarily implemented)

Key tables/entities and relationships:

**Identity / institutional**
- `users`, `roles`/permissions, user-to-school visibility scopes.
- `organizations` (collection company, if needed), `legal_entities` (CNPJ holding beneficiary entity), `schools` (campuses), `school_payment_accounts` (PSP identifiers & onboarding statuses).
- `commercial_contracts`, immutable/versioned `contract_versions` (rates, windows, evidence policy, components, fee treatment, billing, authorized approval rules).

**School receivables**
- `students`, `guardians`, `student_guardians` (many-to-many with financial responsibility).
- `enrollment_contracts` (student obligation context), `debts`, `debt_installments`.
- `debt_assignments` (when the office took responsibility; assigned collector and end date).
- `collection_actions` (channel, time, user, results, evidence), `collection_tasks`.
- `agreements`, `agreement_installments`, acceptance/approval/evidence.

**Payments / attribution / finance**
- `payment_charges` (provider-issued Pix/boleto obligations).
- `payment_transactions`, `provider_webhook_events`, `payment_allocations` (one payment may pay multiple debts), `external_payment_reports`.
- `attribution_decisions`, `attribution_evidence`, `commission_disputes`.
- `commission_entries` immutable ledger including adjustments/reversals.
- `commission_invoices`, `commission_invoice_lines` and payments from schools to office.
- `audit_logs`, private evidence/document records, import jobs/results.

**Important distinction:** a school campus and legal creditor/payment recipient may be different entities. The starter Schools CRUD discussed earlier temporarily uses one unique school CNPJ, but this is **not sufficient for the final domain**. Design a clean migration path toward `legal_entities`/mantenedoras, avoiding permanent assumptions that each campus has a unique CNPJ.

## 6. Lifecycle and key invariants

### Debt
`IMPORTED → PENDING_VERIFICATION → READY_FOR_COLLECTION → ACTIVE_COLLECTION → (SERASA_REVIEW → EXTRAJUDICIAL → JUDICIAL_REVIEW → JUDICIAL_PROCEEDINGS) → CLOSED` is a simplified primary path. Disputed, under payment verification, suspended, agreement active, partially settled, and settled can coexist with progression and should be modeled as separate attributes or related records as appropriate. The workflow is not rigidly linear.

### Payment
Charge creation ≠ paid; payment notification ≠ verified; payment verified ≠ necessarily available in the recipient's external bank. Webhooks can repeat/out-of-order: process idempotently and reconcile to provider state. Do not double-allocate money. Payment totals must reconcile to authorized obligations and unapplied balances. Partial payments and refunds must be handled safely.

### Commission
Eligible recovery must be based on verified payment allocations, attributable collection work, and effective contract version. Direct external payments always require manual attribution approval. Disputed amounts withheld from invoice. Refund or reversal produces a compensating ledger entry; do not delete previously invoiced entries. One recovery cannot produce duplicate fee entries or appear twice on active invoices.

### Security and confidentiality
Prevent cross-school data access even when URL/record IDs are guessed. Sensitive school/guardian documents remain private. Avoid broad student-data exposure in payment links. Audit changes and access to sensitive operations. Protect credentials, webhooks, uploads, backups, and restore processes.

## 7. Target user interfaces

**Public marketing website:** product explanation, benefits, demo/contact (optional early stage).

**School portal:** read-only dashboard, assigned debt portfolio/status, case timeline (approved public events only), agreements and receipts, commission monthly statement, exports.

**Internal operations panel:**
- Collector: today's tasks, assigned debt list, case detail, contact history, next steps, negotiation and agreement approvals.
- Legal: document completeness, Serasa reviews, notices, deadlines, court cases and legal decisions.
- Finance: incoming payments, reconciliations, external school receipts, attribution review queue, disputed commissions, monthly closing and school invoices.
- Manager: school portfolio, team performance, assignment and approval queues, operational dashboards.
- Superadmin: platform settings, school onboarding, users/permissions, contract versions, integrations, job/webhook/audit monitoring.

The earlier UI mockups were prototypes with fictional records and **are not evidence of completed code**.

## 8. Recommended implementation approach with Laravel + Filament

- Start with **one Laravel 13 modular monolith** (do not build multiple backend services or separate React application by default).
- Filament 5 + Livewire + Blade for authenticated back-office and school portal. Reuse Laravel Eloquent models, services, authorization policies and database transactions.
- SQLite for current local development. Test constraints, decimal arithmetic, concurrency and migrations with the production database choice before go-live.
- Use `Form`/`Table`/`Resource` classes and Filament panels according to installed Filament 5 structure.
- Put money/attribution/business state changes in dedicated application/domain services, not Filament resource callbacks alone. Add automated unit/feature tests for those services.
- Keep provider integration behind an adapter/interface and validate webhook authenticity; store webhook event idempotency keys with unique constraints.
- Use private disk for contracts/evidence and asynchronous queue processing for heavy imports and notifications.
- Money amounts: integer minor units or exact decimal SQL columns/decimal arithmetic, consistent rounding and currency. No floating point currency calculations.
- All external integrations, bank statement operations, Serasa, messaging, and judicial services require provider/legal validation before activation.

## 9. Current progress (reported, verify in repo)

User reported, in sequence:

1. Created a **Laravel** project for BomTrato.
2. Ran `composer install` and `npm install` (user called it "nom install").
3. Verified `php artisan --version` reports **Laravel Framework 13.39.0**.
4. Chose **SQLite for now**, rather than PostgreSQL.
5. Followed instructions for SQLite configuration/migrations and **Filament 5 installation/admin user**; answered "done, what's next?". It is reasonable to expect Filament installation may be complete, but **verify**.
6. Was most recently instructed to create the **School** model, migration, and Filament resource, then test CRUD. **User has NOT confirmed completing that step.** Check repo before continuing. The previous suggested starter fields were: `name`, `legal_name`, `cnpj` (unique 14-char string), `email`, `phone`, `address`, `is_active`, `timestamps`, `softDeletes`.

**Next task:** Inspect actual repository. If Schools CRUD not implemented, finish it and test create/edit/list; if already implemented, review schema shortcomings (especially school vs. legal entity), then build maintaining/legal entities and versioned commercial contracts. Keep the project moving one verified feature at a time.

## 10. Full development roadmap (one ordered implementation path)

1. **Foundation:** confirm PHP/Laravel/Filament installation; SQLite environment; Git, migrations, test setup; queue/scheduler/storage strategy.
2. **Auth and roles:** admin account, roles & policies, server-side school data scoping; audit framework.
3. **School and legal entity management:** schools, mantenedoras/receiving entities, contacts, provider onboarding references.
4. **Commercial contract versioning:** configurable commission rules, attribution windows/evidence/components/provider fees, approval roles, monthly billing.
5. **Receivables:** students, guardians, links, enrollment contracts, original debt installments, validation and import with preview/duplicates.
6. **Assignments and collector workflow:** assignments, case pages, contact logs, outcomes, reminders, evidence, disputed/suspended cases.
7. **Negotiations:** payment promises, offers, authorization limits, accepted agreements, scheduled installments, immutable original debts.
8. **Pix and boleto:** sandbox PSP selection/setup; per-beneficiary charge creation; payer pages; verified webhooks; reconciliation, partial/external payments, reversals.
9. **Attribution:** evaluate each recovered payment allocation against the governing contract version and evidence; direct-payment manual approval; disputes and protected review queues.
10. **Commission accounting:** exact calculations, ledger entries, reversals/adjustments, withholding contested items.
11. **Monthly school billing:** close period, statements, invoices, school-to-office payment tracking, no duplicate entries.
12. **Legal case tools:** Serasa review/authorization, manual extrajudicial notices and judicial case tracking; integrations later.
13. **Portals and reporting:** collector/legal/finance/manager/superadmin + read-only school view, exports, performance and finance dashboards. Build basic forms during earlier phases rather than postponing all UIs.
14. **Hardening:** feature/unit/E2E tests for cross-school access, idempotency, refunds, attribution, disputes, concurrent allocations, backup restore, LGPD precautions, auditability.
15. **Pilot and production:** contract/legal/accounting validation, PSP onboarding, monitored pilot with fictional/sandbox testing first, then controlled real school rollout subject to authorization.

### V1 definition of done
A school with verified authorized receiving beneficiary assigns a legitimate overdue tuition installment; the office verifies and assigns it; a collector records documented interaction and negotiates an agreement; BomTrato generates a Pix/boleto in provider sandbox; the payer pays; verified notification reconciles the transaction exactly once and updates installment balances; the attribution engine produces an evidence-linked contract-specific decision; only approved eligible recovery generates a commission entry; the monthly school invoice lists that commission; the school can inspect permitted progress and financial statement **without modifying operations**. Include external school payments, manual attribution approval, disputed fee hold, adjustments and full audit history in V1.

## 11. Acceptance and test priorities

- Unauthenticated or unauthorized actor cannot access debt data; School A cannot access School B, even by forged IDs.
- A verified imported debt isn't duplicated; a paid/contested debt is not pursued without investigation.
- Promise/agreement/payment are distinct states.
- Repeated webhook or retry creates exactly one receipt/commission.
- Partially paid obligations retain accurate remaining balance.
- A payment covering multiple obligations is allocated correctly and never over-counted.
- Direct school payments are **manually reviewed** for commission, regardless of evidence strength.
- Payment before assignment/outside contract attribution criteria earns no automatic commission.
- Contract rule changes don't alter approved historic calculations without adjustment/audit.
- Disputed commission remains excluded from invoice.
- Refund after invoice creates auditable negative correction or credit rather than deletion.
- Month-end closing can be retried safely.
- No real money is handled without payment provider, contract, legal and accounting confirmation.

## 12. Suggested immediate agent response/action

1. State which repository files and package versions you verified.
2. Check whether the `schools` migration/model/Filament resource exist and whether Filament panel works.
3. If not yet implemented, implement a minimal safe School CRUD and migrations with basic validation, then run tests. If already done, review and proceed to legal entities/mantenedoras.
4. List exact files touched, commands run, test results, and next smallest step.
5. Do not assume marketing/UI prototype code exists locally; use mockups only as directional design.

---

**Terminology:** `BomTrato` = product; *escola* = customer institution; *mantenedora* = legal maintaining entity; *responsável financeiro* = debtor/payer; *cobrador* = collection agent; *honorários/comissão de êxito* = fee earned under applicable contract; *atribuição* = contractual evidence-based causation of recovery; *baixa* = confirmed settlement posting; *prestação de contas* = school financial statement.

