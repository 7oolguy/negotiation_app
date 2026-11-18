# BomTrato — Flow-State Build Roadmap (`TODO.md`)

> **How to use this file:** Work top-to-bottom. Each step is a small, complete slice (migration → model/relations → service/policy → Filament UI → test) that unlocks the next phase without rework.
> **Rule:** Never invent commercial contract rates/windows; keep them configurable. Keep all money in integer minor units (centavos) or exact decimal columns.

---

## Phase 0 — Foundation & Aesthetic
*Get the rails clean so every feature drops into place.*

- [x] Laravel 13 (`13.39.0`) + SQLite local environment configured
- [x] Filament 5 (`v5.11.0`) installed with `/admin` panel
- [x] Minimalist Black & White theme applied (`AdminPanelProvider.php` + `resources/css/app.css`)
- [x] Set app locale/timezone to `pt_BR` & `America/Sao_Paulo` (`config/app.php`) and verify Brazilian formatting helpers (BRL, `dd/mm/yyyy`, CPF/CNPJ)
- [x] Configure `private` filesystem disk strategy for sensitive documents/contracts/evidence (`config/filesystems.php`)

---

## Phase 1 — Institutional Core (`LegalEntity` & `School`)
*Finish and verify the base entities before attaching people, contracts, or debts.*

- [x] `schools` migration, `legal_entities` migration, and `legal_entity_id` foreign key migration
- [x] `LegalEntity` and `School` models with `SoftDeletes`, `$fillable`, `$casts`, and `hasMany` / `belongsTo` relations
- [x] Audit & polish `LegalEntityResource` (pt-BR labels, CNPJ mask/validation, active status toggle, `SchoolsRelationManager`)
- [x] Audit & polish `SchoolResource` (select `legal_entity_id`, pt-BR labels, CNPJ/CEP/UF fields, status badge)
- [x] Add feature tests for `LegalEntity` & `School` CRUD + relationship constraints

---

## Phase 2 — Auth, Roles & School Scoping
*Lock down who sees what before any student or debt data exists.*

- [x] Define roles (`superadmin`, `manager`, `collector`, `legal`, `finance`, `school_viewer`) and `user_school` pivot/scoping table
- [x] Build server-side Laravel `Policies` and global/query scopes so `school_viewer` only ever sees their assigned school(s) and is strictly read-only
- [x] Set up `audit_logs` table + trait/service to record sensitive state changes, approvals, and access
- [x] Add Filament `UserResource` for role & school assignment (restricted to `superadmin` / `manager`)
- [x] Write cross-school isolation test (School A user cannot view/forge IDs for School B)

---

## Phase 3 — Commercial Contracts & Versioning
*Define how BomTrato gets paid per school/mantenedora without hardcoding rules.*

- [x] Create `commercial_contracts` and immutable `contract_versions` migrations & models (rate structure, attribution window days, eligible components, PSP fee treatment, billing closing day, approval rules — all nullable/explicitly configured)
- [x] Build contract versioning service (editing rules creates a new effective version; historic approved calculations stay tied to their original `contract_version_id`)
- [x] Create Filament `CommercialContractResource` with version history relation manager
- [x] Add unit tests verifying contract version immutability

---

## Phase 4 — Receivables (Students, Guardians & Debts)
*Bring the overdue tuition data in cleanly and without duplicates.*

- [x] `students`, `guardians`, and `student_guardians` (many-to-many with responsibility type) migrations & models
- [x] `enrollment_contracts`, `debts`, and `debt_installments` migrations & models (using minor-unit integer centavos for amounts; separate lifecycle status from flags like `disputed`, `suspended`, `under_payment_verification`)
- [x] Build Filament resources for `GuardianResource`, `StudentResource`, and `DebtResource` (with duplicate CPF/CNPJ search & warning)
- [x] Build Debt Import Service + Filament import action (CSV/Excel preview, validation, duplicate detection, `import_jobs` audit log)
- [x] Add feature tests for duplicate prevention and guardian-to-multiple-students mapping

---

## Phase 5 — Assignments & Collector Workflow
*Put debts into collectors' hands and track every respectful touchpoint.*

- [x] Create `debt_assignments` (collector, assigned_at, ended_at), `collection_actions` (channel, outcome, notes, `is_internal` flag, evidence attachment on private disk), and `collection_tasks` (follow-up reminders)
- [x] Build Debt Lifecycle Service (enforce transitions like `IMPORTED → PENDING_VERIFICATION → READY_FOR_COLLECTION → ACTIVE_COLLECTION`, plus `disputed`/`suspended` holds)
- [x] Build Collector Workspace in Filament: "Today's Tasks" widget, assigned portfolio table, case detail view with action timeline & quick-log action
- [x] Add tests ensuring internal notes are flagged/excluded from school-facing queries and disputed debts pause collection

---

## Phase 6 — Negotiations & Agreements
*Turn active collections into structured, trackable agreements.*

- [ ] Create `agreements` and `agreement_installments` migrations & models (original principal/fees, authorized discounts, entry payment, installment schedule, acceptance evidence)
- [ ] Build Agreement Service: enforce state separation between **Promise**, **Signed Agreement**, and **Confirmed Payment**; keep original `debt_installments` immutable
- [ ] Add Agreement builder/approval flow inside the Debt case view (respecting school-authorized discount/installment limits)
- [ ] Add tests confirming promises/agreements alone never mark a debt settled or trigger commissions

---

## Phase 7 — Payments & Reconciliation (Option A: Direct to School)
*Issue Pix/boleto to the school's beneficiary account and reconcile every cent idempotently.*

- [ ] Create `school_payment_accounts` (PSP onboarding ref, beneficiary verification status per school/legal entity)
- [ ] Define `PaymentProviderInterface` adapter (sandbox-first; no live credentials)
- [ ] Create `payment_charges`, `provider_webhook_events` (unique idempotency key), `payment_transactions`, `payment_allocations`, and `external_payment_reports`
- [ ] Build Payment Reconciliation Service (db transactions: distinguish charge created ≠ confirmed ≠ settled; handle partial payments, multi-debt allocations, and reversals/refunds safely)
- [ ] Build Payer Payment Page (minimalist, reassuring public route showing only specific payment instructions/Pix/boleto — zero admin/student data leak)
- [ ] Add tests for repeated/out-of-order webhooks, partial balances, and multi-debt allocation math

---

## Phase 8 — Attribution, Commission Ledger & Monthly Billing
*Prove collection attribution, hold disputes, and invoice schools accurately.*

- [ ] Create `attribution_decisions`, `attribution_evidence`, and `commission_disputes` migrations & models
- [ ] Build Attribution Engine Service: evaluate verified `payment_allocations` against governing `contract_version` + documented `collection_actions`; enforce **manual approval required** for direct/external school payments
- [ ] Create immutable `commission_entries` ledger (reversals/refunds create compensating negative entries, never deletes)
- [ ] Create `commission_invoices` and `commission_invoice_lines` + Monthly Closing Service (idempotent retry, excludes `disputed` items on hold, prevents double-invoicing)
- [ ] Build Finance Review Queue in Filament (external payments approval, disputed commissions, monthly closing & invoice export)
- [ ] Add comprehensive unit/feature tests for every commission invariant

---

## Phase 9 — Legal Escalation (Serasa, Extrajudicial, Judicial)
*Handle cases that don't resolve in friendly collection.*

- [ ] Create `serasa_records` (analysis, supporting evidence, approval, inclusion/exclusion dates, or justification for skipping)
- [ ] Create `extrajudicial_notices` (document checklist, notice generation/upload, delivery tracking, response deadline)
- [ ] Create `judicial_cases` (explicitly separate `judicial_review` from actual `court_filing`, lawyer, court number, deadlines, expenses, hearings, closure)
- [ ] Build Legal Workspace in Filament (pending reviews, expiring deadlines widget)

---

## Phase 10 — School Portal & Reporting
*Give schools radical transparency without letting them touch operations.*

- [ ] Create read-only School Portal view/panel (scoped strictly to user's school(s), `is_internal` notes stripped)
- [ ] School dashboard: recovered vs. negotiated vs. open amounts, confirmed receipts, agreement progress
- [ ] Monthly Financial Statement (*Prestação de Contas*) & Commission Invoice viewer + CSV/PDF export
- [ ] Operational & Productivity reports for Managers (collections by channel, recovery rates, aging)

---

## Phase 11 — Hardening & Pilot Readiness
*Verify security, math, and LGPD compliance before real data.*

- [ ] Full authorization & cross-school penetration test suite
- [ ] Concurrency & idempotency tests for payment allocations and month-end closing
- [ ] Verify private disk downloads use signed/authorized controller routes (no public storage leaks)
- [ ] Backup & restore dry-run on SQLite / target production DB
- [ ] Legal & accounting sign-off on commercial contract rules + PSP sandbox-to-production checklist
- [ ] Implement `FilamentUser::canAccessPanel()` on `User` (block inactive/unassigned users from `/admin` before production)

---

## Backlog — Team / Process (not scheduled)

- [ ] Simulated-user agents: AIs that act as real users (collector, manager, finance, legal, school_viewer), each with their own account/login, working through the app like in real life (realistic sessions, not scripted tests) to surface usability, permission and data-leak issues
