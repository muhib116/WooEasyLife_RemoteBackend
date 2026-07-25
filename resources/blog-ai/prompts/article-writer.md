# Draft — Article Writer

Write a complete Bangladesh SEO blog post in Bangla based on the outline.
SEO completeness matters — but natural seller voice matters more than sounding like Surfer/RankMath filler.

Return JSON:
{
  "title": "...",
  "slug": "latin-kebab-only",
  "locale": "bn",
  "focus_keyword": "...",
  "meta_title": "<=70 chars",
  "meta_description": "50-160 chars",
  "excerpt": "...",
  "author_name": "{{author_name}}",
  "robots": "index,follow",
  "quick_answer": "40-60 words Featured Snippet style Bangla answer",
  "ai_search_summary": "100-150 words Bangla summary for ChatGPT/Gemini/Perplexity style overviews",
  "body_html": "valid HTML only (h2,h3,p,ul,ol,li,a,strong,em,blockquote,table,thead,tbody,tr,th,td,figure,img,figcaption,section)",
  "faqs": [{"q": "...", "a": "..."}],
  "external_links": [{"url": "https://...", "anchor": "..."}],
  "image_suggestions": [{"position": "after_intro", "alt": "...", "caption": "...", "prompt": "..."}],
  "seo_notes": ["..."]
}

## Structure contract (source of truth)
- Obey editorial playbook + article_type skeleton in the system message.
- Render body in skeleton slot order. Do not reshuffle H2s for creativity.
- Map outline `sections[].slot` → matching H2 blocks in the same order.
- Bookends are fixed: দ্রুত উত্তর → body slots → এআই সারাংশ → FAQs → closing CTA.
- Competitor gaps fill differentiation / steps / FAQ slots — they do not create a new flow.
- Product claims only from product_brief + cluster_landing.

## Voice first (do this before stuffing SEO)
- Messenger-style Bangla seller talk. Concrete BD COD examples.
- Each H2: problem → practical steps → when useful, soft WooEasyLife mention.
- Prefer short `<p>` paragraphs over long listicles.
- Use a list ONLY when counting real steps/options/tools — not every section.
- Do NOT mirror the outline bullets word-for-word as the final article.
- FAQs: spoken questions sellers actually ask; answers direct, no essay openings.
- quick_answer / ai_search_summary: clear and useful, still human — not brochure copy.

## Must-pass SEO checklist
- Focus keyword in: title, FIRST <p>, meta_description, slug (latin equivalent ok), and **one H2**
- body_html roughly {{min_words}}+ words of Bangla content (aim 1400–2000 for howto/comparison/case_study; glossary may be shorter when instructed)
- Write enough real teaching in each slot so the draft clears {{min_words}} without filler
- NEVER pad with repeated keyword sentences, fake “ধাপ N” clones, or off-topic fraud/history lines when the topic is different (invoice, packing, ads, courier, etc.)
- Stay on the focus keyword’s actual job — every H2 must teach that topic for BD COD sellers
- Include at least 2 secondary keywords from keywords.secondary naturally (not stuffed)
- Keyword density ~1% — natural, no stuffing or repetition
- Prefer long-tail focus keywords from research; do not steal money-page head terms (fraud checker bd, ফ্রড চেকার, etc.)
- If outline/research includes a suggested slug from seo_keyword_inventory, keep that slug when it matches ^[a-z0-9-]+$
- At least 2 internal links using exact paths from link_plan (href="/path")
- MUST include an href to cluster_landing.primary_path
- Include **at least 5 FAQs** (q/a plain text, each answer ~40–80 words)
- Include H2 and H3 headings where they help scanning
- Include a table when comparing options / steps / ratios makes sense
- Start body with Featured Snippet block:
  <section class="seo-quick-answer"><h2>দ্রুত উত্তর</h2><p>...</p></section>
  (use quick_answer text; 40–60 words)
- Include AI Search Summary near the end (before FAQ/CTA):
  <section class="seo-ai-summary"><h2>এআই সারাংশ</h2><p>...</p></section>
  (use ai_search_summary; 100–150 words)
- One soft WooEasyLife CTA near the end pointing to primary_path
- Conclusion that is practical (do NOT start with “In conclusion” / “পরিশেষে বলতে গেলে”)
- Optional: 1 trustworthy external https link in body (not spam)
- No script tags, no invented product claims
- slug must match ^[a-z0-9]+(?:-[a-z0-9]+)*$
- If fix_instructions are provided, obey them strictly while keeping product truth and human voice
