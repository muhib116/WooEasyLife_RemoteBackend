# Wise AI Platform Overview

| Field | Value |
|-------|--------|
| **Title** | Wise AI Platform Overview |
| **Document ID** | `001` |
| **Version** | `0.5.0` |
| **Status** | Approved |
| **Last Updated** | 2026-07-30 |
| **Authors** | Chief AI Architect (ChatGPT) · Documentation Lead (Cursor) |
| **Approver** | Product Owner |
| **Role** | Foundation document — subsequent architecture docs must reference this document rather than redefine principles, constraints, or terminology |

---

## Document Information

This document is the **foundation specification** for the **Wise AI Platform** architecture set. It defines vision, Architecture North Star, design philosophy, principles, **constraints**, **invariants**, quality attributes, capability boundaries, Decision Lifecycle, platform layers, and high-level components.

Detailed engine designs appear in subsequent numbered documents (`002` onward). Later documents must **reference this document** for principles, constraints, terminology, and quality attributes — they must not redefine them. They must satisfy the constraints, invariants, and quality attributes defined here.

**Change control:** Do not modify this document casually. Prefer additive **Minor** updates. Breaking changes require a **Major** version bump and an ADR.

**Architecture freeze:** Wise AI Platform **Reference Architecture 1.0.0** — Documents `001`–`011` are **Approved** and **Frozen**. No new architectural concepts, ownership changes, boundary changes, or published artifacts without an ADR and governance approval. No new architecture documents after `011`. Engineering continues under `docs/implementation/` and must answer: *How do we implement the frozen architecture without violating its contracts?*

**Canonical host:** Hub Backend (`WooEasyLife_RemoteBackend` / WPSaleHub)  
**Commerce source of truth:** WordPress plugin `woo-easy-life` + WooCommerce  
**Compatibility stance:** Extend production behavior; do not replace live merchant paths without an approved migration

---

## Architecture Decision Hierarchy

The repository follows a clear governance model. Higher layers win conflicts.

```text
Document 001 (Foundation)
        ↓
Architecture Decision Records (ADRs)
        ↓
Architecture Documents (002–011 …)
        ↓
Requirements
        ↓
Implementation
        ↓
Tests
```

| Layer | Authority |
|-------|-----------|
| **Document 001** | Highest architectural authority for Wise. North Star, constraints, invariants, terminology, Decision Lifecycle, and quality attributes live here. |
| **ADRs** | Amend or clarify Document `001` (and related architecture) when a deliberate decision is required. An Accepted ADR may justify a Major bump to `001`. |
| **Architecture documents (`002+`)** | Must conform to Document `001` and to Accepted ADRs. They deepen engines and adapters; they must not redefine foundation law. |
| **Requirements** | Define expected merchant/system behavior and acceptance criteria under the approved architecture. |
| **Implementation** | Code implements the approved architecture — it does not invent architecture. |
| **Tests** | Verify the implementation against requirements and evaluation gates (see Document `010`). |

**Conflict rule:** If code, a proposal, or a lower document disagrees with Document `001`, Document `001` (plus any Accepted ADR that formally amends it) prevails until Product Owner and architecture review change it.

---

## Purpose

Define a shared, channel-agnostic understanding of Wise so that:

- Product, architecture, and engineering share one vocabulary
- New channels attach as clients of Wise, not forks of Messenger AI
- Production merchants keep working Messenger, Comments, license, and commerce behavior during and after platform evolution
- Future modules fit into defined layers without rewriting the platform

---

## Scope

**In scope for this document:**

- Explicit **Current State** vs **Target Architecture**
- Architecture Decision Hierarchy (governance)
- Platform vision, Architecture North Star, mission, and problem statement
- Design philosophy, design goals, principles, evolution principle, and Context First
- Architectural constraints, invariants, and quality attributes
- AI capability boundaries and Decision Lifecycle
- Platform scope and explicit out-of-scope boundaries
- Supported channels and supported inputs & modalities (target platform view)
- Platform layers and high-level component overview
- Core terminology and architecture document versioning policy
- Guiding architectural rules
- Long-term roadmap, evaluation gate, and success metrics
- Validation Notes for production gaps
- Glossary and links to related docs

**Out of scope for this document:**

- Engine internals (see `002`–`010`)
- Migration step detail (see `011`)
- APIs, database schemas, class diagrams, or implementation code

---

## Current State (2026)

Validated against Hub Backend and WordPress plugin architecture discovery. This is **what exists in production today** — not the target design.

WooEasyLife already runs for many merchants.

| Plane | Role today |
|-------|------------|
| **Hub Backend** | License/control plane; Meta Page tokens & Graph/webhook edge; courier hub; fraud/Order Intelligence; Blog AI (content); anonymized Messenger intent packs; empty **Wise** scaffold namespaces (no runtime AI Core) |
| **WordPress plugin** | Commerce SoT; local Messenger/Comments inbox; Sales Agent decision pipeline (rules + KB + optional merchant OpenAI); learning approve UX; Semi/Full gates |
| **Messenger / Comments** | Hub-transported Meta channels; plugin-orchestrated automation — **not** a central AI platform |
| **Conversational AI locus** | Plugin-local (Messenger Sales Agent baseline) |
| **Widget / WhatsApp / Voice channel / public Wise API** | Not Wise clients in production |

Production constraints that must not be casually broken: license/domain binding, Meta HMAC inbound, thin webhook → job enqueue, Semi/Full send gates, Woo price/stock grounding, merchant knowledge/learning approval.

---

## Target Architecture

This is **what we are designing** — the destination platform. Migration must extend Current State without default replacement (see Evolution Principle and Document `011`).

| Plane | Target role |
|-------|-------------|
| **Hub Backend** | Remains control plane; **hosts Wise** as the channel-agnostic Intelligence Layer |
| **Wise AI Core** | Single reasoning/recommendation layer: Context First, Decision Lifecycle, shared policies/knowledge contracts |
| **Channel adapters** | Messenger, Comments, Widget, WhatsApp, Instagram family, Voice, API, and future channels — clients of Wise; adapters own transport |
| **WordPress plugin** | Remains Commerce SoT and operator Experience (inbox, Semi approve, knowledge/learning authoring) |
| **WooCommerce** | Remains authority for catalog, price, stock, and orders |
| **Non-Wise AI planes** | Blog AI and Order Intelligence stay separate unless an ADR merges them |

**North Star reminder:** Every conversational capability should eventually rely on the same Wise reasoning, policies, knowledge, and decision contracts — while respecting commerce ownership and backward compatibility.

```text
Experience → Channel → Intelligence (Wise) → Knowledge → Commerce → Infrastructure
```

---

## Vision

A single **Wise AI Platform** on the Hub Backend that powers every WooEasyLife conversational and assistive AI surface with shared intelligence, safety, and learning — while each store’s WooCommerce data and human inbox remain authoritative where they already are.

---

## Architecture North Star

**Wise should become the single intelligence layer for WooEasyLife.**

Every conversational capability, regardless of channel, should eventually rely on the same reasoning, policies, knowledge, and decision contracts — while respecting commerce ownership and backward compatibility.

---

## Mission

- Centralize channel-agnostic AI decisioning in Wise
- Preserve WooCommerce as the source of truth for price, stock, catalog facts, and order creation
- Preserve Hub as the control plane (license, packages, Meta edge, entitlements)
- Preserve Plugin as the commerce and operator surface for merchants
- Extend to new channels without cloning the Sales Agent into each one

---

## Problem Statement

1. **AI logic is Messenger-coupled.** Sales Agent orchestration lives in plugin Messenger helpers; Comments reuse only a thin subset. Widget, WhatsApp, Voice, and public API cannot safely share one brain today.
2. **Two OpenAI worlds.** Blog AI runs on Hub; Sales Agent / order extract / courier AI run on the plugin with merchant keys. There is no shared LLM gateway or prompt registry.
3. **Hub Wise is empty.** Scaffold directories exist but provide no runtime; intent packs are the main Hub-side AI-adjacent asset for Messenger.
4. **Production risk is high.** Thousands of merchants depend on license binding, Meta HMAC forwards, job queues, Semi/Full gates, and Woo grounding. A rewrite would break trust and revenue paths.
5. **Growth requires adapters, not forks.** Each new channel must plug into Wise via a channel adapter framework, not a new private agent copy.

---

## Design Goals

| ID | Goal |
|----|------|
| DG-1 | **Zero breaking changes** for existing Messenger, Comments, license, and commerce paths unless a migrated opt-in is approved |
| DG-2 | **Channel-agnostic AI Core** (Wise) hosted on Hub |
| DG-3 | **Messenger as adapter** — transport + inbox client, not the platform brain |
| DG-4 | **WooCommerce remains commerce SoT** — Wise never becomes price/stock/order authority |
| DG-5 | **Hub remains control plane** — auth, packages, Meta tokens, domain binding |
| DG-6 | **Preserve proven business rules** — Semi/Full, confidence, readiness unlock, human handoff, grounded verification, quiet hours, pause-on-human |
| DG-7 | **Backward-compatible migration** — shadow/dual-run before cutover |
| DG-8 | **Documentation-first change** — approved docs and ADRs before implementation |

---

## Design Philosophy

The platform is designed around separation of responsibilities.

**Adapters connect channels.**  
**Wise understands.**  
**Commerce validates.**  
**Operators approve.**  
**Channels deliver.**

---

## Design Principles

1. **Extend, do not replace** live production behavior by default.
2. **Adapters at the edge; intelligence at the center.**
3. **Facts from Woo; recommendations from Wise; delivery via channel adapters.**
4. **Human-in-the-loop is a first-class mode** (Semi), not an afterthought.
5. **No LLM or Graph send on Meta webhook request paths** — async jobs / deferred processing only.
6. **Merchant-approved knowledge and learning** before use in live replies.
7. **PII minimization** for cross-tenant assets (e.g. anonymized intent packs).
8. **Package entitlement** continues to gate AI surfaces via Hub features.
9. **Fail safe** — prefer handoff / draft over incorrect auto-send.
10. **Separate product planes** — conversational Wise ≠ Blog AI ≠ Order Intelligence fraud graph unless an ADR explicitly merges them.

Principles guide judgment. **Architectural Constraints** and **Architecture Invariants** below are mandatory and stronger than principles.

---

## Evolution Principle

Wise should evolve by **adding capabilities**, not by replacing existing stable ones.

Whenever possible:

- **extend > replace**
- **compose > rewrite**
- **adapt > fork**

This is the migration mindset for a multi-year platform with live merchants.

---

## Context First

Wise should never reason from the latest message alone.

Every decision should consider available **conversation context**, **merchant settings**, **commerce facts**, **customer/thread history**, and **channel context** before producing a recommendation.

Context First is a defining principle of Wise and applies to every engine and channel adapter.

---

## Architectural Constraints

The following constraints apply to **every** Wise component and every future engine document (`002`–`011`).

| ID | Constraint |
|----|------------|
| AC-1 | **Wise must remain channel-agnostic.** No Messenger-, Meta-, or Widget-specific business logic may become a hard dependency inside Wise Core. |
| AC-2 | **Wise must never own commerce facts.** Price, stock, variations, carts, and order creation remain Plugin / WooCommerce authority. |
| AC-3 | **Wise must not depend directly on Meta APIs.** Graph, webhooks, Page tokens, and send transport belong to Hub channel edge / adapters. |
| AC-4 | **Wise must not bypass plugin approval workflows.** Knowledge publish, learning activation, Semi human review, and merchant unlock gates remain enforceable. |
| AC-5 | **Every Wise capability must be accessible through a stable contract.** Channels and engines consume contracts, not private Messenger helpers. |
| AC-6 | **Every Wise component must support future replacement without breaking external contracts.** Internal engines may evolve; adapter-facing and commerce-facing contracts remain stable unless a Major version / ADR says otherwise. |
| AC-7 | **Wise must not execute irreversible commerce side effects alone.** Order create, payment, and courier mutations require Plugin / commerce authority paths. |
| AC-8 | **Wise must not run on Meta webhook request latency budgets.** Decisioning is async relative to inbound webhook acknowledgment. |
| AC-9 | **Cross-tenant assets must remain PII-minimized** (e.g. anonymized intent packs). Raw merchant chat must not become default Hub training data without Product Owner approval. |
| AC-10 | **Backward compatibility is mandatory** for default merchant paths unless an explicit, approved migration opt-in is in force. |

---

## Quality Attributes

Later architecture documents and implementations must satisfy these attributes. They are platform requirements, not slogans.

| Attribute | Meaning for Wise |
|-----------|------------------|
| **Reliability** | Prefer handoff/draft over wrong auto-send; preserve retry/job semantics merchants already depend on |
| **Extensibility** | New channels attach via adapters without forking the decision brain |
| **Scalability** | Decision load can grow across stores/channels without Meta webhook coupling |
| **Explainability** | Decisions expose reason codes (intent, confidence, handoff, answer source) suitable for merchant inbox and audit turns |
| **Observability** | Turns, failures, and shadow comparisons are inspectable without reading private LLM logs as the only signal |
| **Backward Compatibility** | Existing Semi/Full, license, Meta forward, and Woo grounding behavior remain the default until flagged cutover |
| **Security** | Tokens stay on Hub; HMAC inbound remains authoritative; no Page token leakage into Plugin/Vue; package gates enforced |
| **Performance** | Webhook path stays thin; Wise work is bounded by job/time budgets compatible with merchant hosts and Hub queues |
| **Testability** | Contracts and Decision Lifecycle stages can be validated without Meta live traffic |
| **Safety** | Injection, policy-scare, grounding verification, and human pause rules remain enforceable across channels |
| **Determinism** | Given the same Context, Knowledge, and Configuration, Wise should produce explainable and reasonably consistent Decisions — required for debugging, shadow comparison, and regression evaluation |

---

## AI Capability Boundaries

### Wise is responsible for

| Capability | Meaning |
|------------|---------|
| **Understanding** | Interpret customer input (text and normalized multimodal signals) into intents, slots, and dialogue needs |
| **Reasoning** | Apply policies, funnel stage, knowledge, and confidence to choose a next step |
| **Clarification** | Ask for missing product, quantity, lead fields, or variation when facts are incomplete |
| **Recommendation** | Suggest replies, cards, holds, or escalations (including Semi drafts) |
| **Decision support** | Produce a structured decision (action, reply candidates, confidence, handoff) for adapters to apply |

### Wise is NOT responsible for

| Non-responsibility | Authority remains with |
|--------------------|------------------------|
| **Pricing authority** | Plugin / WooCommerce |
| **Stock authority** | Plugin / WooCommerce |
| **Order authority** | Plugin / WooCommerce (incl. order-close modes) |
| **Payment authority** | Plugin / Hub billing planes as already designed |
| **Courier authority** | Plugin / Hub courier modules |
| **Meta transport authority** | Hub Messenger/Comments edge |
| **Merchant approval authority** | Plugin operator UX (knowledge, learning, Semi send) |
| **License / package entitlement authority** | Hub control plane |

These boundaries prevent scope creep: Wise **thinks and recommends**; commerce and channel edges **commit**.

---

## Decision Lifecycle

Wise follows one **Decision Lifecycle** across channels. Engines may specialize stages; they must not invent a conflicting life cycle.

```text
Understand
    ↓
Clarify
    ↓
Verify
    ↓
Decide
    ↓
Respond
    ↓
Learn
```

| Stage | Intent |
|-------|--------|
| **Understand** | Normalize input; detect language/intent; load conversation context and memory (Context First) |
| **Clarify** | Resolve missing product, quantity, lead, or variation before asserting facts |
| **Verify** | Ground claims against Woo facts + merchant-approved knowledge; run safety checks |
| **Decide** | Choose action (suggest, ask, preview, hold, escalate, noop) with confidence — emit a Decision (recommendation) |
| **Respond** | Emit draft or send recommendation; adapter applies Semi/Full, pause, and transport rules; Plugin/commerce may still reject |
| **Learn** | Capture teachable signals; activate only after merchant approval workflows |

This lifecycle is the request path for every Wise decision. Broader engineering values live under Design Philosophy, Design Principles, and Evolution Principle.

It aligns with the production Sales Agent baseline (rules + KB first, optional LLM assist, grounded verification, human handoff) without requiring Messenger-specific design inside Wise.

---

## Platform Scope

Wise AI Platform (target) includes:

- Channel-agnostic conversation decisioning
- Context assembly for decisions (bounded history, settings, facts snapshots)
- Knowledge retrieval over merchant-approved product/store knowledge
- Memory for short-horizon dialogue state (and clear boundaries vs Hub Order Intelligence)
- Decision policies (confidence, handoff, Semi vs Full send recommendations)
- Learning activation of merchant-approved samples
- Channel adapter contracts for Messenger, Comments, Widget, API, and future channels
- Evaluation of reply quality and safety over time
- Migration strategy from plugin-local Sales Agent to Hub-hosted Wise

---

## Out of Scope

| Item | Reason |
|------|--------|
| Replacing WooCommerce order/catalog storage | Commerce SoT stays on Plugin |
| Storing Meta Page long-lived tokens in the Plugin | Tokens stay on Hub |
| Making Blog AI the Messenger runtime | Separate product plane unless ADR says otherwise |
| Merging Order Intelligence fraud graph into chat memory by default | Different trust and PII model |
| Claiming Meta AI Bot as a shipped product | Not an approved feature claim |
| Multi-Page Messenger inbox redesign | Existing product constraint; not part of Wise overview |
| Implementation APIs, DB schemas, Laravel/WordPress code | Later docs / implementation tasks only |
| Redesigning courier webhook or license middleware | Unrelated control-plane hot paths |

---

## Supported Channels

**Target platform clients of Wise** (not all are live AI Core clients today):

| Channel | Current production posture | Target role |
|---------|----------------------------|-------------|
| **Facebook Messenger** | Live inbox + Sales Agent on Plugin; Hub Graph/webhook | Channel adapter → Wise |
| **Instagram Messaging** | Partially via Messenger path (`channel=instagram`) | Same adapter family as Messenger |
| **Facebook/Instagram Comments** | Live sibling module; lighter automation | Channel adapter → Wise (no full sales funnel unless product expands scope) |
| **Website Chat Widget** | Not a Wise client today | Future channel adapter |
| **WhatsApp** | Not a Wise conversational channel today | Future channel adapter |
| **Voice** | Whisper used inside Messenger media enrichment; not a standalone voice channel | Future input channel / modality |
| **Third-party / Public AI API** | Not a Wise decision API today | Future Hub edge → Wise |
| **Future channels** | — | Adapter framework only |

---

## Supported Inputs & Modalities

Wise consumes **normalized inputs** supplied by channel adapters. Not every row is a classical “modality”; all are valid decision inputs over time.

| Input / modality | Notes |
|------------------|--------|
| **Text** | Primary decision input |
| **Voice / audio notes** | Transcription assist exists on Plugin Messenger path; adapter normalizes to text before Wise |
| **Image** | Product match / order extract patterns exist on Plugin; Wise may absorb assist later via normalized signals |
| **Video** | Transport/inbox concern today; not a Wise training corpus by default |
| **Documents / attachments** | Adapter concern; Wise may receive extracted text only when product approves |
| **Structured events** | e.g. product-select payloads, quick replies, postbacks — normalized by adapters |
| **OCR / extracted fields** | Future normalized input from images/documents |
| **Campaign / attribution context** | Optional adapter-supplied context; never invents commerce facts |
| **API payloads** | Third-party API channel supplies structured requests to the same Wise contracts |

Delivery constructs (Messenger cards, typing indicators, reactions) remain **adapter concerns**, not Wise Core responsibilities.

---

## Platform Layers

Every future Wise module must map to exactly one primary layer (and may call downward through stable contracts).

```text
Experience Layer
        ↓
Channel Layer
        ↓
Intelligence Layer   ← Wise AI Core (channel-agnostic)
        ↓
Knowledge Layer
        ↓
Commerce Layer
        ↓
Infrastructure Layer
```

| Layer | Responsibility | Typical owners |
|-------|----------------|----------------|
| **Experience Layer** | Merchant inbox UI, future widget UI, operator Semi review, settings screens | Plugin (primary); future Hub/admin surfaces only where already appropriate |
| **Channel Layer** | Normalize inbound/outbound; Meta Graph/webhook; transport retries; channel capability flags | Hub edge + Plugin inbound/send adapters |
| **Intelligence Layer** | Understand → Clarify → Verify → Decide → Respond recommendations; confidence; handoff | **Wise** (Hub host, target) |
| **Knowledge Layer** | Approved product/store knowledge, packs, embeddings/RAG retrieval contracts | Plugin authoring + Wise retrieval (target); Hub anonymized packs |
| **Commerce Layer** | Catalog, price, stock, order create/close, payments, courier | Plugin + WooCommerce (+ existing Hub courier/billing planes) |
| **Infrastructure Layer** | Queues, jobs, cache, license middleware, observability, hosting | Hub + Plugin runtime infrastructure |

**Rule:** Intelligence must not absorb Channel or Commerce ownership. Experience must not embed a private second brain that bypasses Wise contracts once cutover is approved.

---

## High-Level Component Overview

```text
                    ┌─────────────────────────────────────┐
                    │     Hub Backend (Control Plane)     │
                    │  License · Packages · Meta tokens   │
                    │  Webhooks · Intent packs · Wise host│
                    │                                     │
                    │         ┌─────────────────┐         │
                    │         │   Wise AI Core  │         │
                    │         │  (Intelligence) │         │
                    │         └────────▲────────┘         │
                    └──────────────────┼──────────────────┘
                                       │ decisions
         ┌──────────────┬──────────────┼──────────────┬──────────────┐
         ▼              ▼              ▼              ▼              ▼
    Messenger      Comments       Widget         API          Future…
    adapter        adapter        adapter        adapter      adapters
         │              │              │              │
         └──────────────┴──────────────┼──────────────┘
                                       ▼
                         WordPress Plugin + WooCommerce
                         (commerce SoT · inbox · approve UX)
```

| Component | Responsibility |
|-----------|----------------|
| **Hub Control Plane** | Auth, domain/license binding, packages, Meta OAuth/tokens, webhook verify/forward, entitlements |
| **Wise AI Core** | Channel-agnostic thinking: context, knowledge use, memory, decision, learning activation, evaluation hooks |
| **Channel Adapters** | Normalize inbound events; invoke Wise; apply send/draft/handoff on the correct transport |
| **Plugin + WooCommerce** | Catalog/price/stock/orders; local conversation projections; human Semi review; knowledge/learning authoring |
| **Non-Wise AI planes** | Blog AI, Order Intelligence, courier note AI — remain separate unless ADR merges |

Detailed engine breakdown is deferred to `002-wise-ai-core-architecture.md` and following documents.

---

## Responsibilities

This overview document is responsible for:

- Platform-level vocabulary, layers, constraints, and quality attributes
- AI capability boundaries and decision philosophy
- Alignment with production compatibility rules
- Pointing readers to deeper architecture docs when available

---

## Non-Responsibilities

This overview document is **not** responsible for:

- Specifying engine algorithms or prompts
- Defining REST routes, jobs, or tables
- Changing live Sales Agent behavior
- Approving merchant-facing feature marketing claims

---

## Ownership Summary (Approved Direction)

| Concern | Primary owner |
|---------|----------------|
| WooCommerce facts | Plugin |
| Message/inbox SoT (Messenger/Comments) | Plugin |
| Meta tokens & Graph edge | Hub |
| Channel-agnostic decisioning | Future Wise (Hub) |
| Merchant knowledge authoring & learning approve | Plugin |
| Anonymized intent / AI packs | Hub → Wise |
| Human Semi review UX | Plugin |

---

## Guiding Architectural Rules

1. **Backward compatibility is mandatory.**  
2. **AI Platform extends the system; it does not replace it by default.**  
3. **Plugin remains Commerce Source of Truth.**  
4. **Hub remains Control Plane and Wise host.**  
5. **Messenger, Comments, Widget, WhatsApp, Instagram, Voice, API, and future channels are clients of Wise.**  
6. **Messenger must not be treated as the AI Core.**  
7. **Existing business rules are preserved whenever possible** (Semi/Full, confidence floors, readiness unlock, handoff, grounded verification, quiet hours, pause, order-close modes).  
8. **No silent architecture edits** — conflicts go to Validation Notes and ADR/Product Owner.  
9. **Webhook paths stay thin** — enqueue only; no Wise LLM on the Meta request.  
10. **Package gates remain authoritative** (`messenger_sales_agent`, `ai_intelligence`, and related keys).  
11. **Constraints and Invariants override convenience** — if an implementation idea violates them, it is rejected or requires a Major ADR.  
12. **Later documents must map to Platform Layers and Quality Attributes.**  
13. **Later documents must reference Document `001`** — do not copy or redefine North Star, constraints, invariants, or core terminology.  
14. **Context First** — never decide from the latest message alone.  

---

## Architecture Invariants

These statements are expected to remain true throughout the lifetime of the platform. They are the architectural “laws of physics.”

Any future proposal that breaks an invariant requires a **deliberate architecture review** (Major version + ADR) — not an accidental code change.

| ID | Invariant |
|----|-----------|
| INV-1 | **Wise is channel-agnostic.** |
| INV-2 | **WooCommerce is the Commerce Source of Truth.** |
| INV-3 | **Hub hosts Wise.** |
| INV-4 | **Adapters own transport.** |
| INV-5 | **Wise owns reasoning** (Understanding → recommendation). |
| INV-6 | **Commerce owns facts.** |
| INV-7 | **Humans can always override AI** (Semi, pause, handoff, per-thread AI off). |
| INV-8 | **Stable contracts are preferred over stable implementations.** |
| INV-9 | **Wise produces recommendations, not irreversible commerce commits.** |
| INV-10 | **Backward-compatible defaults until an approved migration opt-in.** |

---

## Core Terminology

These terms are part of the platform language. Every subsequent architecture document must use them consistently.

| Term | Definition |
|------|------------|
| **Context** | The bounded bundle of inputs Wise uses for one decision: recent messages, settings, channel capability flags, lead/memory snapshot, and commerce fact snapshot |
| **Conversation** | The merchant-visible dialogue thread (Plugin inbox SoT for Messenger/Comments today) |
| **Session** | A Wise decision episode over a conversation (one or more turns sharing continuity); not synonymous with WooCommerce session |
| **Knowledge** | Merchant-approved product/store content (pitch, FAQ, objections, policy) plus platform packs — distinct from raw chat history |
| **Memory** | Short-horizon dialogue state (summary, emotion, language, asked flags, soft negotiation counts); not Order Intelligence fraud graphs by default |
| **Decision** | Structured **recommendation** produced by Wise before channel execution — adapters or Plugin/commerce may reject, draft-only, or modify application |
| **Action** | The chosen next step type (e.g. suggest reply, ask, preview, hold, escalate, noop) — adapters apply transport |
| **Capability** | A Wise or channel ability that can be gated (package, settings, channel flags) |
| **Confidence** | Score or floor used to decide draft vs auto-send eligibility; merchant thresholds remain enforceable |
| **Clarification** | A decision to ask for missing information before asserting commerce facts |
| **Grounding** | Constraining claims to Woo facts + approved knowledge; rejecting invented prices/discounts/forbidden claims |
| **Contract** | Stable request/response boundary between adapters, Wise, and commerce — replaceable internals, stable exteriors |

---

## Architecture Document Versioning Policy

Architecture documents in `docs/architecture/` follow **semantic versioning**.

| Change type | Version impact | Examples |
|-------------|----------------|----------|
| **Major** | Breaking architectural change | Reversing a constraint or invariant; moving commerce authority; making Wise Meta-dependent |
| **Minor** | New approved capability or additive section | New quality attribute detail; new channel in target scope; new core term |
| **Patch** | Documentation clarification only | Typos, wording, cross-links, formatting |

**Rules:**

- **Document `001` is the Approved foundation.** Do not modify it casually.
- Approved decisions are not silently rewritten; use ADR + version bump.
- `001` Minor bumps may clarify platform law; Major bumps require Product Owner acknowledgment and ADR when invariants/constraints change.
- Engine docs (`002+`) must reference `001` and must not contradict Constraints, Invariants, or Capability Boundaries without an ADR that updates `001`.
- Governance order is defined in **Architecture Decision Hierarchy** (Document `001` → ADRs → Architecture docs → Requirements → Implementation → Tests).

This policy aligns with `docs/README.md` versioning rules.

---

## Future Extensions

- Additional channel adapters via the Channel Adapter Framework (`009`)
- Shared evaluation harness (`010`)
- Optional unification of LLM gateway across Wise and other Hub AI products (requires ADR)
- Richer cross-store memory only with explicit privacy and product approval
- Expanded structured inputs (OCR, campaign context, API payloads) via Channel Layer normalization

---

## Open Questions

| ID | Question | Owner |
|----|----------|--------|
| OQ-1 | Timeline and opt-in model for dual-running Plugin Sales Agent vs Hub Wise | Product Owner |
| OQ-2 | Merchant OpenAI key vs platform-managed keys for Wise runtime | Product Owner + Architect |
| OQ-3 | Whether Comments ever share full sales/order funnel with Messenger | Product Owner |
| OQ-4 | Whether Blog AI prompt/LLM infrastructure is reused by Wise or kept isolated | Architect + Validator |
| OQ-5 | Retention and residency rules for any Hub-side decision transcripts | Product Owner |

---

## Long-term Roadmap

| Phase | Intent | Compatibility |
|-------|--------|---------------|
| **R0 — Document** | Architecture docs, ADRs, validation against codebase | No runtime change |
| **R1 — Contract** | Stable Wise decision request/response *concepts* (docs only until implementation approved) | No merchant break |
| **R2 — Shadow** | Optional shadow decisions vs Plugin Sales Agent for comparison | Default path unchanged |
| **R3 — Adapter cutover** | Messenger/Comments call Wise behind feature flag | Opt-in / package-gated |
| **R4 — New channels** | Widget, API, WhatsApp, Voice as Wise clients | Additive |
| **R5 — Harden** | Evaluation, learning activation policy, pack evolution | Continuous |

Detailed migration is reserved for `011-migration-strategy.md`.

### Evaluation Gate (ties to Document `010`)

Every capability introduced into Wise must have, **before production rollout**:

1. **Acceptance Criteria**
2. **Evaluation Cases**
3. **Regression Tests**

Shadow comparison against the Plugin Sales Agent baseline is preferred whenever behavior could affect live merchants. See `010-ai-evaluation-framework.md`.

---

## Success Metrics

| Metric | Intent |
|--------|--------|
| **Zero unplanned breaks** | No regression on license, Meta inbound/outbound, Semi drafts, Full gates, order-close |
| **Adapter coverage** | Number of channels invoking the same Wise decision contract |
| **Handoff quality** | Escalations remain explainable (reason codes preserved) |
| **Grounding integrity** | No increase in invented price/discount incidents vs baseline |
| **Migration safety** | Shadow agreement rate before any default cutover |
| **Doc currency** | Approved docs match shipped behavior within one release cycle |
| **Constraint / invariant compliance** | No shipped Wise path violates Architectural Constraints or Architecture Invariants without ADR |

Exact numeric SLOs are set by Product Owner in requirements docs.

---

## Glossary

| Term | Definition |
|------|------------|
| **Wise** | Channel-agnostic AI Platform hosted on the Hub Backend (Intelligence Layer) |
| **Hub** | Laravel remote backend / WPSaleHub control plane |
| **Plugin** | WordPress `woo-easy-life` extension |
| **Commerce SoT** | WooCommerce + Plugin as authority for catalog, price, stock, orders |
| **Channel adapter** | Channel Layer component that maps a channel’s events/transport to/from Wise |
| **Sales Agent** | Existing Plugin Messenger automation (Semi/Full) — production baseline |
| **Semi / Full** | Draft-for-human vs gated auto-send modes |
| **Intent packs** | Hub-served anonymized NLU/label packs (no merchant PII) |
| **Order Intelligence** | Hub cross-merchant risk/intel plane — not Wise chat by default |
| **Blog AI** | Hub content-generation product — separate from Wise conversational core |
| **ADR** | Architecture Decision Record |
| **Platform layer** | One of Experience, Channel, Intelligence, Knowledge, Commerce, Infrastructure |

See also **Core Terminology** for Context, Conversation, Session, Knowledge, Memory, Decision, Action, Capability, Confidence, Clarification, Grounding, and Contract.

---

## Validation Notes

Validated against Hub Backend and WordPress plugin architecture discovery (2026-07-30). See **Current State (2026)** vs **Target Architecture**. Related-doc plan aligned with Document `002` Approved (v0.4.1); no production redesign.

### Conflict V-1 — Sales Agent brain location

| | |
|--|--|
| **Conflict** | Target places channel-agnostic decisioning in Hub **Wise** (Intelligence Layer). Production Sales Agent decision pipeline runs **on the Plugin** today (`messenger-agent` helpers), not in Hub Wise (scaffold empty). |
| **Risk** | Premature cutover would change latency, OpenAI billing locus, failure modes, and Semi/Full behavior for live merchants. |
| **Suggested resolution** | Treat Plugin Sales Agent as the **production baseline**. Migrate via document → shadow → flagged adapter cutover (`011`). Do not delete or bypass Plugin manager send gates in early phases. |

### Conflict V-2 — Multiple AI products on Hub

| | |
|--|--|
| **Conflict** | Hub already runs **Blog AI** (OpenAI) and **Order Intelligence** “AI” profile APIs. Naming “AI Platform” could be misread as a merge. |
| **Risk** | Accidental coupling of content pipelines or fraud graphs into conversational Wise. |
| **Suggested resolution** | Keep planes separate by default (Design Principle 10). Any shared LLM gateway requires an explicit ADR. |

### Conflict V-3 — Channel readiness vs roadmap list

| | |
|--|--|
| **Conflict** | Overview lists Widget, WhatsApp, Voice, API as supported channels for the **platform**. Most are not Wise clients in production today. |
| **Risk** | Stakeholders may assume features are shipped. |
| **Suggested resolution** | Treat the channel table as **target platform scope**. Marketing claims remain gated by `FEATURES.md`. Implementation only after Product Owner priority and adapter design (`009`). |

### Conflict V-4 — Instagram dual meaning

| | |
|--|--|
| **Conflict** | Instagram Messaging already rides the Messenger Hub↔Plugin path; Instagram Comments are part of Comments automation. |
| **Risk** | Duplicate “Instagram” adapters or inconsistent policies. |
| **Suggested resolution** | Model Instagram Messaging under the Messenger adapter family; Comments under the Comments adapter; shared Wise policies via channel capability flags. |

### Conflict V-5 — Decision Lifecycle vs production pipeline naming

| | |
|--|--|
| **Conflict** | Production Sales Agent steps are named as jobs/manager/workers (intent, catalog, KB, verify, Semi/Full send). Wise uses Decision Lifecycle: Understand → Clarify → Verify → Decide → Respond → Learn. |
| **Risk** | Teams may assume a greenfield rewrite of the pipeline. |
| **Suggested resolution** | Treat the Decision Lifecycle as the **stable platform language**. Map existing Sales Agent stages onto it in `007` (Decision System) / `011` without requiring a big-bang rewrite. |

No silent redesign was applied to resolve these conflicts.

---

## Related Documents

| Document | Status |
|----------|--------|
| [docs/README.md](../README.md) | Active · RA **1.0.0** index |
| [`002-wise-ai-core-architecture.md`](002-wise-ai-core-architecture.md) | **Approved** · **Frozen** (Core Blueprint) |
| [`003-context-engine.md`](003-context-engine.md) | **Approved** · **Frozen** |
| [`004-conversation-engine.md`](004-conversation-engine.md) | **Approved** · **Frozen** |
| [`005-knowledge-engine.md`](005-knowledge-engine.md) | **Approved** · **Frozen** |
| [`006-memory-engine.md`](006-memory-engine.md) | **Approved** · **Frozen** |
| [`007-decision-system.md`](007-decision-system.md) | **Approved** · **Frozen** (Decision Coordinator · Decision Engine · Safety · Verification) |
| [`008-learning-engine.md`](008-learning-engine.md) | **Approved** · **Frozen** |
| [`009-channel-adapter-framework.md`](009-channel-adapter-framework.md) | **Approved** · **Frozen** |
| [`010-ai-evaluation-framework.md`](010-ai-evaluation-framework.md) | **Approved** · **Frozen** |
| [`011-migration-strategy.md`](011-migration-strategy.md) | **Approved** · **Frozen** |
| [`../implementation/`](../implementation/) | Engineering (active) |
| `docs/adr/*` | Governance amendments |
| `docs/glossary/` · `docs/api/` | Engineering support (scaffolded) |

**Maturity progression:** `001`–`011` Reference Architecture **1.0.0** (**Approved** · **Frozen**) → `docs/implementation/` → ADRs for amendments → Requirements / API as needed.

---

## Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial Draft overview from approved discovery principles; Validation Notes for production conflicts |
| 0.2.0 | 2026-07-30 | Documentation Lead | Architect review: Architectural Constraints, Quality Attributes, AI Capability Boundaries, Decision Philosophy, Platform Layers, Core Terminology, Versioning Policy; renamed Supported Inputs & Modalities; Validation Note V-5 |
| 0.3.0 | 2026-07-30 | Documentation Lead | North Star; Design Philosophy; Evolution Principle; Context First; Determinism; Decision = recommendation; Decision Lifecycle rename; Architecture Invariants; Evaluation Gate; **Status → Approved** (foundation document) |
| 0.4.0 | 2026-07-30 | Documentation Lead | Separate Current State (2026) vs Target Architecture; Architecture Decision Hierarchy (governance model) |
| 0.4.1 | 2026-07-30 | Documentation Lead | Related docs: Document `002` Approved; reorder `003` Context / `004` Conversation; `007` Decision System |
| 0.4.2 | 2026-07-30 | Documentation Lead | Related docs: link Document `006` Memory Engine Draft |
| 0.4.3 | 2026-07-30 | Documentation Lead | Related docs: Document `006` Memory Engine **Approved** |
| 0.4.4 | 2026-07-30 | Documentation Lead | Related docs: link Document `007` Decision System Draft |
| 0.4.5 | 2026-07-30 | Documentation Lead | Related docs: link Document `008` Learning Engine Draft |
| 0.4.6 | 2026-07-30 | Documentation Lead | Related docs: link Document `009` Channel Adapter Framework Draft |
| 0.4.7 | 2026-07-30 | Documentation Lead | Related docs: link Document `010` AI Evaluation Framework Draft |
| 0.4.8 | 2026-07-30 | Documentation Lead | Record Architecture Freeze (v1.0 Reference Architecture `001`–`010`); next = `011` Migration |
| 0.4.9 | 2026-07-30 | Documentation Lead | Related docs: link Document `011` Migration Strategy Draft |
| 0.5.0 | 2026-07-30 | Documentation Lead | Wise AI Platform Reference Architecture **1.0.0** — Documents `001`–`011` Approved · Frozen; engineering shifts to `docs/implementation/` |
