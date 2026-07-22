# Outline — Content Planner

Create one SEO outline for a Bangla blog post using the selected hook(s).
Prefer the first selected hook as H1; others may become H2 angles if complementary.

Return JSON:
{
  "h1": "...",
  "focus_keyword": "...",
  "slug_suggestion": "latin-kebab-slug",
  "meta_title_hint": "<=70 chars with focus keyword",
  "meta_description_hint": "50-160 chars with focus keyword",
  "quick_answer_points": ["40-60 word featured-snippet answer points"],
  "ai_search_summary_points": ["100-150 word AI-overview style points"],
  "sections": [
    {"slot": "intro_scene", "heading": "H2...", "h3": ["H3..."], "bullets": ["talking points only"], "word_target": 150}
  ],
  "faqs": [{"q": "...", "a_points": ["..."]}],
  "internal_links": [{"path": "/...", "anchor": "...", "reason": "..."}],
  "external_links": [{"url": "https://...", "anchor": "...", "reason": "authoritative source"}],
  "image_plan": [{"position": "after_intro", "alt": "...", "caption": "..."}],
  "cta": "soft CTA sentence for cta_close slot"
}

## Structure contract (source of truth)
- Obey the editorial playbook + article_type skeleton provided in the system message.
- Every body section MUST include `"slot"` matching the skeleton (in order).
- Do NOT invent extra top-level flow. Map competitor gaps into differentiation / steps / faqs slots.
- Section bullets are talking points only — draft will expand into natural paragraphs.
- Plan Quick Answer + AI Summary + FAQs + CTA as fixed bookends (not freeform H2 chaos).

## Requirements
- Target total body ~1400–2000 Bangla words across sections (sum of word_target ≈ that band; glossary may be shorter).
- Use ONLY paths from the provided internal link catalog (2–4 links).
- MUST include cluster_landing.primary_path (or must_link_paths) as the first internal link.
- Echo page FAQs/angle_hint truth — do not invent features beyond product_brief + cluster_landing.
- Include **at least 5 FAQ items** under faqs (q + a_points). Prefer 5–7. Write FAQ qs as spoken seller questions.
- Include H3 children on at least 2 body sections when the skeleton has room.
- Suggest 1–2 trustworthy external references (gov/courier docs/Wikipedia-style) — no spam domains.
- Honor article_type skeleton exactly (howto / comparison / glossary / case_study).
- Outline headings should sound human (problem/scene), not corporate SEO templates.
