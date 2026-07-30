# AI Evaluation Framework

| Field | Value |
|-------|--------|
| **Title** | AI Evaluation Framework |
| **Document ID** | `010` |
| **Version** | `0.1.2` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.7) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.7) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.6) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.5) |
| **Knowledge Engine** | [`005-knowledge-engine.md`](005-knowledge-engine.md) (**Draft** v0.1.7) |
| **Memory Engine** | [`006-memory-engine.md`](006-memory-engine.md) (**Approved** v0.2.4) |
| **Decision System** | [`007-decision-system.md`](007-decision-system.md) (**Draft** v0.1.3) |
| **Learning Engine** | [`008-learning-engine.md`](008-learning-engine.md) (**Draft** v0.1.2) |
| **Channel Adapter Framework** | [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) (**Draft** v0.1.1) |

---

## 1. Document Information

This document defines the **canonical AI Evaluation Framework** for the Wise AI Platform.

It answers one architectural question:

> **How does Wise measure the quality of Decisions, Learning, and Execution without affecting runtime behavior?**

It deepens **only** the AI Evaluation Framework: Evaluation behavior, EvaluationResult as the published artifact, Signals, Metrics, Policy, Evidence, Score, Findings, ownership, boundaries, invariants, quality attributes, and the relationship to runtime.

It does **not** redefine Documents `001`–`009`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. If any statement here conflicts with Document `005`’s Knowledge law, **Document `005` wins**.
6. If any statement here conflicts with Document `006`’s Memory law, **Document `006` wins**.
7. If any statement here conflicts with Document `007`’s Decision law, **Document `007` wins**.
8. If any statement here conflicts with Document `008`’s Learning law, **Document `008` wins**.
9. If any statement here conflicts with Document `009`’s Adapter law, **Document `009` wins**.
10. This document must not reorganize Wise Core or redefine runtime published artifacts.

**Terminology rules:**

- Throughout this document, **EvaluationResult** means the published artifact of Evaluation unless explicitly stated otherwise.
- Document `002` owns **EvaluationResult** (Decision Assessment / shadow / regression artifact) — same published-artifact convention as Documents `003`–`009`.
- **Evaluation** (this Framework) is distinct from **Decision Evaluation** (`007`) and **Learning Evaluation** (`008`).
- Evaluation Framework **owns Evaluation / EvaluationResult** — never DecisionResult, LearningResult, ExecutionRequest, ExecutionResult, Context, Conversation, Knowledge, Memory, or Commerce.
- Evaluation **observes** the platform. It **never participates in runtime execution** and **never changes runtime behavior**.

**Out of scope:** implementation, storage engines, Migration playbooks (`011`), and any technology that would redefine architectural Evaluation as an implementation plan.

---

## 2. Purpose

Provide a production-grade architectural definition of quality measurement so that:

- Wise can assess **Decision**, **Learning**, and **Execution** quality without mutating live paths
- Document `001` **Evaluation Gate** (Acceptance Criteria · Evaluation Cases · Regression Tests) has a home before production rollout
- Shadow / dual-run comparison against Plugin Sales Agent remains **observational** (`001` / `002`)
- Governance, reporting, and Migration (`011`) consume immutable **EvaluationResult**
- Runtime engines retain exclusive ownership of their published artifacts

---

## 3. Scope

**In scope:**

- AI Evaluation Framework role (Evaluation Engine / AI Infrastructure per Document `002`)
- Core concepts: Evaluation, Evaluation Signal, Evaluation Metric, Evaluation Policy, Evaluation Evidence, Evaluation Score, Evaluation Finding, EvaluationResult
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to runtime artifacts (`007`–`009`) and Evaluation Gate (`001`)
- Validation Notes against production reality

**Out of scope:**

- Owning or mutating DecisionResult / LearningResult / ExecutionRequest / ExecutionResult
- Runtime Decision Evaluation or Learning Evaluation
- Channel Translation or commerce authority
- Implementation of scoring suites or storage

---

## 4. Relationship to Documents 001–009

| Inherited concept | How Document `010` uses it |
|-------------------|----------------------------|
| **Evaluation Gate** (`001`) | Acceptance Criteria · Evaluation Cases · Regression Tests before production rollout |
| **Shadow / dual-run** (`001` / `002`) | Observational comparison; does not rewrite production Decisions |
| **Evaluation Engine** (`002`) | AI Infrastructure; owns Decision Assessment → EvaluationResult |
| **Context Fingerprint** (`003`) | Preferred correlation key for matched comparisons |
| **DecisionResult** (`007`) | Evaluation Signal — never mutated |
| **LearningResult** (`008`) | Evaluation Signal — never mutated |
| **ExecutionRequest / ExecutionResult** (`009`) | Evaluation Signals — never mutated |

```text
Document 001 — Evaluation Gate / shadow preference
        ↓
Document 002 — Evaluation Engine owns Decision Assessment
        ↓
Documents 003–006 — Context / information artifacts (read for correlation only)
        ↓
Documents 007–009 — runtime published artifacts (observed Signals)
        ↓
Document 010 — this document (deepens AI Evaluation Framework only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| EVAL-1 | Perform **Evaluation** and **publish EvaluationResult** from Evaluation Signals |
| EVAL-2 | Own **EvaluationResult** only — never runtime Decision / Learning / Execution artifacts |
| EVAL-3 | Remain **observational** and **asynchronous** relative to runtime |
| EVAL-4 | Never change DecisionResult, LearningResult, ExecutionRequest, or ExecutionResult |
| EVAL-5 | Support Evaluation Gate and shadow comparison without forcing production sends |
| EVAL-6 | Require **Evidence** and **Policy** before Metrics and Findings |
| EVAL-7 | Treat EvaluationResult as **immutable** and historical |
| EVAL-8 | Keep Evaluation **bounded**, **channel-neutral**, and **explainable** |
| EVAL-9 | Remain **replaceable** behind the EvaluationResult contract |
| EVAL-10 | Never cross the Execution Boundary |

---

## 5A. Evaluation Invariants

Document `001` has Platform Invariants. Documents `003`–`009` have engine / adapter invariants. The following are the **laws of the AI Evaluation Framework**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **EVAL-INV-1** | Evaluation Framework owns **EvaluationResult**. |
| **EVAL-INV-2** | Evaluation **never changes DecisionResult**. |
| **EVAL-INV-3** | Evaluation **never changes LearningResult**. |
| **EVAL-INV-4** | Evaluation **never changes ExecutionRequest**. |
| **EVAL-INV-5** | Evaluation **never changes ExecutionResult**. |
| **EVAL-INV-6** | Evaluation is **asynchronous**. |
| **EVAL-INV-7** | Evaluation is **observational**. |
| **EVAL-INV-8** | EvaluationResult is **immutable**. |
| **EVAL-INV-9** | Evaluation is **bounded**. |
| **EVAL-INV-10** | Evaluation **never crosses the Execution Boundary**. |

**Notes:**

- **EVAL-INV-1** — Evaluation Engine / Framework owns EvaluationResult (Document `002` ownership table).
- **EVAL-INV-7** — Evaluation may compare Wise vs Plugin baseline in shadow mode; it must not force production lifecycle or send paths (`002` fail-safe).
- **Distinct from Decision / Learning Evaluation** — those create runtime recommendations / advisory Learning; this Framework only measures.

---

## 5B. Evaluation Quality Attributes

Not new platform requirements. Qualities good Evaluation / EvaluationResult should exhibit.

| Attribute | Meaning for Evaluation |
|-----------|------------------------|
| **Accuracy** | Findings and Scores reflect Evidence without fabrication |
| **Fairness** | Comparisons apply consistent Policy across matched cases |
| **Explainability** | Metrics and Findings are attributable to Evidence |
| **Repeatability** | Same Signals + pinned Evaluation Policy → reasonably identical EvaluationResult |
| **Traceability** | EvaluationResult ID correlates to Context Fingerprint / DecisionResult / LearningResult / Execution IDs |
| **Reliability** | Incomplete Evidence yields Incomplete / Need More Evidence — not invented Scores |
| **Completeness** | Coverage of required Metrics for the Evaluation Case is signaled |
| **Safety** | Evaluation never forces unsafe production Actions |
| **Stability** | Suites do not thrash Findings without Evidence change |
| **Boundedness** | Finite Signals / Metrics per Evaluation — never unbounded historical mining as law |

---

## 6. Core Concepts

### 6.1 Evaluation

**Evaluation** is the architectural capability that **measures platform quality**.

Evaluation is **not**:

| Not Evaluation (this Framework) | Why / owner |
|---------------------------------|-------------|
| **Decision Evaluation** | Creates DecisionResult (`007`) |
| **Learning Evaluation** | Creates LearningResult (`008`) |
| **Channel Translation** | Creates ExecutionRequest (`009`) |
| **Runtime execution** | Adapters / Plugin / commerce |
| **Governance approval UX** | Plugin / administration (consumes Findings) |

### 6.2 Evaluation Signal

**Evaluation Signal** is a published artifact or observed outcome available for assessment.

Illustrative Signals (non-exhaustive):

| Signal | Meaning (conceptual) |
|--------|----------------------|
| **DecisionResult** | Published recommendation (`007`) |
| **LearningResult** | Advisory Learning outcome (`008`) |
| **ExecutionResult** | Observed channel application outcome (`009`) |
| **Merchant feedback** | Operator ratings, edits, overrides |
| **Customer outcome** | Post-hoc outcome markers suitable for quality assessment |
| **Human review** | Reviewer judgments for Evaluation Cases |

Signals are **read-only inputs**. Evaluation never mutates them.

### 6.3 Evaluation Metric

**Evaluation Metric** is a conceptual measurement dimension.

Illustrative Metrics (non-exhaustive):

| Metric | Meaning (conceptual) |
|--------|----------------------|
| **Decision Quality** | Fitness of recommendation relative to matched Context / baseline |
| **Policy Compliance** | Adherence to Decision / Channel / Learning Policy signals |
| **Grounding Quality** | Integrity of grounded claims vs invented-claim risk |
| **Safety Compliance** | Safety / escalation posture integrity |
| **Learning Effectiveness** | Quality of LearningResult recommendations (not auto-activation) |
| **Execution Success** | Delivery / draft / gate outcomes vs intended Action shape |
| **Merchant Satisfaction** | Operator feedback dimensions when available |

Metric vocabulary is illustrative and additive.

### 6.4 Evaluation Policy

**Evaluation Policy** is the set of architectural rules governing Evaluation: required Evidence, comparison windows, shadow rules, fail-closed incompleteness, and Capability / Configuration gates.

Policy is applied **before** Metrics and Findings.

Evaluation must never invent policy.

### 6.5 Evaluation Evidence

**Evaluation Evidence** is supporting information for Findings: attributable Signals, Context Fingerprint matches, baseline Decision correlations, and sufficiency markers.

Evidence must not be fabricated.

### 6.6 Evaluation Score

**Evaluation Score** is a conceptual assessment value (or set of values) attached to Metrics under Evaluation Policy.

Scores without Evidence are forbidden.

### 6.7 Evaluation Finding

**Evaluation Finding** is an architectural conclusion derived from Evaluation (pass, regress, drift, Incomplete, Need More Evidence, policy Rejected, etc.).

Findings are advisory for governance and Migration — not runtime mutation authority.

### 6.8 EvaluationResult

**EvaluationResult** is the **published artifact** produced by Evaluation.

Document `002` ownership (deepened):

| Module | Owns |
|--------|------|
| **Evaluation Engine** | **Decision Assessment** → published as **EvaluationResult** |

EvaluationResult conceptually contains:

- **EvaluationResult ID** (unique per publication)
- **Metrics**
- **Findings**
- **Evidence**
- **Score**
- **Policy signals**

**EvaluationResult never changes runtime behavior.**

**EvaluationResult is NOT:**

| Not EvaluationResult | Owner / doc |
|----------------------|-------------|
| DecisionResult | Decision System (`007`) |
| LearningResult | Learning Engine (`008`) |
| ExecutionRequest / ExecutionResult | Adapter Framework (`009`) |
| Decision Context | Context Engine (`003`) |
| Runtime send / commerce commit | Adapters / Plugin / Woo |

### 6.9 Concept stack (one question each)

```text
Evaluation
  ↓ Signal   — What published outcomes are available?
  ↓ Policy   — What rules govern assessment?
  ↓ Metric   — What dimensions are measured?
  ↓ Evidence — What supports the Scores/Findings?
  ↓ Result   — What immutable EvaluationResult is published?
```

---

## 7. Evaluation Framework Role

| | |
|--|--|
| **Classification** (`002`) | AI Infrastructure — Evaluation Engine |
| **Purpose** | Measure quality of Decisions, Learning, and Execution without affecting runtime |
| **Primary behavior** | **Evaluation** |
| **Primary output** | Published **EvaluationResult** |
| **Pipeline position** | Asynchronous observer; optional shadow/regression hooks — never on Execution path |

### 7.1 Responsibilities

- Consume **Evaluation Signals** (read-only)
- Validate Evidence sufficiency
- Apply **Evaluation Policy**
- Calculate **Metrics**; generate **Findings** and **Scores**
- **Publish EvaluationResult** (immutable, historical)
- Support Evaluation Gate and shadow comparison as observation
- Correlate via Context Fingerprint / artifact IDs when available
- Remain **asynchronous** and **channel-neutral**

### 7.2 Non-responsibilities

- Changing DecisionResult / LearningResult / ExecutionRequest / ExecutionResult (**EVAL-INV-2…5**)
- Runtime Decision Evaluation or Learning Evaluation
- Channel Translation or send
- Crossing the Execution Boundary (**EVAL-INV-10**)
- Forcing production lifecycle coordination or sends (`002`)
- Becoming commerce or inbox SoR
- Auto-activating Learning (AC-4 remains Learning / Plugin)

---

## 8. Relationship to Runtime

Evaluation observes published runtime artifacts. It never feeds directly into runtime execution.

```text
DecisionResult
        ↓
LearningResult
        ↓
ExecutionResult
        ↓
Evaluation Signals
        ↓
Evaluation
        ↓
EvaluationResult
```

| Rule | Detail |
|------|--------|
| **Observe** | Read published artifacts and outcomes only (**EVAL-INV-7**) |
| **No mutation** | Never rewrite historical runtime artifacts (**EVAL-INV-2…5**) |
| **No execution** | Never send or commit commerce (**EVAL-INV-10**) |
| **No direct runtime feed** | EvaluationResult informs governance / reporting / Migration — not Decide/Respond loops |
| **Shadow** | Dual-run comparison remains observational; production path unchanged unless Product Owner approves Migration cutover (`011`) |

Overall stack:

```text
Conversation → ConversationView
Knowledge    → KnowledgeHits
Memory       → MemoryView
Context      → Decision Context
Decision     → DecisionResult
Learning     → LearningResult
Adapter      → ExecutionRequest → ExecutionResult
Evaluation   → EvaluationResult
```

---

## 9. Evaluation Model

Architectural process — not implementation.

```text
Evaluation Signals
        ↓
Validate Evidence
        ↓
Apply Evaluation Policy
        ↓
Calculate Metrics
        ↓
Generate Findings
        ↓
Publish EvaluationResult (immutable, historical)
```

### 9.1 Evaluation principles

1. **Observe — never execute**
2. **Measure — never reason for runtime** (no Decision / Learning Evaluation substitute)
3. **Evidence before scoring**
4. **Policy before metrics**
5. **Asynchronous** (**EVAL-INV-6**)
6. **No runtime mutation**
7. **Channel neutral**
8. **Replaceable**

---

## 10. Ownership

| Concern | Owner |
|---------|--------|
| **EvaluationResult / Evaluation** | Evaluation Framework (**EVAL-INV-1**) |
| **DecisionResult** | Decision System (`007`) |
| **LearningResult** | Learning Engine (`008`) |
| **ExecutionRequest / ExecutionResult** | Adapter Framework (`009`) |
| **Decision Context** | Context Engine (`003`) |
| **ConversationView / KnowledgeHits / MemoryView** | Documents `004`–`006` |
| **Commerce / business state** | Plugin / WooCommerce |
| **Evaluation Gate acceptance** | Product / requirements consumers of EvaluationResult |

**Rule:** Other modules may **read** EvaluationResult. They must not mutate it in place. Republishing yields a new EvaluationResult ID. Runtime artifacts remain unchanged.

---

## 11. Lifetime

| Concept | Lifetime |
|---------|----------|
| **EvaluationResult** | Immutable once published (**EVAL-INV-8**); historical for reporting and governance |
| **Evaluation Signals** | Owned by their producers; Evaluation holds read-only correlation |
| **Shadow comparisons** | Observational records; do not rewrite baseline or candidate Decisions |

EvaluationResult is used for reporting, governance, continuous improvement, and Migration readiness — not current-request execution.

---

## 12. Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**EVAL-INV-10**) |
| **Decision Boundary** | Never change DecisionResult (**EVAL-INV-2**) |
| **Learning Boundary** | Never change LearningResult (**EVAL-INV-3**); never auto-activate Learning |
| **Adapter Boundary** | Never change ExecutionRequest / ExecutionResult (**EVAL-INV-4**, **EVAL-INV-5**) |
| **Reasoning Boundary** | Does not replace Decision Evaluation or Learning Evaluation |
| **Observational Boundary** | No direct feed into runtime Decide/Respond (**EVAL-INV-7**) |
| **Channel-agnostic** | No channel transport logic inside Evaluation |
| **Implementation Boundary** | No storage/suite/schema law in this document |
| **Webhook latency** | Evaluation is not performed on inbound acknowledgment path (AC-8) |

---

## 13. Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Sufficient Evidence; Metrics/Findings comply with Evaluation Policy |
| **Valid with warnings** | Partial coverage; limitations signaled |
| **Rejected** | Policy violation |
| **Incomplete** | Insufficient Evidence — Need More Evidence |

Checks (conceptual): Evidence non-fabricated; Policy applied; Scores attributable; no runtime mutation authority; no execution authority; bounds respected; traceability seeds attached.

---

## 14. Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — EvaluationResult

| | |
|--|--|
| **Name** | **EvaluationResult** (published result of Evaluation) |
| **Producer** | AI Evaluation Framework |
| **Consumers** | Governance, Administration, Reporting, Migration (`011`), Learning governance |
| **Guarantees** | Immutable; observational; bounded; no runtime mutation; no execution authority (**EVAL-INV-1**, **EVAL-INV-7**, **EVAL-INV-8**) |
| **Non-guarantees** | Complete coverage of all Metrics; automatic production cutover |

### 14.2 EvaluationResult Identity

Each published EvaluationResult has a unique **EvaluationResult ID**.

EvaluationResult is **immutable**.

Semantic Decision comparison for matched cases prefers **Context Fingerprint** (`003`); EvaluationResult records assessment — it does not redefine Decision identity.

### 14.3 Related contracts (Document `002`)

| Contract | Producer | Use |
|----------|----------|-----|
| **EvaluationResult** | Evaluation Engine | Canonical published assessment artifact |
| **DecisionResult / LearningResult / ExecutionResult** | `007` / `008` / `009` | Read-only Evaluation Signals |
| **EffectiveConfig / CapabilitySet** | Platform Services | Evaluation Policy bounds |
| **Baseline Decision** (optional) | Plugin / prior Wise Decision | Shadow comparison input |

### 14.4 Versioning

- Additive Metrics or Findings types → Minor
- Changing ownership toward runtime mutation → ADR + Document `002`
- Breaking Evaluation Gate semantics → coordinate with Document `001`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Metric** | Additive vocabulary via contract versioning |
| **New Finding type** | Additive; remains advisory |
| **New Evaluation Policy** | Via Capability / Configuration / policy extension |
| **Replace Evaluation backend** | Allowed if EvaluationResult contract remains stable |
| **New evaluation suite** | Document `002` extension point + this Framework |

**Forbidden:** Changing DecisionResult / LearningResult / ExecutionRequest / ExecutionResult; runtime reasoning substitute; Execution; channel-specific second brain; forcing production sends from Evaluation.

---

## 16. Failure Philosophy

If Evidence is insufficient:

1. Publish EvaluationResult indicating **Incomplete** or **Need More Evidence**
2. Attach Evidence and Policy signals honestly
3. **Never fabricate metrics**
4. **Never change runtime behavior**
5. **Never rewrite historical artifacts**
6. Never execute Actions or cross the Execution Boundary

Insufficient Evidence is not an invitation to invent Scores — it is an explicit Incomplete Finding.

---

## 17. Observability

Published EvaluationResult should support:

- Traceability of Signals, Metrics, Findings, and Incomplete outcomes
- Correlation via EvaluationResult ID with Context Fingerprint / DecisionResult / LearningResult / Execution IDs
- Shadow agreement / regression visibility for Migration readiness
- Audit that Evaluation did not mutate runtime artifacts

Observability of Evaluation must not become a runtime control plane.

---

## 18. Replaceability

Evaluation Framework internals may be replaced if:

- EvaluationResult contract remains stable
- Ownership remains EvaluationResult for published assessment
- Runtime artifact ownership remains untouched (`007`–`009`)
- Documents `001`–`009` constraints and EVAL-INV-* hold
- Evaluation remains observational, asynchronous, bounded, and non-mutating

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Evaluation Framework contribution |
|-------------------------|-----------------------------------|
| **Understand → Decide** | None as runtime participant; may later observe DecisionResult |
| **Respond** | None — Execution Boundary; may later observe ExecutionResult |
| **Learn** | May observe LearningResult; does not perform Learning Evaluation |
| **Continuous / shadow** | Primary — asynchronous Evaluation against published artifacts |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| Optional Evaluation hooks | Shadow / regression observation only |
| Production Decision semantics | Unchanged by Evaluation |

```text
007 Decision  → DecisionResult
008 Learning  → LearningResult
009 Adapter   → ExecutionRequest → ExecutionResult
010 Evaluation → EvaluationResult (observe only)
001 Gate      → Acceptance / Cases / Regression (consumes Findings)
011 Migration → uses EvaluationResult for shadow readiness
```

Evaluation **never** redefines Conversation SoT, Knowledge SoR, MemoryView, Decision Context, DecisionResult, LearningResult, or Execution artifacts.

---

## 20. Validation Notes

Validated against Documents `001`–`009`, Hub Backend, and Plugin discovery. No production redesign.

### Conflict V-010-1 — Limited evaluation in production

| | |
|--|--|
| **Conflict** | Current production has limited evaluation beyond operational metrics; no independent Evaluation Framework as defined here. |
| **Risk** | Treating Document `010` as shipped; inventing runtime-coupled scoring. |
| **Suggested resolution** | Introduce Evaluation Framework through Document `011` Migration; keep production paths unchanged; prefer shadow comparison first (`001`). |

### Conflict V-010-2 — Evaluation mistaken for Decision Evaluation

| | |
|--|--|
| **Conflict** | Teams may use Evaluation to re-select Intent or rewrite recommendations. |
| **Risk** | EVAL-INV-2 / EVAL-INV-7 violations. |
| **Suggested resolution** | Decision Evaluation remains `007`; Evaluation Framework only measures published DecisionResult. |

### Conflict V-010-3 — Evaluation mistaken for Learning Evaluation

| | |
|--|--|
| **Conflict** | Evaluation Findings treated as LearningResult activation. |
| **Risk** | AC-4 / EVAL-INV-3 violations. |
| **Suggested resolution** | Learning Evaluation / LearningResult remain `008`; Evaluation may assess LearningResult quality only. |

### Conflict V-010-4 — Shadow forces production cutover

| | |
|--|--|
| **Conflict** | Shadow disagreement used to force send/path changes automatically. |
| **Risk** | Execution Boundary and fail-safe violations (`002`). |
| **Suggested resolution** | Shadow remains observational; cutover only via approved Migration (`011`) and Product Owner. |

### Conflict V-010-5 — EvaluationRecord vs EvaluationResult naming — **closed**

| | |
|--|--|
| **Conflict** | Document `002` previously used EvaluationRecord; Document `010` uses EvaluationResult. |
| **Risk** | Dual artifact confusion. |
| **Resolution** | Document `002` v0.3.7 standardizes on **EvaluationResult** repository-wide. Conflict closed. |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Evaluation Gate; shadow preference; Success Metrics |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Evaluation Engine; EvaluationResult; fail-safe |
| [`003-context-engine.md`](003-context-engine.md) | Context Fingerprint for matched comparisons |
| [`004-conversation-engine.md`](004-conversation-engine.md) | Conversation artifacts — correlation only |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | Knowledge artifacts — correlation / grounding Metrics |
| [`006-memory-engine.md`](006-memory-engine.md) | Memory artifacts — correlation only |
| [`007-decision-system.md`](007-decision-system.md) | DecisionResult as Evaluation Signal |
| [`008-learning-engine.md`](008-learning-engine.md) | LearningResult as Evaluation Signal |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | ExecutionResult as Evaluation Signal |
| [`011-migration-strategy.md`](011-migration-strategy.md) | Shadow readiness / cutover using EvaluationResult |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Evaluation → EvaluationResult; Signal/Metric/Policy/Evidence/Score/Finding; EVAL-INV-1…10; observational/async; Validation Notes V-010-1…5 |
| 0.1.1 | 2026-07-30 | Documentation Lead | Align with Document `002` v0.3.7 — EvaluationResult only; close V-010-5 |
| 0.1.2 | 2026-07-30 | Documentation Lead | Reference Architecture **1.0.0** freeze — **Status → Approved** · Frozen with Documents `001`–`011` |
