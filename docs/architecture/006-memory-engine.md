# Memory Engine

| Field | Value |
|-------|--------|
| **Title** | Memory Engine |
| **Document ID** | `006` |
| **Version** | `0.2.5` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.3) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.2) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.3) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.2) |
| **Knowledge Engine** | [`005-knowledge-engine.md`](005-knowledge-engine.md) (**Draft** v0.1.3) |

---

## 1. Document Information

This document defines the **canonical Memory Engine** for Wise AI Core.

It answers one architectural question:

> **What information should Wise remember across Decision requests without confusing memory with Conversation or Knowledge?**

It deepens **only** the Memory Engine: Memory Resolution behavior, MemoryView as the published artifact, Domain, Scope, Origin, Items, Provenance, Lifetime, ownership, boundaries, invariants, quality attributes, contracts, and the relationship to Context.

It does **not** redefine Documents `001`–`005`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. If any statement here conflicts with Document `005`’s Knowledge law, **Document `005` wins**.
6. This document must not reorganize the Core or redefine Decision Context, ConversationView, or KnowledgeHits.

**Terminology rules:**

- Throughout this document, **MemoryView** means the published artifact of Memory Resolution unless explicitly stated otherwise.
- Memory Engine **owns Memory Resolution / MemoryView only** — never Conversation SoT, never Knowledge SoR, never Decision Context, never business data SoR.
- Memory Engine **never becomes a system of record**.

**Out of scope:** implementation, APIs, database schemas, Laravel, WordPress, Redis, vector memory, embeddings, semantic search, prompts, cache engines, storage engines, Learning Engine internals (`008`), Decision System internals (`007`), channel adapters (`009`).

---

## 2. Purpose

Provide a production-grade architectural definition of remembered information so that:

- Wise retains **useful continuity across Decision requests** without treating inbox history as Memory SoR
- Context First receives a bounded **MemoryView** (or explicit absence)
- Conversation remains dialogue framing (`004`); Knowledge remains approved content (`005`); Memory remains retained continuity signals
- Memory updates occur only through **approved lifecycle boundaries** — not ad-hoc invention during Decide
- Migration (`011`) can map Plugin session/thread continuity fields onto MemoryView without a big-bang rewrite

---

## 3. Scope

**In scope:**

- Memory Engine role (AI Core Engine per Document `002`)
- Core concepts: Memory, Memory Domain, Memory Scope, Memory Origin, Memory Resolution, Memory Item, MemoryView, Memory Provenance, Memory Lifetime
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Context (`003`), Conversation (`004`), Knowledge (`005`), Decision (`007`), Learning (`008`), adapters (`009`)
- Validation Notes against production reality

**Out of scope:**

- Persisting Conversation / inbox SoT
- Authoring or retrieving approved Knowledge SoR
- Commerce fact authority
- Storage technology of any kind
- Implementation of Memory Resolution algorithms

---

## 4. Relationship to Documents 001–005

| Inherited concept | How Document `006` uses it |
|-------------------|----------------------------|
| **Memory** (`001`) | Short-horizon dialogue state (summary, emotion, language, asked flags, soft negotiation counts); not Order Intelligence fraud graphs by default — deepened here without changing law |
| **Session** (`001`) | Continuity episode that may span multiple Decision requests; Memory aids Session continuity |
| **Module map** (`002`) | Memory Engine = AI Core; owns **MemoryView** |
| **Execution Boundary** (`002`) | Memory Engine never sends or commits commerce |
| **Context First** (`003`) | MemoryView contributes continuity facets to Decision Context |
| **ConversationView** (`004`) | Frames dialogue for one Decision; is not Memory |
| **KnowledgeHits** (`005`) | Approved knowledge projections; are not Memory |
| **Downstream graph** (`003`) | Memory contributes to Context; does not redefine Context |

```text
Document 001 — Memory / Session terminology
        ↓
Document 002 — Memory Engine owns MemoryView
        ↓
Document 003 — Context consumes MemoryView; does not own Memory SoR
        ↓
Document 004 — Conversation frames Turns; not Memory
        ↓
Document 005 — Knowledge retrieves approved content; not Memory
        ↓
Document 006 — this document (deepens Memory Engine only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| MEM-1 | Perform **Memory Resolution** and **publish MemoryView** when Decision continuity requires remembered information |
| MEM-2 | Own **MemoryView only** — never Conversation, Knowledge, Commerce, or Decision Context |
| MEM-3 | Contribute MemoryView to Context without redefining Context |
| MEM-4 | Keep resolution **bounded**, **channel-agnostic**, and **explainable** |
| MEM-5 | Make missing memory **explicit** — never fabricate preferences or prior interactions |
| MEM-6 | Treat MemoryView as **read-only during Decision making** |
| MEM-7 | Allow Memory updates only through **approved lifecycle boundaries** |
| MEM-8 | Never become a system of record |
| MEM-9 | Remain replaceable behind the MemoryView contract |
| MEM-10 | Never cross the Execution Boundary |

---

## 5A. Memory Invariants

Document `001` has Platform Invariants. Documents `003`–`005` have engine invariants. The following are the **laws of the Memory Engine**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **MEM-INV-1** | Memory Engine owns **MemoryView**. |
| **MEM-INV-2** | Memory Engine **never owns Conversation**. |
| **MEM-INV-3** | Memory Engine **never owns Knowledge**. |
| **MEM-INV-4** | MemoryView is **immutable once published**. |
| **MEM-INV-5** | Memory Resolution is **bounded**. |
| **MEM-INV-6** | Memory Engine is **channel-agnostic**. |
| **MEM-INV-7** | Memory Engine **never crosses the Execution Boundary**. |
| **MEM-INV-8** | Memory **contributes to Context** but **never replaces Context**. |
| **MEM-INV-9** | Memory Engine **never owns Decision Context**. |
| **MEM-INV-10** | MemoryView is a **read-only projection** of remembered information. |

**Notes:**

- **MEM-INV-2 / MEM-INV-3** — Conversation SoT stays with Plugin / Conversation Engine framing; Knowledge SoR stays with business authorities / KnowledgeHits.
- **MEM-INV-8 / MEM-INV-9** — MemoryView feeds Context assembly; it is not a second Decision Context.
- **Not a SoR** — underlying retention stores (if any) are infrastructure behind contracts; Memory Engine publishes projections only.

---

## 5B. Memory Quality Attributes

Not new platform requirements. Qualities good Memory Resolution / MemoryView should exhibit.

| Attribute | Meaning for Memory |
|-----------|--------------------|
| **Relevance** | Items prioritized for the current Decision request |
| **Freshness** | Lifetime and staleness are respected and signaled |
| **Stability** | Remembered items do not thrash without cause across adjacent Decisions |
| **Traceability** | Resolution explainability shows what was included, excluded, or absent |
| **Provenance** | Origin and lifetime are conceptually attributable |
| **Consistency** | Items do not silently contradict each other without signals |
| **Determinism** | Same resolution inputs + pinned policy → reasonably identical MemoryView |
| **Boundedness** | Finite Memory Items / view size — never unbounded history dumps |

---

## 6. Core Concepts

### 6.1 Memory

From Document `001`:

> **Memory** — Short-horizon dialogue state (summary, emotion, language, asked flags, soft negotiation counts); not Order Intelligence fraud graphs by default.

Deepening (without changing law):

**Memory** is information **intentionally retained across Decision requests** to improve future reasoning and continuity.

Memory is **not**:

| Not Memory | Why / owner |
|------------|-------------|
| **Conversation** | Merchant-visible dialogue SoT / Turn Framing (`001` / `004`) |
| **Knowledge** | Approved business content / KnowledgeHits (`001` / `005`) |
| **Commerce** | Price/stock authority (Plugin / Woo) |
| **Decision** | Recommendation output (`007`) |
| **Context** | Decision Context assembly (`003`) |
| **Order Intelligence fraud graphs** | Separate plane unless ADR (`001`) |

### 6.2 Memory Domain

**Memory Domain** is the **category of remembered information** — what *kind* of continuity is retained — independent of storage.

Domains are **not** Origins. A Domain classifies memory; an Origin answers where the Memory Item came from.

| Illustrative Domain | Meaning (conceptual) |
|---------------------|----------------------|
| **Customer Preferences** | Retained customer preference signals relevant to future Decisions |
| **Merchant Preferences** | Retained merchant/operator preference signals for AI behavior |
| **Interaction History** | Compact continuity markers from prior Decisions (not full Conversation SoT) |
| **User Defaults** | Default choices previously established for continuity |
| **Learned Preferences** | Preference signals admitted only via approved Learning / lifecycle boundaries (`008`) — never invented mid-Decide |
| **Operational Context** | Short-horizon operational continuity (language, asked flags, soft negotiation counts, emotion/summary markers per `001`) |

The domain vocabulary above is **illustrative and intentionally non-exhaustive**. Future domains may be introduced through additive versioning without changing the MemoryView contract.

**Domain vs Conversation vs Knowledge:**

| Concept | Answers |
|---------|---------|
| **Conversation** | What was said in the dialogue (SoT / framed Turns)? |
| **Knowledge** | What approved content may we ground on? |
| **Memory Domain** | What *kind* of retained continuity signal is this? |

### 6.3 Memory Scope

**Memory Scope** is the boundary within which remembered information applies for a Decision (e.g. store, thread/session, customer identity as projected, operator, language — channel-neutral).

Resolution must not return Items outside declared Scope without explicit signaling.

**Scope vs Domain:** Scope bounds *applicability for this Decision*; Domain classifies *kind of memory*.

### 6.4 Memory Origin

**Memory Origin** answers: **Where did this remembered information come from?**

Memory is generally **created**, not retrieved from a knowledge repository. Its architectural identity is origin — not storage. Storage remains intentionally hidden behind contracts; Memory Engine never becomes a SoR.

Illustrative Origins (non-exhaustive):

| Origin | Meaning (conceptual) |
|--------|----------------------|
| **Previous Decision** | Continuity markers emitted from a prior Decision / Learn boundary |
| **Operator Default** | Merchant/operator-set defaults admitted via approved boundaries |
| **Learning Engine** | Preference or pattern Items admitted only via approved Learning activation (`008`) |
| **Adapter Projection** | Channel-neutral continuity markers projected at the Core boundary |

Origins are not Meta transport objects and are not Knowledge Sources. Knowledge uses Source because approved content exists in repositories; Memory uses Origin because continuity is created.

**Why not “Memory Source”:** Source and “how it entered retention” overlap. One concept — Origin — answers the single question.

### 6.5 Memory Resolution

**Memory Resolution** is the **primary behavior** of the Memory Engine.

It selects remembered information relevant to one Decision request and publishes MemoryView.

```text
Memory Resolution (behavior)
        ↓
MemoryView (published artifact)
```

This mirrors Documents `003`–`005`:

```text
Context Assembly      → Decision Context
Turn Framing          → ConversationView
Knowledge Retrieval   → KnowledgeHits
Memory Resolution     → MemoryView
```

Memory Resolution **must not define** (architecture non-goals): databases, Redis, vector memory, embeddings, semantic search, prompts, cache implementation, storage engines, APIs, or schemas.

### 6.6 Memory Item

**Memory Item** is a single remembered fact, preference, or continuity marker within a MemoryView.

A Memory Item conceptually may carry:

- Content of the remembered signal (projection)
- **Memory Domain**
- **Memory Scope** applicability
- **Memory Provenance** (origin, lifetime)
- Relevance / inclusion signals

Memory Items are **not** Turns, not KnowledgeHits, and not commerce facts.

### 6.7 MemoryView

**MemoryView** is the **published artifact** produced by Memory Resolution.

Document `002` ownership:

| Module | Owns |
|--------|------|
| **Memory Engine** | **MemoryView** |

MemoryView includes (conceptual):

- MemoryView ID (unique per publication)
- Bounded set of Memory Items
- Domain / Scope / Origin signals
- Provenance and Lifetime characteristics
- Absence / incompleteness signals when required memory is missing

**MemoryView is NOT:**

| Not MemoryView | Owner / doc |
|----------------|-------------|
| ConversationView | Conversation Engine (`004`) |
| KnowledgeHits | Knowledge Engine (`005`) |
| Decision Context | Context Engine (`003`) |
| Decision / Action | Decision System (`007`) |
| Commerce authority | Plugin / Woo |

MemoryView may **reference** Memory Domain, Memory Scope, Memory Origin, Memory Lifetime, and Provenance. It must **never** contain execution authority.

### 6.8 Memory Provenance

**Memory Provenance** conceptually records, for each Item (or view):

| Element | Meaning |
|---------|---------|
| **Origin** | Where the Item came from (see Memory Origin) |
| **Lifetime** | Intended persistence characteristics (see Memory Lifetime) |

Provenance supports audit, Evaluation, and debugging. It is not an API schema.

### 6.9 Memory Lifetime

**Memory Lifetime** represents the **intended persistence characteristics** of remembered information (conceptual classes, not TTLs as code).

Illustrative lifetime classes (non-exhaustive):

| Lifetime class | Meaning |
|----------------|---------|
| **Ephemeral** | Valid only for the immediate Session / short horizon |
| **Session-bound** | Retained for the Wise Session over a Conversation |
| **Durable preference** | Longer-lived preference admitted via approved boundaries |
| **Revoked / expired** | Explicitly no longer eligible for Resolution |

Lifetime does not authorize unbounded retention of raw chat as Memory SoR (**AC-9** posture remains).

### 6.10 Concept stack (one question each)

```text
Memory
  ↓ Domain   — What kind of continuity?
  ↓ Scope    — Where does it apply for this Decision?
  ↓ Origin   — Where did it come from?
  ↓ Lifetime — How long is it intended to persist?
```

Contrast with Knowledge (`005`): Domain → Source → Authority → Revision (repository-grounded truth). Memory does **not** use Source — continuity is created, not fetched from a knowledge repository.

---

## 7. Memory Engine Role

| | |
|--|--|
| **Classification** (`002`) | AI Core Engine |
| **Purpose** | Short-horizon / retained continuity for Context First without owning inbox or knowledge SoR |
| **Primary behavior** | **Memory Resolution** |
| **Primary output** | Published **MemoryView** |
| **Pipeline position** | Contributes continuity during Context assembly / Understand |

### 7.1 Responsibilities

- Accept resolution signals (Session/Thread identity, Scope, Domain hints, capability/config bounds)
- Perform **Memory Resolution** over eligible remembered Items only
- **Publish MemoryView** with Provenance and Lifetime references
- Mark absence, staleness, and out-of-scope exclusions explicitly
- Remain **read-only during Decision making** (no mid-Decide mutation of the published view)
- Contribute MemoryView to Context without owning Decision Context (**MEM-INV-8**, **MEM-INV-9**)

### 7.2 Non-responsibilities

- Owning Conversation / message SoT (**MEM-INV-2**)
- Owning Knowledge SoR or publishing KnowledgeHits (**MEM-INV-3**)
- Owning or inventing commerce facts
- Choosing Actions, Safety, Verification, or lifecycle coordination
- Channel send / Graph / commerce commits
- Becoming a persistence SoR
- Defining storage/search/embedding technology as architecture law
- Activating Learning samples (Learning Engine / approve workflows)

---

## 8. Relationship to Context

Document `003` law: Memory contributes to Context; it does not redefine Context.

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
```

| Rule | Detail |
|------|--------|
| **Contribution** | MemoryView feeds Context continuity facets |
| **Non-redefinition** | Memory Engine must not publish a competing Decision Context (**MEM-INV-8**, **MEM-INV-9**) |
| **Separation** | ConversationView frames dialogue; KnowledgeHits ground approved content; MemoryView carries retained continuity — none replaces another |
| **Immutability** | Published MemoryView is immutable (**MEM-INV-4**); Context republication may request a new MemoryView publication |
| **Identity** | MemoryView ID supports audit/replay; Context ID / Fingerprint remain Context Engine concepts (`003`) |

---

## 9. Memory Resolution Model

Architectural process — not implementation.

```text
Decision request materials / Context assembly signals
  (Session/Thread identity, Scope, Domain hints, bounds)
        ↓
Memory Engine performs Memory Resolution
  — eligible Items only
  — within Domain + Scope
  — Origin / Lifetime respected
  — bounded result set
  — read-only for this Decision
        ↓
Attach Provenance (origin, lifetime)
Mark absence / freshness / relevance exclusions
        ↓
Validate bounds + non-invention + no Conversation/Knowledge embed
        ↓
Publish MemoryView (immutable for this request)
        ↓
Context Engine / Decision consumers
```

### 9.1 Resolution principles

1. **Remember intentionally** — not every Turn becomes Memory.
2. **Project, don’t own SoR** — MemoryView is a read-only projection (**MEM-INV-10**).
3. **Bound before enrich** — finite Items (**MEM-INV-5**).
4. **Relevance over dump** — prioritize Items for the current Decision.
5. **No fabrication** — missing memory → explicit absence.
6. **No preference invention** — never infer or guess customer/merchant preferences.
7. **Channel-neutral** — no Meta hard dependency (**MEM-INV-6**).
8. **No execution** — never authorize send/commerce (**MEM-INV-7**).
9. **Updates outside Decide** — Memory updates only through approved lifecycle boundaries (Learn markers, operator defaults, approved Learning activation — not silent mutation inside Decision Engine).

---

## 10. Memory Ownership

| Concern | Owner |
|---------|--------|
| **MemoryView / Memory Resolution** | Memory Engine (**MEM-INV-1**) |
| **Conversation / Thread / message SoT** | Plugin; framed by Conversation Engine (**MEM-INV-2**) |
| **Knowledge SoR / KnowledgeHits** | Business authorities / Knowledge Engine (**MEM-INV-3**) |
| **Decision Context** | Context Engine (`003`) |
| **Commerce facts authority** | Plugin / WooCommerce |
| **Learning activation** | Learning Engine + approve workflows (`008`) |
| **Channel transport** | Adapters / Hub edge |
| **Underlying retention store (if any)** | Infrastructure behind contracts — **not** Memory Engine SoR claim |

**Rule:** Other modules may **read** a published MemoryView. They must not mutate it in place. Republishing yields a new MemoryView ID for that request.

---

## 11. Memory Lifetime

| Concept | Lifetime |
|---------|----------|
| **Remembered Items (conceptual retention)** | Per Memory Lifetime class; admitted via approved boundaries |
| **MemoryView** | Request / decision scoped — published for one Decision resolution; then read-only |
| **Provenance on view** | Frozen at publication time for that MemoryView |
| **Session** (`001`) | May span multiple MemoryView publications over time |

### 11.1 Freshness

Mark freshness/absence when:

- Required Domain/Scope has no eligible Items
- Lifetime class marks Items expired/revoked
- Origin/approval path for Learned Preferences cannot be confirmed
- Scope signals are missing or contradictory

Freshness failures authorize safer downstream Actions (Clarify / Hold / Escalate / Draft) — not fabricated memory.

### 11.2 Updates through approved lifecycle boundaries

Memory may be **updated** (for future Decisions) only through approved boundaries, for example:

- Learn-stage markers after a Decision completes
- Operator-set defaults
- Approved Learning activation (`008` / AC-4)
- Adapter-projected continuity markers at the Core boundary

Updates **do not** mutate an already published MemoryView for an in-flight Decision (**MEM-INV-4**).

---

## 12. Memory Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**MEM-INV-7**) |
| **Conversation Boundary** | No owning Conversation SoT or replacing ConversationView (**MEM-INV-2**) |
| **Knowledge Boundary** | No owning Knowledge SoR or publishing KnowledgeHits (**MEM-INV-3**) |
| **Context Boundary** | Do not redefine or replace Decision Context (**MEM-INV-8**, **MEM-INV-9**) |
| **Commerce Boundary** | Do not become price/stock authority (AC-2) |
| **SoR Boundary** | Memory Engine never claims to be the system of record |
| **Channel-agnostic** | No Meta Graph / token logic inside Memory Engine (**MEM-INV-6**, AC-1, AC-3) |
| **Implementation Boundary** | No Redis/vector/embedding/prompt/storage law in this document |
| **PII / training** | Raw chat must not become default Hub training via Memory (AC-9) |
| **Webhook latency** | Full resolution is not performed on Meta webhook acknowledgment path (AC-8) |

---

## 13. Memory Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | MemoryView is bounded, in scope, coherent enough for continuity |
| **Valid with warnings** | Partial coverage, staleness warnings, or truncated Items — proceed with signals |
| **Valid — empty** | Explicit absence of remembered Items (not an error if none required) |
| **Invalid — incomplete** | Required memory missing for the attempted path; downstream must Clarify / Hold / Escalate — never invent |
| **Invalid — unsafe projection** | Conversation/Knowledge/Commerce embeds, execution authority, or fabricated Items |

Checks (conceptual): bounds respected; Domain/Scope coherent; provenance present or explicitly unknown; no fabricated preferences; no Conversation/Knowledge SoR embed; no execution authority; read-only publication; traceability seeds attached.

---

## 14. Memory Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — MemoryView

| | |
|--|--|
| **Name** | **MemoryView** (published result of Memory Resolution) |
| **Producer** | Memory Engine |
| **Consumers** | Context Engine, Decision Coordinator / Decision Engine, Conversation Engine (optional read), Evaluation |
| **Guarantees** | Bounded; channel-neutral; immutable once published; absence explicit; no execution authority; read-only projection (**MEM-INV-10**) |
| **Non-guarantees** | Complete interaction history; perfect preference models; durable SoR semantics |

### 14.2 MemoryView Identity

Each published MemoryView has a unique **MemoryView ID**.

MemoryView ID supports observability, replay, debugging, and audit correlation.

MemoryView ID is **not** a deterministic comparison identity for whole Decisions.

Unlike Context, MemoryView does **not** define a Decision-level Fingerprint. Semantic Decision comparison remains at the Context level (Document `003`).

### 14.3 Input contracts

| Contract | Producer | Use |
|----------|----------|-----|
| **Resolution request signals** | Context assembly / Coordinator boundary | Scope, Domain hints, Session/Thread identity, bounds |
| **EffectiveConfig / CapabilitySet** | Platform Services (via boundary) | Resolution policy bounds |
| **Eligible memory projections** | Approved lifecycle boundaries / adapters | Materials eligible for Items |
| **ConversationView** (optional read) | Conversation Engine | Continuity hints only — not ownership |
| **LearningHints** (optional) | Learning Engine | Approved patterns — not auto-activate |

### 14.4 Versioning

- Additive Domain, Origin, or Lifetime classes → Minor (vocabularies illustrative / non-exhaustive)
- Changing ownership away from MemoryView-only → ADR + Document `002`
- Breaking view semantics that Context depends on → coordinate with Document `003`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Memory Domain** | Additive vocabulary via contract versioning |
| **New Memory Origin** | Via approved lifecycle boundary; Memory Engine still publishes projections only |
| **New Lifetime class** | Additive; does not imply unbounded raw-chat retention |
| **Tighter bounds** | EffectiveConfig / Capability policy |
| **Replace retention backend** | Allowed if MemoryView contract remains stable |

**Forbidden:** Treating Conversation history dump as MemoryView; treating KnowledgeHits as Memory; channel-specific second Memory Engine; inventing preferences in Prompt/Model Gateway; Hub claiming Memory SoR that bypasses AC-4/AC-9.

---

## 16. Failure Philosophy

If required memory cannot be resolved:

1. Publish **explicit absence** or Invalid — incomplete with reason codes
2. Allow Context / Decision paths to Clarify, Hold, Escalate, or Draft-only
3. **Never fabricate memories**
4. **Never infer user preferences**
5. **Never invent merchant preferences**
6. **Never guess previous interactions**
7. Never send or commit commerce from the Memory Engine
8. Never call Model Gateway to invent missing Memory Items

---

## 17. Observability

Published MemoryView should support:

- Traceability of included / excluded / absent Items
- Provenance and Lifetime visibility
- Correlation via **MemoryView ID**, and with Context ID when embedded in Decision Context (`003`)
- Evaluation/shadow under matched Context Fingerprints

Observability artifacts must not become Conversation SoT, Knowledge SoR, or Memory SoR claims.

---

## 18. Replaceability

Memory Engine internals may be replaced if:

- MemoryView contract remains stable
- Ownership remains MemoryView-only
- Conversation and Knowledge ownership remain untouched
- Documents `001`–`005` constraints and MEM-INV-* hold
- Resolution still enforces bounded, non-inventing, read-only publication behavior

Retention technology may churn without rewriting Decision Engine or Context ownership.

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Memory Engine contribution |
|-------------------------|----------------------------|
| **Understand** | Primary — resolve MemoryView for continuity |
| **Clarify** | Absence / incompleteness may justify Clarification |
| **Verify** | Memory does not replace Grounding via Knowledge/Commerce |
| **Decide** | MemoryView informs continuity; Engine does not Decide; view stays read-only |
| **Respond** | None — Execution Boundary |
| **Learn** | May emit/update markers for future retention via approved boundaries; does not auto-activate Learning |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| With Context continuity | Perform Memory Resolution; publish MemoryView |
| Decision System | Consume MemoryView via Context |

```text
004 Conversation → ConversationView (dialogue framing)
005 Knowledge    → KnowledgeHits (approved content)
006 Memory       → MemoryView (retained continuity)
003 Context      → Decision Context (owns assembly)
007 Decision     → consumes Context
008 Learning     → approved patterns / activation (distinct)
011 Migration    → maps Plugin continuity fields → MemoryView
```

These engines contribute to or consume MemoryView / Context; **none of them redefine Conversation SoT, Knowledge SoR, or Decision Context**.

---

## 20. Validation Notes

Validated against Documents `001`–`005`, Hub Backend, and WordPress Plugin discovery. No production redesign.

### Conflict V-006-1 — Memory Engine not in production runtime

| | |
|--|--|
| **Conflict** | Production Sales Agent keeps continuity (language, asked flags, summaries, soft counts) inside Plugin session/thread state; Hub Memory Engine is Target Architecture. |
| **Risk** | Treating Document `006` as shipped. |
| **Suggested resolution** | Introduce Memory Engine through Document `011` Migration; map Plugin continuity fields onto MemoryView; keep default merchant path unchanged until flagged migration. |

### Conflict V-006-2 — Conversation history mistaken for Memory

| | |
|--|--|
| **Conflict** | Teams may dump inbox history into MemoryView. |
| **Risk** | Dual SoT and Conversation/Memory confusion. |
| **Suggested resolution** | Conversation SoT stays Plugin; ConversationView frames Turns (`004`); MemoryView holds intentional retained Items only (**MEM-INV-2**). |

### Conflict V-006-3 — Knowledge mistaken for Memory

| | |
|--|--|
| **Conflict** | FAQ/product content treated as “remembered.” |
| **Risk** | Bypassing Knowledge Authority / approval (AC-4). |
| **Suggested resolution** | Approved content remains KnowledgeHits (`005`); Memory retains continuity preferences/markers (**MEM-INV-3**). |

### Conflict V-006-4 — Learning vs Memory

| | |
|--|--|
| **Conflict** | Learned preferences may be written without approval. |
| **Risk** | Invented or unapproved preference Memory. |
| **Suggested resolution** | Learned Preferences Domain Items enter only via approved Learning / lifecycle boundaries (`008`); never fabricate during Decide. |

### Conflict V-006-5 — MemoryView vs Context ownership

| | |
|--|--|
| **Conflict** | Both may carry continuity materials. |
| **Risk** | Duplicate ownership / mutation races. |
| **Suggested resolution** | Memory Engine owns MemoryView; Context Engine owns Decision Context and may embed/reference MemoryView (`003` / **MEM-INV-1**, **MEM-INV-8**, **MEM-INV-9**). |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Memory / Session terminology |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Module map; MemoryView ownership |
| [`003-context-engine.md`](003-context-engine.md) | Context consumes MemoryView |
| [`004-conversation-engine.md`](004-conversation-engine.md) | ConversationView — distinct from Memory |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | KnowledgeHits — distinct from Memory |
| [`007-decision-system.md`](007-decision-system.md) | Consumes MemoryView via Context (Draft) |
| [`008-learning-engine.md`](008-learning-engine.md) | Approved Learning → future Memory Items (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | May project continuity markers (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | May evaluate under matched Context Fingerprints (Draft) |
| `011-migration-strategy.md` | Maps Plugin continuity → Memory Engine |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Memory Resolution → MemoryView; Domain/Source/Scope/Item/Provenance/Lifetime; MEM-INV-1…10; quality attributes; Context relationship; Validation Notes V-006-1…5 |
| 0.2.0 | 2026-07-30 | Documentation Lead | Replace Memory Source with **Memory Origin**; Provenance = Origin + Lifetime; concept stack (Domain/Scope/Origin/Lifetime); ownership artifact = MemoryView only; **Status → Approved** |
| 0.2.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.2.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `008` Learning Engine Draft |
| 0.2.3 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.2.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.2.5 | 2026-07-30 | Documentation Lead | Included in Wise AI Platform Reference Architecture **1.0.0** freeze (`001`–`011`) |
