# WooEasyLife / Wise AI Platform Documentation

**Product:** Wise AI Platform  
**Bundle:** Reference Architecture **1.0.0**  
**Status:** **Approved** · **Frozen** (`architecture/001`–`011`)  
**Last Updated:** 2026-07-30  
**Maintainers:** Documentation Lead (Cursor) · Chief AI Architect (ChatGPT) · Product Owner  

**Governance:** Document `001` (Constitution) → Document `002` (Core Blueprint) → Documents `003`–`010` (engines) → Document `011` (Migration) → **ADRs** for amendments → **`implementation/`** for engineering  

**Freeze rules:** No new architecture documents. No ownership or boundary changes outside ADRs. No new published artifacts unless approved through architecture governance. Engineering must answer: *How do we implement the frozen architecture without violating its contracts?*

Published artifacts (immutable set):

`ConversationView` · `KnowledgeHits` · `MemoryView` · Decision Context · `DecisionResult` · `LearningResult` · `ExecutionRequest` · `EvaluationResult`

---

## Purpose

This directory is the official, Git-friendly source of truth for the **Wise AI Platform** architecture and the engineering that realizes it.

- **`architecture/`** — frozen Reference Architecture 1.0.0 (`001`–`011`)
- **`implementation/`** — how to build without violating contracts
- **`adr/`** — amendments to frozen architecture
- **`diagrams/`**, **`api/`**, **`glossary/`** — supporting engineering assets

It does not replace product feature claims in `.cursor/skills/wooeasylife-brand/FEATURES.md` or package marketing copy in `docs/package-features-bn.md`.

---

## Folder Structure

```text
docs/
├── README.md                 # This index
├── package-features-bn.md    # Package/feature marketing catalog (BN)
├── architecture/             # 001–011 — Reference Architecture 1.0.0 (FROZEN)
├── implementation/           # Engineering (active)
│   ├── runtime/
│   ├── contracts/
│   ├── state-machines/
│   ├── sequences/
│   ├── integrations/
│   └── reference/
├── adr/                      # Architecture Decision Records
├── diagrams/                 # Diagrams (non-code)
├── api/                      # API surface docs (when authored)
└── glossary/                 # Shared terms (architecture + engineering)
```

| Path | Role |
|------|------|
| `architecture/` | Frozen platform design (`001`–`011`) — do not expand |
| `implementation/` | Runtime behavior, contracts, sequences, reference builds |
| `adr/` | Discrete decisions that may amend frozen architecture |
| `diagrams/` | Visual aids referenced by architecture or implementation |
| `api/` | External/internal API documentation |
| `glossary/` | Canonical term list aligned with Document `001` |

---

## Reference Architecture 1.0.0 (`001`–`011`)

| Doc | Title | Version | Status |
|-----|-------|---------|--------|
| [architecture/001-wise-ai-platform-overview.md](architecture/001-wise-ai-platform-overview.md) | Platform Overview (Constitution) | 0.5.0 | **Approved** · **Frozen** |
| [architecture/002-wise-ai-core-architecture.md](architecture/002-wise-ai-core-architecture.md) | Core Architecture (Blueprint) | 0.4.0 | **Approved** · **Frozen** |
| [architecture/003-context-engine.md](architecture/003-context-engine.md) | Context Engine | 0.2.7 | **Approved** · **Frozen** |
| [architecture/004-conversation-engine.md](architecture/004-conversation-engine.md) | Conversation Engine | 0.2.6 | **Approved** · **Frozen** |
| [architecture/005-knowledge-engine.md](architecture/005-knowledge-engine.md) | Knowledge Engine | 0.1.8 | **Approved** · **Frozen** |
| [architecture/006-memory-engine.md](architecture/006-memory-engine.md) | Memory Engine | 0.2.5 | **Approved** · **Frozen** |
| [architecture/007-decision-system.md](architecture/007-decision-system.md) | Decision System | 0.1.4 | **Approved** · **Frozen** |
| [architecture/008-learning-engine.md](architecture/008-learning-engine.md) | Learning Engine | 0.1.3 | **Approved** · **Frozen** |
| [architecture/009-channel-adapter-framework.md](architecture/009-channel-adapter-framework.md) | Channel Adapter Framework | 0.1.2 | **Approved** · **Frozen** |
| [architecture/010-ai-evaluation-framework.md](architecture/010-ai-evaluation-framework.md) | AI Evaluation Framework | 0.1.2 | **Approved** · **Frozen** |
| [architecture/011-migration-strategy.md](architecture/011-migration-strategy.md) | Migration Strategy | 0.2.0 | **Approved** · **Frozen** |

---

## Engineering phase

Active work lives in [`implementation/`](implementation/README.md).

| Area | Question it answers |
|------|---------------------|
| `runtime/` | How does each module behave in production? |
| `contracts/` | Formal definitions of each published artifact |
| `state-machines/` | Allowed state transitions without new ownership |
| `sequences/` | End-to-end request flows |
| `integrations/` | Hub / Plugin / channel edges under Adapter law |
| `reference/` | Reference implementation conforming to architecture |

Do **not** invent Prompt/LLM/RAG/Embeddings **architecture** docs. Those belong only as implementation choices behind frozen contracts (if needed).

---

## Documentation Workflow

1. **Architecture change** — ADR first; update `001`/`002` only if the ADR requires it; never silent redesign.
2. **Engineering** — author under `implementation/`; validate against `001`–`011` contracts.
3. **Validate** — Cursor checks Hub + Plugin production risk.
4. **Decide** — Product Owner approves merchant-facing scope.
5. **Version** — document headers + revision history in the same change.

**Rules:**

- Do not invent features.
- Do not redesign production behavior unless the Product Owner explicitly requests it.
- Do not change approved architecture without a new ADR.
- Implementation docs must not redefine ownership, boundaries, or published artifacts.
- If a proposal conflicts with the live codebase, record **Validation Notes**; do not silently rewrite production.

---

## Versioning Rules

| Layer | Versioning |
|-------|------------|
| **Reference Architecture bundle** | **1.0.0** (this freeze) — major only when ADR breaks platform law |
| **Per-document** | `MAJOR.MINOR.PATCH` in each header (history preserved) |
| **Implementation docs** | Independent; must cite the RA version they implement |
| **ADRs** | Once **Accepted**, not rewritten in place; supersede with a new ADR |

Status values: `Draft` · `In Review` · `Approved` · `Superseded` · `Deprecated`

---

## Naming Conventions

| Asset | Convention | Example |
|-------|------------|---------|
| Architecture specs | `NNN-kebab-case-title.md` | `001-wise-ai-platform-overview.md` |
| Implementation | `kebab-case.md` under area folders | `contracts/decision-result.md` |
| ADRs | `NNNN-kebab-case-title.md` | `0001-hub-hosts-wise-core.md` |
| Diagrams | `kebab-case.ext` | `wise-high-level.mmd` |

- **Wise** = AI Platform (target Intelligence Layer on Hub)
- **Plugin** = WordPress `woo-easy-life`
- **Hub** = Laravel control plane (`WooEasyLife_RemoteBackend` / WPSaleHub)

---

## Review Process

| Role | Responsibility |
|------|----------------|
| **Chief AI Architect (ChatGPT)** | Design proposals; ADRs; engineering review against frozen law |
| **Documentation Lead / Validator (Cursor)** | Codebase validation; refuse silent redesign; maintain consistency |
| **Product Owner** | Business approval; merchant-facing scope |

---

## Relationship: ChatGPT · Cursor · Product Owner

```text
ChatGPT (Chief AI Architect)
        │ proposes design / engineering approach
        ▼
Cursor (Documentation Lead + Codebase Validator)
        │ validates · documents · flags risk
        ▼
Product Owner
        │ approves business decisions
        ▼
docs/ (Git) — official record
```

---

## Related Existing Materials (outside this tree)

| Location | Use |
|----------|-----|
| `docs/package-features-bn.md` | Package feature catalog (business/marketing) |
| `.cursor/skills/wooeasylife-brand/FEATURES.md` | Shipped vs do-not-claim product inventory |
| Plugin `docs/` (Messenger/Comments Hub contracts) | Live Hub↔Plugin channel contracts |

---

## Revision History

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 0.1.0 | 2026-07-30 | Documentation Lead | Initial documentation repository README |
| 0.2.0 | 2026-07-30 | Documentation Lead | Align versioning language with Document 001; track 001 v0.2.0 |
| 0.3.0 | 2026-07-30 | Documentation Lead | Document 001 Approved as foundation; require subsequent docs to reference 001 |
| 0.4.0 | 2026-07-30 | Documentation Lead | Track 001 v0.4.0; document governance hierarchy in README header |
| 0.4.1–0.6.5 | 2026-07-30 | Documentation Lead | Indexed Documents `002`–`011` through Migration Draft |
| **1.0.0** | 2026-07-30 | Documentation Lead | **Reference Architecture freeze** — `001`–`011` Approved · Frozen; scaffold `implementation/`, `adr/`, `diagrams/`, `api/`, `glossary/`; engineering phase begins |
