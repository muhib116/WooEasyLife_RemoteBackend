# Editorial playbook — WooEasyLife blog (source of truth)

This file is the **structure + sourcing contract** for every Blog AI post.
Voice rules live in `prompts/system.md`. Product truth lives in `product_brief` + `cluster_landing`.

## Source-of-truth hierarchy (never reverse)

1. **Product / landing truth** — `cluster_landing.landing_page_reference`  
   (`primary_url`, SEO page H1/lead/FAQs/claims, optional live snapshot).  
   Never invent features, prices, metrics, or partnerships. Soft-link `primary_path`.
2. **Structure truth** — this playbook + `skeletons/{article_type}.md`  
   Section **order is fixed**. Do **not** copy the landing page layout as the blog outline.
3. **Standing memory** — prefer/avoid keywords, brand instructions, lessons  
   Adjusts wording/angles inside slots; does not reorder slots.
4. **Competitor gaps** — open gaps / must-cover angles  
   Map into the matching skeleton slot (usually differentiation, steps, or FAQ).  
   Never create a new top-level flow just to cover a gap.
5. **Performance learning / GSC** — topic, title angle, CTR  
   Chooses *what* to write about, not section order.

## Landing URL as reference

- Every draft receives the cluster landing **URL** (`primary_url`) plus SEO brief.
- Use landing to lock problem, claims, FAQs, and CTA destination.
- Blog flow stays: Quick Answer → intro → skeleton body → AI Summary → FAQs → CTA  
  (informational blog), even when the landing is a tool/calculator page.

## Canonical post flow (all article types)

Render in this exact order:

1. **দ্রুত উত্তর** (`seo-quick-answer`) — 40–60 words, direct answer  
2. **Intro scene** — real BD seller problem / moment (short paragraphs)  
3. **Body slots** — follow the article_type skeleton (howto / comparison / glossary / case_study)  
4. **এআই সারাংশ** (`seo-ai-summary`) — 100–150 words overview  
5. **FAQs** — 5–7 spoken seller questions (JSON `faqs`; optional matching H2 in body)  
6. **Closing CTA** — one soft push to `cluster_landing.primary_path` (and `/pricing` only if natural)

## What each slot is for

| Slot | Purpose | Source |
|------|---------|--------|
| quick_answer | Featured snippet | Focus keyword + landing lead |
| intro_scene | Hook with pain | Seller reality + angle_hint |
| core / steps / comparison / definition | Teach | Product truth + outline talking points |
| differentiation | Beat generic blogs | Competitor open gaps + BD COD specifics |
| tool_bridge | Soft product mention + must links | `must_link_paths`, `seo_tools` |
| ai_summary | AI-overview block | Compress body truth — no new claims |
| faqs | Objections / how-to details | Landing FAQs + competitor faq_gaps + real seller Qs |
| cta_close | Next action | primary_path only; no hard sell |

## Consistency rules

- Outline `sections` must include a `slot` key matching the skeleton (in order).
- Draft must follow outline slot order; do not reshuffle H2s for “creativity.”
- One idea per H2. Do not merge CTA into the middle of teaching slots.
- Lists only inside steps/checklist/comparison slots — not in intro or CTA.
- Keyword once in first content `<p>` (after quick answer) and in **one** body H2 — not every heading.
- If outline and skeleton conflict, **skeleton wins**.
