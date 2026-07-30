# Migration Strategy

| Field | Value |
|-------|--------|
| **Title** | Migration Strategy |
| **Document ID** | `011` |
| **Version** | `0.2.0` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** · **Frozen** · RA 1.0.0) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** · **Frozen** · RA 1.0.0) |
| **Engine set** | Documents `003`–`010` (**Approved** · **Frozen** · RA 1.0.0) |
| **Reference Architecture** | Wise AI Platform **1.0.0** (`001`–`011`) |

---

## 1. Document Information

This document defines the **canonical Migration Strategy** for the Wise AI Platform.

It answers one architectural question:

> **How does the current WooEasyLife implementation evolve into the Wise AI Platform without disrupting production?**

This document is **not** an architecture document that introduces new concepts.

It **maps** the current production implementation to the approved architecture defined in Documents `001`–`010`.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Documents `003`–`010`, those documents win for their own modules.
4. This document **must not** introduce new engines, artifacts, ownership, runtime concepts, or architectural layers.
5. Migration changes **implementations** and **cutover sequencing** — never architecture.

**Terminology rules:**

- Published artifacts remain exactly those frozen in Documents `002`–`010`:
  - ConversationView · KnowledgeHits · MemoryView · Decision Context · DecisionResult · LearningResult · ExecutionRequest · EvaluationResult
- **Legacy** means today’s Plugin Sales Agent production path.
- **Wise** means the target Intelligence Layer defined by Documents `001`–`010`.
- **Shadow**, **Dual Run**, **Promotion**, and **Rollback** are migration control modes — not new engines.

---

## 2. Purpose

Provide a production-safe journey from Current State to Target Architecture so that:

- Merchants keep working Messenger, Comments, license, and commerce behavior during migration
- Plugin Sales Agent capabilities are absorbed into Wise modules **incrementally**
- Ownership moves **one boundary at a time**
- EvaluationResult gates promotion before any customer-facing cutover
- Rollback to yesterday’s production remains possible at every phase
- Documents `001`–`010` remain frozen — migration realizes them; it does not redesign them

---

## 3. Scope

**In scope:**

- Migration principles and governance
- Factual Current Production Architecture (as of validation)
- Target Architecture reference (Documents `001`–`010` only)
- Gap analysis: current component → target component → migration action → status
- Migration phases with purpose, entry/exit criteria, and rollback conditions
- Feature flags, Shadow Mode, Dual Run, Promotion Gates, Rollback
- Validation strategy, migration contracts, risk management
- Success criteria and completion criteria
- Validation Notes against today’s WooEasyLife production

**Out of scope:**

- New engines, artifacts, ownership tables, or layers
- Redefinition of Documents `001`–`010`
- Implementation technology, storage engines, transport recipes, schemas, or code
- Expanding platform law beyond the frozen Reference Architecture
- Merging Blog AI or Order Intelligence into Wise without ADR (Document `001`)

---

## 4. Relationship to Documents 001–010

| Document | Role relative to Migration |
|----------|----------------------------|
| **`001` Platform Constitution** | Binding law: Evolution Principle, Current State vs Target, Evaluation Gate, fail-safe, commerce/Plugin ownership |
| **`002` Core Blueprint** | Stable module map and Execution Boundary; migration maps Sales Agent stages onto modules without reorganizing Core |
| **`003` Context Engine** | Target owner of Decision Context; migration must not invent alternate Context artifacts |
| **`004` Conversation Engine** | Target owner of ConversationView; Plugin inbox SoT preserved |
| **`005` Knowledge Engine** | Target owner of KnowledgeHits; merchant knowledge remains authoritative under Knowledge contracts |
| **`006` Memory Engine** | Target owner of MemoryView; migration separates retained continuity from Decision |
| **`007` Decision System** | Target owner of DecisionResult; Sales Agent manager maps here |
| **`008` Learning Engine** | Target owner of LearningResult; approve-gated learning maps here |
| **`009` Channel Adapter Framework** | Target owner of ExecutionRequest; Hub Meta edge + Plugin send gates map here |
| **`010` AI Evaluation Framework** | Target owner of EvaluationResult; required for Shadow, Dual Run, and Promotion Gates |

**Inheritance rule:** `001` remains Constitution. `002` remains Core Blueprint. `003`–`010` remain the complete target architecture. **`011` only explains the journey.**

---

## 5. Migration Principles

| ID | Principle | Meaning |
|----|-----------|---------|
| **MIG-P1** | **Never break production** | Default path remains Legacy until Promotion Gates pass |
| **MIG-P2** | **Incremental migration** | No big-bang rewrite of Sales Agent into Wise |
| **MIG-P3** | **One ownership change at a time** | Move one module boundary per phase; never batch ownership transfers |
| **MIG-P4** | **Runtime remains stable** | Customer-visible reply path unchanged unless Dual Run / cutover is explicitly approved |
| **MIG-P5** | **Rollback must always be possible** | Every phase answers: can yesterday’s production be restored immediately? |
| **MIG-P6** | **Evaluation before promotion** | EvaluationResult required before replacing Legacy on any customer path |
| **MIG-P7** | **Shadow before cutover** | Observational Wise path precedes Dual Run; Dual Run precedes Promotion |
| **MIG-P8** | **Feature flags first** | Every new capability is independently enableable and disableable |
| **MIG-P9** | **Extend > replace** | Absorb proven Semi/Full, confidence, pause, and Woo grounding rules — do not discard them |
| **MIG-P10** | **Architecture is frozen** | Migration never invents engines or artifacts; ADRs required for architecture change |
| **MIG-P11** | **No silent redesign** | Production gaps go to Validation Notes; they do not rewrite Current State |
| **MIG-P12** | **Commerce authority unchanged** | Plugin / WooCommerce remain price, stock, order, and operator SoT |

---

## 6. Current Production Architecture

Validated against Hub Backend and WordPress plugin discovery. This is **what exists today** — not a redesign.

### 6.1 Planes today

| Plane | Role today |
|-------|------------|
| **Hub Backend** | License/control plane; Meta Page tokens and webhook/send edge; courier and fraud planes; Blog AI (separate); anonymized Messenger intent packs; **no runtime Wise AI Core** |
| **WordPress plugin** | Commerce SoT; local Messenger/Comments inbox; **Sales Agent decision pipeline**; merchant knowledge; learning approve UX; Semi/Full send gates |
| **WooCommerce** | Catalog, price, stock, order authority |
| **Messenger / Comments** | Hub-transported Meta channels; plugin-orchestrated automation — **not** a central AI platform |
| **Conversational AI locus** | Plugin-local (Messenger Sales Agent baseline) |

### 6.2 Logical production flow (Messenger Sales Agent)

```text
Messenger (customer message)
        ↓
Hub Meta edge (receive / forward)
        ↓
Plugin inbox store
        ↓
Plugin Sales Agent (jobs → manager → workers)
        ↓
Intent / catalog / knowledge / draft stages (mixed)
        ↓
Prompt assembly (Plugin-local, optional model assist)
        ↓
Merchant Knowledge Base (Plugin product knowledge + Woo facts)
        ↓
Semi draft  or  Full auto-send (Plugin gates)
        ↓
Hub Meta edge (deliver)
        ↓
Customer
```

Parallel path: Comments uses a thinner automation subset — not a second platform brain, but also not yet a Wise adapter client.

### 6.3 What production mixes today

| Concern | Where it lives today |
|---------|----------------------|
| Channel receive / deliver | Hub Meta edge + Plugin inbox / send helpers |
| Conversation / thread continuity | Plugin contact thread state and agent turns |
| Knowledge retrieval | Plugin merchant knowledge + WooCommerce snapshots |
| Short-term continuity (“memory”) | Plugin thread state memory fields |
| Context assembly | Ad-hoc inside Sales Agent manager (no Decision Context artifact) |
| Recommendation + send gating | Same Plugin Sales Agent path (mixed Decision + Respond) |
| Learning | Merchant-approved learning queue (Plugin) |
| Quality measurement | Readiness / tests / ops checks — **no** EvaluationResult / Shadow pipeline |

### 6.4 Production constraints that must not be casually broken

- License / domain binding
- Meta inbound integrity and thin receive → process separation
- Semi / Full automation modes and Full unlock readiness
- Confidence floors, quiet hours, pause-on-human, per-contact AI pause
- WooCommerce grounding for price, stock, and order creation authority
- Merchant knowledge / learning approval UX on Plugin
- Package entitlement for Sales Agent capability

---

## 7. Target Architecture

Reference only. **Do not redefine.** Full law lives in Documents `001`–`010`.

### 7.1 Target planes

| Plane | Target role |
|-------|-------------|
| **Hub Backend** | Control plane; **hosts Wise** Intelligence Layer |
| **Wise AI Core** | Channel-agnostic reasoning and recommendation (`002`–`008`, Evaluation `010`) |
| **Channel adapters** | Translate DecisionResult → ExecutionRequest; apply channel policies (`009`) |
| **WordPress plugin** | Commerce SoT and operator Experience (inbox, Semi approve, knowledge/learning authoring) |
| **WooCommerce** | Catalog, price, stock, orders |

### 7.2 Target logical flow

```text
Channel event
        ↓
Channel Adapter (normalize inbound)
        ↓
Conversation → ConversationView
        ↓
Knowledge → KnowledgeHits
        ↓
Memory → MemoryView
        ↓
Context → Decision Context
        ↓
Decision → DecisionResult
        ↓
Learning → LearningResult (async / advisory; AC-4 gated)
        ↓
Channel Adapter → ExecutionRequest → channel delivery
        ↓
Evaluation → EvaluationResult (observe only; never mutates runtime)
```

### 7.3 Published artifacts (immutable contract set)

| Artifact | Owner document |
|----------|----------------|
| ConversationView | `004` |
| KnowledgeHits | `005` |
| MemoryView | `006` |
| Decision Context | `003` |
| DecisionResult | `007` |
| LearningResult | `008` |
| ExecutionRequest | `009` |
| EvaluationResult | `010` |

Migration **never** renames, splits, or replaces these contracts.

---

## 8. Gap Analysis

For every major current production component: map to target, name the migration action, and record status.

| Current component | Target component | Migration action | Status |
|-------------------|------------------|------------------|--------|
| Hub Meta receive / forward / send edge | Channel Adapter Framework (`009`) | **Extract** transport into Adapter Contract; keep Hub as Meta edge | Planned |
| Plugin Messenger inbound store / inbox | Conversation SoT + Adapter edge (`004` / `009`) | **Preserve** inbox SoT on Plugin; adapters do not become SoR | Planned |
| Plugin Sales Agent manager | Decision System (`007`) — Coordinator + Decision Engine | **Split** recommendation from Respond gates | Planned |
| Intent / understand workers | Decision System + Conversation framing (`007` / `004`) | **Absorb** into Decision / ConversationView production | Planned |
| Catalog / product snapshot workers | Commerce facts via Context + Plugin/Woo (`003` / `001`) | **Keep** Woo authority; feed Decision Context — do not move commerce into Wise | Planned |
| Merchant product knowledge / FAQ / objections | Knowledge Engine (`005`) | **Gradual replace** retrieval behind KnowledgeHits; authoring UX stays Plugin | Planned |
| Hub anonymized intent packs | Knowledge / Configuration inputs (`005` / `002`) | **Re-home** as approved Knowledge or Config sources — not a second brain | Planned |
| Plugin-local prompt assembly | Prompt Engine (Support, `002`) behind Decision | **Relocate** assist assembly behind Core contracts; not a new published artifact | Planned |
| Optional model assist (merchant keys today) | Model Gateway (`002`) | **Centralize** invocation behind Gateway contracts over time | Planned |
| Thread state “memory” fields | Memory Engine (`006`) | **Extract** retained continuity → MemoryView | Planned |
| Ad-hoc manager context assembly | Context Engine (`003`) | **Replace** with Decision Context publication | Planned |
| Semi draft / Full auto-send gates | Adapter + Plugin Respond edge (`009` / `001`) | **Move** application of DecisionResult to Adapter/Plugin gates; never into Core | Planned |
| Learning approve queue | Learning Engine (`008`) | **Absorb** into LearningResult with AC-4 activation | Planned |
| Mixed recommend+send on one path | Execution Boundary (`002`) | **Enforce** DecisionResult stops at Boundary; Respond outside Core | Planned |
| Readiness / smoke / fixture checks | Evaluation Framework (`010`) | **Extend** into EvaluationResult + Shadow / Dual Run | Planned |
| Comments thin automation | Channel Adapter (Comments) (`009`) | **Attach** as adapter client of Wise after Messenger path is safe | Later |
| Blog AI / Order Intelligence | Non-Wise planes (`001`) | **Leave separate** unless ADR merges | Explicit non-merge |
| Empty Hub Wise scaffold | Wise AI Core host (`001` / `002`) | **Populate** only behind flags; default remains Legacy | Planned |

**Action vocabulary (only):** Preserve · Extract · Absorb · Split · Move · Relocate · Re-home · Gradual replace · Replace · Enforce · Extend · Attach · Leave separate · Populate.

No new target components appear in this table.

---

## 9. Migration Phases

Phases are sequential for **ownership transfer**. Observability and flags may run continuously. Each phase must satisfy MIG-P5 (immediate rollback).

### Phase 0 — Production Baseline

| | |
|--|--|
| **Purpose** | Freeze factual Current State; document baseline quality and constraints |
| **Entry criteria** | Documents `001`–`010` frozen; this Migration Strategy drafted |
| **Exit criteria** | Baseline inventory accepted; production constraints listed; no runtime change |
| **Rollback conditions** | N/A (no runtime change). If documentation is wrong → correct Validation Notes, do not “fix” production |

### Phase 1 — Observability

| | |
|--|--|
| **Purpose** | Make Legacy path measurable without changing replies |
| **Entry criteria** | Phase 0 complete |
| **Exit criteria** | Correlation identity available for later Shadow matching; ops can observe Legacy outcomes; Evaluation Framework contracts understood (`010`) |
| **Rollback conditions** | Disable observability flags; Legacy reply path unchanged |

### Phase 2 — Conversation Engine

| | |
|--|--|
| **Purpose** | Publish ConversationView from existing inbox/thread facts without changing Decision ownership yet |
| **Entry criteria** | Phase 1 exit; Conversation law (`004`) referenced |
| **Exit criteria** | ConversationView produced for opted traffic; Legacy still owns recommendation + send; no ownership violation |
| **Rollback conditions** | Flag `conversation_engine` off → Legacy framing only |

### Phase 3 — Knowledge Engine

| | |
|--|--|
| **Purpose** | Serve KnowledgeHits from merchant knowledge / Woo-grounded facts behind Knowledge contracts |
| **Entry criteria** | Phase 2 exit or parallel-safe with Conversation if ownership remains separate |
| **Exit criteria** | KnowledgeHits available to Shadow Decision path; Plugin authoring UX preserved; Woo remains fact authority |
| **Rollback conditions** | Flag `knowledge_engine` off → Legacy knowledge path |

### Phase 4 — Memory Engine

| | |
|--|--|
| **Purpose** | Publish MemoryView from retained continuity; stop treating thread memory as Decision-owned state |
| **Entry criteria** | Memory law (`006`) referenced; Observability in place |
| **Exit criteria** | MemoryView published for opted traffic; Lifetime / Origin rules respected; Legacy Decision path still authoritative for customers |
| **Rollback conditions** | Flag `memory_engine` off → Legacy thread memory only |

### Phase 5 — Context Engine

| | |
|--|--|
| **Purpose** | Assemble Decision Context as the sole Decision input contract |
| **Entry criteria** | ConversationView / KnowledgeHits / MemoryView available on Shadow path (as required by Context First) |
| **Exit criteria** | Decision Context published with Context identity / fingerprint suitable for Evaluation matching; no Decision bypass of Context |
| **Rollback conditions** | Flag `context_engine` off → Legacy ad-hoc assembly for customer path |

### Phase 6 — Decision System

| | |
|--|--|
| **Purpose** | Produce DecisionResult from Decision Context; split recommendation from Respond |
| **Entry criteria** | Phase 5 exit on Shadow path; Decision law (`007`) referenced |
| **Exit criteria** | DecisionResult published; Execution Boundary respected on Wise path; customer path still Legacy unless Dual Run approved |
| **Rollback conditions** | Flag `decision_system` off → Legacy manager recommendations |

### Phase 7 — Learning Engine

| | |
|--|--|
| **Purpose** | Map approve-gated learning to LearningResult; keep activation advisory and AC-4 gated |
| **Entry criteria** | DecisionResult Shadow path exists; Learning law (`008`) referenced |
| **Exit criteria** | LearningResult publishable; never mutates in-flight DecisionResult; merchant approve remains required for activation |
| **Rollback conditions** | Flag `learning_engine` off → Legacy learning queue only |

### Phase 8 — Channel Adapter

| | |
|--|--|
| **Purpose** | Translate DecisionResult → ExecutionRequest; apply Semi/Full and transport gates at Adapter/Plugin edge |
| **Entry criteria** | DecisionResult available; Adapter law (`009`) referenced |
| **Exit criteria** | ExecutionRequest path proven in Shadow/Dual Run; adapters do not reason; commerce authority unchanged |
| **Rollback conditions** | Flag `adapter_framework` off → Legacy send/draft application |

### Phase 9 — Evaluation Framework

| | |
|--|--|
| **Purpose** | Publish EvaluationResult for Shadow and Dual Run; feed Promotion Gates |
| **Entry criteria** | Evaluation law (`010`) referenced; Signals available from Decision / Learning / Execution observations |
| **Exit criteria** | EvaluationResult immutable and historical; observational only; no runtime mutation |
| **Rollback conditions** | Flag `evaluation_framework` off → stop publishing EvaluationResult; never affects Legacy replies |

### Phase 10 — Production Cutover

| | |
|--|--|
| **Purpose** | Promote Wise path for approved scopes after gates pass |
| **Entry criteria** | Phases 2–9 exit for the cutover scope; Promotion Gates (§13) pass; Product Owner approval |
| **Exit criteria** | Customer path uses Wise DecisionResult + Adapter ExecutionRequest for approved scope; Legacy available for rollback |
| **Rollback conditions** | Flags reverse cutover immediately to Legacy; Dual Run / Shadow may remain for monitoring |

### Phase 11 — Legacy Removal

| | |
|--|--|
| **Purpose** | Retire Legacy Sales Agent runtime only after sustained Success Criteria |
| **Entry criteria** | Phase 10 stable for agreed soak period; Completion Criteria (§19) nearly met |
| **Exit criteria** | Legacy runtime removed for migrated scopes; feature flags retired; architecture matches `001`–`010` |
| **Rollback conditions** | If soak fails before removal → restore Legacy flags (Phase 10 rollback). After removal, restore only from approved recovery plan — prefer not reaching this without verified rollback artifacts |

**Phase order rule:** Do not skip Shadow (Phases 1–9 observational use) before Cutover. Learning may trail Decision but must not activate into runtime without AC-4. Evaluation may begin as soon as Signals exist, but Promotion requires EvaluationResult.

---

## 10. Feature Flags

Every new capability must be independently enableable. Flags are migration controls — not architectural modules.

| Flag | Controls |
|------|----------|
| `conversation_engine` | ConversationView publication / consumption on Wise path |
| `knowledge_engine` | KnowledgeHits publication / consumption on Wise path |
| `memory_engine` | MemoryView publication / consumption on Wise path |
| `context_engine` | Decision Context publication / Context First enforcement on Wise path |
| `decision_system` | DecisionResult publication on Wise path |
| `learning_engine` | LearningResult publication / advisory reads |
| `adapter_framework` | ExecutionRequest translation from DecisionResult |
| `evaluation_framework` | EvaluationResult publication |
| `shadow_mode` | Wise runs observationally; Legacy remains customer path |
| `dual_run` | Both paths may produce replies for comparison scope; governance consumes EvaluationResult |
| `rollback_enabled` | Guarantees Legacy path can be restored immediately |

**Rules:**

- Default for customer-facing Wise cutover flags: **off** until Promotion Gates pass
- `shadow_mode` may be on while customer path remains Legacy
- `dual_run` never implies automatic promotion
- `rollback_enabled` must remain on through Phase 10 soak
- Flag drift (undocumented combos) is a migration defect (§17)

---

## 11. Shadow Mode

Shadow Mode proves Wise without customer impact.

```text
Production request
        ↓
Legacy path → reply → Customer

AND (in parallel, observational)

Wise path → DecisionResult
        ↓
Evaluation → EvaluationResult
        ↓
Discard (no customer delivery from Wise)
```

| Rule | Detail |
|------|--------|
| **Customer path** | Legacy only |
| **Wise output** | DecisionResult (and intermediate artifacts) for measurement |
| **Evaluation** | EvaluationResult only — observational |
| **Forbidden** | Wise ExecutionRequest delivery to customer while only `shadow_mode` is on |
| **Contamination** | Shadow must not rewrite Legacy state, learning activation, or send gates |

---

## 12. Dual Run Strategy

Dual Run compares Legacy and Wise under governance. It is not auto-cutover.

```text
Production request
        ↓
Legacy → Reply (customer or controlled cohort per policy)
        ↓
Wise → Reply candidate
        ↓
Compare
        ↓
EvaluationResult
        ↓
Governance (human / Product Owner) — no automatic promotion
```

| Rule | Detail |
|------|--------|
| **No automatic promotion** | EvaluationResult informs; it never flips production alone |
| **Cohort control** | Dual Run scope is explicit (store, mode, channel, percentage) — architecture does not invent cohort tech |
| **Safety** | Fail-safe and Semi/Full gates remain enforceable |
| **Exit** | Dual Run ends in either rollback to Legacy-only or Promotion Gates → Cutover |

---

## 13. Promotion Gates

Before replacing Legacy on any customer-facing scope, require the gate chain:

```text
EvaluationResult
        ↓
Regression
        ↓
Safety
        ↓
Accuracy
        ↓
Merchant / Product Owner Approval
        ↓
Promotion (feature flags)
```

| Gate | Meaning |
|------|---------|
| **EvaluationResult** | Immutable assessment exists for the cutover scope (`010`) |
| **Regression** | Document `001` Evaluation Gate regression expectations satisfied for the scope |
| **Safety** | No increase in unsafe / ungrounded / commerce-violating recommendations |
| **Accuracy** | Equal or better decision quality vs Legacy baseline for the scope |
| **Approval** | Product Owner (and merchant policy where required) explicitly approve |
| **Promotion** | Cutover flags enabled only after the above |

**Hard rule:** Shadow disagreement alone never forces cutover (`010` / Execution Boundary).

---

## 14. Rollback Strategy

Every migration phase must answer:

> **Can yesterday’s production be restored immediately?**

If not, the migration phase is **incomplete**.

| Requirement | Detail |
|-------------|--------|
| **Immediate** | Flag reversal restores Legacy customer path without architecture change |
| **Scope** | Rollback may be global or per cutover cohort |
| **State** | Rollback must not require rewriting Documents `001`–`010` |
| **Learning** | Activated LearningHints / LearningResult reads must be disableable without corrupting Commerce |
| **Evaluation** | Turning off Evaluation never changes Legacy replies |
| **Proof** | Rollback drills are part of Phase 10 entry — not optional |

Preferred rollback order:

1. Disable cutover / `dual_run` customer delivery
2. Keep `shadow_mode` if useful
3. Disable module flags newest-first (reverse of ownership transfer)
4. Confirm Legacy Sales Agent path healthy
5. Record EvaluationResult of the rollback event for governance

---

## 15. Validation Strategy

Every phase validates all of the following before exit:

| Validation | Question |
|------------|----------|
| **Architecture compliance** | Does the phase still match Documents `001`–`010` with no new concepts? |
| **Ownership compliance** | Is only the intended ownership moved? One change at a time? |
| **Published artifacts** | Are only frozen artifacts published (ConversationView … EvaluationResult)? |
| **Evaluation quality** | Are EvaluationResults usable for gates (when Evaluation is in scope)? |
| **Regression** | Is Legacy baseline preserved for customer path until Promotion? |
| **Production stability** | Are Semi/Full, pause, Woo grounding, license, and Meta edge intact? |
| **Rollback proof** | Was immediate Legacy restore verified for this phase? |

Failed validation → fix or roll back. Do not “document away” production breakage.

---

## 16. Migration Contracts

Migration **never changes** these published artifacts:

```text
ConversationView
KnowledgeHits
MemoryView
Decision Context
DecisionResult
LearningResult
ExecutionRequest
EvaluationResult
```

| Rule | Detail |
|------|--------|
| **Stable contracts** | Implementations behind flags may change; artifact names and ownership do not |
| **Additive only** | Optional fields may be added under Minor contract evolution rules in `002`; breaking changes require ADR |
| **No dual names** | Do not reintroduce legacy aliases (e.g. EvaluationRecord) |
| **Execution Boundary** | DecisionResult remains a recommendation through all phases |
| **Evaluation** | EvaluationResult never becomes a runtime input that mutates Decide/Respond |

Migration changes **which implementation produces** an artifact — never **what the artifact means**.

---

## 17. Risk Management

| Risk | Impact | Detection | Mitigation |
|------|--------|-----------|------------|
| **Ownership confusion** | Two brains; AC-1 / module map violations | Dual writers of same artifact; undocumented owners | One ownership change per phase; Document `002` table is authority |
| **Mixed runtime** | Partial Wise + Legacy recommend on same customer turn | Flag matrix audit; reply provenance | Forbid mixed recommenders per turn; Shadow discards Wise delivery |
| **Partial migration** | Context without Decision, or Decision without Adapter gates | Phase exit checklist | Do not promote incomplete chains; Dual Run only with full Decision→Adapter path |
| **Feature flag drift** | Undocumented combos; unreproducible incidents | Flag inventory vs this document | Treat drift as defect; `rollback_enabled` always on through soak |
| **Rollback failure** | Cannot restore yesterday’s production | Failed rollback drill | Phase incomplete until drill passes (MIG-P5) |
| **Shadow contamination** | Shadow writes learning / send / commerce state | State diffs Legacy vs Shadow | Shadow read-only w.r.t. customer effects; discard Wise delivery |
| **Evaluation misuse** | EvaluationResult used to rewrite Intent or auto-cutover | Runtime dependency on Evaluation | EVAL observational law (`010`); Promotion Gates require human approval |
| **Commerce authority leak** | Wise becomes price/stock/order SoT | Ungrounded recommendations; order mutations from Core | DG-4 / Plugin-Woo authority; adapters apply only |
| **Channel fork** | New channel clones Sales Agent instead of Adapter | Parallel private agent | Attach via `009` only after Messenger migration safe |
| **Architecture thaw** | New engines/artifacts invented mid-migration | Doc diffs vs freeze | Refuse; require ADR + update `001`/`002` before any work |

---

## 18. Success Criteria

Migration is successful for a scope when:

| Criterion | Meaning |
|-----------|---------|
| **No production downtime** | Merchant Messenger / Comments / commerce paths remain available |
| **Rollback verified** | Immediate Legacy restore proven |
| **Decision quality** | Equal or better vs Legacy baseline (EvaluationResult) |
| **Execution success** | Equal or better delivery / draft application success under Semi/Full policy |
| **Grounding / hallucination** | Equal or lower ungrounded claim rate |
| **Safety** | No regression in unsafe / policy-violating recommendations |
| **Ownership** | No runtime ownership violations vs Document `002` |
| **Architecture implemented** | Scope behaves per Documents `001`–`010` |
| **Operator experience** | Semi approve, knowledge authoring, and learning approve remain usable |
| **Commerce integrity** | Woo price/stock/order authority unchanged |

Never invent fake metrics. Compare against Phase 0 baseline and EvaluationResult trends.

---

## 19. Completion Criteria

Migration is **complete** when all of the following are true:

1. Legacy Sales Agent runtime removed for migrated conversational scopes (or explicitly retained only as Adapter/Plugin Respond edge — not as a second Decision brain)
2. Migration feature flags for cutover retired or reduced to permanent platform configuration without dual brains
3. Runtime architecture matches Documents `001`–`010`
4. Evaluation confirms readiness under Document `001` Evaluation Gate
5. No runtime ownership violations
6. Shadow contamination and Evaluation misuse risks closed for production path
7. Product Owner accepts Completion

Until then, Legacy remains the safety net.

---

## 20. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Constitution; Current State; Evolution; Evaluation Gate |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Module map; Execution Boundary; replaceability |
| [`003-context-engine.md`](003-context-engine.md) | Decision Context |
| [`004-conversation-engine.md`](004-conversation-engine.md) | ConversationView |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | KnowledgeHits |
| [`006-memory-engine.md`](006-memory-engine.md) | MemoryView |
| [`007-decision-system.md`](007-decision-system.md) | DecisionResult |
| [`008-learning-engine.md`](008-learning-engine.md) | LearningResult |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | ExecutionRequest |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | EvaluationResult; Shadow observation |
| `docs/adr/*` | Required for any architecture change during migration |
| `docs/requirements/*` | Acceptance criteria and evaluation cases (when authored) |

---

## 21. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: migration-only bridge from Plugin Sales Agent Current State to frozen Documents `001`–`010`; phases 0–11; flags; Shadow; Dual Run; Promotion Gates; Validation Notes |
| 0.2.0 | 2026-07-30 | Documentation Lead | Appendix A Migration Timeline Matrix; **Status → Approved**; included in Reference Architecture **1.0.0** freeze (`001`–`011`) |

---

## 22. Validation Notes

Validated against Documents `001`–`010`, Hub Backend, and WordPress plugin Sales Agent discovery. **No production redesign.** Assumptions are explicit.

### Conflict V-011-1 — Conversational AI still Plugin-local

| | |
|--|--|
| **Conflict** | Target hosts Wise on Hub; production Sales Agent brain runs on Plugin. |
| **Risk** | Teams treat Document `011` as permission to flip default path immediately. |
| **Suggested resolution** | Default remains Legacy until Phase 10 gates pass; Hub Wise populates only behind flags (MIG-P1, MIG-P8). |

### Conflict V-011-2 — Mixed recommend + send on one path

| | |
|--|--|
| **Conflict** | Plugin Sales Agent manager both recommends and applies Semi/Full send gates. |
| **Risk** | “Migrating Decision” silently moves Respond into Core. |
| **Suggested resolution** | Phase 6 publishes DecisionResult only; Phase 8 moves application to Adapter/Plugin edge (Execution Boundary). |

### Conflict V-011-3 — No production Shadow / EvaluationResult today

| | |
|--|--|
| **Conflict** | Architecture defines Shadow and EvaluationResult; production has readiness/tests, not Evaluation Framework. |
| **Risk** | Cutover without observational proof. |
| **Suggested resolution** | Phases 1 and 9 are mandatory before Phase 10; Evaluation misuse forbidden (`010`). |

### Conflict V-011-4 — Knowledge is Plugin merchant knowledge, not an external sheet store

| | |
|--|--|
| **Conflict** | Informal migration sketches sometimes assume a Google Sheet KB. |
| **Risk** | Wrong cutover plan; inventing a parallel knowledge plane. |
| **Resolution** | Current Knowledge source is Plugin merchant product knowledge + WooCommerce facts (+ optional Hub intent packs). Migrate behind KnowledgeHits (`005`); do not invent a sheet-based architecture. |

### Conflict V-011-5 — Prompt assembly is Plugin-local today

| | |
|--|--|
| **Conflict** | Target Prompt Engine sits in Core Support (`002`); production prompt assembly is inside Sales Agent. |
| **Risk** | Treating “Prompt Builder” as a separate published artifact or new engine. |
| **Suggested resolution** | Relocate assist assembly behind Prompt Engine / Model Gateway contracts; published artifacts remain the frozen set only. |

### Conflict V-011-6 — Comments is a parallel thin path

| | |
|--|--|
| **Conflict** | Comments automation exists beside Messenger Sales Agent. |
| **Risk** | Migrating Comments first forks effort; or ignoring it creates a leftover brain. |
| **Suggested resolution** | Messenger scope first; attach Comments as Adapter client after Messenger Shadow/Cutover is safe (`009`). |

### Conflict V-011-7 — Blog AI / Order Intelligence are separate planes

| | |
|--|--|
| **Conflict** | Hub already hosts non-Wise AI planes. |
| **Risk** | Migration accidentally merges them into Wise. |
| **Suggested resolution** | Leave separate per Document `001` unless ADR merges (Gap Analysis: Leave separate). |

No production paths were redesigned to resolve these conflicts.

---

## Appendix A — Migration Timeline Matrix

One-page implementer roadmap. **Does not change architecture** — readiness states only.

| Phase | Conversation | Knowledge | Memory | Context | Decision | Learning | Adapter | Evaluation |
|-------|--------------|-----------|--------|---------|----------|----------|---------|------------|
| **0** | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy |
| **1** | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy | Observe |
| **2** | Wise | Legacy | Legacy | Legacy | Legacy | Legacy | Legacy | Observe |
| **3** | Wise | Wise | Legacy | Legacy | Legacy | Legacy | Legacy | Observe |
| **4** | Wise | Wise | Wise | Legacy | Legacy | Legacy | Legacy | Observe |
| **5** | Wise | Wise | Wise | Wise | Legacy | Legacy | Legacy | Observe |
| **6** | Wise | Wise | Wise | Wise | Wise | Legacy | Legacy | Observe |
| **7** | Wise | Wise | Wise | Wise | Wise | Wise | Legacy | Observe |
| **8** | Wise | Wise | Wise | Wise | Wise | Wise | Wise | Observe |
| **9** | Wise | Wise | Wise | Wise | Wise | Wise | Wise | Evaluate |
| **10** | Wise | Wise | Wise | Wise | Wise | Wise | Wise | Gate |
| **11** | Wise | Wise | Wise | Wise | Wise | Wise | Wise | Complete |

| Cell | Meaning |
|------|---------|
| **Legacy** | Customer path still uses today’s Plugin Sales Agent implementation for that concern |
| **Wise** | Wise module path publishes/consumes the frozen artifact for that concern (often Shadow until Phase 10) |
| **Observe** | Evaluation / observability scaffolding — no EvaluationResult-gated promotion yet |
| **Evaluate** | EvaluationResult published for Shadow / Dual Run |
| **Gate** | Promotion Gates consume EvaluationResult for cutover |
| **Complete** | Migration complete for Evaluation role; flags retired per Completion Criteria |

Customer delivery remains **Legacy** until Phase **10** Promotion (MIG-P1, MIG-P7). Cell **Wise** before Phase 10 does not imply customer cutover.
