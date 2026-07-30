# Wise AI Core Architecture

| Field | Value |
|-------|--------|
| **Title** | Wise AI Core Architecture |
| **Document ID** | `002` |
| **Version** | `0.4.0` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Foundation** | [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) (**Approved** v0.4.9) |

---

## 1. Document Information

This document defines the **internal organization** of **Wise AI Core** — the channel-agnostic Intelligence Layer hosted on the Hub Backend (Document `001` Target Architecture).

It explains **how Wise is structured internally**: modules, boundaries, dependencies, request flow, and replaceability.

It does **not** redefine Document `001` law (Vision, North Star, Design Philosophy, Design Principles, Context First, Constraints, Invariants, Quality Attributes, Core Terminology). Those are inherited by reference.

**Conflict rule:** If any statement here conflicts with Document `001`, **Document `001` wins**.

**Core authority:** The module map defined in this document is considered **stable**. Future engine documents may deepen module behavior but must not reorganize the Core without an ADR and an update to this document.

**Architecture freeze (Wise AI Platform Reference Architecture 1.0.0):** Documents `001`–`011` are **Approved** and **Frozen**. No new architectural concepts; no ownership changes; no boundary changes; no new published artifacts — except through ADRs and architecture governance. No new architecture documents after `011`. Engineering lives under `docs/implementation/` and must not expand platform law.

---

## 2. Purpose

Provide a stable internal architecture for Wise so that:

- Engine documents (`003`–`010`) deepen modules without inventing conflicting boundaries
- Channel adapters (`009`) invoke Wise through stable contracts, not private module state
- Migration from the Plugin Sales Agent baseline (`011`) can map existing stages onto Wise modules without a big-bang rewrite
- Implementations can replace modules independently while preserving external contracts

---

## 3. Scope

**In scope:**

- Wise AI Core principles (engineering guidance for engines)
- Module classification (Platform Services vs AI Engines)
- Wise AI Core internal modules and responsibilities
- Internal state ownership and Execution Boundary
- Module boundaries, communication rules, and dependency rules
- Allowed and forbidden dependencies
- Logical request flow through Wise (architectural, not implementation)
- Internal contract concepts (not API schemas)
- Extension and replaceability model
- Failure, scalability, and observability philosophies for the Core
- Validation Notes against Document `001`, Hub Backend, and WordPress Plugin

**Out of scope:**

- Channel adapter internals (Document `009`)
- Migration sequencing detail (Document `011`)
- Requirements, acceptance criteria, and evaluation case catalogs (requirements + Document `010`)
- Implementation: code, APIs, database tables, class diagrams, Laravel, WordPress, pseudocode
- Redefinition of Document `001` foundation content

---

## 4. Relationship to Document 001

| Document `001` concept | How Document `002` uses it |
|------------------------|----------------------------|
| **Architecture North Star** | Wise Core is the single intelligence layer modules must realize |
| **Design Philosophy** | Adapters connect; Wise understands; Commerce validates; Operators approve; Channels deliver — Core only owns “Wise understands / recommends” |
| **Context First** | Context Engine is mandatory on every request path |
| **Decision Lifecycle** | Conceptual stages in Document `001`; realized internally by the **Core Pipeline** in this document (see §4A) |
| **Architectural Constraints / Invariants** | Binding on every module (channel-agnostic, no commerce ownership, no Meta transport, recommendations not commits) |
| **Quality Attributes** | Reliability, Determinism, Explainability, Observability, etc. apply to Core module design |
| **AI Capability Boundaries** | Core Responsibilities / Non-Responsibilities restated only as module ownership, not new product law |
| **Platform Layers** | This document details the **Intelligence Layer** only |
| **Architecture Decision Hierarchy** | This document conforms to `001` and Accepted ADRs |

---

## 4A. Decision Lifecycle vs Core Pipeline

Document `001` and Document `002` describe **different levels**. They do not conflict.

```text
Document 001
Decision Lifecycle (conceptual platform law)
  Understand → Clarify → Verify → Decide → Respond → Learn
        ↓
   implemented by
        ↓
Document 002
Core Pipeline (internal realization inside Wise)
  Context → Conversation/Memory → Knowledge
        → Decision Coordinator
        → Decision Engine
        → Safety → Verification
        → Prompt / Model (optional)
        → Verification (again if needed)
        → Recommendation
        → Learning / Evaluation markers
        ── Execution Boundary ──
        → Respond / Learn activation outside Core
```

| Level | Owner | What it answers |
|-------|--------|-----------------|
| **Decision Lifecycle** | Document `001` | What stages every Wise decision must conceptually pass through |
| **Core Pipeline** | Document `002` | Which modules realize those stages inside Wise, and in what architectural order |

**Mapping (conceptual → realization):**

| Lifecycle stage (`001`) | Primary Core realization (`002`) |
|-------------------------|----------------------------------|
| **Understand** | Context + Conversation + Knowledge (+ Memory); Decision Engine intent/understanding |
| **Clarify** | Decision Engine clarification choices |
| **Verify** | Verification Engine (pre- and post-model) + Safety Engine |
| **Decide** | Decision Engine recommendation; Coordinator finalizes Decision envelope |
| **Respond** | Outside Core — adapters / Plugin apply Respond rules after Execution Boundary |
| **Learn** | Learning Engine markers inside Core; activation remains approval-gated outside |

Someone reading both documents should treat `001` as the **conceptual lifecycle** and `002` as the **internal pipeline**, not as two competing stage lists.

---

## 5. Design Goals

These goals are **Core-specific** and must not contradict Document `001`.

| ID | Goal |
|----|------|
| CG-1 | Organize Wise as **independent modules** with contract-based communication |
| CG-2 | Keep **zero channel / Meta / Woo ownership** inside Core modules |
| CG-3 | Make every major module **independently replaceable** |
| CG-4 | Preserve a clear mapping from Document `001` **Decision Lifecycle** to module participation |
| CG-5 | Support **shadow and dual-run** against Plugin Sales Agent without rewriting adapters first |
| CG-6 | Prefer **extend > replace** when absorbing production Sales Agent capabilities into modules |
| CG-7 | Ensure decisions remain **explainable** (reason codes, answer sources, confidence) |
| CG-8 | Fail **safely** toward clarification, draft, or human — never invented commerce facts |
| CG-9 | Separate **lifecycle coordination** from **action selection** (Decision Coordinator vs Decision Engine) |

---

## 5A. AI Core Principles

Document `001` defines platform principles. The following are **engineering guidance for every Wise Core module**. They do not replace Document `001`.

Wise Core is:

| Principle | Meaning |
|-----------|---------|
| **Stateless where possible** | Prefer request-scoped computation over hidden long-lived Core state |
| **State-aware where necessary** | Use Memory and Conversation views when Context First requires continuity |
| **Contract-driven** | Modules interact only through explicit contracts |
| **Replaceable** | Internals may change without breaking adapter-facing Decision contracts |
| **Observable** | Verdicts, reasons, sources, and confidence are capturable |
| **Deterministic when possible** | Same Context + Knowledge + Configuration → reasonably consistent Decision (model assist may be explicitly non-deterministic when flagged) |
| **Model-independent** | Policies and grounding must work when Model Gateway is unavailable |
| **Channel-independent** | No Meta/Widget/API transport semantics inside Core modules |

---

## 6. AI Core Responsibilities

Wise AI Core is responsible for:

| Responsibility | Notes |
|----------------|-------|
| Assembling and enforcing **Context First** decision inputs | Via Context Engine |
| Running the **Decision Lifecycle** to produce a **Decision** (recommendation) | **Decision Coordinator** owns lifecycle flow |
| Choosing Actions and confidence | **Decision Engine** owns action selection |
| Using merchant-approved **Knowledge** and short-horizon **Memory** | Never inventing Woo facts |
| Applying **Safety** and **Verification** inside the decision pipeline | Including pre- and post-model verification |
| Coordinating optional model assistance through a **Model Gateway** | No channel-specific prompts as Core law |
| Emitting structured recommendations for adapters | Action, reply candidates, confidence, handoff/clarify reasons |
| Supporting **Learning** activation policy for approved samples | Activation ≠ merchant approve UX |
| Exposing **Capability** and **Configuration** resolution for a store/channel | Platform Services — not reasoning engines |
| Participating in **Evaluation** hooks for shadow/regression | Per Document `001` Evaluation Gate |

---

## 7. AI Core Non-Responsibilities

Wise AI Core is **not** responsible for:

| Non-responsibility | Owner |
|--------------------|--------|
| Meta Graph, webhooks, Page tokens, send/typing/upload | Hub Channel Layer / adapters |
| Message/inbox persistence SoT | Plugin |
| Price, stock, variations, carts, order create/close | Plugin / WooCommerce |
| Payment, courier mutations, license issuance | Existing Hub/Plugin commerce & control planes |
| Merchant Semi inbox UX, Send & teach UI, knowledge editing UI | Plugin Experience Layer |
| Final auto-send authority without adapter/Plugin gates | Adapters + Plugin (Semi/Full, pause, already-answered) |
| Blog AI content pipelines; Order Intelligence fraud graph | Separate product planes unless ADR merges |

---

## 8. Internal Architecture

Wise AI Core sits entirely in the **Intelligence Layer**.

```text
                    Channel Adapter (outside Core)
                              │
                              ▼
                 ┌────────────────────────────┐
                 │     Wise AI Core           │
                 │                            │
                 │  Platform Services         │
                 │  Capability · Configuration│
                 │            │               │
                 │            ▼               │
                 │  Context · Conversation    │
                 │  Knowledge · Memory        │
                 │            │               │
                 │  Decision Coordinator     │
                 │            │               │
                 │      Decision Engine       │
                 │            │               │
                 │  Safety · Verification     │
                 │  Prompt · Model Gateway    │
                 │  Learning · Evaluation     │
                 │            │               │
                 │   Decision (recommendation)│
                 └────────────┬───────────────┘
                              │
            ─ ─ ─ ─ ─ ─ ─ ─ ─ ┼ ─ ─ ─ ─ ─ ─ ─ ─ ─
            EXECUTION BOUNDARY │
            ─ ─ ─ ─ ─ ─ ─ ─ ─ ┼ ─ ─ ─ ─ ─ ─ ─ ─ ─
                              ▼
              Adapter applies Respond rules
              Commerce validates facts / commits
              Operators may approve (Semi)
```

### Execution Boundary

**Everything above the Execution Boundary stays inside Wise.**

- Nothing below this line is owned by Wise Core.
- **Nothing inside Wise sends messages.**
- **Nothing inside Wise creates orders.**
- **Nothing inside Wise commits commerce actions.**

Wise emits a **Decision (recommendation)** only. Execution (send, draft persistence policy, order close, payments, courier) remains with adapters, Plugin, and commerce planes.

**Coordination rule:** The **Decision Coordinator** is the **only** module permitted to coordinate the Decision Lifecycle. Other modules must not coordinate each other.

---

## 9. Module Classification

Modules are not a flat list. Classification prevents treating configuration services as “AI engines.”

### 9.1 Platform Services

Not AI reasoning. They provide entitlement and settings resolution.

| Module | Role |
|--------|------|
| **Capability Registry** | Effective capability set |
| **Configuration Manager** | Effective settings for one decision |

### 9.2 AI Core Engines

Primary reasoning and continuity engines.

| Module | Role |
|--------|------|
| **Context Engine** | Bounded Context assembly (Context First) |
| **Conversation Engine** | Turn framing / continuity view |
| **Knowledge Engine** | Approved knowledge + packs retrieval |
| **Memory Engine** | Short-horizon continuity (MemoryView) |
| **Decision Coordinator** | Lifecycle ownership and module invocation |
| **Decision Engine** | Action selection, confidence, clarification choices |

### 9.3 AI Support Engines

Assist the decision pipeline; do not own lifecycle coordination.

| Module | Role |
|--------|------|
| **Safety Engine** | Safety verdicts / forced escalate-clarify |
| **Verification Engine** | Grounding checks (pre- and post-model) |
| **Prompt Engine** | Prompt assembly |
| **Model Gateway** | Model invocation |

### 9.4 AI Infrastructure Engines

Cross-cutting learning and assessment.

| Module | Role |
|--------|------|
| **Learning Engine** | Approved patterns / learnable markers |
| **Evaluation Engine** | Decision assessment / shadow hooks |

---

## 9A. Module Overview

| Module | Class | Primary role | Deep-dive doc (planned) |
|--------|-------|--------------|-------------------------|
| Capability Registry | Platform Service | Entitlements → capabilities | — |
| Configuration Manager | Platform Service | Settings snapshot → effective config | — |
| Context Engine | AI Core | Build Context | `003` |
| Conversation Engine | AI Core | Turn framing | `004` |
| Knowledge Engine | AI Core | Retrieval | `005` |
| Memory Engine | AI Core | MemoryView | `006` |
| Decision Coordinator | AI Core | Lifecycle coordination | `007` (Decision System) |
| Decision Engine | AI Core | Action / confidence / clarify | `007` (Decision System) |
| Safety Engine | AI Support | Safety verdict | `007` (Decision System) |
| Verification Engine | AI Support | Grounding verdict | `007` (Decision System) |
| Prompt Engine | AI Support | Prompt assembly | — |
| Model Gateway | AI Support | Model invocation | — |
| Learning Engine | AI Infrastructure | Approved patterns | `008` |
| Evaluation Engine | AI Infrastructure | Decision assessment | `010` |

---

## 9B. Internal State Ownership

Each module **owns** one primary artifact. Overlap is forbidden without ADR.

| Module | Owns |
|--------|------|
| **Context Engine** | Decision Context |
| **Conversation Engine** | Turn Framing |
| **Knowledge Engine** | Retrieval results (KnowledgeHits) |
| **Memory Engine** | MemoryView |
| **Decision Coordinator** | Lifecycle control / stage progression |
| **Decision Engine** | Recommendation (Action, confidence, clarify/handoff choices) |
| **Safety Engine** | Safety Verdict |
| **Verification Engine** | Grounding / Verification Verdict |
| **Prompt Engine** | Prompt Assembly |
| **Model Gateway** | Model Invocation results |
| **Learning Engine** | Approved Patterns (+ learnable markers) |
| **Evaluation Engine** | EvaluationResult |
| **Capability Registry** | CapabilitySet |
| **Configuration Manager** | EffectiveConfig |

---

## 10. Module Responsibilities

For each module: purpose, responsibilities, non-responsibilities, inputs, outputs, dependencies, extension points.

### 10.1 Capability Registry (Platform Service)

| | |
|--|--|
| **Purpose** | Resolve which Wise capabilities are available for a store, package, and channel |
| **Responsibilities** | Map entitlements and channel capability flags into an effective capability set |
| **Non-responsibilities** | Issuing licenses; storing Hub package catalog; UI for billing; reasoning |
| **Inputs** | Store identity, package/feature signals, channel capability flags |
| **Outputs** | CapabilitySet |
| **Dependencies** | Configuration Manager (read); entitlement snapshot at boundary |
| **Extension points** | New capability keys without changing Decision Engine |

### 10.2 Configuration Manager (Platform Service)

| | |
|--|--|
| **Purpose** | Provide effective merchant/AI settings for one decision |
| **Responsibilities** | Resolve mode, confidence floors, quiet hours, persona, catalog scope flags, pause signals |
| **Non-responsibilities** | Settings UI; writing WordPress options; reasoning |
| **Inputs** | Settings snapshot from adapter/Plugin boundary |
| **Outputs** | EffectiveConfig |
| **Dependencies** | None internal (leaf) |
| **Extension points** | New settings keys via contract versioning |

### 10.3 Context Engine

| | |
|--|--|
| **Purpose** | Enforce **Context First** — assemble the bounded Context for a decision |
| **Responsibilities** | Combine conversation window, configuration, capability set, memory snapshot, knowledge handles, commerce fact snapshot, channel context flags |
| **Non-responsibilities** | Fetching Meta history; querying Woo directly; owning fraud graphs |
| **Inputs** | Adapter-normalized request materials |
| **Outputs** | Context |
| **Dependencies** | Capability Registry, Configuration Manager; may use Conversation/Memory/Knowledge for assembly |
| **Extension points** | New context facets without breaking Decision contract |

### 10.4 Conversation Engine

| | |
|--|--|
| **Purpose** | Represent conversation continuity for Wise without owning inbox SoT |
| **Responsibilities** | Interpret conversation projection; frame the current turn |
| **Non-responsibilities** | Persisting messages as SoT; unread badges; Mid dedupe; Graph send |
| **Inputs** | Conversation projection / recent turns from Context |
| **Outputs** | ConversationView (Turn Framing) |
| **Dependencies** | Context Engine; Memory Engine (optional read) |
| **Extension points** | Channel-neutral stage models |

### 10.5 Knowledge Engine

| | |
|--|--|
| **Purpose** | Retrieve merchant-approved knowledge and platform packs |
| **Responsibilities** | Retrieval over approved knowledge; expose answer sources and gaps |
| **Non-responsibilities** | Merchant edit/approve UI; inventing prices; owning Woo catalog |
| **Inputs** | Context, query intent, product scope |
| **Outputs** | KnowledgeHits |
| **Dependencies** | Context; optional Model Gateway for embeddings assist only |
| **Extension points** | Replace retrieval backend; add pack sources |

### 10.6 Memory Engine

| | |
|--|--|
| **Purpose** | Short-horizon dialogue memory for Context First continuity |
| **Responsibilities** | Read/update memory facets for the session |
| **Non-responsibilities** | Order Intelligence graphs; cross-tenant PII training stores |
| **Inputs** | Context, prior memory snapshot, Decision outcomes |
| **Outputs** | MemoryView |
| **Dependencies** | Context; Conversation (optional) |
| **Extension points** | Replace memory store behind contract |

### 10.7 Decision Coordinator

| | |
|--|--|
| **Purpose** | Own the Decision Lifecycle and invoke modules in order |
| **Responsibilities** | Control flow across Understand → Clarify → Verify → Decide → Respond-prep → Learn markers; invoke Core/Support/Infrastructure modules; enforce Execution Boundary (no send/commit) |
| **Non-responsibilities** | Choosing the Action itself; scoring confidence; channel send; Woo commits |
| **Inputs** | Context, CapabilitySet, EffectiveConfig |
| **Outputs** | Final Decision envelope after pipeline completion |
| **Dependencies** | May invoke Decision Engine, Safety, Verification, Prompt, Model Gateway, Learning, Evaluation, Knowledge, Memory, Conversation |
| **Extension points** | New lifecycle stages only via ADR + Document `001` alignment |

**Hard rule:** Decision Coordinator is the **only** module permitted to coordinate the Decision Lifecycle.

### 10.8 Decision Engine

| | |
|--|--|
| **Purpose** | Choose what to recommend — not how the lifecycle is scheduled |
| **Responsibilities** | Select Action; score confidence; decide clarification vs proceed vs handoff candidates; produce recommendation content candidates |
| **Non-responsibilities** | Coordinating other modules; channel send; Woo commits; merchant UI |
| **Inputs** | Context, ConversationView, KnowledgeHits, MemoryView, Safety/Verification verdicts as supplied by Coordinator |
| **Outputs** | Recommendation core (Action, confidence, clarify/handoff intent, reply candidates) |
| **Dependencies** | Does **not** call Coordinator; called **by** Coordinator; may conceptually use Knowledge/Memory views passed in |
| **Extension points** | New Actions; scoring policies; clarification strategies |

### 10.9 Safety Engine

| | |
|--|--|
| **Purpose** | Prevent unsafe or out-of-policy recommendations inside the pipeline |
| **Responsibilities** | Injection/policy-scare/should-not-sell/handoff triggers |
| **Non-responsibilities** | Transport blocking; Meta moderation APIs; lifecycle coordination |
| **Inputs** | Context, candidate intents/replies |
| **Outputs** | SafetyVerdict |
| **Dependencies** | Context; Learning (approved handoff patterns, read-only) |
| **Extension points** | New safety rules without Decision Engine rewrite |

### 10.10 Verification Engine

| | |
|--|--|
| **Purpose** | Ground claims inside the decision pipeline (“Verify”), including **after** model assistance |
| **Responsibilities** | Check candidates against commerce fact snapshot + approved knowledge; reject invented discounts/forbidden claims; re-verify model-altered text |
| **Non-responsibilities** | Becoming price/stock authority; mutating Woo; lifecycle coordination |
| **Inputs** | Candidate reply, Context commerce snapshot, KnowledgeHits |
| **Outputs** | VerificationVerdict |
| **Dependencies** | Context, Knowledge |
| **Extension points** | Additional verifiers |

**Dual role:** Verification participates **before** optional model assistance (on grounded candidates) and **again after** Model Gateway if text changed.

### 10.11 Prompt Engine

| | |
|--|--|
| **Purpose** | Compose prompts for optional model assists |
| **Responsibilities** | Build understand/draft/tone prompts from Context, persona, facts, and approved learning |
| **Non-responsibilities** | Calling models; Blog AI pipelines; lifecycle coordination |
| **Inputs** | Context, KnowledgeHits, MemoryView, EffectiveConfig, LearningHints |
| **Outputs** | PromptPayload |
| **Dependencies** | Context, Knowledge, Memory, Configuration, Learning (read) |
| **Extension points** | Prompt templates/versioning |

### 10.12 Model Gateway

| | |
|--|--|
| **Purpose** | Single gateway for model assists |
| **Responsibilities** | Route model requests; return structured assists |
| **Non-responsibilities** | Owning merchant keys UI; bypassing Verification; channel sends; lifecycle coordination |
| **Inputs** | PromptPayload / embedding requests |
| **Outputs** | ModelAssist |
| **Dependencies** | Configuration, Capability |
| **Extension points** | Swap providers/models without changing Decision Engine |

### 10.13 Learning Engine

| | |
|--|--|
| **Purpose** | Apply approved learning; support Learn stage markers |
| **Responsibilities** | Select approved tone/handoff/moderation samples; emit learnable markers for Plugin approve workflows |
| **Non-responsibilities** | Merchant approve UI; auto-activating unapproved samples |
| **Inputs** | Decision outcomes, human feedback signals, approved sample sets |
| **Outputs** | LearningHints / approved patterns |
| **Dependencies** | Configuration, Capability |
| **Extension points** | New learning types without Messenger coupling |

### 10.14 Evaluation Engine

| | |
|--|--|
| **Purpose** | Support Evaluation Gate (Document `001` / `010`) |
| **Responsibilities** | Shadow compare, regression hooks, explainability capture |
| **Non-responsibilities** | Production send path; forcing lifecycle coordination |
| **Inputs** | Decision, Context, optional baseline Decision |
| **Outputs** | EvaluationResult |
| **Dependencies** | Decision outputs (read); Configuration |
| **Extension points** | New evaluation suites |

---

## 11. Module Communication Rules

1. **Modules communicate through contracts**, not by reading private state.
2. **No circular dependencies.**
3. **No Messenger / Meta knowledge** as hard-coded transport inside modules.
4. **No WooCommerce mutable logic** — only commerce fact snapshots at the Core boundary.
5. **No transport awareness.**
6. **Decision Coordinator is the only lifecycle coordinator.** Support engines must not coordinate Core engines.
7. **Decision Engine must not invoke the Coordinator.**
8. **Nothing inside Wise sends messages or commits commerce** (Execution Boundary).
9. **Explainability artifacts travel with the Decision.**

---

## 12. Module Dependency Rules

### Allowed dependency directions (conceptual)

```text
Platform Services:
  Configuration Manager (leaf)
  Capability Registry → Configuration Manager

AI Core:
  Context → Platform Services (+ Conversation/Memory/Knowledge for assembly)
  Conversation ↔ Memory
  Knowledge → Context (+ optional Model Gateway)
  Decision Coordinator → (invokes) Decision Engine, Safety, Verification,
                          Prompt, Model Gateway, Learning, Evaluation,
                          and reads Context/Conversation/Knowledge/Memory
  Decision Engine → (receives views/verdicts; does not coordinate)

AI Support:
  Safety → Context, Learning (read)
  Verification → Context, Knowledge
  Prompt → Context, Knowledge, Memory, Configuration, Learning (read)
  Model Gateway → Configuration, Capability

AI Infrastructure:
  Learning → Configuration, Capability
  Evaluation → Decision outputs, Configuration
```

**Rules of thumb:**

- Only **Decision Coordinator** may sequence the lifecycle.
- Peer Support engines do not call each other except via Coordinator-mediated flow.
- **Evaluation** observes; it does not change production Decision semantics unless shadow mode is configured.

---

## 13. Allowed Dependencies

| From | May depend on |
|------|----------------|
| Capability Registry | Configuration Manager |
| Configuration Manager | — (leaf) |
| Context Engine | Capability, Configuration, Conversation, Memory, Knowledge (assembly) |
| Conversation Engine | Context, Memory |
| Knowledge Engine | Context; Model Gateway (embeddings assist only) |
| Memory Engine | Context, Conversation |
| Decision Coordinator | Context, Conversation, Knowledge, Memory, Decision Engine, Safety, Verification, Prompt, Model Gateway, Learning, Evaluation, Capability, Configuration |
| Decision Engine | Passed-in Context/Conversation/Knowledge/Memory/Safety/Verification views (no Coordinator call) |
| Safety Engine | Context, Learning (read) |
| Verification Engine | Context, Knowledge |
| Prompt Engine | Context, Knowledge, Memory, Configuration, Learning (read) |
| Model Gateway | Configuration, Capability |
| Learning Engine | Configuration, Capability |
| Evaluation Engine | Decision outputs, Configuration |

**External boundary inputs allowed into Core (snapshots only):** entitlement signals, settings, conversation projection, approved knowledge projection, commerce fact snapshot, channel capability flags, optional baseline Decision for shadow.

---

## 14. Forbidden Dependencies

| Forbidden | Why |
|-----------|-----|
| Any Core module → Meta Graph / webhook / Page token APIs | AC-3 / INV-4 |
| Any Core module → WooCommerce as mutable authority | AC-2 / INV-2 / INV-6 |
| Any Core module → Plugin private Messenger helpers | AC-1 / AC-5 |
| Decision Engine → Decision Coordinator | Prevents reverse control |
| Safety/Verification/Prompt/Gateway → Decision Coordinator | Only Coordinator coordinates |
| Knowledge/Memory → Decision Coordinator (push-control) | Coordinator pulls |
| Model Gateway → Decision Engine / Coordinator | Gateway is leaf |
| Learning Engine → auto-activate unapproved samples | AC-4 |
| Evaluation Engine → force production sends | Fail-safe |
| Wise Core → Blog AI / Order Intelligence writes by default | Separate planes |
| Any module below Execution Boundary behaviors (send/order/commit) | Recommendation vs Execution |
| Circular module graphs | Communication Rules |

---

## 15. Extension Model

Wise extends by **composition**, not forks (Document `001` Evolution Principle).

| Extension type | How |
|----------------|-----|
| **New channel** | New adapter outside Core; same Decision contract |
| **New capability** | Capability Registry + Configuration + Decision policy |
| **New knowledge source** | Knowledge Engine backend behind contract |
| **New model provider** | Model Gateway replacement |
| **New safety rule** | Safety Engine extension |
| **New verifier** | Verification Engine extension |
| **New learning type** | Learning Engine + Plugin approve workflow |
| **New evaluation suite** | Evaluation Engine + Document `010` |
| **New Action** | Decision Engine extension (Coordinator unchanged unless lifecycle changes) |

**Forbidden extension pattern:** Channel-specific second brain, or Support engines coordinating the lifecycle.

---

## 16. Request Flow Through Wise

Architectural flow for one decision request. **Not** an implementation sequence.

This section is the **Core Pipeline** — the internal realization of Document `001`’s **Decision Lifecycle** (see §4A). Safety and Verification are **part of the decision pipeline**, not afterthoughts after a finished Decision.

```text
Adapter request (normalized)
        ↓
Capability Registry + Configuration Manager   ← Platform Services
        ↓
Context Engine                               ← Context First / Understand
        ↓
Conversation Engine + Memory Engine          ← Understand continuity
        ↓
Knowledge Engine                             ← Understand grounding inputs
        ↓
Decision Coordinator begins Core Pipeline
        ↓
Decision Engine (Understand / Clarify / Action candidates)
        ↓
Safety Check (Safety Engine)                 ← Verify (policy/safety)
        ↓
Verification (Verification Engine)           ← Verify (pre-model grounding)
        ↓
Prompt Engine (if assist needed)
        ↓
Model Gateway (optional)
        ↓
Verification (again if model output changed) ← Verify (post-model)
        ↓
Decision Engine finalizes recommendation     ← Decide
        ↓
Learning Engine (read samples / mark learnable) ← Learn markers
        ↓
Evaluation Engine (optional shadow/regression)
        ↓
Decision (recommendation)
        ↓
═══════════ EXECUTION BOUNDARY ═══════════
        ↓
Respond outside Core: adapter/Plugin gates → channel delivery
Commerce validates facts / commits when required
Operators may approve (Semi)
Learn activation remains approval-gated
```

**Notes:**

- Document `001` lifecycle stages and this pipeline describe **different levels** — conceptual vs realization (§4A).
- Verification has an explicit **dual role**: before optional model assistance and after model-altered text.
- Quiet hours, package gates, and pause may short-circuit at the boundary.
- Order-close and irreversible commerce remain outside Core.
- History sync / webhook paths must not invoke full Core synchronously (Document `001` AC-8).

---

## 17. Internal Contracts

Contracts are conceptual boundaries. This document does **not** define schemas.

| Contract | Producer | Consumer | Payload (conceptual) |
|----------|----------|----------|----------------------|
| **CapabilitySet** | Capability Registry | Context, Coordinator | Allowed capabilities |
| **EffectiveConfig** | Configuration Manager | Most modules | Mode, floors, persona, flags |
| **Context** | Context Engine | Lifecycle modules | Bounded decision inputs |
| **ConversationView** | Conversation Engine | Decision Engine, Memory | Turn Framing |
| **KnowledgeHits** | Knowledge Engine | Decision, Verification, Prompt | Grounded sources / gaps |
| **MemoryView** | Memory Engine | Context, Decision, Prompt | Retained continuity |
| **SafetyVerdict** | Safety Engine | Coordinator / Decision Engine | Allow / escalate / clarify |
| **VerificationVerdict** | Verification Engine | Coordinator / Decision Engine | Pass / fail / sanitize |
| **PromptPayload** | Prompt Engine | Model Gateway | Prompt Assembly |
| **ModelAssist** | Model Gateway | Coordinator / Decision / Knowledge | Model Invocation result |
| **Decision** | Decision Engine (+ Coordinator envelope) | Adapter, Evaluation | Recommendation |
| **LearningHints** | Learning Engine | Prompt, Safety | Approved Patterns |
| **EvaluationResult** | Evaluation Engine | Ops/shadow pipelines | Quality assessment |

Versioning of contracts prefers additive Minor evolution; breaking contract changes require ADR + Document `001` Major when foundation terms change.

---

## 18. Replaceable Components

Every major module must be replaceable behind its contract without rewriting adapters.

**Highest-priority replaceables:**

| Component | Why |
|-----------|-----|
| **Model Gateway** | Providers, keys, and models will change |
| **Knowledge Engine** | Retrieval tech will evolve |
| **Prompt Engine** | Prompt strategy evolves quickly |
| **Memory Engine** | Storage/strategy may change |
| **Safety Engine** | Rulesets evolve |
| **Verification Engine** | Grounding checks expand |
| **Decision Engine** | Action/confidence strategies evolve without changing Coordinator |
| **Evaluation Engine** | Suites grow with Document `010` |

**Replacement rule:** External Decision contract, Execution Boundary, and Document `001` invariants remain stable.

---

## 19. Failure Philosophy

Aligned with Document `001` fail-safe posture.

When uncertain or unsafe, Wise should prefer:

1. **Clarification**
2. **Draft**
3. **Human**

Wise must **never**:

- Invent commerce facts
- Bypass commerce authority
- Bypass operator approval for knowledge/learning activation
- Depend on Meta transport to “complete” a Decision
- Fail open into Full auto-send when Safety or Verification fails
- Send messages or create orders from inside Core

If Model Gateway fails, Coordinator/Decision Engine fall back to non-model grounded paths or handoff — not fabricated content.

---

## 20. Scalability Philosophy

- Core work is **request/decision scoped**, not webhook-scoped.
- Modules support growth without Meta coupling.
- Expensive assists stay behind Model Gateway budgets conceptually.
- Shadow/evaluation traffic is isolatable from production recommendation paths.
- Per-store configuration and knowledge scale by **projection snapshots**.

---

## 21. Observability Philosophy

- Every Decision should expose **Action**, **confidence**, **reasons**, and **answer sources** where applicable.
- Safety and Verification verdicts (including post-model re-checks) are capturable.
- Determinism target: same Context + Knowledge + Configuration → reasonably consistent Decision when model assist is off or pinned.
- Evaluation Engine consumes these artifacts; private LLM logs must not be the only debug path.

---

## 22. Validation Notes

Validated against Document `001` (Approved v0.4.0), Hub Backend, and WordPress Plugin discovery. Approved in v0.3.0 with stable module map; no production redesign.

### Conflict V-002-1 — Core does not exist in production runtime

| | |
|--|--|
| **Conflict** | Hub-hosted Wise modules are Target Architecture; production AI runs in Plugin Sales Agent. |
| **Risk** | Treating Document `002` as shipped. |
| **Suggested resolution** | Map Plugin manager ≈ Coordinator + Decision Engine in Document `011`; default path unchanged until flagged migration. |

### Conflict V-002-2 — Model Gateway vs existing OpenAI clients

| | |
|--|--|
| **Conflict** | Plugin Sales Agent and Hub Blog AI already call OpenAI. |
| **Risk** | Accidental merge without ADR. |
| **Suggested resolution** | Model Gateway is Wise-scoped; sharing with Blog AI requires ADR (Document `001` OQ-4). |

### Conflict V-002-3 — Knowledge/Learning SoT vs engines

| | |
|--|--|
| **Conflict** | Authoring/approve UX is on Plugin. |
| **Risk** | Hub UIs bypassing approval (AC-4). |
| **Suggested resolution** | Engines consume approved projections only. |

### Conflict V-002-4 — Recommendation vs manager-only send

| | |
|--|--|
| **Conflict** | Production auto-send is manager-gated on Plugin. |
| **Risk** | Send inside Coordinator/Gateway. |
| **Suggested resolution** | Execution Boundary: Core never sends or commits commerce. |

### Conflict V-002-5 — Module map vs Documents `003`–`010`

| | |
|--|--|
| **Conflict** | Planned docs vs richer module map (Coordinator, Safety, Verification, Platform Services). |
| **Risk** | Duplicate ownership. |
| **Suggested resolution** | Document `002` owns the module map and classification; `007` (**Decision System**) deepens Coordinator + Decision Engine + Safety + Verification. |

### Conflict V-002-6 — Coordinator vs production “manager”

| | |
|--|--|
| **Conflict** | Production uses a single manager concept that both coordinates the pipeline and decides/sends. |
| **Risk** | Forcing a big-bang code split before migration readiness. |
| **Suggested resolution** | Treat Coordinator/Decision Engine as **architectural roles**. During migration, one runtime component may temporarily fulfill both roles if contracts and Execution Boundary hold — then split when safe (`011`). |

No production paths were redesigned to resolve these conflicts.

---

## 23. Related Documents

| Document | Relationship |
|----------|----------------|
| [`001-wise-ai-platform-overview.md`](001-wise-ai-platform-overview.md) | Foundation — inherit and conform |
| [`003-context-engine.md`](003-context-engine.md) | Deepens Context Engine (**Approved**) |
| [`004-conversation-engine.md`](004-conversation-engine.md) | Deepens Conversation Engine (**Approved**) |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | Deepens Knowledge Engine (Draft) |
| [`006-memory-engine.md`](006-memory-engine.md) | Deepens Memory Engine (**Approved**) |
| [`007-decision-system.md`](007-decision-system.md) | Deepens **Decision System**: Decision Coordinator, Decision Engine, Safety, Verification (Draft) |
| [`008-learning-engine.md`](008-learning-engine.md) | Deepens Learning Engine (Draft) |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | Outside Core — adapters invoke Decision contract (Draft) |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | Deepens Evaluation Engine / Evaluation Gate (Draft) |
| [`011-migration-strategy.md`](011-migration-strategy.md) | Maps Plugin Sales Agent → Wise modules; shadow/cutover |
| `docs/adr/*` | Formal amendments |
| `docs/requirements/*` | Behavior and acceptance criteria |

**Do not draft next:** Prompt Architecture, LLM Architecture, RAG Architecture, Embeddings Architecture, or any new numbered architecture document after `011`. Architecture set `001`–`011` is **Frozen** (RA 1.0.0). Next work: `docs/implementation/` — runtime, contracts, state-machines, sequences, integrations, reference.

---

## 24. Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft: module map, dependency/communication rules, request flow, replaceability, philosophies; Validation Notes |
| 0.2.0 | 2026-07-30 | Documentation Lead | AI Core Principles; Decision Coordinator vs Decision Engine; Module Classification (Platform Services / Core / Support / Infrastructure); Execution Boundary; Internal State Ownership; dual Verification in pipeline; strengthened coordination rule; Validation Note V-002-6 |
| 0.3.0 | 2026-07-30 | Documentation Lead | Rename Orchestrator → **Decision Coordinator**; §4A Lifecycle vs Core Pipeline; reorder plan (`003` Context, `004` Conversation, `007` Decision System); Core authority / stable module map; **Status → Approved** |
| 0.3.1 | 2026-07-30 | Documentation Lead | Related docs: link Document `006` Memory Engine Draft |
| 0.3.2 | 2026-07-30 | Documentation Lead | Ownership artifact = **MemoryView** (drop Session Memory dual name); Document `006` Approved |
| 0.3.3 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.3.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `008` Learning Engine Draft |
| 0.3.5 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.3.6 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.3.7 | 2026-07-30 | Documentation Lead | Standardize published artifact name **EvaluationResult** (replace EvaluationRecord); ownership table aligned with Documents `003`–`010` |
| 0.3.8 | 2026-07-30 | Documentation Lead | Record Architecture Freeze (v1.0 Reference Architecture `001`–`010`); next deliverable = `011` Migration |
| 0.3.9 | 2026-07-30 | Documentation Lead | Related docs: link Document `011` Migration Strategy Draft |
| 0.4.0 | 2026-07-30 | Documentation Lead | Wise AI Platform Reference Architecture **1.0.0** — Documents `001`–`011` Approved · Frozen; engineering shifts to `docs/implementation/` |
