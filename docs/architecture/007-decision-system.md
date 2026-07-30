# Decision System

| Field | Value |
|-------|--------|
| **Title** | Decision System |
| **Document ID** | `007` |
| **Version** | `0.1.4` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.4) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.3) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.4) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.3) |
| **Knowledge Engine** | [`005-knowledge-engine.md`](005-knowledge-engine.md) (**Draft** v0.1.4) |
| **Memory Engine** | [`006-memory-engine.md`](006-memory-engine.md) (**Approved** v0.2.1) |

---

## 1. Document Information

This document defines the **canonical Decision System** for Wise AI Core.

It answers one architectural question:

> **How does Wise transform a completed Decision Context into a recommendation without executing actions?**

It deepens **only** the Decision System: Decision Evaluation behavior, DecisionResult as the published artifact, Intent, Candidates, Policy, Confidence, Explanation, ownership, boundaries, invariants, quality attributes, contracts, and the relationship to Context.

It does **not** redefine Documents `001`–`006`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. If any statement here conflicts with Document `005`’s Knowledge law, **Document `005` wins**.
6. If any statement here conflicts with Document `006`’s Memory law, **Document `006` wins**.
7. This document must not reorganize the Core or redefine Decision Context, ConversationView, KnowledgeHits, or MemoryView.

**Terminology rules:**

- Throughout this document, **DecisionResult** means the published artifact of Decision Evaluation unless explicitly stated otherwise.
- **Decision** (`001`) remains the platform term for a structured recommendation; **DecisionResult** is the Decision System’s published form of that recommendation.
- Decision System **owns Decision Evaluation / DecisionResult** as its primary published reasoning artifact — never Decision Context, ConversationView, KnowledgeHits, MemoryView, or commerce SoR.
- Decision System **never executes Actions** and **never crosses the Execution Boundary**.

**Composition (inherited from Document `002`):**

Document `002` places under Decision System:

| Module | Role within Decision System |
|--------|-----------------------------|
| **Decision Coordinator** | Lifecycle control / stage progression only |
| **Decision Engine** | Decision Evaluation — selects recommendation |
| **Safety Engine** | Safety verdicts that constrain candidates |
| **Verification Engine** | Grounding / verification verdicts that constrain candidates |

This document deepens **Decision Evaluation → DecisionResult**. Coordinator, Safety, and Verification remain as Document `002` defines them; they contribute to producing a valid DecisionResult and do not redefine Context or information engines.

**Out of scope:** implementation, APIs, database schemas, storage engines, Learning Engine internals (`008`), channel adapter internals (`009`), Evaluation Framework internals (`010`), and any technology that would redefine architectural reasoning as an implementation recipe.

---

## 2. Purpose

Provide a production-grade architectural definition of recommendation reasoning so that:

- Wise turns a completed **Decision Context** into a **DecisionResult** without executing channel or commerce Actions
- Context First remains the sole architectural input path for assembled information
- Conversation, Knowledge, and Memory stay owned by their engines (`004`–`006`)
- Failure paths are explicit recommendations (Clarify / Hold / Escalate / Draft) — never fabrication
- Migration (`011`) can map Plugin Sales Agent reasoning onto Decision Evaluation without a big-bang rewrite

---

## 3. Scope

**In scope:**

- Decision System role (AI Core / Support modules composing Decision System per Document `002`)
- Core concepts: Decision, Decision Intent, Decision Candidate, Decision Evaluation, Decision Policy, Decision Confidence, Decision Explanation, DecisionResult
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Context (`003`) and upstream information engines (`004`–`006`)
- Validation Notes against production reality

**Out of scope:**

- Owning or assembling Decision Context
- Owning Conversation / Knowledge / Memory published artifacts
- Commerce fact authority
- Channel send / draft persistence / order commits (Execution Boundary)
- Implementation of evaluation algorithms or storage

---

## 4. Relationship to Documents 001–006

| Inherited concept | How Document `007` uses it |
|-------------------|----------------------------|
| **Decision** (`001`) | Structured recommendation before execution — published here as DecisionResult |
| **Action** (`001`) | Chosen next-step *type* inside the recommendation — adapters apply transport |
| **Decision Lifecycle** (`001`) | Understand → Clarify → Verify → Decide → Respond → Learn — Decision System realizes Decide (+ Clarify/Verify participation) |
| **Module map** (`002`) | Decision System = Coordinator · Decision Engine · Safety · Verification |
| **Execution Boundary** (`002`) | Decision System never sends or commits commerce |
| **Decision Context** (`003`) | Sole architectural input to Decision Evaluation |
| **ConversationView** (`004`) | Arrives only via Decision Context — never owned here |
| **KnowledgeHits** (`005`) | Arrives only via Decision Context — never owned here |
| **MemoryView** (`006`) | Arrives only via Decision Context — never owned here |

```text
Document 001 — Decision / Action / Lifecycle terminology
        ↓
Document 002 — Decision System module map + Execution Boundary
        ↓
Document 003 — Decision Context (sole input)
        ↓
Documents 004–006 — ConversationView / KnowledgeHits / MemoryView (inside Context)
        ↓
Document 007 — this document (deepens Decision System only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| DEC-1 | Perform **Decision Evaluation** and **publish DecisionResult** for one Decision Context |
| DEC-2 | Own **DecisionResult** as the published recommendation artifact |
| DEC-3 | Consume Decision Context only — never own Context |
| DEC-4 | Never own Conversation, Knowledge, Memory, or Commerce |
| DEC-5 | Never execute Actions; never cross the Execution Boundary |
| DEC-6 | Keep evaluation **bounded**, **channel-agnostic**, and **explainable** |
| DEC-7 | Make missing information **explicit** — never invent facts, preferences, or policy |
| DEC-8 | Treat DecisionResult as **immutable** and **read-only** once published |
| DEC-9 | Prefer policy-compliant Clarification / Hold / Escalate / Draft over unsafe assertion |
| DEC-10 | Remain replaceable behind the DecisionResult contract |

---

## 5A. Decision Invariants

Document `001` has Platform Invariants. Documents `003`–`006` have engine invariants. The following are the **laws of the Decision System**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **DEC-INV-1** | Decision System owns **DecisionResult**. |
| **DEC-INV-2** | Decision System **consumes Decision Context** but **never owns it**. |
| **DEC-INV-3** | Decision System **never owns ConversationView**. |
| **DEC-INV-4** | Decision System **never owns KnowledgeHits**. |
| **DEC-INV-5** | Decision System **never owns MemoryView**. |
| **DEC-INV-6** | DecisionResult is **immutable once published**. |
| **DEC-INV-7** | Decision System **never crosses the Execution Boundary**. |
| **DEC-INV-8** | Decision System **never executes commerce**. |
| **DEC-INV-9** | Decision Evaluation is **bounded**. |
| **DEC-INV-10** | DecisionResult is a **read-only recommendation**. |

**Notes:**

- **DEC-INV-1** deepens Document `002` Decision Engine ownership of Recommendation; DecisionResult is that published recommendation artifact.
- **DEC-INV-2** — Context Engine remains the sole owner of Decision Context (`003`).
- **Safety / Verification** — per Document `002`, SafetyVerdict and VerificationVerdict remain owned by Safety and Verification engines; they constrain Decision Evaluation and travel with explainability, but do not replace DecisionResult as the recommendation artifact.
- **Coordinator** owns lifecycle progression (`002`), not DecisionResult content selection.

---

## 5B. Decision Quality Attributes

Not new platform requirements. Qualities good Decision Evaluation / DecisionResult should exhibit.

| Attribute | Meaning for Decision |
|-----------|----------------------|
| **Correctness** | Recommendation fits the supplied Context and does not assert ungrounded commerce facts |
| **Consistency** | Similar Context + pinned policy → similar Intent / recommendation shape |
| **Explainability** | Why this Intent and recommendation were selected is expressible |
| **Determinism** | Same Context + pinned Decision Policy → reasonably identical DecisionResult |
| **Boundedness** | Finite Candidates and evaluation effort — never unbounded search of history or knowledge |
| **Traceability** | DecisionResult ID correlates to Context ID / Fingerprint for audit and replay |
| **Policy Compliance** | Selected recommendation satisfies Decision Policy constraints |
| **Safety** | Unsafe or forbidden paths are blocked or forced to Escalate / Clarify / Hold |
| **Completeness** | Required Context facets for the attempted Intent are present or absence is explicit |
| **Confidence** | Confidence is attached honestly; low confidence prefers Draft / Clarify / Hold / Escalate |

---

## 6. Core Concepts

### 6.1 Decision

From Document `001`:

> **Decision** — Structured **recommendation** produced by Wise before channel execution — adapters or Plugin/commerce may reject, draft-only, or modify application.

Deepening (without changing law):

A **Decision** is a **bounded reasoning outcome** for one Decision Context.

Decision is **not**:

| Not Decision | Why / owner |
|--------------|-------------|
| **Decision Context** | Assembled inputs (`003`) |
| **Conversation** | Dialogue SoT / ConversationView (`004`) |
| **Knowledge** | Approved content / KnowledgeHits (`005`) |
| **Memory** | Continuity / MemoryView (`006`) |
| **Execution** | Channel send / commerce commit (adapters / Plugin / Woo) |
| **Learning activation** | Learning Engine (`008`) |

### 6.2 Decision Intent

**Decision Intent** is the **objective** the Decision attempts to satisfy for this request.

Illustrative Intents (non-exhaustive):

| Intent | Meaning (conceptual) |
|--------|----------------------|
| **Answer Question** | Provide a grounded informational recommendation |
| **Clarify** | Ask for missing information before asserting facts |
| **Recommend Product** | Suggest product / option within grounded bounds |
| **Create Draft** | Produce draft-only recommendation for human review |
| **Hold** | Pause progression without asserting unsafe completion |
| **Escalate** | Hand off to human / operator path |

Intent vocabulary is **illustrative**. New Intents may be added via additive versioning without changing the DecisionResult contract shape.

**Intent vs Action (`001`):** Intent is the evaluation objective; Action is the chosen next-step *type* expressed inside the recommendation for adapters to apply.

### 6.3 Decision Candidate

**Decision Candidate** is a **possible recommendation** considered during Decision Evaluation.

Candidates are evaluated under Decision Policy, Safety, and Verification constraints. Candidates are internal to Evaluation; only the selected recommendation is published in DecisionResult.

### 6.4 Decision Evaluation

**Decision Evaluation** is the **primary behavior** of the Decision System.

It evaluates one Decision Context and produces one DecisionResult.

```text
Decision Evaluation (behavior)
        ↓
DecisionResult (published artifact)
```

This mirrors Documents `003`–`006`:

```text
Context Assembly      → Decision Context
Turn Framing          → ConversationView
Knowledge Retrieval   → KnowledgeHits
Memory Resolution     → MemoryView
Decision Evaluation   → DecisionResult
```

Decision Evaluation **must not define** (architecture non-goals): storage engines, APIs, schemas, or any implementation recipe for how Candidates are scored.

### 6.5 Decision Policy

**Decision Policy** is the set of **architectural constraints** governing acceptable recommendations (conceptual): confidence floors, handoff rules, Semi vs Full recommendation posture, clarification-before-assertion rules, forbidden claim classes, and mode gates supplied via EffectiveConfig / CapabilitySet (Document `002` Platform Services).

Policy is applied **before** optimization among Candidates.

Decision Evaluation must never invent policy.

### 6.6 Decision Confidence

**Decision Confidence** is a conceptual indication of how strongly Evaluation stands behind the selected recommendation.

Confidence informs Draft vs proceed posture and Escalation / Clarification preference. Merchant-enforceable floors remain binding (`001` Confidence term).

### 6.7 Decision Explanation

**Decision Explanation** is a conceptual account of **why** the recommendation was selected (Intent, policy signals, grounding / absence signals, Safety / Verification outcomes as applicable).

Explanation supports merchant audit, Evaluation, and debugging. It is not an API schema.

### 6.8 DecisionResult

**DecisionResult** is the **published artifact** produced by Decision Evaluation.

Document `002` ownership (deepened):

| Module | Owns |
|--------|------|
| **Decision Engine** (within Decision System) | Recommendation → published as **DecisionResult** |

DecisionResult conceptually contains:

- **DecisionResult ID** (unique per publication)
- **Decision Intent**
- **Selected recommendation** (including Action type per `001`)
- **Confidence**
- **Explanation**
- **Policy signals**
- **Validation signals** (Valid / Valid with warnings / Invalid / Unsafe)

**DecisionResult must never contain execution authority.**

**DecisionResult is NOT:**

| Not DecisionResult | Owner / doc |
|--------------------|-------------|
| Decision Context | Context Engine (`003`) |
| ConversationView | Conversation Engine (`004`) |
| KnowledgeHits | Knowledge Engine (`005`) |
| MemoryView | Memory Engine (`006`) |
| Channel send / commerce commit | Adapters / Plugin / Woo |
| Learning activation | Learning Engine (`008`) |

### 6.9 Concept stack (one question each)

```text
Decision
  ↓ Intent      — What objective are we trying to satisfy?
  ↓ Candidates  — What recommendations are under consideration?
  ↓ Policy      — What constraints must any selection obey?
  ↓ Evaluation  — Which Candidate wins under those constraints?
  ↓ Result      — What immutable recommendation is published?
```

---

## 7. Decision System Role

| | |
|--|--|
| **Classification** (`002`) | Decision Coordinator + Decision Engine = AI Core; Safety + Verification = AI Support — together the **Decision System** |
| **Purpose** | Transform completed Decision Context into a structured recommendation without executing Actions |
| **Primary behavior** | **Decision Evaluation** |
| **Primary output** | Published **DecisionResult** |
| **Pipeline position** | After Context assembly; before Execution Boundary / Respond application |

### 7.1 Responsibilities

- Accept a completed (or explicitly incomplete) **Decision Context** as sole architectural input
- Validate completeness relative to attempted Intent
- Apply **Decision Policy**
- Evaluate **Decision Candidates** under Safety / Verification constraints (per Document `002`)
- Select one recommendation; attach **Explanation** and **Confidence**
- **Publish DecisionResult** (immutable, read-only)
- Prefer Clarify / Hold / Escalate / Draft when Context or policy forbids assertion
- Remain **channel-agnostic**

### 7.2 Non-responsibilities

- Owning or assembling Decision Context (**DEC-INV-2**)
- Owning ConversationView / KnowledgeHits / MemoryView (**DEC-INV-3…5**)
- Bypassing Context to read Conversation, Knowledge, or Memory engines directly
- Executing Actions, channel send, or commerce commits (**DEC-INV-7**, **DEC-INV-8**)
- Becoming commerce or inbox SoR
- Inventing missing facts, preferences, or policy
- Activating Learning samples (Learning Engine / approve workflows)
- Coordinating peer engines outside Document `002` Coordinator rules

### 7.3 Internal separation (Document `002`)

| Concern | Owner inside Decision System |
|---------|------------------------------|
| Lifecycle sequencing | Decision Coordinator |
| Candidate selection / Confidence / Intent | Decision Engine (Decision Evaluation) |
| Safety constraints | Safety Engine |
| Grounding / verification constraints | Verification Engine |
| Published recommendation artifact | **DecisionResult** (**DEC-INV-1**) |

**Hard rule (`002`):** Only the Decision Coordinator may coordinate the Decision Lifecycle. Decision Engine must not reverse-control the Coordinator.

---

## 8. Relationship to Context

Document `003` law: Context First. Decision System consumes Decision Context; it does not redefine Context.

```text
ConversationView
        ↓
KnowledgeHits
        ↓
MemoryView
        ↓
Context Engine
        ↓
Decision Context
        ↓
Decision Evaluation
        ↓
DecisionResult
```

| Rule | Detail |
|------|--------|
| **Sole input** | Decision Context is the only architectural input to Decision Evaluation |
| **No bypass** | Decision System must not read Conversation, Knowledge, or Memory engines directly for materials already owned by Context assembly |
| **Non-ownership** | Decision System never owns Decision Context (**DEC-INV-2**) |
| **Immutability** | Published DecisionResult is immutable (**DEC-INV-6**); a new request yields a new DecisionResult ID |
| **Identity** | DecisionResult ID supports audit/replay; Context ID / Fingerprint remain Context Engine concepts (`003`) |

---

## 9. Decision Evaluation Model

Architectural process — not implementation.

```text
Decision Context
        ↓
Validate completeness
        ↓
Apply Decision Policy
        ↓
Evaluate Decision Candidates
  (constrained by Safety / Verification per Document 002)
        ↓
Select Recommendation
        ↓
Attach Explanation
        ↓
Attach Confidence
        ↓
Validate boundaries
        ↓
Publish DecisionResult (immutable for this request)
```

### 9.1 Decision principles

1. **Context First** — reason only from supplied Decision Context.
2. **No invention** — never fabricate missing information, policy, or preferences.
3. **Policy before optimization** — constraints beat Candidate preference.
4. **Bounded reasoning** — finite Candidates and evaluation (**DEC-INV-9**).
5. **One DecisionResult per Decision request** — republishing creates a new ID.
6. **No execution** — recommendation only (**DEC-INV-7**, **DEC-INV-8**, **DEC-INV-10**).
7. **Channel neutral** — no Meta / transport hard dependency.
8. **Recommendation only** — adapters / Plugin / commerce apply Actions.

---

## 10. Decision Ownership

| Concern | Owner |
|---------|--------|
| **DecisionResult / Decision Evaluation** | Decision System (**DEC-INV-1**) |
| **Lifecycle progression** | Decision Coordinator (`002`) |
| **SafetyVerdict** | Safety Engine (`002`) |
| **VerificationVerdict** | Verification Engine (`002`) |
| **Decision Context** | Context Engine (`003`) |
| **ConversationView** | Conversation Engine (`004`) |
| **KnowledgeHits** | Knowledge Engine (`005`) |
| **MemoryView** | Memory Engine (`006`) |
| **Commerce facts authority** | Plugin / WooCommerce |
| **Channel transport / send** | Adapters / Hub edge |
| **Learning activation** | Learning Engine + approve workflows (`008`) |

**Rule:** Other modules may **read** a published DecisionResult. They must not mutate it in place. Republishing yields a new DecisionResult ID.

---

## 11. Decision Lifetime

| Concept | Lifetime |
|---------|----------|
| **DecisionResult** | Request scoped — published for one Decision Evaluation; then immutable / read-only |
| **Decision Intent / Confidence / Explanation on view** | Frozen at publication for that DecisionResult |
| **Decision Context** | Owned by Context Engine; may outlive or precede Evaluation as its input snapshot |
| **Session** (`001`) | May span multiple DecisionResult publications over time |

Republishing (new Evaluation for a new or revised Context) creates a **new DecisionResult**, never an in-place edit (**DEC-INV-6**).

---

## 12. Decision Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**DEC-INV-7**, **DEC-INV-8**) |
| **Context Boundary** | Consume Decision Context; do not own or reassemble it (**DEC-INV-2**) |
| **Conversation Boundary** | Do not own ConversationView (**DEC-INV-3**) |
| **Knowledge Boundary** | Do not own KnowledgeHits (**DEC-INV-4**) |
| **Memory Boundary** | Do not own MemoryView (**DEC-INV-5**) |
| **Commerce Boundary** | Do not become price/stock authority (AC-2) |
| **Channel-agnostic** | No Meta Graph / token logic inside Decision Evaluation |
| **Implementation Boundary** | No storage/API/schema/algorithm law in this document |
| **Learning Boundary** | Do not auto-activate Learning; Learn markers are post-Decision (`008`) |
| **Webhook latency** | Full Decision Evaluation is not performed on Meta webhook acknowledgment path (AC-8) |

---

## 13. Decision Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Recommendation satisfies Decision Policy and boundary rules |
| **Valid with warnings** | Recommendation has limitations (partial Context, soft confidence, constrained Claims) — proceed with signals |
| **Invalid** | Insufficient Context for the attempted Intent; publish Clarify / Hold / Escalate / Draft — never invent |
| **Unsafe** | Boundary or policy violation; block assertion; prefer Escalate / Hold / Clarify / Draft |

Checks (conceptual): Context present as sole input; no engine bypass; policy applied; Confidence and Explanation attached; no execution authority; bounds respected; Safety / Verification constraints respected; traceability seeds attached.

---

## 14. Decision Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — DecisionResult

| | |
|--|--|
| **Name** | **DecisionResult** (published result of Decision Evaluation) |
| **Producer** | Decision System |
| **Consumers** | Decision Coordinator (envelope), Response / adapter boundary, Evaluation (`010`), optional Learning markers (`008`) |
| **Guarantees** | Bounded; channel-neutral; immutable once published; recommendation-only; no execution authority; absence / failure Intents explicit (**DEC-INV-6**, **DEC-INV-10**) |
| **Non-guarantees** | Perfect foresight; merchant acceptance of recommendation; automatic channel send |

### 14.2 DecisionResult Identity

Each published DecisionResult has a unique **DecisionResult ID**.

DecisionResult ID supports observability, replay, debugging, and audit correlation.

DecisionResult ID is **not** a deterministic semantic identity for whole Decisions across systems.

Semantic Decision comparison remains at the **Context Fingerprint** level (Document `003`). Context Fingerprint remains owned by the Context Engine.

### 14.3 Input contracts

| Contract | Producer | Use |
|----------|----------|-----|
| **Decision Context** | Context Engine | **Sole** architectural input |
| **EffectiveConfig / CapabilitySet** | Platform Services (via boundary / Coordinator) | Decision Policy bounds |
| **SafetyVerdict** | Safety Engine | Constrains Candidates |
| **VerificationVerdict** | Verification Engine | Constrains Candidates / grounding |

ConversationView, KnowledgeHits, and MemoryView are **not** separate Decision System inputs — they arrive only as facets already assembled into Decision Context.

### 14.4 Versioning

- Additive Decision Intents or Policy classes → Minor
- Changing ownership away from DecisionResult recommendation semantics → ADR + Document `002`
- Breaking Result semantics that adapters depend on → coordinate with Document `001` Decision / Action terms and Document `009`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Decision Intent** | Additive vocabulary via contract versioning |
| **New Decision Policy** | Via Capability / Configuration / policy extension — Evaluation still publishes DecisionResult |
| **New Evaluation strategy** | Allowed behind DecisionResult contract |
| **Replace Decision backend** | Allowed if DecisionResult contract remains stable |
| **New Action type** (`001`) | Decision Engine extension; Coordinator unchanged unless Lifecycle changes (`002`) |

**Forbidden:** Executing Actions; owning Context / Conversation / Knowledge / Memory / Commerce; inventing missing information; channel-specific second Decision System; bypassing Context to read upstream engines; embedding execution authority in DecisionResult.

---

## 16. Failure Philosophy

If Decision cannot be completed safely or completely:

1. Publish **DecisionResult** indicating **Clarify**, **Hold**, **Escalate**, or **Draft** as appropriate
2. Attach Explanation and Confidence honestly
3. **Never fabricate missing information**
4. **Never invent policy**
5. **Never execute Actions**
6. **Never bypass Context**
7. Never assert ungrounded commerce facts

Incomplete Context is not an invitation to invent — it is a Clarification / Hold / Escalate / Draft recommendation.

---

## 17. Observability

Published DecisionResult should support:

- Traceability of Intent, selected recommendation, excluded Candidates (as signals), and failure Intents
- Correlation via **DecisionResult ID** with Context ID / Fingerprint (`003`)
- Policy, Safety, and Verification signals suitable for audit
- Evaluation / shadow under matched Context Fingerprints (`010`)

Observability artifacts must not become execution authority or ownership of Context / Conversation / Knowledge / Memory.

---

## 18. Replaceability

Decision System internals (including Evaluation strategies) may be replaced if:

- DecisionResult contract remains stable
- Ownership remains DecisionResult for the published recommendation
- Context / Conversation / Knowledge / Memory ownership remain untouched
- Documents `001`–`006` constraints and DEC-INV-* hold
- Evaluation still enforces bounded, non-inventing, recommendation-only publication
- Coordinator remains the sole lifecycle coordinator (`002`)

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Decision System contribution |
|-------------------------|------------------------------|
| **Understand** | Consumes assembled Decision Context; may form preliminary Intent / Candidates |
| **Clarify** | May publish DecisionResult with Clarify Intent when Context incomplete |
| **Verify** | Safety / Verification constrain Candidates (Document `002`) |
| **Decide** | Primary — Decision Evaluation publishes DecisionResult |
| **Respond** | DecisionResult informs recommendations only — Execution Boundary; adapters apply |
| **Learn** | May emit learnable markers after Decision; does not auto-activate Learning (`008`) |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| Decision Coordinator | Sequences Decision System modules |
| Decision Engine | Decision Evaluation → DecisionResult |
| Safety / Verification | Constrain Candidates before publish |

```text
004 Conversation → ConversationView
005 Knowledge    → KnowledgeHits
006 Memory       → MemoryView
003 Context      → Decision Context
007 Decision     → DecisionResult (recommendation)
008 Learning     → approved patterns (distinct)
009 Adapters     → apply Actions (Execution)
010 Evaluation   → assess DecisionResult under Context Fingerprint
011 Migration    → maps Plugin Sales Agent reasoning → Decision System
```

These modules contribute to or consume DecisionResult / Context; **none of them redefine Conversation SoT, Knowledge SoR, MemoryView ownership, or Decision Context**.

---

## 20. Validation Notes

Validated against Documents `001`–`006`, Hub Backend, and WordPress Plugin discovery. No production redesign.

### Conflict V-007-1 — Reasoning mixed inside Plugin Sales Agent

| | |
|--|--|
| **Conflict** | Production Sales Agent mixes intent understanding, catalog/KB use, verification, Semi/Full send posture, and reply selection inside Plugin-local pipelines. Hub Decision System is Target Architecture. |
| **Risk** | Treating Document `007` as shipped; dual reasoning paths without migration control. |
| **Suggested resolution** | Introduce Decision System through Document `011` Migration; map Plugin Sales Agent stages onto Decision Lifecycle + DecisionResult; keep default merchant path unchanged until flagged migration. |

### Conflict V-007-2 — DecisionResult confused with Context

| | |
|--|--|
| **Conflict** | Teams may treat recommendation payload as a second Context or mutate Context during Decide. |
| **Risk** | Ownership races; non-replayable Decisions. |
| **Suggested resolution** | Context Engine owns Decision Context (`003`); Decision System publishes immutable DecisionResult (**DEC-INV-1**, **DEC-INV-2**, **DEC-INV-6**). |

### Conflict V-007-3 — Bypassing Context to read engines

| | |
|--|--|
| **Conflict** | Decision Evaluation may be tempted to pull ConversationView / KnowledgeHits / MemoryView directly. |
| **Risk** | Duplicate assembly, inconsistent snapshots, Context First violation. |
| **Suggested resolution** | Decision Context is the sole architectural input; upstream views arrive only via Context assembly (`003`). |

### Conflict V-007-4 — Recommendation treated as execution authority

| | |
|--|--|
| **Conflict** | DecisionResult may be mistaken for permission to send or commit commerce. |
| **Risk** | Crossing Execution Boundary; AC-2 / AC-8 violations. |
| **Suggested resolution** | DecisionResult is recommendation-only (**DEC-INV-7**, **DEC-INV-8**, **DEC-INV-10**); adapters / Plugin / Woo apply Actions. |

### Conflict V-007-5 — Coordinator vs Evaluation ownership

| | |
|--|--|
| **Conflict** | Lifecycle control and recommendation selection may blur. |
| **Risk** | Reverse control; non-replaceable Decision Engine. |
| **Suggested resolution** | Document `002` stands: Coordinator sequences; Decision Engine evaluates; DecisionResult is the published recommendation (**DEC-INV-1**). |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Decision / Action / Lifecycle / Confidence terminology |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Decision System module map; Execution Boundary; Coordinator split |
| [`003-context-engine.md`](003-context-engine.md) | Decision Context — sole input |
| [`004-conversation-engine.md`](004-conversation-engine.md) | ConversationView via Context |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | KnowledgeHits via Context |
| [`006-memory-engine.md`](006-memory-engine.md) | MemoryView via Context |
| [`008-learning-engine.md`](008-learning-engine.md) | Post-Decision markers / approved patterns (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | Applies Actions from DecisionResult (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | Assesses DecisionResult under Context Fingerprints (Draft) |
| `011-migration-strategy.md` | Maps Plugin Sales Agent → Decision System |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Decision Evaluation → DecisionResult; Intent/Candidate/Policy/Confidence/Explanation; DEC-INV-1…10; quality attributes; Context-only input; Validation Notes V-007-1…5 |
| 0.1.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `008` Learning Engine Draft |
| 0.1.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.1.3 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.1.4 | 2026-07-30 | Documentation Lead | Reference Architecture **1.0.0** freeze — **Status → Approved** · Frozen with Documents `001`–`011` |
