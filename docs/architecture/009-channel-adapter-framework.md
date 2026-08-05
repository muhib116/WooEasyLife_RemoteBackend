# Channel Adapter Framework

| Field | Value |
|-------|--------|
| **Title** | Channel Adapter Framework |
| **Document ID** | `009` |
| **Version** | `0.1.2` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.6) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.5) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.5) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.4) |
| **Knowledge Engine** | [`005-knowledge-engine.md`](005-knowledge-engine.md) (**Draft** v0.1.6) |
| **Memory Engine** | [`006-memory-engine.md`](006-memory-engine.md) (**Approved** v0.2.3) |
| **Decision System** | [`007-decision-system.md`](007-decision-system.md) (**Draft** v0.1.2) |
| **Learning Engine** | [`008-learning-engine.md`](008-learning-engine.md) (**Draft** v0.1.1) |

---

## 1. Document Information

This document defines the **canonical Channel Adapter Framework** for the Wise AI Platform.

It answers one architectural question:

> **How does Wise apply DecisionResults to external platforms while keeping the Core completely channel-agnostic?**

It deepens **only** the Channel Adapter Framework: Channel Translation behavior, ExecutionRequest as the published artifact, Channel, Capability, Policy, Adapter Contract, ExecutionResult observation, ownership, boundaries, invariants, quality attributes, and the relationship to Decision.

It does **not** redefine Documents `001`–`008`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. If any statement here conflicts with Document `005`’s Knowledge law, **Document `005` wins**.
6. If any statement here conflicts with Document `006`’s Memory law, **Document `006` wins**.
7. If any statement here conflicts with Document `007`’s Decision law, **Document `007` wins**.
8. If any statement here conflicts with Document `008`’s Learning law, **Document `008` wins**.
9. This document must not reorganize Wise Core or redefine Core published artifacts.

**Terminology rules:**

- Throughout this document, **ExecutionRequest** means the published artifact of Channel Translation unless explicitly stated otherwise.
- Channel Adapter Framework sits in the **Channel Layer** (`001`) — **outside Wise AI Core** (`002`).
- Adapter Framework **owns Channel Translation / ExecutionRequest** — never Decision Context, ConversationView, KnowledgeHits, MemoryView, DecisionResult, LearningResult, Commerce authority, or business state.
- Adapters **apply recommendations**. They **never create recommendations** and **never perform reasoning**.

**Out of scope:** implementation, transport recipes, storage engines, Evaluation Framework internals (`010`), Migration playbooks (`011`), and any technology that would redefine architectural translation as an implementation plan.

---

## 2. Purpose

Provide a production-grade architectural definition of channel application so that:

- Wise Core remains **channel-agnostic** (AC-1) while external platforms receive applied recommendations
- **DecisionResult** stays a recommendation; adapters translate it into **ExecutionRequest** without rewriting Decisions
- New channels attach as **replaceable adapters**, not forks of Core intelligence
- Semi/Full, pause, and transport gates remain enforceable at the adapter / Plugin edge (`001`)
- Migration (`011`) can separate Plugin-mixed translation+reasoning into Decision System + Adapters without a big-bang rewrite

---

## 3. Scope

**In scope:**

- Channel Adapter Framework role (Channel Layer / outside Core per Documents `001`–`002`)
- Core concepts: Channel Adapter, Channel, Channel Capability, ExecutionRequest, ExecutionResult, Channel Translation, Channel Policy, Adapter Contract
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Decision (`007`) and Execution Boundary (`002`)
- Validation Notes against production reality

**Out of scope:**

- Owning or creating DecisionResult / Decision Context / ConversationView / KnowledgeHits / MemoryView / LearningResult
- Commerce fact authority / business state SoR
- Reasoning, Learning Evaluation, or Knowledge authorship
- Implementation of transport, retries, or connector stacks
- **Voice telephony / STT / TTS** — planned as future Channel adapters; Core may expose a spoken **DecisionResult side-channel** (`decision.voice`) without owning call audio (see plugin skill `voice-prep.md`)

---

## 4. Relationship to Documents 001–008

| Inherited concept | How Document `009` uses it |
|-------------------|----------------------------|
| **Adapters connect channels** (`001`) | Framework deepens how adapters translate and apply |
| **AC-1 / INV-4** (`001`) | Core stays channel-agnostic; adapters own transport |
| **Execution Boundary** (`002`) | Respond application occurs outside Core |
| **DecisionResult** (`007`) | Consumed as immutable recommendation input |
| **Conversation SoT** (`004`) | Remains Plugin / Conversation ownership — adapters do not become inbox SoR |
| **LearningResult** (`008`) | Never owned or applied as Decision substitute |
| **Channel Layer** (`001`) | Normalize inbound/outbound; capability flags; transport |

```text
Document 001 — Channel Layer / adapter philosophy / AC-1
        ↓
Document 002 — Execution Boundary; adapters outside Core
        ↓
Documents 003–006 — information artifacts (not owned here)
        ↓
Document 007 — DecisionResult (recommendation consumed here)
        ↓
Document 008 — LearningResult (not owned / not reasoned here)
        ↓
Document 009 — this document (deepens Channel Adapter Framework only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| ADP-1 | Perform **Channel Translation** and **publish ExecutionRequest** from DecisionResult |
| ADP-2 | Own **ExecutionRequest** only — never Core information or Decision artifacts |
| ADP-3 | **Apply** recommendations; never **create** or rewrite them |
| ADP-4 | Keep Core **channel-agnostic**; isolate channel concerns in adapters |
| ADP-5 | Honor **Channel Policy** and **Channel Capability** without inventing content |
| ADP-6 | Treat ExecutionRequest as **immutable**; never mutate DecisionResult |
| ADP-7 | Observe **ExecutionResult** without changing historical DecisionResult |
| ADP-8 | Keep translation **bounded**, **deterministic within policy**, and **traceable** |
| ADP-9 | Remain **replaceable** behind Adapter Contract |
| ADP-10 | Never become commerce or business-state authority |

---

## 5A. Adapter Invariants

Document `001` has Platform Invariants. Documents `003`–`008` have engine invariants. The following are the **laws of the Channel Adapter Framework**.

They remain true unless an ADR updates this document (and Document `002` when boundary impact applies).

| ID | Invariant |
|----|-----------|
| **ADP-INV-1** | Adapter Framework owns **ExecutionRequest**. |
| **ADP-INV-2** | Adapter Framework **never owns DecisionResult**. |
| **ADP-INV-3** | Adapter Framework **never owns Decision Context**. |
| **ADP-INV-4** | Adapter Framework **never performs reasoning**. |
| **ADP-INV-5** | ExecutionRequest is **immutable**. |
| **ADP-INV-6** | Adapter Framework is **channel isolated**. |
| **ADP-INV-7** | Adapter Framework **never owns commerce authority**. |
| **ADP-INV-8** | Adapter Framework **never mutates DecisionResult**. |
| **ADP-INV-9** | Translation is **bounded**. |
| **ADP-INV-10** | ExecutionResult **never changes historical DecisionResult**. |

**Notes:**

- **ADP-INV-4** — Intent selection, Candidate evaluation, and recommendation creation remain Decision System (`007`).
- **ADP-INV-6** — One channel’s policy/transport must not leak into another channel’s adapter or into Core.
- **ExecutionResult** is observed; it is not a Decision System ownership claim and does not rewrite DecisionResult.

---

## 5B. Adapter Quality Attributes

Not new platform requirements. Qualities good Channel Translation / ExecutionRequest should exhibit.

| Attribute | Meaning for Adapters |
|-----------|----------------------|
| **Portability** | Same DecisionResult can be translated for different Channels via Capability maps |
| **Replaceability** | Adapter backends may change behind Adapter Contract |
| **Determinism** | Same DecisionResult + Channel Policy + Capability → reasonably identical ExecutionRequest |
| **Traceability** | ExecutionRequest / ExecutionResult IDs correlate to DecisionResult ID |
| **Reliability** | Failures are explicit; retries do not invent new recommendation content |
| **Isolation** | Channel concerns stay in adapters; Core remains channel-agnostic |
| **Compatibility** | Translation respects declared Channel Capability |
| **Safety** | Channel Policy and Semi/Full / pause gates are honored |
| **Consistency** | Degraded Capability paths are signaled, not silently rewritten into new intent |
| **Boundedness** | Finite translation steps — never unbounded re-interpretation of DecisionResult |

---

## 6. Core Concepts

### 6.1 Channel Adapter

**Channel Adapter** is the architectural component responsible for **translating DecisionResult into platform-specific execution** (via ExecutionRequest) and participating in Channel Layer delivery.

Adapters **apply** recommendations. They do **not** own reasoning, Knowledge, Memory, Learning, or commerce authority.

### 6.2 Channel

**Channel** is an external platform Wise may serve through an adapter.

Illustrative Channels (non-exhaustive; aligned with Document `001` target posture):

| Channel | Meaning (conceptual) |
|---------|----------------------|
| **Facebook Messenger** | Messaging surface |
| **WhatsApp** | Messaging surface |
| **Instagram** | Messaging / comments family as product defines |
| **Web Chat** | On-site chat surface |
| **REST API** | Programmatic client surface |
| **WooCommerce** | Commerce-adjacent application surface (never Woo SoR ownership) |
| **Email** | Asynchronous message surface |

Channel vocabulary is illustrative. New Channels attach via Adapter Contract extension.

### 6.3 Channel Capability

**Channel Capability** is what one Channel can support for application of a recommendation.

Illustrative Capabilities (non-exhaustive):

| Capability | Meaning (conceptual) |
|------------|----------------------|
| **Text** | Plain textual delivery |
| **Image** | Image delivery |
| **Quick Reply** | Structured quick-reply constructs |
| **Button** | Button / CTA constructs |
| **Order Creation** | Application of an order-related Action *recommendation* — commerce authority remains Plugin/Woo |
| **Typing Indicator** | Transport-side presence signal |
| **Template** | Channel template constructs |

Capabilities constrain Translation. Missing Capability → degrade with warnings or Invalid — never invent unsupported constructs as if Core recommended them.

### 6.4 ExecutionRequest

**ExecutionRequest** is the **published artifact** of Channel Translation.

It represents **platform-oriented execution instructions** derived from DecisionResult under Channel Policy and Capability — without embedding new reasoning.

ExecutionRequest conceptually carries:

- ExecutionRequest ID (unique per publication)
- Correlation to DecisionResult ID
- Channel identity
- Capability-mapped application instructions
- Policy / validation signals
- Absence / degradation signals when Capability is incomplete

**ExecutionRequest must never contain new recommendation authority** that contradicts or replaces DecisionResult.

### 6.5 ExecutionResult

**ExecutionResult** is the **observed result** returned by channel execution (delivered, draft-only, rejected by gate, failed transport, degraded, etc.).

ExecutionResult:

- Has a unique ExecutionResult ID
- Correlates to ExecutionRequest ID / DecisionResult ID
- Is **not** owned by the Decision System
- **Never changes historical DecisionResult** (**ADP-INV-10**)

### 6.6 Channel Translation

**Channel Translation** is the **primary behavior** of the Adapter Framework.

It transforms DecisionResult into ExecutionRequest.

```text
Channel Translation (behavior)
        ↓
ExecutionRequest (published artifact)
```

This mirrors Core engines’ behavior → artifact pattern, while living **outside** Core:

```text
Decision Evaluation   → DecisionResult
Learning Evaluation   → LearningResult
Channel Translation   → ExecutionRequest
```

Channel Translation **must not define** (architecture non-goals): connector stacks, storage engines, schemas, or transport algorithms as architecture law.

### 6.7 Channel Policy

**Channel Policy** is the set of architectural rules governing one Channel: rate limits, Capability restrictions, transport constraints, compliance rules, Semi/Full / pause / already-answered gates as applicable.

Policy is honored during Translation. Adapters must not bypass Channel Policy to “force deliver” a recommendation.

### 6.8 Adapter Contract

**Adapter Contract** is the conceptual interface between Wise Core (DecisionResult / stable Decision contract) and Channels.

It guarantees:

- Core remains channel-agnostic
- Adapters consume recommendations and publish ExecutionRequest
- Replaceable adapters; stable exteriors (AC-5 posture)

Adapter Contract is not a schema document.

### 6.9 Concept stack (one question each)

```text
Adapter
  ↓ Channel      — Which external platform?
  ↓ Capability   — What can this Channel apply?
  ↓ Policy       — What constraints govern application?
  ↓ Translation  — How is DecisionResult mapped?
  ↓ Request      — What immutable ExecutionRequest is published?
```

---

## 7. Adapter Framework Role

| | |
|--|--|
| **Classification** | Channel Layer — **outside** Wise AI Core (`001` / `002`) |
| **Purpose** | Apply DecisionResults to external platforms without importing channel logic into Core |
| **Primary behavior** | **Channel Translation** |
| **Primary output** | Published **ExecutionRequest** |
| **Pipeline position** | After DecisionResult; across / after the Execution Boundary for Respond application |

### 7.1 Responsibilities

- Consume immutable **DecisionResult**
- Apply **Channel Policy** and **Channel Capability**
- Perform **Channel Translation** → publish **ExecutionRequest**
- Observe **ExecutionResult** without rewriting DecisionResult
- Enforce adapter-side gates (Semi/Full, pause, transport eligibility) without inventing new recommendation content
- Keep channel isolation (**ADP-INV-6**)
- Remain replaceable behind Adapter Contract

### 7.2 Non-responsibilities

- Creating or rewriting recommendations (**ADP-INV-4**, **ADP-INV-8**)
- Owning DecisionResult / Decision Context (**ADP-INV-2**, **ADP-INV-3**)
- Owning ConversationView / KnowledgeHits / MemoryView / LearningResult
- Owning commerce authority or business state (**ADP-INV-7**)
- Learning Evaluation or Knowledge authorship
- Becoming inbox SoT
- Absorbing Wise Core intelligence

---

## 8. Relationship to Decision

Document `007` law: DecisionResult is a read-only recommendation. Adapters apply; they do not Decide.

```text
DecisionResult
        ↓
Channel Translation
        ↓
ExecutionRequest
        ↓
External Platform
        ↓
ExecutionResult
```

| Rule | Detail |
|------|--------|
| **Consume** | DecisionResult is input only |
| **No mutation** | Adapters never mutate DecisionResult (**ADP-INV-8**) |
| **No reasoning** | Translation maps; it does not re-select Intent or invent content (**ADP-INV-4**) |
| **Observation** | ExecutionResult reports application outcome; history of DecisionResult stays intact (**ADP-INV-10**) |

Overall stack:

```text
Conversation → ConversationView
Knowledge    → KnowledgeHits
Memory       → MemoryView
Context      → Decision Context
Decision     → DecisionResult
Learning     → LearningResult
Adapter      → ExecutionRequest
```

---

## 9. Channel Translation Model

Architectural process — not implementation.

```text
DecisionResult
        ↓
Honor DecisionResult (no rewrite)
        ↓
Apply Channel Policy
        ↓
Map to Channel Capability
        ↓
Channel Translation
        ↓
Validate bounds / degradation signals
        ↓
Publish ExecutionRequest (immutable)
        ↓
Channel execution (connectors)
        ↓
Observe ExecutionResult
```

### 9.1 Translation principles

1. **Translate — never reason**
2. **Honor DecisionResult**
3. **Honor Channel Policy**
4. **Honor Channel Capability**
5. **Never invent content**
6. **No business ownership**
7. **Channel isolation**
8. **Replaceable adapters**

---

## 10. Ownership

| Concern | Owner |
|---------|--------|
| **ExecutionRequest / Channel Translation** | Adapter Framework (**ADP-INV-1**) |
| **ExecutionResult (observation)** | Channel execution path reports; not Decision System ownership |
| **DecisionResult** | Decision System (`007`) |
| **Decision Context** | Context Engine (`003`) |
| **ConversationView / inbox SoT** | Conversation Engine / Plugin (`004` / `001`) |
| **KnowledgeHits** | Knowledge Engine (`005`) |
| **MemoryView** | Memory Engine (`006`) |
| **LearningResult** | Learning Engine (`008`) |
| **Commerce facts / business state** | Plugin / WooCommerce |
| **Wise Core reasoning** | Documents `002` / `007` |

**Rule:** Other modules may **read** ExecutionRequest / ExecutionResult for observability. They must not mutate published ExecutionRequest in place. Republishing yields a new ExecutionRequest ID. DecisionResult remains unchanged.

---

## 11. Lifetime

| Concept | Lifetime |
|---------|----------|
| **ExecutionRequest** | Immutable once published (**ADP-INV-5**); request-scoped application artifact |
| **ExecutionResult** | Observed outcome for an ExecutionRequest; does not rewrite DecisionResult |
| **DecisionResult** | Remains unchanged through Translation and execution (**ADP-INV-8**, **ADP-INV-10**) |

Failed Translation publishes failure signals — it does not edit the originating DecisionResult.

---

## 12. Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary (`002`)** | Application of Respond occurs outside Core; Core never sends |
| **Reasoning Boundary** | No Intent/Candidate selection; no Decision Evaluation (**ADP-INV-4**) |
| **Decision Boundary** | Never own or mutate DecisionResult (**ADP-INV-2**, **ADP-INV-8**) |
| **Context Boundary** | Never own Decision Context (**ADP-INV-3**) |
| **Conversation / Knowledge / Memory / Learning** | Never own those published artifacts |
| **Commerce Boundary** | Never become price/stock/order SoR (**ADP-INV-7**, AC-2) |
| **Channel Isolation** | No cross-channel leakage into Core or peer adapters (**ADP-INV-6**, AC-1) |
| **Content Boundary** | Never invent recommendation content to force delivery |
| **Implementation Boundary** | No connector/storage/schema law in this document |
| **Webhook latency** | Heavy work stays off inbound acknowledgment path (AC-8 posture) |

---

## 13. Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Translation satisfies Channel Capability and Channel Policy |
| **Valid with warnings** | Capability degraded (e.g. template → text) with explicit signals |
| **Invalid** | Unsupported Capability for the recommended Action shape |
| **Rejected** | Channel Policy violation (gates, compliance, rate, pause) |

Checks (conceptual): DecisionResult honored; no invented content; Capability mapping explicit; Policy applied; bounds respected; no Decision mutation; traceability seeds attached.

---

## 14. Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — ExecutionRequest

| | |
|--|--|
| **Name** | **ExecutionRequest** (published result of Channel Translation) |
| **Producer** | Adapter Framework |
| **Consumers** | Channel connectors / transport edge |
| **Guarantees** | Immutable; bounded; channel-isolated; recommendation-derived; no new reasoning; correlates to DecisionResult (**ADP-INV-1**, **ADP-INV-5**) |
| **Non-guarantees** | Successful delivery; merchant/operator acceptance; commerce commit success |

### 14.2 ExecutionRequest Identity

Each published ExecutionRequest has a unique **ExecutionRequest ID**.

### 14.3 ExecutionResult Identity

Each observed ExecutionResult has a unique **ExecutionResult ID**.

ExecutionResult correlates to ExecutionRequest ID and DecisionResult ID for audit. It never rewrites historical DecisionResult (**ADP-INV-10**).

### 14.4 Input contracts

| Contract | Producer | Use |
|----------|----------|-----|
| **DecisionResult** | Decision System (`007`) | Sole recommendation input for Translation |
| **Channel Policy / Capability flags** | Channel Layer / configuration boundary | Constrain Translation |
| **Adapter Contract** | Platform boundary | Stable Core↔Channel interface (AC-5) |

### 14.5 Versioning

- Additive Channels or Capabilities → Minor
- Changing ownership toward reasoning or Decision mutation → ADR + Documents `001`/`002`
- Breaking Adapter Contract semantics → coordinate with Document `001` AC-5 / INV-4

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Channel** | New adapter behind Adapter Contract; Core unchanged (AC-1) |
| **New Capability** | Additive Capability vocabulary + Translation mapping |
| **New Translation strategy** | Allowed behind ExecutionRequest contract |
| **Replace Adapter backend** | Allowed if Adapter Contract / ExecutionRequest remain stable |

**Forbidden:** Reasoning; Learning Evaluation as adapter duty; Knowledge / Memory / Decision / Commerce ownership; mutating DecisionResult; channel-specific second brain inside Core.

---

## 16. Failure Philosophy

If Translation fails:

1. Publish **failure** / Invalid / Rejected signals on or instead of a successful ExecutionRequest path
2. **Do not modify DecisionResult**
3. **Do not retry by inventing new content**
4. **Do not perform reasoning** to “fix” Capability gaps
5. **Do not bypass Channel Policy**
6. Do not claim commerce authority to force outcomes

Capability gaps authorize degradation with warnings or Invalid — not a new Decision.

---

## 17. Observability

Published ExecutionRequest / observed ExecutionResult should support:

- Correlation to DecisionResult ID and ExecutionRequest / ExecutionResult IDs
- Capability degradation and Policy rejection visibility
- Delivery / draft-only / gate-reject outcomes without rewriting Decision history
- Evaluation / shadow consumption under matched Context Fingerprints when applicable (`010`)

Observability must not become Core channel coupling or Decision mutation.

---

## 18. Replaceability

Adapter Framework internals and per-Channel adapters may be replaced if:

- ExecutionRequest / Adapter Contract remain stable
- Ownership remains ExecutionRequest for published translation
- Core stays channel-agnostic (AC-1)
- Documents `001`–`008` constraints and ADP-INV-* hold
- Translation remains non-reasoning, non-mutating, and bounded

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Adapter Framework contribution |
|-------------------------|--------------------------------|
| **Understand → Decide** | None inside Core — adapters may supply normalized inbound materials earlier via Channel Layer, but do not Decide |
| **Respond** | Primary — Channel Translation → ExecutionRequest → channel application |
| **Learn** | May observe ExecutionResult as Learning Signals elsewhere (`008`); adapters do not own Learning |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| Above Execution Boundary | DecisionResult only |
| Below Execution Boundary | Adapter Framework applies Respond |

```text
007 Decision  → DecisionResult
009 Adapter   → ExecutionRequest → Channel → ExecutionResult
008 Learning  → may observe outcomes asynchronously (distinct)
010 Evaluation → may assess Decision vs Execution outcomes
011 Migration → separates Plugin mixed translate+reason paths
```

Adapters **never** redefine Conversation SoT, Knowledge SoR, MemoryView, Decision Context, DecisionResult, or LearningResult.

---

## 20. Validation Notes

Validated against Documents `001`–`008`, Hub Backend, and Plugin discovery. No production redesign.

### Conflict V-009-1 — Translation mixed with reasoning in production

| | |
|--|--|
| **Conflict** | Current Plugin Sales Agent mixes recommendation selection with channel send/draft application on the same path. |
| **Risk** | Treating Document `009` as shipped; keeping a Messenger-coupled second brain. |
| **Suggested resolution** | Separate Decision System (`007`) from Channel Adapters through Document `011` Migration; keep default merchant path unchanged until flagged cutover. |

### Conflict V-009-2 — Adapter treated as Decision owner

| | |
|--|--|
| **Conflict** | Adapters may rewrite replies or Intent when Capability is missing. |
| **Risk** | ADP-INV-4 / ADP-INV-8 violations; non-replayable Decisions. |
| **Suggested resolution** | Degrade or Invalid with signals; never invent a new DecisionResult. |

### Conflict V-009-3 — Commerce authority leakage

| | |
|--|--|
| **Conflict** | Order-related Capabilities may be mistaken for Woo SoR ownership. |
| **Risk** | AC-2 violation. |
| **Suggested resolution** | Adapters apply recommendations only; Plugin/Woo remain commerce authority (**ADP-INV-7**). |

### Conflict V-009-4 — Channel logic absorbed into Core

| | |
|--|--|
| **Conflict** | Delivery constructs and transport rules may creep into Wise Core. |
| **Risk** | AC-1 / INV-4 violations. |
| **Suggested resolution** | Delivery constructs remain adapter concerns (`001`); Core publishes DecisionResult only. |

### Conflict V-009-5 — ExecutionResult rewriting Decision history

| | |
|--|--|
| **Conflict** | Failed send may be used to alter the historical DecisionResult. |
| **Risk** | Audit/replay breakage (**ADP-INV-10**). |
| **Suggested resolution** | Observe ExecutionResult; keep DecisionResult immutable; future Decisions use new Context / Evaluation. |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Channel Layer; AC-1; INV-4; Supported Channels |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Execution Boundary; adapters outside Core |
| [`003-context-engine.md`](003-context-engine.md) | Decision Context — not owned here |
| [`004-conversation-engine.md`](004-conversation-engine.md) | Conversation projections / SoT — not owned here |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | KnowledgeHits — not owned here |
| [`006-memory-engine.md`](006-memory-engine.md) | MemoryView — not owned here |
| [`007-decision-system.md`](007-decision-system.md) | DecisionResult consumed for Translation |
| [`008-learning-engine.md`](008-learning-engine.md) | May observe ExecutionResult as Signals — distinct |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | May compare Decision vs Execution outcomes (Draft) |
| `011-migration-strategy.md` | Adapter cutover / separation from Plugin reasoning |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Channel Translation → ExecutionRequest; Capability/Policy/Contract; ExecutionResult observation; ADP-INV-1…10; Validation Notes V-009-1…5 |
| 0.1.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.1.2 | 2026-07-30 | Documentation Lead | Reference Architecture **1.0.0** freeze — **Status → Approved** · Frozen with Documents `001`–`011` |
