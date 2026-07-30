# Knowledge Engine

| Field | Value |
|-------|--------|
| **Title** | Knowledge Engine |
| **Document ID** | `005` |
| **Version** | `0.1.8` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.1) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.0) |
| **Context Specification** | [`003-context-engine.md`](003-context-engine.md) (**Approved** v0.2.1) |
| **Conversation Specification** | [`004-conversation-engine.md`](004-conversation-engine.md) (**Approved** v0.2.0) |

---

## 1. Document Information

This document defines the **canonical Knowledge Engine** for Wise AI Core.

It answers one architectural question:

> **How does Wise use approved business knowledge without owning business data?**

It deepens **only** the Knowledge Engine: Knowledge Retrieval behavior, KnowledgeHits as the published artifact, Domain, authority, scope, provenance, revision, ownership, lifetime, boundaries, invariants, quality attributes, contracts, and the relationship to Context.

It does **not** redefine Documents `001`, `002`, `003`, or `004`. Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. If any statement here conflicts with Document `003`’s Context law, **Document `003` wins**.
4. If any statement here conflicts with Document `004`’s Conversation law, **Document `004` wins**.
5. This document must not reorganize the Core or redefine Decision Context or ConversationView.

**Terminology rules:**

- Throughout this document, **KnowledgeHits** means the published artifact of Knowledge Retrieval unless explicitly stated otherwise.
- Knowledge Engine **owns Knowledge Retrieval / KnowledgeHits only** — never business data SoR, never Decision Context.

**Out of scope:** implementation, APIs, database schemas, Laravel, WordPress, embeddings, vector databases, RAG algorithms, search/ranking algorithms, LLM prompts, token budgets, caching strategies, merchant knowledge-editing UI, Learning activation UI, Conversation framing (`004`), Memory store (`006`), Decision System (`007`), channel adapters (`009`).

---

## 2. Purpose

Provide a production-grade architectural definition of approved-knowledge use so that:

- Wise grounds recommendations in **approved** knowledge without owning business data
- Context First receives bounded **KnowledgeHits** (or explicit incompleteness)
- Verification and Decision can cite **provenance** without private Plugin helper coupling
- Merchant approve workflows remain authoritative (Document `001` AC-4)
- Migration (`011`) can map Plugin KB / Hub packs onto KnowledgeHits without a big-bang rewrite

---

## 3. Scope

**In scope:**

- Knowledge Engine role (AI Core Engine per Document `002`)
- Core concepts: Knowledge, Knowledge Domain, Knowledge Source, Knowledge Authority, Knowledge Scope, Knowledge Retrieval, KnowledgeHit / KnowledgeHits, Knowledge Provenance, Knowledge Revision
- Ownership, lifetime, boundaries, invariants, quality attributes
- Conceptual contracts (not schemas)
- Relationship to Context (`003`), Conversation (`004`), Verification/Decision (`007`), Learning (`008`), adapters (`009`)
- Validation Notes against production reality

**Out of scope:**

- How merchants author/approve knowledge in UI
- How WooCommerce stores prices/stock (Commerce SoT — facts snapshots are Context/boundary concerns, not Knowledge ownership)
- How ConversationView is framed (`004`)
- How MemoryView is retained (`006`)
- Retrieval implementation technology of any kind

---

## 4. Relationship to Documents 001–004

| Inherited concept | How Document `005` uses it |
|-------------------|----------------------------|
| **Knowledge** (`001`) | Merchant-approved product/store content + platform packs — distinct from raw chat |
| **Grounding** (`001`) | KnowledgeHits supply approved content for grounding; inventing is forbidden |
| **AC-4** (`001`) | Approve workflows remain enforceable; unapproved knowledge must not enter Hits as approved |
| **Module map** (`002`) | Knowledge Engine = AI Core; owns Retrieval / KnowledgeHits |
| **Execution Boundary** (`002`) | Knowledge Engine never sends or commits commerce |
| **Context First** (`003`) | KnowledgeHits contribute grounding facets to Decision Context |
| **CX / CV ownership** (`003` / `004`) | Knowledge does not own Context or ConversationView |
| **Downstream graph** (`003`) | Knowledge contributes to Context; does not redefine Context |

```text
Document 001 — Knowledge / Grounding terminology + AC-4
        ↓
Document 002 — Knowledge Engine owns KnowledgeHits
        ↓
Document 003 — Context consumes KnowledgeHits; does not own Knowledge SoR
        ↓
Document 004 — Conversation contributes dialogue; not Knowledge
        ↓
Document 005 — this document (deepens Knowledge Engine only)
```

---

## 5. Design Goals

| ID | Goal |
|----|------|
| KN-1 | Perform **Knowledge Retrieval** and **publish KnowledgeHits** for a Decision request when grounding requires approved knowledge |
| KN-2 | Never own business data SoR — business/merchant/platform systems remain authoritative |
| KN-3 | Never own or replace **Decision Context** |
| KN-4 | Contribute KnowledgeHits to Context without redefining Context |
| KN-5 | Use **approved** knowledge only |
| KN-6 | Keep retrieval **bounded**, **channel-agnostic**, and **explainable** |
| KN-7 | Make missing knowledge **explicit** — never invent product facts or merchant policy |
| KN-8 | Remain replaceable behind the KnowledgeHits contract |
| KN-9 | Never cross the Execution Boundary |

---

## 5A. Knowledge Invariants

Document `001` has Platform Invariants. Documents `003`/`004` have engine invariants. The following are the **laws of the Knowledge Engine**.

They remain true unless an ADR updates this document (and Document `002` when ownership impact applies).

| ID | Invariant |
|----|-----------|
| **KN-INV-1** | Knowledge Engine owns **KnowledgeHits**. |
| **KN-INV-2** | Business systems remain the **Knowledge System of Record**. |
| **KN-INV-3** | KnowledgeHits are **immutable once published**. |
| **KN-INV-4** | Knowledge Retrieval is **bounded**. |
| **KN-INV-5** | Knowledge must originate from **approved authorities**. |
| **KN-INV-6** | Knowledge Engine is **channel-agnostic**. |
| **KN-INV-7** | Knowledge Engine **never crosses the Execution Boundary**. |
| **KN-INV-8** | Knowledge **contributes to Context** but **never replaces Context**. |
| **KN-INV-9** | Knowledge Engine **never owns Decision Context**. |
| **KN-INV-10** | KnowledgeHits are **read-only projections** of approved knowledge. |

**Notes:**

- **KN-INV-2** — Plugin merchant KB, Hub-served packs, and other designated business systems remain SoR. Wise retrieves projections; it does not become the authoring store.
- **KN-INV-5 / AC-4** — Unapproved drafts must not appear as approved Hits.
- **KN-INV-8 / KN-INV-9** — KnowledgeHits feed Context assembly; they are not a second Decision Context.

---

## 5B. Knowledge Quality Attributes

Not new platform requirements. Qualities good Knowledge Retrieval / KnowledgeHits should exhibit.

| Attribute | Meaning for Knowledge |
|-----------|------------------------|
| **Correctness** | Hits reflect the approved knowledge as projected — not invented content |
| **Approval** | Hits are marked as originating from approved authority/revision only |
| **Freshness** | Hits respect allowed freshness for the Decision; staleness is signaled |
| **Scope** | Hits apply only within declared Knowledge Scope |
| **Domain clarity** | Hits declare Knowledge Domain (kind of knowledge), distinct from Source and Scope |
| **Relevance** | Hits prioritize knowledge most relevant to the current Decision request |
| **Provenance** | Source, authority, and revision are conceptually attributable |
| **Consistency** | Hits do not silently mix contradictory approved revisions without signals |
| **Traceability** | Retrieval explainability shows what was included, excluded, or missing |
| **Determinism** | Same retrieval inputs + pinned retrieval policy → reasonably identical KnowledgeHits |

---

## 6. Core Concepts

### 6.1 Knowledge

From Document `001`:

> **Knowledge** — Merchant-approved product/store content (pitch, FAQ, objections, policy) plus platform packs — distinct from raw chat history.

Deepening (without changing law):

**Knowledge** is **approved information** that Wise may use for reasoning and grounding.

Knowledge is **not**:

- Raw conversation history (Conversation / ConversationView)
- Short-horizon MemoryView continuity
- Live mutable commerce authority (price/stock ownership)
- A Decision or Action
- Decision Context

### 6.2 Knowledge Domain

**Knowledge Domain** is the **category of approved knowledge** Wise may retrieve — what *kind* of knowledge is being used — independent of where it is stored.

Domains are **not** Sources. A Domain classifies knowledge; a Source holds it.

| Illustrative Domain | Meaning (conceptual) |
|---------------------|----------------------|
| **Merchant Policy** | Store policies, terms, return/COD rules as merchant-approved content |
| **Product Knowledge** | Product descriptions, pitches, FAQs, objections |
| **Store Information** | Store identity, hours, contact, service framing (non-commerce-authority facts as approved content) |
| **Courier Rules** | Shipping/courier guidance as approved knowledge (not live courier API ownership) |
| **Platform Capability** | What Wise/platform capabilities may be cited when approved for merchant use |
| **Compliance** | Compliance-oriented approved statements |
| **AI Pack** | Platform packs (e.g. anonymized intent/FAQ packs) when Authority-approved for the Decision |

The domain vocabulary above is **illustrative and intentionally non-exhaustive**. Future domains may be introduced through additive versioning without changing the KnowledgeHits contract.

**Domain vs Source (examples):**

| Source (where) | Domain (what kind) |
|----------------|--------------------|
| Merchant FAQ spreadsheet | Product Knowledge and/or Merchant Policy |
| WooCommerce products (as projected approved content) | Catalog / Product Knowledge |
| Courier configuration store | Shipping / Courier Rules |

**Domain vs Scope:**

| Concept | Answers |
|---------|---------|
| **Knowledge Domain** | What *kind* of knowledge is this? |
| **Knowledge Scope** | Within what *boundary* may this Hit apply for this Decision (store, catalog segment, language, …)? |

A Hit may belong to one Domain (or a declared primary Domain) and still be limited by Scope.

Domains do **not** grant Execution Boundary rights and do **not** make Knowledge Engine the owner of Woo, courier systems, or merchant SoR.

### 6.3 Knowledge Source

**Knowledge Source** is the system or repository that contains approved knowledge available for projection into Wise.

Examples of *kinds* of sources (illustrative, not an inventory mandate): merchant knowledge base, spreadsheet exports, WooCommerce catalog projections, courier configuration stores, platform intent/FAQ packs, other Product-Owner-approved repositories.

Sources are **outside** Knowledge Engine ownership as SoR (**KN-INV-2**).

One Source may contribute to multiple Domains. One Domain may be served by multiple Sources.

### 6.4 Knowledge Authority

**Knowledge Authority** is the authority responsible for **approving** that knowledge for Wise use.

Authority enforces Document `001` AC-4: Knowledge Engine must not bypass approve workflows. Authority is not Meta, not the Model Gateway, and not the Conversation Engine.

### 6.5 Knowledge Scope

**Knowledge Scope** is the boundary within which knowledge applies for a Decision (e.g. store, catalog segment, product focus, language, channel capability — as channel-neutral scope flags, not transport).

Retrieval must not return Hits outside the declared scope without explicit signaling.

Scope is **not** Domain: Scope bounds *applicability for this Decision*; Domain classifies *kind of knowledge*.

### 6.6 Knowledge Retrieval

**Knowledge Retrieval** is the **primary behavior** of the Knowledge Engine.

It selects approved knowledge relevant to one Decision request — within Domain and Scope constraints — and publishes KnowledgeHits.

```text
Knowledge Retrieval (behavior)
        ↓
KnowledgeHits (published artifact)
```

This mirrors Documents `003` and `004`:

```text
Context Assembly → Decision Context
Turn Framing     → ConversationView
Knowledge Retrieval → KnowledgeHits
```

Knowledge Retrieval **must not define** (architecture non-goals): embeddings, vector databases, RAG algorithms, search algorithms, ranking algorithms, LLM prompts, token budgets, or other implementation details. Those may appear only in later technical design — never as Knowledge Engine law.

### 6.7 KnowledgeHit / KnowledgeHits

**KnowledgeHits** (one or more **KnowledgeHit** entries) are the **published artifact** produced by Knowledge Retrieval.

Document `002` ownership:

| Module | Owns |
|--------|------|
| **Knowledge Engine** | **KnowledgeHits** (retrieval results) |

A KnowledgeHit conceptually carries:

- Selected approved knowledge content (projection)
- **Knowledge Domain** (kind of knowledge)
- Knowledge Scope applicability
- **Knowledge Provenance** (source, authority, revision)
- Relevance / inclusion signals
- Gap signals when expected knowledge is missing

**KnowledgeHits are NOT:**

| Not KnowledgeHits | Owner / doc |
|-------------------|-------------|
| Conversation / ConversationView | Plugin SoT / Conversation Engine (`004`) |
| Memory / MemoryView | Memory Engine (`006`) |
| Commerce authority | Plugin / Woo |
| Decision / Action | Decision System (`007`) |
| Decision Context | Context Engine (`003`) |

KnowledgeHits may **reference** Domain, Source, Authority, and Revision. They must **never** contain execution authority (send, order create, payment, courier mutate).

### 6.8 Knowledge Provenance

**Knowledge Provenance** conceptually records, for each Hit (or Hit set):

| Element | Meaning |
|---------|---------|
| **Domain** | Which Knowledge Domain the Hit belongs to |
| **Source** | Which Knowledge Source the projection came from |
| **Authority** | Which Knowledge Authority approved it for Wise use |
| **Revision** | Which approved Knowledge Revision was projected |

Provenance supports Grounding, Evaluation, audit, and merchant trust. It is not an API schema.

### 6.9 Knowledge Revision

**Knowledge Revision** represents the **approved revision** of the originating knowledge at projection time.

- Republishing KnowledgeHits for a new Decision may project a newer approved revision.
- A published KnowledgeHits artifact does not mutate when the SoR later changes (**KN-INV-3**).
- Mixing revisions inside one publication without signals violates Consistency.

---

## 7. Knowledge Engine Role

| | |
|--|--|
| **Classification** (`002`) | AI Core Engine |
| **Purpose** | Retrieve merchant-approved knowledge and platform packs without owning business data |
| **Primary behavior** | **Knowledge Retrieval** |
| **Primary output** | Published **KnowledgeHits** |
| **Pipeline position** | Contributes grounding inputs during Understand / Context assembly and to Verification consumers |

### 7.1 Responsibilities

- Accept retrieval intent and scope signals (from Context assembly / Coordinator boundary)
- Perform **Knowledge Retrieval** over approved Sources only
- **Publish KnowledgeHits** with Provenance and Revision references
- Mark gaps, out-of-scope exclusions, and freshness warnings explicitly
- Contribute Hits to Context without owning Decision Context (**KN-INV-8**, **KN-INV-9**)

### 7.2 Non-responsibilities

- Owning or editing business data / knowledge SoR
- Merchant approve UI or learning activation UI
- Inventing prices, stock, discounts, or merchant policy
- Owning ConversationView, MemoryView, or Decision Context
- Choosing Actions, Safety policy ownership, or lifecycle coordination
- Channel send / Graph / commerce commits
- Defining embeddings, RAG, ranking algorithms, or prompts as architecture law

---

## 8. Relationship to Context

Document `003` law: Knowledge contributes to Context; it does not redefine Context.

```text
Approved Knowledge Sources (by Domain)
        ↓
Knowledge Retrieval (Domain + Scope constrained)
        ↓
KnowledgeHits
        ↓
Context Engine
        ↓
Decision Context
```

| Rule | Detail |
|------|--------|
| **Contribution** | KnowledgeHits feed Context grounding facets / handles |
| **Non-redefinition** | Knowledge Engine must not publish a competing Decision Context (**KN-INV-8**, **KN-INV-9**) |
| **SoR** | Business systems remain Knowledge SoR (**KN-INV-2**); Context may carry Hits or handles as snapshots |
| **Immutability** | Published KnowledgeHits are immutable (**KN-INV-3**); Context republication may request a new KnowledgeHits publication |
| **Identity** | KnowledgeHits may carry publication identity for audit; Context ID / Fingerprint remain Context Engine concepts (`003`). Semantic Decision comparison remains at Context level. |
| **Separation from Conversation** | ConversationView frames dialogue (`004`); KnowledgeHits ground approved content — neither replaces the other |

---

## 9. Knowledge Retrieval Model

Architectural process — not implementation.

```text
Decision request materials / Context assembly signals
  (scope, trigger intent hints, capability/config bounds)
        ↓
Knowledge Engine performs Knowledge Retrieval
  — approved Sources only
  — within Knowledge Domain + Knowledge Scope
  — bounded result set
        ↓
Attach Provenance (domain, source, authority, revision)
Mark gaps / freshness / relevance exclusions
        ↓
Validate approval + bounds + non-invention
        ↓
Publish KnowledgeHits (immutable for this request)
        ↓
Context Engine / Verification / Decision consumers
```

### 9.1 Retrieval principles

1. **Approved only** — never treat drafts as approved (**KN-INV-5**).
2. **Project, don’t own** — read-only projections (**KN-INV-10**).
3. **Bound before enrich** — finite Hits (**KN-INV-4**).
4. **Relevance over dump** — prioritize Hits relevant to the Decision request.
5. **No invention** — missing knowledge → explicit incompleteness.
6. **Channel-neutral** — no Meta/Messenger hard dependency (**KN-INV-6**).
7. **No execution** — Hits never authorize send/commerce (**KN-INV-7**).
8. **One publication per need** — one KnowledgeHits publication per retrieval pass for a Decision request.

---

## 10. Knowledge Ownership

| Concern | Owner |
|---------|--------|
| **KnowledgeHits / Knowledge Retrieval** | Knowledge Engine (**KN-INV-1**) |
| **Knowledge System of Record** | Business / merchant / designated platform systems (**KN-INV-2**) |
| **Knowledge Authority / approve workflows** | Plugin (and related Product Owner paths) per AC-4 |
| **Decision Context** | Context Engine (`003`) |
| **ConversationView** | Conversation Engine (`004`) |
| **MemoryView** | Memory Engine (`006`) |
| **Commerce facts authority** | Plugin / WooCommerce |
| **Channel transport** | Adapters / Hub edge |

**Rule:** Other modules may **read** published KnowledgeHits. They must not mutate them in place. Republishing yields a new KnowledgeHits publication for that request.

---

## 11. Knowledge Lifetime

| Concept | Lifetime |
|---------|----------|
| **Knowledge in SoR** | Long-lived under business systems + approve workflows |
| **Knowledge Revision** | Approved revision identity at SoR; projected into Hits |
| **KnowledgeHits** | Request / decision scoped — published for one Decision retrieval; then read-only |
| **Provenance on Hits** | Frozen at publication time for that Hits artifact |

### 11.1 Freshness

Mark freshness/incompleteness when:

- Required scope has no approved Hits
- Projected revision is older than allowed freshness policy
- Authority/approval status cannot be confirmed as approved
- Scope signals are missing or contradictory

Freshness failures authorize safer downstream Actions (Clarify / Hold / Escalate / Draft) — not fabricated knowledge.

---

## 12. Knowledge Boundaries

| Boundary | Rule |
|----------|------|
| **Execution Boundary** | No send, no order create, no commerce commit (**KN-INV-7**) |
| **SoR Boundary** | No claiming Knowledge System of Record (**KN-INV-2**) |
| **Approval Boundary** | No unapproved knowledge as approved Hits (**KN-INV-5**, AC-4) |
| **Context Boundary** | Do not redefine or replace Decision Context (**KN-INV-8**, **KN-INV-9**) |
| **Conversation Boundary** | Do not frame dialogue or own ConversationView (`004`) |
| **Memory Boundary** | Do not own MemoryView (`006`) |
| **Commerce Boundary** | Do not become price/stock authority (AC-2); commerce snapshots remain boundary/Context concerns |
| **Channel-agnostic** | No Meta Graph / token logic inside Knowledge Engine (**KN-INV-6**, AC-1, AC-3) |
| **Implementation Boundary** | No embeddings/RAG/search/prompt law in this document |
| **Webhook latency** | Full retrieval is not performed on Meta webhook acknowledgment path (AC-8) |

---

## 13. Knowledge Validation

| Outcome | Meaning |
|---------|---------|
| **Valid** | Hits are approved, in scope, bounded, and coherent enough for grounding |
| **Valid with warnings** | Truncation, freshness warnings, or partial scope coverage — proceed with signals |
| **Invalid — incomplete** | Required knowledge missing; downstream must Clarify / Hold / Escalate — never invent |
| **Invalid — unsafe projection** | Unapproved content, boundary violations, or execution-tainted materials |

Checks (conceptual): approval confirmed; scope respected; bounds respected; provenance present or explicitly unknown; no fabricated Hits; no execution authority embedded; traceability seeds attached.

---

## 14. Knowledge Contracts

Conceptual only — no schemas.

### 14.1 Primary contract — KnowledgeHits

| | |
|--|--|
| **Name** | **KnowledgeHits** (published result of Knowledge Retrieval) |
| **Producer** | Knowledge Engine |
| **Consumers** | Context Engine, Verification, Decision Engine / Coordinator, Prompt (read), Evaluation |
| **Guarantees** | Approved-origin projections; bounded; immutable once published; provenance conceptually available; no execution authority; read-only (**KN-INV-10**) |
| **Non-guarantees** | Complete catalog coverage; live SoR freshness forever; ranking algorithm specifics |

### 14.2 KnowledgeHits Identity

Each published KnowledgeHits artifact has a unique **KnowledgeHits ID** (publication identity).

KnowledgeHits ID supports observability, replay, debugging, and audit correlation.

KnowledgeHits ID is **not** a deterministic comparison identity for whole Decisions.

Unlike Context, KnowledgeHits does **not** define a Decision-level Fingerprint. Semantic Decision comparison remains at the Context level (Document `003`).

### 14.3 Input contracts

| Contract | Producer | Use |
|----------|----------|-----|
| **Retrieval request signals** | Context assembly / Coordinator boundary | Scope, intent hints, bounds |
| **EffectiveConfig / CapabilitySet** | Platform Services (via boundary) | Retrieval policy bounds |
| **Approved knowledge projections** | Adapters / SoR gateways | Materials eligible for Hits |

### 14.4 Versioning

- Additive provenance fields or scope dimensions → Minor
- Changing ownership away from KnowledgeHits-only → ADR + Document `002`
- Breaking Hit semantics that Context/Verification depend on → coordinate with Documents `003` / `007`

---

## 15. Extension Model

| Extension | How |
|-----------|-----|
| **New Knowledge Domain** | Additive domain vocabulary via contract versioning; does not change SoR ownership |
| **New Knowledge Source** | Behind SoR + Authority; may map into one or more Domains; Knowledge Engine consumes approved projections |
| **New Authority workflow** | Remains outside Core UI; Hits still require approval signals |
| **New scope dimension** | Additive contract versioning |
| **Tighter bounds** | EffectiveConfig / Capability policy |
| **Replace retrieval backend** | Allowed if KnowledgeHits contract remains stable (Document `002` replaceability) |

**Forbidden:** Channel-specific second Knowledge Engine; inventing Hits in Prompt/Model Gateway; Hub becoming merchant KB SoR without ADR; unapproved learning samples as KnowledgeHits.

---

## 16. Failure Philosophy

If required knowledge cannot be retrieved:

1. Publish **explicit incompleteness** (Invalid — incomplete or Valid with gap warnings)
2. Allow Context / Decision paths to Clarify, Hold, Escalate, or Draft-only
3. **Never fabricate knowledge**
4. **Never guess product information**
5. **Never infer merchant policy**
6. Never send or commit commerce from the Knowledge Engine
7. Never call Model Gateway to invent missing approved knowledge

---

## 17. Observability

Published KnowledgeHits should support:

- Traceability of included / excluded / missing knowledge
- Provenance visibility (source, authority, revision)
- Correlation via **KnowledgeHits ID**, and with Context ID when embedded in Decision Context (`003`)
- Evaluation/shadow under matched Context Fingerprints

Observability artifacts must not become Knowledge SoR.

---

## 18. Replaceability

Knowledge Engine internals may be replaced if:

- KnowledgeHits contract remains stable
- Ownership remains KnowledgeHits-only
- Business systems remain Knowledge SoR
- Documents `001`–`004` constraints and KN-INV-* hold
- Retrieval still enforces approved-only, bounded, non-inventing behavior

Retrieval technology may churn without rewriting Decision Engine or Context ownership.

---

## 19. Mapping to Lifecycle

| Lifecycle stage (`001`) | Knowledge Engine contribution |
|-------------------------|--------------------------------|
| **Understand** | Primary — retrieve approved knowledge for grounding inputs |
| **Clarify** | Gaps / incompleteness justify Clarification |
| **Verify** | KnowledgeHits (with provenance) support Grounding checks |
| **Decide** | Hits inform recommendation content; Engine does not Decide |
| **Respond** | None — Execution Boundary |
| **Learn** | Does not activate learning; Learning Engine is separate (`008`) |

| Core Pipeline (`002`) | Role |
|-----------------------|------|
| With Context continuity / grounding | Perform Knowledge Retrieval; publish KnowledgeHits |
| Verification / Decision | Consume Hits via Context or Coordinator-supplied views |

```text
005 Knowledge     → contributes KnowledgeHits to Context
003 Context       → owns Decision Context
004 Conversation  → contributes ConversationView (dialogue), not Knowledge
006 Memory        → MemoryView (distinct from Knowledge)
007 Decision      → consumes Context (including KnowledgeHits)
008 Learning      → approved patterns (distinct from Knowledge SoR authoring)
009 Adapters      → may project SoR materials into Core
011 Migration     → maps Plugin KB / packs → KnowledgeHits
```

These engines contribute to or consume KnowledgeHits / Context; **none of them redefine Knowledge SoR or Decision Context**.

---

## 20. Validation Notes

Validated against Documents `001`–`004`, Hub Backend, and WordPress Plugin discovery. No production redesign.

### Conflict V-005-1 — Knowledge Engine not in production runtime

| | |
|--|--|
| **Conflict** | Production Sales Agent retrieves KB/catalog grounding inside Plugin flows; Hub Knowledge Engine is Target Architecture. |
| **Risk** | Treating Document `005` as shipped. |
| **Suggested resolution** | Introduce Knowledge Engine through Document `011` Migration; map Plugin KB retrieval onto KnowledgeHits; keep default merchant path unchanged until flagged migration. |

### Conflict V-005-2 — Plugin KB SoR vs Wise Hits

| | |
|--|--|
| **Conflict** | Temptation to move authoring SoR to Hub. |
| **Risk** | Bypassing Plugin approve workflows (AC-4). |
| **Suggested resolution** | Plugin (and designated authorities) remain Knowledge SoR / Authority; Wise publishes Hits only (**KN-INV-2**, **KN-INV-5**). |

### Conflict V-005-3 — Commerce facts vs Knowledge

| | |
|--|--|
| **Conflict** | Prices/stock sometimes treated as “knowledge.” |
| **Risk** | Knowledge Engine becoming commerce authority. |
| **Suggested resolution** | Commerce fact snapshots remain Context/boundary projections from Plugin/Woo (AC-2); KnowledgeHits cover approved content/packs, not live commerce ownership. |

### Conflict V-005-4 — Document 002 “embeddings assist” vs this document

| | |
|--|--|
| **Conflict** | Document `002` mentions optional Model Gateway embeddings assist for Knowledge. |
| **Risk** | Reading Document `005` as defining embeddings/RAG architecture. |
| **Suggested resolution** | Any assist remains an optional implementation behind the KnowledgeHits contract. This document intentionally defines **no** embeddings/RAG/search law; technical design may follow later without changing KN-INV-*. |

### Conflict V-005-5 — KnowledgeHits vs Context ownership

| | |
|--|--|
| **Conflict** | Both may carry grounding materials. |
| **Risk** | Duplicate ownership / mutation races. |
| **Suggested resolution** | Knowledge Engine owns KnowledgeHits; Context Engine owns Decision Context and may embed/reference Hits without owning Knowledge SoR (`003` / **KN-INV-1**, **KN-INV-8**, **KN-INV-9**). |

No production paths were redesigned to resolve these conflicts.

---

## 21. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Knowledge / Grounding terminology; AC-4 |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Module map; KnowledgeHits ownership |
| [`003-context-engine.md`](003-context-engine.md) | Context consumes KnowledgeHits |
| [`004-conversation-engine.md`](004-conversation-engine.md) | Dialogue framing — distinct from Knowledge |
| [`006-memory-engine.md`](006-memory-engine.md) | MemoryView — distinct from Knowledge (**Approved**) |
| [`007-decision-system.md`](007-decision-system.md) | Consumes Hits for Decide/Verify (Draft) |
| [`008-learning-engine.md`](008-learning-engine.md) | Approved patterns — not Knowledge SoR authoring (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | May project SoR materials (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | May evaluate grounding under matched Context Fingerprints (Draft) |
| `011-migration-strategy.md` | Maps Plugin KB / packs → Knowledge Engine |
| `docs/adr/*` | Required for ownership or invariant breaks |

---

## 22. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Knowledge Retrieval → KnowledgeHits; Source/Authority/Scope/Provenance/Revision; KN-INV-1…10; quality attributes; Context relationship; Validation Notes V-005-1…5 |
| 0.1.1 | 2026-07-30 | Documentation Lead | Add **Knowledge Domain** (distinct from Source and Scope); Domain in Hits/Provenance; illustrative domain vocabulary |
| 0.1.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `006` Memory Engine Draft |
| 0.1.3 | 2026-07-30 | Documentation Lead | Align Memory ownership wording to MemoryView; Document `006` Approved |
| 0.1.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.1.5 | 2026-07-30 | Documentation Lead | Related docs: link Document `008` Learning Engine Draft |
| 0.1.6 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.1.7 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.1.8 | 2026-07-30 | Documentation Lead | Reference Architecture **1.0.0** freeze — **Status → Approved** · Frozen with Documents `001`–`011` |
