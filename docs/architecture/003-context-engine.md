# Context Engine

| Field | Value |
|-------|--------|
| **Title** | Context Engine |
| **Document ID** | `003` |
| **Version** | `0.2.7` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.1) |
| **Core Blueprint** | [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) (**Approved** v0.3.0) |

---

## 1. Document Information

This document defines the **canonical Context Engine** for Wise AI Core.

It deepens **only** the Context Engine: what Context is, how it is assembled, what it contains, ownership, lifetime, boundaries, validation, contracts, invariants, quality attributes, Context ID, fingerprinting, and extension.

It does **not** redefine Document `001` (Platform Constitution) or Document `002` (Core Blueprint). Those are inherited by reference.

**Conflict rules:**

1. If any statement here conflicts with Document `001`, **Document `001` wins**.
2. If any statement here conflicts with Document `002`’s module map, classification, Execution Boundary, or ownership table, **Document `002` wins** (unless an ADR updates Document `002`).
3. This document must not reorganize the Core. New Context facets are additive extensions under Document `002`’s stable module map.

**Terminology rule:** Throughout this document, **Context** refers to the **Decision Context** unless explicitly stated otherwise.

**Out of scope for this document:** implementation, APIs, database schemas, Laravel, WordPress, prompts, embeddings, RAG / vector search, token budgets, prompt truncation, caching, LLM providers, channel adapter internals (Document `009`), Decision System internals (Document `007`), Knowledge/Memory/Conversation engine deep-dives (`004`–`006`).

---

## 2. Purpose

Provide a production-grade architectural definition of Context so that:

- Every Core Pipeline decision begins with a **bounded, validated Context** (Context First)
- Downstream engines consume a stable **Decision Context** artifact, not private adapter state
- Channel adapters supply projections and snapshots without becoming the intelligence layer
- Migration (`011`) can map today’s Plugin Sales Agent “load settings / history / facts” stages onto Context without a big-bang rewrite
- Determinism, explainability, and evaluation remain possible because inputs to a Decision are explicit

---

## 3. Scope

**In scope:**

- Context Engine role within Document `002` classification (AI Core Engine)
- Meaning of Context relative to Conversation, Session, Memory, Knowledge, Decision
- Context facets (what Context contains)
- Assembly model and assembly order (architectural)
- Ownership of Decision Context
- Context lifetime and freshness
- Context Invariants and Context Quality Attributes
- Context Identity (**Context ID**) and Context Fingerprint
- Boundaries (what Context may / must not include or do)
- Context validation and incompleteness handling
- Conceptual contracts (not schemas)
- Extension model for new facets
- Failure, observability, and replaceability philosophies for Context
- Validation Notes against Document `001`/`002` and production reality

**Out of scope:**

- How Conversation Engine frames a turn (Document `004`)
- How Knowledge Engine retrieves (Document `005`)
- How Memory Engine stores session memory (Document `006`)
- How Decision Coordinator sequences the pipeline (Document `007`)
- How adapters fetch Meta/Woo data (Document `009`)
- Evaluation case catalogs (Document `010`)
- Implementation of any of the above

---

## 4. Relationship to Documents 001 and 002

| Inherited concept | How Document `003` uses it |
|-------------------|----------------------------|
| **Context First** (`001`) | Context Engine is the mandatory enforcement point before recommendation |
| **Core Terminology — Context** (`001`) | Canonical meaning of Context; this doc only deepens facets and assembly |
| **Decision Lifecycle — Understand** (`001`) | Context assembly is primary realization of Understand inputs |
| **Core Pipeline** (`002` §4A) | Context Engine is the first AI Core stage after Platform Services |
| **Module map** (`002`) | Context Engine remains an AI Core Engine; ownership = Decision Context |
| **Execution Boundary** (`002`) | Context never sends messages, creates orders, or commits commerce |
| **AI Core Principles** (`002`) | Stateless where possible; state-aware via Memory/Conversation views; contract-driven |
| **Platform Services** (`002`) | CapabilitySet and EffectiveConfig are inputs to Context, not owned by Context |
| **Determinism** (`001` / `002`) | Same Context + Knowledge + Configuration → reasonably consistent Decision |

```text
Document 001 — Context First (platform law)
        ↓
Document 002 — Context Engine in Core Pipeline + owns Decision Context
        ↓
Document 003 — this document (deepens Context Engine only)
```

---

## 5. Design Goals

These goals are **Context-specific** and must not contradict Documents `001`/`002`.

| ID | Goal |
|----|------|
| CX-1 | Every Decision request has exactly one **Decision Context** owned by the Context Engine |
| CX-2 | Context is **bounded** — finite window, snapshots, and handles — never an unbounded dump of history or catalogs |
| CX-3 | Context is **channel-agnostic** — channel identity appears as flags/capabilities, not Meta transport logic |
| CX-4 | Context carries **commerce fact snapshots**, never live mutable Woo authority |
| CX-5 | Context assembly is **explicit and explainable** — missing facets are visible, not silently invented |
| CX-6 | Context remains **replaceable** behind the Context contract without rewriting Decision Engine |
| CX-7 | Context supports **shadow/evaluation** by making decision inputs comparable across runs |
| CX-8 | Context respects **package gates, pause, quiet hours, and human override** as configuration/capability inputs — it does not invent policy |

---

## 5A. Context Invariants

Document `001` defines Platform Invariants. Document `002` defines Core structure and ownership. The following are the **laws of the Context Engine**.

They are expected to remain true unless an ADR updates this document (and Document `002` when ownership or module-map impact applies).

| ID | Invariant |
|----|-----------|
| **CX-INV-1** | Every Decision has exactly one Decision Context. |
| **CX-INV-2** | Decision Context is immutable once published. |
| **CX-INV-3** | Context never owns Commerce authority. |
| **CX-INV-4** | Context never owns Conversation SoT. |
| **CX-INV-5** | Context is bounded. |
| **CX-INV-6** | Missing information is explicit, never fabricated. |
| **CX-INV-7** | Context is channel-agnostic. |
| **CX-INV-8** | Context never crosses the Execution Boundary. |

**Notes:**

- **CX-INV-1** — One published Decision Context per Decision request; each publication has a unique **Context ID**. Superseding republication yields a new Context ID (and a new immutable Context), not concurrent competing Contexts.
- **CX-INV-2** — Downstream modules read Context; they do not mutate it in place. A corrected assembly is a new publication by the Context Engine (new Context ID).
- **CX-INV-3…4** — Snapshots and projections only; Plugin / Woo remain SoT.
- **CX-INV-8** — Aligned with Document `002` Execution Boundary: no send, no order create, no commerce commit from Context.

---

## 5B. Context Quality Attributes

Document `001` defines platform-wide Quality Attributes. The following are **not** new platform requirements. They are the qualities a good Decision Context should exhibit.

| Attribute | Meaning for Context |
|-----------|---------------------|
| **Completeness** | Required facets for the attempted path are present, or incompleteness is explicitly marked |
| **Freshness** | Snapshots and windows are within allowed freshness; staleness is signaled, not ignored |
| **Boundedness** | Finite conversation window, knowledge scope, and catalog/fact snapshots — never unbounded dumps |
| **Consistency** | Facets do not contradict each other in ways that force invented resolution inside Context |
| **Traceability** | Assembly explainability seeds show what was included, excluded, warned, or missing |
| **Determinism** | Same adapter materials + CapabilitySet + EffectiveConfig + peer views → reasonably identical Context and Context Fingerprint when assembly policy is pinned |

These attributes support Document `001` Reliability, Explainability, Observability, and Determinism **as applied to Context**, without redefining platform law.

---

## 6. What Context Is

### 6.1 Inherited definition

From Document `001` Core Terminology:

> **Context** — The bounded bundle of inputs Wise uses for one decision: recent messages, settings, channel capability flags, lead/memory snapshot, and commerce fact snapshot.

Document `003` treats that definition as **law**. The remainder of this section deepens structure without changing meaning.

### 6.2 Decision Context (owned artifact)

Document `002` Internal State Ownership:

| Module | Owns |
|--------|------|
| **Context Engine** | **Decision Context** |

**Decision Context** is the complete, validated Context artifact for one Decision episode (or one Decision request within a Session). Downstream modules read Decision Context; they do not rebuild a competing “private context.”

### 6.3 What Context is not

| Not Context | Why | Owner / doc |
|-------------|-----|-------------|
| **Conversation** (inbox SoT) | Merchant-visible thread persistence | Plugin; framed by Conversation Engine (`004`) |
| **Session** | Continuity episode concept spanning turns | Platform term (`001`); Memory aids continuity (`006`) |
| **Knowledge** | Approved content corpus / retrieval results | Knowledge Engine owns KnowledgeHits (`005`) |
| **Memory** | Short-horizon dialogue state store | Memory Engine owns MemoryView (`006`) |
| **Decision** | Recommendation output | Decision Engine (`007`) |
| **Action execution** | Send, order create, courier mutate | Outside Execution Boundary |
| **Entitlement catalog / settings UI** | Billing and merchant UX | Hub/Plugin; Platform Services expose snapshots |

Context may **include projections, handles, and snapshots** from Conversation, Memory, Knowledge, Capability, and Configuration. Including a projection does not transfer ownership of the underlying SoT.

---

## 7. Context Engine Role

| | |
|--|--|
| **Classification** (`002`) | AI Core Engine |
| **Purpose** | Enforce **Context First** by assembling and validating the bounded Decision Context for every decision request |
| **Primary output** | Decision Context (Context contract) |
| **Pipeline position** | After Platform Services (Capability Registry + Configuration Manager); before / with Conversation + Memory views and Knowledge retrieval as assembly contributors |

### 7.1 Responsibilities

- Accept adapter-normalized request materials (projections and snapshots only)
- Resolve or attach **CapabilitySet** and **EffectiveConfig** from Platform Services
- Obtain **ConversationView** / turn framing inputs via Conversation Engine (or conversation projection suitable for assembly)
- Obtain **MemoryView** via Memory Engine when continuity requires it
- Attach **knowledge handles** or invoke Knowledge Engine for retrieval inputs required by Context First (without owning KnowledgeHits as a permanent SoT)
- Attach **commerce fact snapshot** supplied at the Core boundary
- Attach **channel context flags** (capabilities, modality, locale hints) without Meta transport semantics
- Produce a single **Decision Context** that is bounded, validated, and explainable
- Emit incompleteness / freshness signals when required facets are missing or stale

### 7.2 Non-responsibilities

- Fetching Meta Graph history, Page tokens, webhooks, or send transport (AC-3 / INV-4)
- Querying WooCommerce as live mutable authority (AC-2 / INV-2 / INV-6)
- Owning inbox/message SoT
- Choosing Actions, scoring confidence, or coordinating the Decision Lifecycle (Decision Engine / Decision Coordinator)
- Running Safety/Verification policies (Support engines under `007`)
- Calling models as a substitute for missing Context
- Persisting long-term training corpora from raw chat (AC-9)
- Sending messages, creating orders, or committing commerce (Execution Boundary)

---

## 8. What Context Contains (Facets)

Decision Context is composed of **facets**. Facets are architectural groups, not database columns.

### 8.1 Required facets (every Decision Context)

| Facet | Content (conceptual) | Source |
|-------|----------------------|--------|
| **Identity** | Store / tenant identity, conversation identity, decision-request identity | Adapter boundary |
| **CapabilitySet** | Effective Wise/channel capabilities for this store/channel | Capability Registry |
| **EffectiveConfig** | Mode, confidence floors, quiet hours, persona, pause, catalog-scope flags | Configuration Manager |
| **Channel context flags** | Channel type, modality hints, capability flags — **not** transport APIs | Adapter |
| **Conversation window** | Bounded recent turns / conversation projection for this decision | Adapter projection → Conversation Engine framing |
| **Trigger input** | Normalized current customer/operator input that prompted this decision | Adapter |

### 8.2 Continuity facets (required when Session continuity applies)

| Facet | Content (conceptual) | Source |
|-------|----------------------|--------|
| **Memory snapshot** | Short-horizon MemoryView (summary, language, asked flags, soft counts, etc.) | Memory Engine |
| **Lead / thread signals** | Channel-neutral lead or thread state signals already projected (not Plugin UI) | Adapter projection |

If continuity applies and Memory is unavailable, Context validation must mark **incompleteness** rather than invent memory.

### 8.3 Grounding facets (required before Decide asserts commerce)

| Facet | Content (conceptual) | Source |
|-------|----------------------|--------|
| **Commerce fact snapshot** | Price/stock/variation/cart/order facts **as provided** for this decision — snapshot only | Plugin / commerce boundary via adapter |
| **Knowledge handles / hits** | Handles to approved knowledge scope and/or KnowledgeHits for this decision | Knowledge Engine |

Context First forbids deciding from the latest message alone. Grounding facets may be empty **only** when the Decision Engine path is explicitly Clarify / Hold / Escalate because facts or knowledge are missing — never by inventing facts.

### 8.4 Optional / assist facets

| Facet | Content (conceptual) | Notes |
|-------|----------------------|--------|
| **Locale / language hints** | Detected or configured language signals | Must not replace Conversation/Memory language ownership |
| **Prior Decision reference** | Shadow baseline or previous recommendation id for evaluation | Evaluation / migration use |
| **Explainability seeds** | Assembly reason codes (what was included / excluded) | Supports Observability |

### 8.5 Explicitly excluded from Context

| Excluded | Why |
|----------|-----|
| Raw Meta payloads / Page access tokens | Transport / secrets; adapter-owned |
| Full Woo catalog dumps | Unbounded; violates bounded Context |
| Unapproved learning samples | AC-4 |
| Fraud Order Intelligence graphs as default memory | Separate plane unless ADR |
| Private LLM logs as the only Context | Observability must be structured |
| Execution instructions (“send now”, “create order”) as Core authority | Recommendation vs Execution |

---

## 9. How Context Is Assembled

Assembly is an **architectural process**, not an implementation sequence. The Decision Coordinator may invoke Context Engine as part of the Core Pipeline; Context Engine may call Platform Services and peer Core engines for assembly contributions.

```text
Adapter-normalized request materials
        ↓
Capability Registry → CapabilitySet
Configuration Manager → EffectiveConfig
        ↓
Context Engine begins assembly
        ↓
Conversation Engine → ConversationView / turn framing inputs
Memory Engine → MemoryView (when continuity applies)
Knowledge Engine → knowledge handles / KnowledgeHits (when grounding requires)
        ↓
Attach commerce fact snapshot (boundary-supplied)
Attach channel context flags
        ↓
Bound + validate
        ↓
Decision Context (owned by Context Engine)
        ↓
Downstream Core Pipeline (Decision Coordinator / Decision Engine / …)
```

### 9.1 Assembly principles

1. **Snapshots in, never live authority in.** Commerce and inbox SoT stay outside Core.
2. **Bound before enrich.** Window sizes and catalog scope are finite by policy (EffectiveConfig / Capability).
3. **Compose, don’t fork.** Channel-specific raw shapes are normalized at the adapter boundary before Context sees them.
4. **No model fill-in for missing facts.** Missing commerce or knowledge → incompleteness signal → Clarify/Hold/Escalate paths later — not hallucinated Context.
5. **One owner.** Only Context Engine publishes Decision Context for a request.

### 9.2 Relationship to Conversation / Memory / Knowledge during assembly

| Engine | Role relative to Context |
|--------|---------------------------|
| **Conversation Engine** | Supplies Turn Framing / ConversationView used inside or alongside Context; does not own Decision Context |
| **Memory Engine** | Supplies MemoryView snapshot for continuity; does not own Decision Context |
| **Knowledge Engine** | Supplies retrieval results or handles; owns KnowledgeHits, not Decision Context |
| **Platform Services** | Supply CapabilitySet and EffectiveConfig; not AI reasoning |

Circular private-state reads are forbidden (Document `002` communication rules). Assembly uses **contracts**.

---

## 10. Context Ownership

| Concern | Owner |
|---------|--------|
| **Decision Context artifact** | Context Engine |
| **Inbox / message persistence** | Plugin (Conversation SoT) |
| **MemoryView** | Memory Engine |
| **Approved knowledge corpus** | Plugin authoring + Knowledge Engine retrieval |
| **Capability / configuration resolution** | Platform Services |
| **Commerce facts authority** | Plugin / WooCommerce |
| **Channel transport** | Adapters / Hub channel edge |

**Rule:** Other modules may **read** Decision Context. They must not silently mutate it into a divergent private copy that becomes the de-facto decision input (**CX-INV-2**). If a facet must change mid-pipeline (rare), the Context Engine **republishes** a new Decision Context for that request — never an ad-hoc rewrite inside Prompt or Model Gateway.

---

## 11. Context Lifetime

| Concept | Lifetime |
|---------|----------|
| **Decision Context** | Request / decision scoped — created for one Decision request; discarded or archived for observability after the Decision completes |
| **Session** | May span multiple Decision Contexts over time |
| **Conversation** | Longer than Session; Plugin SoT |
| **MemoryView inside Context** | Snapshot at assembly time; later Memory updates do not retroactively mutate a finished Decision Context |
| **Commerce fact snapshot** | Valid only for the decision window; staleness is a validation concern |
| **CapabilitySet / EffectiveConfig** | Snapshot for this decision; entitlement changes apply to subsequent decisions |

### 11.1 Freshness

Context Engine should expose freshness signals when:

- Commerce snapshot is older than the decision’s allowed freshness policy
- Conversation window excludes material turns the adapter failed to project
- Memory snapshot is missing under an active Session
- Knowledge handles point to unpublished or revoked content

Freshness failures do not authorize inventing data. They authorize safer Actions later (Clarify, Hold, Escalate, Draft).

### 11.2 Statelessness

Aligned with Document `002` AI Core Principles:

- Prefer **stateless** Context assembly per request
- Be **state-aware** by including Memory/Conversation snapshots when needed
- Do not hide long-lived Core-side “shadow inbox” as Context SoT

---

## 12. Context Boundaries

### 12.1 Hard boundaries (must not cross)

| Boundary | Rule |
|----------|------|
| **Execution Boundary** (`002`) | Context assembly never sends, never creates orders, never commits commerce |
| **Channel-agnostic** (AC-1) | No Meta/Widget business logic hard-coded inside Context Engine |
| **Commerce SoT** (AC-2) | Context holds snapshots only |
| **Meta independence** (AC-3) | No Graph/token dependency inside Context Engine |
| **Approval workflows** (AC-4) | Unapproved knowledge/learning must not enter Context as approved |
| **Contracts** (AC-5) | Adapters supply projections via contracts, not private helpers |
| **PII minimization** (AC-9) | Cross-tenant Context must not default to raw chat training stores |
| **Webhook latency** (AC-8) | Full Context assembly is not performed on Meta webhook acknowledgment path |

### 12.2 Soft boundaries (policy)

- Conversation window size and knowledge scope are bounded by EffectiveConfig / Capability
- Optional facets may be omitted when Capability disables a feature
- Human override / pause signals in EffectiveConfig must remain visible in Context

---

## 13. Context Validation

Validation occurs **before** Decision Context is published as ready for Decide. Validation is architectural quality control, not a code schema.

### 13.1 Validation outcomes

| Outcome | Meaning |
|---------|---------|
| **Valid** | Required facets present; bounds respected; snapshots coherent enough to proceed |
| **Valid with warnings** | Proceed allowed; incompleteness/freshness warnings attached for explainability |
| **Invalid — incomplete** | Missing required facet for the attempted path; Coordinator/Decision Engine must Clarify, Hold, or Escalate — not invent |
| **Invalid — unsafe projection** | Adapter materials violate boundaries (e.g. raw secrets, unapproved knowledge marked approved); reject assembly |

### 13.2 Validation checks (conceptual)

1. **Identity present** — store + conversation + request identity
2. **CapabilitySet + EffectiveConfig attached**
3. **Trigger input normalized** (channel-neutral)
4. **Conversation window bounded** and non-empty when Continuity/Understand requires dialogue
5. **Memory present or explicitly marked absent** when Session continuity applies
6. **Commerce snapshot present or explicitly marked absent** when the path would assert commerce claims
7. **Knowledge handles present or explicitly marked absent** when the path would answer from knowledge
8. **No forbidden materials** (tokens, unbounded catalogs, unapproved learning)
9. **Explainability seeds** record what was included/excluded

### 13.3 Fail-safe posture

Aligned with Document `001` Reliability and Document `002` Failure Philosophy:

- Prefer **Clarify / Draft / Human** over proceeding on invented Context
- Never “repair” missing price/stock by model imagination inside Context Engine
- Quiet hours / pause / package gates remain visible so downstream can short-circuit safely

---

## 14. Context Contracts

Contracts are conceptual boundaries. This document does **not** define schemas, field types, or API routes.

### 14.1 Primary contract — Decision Context

| | |
|--|--|
| **Name** | **Context** / Decision Context |
| **Producer** | Context Engine |
| **Consumers** | Decision Coordinator, Decision Engine, Safety, Verification, Prompt, Knowledge (as input), Memory (as input), Evaluation |
| **Guarantees** | Bounded; validated outcome attached; channel-agnostic; snapshot-based commerce; no execution authority; immutable once published (**CX-INV-2**) |
| **Non-guarantees** | Live Woo freshness forever; complete inbox history; model correctness |

### 14.2 Context Identity contracts

| Contract | Role |
|----------|------|
| **Context ID** | Unique identity of one published Decision Context; changes on every republication |
| **Context Fingerprint** | Deterministic comparison identity; identical when published Context + assembly policy are equivalent |

| | Context ID | Context Fingerprint |
|--|------------|---------------------|
| **Producer** | Context Engine | Context Engine |
| **Consumers** | Audit trails, replay, correlation, Decision linkage | Evaluation, shadow, regression, determinism debug |
| **Stability** | Unique per publication | Stable across equivalent publications |
| **Is not** | Fingerprint; not Decision Context body | Context ID; not Decision Context body |

### 14.3 Input contracts (consumed during assembly)

| Contract | Producer | Use in Context |
|----------|----------|----------------|
| **CapabilitySet** | Capability Registry | Required facet |
| **EffectiveConfig** | Configuration Manager | Required facet |
| **ConversationView** | Conversation Engine | Continuity / turn framing |
| **MemoryView** | Memory Engine | Continuity snapshot |
| **KnowledgeHits** / knowledge handles | Knowledge Engine | Grounding facet |
| **Adapter Request Materials** | Channel adapter | Identity, trigger, projections, commerce snapshot, channel flags |

### 14.4 Versioning

- Additive facet additions → Minor (Document `003` + possibly `002` notes if ownership changes)
- Removing/renaming required facets or changing ownership → ADR + Document `002` update + Major as needed
- Adapter-facing material contracts prefer stable exteriors (INV-8)

---

## 15. Extension Model

Context extends by **additive facets and projection types**, not by forking a Messenger-specific Context Engine.

| Extension | How |
|-----------|-----|
| **New channel** | Adapter supplies same conceptual materials with new channel flags; Context Engine unchanged in law |
| **New modality** (image/voice/PDF summary) | Normalized trigger + optional modality facet; no Meta-specific Core logic |
| **New configuration keys** | Configuration Manager → EffectiveConfig; Context attaches without owning settings UI |
| **New capability keys** | Capability Registry → CapabilitySet |
| **New grounding domain** (e.g. shipping promise snapshot) | New optional/required facet via ADR if it changes Decide semantics |
| **Tighter bounds** | EffectiveConfig/Capability policies; not hard-coded channel rules |

**Forbidden extension pattern:** A second Context builder inside Prompt, Model Gateway, or a channel adapter that bypasses Context Engine.

---

## 16. Failure Philosophy

When Context cannot be assembled safely:

1. Publish **Invalid — incomplete** or **Invalid — unsafe** with reason codes
2. Allow Coordinator/Decision Engine to Choose Clarify, Hold, Escalate, or Draft-only paths
3. Never invent commerce facts or approved knowledge to “complete” Context
4. Never call Model Gateway to fabricate missing Context facets
5. Never send or commit commerce from the Context Engine

If Platform Services fail (no Capability/Config), Context assembly fails closed for gated features.

---

## 17. Context Identity and Fingerprint

Published Decision Context has two related but distinct identities.

### 17.1 Context Identity

Decision Context has a unique **Context ID**.

**Context Fingerprint** is a deterministic comparison identity.

| | **Context ID** | **Context Fingerprint** |
|--|----------------|-------------------------|
| **Purpose** | Identify one publication | Compare semantic equivalence |
| **Nature** | Unique | Deterministic |
| **On republish** | Always new | Changes only if published Context or assembly policy meaningfully changes |
| **Example (illustrative)** | `CX-000245` | `9F7A…` |

```text
Context ID          ← unique publication
        ↓
Fingerprint         ← semantic comparison
        ↓
Evaluation · Regression · Shadow · Replay · Debugging
```

- **Republishing** creates a new Context ID (**CX-INV-2**).
- **Fingerprint** changes only if the published Context or assembly policy meaningfully changes.
- This distinction matters when replaying or comparing Decisions: same fingerprint with different Context IDs means equivalent inputs published more than once; different fingerprints means inputs (or policy) differed.

Illustrative ID/fingerprint strings above are **not** schemas or encoding rules.

### 17.2 Context Fingerprint

A **Context Fingerprint** is not the Context itself and is not the Context ID.

It is a **deterministic comparison identity** derived from a published Decision Context (and its assembly policy version), used so that Evaluation, regression, shadow, debugging, and observability can ask: “Were these Decisions made from equivalent inputs?”

```text
Decision Context (Context ID)
        ↓
Context Fingerprint
        ↓
Evaluation · Regression · Shadow · Debugging · Observability
```

| | |
|--|--|
| **What it is** | A stable identity for comparing Contexts across runs |
| **What it is not** | A substitute for Decision Context; not Context ID; not Knowledge; not a Decision; not an API schema |
| **Producer** | Context Engine (at or after publish) |
| **Consumers** | Evaluation Engine (`010`), shadow/migration (`011`), operators debugging determinism |
| **Guarantee** | Same pinned inputs + assembly policy → same fingerprint |
| **Non-guarantee** | Semantic equality of every optional assist facet if policy marks them non-deterministic |

**Uses:**

| Use | How fingerprint helps |
|-----|------------------------|
| **Regression testing** | Confirm fixture Decisions still see equivalent Context (matched fingerprint) |
| **Evaluation** | Pair baseline vs candidate Decisions under matched Context |
| **Shadow mode** | Compare Plugin baseline and Wise candidate when fingerprints match |
| **Debugging** | Detect “same message, different Context” vs “equivalent Context, different Decision”; use Context ID to point at a specific publication |
| **Observability** | Correlate turns without dumping full Context bodies as the only signal |
| **Replay** | Address a specific publication via Context ID; judge equivalence via Fingerprint |

Neither Context ID nor Fingerprint becomes a parallel SoT that replaces Decision Context (**CX-INV-1**).

---

## 18. Observability Philosophy

Decision Context should support:

- **Explainability** — which facets were present, warned, or missing (Traceability)
- **Determinism debugging** — Context Fingerprint comparison for shadow and regression; Context ID for a specific publication
- **Evaluation** — Evaluation Engine may compare baseline vs candidate Decisions against matched fingerprints / Context materials
- **Audit** — reason codes and Context ID suitable for merchant-visible or operator-visible trails without requiring private LLM logs

Observability artifacts must not become a parallel SoT that replaces Decision Context.

---

## 19. Replaceability

Context Engine internals may be replaced if:

- The Decision Context contract remains stable for consumers
- Context Fingerprint semantics remain comparable under a declared assembly policy version
- Ownership remains with Context Engine
- Documents `001`/`002` constraints, Execution Boundary, and Context Invariants hold
- Assembly still enforces Context First

Highest-churn internals (windowing strategy, projection normalization helpers) must not leak into Decision Engine.

---

## 20. Mapping to Decision Lifecycle and Core Pipeline

| Document `001` Lifecycle | Context Engine contribution |
|--------------------------|-----------------------------|
| **Understand** | Primary — assemble Decision Context (conversation, config, memory, knowledge handles, commerce snapshot, channel flags) |
| **Clarify** | Supplies incompleteness signals that justify Clarification Actions |
| **Verify** | Provides commerce/knowledge snapshots Verification consumes (does not perform Verification) |
| **Decide** | Provides the only sanctioned Decision Context input bundle |
| **Respond** | None inside Core — Execution Boundary (**CX-INV-8**) |
| **Learn** | May carry prior markers/references; does not activate learning |

| Document `002` Core Pipeline stage | Context role |
|------------------------------------|--------------|
| After Platform Services | Assemble Decision Context |
| Before Decision Coordinator action selection | Publish Valid / Valid-with-warnings / Invalid Context (+ Context ID + Fingerprint) |
| During later stages | Read-only input unless republished by Context Engine (**CX-INV-2**) |

### 20.1 Downstream document dependency (natural graph)

Document `003` is the Context foundation for later engines. It does not redefine them.

```text
004 Conversation  →  contributes Turn Framing / ConversationView to Context
005 Knowledge     →  contributes KnowledgeHits / handles to Context
006 Memory        →  contributes MemoryView to Context
007 Decision System → consumes Context (and Context ID / Fingerprint for eval/shadow)
```

These engines contribute to or consume Context; **none of them redefine Context**.

Contributors do not own Decision Context. The Decision System does not assemble a competing Context.

---

## 21. Validation Notes

Validated against Documents `001`/`002` and production discovery. Approved in v0.2.0; v0.2.1 adds Context ID vs Fingerprint; no production redesign.

### Conflict V-003-1 — Context Engine not in production runtime

| | |
|--|--|
| **Conflict** | Production Sales Agent assembles settings/history/facts inside Plugin manager flows; Hub Wise Context Engine is Target Architecture. |
| **Risk** | Treating Document `003` as shipped. |
| **Suggested resolution** | Map Plugin “load settings / thread / facts” onto Decision Context in Document `011`; keep default merchant path unchanged until flagged migration. |

### Conflict V-003-2 — Conversation window SoT vs Context

| | |
|--|--|
| **Conflict** | Inbox SoT is on Plugin; Context needs a conversation window. |
| **Risk** | Duplicating inbox as Hub SoT. |
| **Suggested resolution** | Adapters project bounded windows into Core; Plugin remains Conversation SoT (`004` deepens framing). |

### Conflict V-003-3 — Live Woo vs commerce snapshot

| | |
|--|--|
| **Conflict** | Production reads Woo facts at decision time on Plugin. |
| **Risk** | Context Engine querying Woo directly on Hub. |
| **Suggested resolution** | Snapshot at boundary; Plugin remains commerce authority (AC-2 / **CX-INV-3**). |

### Conflict V-003-4 — Knowledge/Memory ownership during assembly

| | |
|--|--|
| **Conflict** | Context assembly uses Knowledge/Memory engines. |
| **Risk** | Context Engine absorbs KnowledgeHits or MemoryView ownership. |
| **Suggested resolution** | Document `002` ownership table stands: Context owns Decision Context only; Knowledge owns KnowledgeHits; Memory owns MemoryView (**CX-INV-4** for Conversation SoT). |

No production paths were redesigned to resolve these conflicts.

---

## 22. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Context First, terminology, constraints, invariants |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | Module map, ownership, Core Pipeline, Execution Boundary |
| [`004-conversation-engine.md`](004-conversation-engine.md) | Contributes Turn Framing / ConversationView to Context (**Approved**) |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | Contributes KnowledgeHits / handles to Context (Draft) |
| [`006-memory-engine.md`](006-memory-engine.md) | Contributes MemoryView to Context (**Approved**) |
| [`007-decision-system.md`](007-decision-system.md) | Consumes Context; Clarify/Decide on incompleteness (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | Supplies adapter-normalized request materials (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | Uses Context Fingerprint (and Context ID for publication linkage) (Draft) |
| `011-migration-strategy.md` | Maps Plugin assembly stages → Context Engine; shadow via fingerprint; replay via Context ID |
| `docs/adr/*` | Required for invariant, ownership, or required-facet breaks |

---

## 23. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: Context definition deepening, facets, assembly, ownership, lifetime, boundaries, validation, contracts, extension; Validation Notes V-003-1…4 |
| 0.2.0 | 2026-07-30 | Documentation Lead | Terminology rule (Context = Decision Context); Context Invariants CX-INV-1…8; Context Quality Attributes; Context Fingerprint; downstream dependency graph; **Status → Approved** |
| 0.2.1 | 2026-07-30 | Documentation Lead | Context ID vs Fingerprint; reinforce `004`–`007` do not redefine Context; explicit non-goals (tokens/embeddings/caching/providers) |
| 0.2.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `006` Memory Engine Draft |
| 0.2.3 | 2026-07-30 | Documentation Lead | Align Memory ownership wording to MemoryView; Document `006` Approved |
| 0.2.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.2.5 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.2.6 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.2.7 | 2026-07-30 | Documentation Lead | Included in Wise AI Platform Reference Architecture **1.0.0** freeze (`001`–`011`) |
