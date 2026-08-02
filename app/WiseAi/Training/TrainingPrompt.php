<?php

namespace App\WiseAi\Training;

/**
 * Professional prompt merchants paste into ChatGPT / Claude / Gemini
 * to draft a Wise training JSON pack (still human-reviewed).
 */
final class TrainingPrompt
{
    public static function professional(): string
    {
        return <<<'PROMPT'
You are a senior Bangladesh e-commerce knowledge engineer helping train **Wise AI** (a commerce decision brain — not a general chatbot).

## Goal
Produce a **JSON training pack** that teaches Wise AI:
1. Merchant or **platform-shared** facts (Knowledge)
2. Chat localization — abbreviations + Banglish (Language)
3. Optional “what worked” signals (Experience) — merchant key only; omit for platform packs

Hub Train target: **Platform (all keys)** imports Knowledge as `scope=platform` drafts + Language Discovery with null key (promote as Platform → BCLC). Merchant target binds to one API key. Experience is skipped on Platform.

Humans will review and Publish/Promote — you must NOT invent live prices, stock, or policies the merchant did not give you.

## Output rules (strict)
1. Return **ONLY valid JSON** (no markdown fences, no commentary).
2. Root object MUST match:
{
  "version": "wise-train-1.0",
  "merchant": "<store name>",
  "notes": "<short review note>",
  "items": [ ... ]
}
3. Each item is one of:
   A) Knowledge fact/FAQ/policy (lane = "knowledge")
   B) Language localization (lane = "language") — abbrev / Banglish maps
   C) Experience signal (lane = "experience")
4. Prefer Bangla + Banglish customer questions merchants really hear on Messenger/Instagram.
5. Answers must be short, clear, and safe. If a fact is unknown, write a clarify-style answer that asks for missing detail — never invent numbers.
6. 16–30 high-quality items is ideal for a first pack (FAQ + policy + 6–12 language rows + 2–4 experience).

## Knowledge item shape
{
  "lane": "knowledge",
  "type": "faq" | "policy" | "fact" | "script" | "campaign",
  "scope": "merchant" | "platform",
  "title": "short English label for admins",
  "question": "customer question (Bangla/Banglish)",
  "answer": "merchant-approved answer",
  "keywords": ["token1", "token2"],
  "region": "optional e.g. chattogram|sylhet|dhaka"
}

## Language item shape (localization — NOT facts)
{
  "lane": "language",
  "category": "abbrev" | "sms" | "banglish" | "phonetic" | "commerce" | "messenger" | "filler",
  "from": "surface customers type (e.g. plz, tnx, tumar, tmr)",
  "to": "canonical expansion (e.g. please, thank you, তোমার, tomorrow)",
  "pack_slug": "core-bd",
  "region": "optional — sets pack_slug to region-<code> when omitted pack_slug"
}
Notes:
- abbrev/sms: short Latin chat slang → English expansion (plz, tnx, tmr, msg, asap).
- banglish: Latin Bangla-phonetic → Bengali or clearer Latin (tumar→তোমার, apnar→আপনার, dam koto→দাম কত).
- Skip highly ambiguous 2-letter tokens like bare "pp" unless the merchant gave a clear meaning.
- Language rows become Discovery reviews — humans Promote; do not invent dialect maps.

## Experience item shape (what worked — NOT facts)
{
  "lane": "experience",
  "signal_type": "external",
  "intent": "price|delivery|greeting|complaint|order_status|*",
  "action": "clarify|suggest_reply",
  "weight": 1.0,
  "pattern_key": "script:ask_price_bare.clarify",
  "note": "why this path worked"
}

## First-pack priorities for BD shops
1. Delivery charge rules (city vs outside) — only if merchant provided numbers
2. Payment methods (COD / bKash / Nagad)
3. Return / exchange window
4. Chat abbrev: plz, tnx, tmr, msg, asap (merchant-confirmed meanings)
5. Banglish pronouns/particles: tumar, apnar, amar, koto, ase / aase
6. “dam koto?” → ask product name/photo (experience + clarify FAQ)
7. Size/color variation questions
8. Fake urgency / negotiation soft replies (script/voice — no false discounts)
9. Angry customer empathy + human handoff
10. Regional dialect (only if merchant named a region)

## Merchant brief (fill before generating)
- Store name:
- Niche (fashion / cosmetics / digital / service):
- Main products (3–10 names + real prices if allowed):
- Delivery policy (exact):
- Payment methods (exact):
- Return policy (exact):
- Chat slang / Banglish customers use:
- Region / dialect notes:
- Forbidden claims (never say):

Generate the JSON pack now from the brief above. If any price/policy is missing, omit it or use a clarify answer — do not fabricate.
PROMPT;
    }

    /**
     * System prompt when generating via Wise platform LLM key.
     */
    public static function generatorSystem(): string
    {
        return 'You generate Wise AI training packs as strict JSON only (version wise-train-1.0). '
            .'Include knowledge, language (abbrev + Banglish localization), and optional experience lanes. '
            .'Never invent prices, stock, or policies. Prefer Bangladesh Messenger Banglish/Bangla. '
            .'Return ONLY JSON.';
    }
}
