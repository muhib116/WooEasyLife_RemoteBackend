# Learning Engine

| Field | Value |
|-------|--------|
| **Title** | Learning Engine |
| **Document ID** | `008` |
| **Version** | `0.1.3` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.5) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.4) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.4) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.3) |
| **Knowledge Engine** | [`005-knowledge-engine.md`](005-knowledge-engine.md) (**Draft** v0.1.5) |
| **Memory Engine** | [`006-memory-engine.md`](006-memory-engine.md) (**Approved** v0.2.2) |
| **Decision System** | [`007-decision-system.md`](007-decision-system.md) (**Draft** v0.1.1) |

---

## 1. Document Information

This document defines the **canonical Learning Engine** for Wise AI Core.

It answers one architectural question:

> **How does Wise improve over time without changing Decisions during execution?**

It deepens **only** the Learning Engine: Learning Evaluation behavior, LearningResult as the published artifact, Signals, Candidates, Policy, Evidence, Confidence, ownership, boundaries, invariants, quality attributes, contracts, and the relationship to Decision.

It does **not** redefine Documents `001`–`007`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. If any statement here conflicts with Document `005`’s Knowledge law, **Document `005` wins**.
6. If any statement here conflicts with Document `006`’s Memory law, **Document `006` wins**.
7. If any statement here conflicts with Document `007`’s Decision law, **Document `007` wins**.
8. This document must not reorganize the Core or redefine Decision Context, ConversationView, KnowledgeHits, MemoryView, or DecisionResult.

**Terminology rules:**

- Throughout this document, **LearningResult** means the published artifact of Learning Evaluation unless explicitly stated otherwise.
- Document `002` ownership of **Approved Patterns** (+ learnable markers) is deepened here: Learning Evaluation publishes **LearningResult**; approved patterns become active only through **approved lifecycle boundaries** (AC-4).
- Learning Engine **owns Learning Evaluation / LearningResult** — never Decision Context, ConversationView, KnowledgeHits, MemoryView, DecisionResult, Commerce, or Execution.
- Learning **never changes an in-flight Decision** and is **never part of the execution path**.

**Out of scope:** implementation, APIs, database schemas, storage engines, channel adapter internals (`009`), Evaluation Framework internals (`010`), merchant approve UX design (Plugin remains approval authority per `001`), and any technology recipe that would redefine architectural Learning as an implementation plan.

---

## 2. Purpose

Provide a production-grade architectural definition of improvement-over-time so that:

- Wise can propose future improvements from **approved evidence** without mutating live Decisions
- Learning remains **asynchronous** and outside the Execution Boundary
- Knowledge, Memory, and Policy changes activate only through **approved lifecycle boundaries** (AC-4)
- DecisionResult, Context, Conversation, Knowledge, and Memory ownership stay intact (`003`–`007`)
- Migration (`011`) can introduce Learning without redesigning production Sales Agent paths

---

## 3. Scope

**In scope:**

- Learning Engine role (AI Infrastructure per Document `002`)
- Core concepts: Learning, Learning Signal, Learning Candidate, Learning Evaluation, Learning Policy, Learning Evidence, Learning Confidence, LearningResult
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Decision (`007`) and approved activation into Knowledge / Memory / Policy
- Validation Notes against production reality

**Out of scope:**

- Owning or mutating Decision Context / ConversationView / KnowledgeHits / MemoryView / DecisionResult
- Commerce fact authority
- Channel send / commerce commits
- Auto-activating unapproved samples
- Implementation of evaluation algorithms or storage

---

## 4. Relationship to Documents 001–007

| Inherited concept | How Document `008` uses it |
|-------------------|----------------------------|
| **AC-4** (`001`) | Knowledge publish and learning activation remain approval-gated |
| **Learn stage** (`001` / `002`) | Markers and approved-pattern selection; activation ≠ merchant approve UX |
| **Module map** (`002`) | Learning Engine = AI Infrastructure; owns Approved Patterns (+ learnable markers) |
| **Execution Boundary** (`002`) | Learning never sends or commits commerce |
| **DecisionResult** (`007`) | May contribute **Learning Signals** after completion — never mutated |
| **Knowledge / Memory** (`005` / `006`) | Future improvements may target these only via approved boundaries |
| **Context** (`003`) | Never mutated by Learning |

```text
Document 001 — AC-4 / Learn stage / merchant approval authority
        ↓
Document 002 — Learning Engine owns Approved Patterns (+ markers)
        ↓
Documents 003–006 — Context / Conversation / Knowledge / Memory (untouched by Learning mutation)
        ↓
Document 007 — DecisionResult (immutable recommendation; source of post-hoc Signals)
        ↓
Document 008 — this document (deepens Learning Engine only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| LRN-1 | Perform **Learning Evaluation** and **publish LearningResult** from Learning Signals |
| LRN-2 | Own **LearningResult** only — never Context, Conversation, Knowledge, Memory, Decision, or Commerce |
| LRN-3 | Keep Learning **asynchronous** and off the execution path |
| LRN-4 | Never change an **in-flight Decision** or mutate a published DecisionResult |
| LRN-5 | Require **evidence** and **policy** before proposing improvements |
| LRN-6 | Never **self-approve**; activation only via approved lifecycle boundaries (AC-4) |
| LRN-7 | Treat LearningResult as **immutable** and **advisory** |
| LRN-8 | Keep evaluation **bounded**, **channel-agnostic**, and **explainable** |
| LRN-9 | Remain replaceable behind the LearningResult contract |
| LRN-10 | Never cross the Execution Boundary |

---

## 5A. Learning Invariants

Document `001` has Platform Invariants. Documents `003`–`007` have engine invariants. The following are the **laws of the Learning Engine**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **LRN-INV-1** | Learning Engine owns **LearningResult**. |
| **LRN-INV-2** | Learning **never changes an in-flight Decision**. |
| **LRN-INV-3** | Learning **never owns Decision Context**. |
| **LRN-INV-4** | Learning **never owns ConversationView**. |
| **LRN-INV-5** | Learning **never owns KnowledgeHits**. |
| **LRN-INV-6** | Learning **never owns MemoryView**. |
| **LRN-INV-7** | LearningResult is **immutable**. |
| **LRN-INV-8** | Learning is **asynchronous**. |
| **LRN-INV-9** | Learning Evaluation is **bounded**. |
| **LRN-INV-10** | Learning **never crosses the Execution Boundary**. |

**Notes:**

- **LRN-INV-1** deepens Document `002` Approved Patterns ownership: LearningResult is the published evaluation artifact; activation of patterns remains AC-4 gated.
- **LRN-INV-2** — originating DecisionResult stays immutable (`007`); Learning is future-facing only.
- **LearningHints** (Document `002` contract name) may expose **already approved** patterns for read by other modules — distinct from publishing a new LearningResult recommendation.

---

## 5B. Learning Quality Attributes

Not new platform requirements. Qualities good Learning Evaluation / LearningResult should exhibit.

| Attribute | Meaning for Learning |
|-----------|----------------------|
| **Accuracy** | Proposed improvements match Evidence and do not invent outcomes |
| **Stability** | Learning does not thrash future behavior without durable Evidence |
| **Explainability** | Why a Candidate is proposed (or rejected) is expressible |
| **Traceability** | LearningResult ID correlates to Signals / Evidence / DecisionResult IDs as applicable |
| **Determinism** | Same Signals + pinned Learning Policy → reasonably identical LearningResult |
| **Safety** | Unsafe or policy-violating Candidates are rejected; no auto-activation |
| **Relevance** | Candidates target meaningful future improvement opportunities |
| **Evidence Quality** | Evidence is sufficient, attributable, and non-fabricated |
| **Confidence** | Confidence is attached honestly; low confidence prefers Reject / Need More Evidence |
| **Boundedness** | Finite Signals / Candidates per Evaluation — never unbounded history mining as law |

---

## 6. Core Concepts

### 6.1 Learning

**Learning** is the architectural capability to **improve future behavior from approved evidence**.

Learning is **not**:

| Not Learning | Why / owner |
|--------------|-------------|
| **Decision** | In-flight or published recommendation (`007`) |
| **Decision Context** | Assembled inputs (`003`) |
| **Conversation** | Dialogue SoT / ConversationView (`004`) |
| **Knowledge** | Approved content / KnowledgeHits (`005`) — Learning may *recommend* future Knowledge changes only |
| **Memory** | Continuity / MemoryView (`006`) — Learning may *recommend* future Memory Items only via approved boundaries |
| **Execution** | Channel send / commerce commit |
| **Evaluation** (`010`) | Assessment of Decisions — related but distinct |

### 6.2 Learning Signal

**Learning Signal** is information that may indicate an **opportunity to improve**.

Illustrative Signals (non-exhaustive):

| Signal | Meaning (conceptual) |
|--------|----------------------|
| **Merchant feedback** | Operator ratings, edits, or approve/reject outcomes |
| **Human corrections** | Corrected drafts / overrides of recommendations |
| **Conversation outcomes** | Post-hoc dialogue results projected as Signals (not Conversation SoT ownership) |
| **Verification outcomes** | Grounding pass/fail patterns suitable for improvement Signals |
| **Safety outcomes** | Safety escalations / blocks as improvement Signals |
| **Customer confirmations** | Explicit confirmations relevant to future preference/policy Candidates |

Signals are **inputs to Learning Evaluation**. They are not mutable DecisionResult state.

### 6.3 Learning Candidate

**Learning Candidate** is a **possible improvement** identified during Learning Evaluation (e.g. future Knowledge revision, Memory preference admission, Decision Policy adjustment recommendation).

Candidates remain advisory until approved lifecycle activation.

### 6.4 Learning Evaluation

**Learning Evaluation** is the **primary behavior** of the Learning Engine.

It evaluates Learning Signals and produces LearningResult.

```text
Learning Evaluation (behavior)
        ↓
LearningResult (published artifact)
```

This mirrors Documents `003`–`007`:

```text
Context Assembly      → Decision Context
Turn Framing          → ConversationView
Knowledge Retrieval   → KnowledgeHits
Memory Resolution     → MemoryView
Decision Evaluation   → DecisionResult
Learning Evaluation   → LearningResult
```

Learning Evaluation **must not define** (architecture non-goals): storage engines, APIs, schemas, or scoring algorithms as architecture law.

### 6.5 Learning Policy

**Learning Policy** is the set of **architectural rules** governing what improvements may be accepted for recommendation (and what must be rejected): evidence thresholds, forbidden auto-activation, PII / AC-9 posture, scope limits, and Capability / Configuration gates.

Policy is applied **before** optimization among Candidates.

Learning must never invent policy.

### 6.6 Learning Evidence

**Learning Evidence** is conceptual evidence supporting a proposed improvement: attributable Signals, correlation identifiers, outcome markers, and sufficiency signals.

Evidence must not be fabricated.

### 6.7 Learning Confidence

**Learning Confidence** is a conceptual indication of how strongly Evaluation stands behind a Candidate.

Low confidence prefers **Reject**, **Need More Evidence**, or **No Recommendation**.

### 6.8 LearningResult

**LearningResult** is the **published artifact** produced by Learning Evaluation.

Document `002` ownership (deepened):

| Module | Owns |
|--------|------|
| **Learning Engine** | **Approved Patterns** (+ learnable markers) → published evaluation artifact **LearningResult**; activation remains AC-4 gated |

LearningResult conceptually contains:

- **LearningResult ID** (unique per publication)
- **Candidate** (proposed improvement)
- **Evidence**
- **Confidence**
- **Policy signals**
- **Recommendation** (advisory — Accept for approval workflow / Reject / Need More Evidence / No Recommendation)

**LearningResult never changes runtime behavior directly.**

**LearningResult is NOT:**

| Not LearningResult | Owner / doc |
|--------------------|-------------|
| DecisionResult | Decision System (`007`) |
| Decision Context | Context Engine (`003`) |
| ConversationView | Conversation Engine (`004`) |
| KnowledgeHits | Knowledge Engine (`005`) |
| MemoryView | Memory Engine (`006`) |
| Execution authority | Adapters / Plugin / Woo |
| Self-approved live pattern | Forbidden (AC-4) |

### 6.9 Concept stack (one question each)

```text
Learning
  ↓ Signal     — What evidence of improvement opportunity arrived?
  ↓ Candidate  — What future change is proposed?
  ↓ Policy     — What rules govern acceptance?
  ↓ Evidence   — What supports the Candidate?
  ↓ Result     — What immutable advisory recommendation is published?
```

---

## 7. Learning Engine Role

| | |
|--|--|
| **Classification** (`002`) | AI Infrastructure |
| **Purpose** | Improve future behavior from approved evidence without mutating in-flight Decisions |
| **Primary behavior** | **Learning Evaluation** |
| **Primary output** | Published **LearningResult** |
| **Pipeline position** | Asynchronous relative to Decide / Respond; Learn-stage markers only inside Core |

### 7.1 Responsibilities

- Accept **Learning Signals** (including post-hoc Signals derived from completed Decisions)
- Validate Evidence sufficiency
- Apply **Learning Policy**
- Evaluate **Learning Candidates**
- Attach **Confidence** and policy / validation signals
- **Publish LearningResult** (immutable, advisory)
- Emit learnable markers for approval workflows (Document `002`)
- Expose **already approved** patterns for read by other modules (LearningHints contract — Document `002`) without re-owning Knowledge/Memory/Decision
- Remain **asynchronous** and **channel-agnostic**

### 7.2 Non-responsibilities

- Changing in-flight Decisions or mutating DecisionResult (**LRN-INV-2**)
- Owning Decision Context / ConversationView / KnowledgeHits / MemoryView (**LRN-INV-3…6**)
- Mutating Context, Conversation, Knowledge, or Memory stores
- Self-approving or auto-activating samples (AC-4)
- Executing Actions or crossing the Execution Boundary (**LRN-INV-10**)
- Replacing Evaluation Framework (`010`)
- Becoming commerce or inbox SoR

---

## 8. Relationship to Decision

Document `007` law: DecisionResult is an immutable recommendation. Learning observes outcomes asynchronously; it does not rewrite Decisions.

```text
DecisionResult
        ↓
Learning Signals
        ↓
Learning Evaluation
        ↓
LearningResult
        ↓
Approved lifecycle boundary (AC-4)
        ↓
Future Knowledge
   or Future Memory
   or Future Policy
```

| Rule | Detail |
|------|--------|
| **No mutation** | Learning never modifies the originating DecisionResult |
| **No mutable consumption** | Learning never consumes DecisionResult as mutable state |
| **Post-hoc Signals only** | Completed Decision outcomes may contribute Signals after publication |
| **Future activation only** | Approved Learning affects future Decisions / Knowledge / Memory / Policy — never the Decision already published |
| **Not on execution path** | Learning is not required to complete Respond / channel application |

Overall information stack:

```text
Conversation → ConversationView
Knowledge    → KnowledgeHits
Memory       → MemoryView
Context      → Decision Context
Decision     → DecisionResult
Learning     → LearningResult
```

---

## 9. Learning Evaluation Model

Architectural process — not implementation.

```text
Learning Signals
        ↓
Validate evidence
        ↓
Apply Learning Policy
        ↓
Evaluate Candidates
        ↓
Estimate Confidence
        ↓
Publish LearningResult (immutable, advisory)
```

### 9.1 Learning principles

1. **Asynchronous by design** — Learning is not on the execution path (**LRN-INV-8**).
2. **No runtime mutation** — never change in-flight Decisions or live Context/Conversation/Knowledge/Memory (**LRN-INV-2**).
3. **Evidence before optimization** — no Candidate without Evidence.
4. **Policy before adaptation** — Learning Policy beats Candidate preference.
5. **No self-approval** — activation only via approved lifecycle boundaries (AC-4).
6. **Future improvements only** — LearningResult is advisory for future behavior.
7. **Channel neutral** — no Meta / transport hard dependency.
8. **No execution** — never send or commit commerce (**LRN-INV-10**).

---

## 10. Learning Ownership

| Concern | Owner |
|---------|--------|
| **LearningResult / Learning Evaluation** | Learning Engine (**LRN-INV-1**) |
| **Already approved patterns (read)** | Learning Engine exposes; activation governed by AC-4 |
| **DecisionResult** | Decision System (`007`) |
| **Decision Context** | Context Engine (`003`) |
| **ConversationView** | Conversation Engine (`004`) |
| **KnowledgeHits / Knowledge SoR** | Knowledge Engine / business authorities (`005`) |
| **MemoryView** | Memory Engine (`006`) |
| **Merchant approval UX** | Plugin (`001`) |
| **Commerce facts authority** | Plugin / WooCommerce |
| **Channel transport / send** | Adapters / Hub edge |
| **Decision assessment** | Evaluation Engine (`010`) |

**Rule:** Other modules may **read** a published LearningResult. They must not mutate it in place. Republishing yields a new LearningResult ID. Activation into Knowledge / Memory / Policy is a **separate approved boundary**, not an in-place LearningResult edit.

---

## 11. Learning Lifetime

| Concept | Lifetime |
|---------|----------|
| **LearningResult** | Immutable once published (**LRN-INV-7**); advisory until approved |
| **Learning recommendations** | Remain advisory until approved lifecycle activation |
| **Approved activation** | Affects **future** Knowledge / Memory / Policy / pattern reads only — never current in-flight Decision |
| **Learn-stage markers** | May be emitted around Decision completion; do not activate patterns by themselves |

Approval activates **future behavior only**. Never current behavior.

---

## 12. Learning Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**LRN-INV-10**) |
| **Decision Boundary** | Never change in-flight Decision; never mutate DecisionResult (**LRN-INV-2**) |
| **Context Boundary** | Never own or mutate Decision Context (**LRN-INV-3**) |
| **Conversation Boundary** | Never own or mutate ConversationView (**LRN-INV-4**) |
| **Knowledge Boundary** | Never own KnowledgeHits; never mutate Knowledge SoR without approval path (**LRN-INV-5**, AC-4) |
| **Memory Boundary** | Never own or mutate MemoryView (**LRN-INV-6**) |
| **Approval Boundary** | No self-approval / auto-activation (AC-4) |
| **PII / training** | Raw chat must not become default Hub training via Learning (AC-9) |
| **Channel-agnostic** | No Meta Graph / token logic inside Learning Evaluation |
| **Implementation Boundary** | No storage/API/schema/algorithm law in this document |
| **Webhook latency** | Learning Evaluation is not performed on Meta webhook acknowledgment path (AC-8) |

---

## 13. Learning Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Sufficient Evidence; Candidate complies with Learning Policy |
| **Valid with warnings** | Evidence incomplete or soft Confidence — advisory with limitations |
| **Rejected — policy** | Policy violation; no activation path |
| **Rejected — confidence** | Insufficient Confidence; Need More Evidence / No Recommendation |
| **Rejected — insufficient evidence** | Evidence missing or fabricated signals refused |

Checks (conceptual): Evidence present and non-invented; policy applied; Confidence attached; no runtime mutation authority; no execution authority; bounds respected; AC-4 posture intact; traceability seeds attached.

---

## 14. Learning Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — LearningResult

| | |
|--|--|
| **Name** | **LearningResult** (published result of Learning Evaluation) |
| **Producer** | Learning Engine |
| **Consumers** | Evaluation (`010`), Migration (`011`), Administration / approval workflows, Knowledge governance, Memory governance |
| **Guarantees** | Bounded; channel-neutral; immutable; advisory; no execution authority; no in-flight Decision mutation (**LRN-INV-7**, **LRN-INV-2**) |
| **Non-guarantees** | Automatic approval; immediate behavior change; complete coverage of all Signals |

### 14.2 LearningResult Identity

Each published LearningResult has a unique **LearningResult ID**.

LearningResult ID supports observability, replay, debugging, and audit correlation.

LearningResult is **immutable** and **advisory**.

Semantic Decision comparison remains at the Context Fingerprint level (`003`); LearningResult does not redefine Decision identity.

### 14.3 Related contracts (Document `002`)

| Contract | Producer | Use |
|----------|----------|-----|
| **LearningHints** | Learning Engine | Read-only exposure of **already approved** patterns to Prompt/Safety (and similar) — not a self-approval path |
| **Learning Signals** | Post-Decision markers, merchant feedback, Safety/Verification outcomes, etc. | Inputs to Learning Evaluation |
| **EffectiveConfig / CapabilitySet** | Platform Services | Learning Policy bounds |

### 14.4 Versioning

- Additive Signal or Candidate types → Minor
- Changing ownership away from advisory LearningResult → ADR + Document `002`
- Breaking activation semantics that AC-4 depends on → coordinate with Document `001`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Learning Signal** | Additive vocabulary via contract versioning |
| **New Learning Policy** | Via Capability / Configuration / policy extension |
| **New Evaluation strategy** | Allowed behind LearningResult contract |
| **Replace Learning backend** | Allowed if LearningResult contract remains stable |

**Forbidden:** Changing runtime Decisions; mutating Context / Conversation / Knowledge / Memory; executing Actions; auto-activating unapproved samples; channel-specific second Learning Engine; treating LearningResult as execution authority.

---

## 16. Failure Philosophy

If evidence is insufficient or policy forbids adaptation:

1. Publish LearningResult indicating **Reject**, **Need More Evidence**, or **No Recommendation**
2. Attach Evidence and Confidence honestly
3. **Never invent evidence**
4. **Never auto-approve**
5. **Never change runtime behavior**
6. **Never mutate** DecisionResult, Context, Conversation, Knowledge, or Memory
7. Never execute Actions

Insufficient Evidence is not an invitation to invent — it is an explicit advisory rejection or hold for more Evidence.

---

## 17. Observability

Published LearningResult should support:

- Traceability of Signals, Evidence, Candidates, and Reject / Need More Evidence outcomes
- Correlation via **LearningResult ID** with DecisionResult ID / Context ID when Signals originate from completed Decisions
- Policy and Confidence visibility for approval workflows and Evaluation
- Audit that activation did not bypass AC-4

Observability artifacts must not become execution authority or ownership of Context / Conversation / Knowledge / Memory / Decision.

---

## 18. Replaceability

Learning Engine internals may be replaced if:

- LearningResult contract remains stable
- Ownership remains LearningResult for published evaluation
- Context / Conversation / Knowledge / Memory / Decision ownership remain untouched
- Documents `001`–`007` constraints and LRN-INV-* hold
- Evaluation remains bounded, non-inventing, asynchronous, and advisory
- Activation remains approval-gated (AC-4)

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Learning Engine contribution |
|-------------------------|------------------------------|
| **Understand** | May supply **already approved** pattern reads (LearningHints) — not new activation |
| **Clarify / Verify / Decide** | No mutation of in-flight Decision; Learning is not on the critical Decide path |
| **Respond** | None — Execution Boundary |
| **Learn** | Primary — emit markers; perform asynchronous Learning Evaluation; publish LearningResult |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| Learn markers | Inside Core after Decision |
| Approved pattern reads | LearningHints to allowed consumers |
| Activation | Outside Core — Plugin / governance approve workflows |

```text
007 Decision  → DecisionResult (immutable)
008 Learning  → LearningResult (advisory)
        ↓ approved lifecycle (AC-4)
005 Knowledge / 006 Memory / Decision Policy (future only)
009 Adapters  → Execution (unchanged by Learning)
010 Evaluation → may consume LearningResult / DecisionResult
011 Migration → introduces Learning Engine without production redesign
```

Learning **never** redefines Conversation SoT, Knowledge SoR, MemoryView ownership, Decision Context, or DecisionResult.

---

## 20. Validation Notes

Validated against Documents `001`–`007`, Hub Backend, and WordPress Plugin discovery. No production redesign.

### Conflict V-008-1 — Little architectural Learning in production

| | |
|--|--|
| **Conflict** | Current Plugin Sales Agent has merchant learning/approve UX fragments but almost no Hub Learning Engine as defined here. |
| **Risk** | Treating Document `008` as shipped; inventing auto-learning in production. |
| **Suggested resolution** | Introduce Learning Engine through Document `011` Migration; keep Plugin approval authority (AC-4); no production redesign of live paths. |

### Conflict V-008-2 — Learning mistaken for Decision mutation

| | |
|--|--|
| **Conflict** | Teams may apply LearningResult to rewrite the Decision that produced Signals. |
| **Risk** | Non-replayable Decisions; LRN-INV-2 violation. |
| **Suggested resolution** | DecisionResult remains immutable (`007`); Learning is asynchronous and future-facing only. |

### Conflict V-008-3 — Auto-activation bypasses AC-4

| | |
|--|--|
| **Conflict** | LearningResult recommendation treated as live pattern without merchant/governance approval. |
| **Risk** | AC-4 violation; unsafe tone/handoff/knowledge drift. |
| **Suggested resolution** | LearningResult is advisory; activation only via approved lifecycle boundaries; Plugin remains approval UX authority (`001`). |

### Conflict V-008-4 — Learning owns Knowledge or Memory

| | |
|--|--|
| **Conflict** | Learning may be used to write KnowledgeHits or MemoryView directly. |
| **Risk** | Dual ownership; SoR confusion. |
| **Suggested resolution** | Knowledge Engine / Memory Engine retain ownership (`005` / `006`); Learning recommends; approved boundaries admit future Items/content. |

### Conflict V-008-5 — LearningHints vs LearningResult confusion

| | |
|--|--|
| **Conflict** | Read of approved patterns (LearningHints) confused with publishing new LearningResult. |
| **Risk** | Implicit self-approval. |
| **Suggested resolution** | LearningHints = already approved reads (`002`); LearningResult = advisory evaluation artifact requiring activation (**LRN-INV-1**, AC-4). |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | AC-4; Learn stage; merchant approval authority |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Learning Engine module map; LearningHints; Approved Patterns |
| [`003-context-engine.md`](003-context-engine.md) | Context never mutated by Learning |
| [`004-conversation-engine.md`](004-conversation-engine.md) | ConversationView never owned/mutated |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | Future Knowledge via approved boundaries |
| [`006-memory-engine.md`](006-memory-engine.md) | Future Memory via approved boundaries |
| [`007-decision-system.md`](007-decision-system.md) | DecisionResult → Learning Signals (post-hoc) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | Execution remains outside Learning (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | May consume LearningResult / DecisionResult (Draft) |
| `011-migration-strategy.md` | Introduces Learning Engine without redesign |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Learning Evaluation → LearningResult; Signal/Candidate/Policy/Evidence/Confidence; LRN-INV-1…10; async/advisory/AC-4; Validation Notes V-008-1…5 |
| 0.1.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.1.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.1.3 | 2026-07-30 | Documentation Lead | Reference Architecture **1.0.0** freeze — **Status → Approved** · Frozen with Documents `001`–`011` |
