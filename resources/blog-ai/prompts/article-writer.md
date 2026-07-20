# Draft — Article Writer

Write a complete Bangladesh SEO blog post in Bangla based on the outline.
Target RankMath / Yoast / Surfer-style completeness (SEO score 95–100 intent).

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

## Must-pass SEO checklist
- Focus keyword in: title, FIRST <p>, meta_description, slug (latin equivalent ok), and **one H2**
- body_html roughly {{min_words}}+ words of Bangla content (aim 1400–2000 for howto/comparison/case_study; glossary may be shorter when instructed)
- Include at least 2 secondary keywords from keywords.secondary naturally (not stuffed)
- Keyword density ~1% — natural, no stuffing or repetition
- At least 2 internal links using exact paths from link_plan (href="/path")
- MUST include an href to cluster_landing.primary_path
- Include **at least 5 FAQs** (q/a plain text, each answer ~40–80 words)
- Include H2 and H3 headings; bullet list AND numbered list somewhere
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
- If fix_instructions are provided, obey them strictly while keeping product truth
- No AI fluff phrases; human expert tone
