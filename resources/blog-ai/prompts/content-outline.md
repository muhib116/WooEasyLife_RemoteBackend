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
    {"heading": "H2 with practical angle...", "h3": ["H3..."], "bullets": ["..."], "word_target": 220}
  ],
  "faqs": [{"q": "...", "a_points": ["..."]}],
  "internal_links": [{"path": "/...", "anchor": "...", "reason": "..."}],
  "external_links": [{"url": "https://...", "anchor": "...", "reason": "authoritative source"}],
  "image_plan": [{"position": "after_intro", "alt": "...", "caption": "..."}],
  "cta": "soft CTA sentence"
}

## Requirements
- Target total body ~1400–2000 Bangla words across sections (sum of word_target ≈ that band).
- Use ONLY paths from the provided internal link catalog (2–4 links).
- MUST include cluster_landing.primary_path (or must_link_paths) as the first internal link.
- Echo page FAQs/angle_hint truth — do not invent features beyond product_brief + cluster_landing.
- Include **at least 5 FAQ items** under faqs (q + a_points). Prefer 5–7.
- Include at least 4 H2 sections; at least 2 sections should list H3 children.
- Include a differentiation section that beats generic competitor blogs
  (practical BD COD steps + WooEasyLife truth).
- Plan one Featured Snippet “Quick Answer” (দ্রুত উত্তর) and one AI Search Summary block.
- Suggest 1–2 trustworthy external references (gov/courier docs/Wikipedia-style) — no spam domains.
- Include a differentiation / checklist / comparison angle where useful.
- Honor article_type when provided (howto / comparison / glossary / case_study).
