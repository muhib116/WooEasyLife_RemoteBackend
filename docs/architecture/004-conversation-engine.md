# Conversation Engine

| Field | Value |
|-------|--------|
| **Title** | Conversation Engine |
| **Document ID** | `004` |
| **Version** | `0.2.6` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.1) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.0) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.1) |

---

## 1. Document Information

This document defines the **canonical Conversation Engine** for Wise AI Core.

It deepens **only** the Conversation Engine: what Conversation, Thread, and Turn mean for Wise; what ConversationView and Turn Framing are; Conversation Stages; ownership; lifetime; boundaries; invariants; quality attributes; contracts; and the relationship to Context.

It does **not** redefine Documents `001`, `002`, or `003`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law (invariants, ownership, Context ID / Fingerprint), **Document `003` wins**.
4. This document must not reorganize the Core or redefine Decision Context.

**Terminology rules:**

- Throughout this document, **ConversationView** is the Wise-facing framed view of a conversation for one Decision.
- **Conversation** (the SoT dialogue) remains Plugin-owned unless explicitly stated otherwise.
- Conversation Engine **owns ConversationView / Turn Framing only** — never the Conversation SoT.

**Out of scope:** implementation, APIs, database schemas, Laravel, WordPress, Messenger / Meta Graph APIs, Mid dedupe, unread badges, send/typing transport, intent/sentiment/emotion NLP, embeddings, RAG, summarization algorithms, token budgets, LLM prompts/providers, Context assembly internals (`003`), Memory store internals (`006`), Decision System internals (`007`), channel adapter internals (`009`).

---

## 2. Purpose

Provide a production-grade architectural definition of conversation framing so that:

- Wise reasons about dialogue continuity without owning inbox persistence
- Context First receives a bounded, channel-neutral **ConversationView**
- Channel adapters project threads/turns without becoming the intelligence layer
- Decision Engine consumes Turn Framing, not raw Meta or Plugin private structures
- Migration (`011`) can map production Messenger/Comments inbox concepts onto ConversationView without a big-bang rewrite

---

## 3. Scope

**In scope:**

- Conversation Engine role (AI Core Engine per Document `002`)
- Definitions: Conversation, Thread, Turn, ConversationView, Turn Framing, Conversation Stages
- Ownership (ConversationView vs Plugin Conversation SoT)
- Lifetime and freshness of ConversationView
- Boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Context (`003`), Memory (`006`), Decision System (`007`), adapters (`009`)
- Validation Notes against production reality

**Out of scope:**

- Persisting messages, threads, unread state, or merchant inbox UX
- Meta webhook/Graph send paths
- Knowledge retrieval (`005`)
- Memory retention internals (`006`)
- Action selection / Safety / Verification (`007`)
- Implementation of any of the above

---

## 4. Relationship to Documents 001–003

| Inherited concept | How Document `004` uses it |
|-------------------|----------------------------|
| **Conversation** (`001`) | Merchant-visible dialogue thread; Plugin inbox SoT today |
| **Session** (`001`) | Wise decision episode over a conversation; not WooCommerce session |
| **Context First** (`001` / `003`) | ConversationView contributes continuity to Decision Context |
| **Module map** (`002`) | Conversation Engine = AI Core; owns Turn Framing |
| **Execution Boundary** (`002`) | Conversation Engine never sends or commits commerce |
| **CX-INV-4** (`003`) | Context never owns Conversation SoT — Conversation Engine also never owns it |
| **Downstream graph** (`003` §20.1) | Conversation contributes to Context; does not redefine Context |

```text
Document 001 — Conversation / Session terminology
        ↓
Document 002 — Conversation Engine owns Turn Framing
        ↓
Document 003 — Context consumes ConversationView; Plugin remains SoT
        ↓
Document 004 — this document (deepens Conversation Engine only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| CV-1 | Publish exactly one **ConversationView** per Decision request that needs dialogue framing |
| CV-2 | Keep Conversation Engine **channel-agnostic** — no Meta/Widget transport logic |
| CV-3 | Never become Conversation SoT — Plugin (or future channel inbox owner) remains authority |
| CV-4 | Frame the **current Turn** relative to a bounded Thread window |
| CV-5 | Expose **Conversation Stages** as channel-neutral progress signals, not product UI labels alone |
| CV-6 | Contribute to Context without redefining Context (`003`) |
| CV-7 | Remain replaceable behind the ConversationView contract |
| CV-8 | Fail with explicit incompleteness when projection is missing — never invent turns |

---

## 5A. Conversation Invariants

Document `001` has Platform Invariants. Document `003` has Context Invariants. The following are the **laws of the Conversation Engine**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **CV-INV-1** | Conversation Engine owns **ConversationView** (Turn Framing) only. |
| **CV-INV-2** | Plugin (or designated inbox owner) remains **Conversation SoT**. |
| **CV-INV-3** | ConversationView is **bounded** — never an unbounded history dump. |
| **CV-INV-4** | ConversationView is **immutable once published** for a Decision request. |
| **CV-INV-5** | Conversation Engine is **channel-agnostic**. |
| **CV-INV-6** | Missing turns/projections are **explicit**, never fabricated. |
| **CV-INV-7** | Conversation Engine **never crosses the Execution Boundary** (no send, no order, no commerce commit). |
| **CV-INV-8** | Conversation Engine **does not redefine Context**; it contributes ConversationView to Context assembly. |
| **CV-INV-9** | ConversationView **never owns or embeds** Knowledge, Memory, Commerce authority, or Decision outputs. |

**Notes:**

- **CV-INV-9** — ConversationView may reference dialogue continuity only. Knowledge, Memory, Commerce, and Decision remain owned by their respective engines. ConversationView must not become a secondary Context object.

---

## 5B. Conversation Quality Attributes

Not new platform requirements. Qualities a good ConversationView should exhibit.

| Attribute | Meaning for ConversationView |
|-----------|------------------------------|
| **Completeness** | Current Turn and required window turns are present, or incompleteness is marked |
| **Boundedness** | Finite window size per EffectiveConfig / Capability policy |
| **Relevance** | The bounded Turn window prioritizes Turns most relevant to framing the current Decision request |
| **Ordering** | Turns are ordered consistently for framing (oldest→newest or explicitly declared) |
| **Role clarity** | Speaker roles (customer / operator / system / assistant-candidate) are explicit |
| **Stage clarity** | Conversation Stage is present or explicitly unknown |
| **Traceability** | Framing reason codes show what was included, truncated, or missing |
| **Channel neutrality** | No hard dependency on Meta/Messenger private shapes |
| **Determinism** | Same projection + framing policy → reasonably identical ConversationView |

---

## 6. Core Concepts

### 6.1 Conversation

From Document `001`:

> **Conversation** — The merchant-visible dialogue thread (Plugin inbox SoT for Messenger/Comments today).

Deepening (without changing law):

- Conversation is the **persistent dialogue** merchants see in inbox UX.
- Wise does **not** own Conversation persistence.
- Adapters project a bounded slice of Conversation into Core for framing.

### 6.2 Thread

**Thread** is the channel-neutral identity of one ongoing dialogue between a storefront presence and a counterpart (customer/lead), used to group Turns.

| | |
|--|--|
| **Is** | Logical dialogue identity + continuity handle for framing |
| **Is not** | Meta thread API object; not Plugin table schema; not Decision Context |
| **SoT** | Lives with Conversation SoT (Plugin for Messenger/Comments today) |
| **In Wise** | Appears as thread identity + projected signals inside ConversationView |

Multiple Sessions (Document `001`) may occur over one Thread over time.

### 6.3 Turn

**Turn** is one atomic contribution to a Thread: a single speaker’s input or recorded system event relevant to dialogue sequencing.

| Aspect | Meaning |
|--------|---------|
| **Speaker role** | Customer, operator/human, system, or assistant-candidate (draft/recommendation — not yet executed send) |
| **Content** | Normalized text and/or modality summary (image/voice/PDF as projected summaries — not Meta binary blobs as Core law) |
| **Ordering** | Position in the Thread window |
| **Trigger Turn** | The Turn that prompted the current Decision request |

Turns in ConversationView are **projections**, not SoT rows.

### 6.4 Turn Framing

Conversation Engine performs **Turn Framing**.

Turn Framing produces the published **ConversationView** artifact.

```text
Turn Framing (behavior)
        ↓
ConversationView (published artifact)
```

This mirrors Document `003`:

```text
Context Assembly (behavior)
        ↓
Decision Context (published artifact)
```

**Turn Framing** interprets the Trigger Turn relative to the Thread window so Decision Engine can Understand without raw inbox coupling.

Turn Framing answers:

1. What is the current customer/operator input?
2. What recent Turns matter for continuity?
3. Who spoke which Turn?
4. What Conversation Stage applies (if known)?
5. What is missing or truncated?

Turn Framing is not Action selection, not Knowledge retrieval, and not Context assembly. **ConversationView is the result of Turn Framing**, not the framing process itself.

### 6.5 ConversationView

**ConversationView** is the Conversation Engine’s owned **published artifact**: the framed, bounded, channel-neutral view of a Thread for one Decision.

Document `002` ownership:

| Module | Owns |
|--------|------|
| **Conversation Engine** | **Turn Framing** / ConversationView |

ConversationView includes (conceptual):

- ConversationView ID (unique per publication)
- Thread identity
- Bounded Turn window
- Current / Trigger Turn framing
- Conversation Stage (or unknown) — as an **interpretation**, not SoT
- Role map for visible turns
- Framing completeness / truncation / relevance signals
- Optional linkage hints to MemoryView (without owning Memory)

### 6.6 Conversation Stages

**Conversation Stages** are channel-neutral progress labels for dialogue state, used as framing signals — not as Messenger UI chrome and not as commerce order status.

**Ownership of Stage:** Conversation Stage is an **interpretation produced by the Conversation Engine**. It is **not** an intrinsic property of the Conversation itself, nor is it Conversation SoT. Different framing policies may legitimately interpret the same Conversation differently while remaining within the architecture. Stages must not be persisted as if they were objective SoT truth.

Stages are architectural categories. Production lead labels may map onto them in Document `011` without requiring identical names.

| Stage (conceptual) | Meaning |
|--------------------|---------|
| **New** | Early contact; little established intent |
| **Discovering** | Exploring products/needs |
| **Qualifying** | Intent and constraints becoming clear |
| **Negotiating** | Price/options/objections in play |
| **Confirming** | Ready to lock details / order path |
| **Fulfilled / Converted** | Outcome reached for this dialogue goal |
| **Paused / Nurture** | Deferred; continuity retained lightly |
| **Escalated / Human** | Human ownership signaled |
| **Closed / Lost / Spam** | Terminal or suppressed paths |
| **Unknown** | Stage not yet determined — explicit, not invented |

The stage vocabulary shown above is **illustrative and intentionally non-exhaustive**. Future stages may be introduced through additive versioning without changing the ConversationView contract.

**Rules:**

- Stages inform Clarify/Decide; they do not authorize send or order create.
- Merchant UI labels may be richer; ConversationView exposes channel-neutral stage signals.
- Conversation Engine may read Memory hints for stage continuity but does not own MemoryView (`006`).
- Do not treat a stage label (e.g. “Negotiating”) as an objective property of the Conversation SoT.

### 6.7 What Conversation Engine concepts are not

| Not owned here | Owner / doc |
|----------------|-------------|
| Inbox / message SoT | Plugin (CV-INV-2) |
| Decision Context | Context Engine (`003`) |
| MemoryView | Memory Engine (`006`) |
| KnowledgeHits | Knowledge Engine (`005`) |
| Decision / Action | Decision System (`007`) |
| Channel send / Graph | Adapters / Hub edge (`009`) |
| Commerce facts | Plugin / Woo |

ConversationView must not embed Knowledge, Memory, Commerce authority, or Decision outputs (**CV-INV-9**).

---

## 7. Conversation Engine Role

| | |
|--|--|
| **Classification** (`002`) | AI Core Engine |
| **Purpose** | Represent conversation continuity for Wise without owning inbox SoT; perform Turn Framing |
| **Primary behavior** | **Turn Framing** |
| **Primary output** | Published **ConversationView** (result of Turn Framing) |
| **Pipeline position** | Contributes during Context assembly / Understand continuity (Document `002` Core Pipeline) |

### 7.1 Responsibilities

- Accept adapter-normalized **conversation projection** (bounded Thread window + Trigger Turn)
- Perform **Turn Framing** and **publish ConversationView**
- Interpret and surface **Conversation Stage** (or Unknown) — as framing interpretation, not SoT
- Mark truncation, missing turns, relevance choices, and role ambiguity explicitly
- Optionally read **MemoryView** for continuity hints (does not write Memory SoT; does not embed Memory)
- Contribute ConversationView to Context assembly without owning Decision Context (**CV-INV-8**, **CV-INV-9**)

### 7.2 Non-responsibilities

- Persisting messages, threads, unread badges, Mid dedupe
- Meta Graph history fetch, Page tokens, send/typing/upload
- Choosing Actions, confidence, Safety, Verification
- Assembling Decision Context (Context Engine)
- Owning or activating Knowledge / Learning
- Inventing Turns to fill gaps
- Crossing Execution Boundary

---

## 8. Relationship to Context

Document `003` law: Conversation contributes to Context; it does not redefine Context.

```text
Adapter conversation projection
        ↓
Conversation Engine → ConversationView (Turn Framing)
        ↓
Context Engine includes Conversation window / ConversationView in Decision Context
        ↓
Decision System consumes Context (which embeds or references ConversationView)
```

| Rule | Detail |
|------|--------|
| **Contribution** | ConversationView feeds Context First continuity |
| **Non-redefinition** | Conversation Engine must not publish a competing Decision Context (**CV-INV-8**) |
| **SoT** | Context may carry a conversation window facet; Plugin remains Conversation SoT (**CX-INV-4**, **CV-INV-2**) |
| **Immutability** | Published ConversationView for a request is immutable (**CV-INV-4**); Context republication may request a new ConversationView publication |
| **Identity** | Each published ConversationView has a **ConversationView ID**; Context ID / Fingerprint remain Context Engine concepts (`003`). ConversationView has **no Fingerprint** — semantic comparison is at Context level. |

---

## 9. How ConversationView Is Published

Architectural process — not implementation.

```text
Adapter-normalized conversation projection
  (Thread id, bounded Turns, Trigger Turn, optional lead/stage signals)
        ↓
Conversation Engine performs Turn Framing
  + optional MemoryView (read)
  + framing policy from EffectiveConfig / Capability (via Context boundary)
        ↓
Bound + prioritize relevant Turns → order → role-map → frame Trigger Turn → stage interpretation
        ↓
Validate completeness / truncation / relevance
        ↓
Publish ConversationView (ConversationView ID; immutable for this request)
        ↓
Context Engine / Decision Coordinator consumers
```

### 9.1 Framing principles

1. **Project, don’t own** — work only on adapter projections.
2. **Bound before enrich** — window size is finite; prioritize **relevant** Turns for the current Decision request.
3. **Normalize roles** — channel-specific actor labels map to neutral roles.
4. **No model fill-in for missing Turns** — incompleteness is explicit (**CV-INV-6**).
5. **Stage humility** — Stage is an interpretation; prefer Unknown over invented certainty; never persist Stage as Conversation SoT.
6. **One publication per need** — one published ConversationView per Decision request framing pass.
7. **No secondary Context** — do not embed Knowledge, Memory bodies, Commerce authority, or Decision outputs (**CV-INV-9**).

---

## 10. Conversation Ownership

| Concern | Owner |
|---------|--------|
| **ConversationView / Turn Framing** | Conversation Engine (**CV-INV-1**) |
| **Conversation / Thread / message SoT** | Plugin (Messenger/Comments today) (**CV-INV-2**) |
| **Decision Context** | Context Engine (`003`) |
| **MemoryView** | Memory Engine (`006`) |
| **Channel transport** | Adapters / Hub edge |
| **Merchant inbox UX** | Plugin Experience Layer |

**Rule:** Other modules may **read** a published ConversationView. They must not mutate it in place. Republishing yields a new ConversationView ID for that request.

---

## 11. Conversation Lifetime

| Concept | Lifetime |
|---------|----------|
| **Conversation / Thread (SoT)** | Long-lived on Plugin (or inbox owner) |
| **Session** (`001`) | One or more Decision episodes over a Conversation |
| **ConversationView** | Request / decision scoped — published for one Decision framing; then read-only |
| **Turn window inside View** | Snapshot at framing time; later inbox messages do not mutate a published View |

### 11.1 Freshness

Mark freshness/incompleteness when:

- Projection omits Turns that framing policy requires
- Trigger Turn is missing or ambiguous
- Stage signals conflict without resolution policy
- Window was truncated by adapter limits

Freshness failures authorize safer downstream Actions (Clarify / Hold / Escalate / Draft) — not invented dialogue.

---

## 12. Conversation Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**CV-INV-7**) |
| **SoT Boundary** | No claiming inbox persistence authority (**CV-INV-2**) |
| **Channel-agnostic** | No Meta Graph / token / Mid logic inside Conversation Engine (**CV-INV-5**, AC-1, AC-3) |
| **Context Boundary** | Do not redefine or replace Decision Context (**CV-INV-8**) |
| **Memory Boundary** | May read MemoryView; do not own MemoryView |
| **Knowledge Boundary** | Do not retrieve or invent product knowledge |
| **Webhook latency** | Full framing is not performed on Meta webhook acknowledgment path (AC-8) |
| **PII** | Cross-tenant training from raw chat is not a Conversation Engine duty (AC-9) |

---

## 13. Conversation Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Trigger Turn + bounded window coherent enough to frame |
| **Valid with warnings** | Truncation or Unknown stage; proceed allowed with signals |
| **Invalid — incomplete** | Missing Trigger Turn or required projection; downstream must Clarify/Hold/Escalate |
| **Invalid — unsafe projection** | Projection violates boundaries (raw secrets, transport payloads as Core input) |

Checks (conceptual): Thread identity present; Trigger Turn identified; window bounded; roles explicit or marked ambiguous; stage present or Unknown; no fabricated Turns; traceability seeds attached.

---

## 14. Conversation Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — ConversationView

| | |
|--|--|
| **Name** | **ConversationView** (published result of Turn Framing) |
| **Producer** | Conversation Engine |
| **Consumers** | Context Engine, Decision Coordinator / Decision Engine, Memory Engine (optional), Evaluation |
| **Guarantees** | Bounded; channel-neutral; immutable once published; incompleteness explicit; no execution authority; no Knowledge/Memory/Commerce/Decision embed (**CV-INV-9**) |
| **Non-guarantees** | Full inbox history; perfect stage detection; live SoT freshness forever; Stage as objective SoT truth |

### 14.2 ConversationView Identity

Each published ConversationView has a unique **ConversationView ID**.

ConversationView ID identifies a single publication produced by the Conversation Engine for one Decision request.

ConversationView IDs support:

- observability
- replay
- debugging
- audit correlation

ConversationView ID is **not** a deterministic comparison identity.

Unlike Context, ConversationView does **not** define a Fingerprint because semantic comparison is performed at the Context level (Document `003`).

| | **ConversationView ID** | **Context Fingerprint** (`003`) |
|--|-------------------------|--------------------------------|
| **Purpose** | Identify one ConversationView publication | Compare Decision Context equivalence |
| **Nature** | Unique per publication | Deterministic |
| **Owner** | Conversation Engine | Context Engine |

Illustrative ID strings are **not** schemas or encoding rules.

### 14.3 Input contracts

| Contract | Producer | Use |
|----------|----------|-----|
| **Conversation projection** | Channel adapter | Thread, Turns, Trigger Turn, optional stage/lead signals |
| **EffectiveConfig / CapabilitySet** | Platform Services (via boundary) | Window bounds, framing policy |
| **MemoryView** | Memory Engine (optional) | Continuity hints only |

### 14.4 Versioning

- Additive stage labels or role types → Minor (vocabulary is illustrative / non-exhaustive)
- Changing ownership away from ConversationView-only → ADR + Document `002`
- Breaking framing semantics that Context depends on → coordinate with Document `003`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New channel** | Adapter projects same conceptual Thread/Turn materials; Conversation Engine law unchanged |
| **New modality** | Normalized Turn content / modality summary in projection |
| **New stage label** | Additive stage vocabulary via contract versioning |
| **Tighter windows** | EffectiveConfig / Capability policy |
| **Comments vs Messenger** | Same ConversationView contract; different adapter projections |

**Forbidden:** Channel-specific second Conversation Engine; framing inside Prompt/Model Gateway that bypasses ConversationView; Hub inbox SoT replacing Plugin without ADR.

---

## 16. Failure Philosophy

When framing cannot complete safely:

1. Publish Invalid — incomplete / unsafe with reason codes
2. Allow Context / Decision paths to Clarify, Hold, Escalate, or Draft-only
3. Never invent Turns, speakers, or Stages to “complete” the view
4. Never send or commit commerce
5. Never call Model Gateway solely to fabricate missing dialogue history

---

## 17. Observability Philosophy

Published ConversationView should support:

- Traceability of included / truncated / missing / relevance-prioritized Turns
- Stage and role clarity signals (Stage as interpretation, not SoT)
- Correlation via **ConversationView ID**, and with Context ID when embedded in a Decision Context (`003`)
- Evaluation/shadow comparison of framing outcomes under matched Context Fingerprints (not ConversationView Fingerprints)

Observability artifacts must not become Conversation SoT.

---

## 18. Replaceability

Conversation Engine internals may be replaced if:

- ConversationView contract remains stable
- Ownership remains ConversationView-only
- Plugin remains Conversation SoT
- Documents `001`–`003` constraints and CV-INV-* hold

Windowing and stage-mapping strategies may churn without rewriting Decision Engine.

---

## 19. Mapping to Lifecycle and Core Pipeline

| Lifecycle stage (`001`) | Conversation Engine contribution |
|-------------------------|----------------------------------|
| **Understand** | Primary — Turn Framing / ConversationView |
| **Clarify** | Incomplete framing signals justify Clarification |
| **Verify / Decide** | Provides framed dialogue for Decision; does not verify commerce or choose Action |
| **Respond** | None — Execution Boundary |
| **Learn** | May expose framing markers; does not activate learning |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| With Context continuity | Perform Turn Framing; publish ConversationView for Context assembly |
| Decision System | Consume ConversationView via Context |

```text
004 Conversation → contributes ConversationView to Context
003 Context      → owns Decision Context (does not own Conversation SoT)
006 Memory       → may inform stage continuity; owns MemoryView
007 Decision     → consumes Context (including ConversationView)
009 Adapters     → project Conversation SoT into Core
```

These engines contribute to or consume ConversationView / Context; **none of them redefine Conversation SoT or Decision Context**.

---

## 20. Validation Notes

Validated against Documents `001`–`003` and production discovery. Approved in v0.2.0 with ConversationView Identity, Stage-as-interpretation, CV-INV-9, and Turn Framing → artifact emphasis; no production redesign.

### Conflict V-004-1 — Conversation Engine not in production runtime

| | |
|--|--|
| **Conflict** | Production dialogue framing lives inside Plugin Sales Agent / inbox flows; Hub Conversation Engine is Target Architecture. |
| **Risk** | Treating Document `004` as shipped. |
| **Suggested resolution** | Map Plugin thread/turn loading onto ConversationView in Document `011`; default merchant path unchanged until flagged migration. |

### Conflict V-004-2 — Plugin SoT vs Wise view

| | |
|--|--|
| **Conflict** | Temptation to mirror full inbox on Hub as SoT. |
| **Risk** | Dual SoT and sync bugs. |
| **Suggested resolution** | Keep Plugin Conversation SoT (**CV-INV-2**); adapters project bounded windows only. |

### Conflict V-004-3 — Lead labels vs Conversation Stages

| | |
|--|--|
| **Conflict** | Production Messenger lead labels are product UI vocabulary. |
| **Risk** | Hard-coding Meta/Messenger labels into Core. |
| **Suggested resolution** | Map labels → channel-neutral Stages in adapter/`011`; ConversationView exposes neutral stages. |

### Conflict V-004-4 — ConversationView vs Context ownership

| | |
|--|--|
| **Conflict** | Both carry conversation window information. |
| **Risk** | Duplicate ownership / mutation races. |
| **Suggested resolution** | Conversation Engine owns ConversationView; Context Engine owns Decision Context and may embed/reference ConversationView without owning Conversation SoT (`003` / **CV-INV-1**, **CV-INV-8**). |

### Conflict V-004-5 — Stage persisted as SoT

| | |
|--|--|
| **Conflict** | Production lead labels are stored on Plugin threads and may be mistaken for Wise Conversation Stage SoT. |
| **Risk** | Treating Conversation Stage as objective Conversation property. |
| **Suggested resolution** | Stage in ConversationView is Engine interpretation only; Plugin labels remain UI/SoT product data mapped in `011`. |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Conversation / Session terminology, constraints |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Module map; Turn Framing ownership |
| [`003-context-engine.md`](003-context-engine.md) | Context consumes ConversationView; CX-INV-4 |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | Separate grounding contributions (Draft) |
| [`006-memory-engine.md`](006-memory-engine.md) | MemoryView; optional framing hints (**Approved**) |
| [`007-decision-system.md`](007-decision-system.md) | Consumes framed dialogue via Context (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | Supplies conversation projections (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | May evaluate framing under matched Context Fingerprints (Draft) |
| `011-migration-strategy.md` | Maps Plugin inbox/Sales Agent stages → ConversationView |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Conversation/Thread/Turn/ConversationView/Turn Framing/Stages; ownership; lifetime; boundaries; CV-INV-1…8; quality attributes; contracts; Context relationship; Validation Notes V-004-1…4 |
| 0.2.0 | 2026-07-30 | Documentation Lead | ConversationView ID; Stage as interpretation (not SoT); Turn Framing → published ConversationView; illustrative stage vocabulary; CV-INV-9; Relevance quality attribute; publish terminology; **Status → Approved** |
| 0.2.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `006` Memory Engine Draft |
| 0.2.2 | 2026-07-30 | Documentation Lead | Align Memory ownership wording to MemoryView; Document `006` Approved |
| 0.2.3 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.2.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.2.5 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.2.6 | 2026-07-30 | Documentation Lead | Included in Wise AI Platform Reference Architecture **1.0.0** freeze (`001`–`011`) |
