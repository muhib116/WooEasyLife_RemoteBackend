# Implementation

**Reference Architecture:** Wise AI Platform **1.0.0** (`../architecture/001`–`011` · **Frozen**)  
**Status:** Active (engineering)  
**Last Updated:** 2026-07-30  

Every document in this tree answers one question:

> **How do we implement the frozen architecture without violating its contracts?**

## Rules

- Do **not** invent engines, ownership, layers, or published artifacts.
- Do **not** redefine Documents `001`–`011`.
- Cite the frozen artifact you implement (`ConversationView`, `KnowledgeHits`, `MemoryView`, Decision Context, `DecisionResult`, `LearningResult`, `ExecutionRequest`, `EvaluationResult`).
- Architecture amendments require an **ADR** first.
- Prefer Migration flags / Shadow / Dual Run / Promotion Gates from Document `011`.

## Areas

| Path | Purpose |
|------|---------|
| [runtime/](runtime/README.md) | Production behavior of each module |
| [contracts/](contracts/README.md) | Formal published-artifact contracts |
| [state-machines/](state-machines/README.md) | Allowed transitions (no new ownership) |
| [sequences/](sequences/README.md) | End-to-end request flows |
| [integrations/](integrations/README.md) | Hub / Plugin / channel edges |
| [reference/](reference/README.md) | Reference implementation notes |

## Start here

1. Read Document `001` (law) and `002` (module map).
2. Read Document `011` (journey) — especially Appendix A Timeline Matrix.
3. Author the matching contract + runtime + sequence for one module at a time.
