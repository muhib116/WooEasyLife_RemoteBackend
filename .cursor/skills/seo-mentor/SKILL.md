---
name: seo-mentor
description: >
  WooEasyLife SEO mentor strategy, content IA, FAQ roadmap, competitive
  playbook (eCourier hubs; fraud-checker SERP rivals Elite Mart and
  FraudChecker.link), and the 90-day one-topic courier pillar authority
  playbook (pillar → cluster → deep FAQs → YouTube → closed-loop links).
  Use for today's plan, SEO next steps, FAQ architecture, sitemap/internal
  links, GSC-driven content, fraud checker ranking tactics, SteadFast/Pathao
  cluster campaigns, competitor audits, and organic growth vs BD courier/COD
  competitors. Also use when a new plugin or app feature ships, to scan the
  product and turn it into keyword/FAQ/landing rows in
  config/seo_keyword_inventory.php. Pair with wooeasylife-brand and FEATURES.md.
  Canonical domain: app.wpsalehub.com.
---

# WooEasyLife SEO Mentor Skill

**Last reviewed:** 2026-07-30
**Rule (daily plan format):** `.cursor/rules/seo-mentor.mdc`  
**Brand + creatives:** `.cursor/skills/wooeasylife-brand/SKILL.md`  
**Features (claims only if Shipped):** `.cursor/skills/wooeasylife-brand/FEATURES.md`  
**Keyword / slug inventory (wins on conflicts):** `config/seo_keyword_inventory.php` · `App\Services\Seo\SeoKeywordInventory`  
**Canonical CTAs:** `https://app.wpsalehub.com/...` only — never wooeasylife.com

When planning FAQs, blogs, or commercial pages: **read the inventory first**. Do not invent duplicate slugs for money-page head terms. Blog AI already merges this inventory into seed queries, research, topic picker, and LP head-term guards.

**90-day rule (authority):** One Topic → One Authority → One Category → Then Expand. Prefer owning every important search around the **active courier pillar** (default campaign: SteadFast Fraud Check → `/steadfast-fraud-check`) before hopping to Messenger / AI / Pricing themes. See **Courier pillar authority playbook** below.

---

## Agent routing (read first)

| User asks… | Do this |
|------------|---------|
| আজকের প্ল্যান / today's plan | Follow `.cursor/rules/seo-mentor.mdc`; if calendar active → `SeoAuthorityCalendar::resolve()`; **Sundays** also merge Step 9 from `config/seo_authority_metrics.php` / `seo:weekly-report` |
| next steps / SEO plan / organic roadmap | Re-read this skill → give **Default priority stack** + active cluster status (not a week dump) |
| SteadFast cluster / pillar guide / 90-day / authority playbook | **Courier pillar authority playbook** (Steps 1–10) |
| FAQ / `/faq` | Phase 2 — but only after Phase 1 P0s; invent no slugs (use `plannedFaqs()`). Cluster FAQs: deep 600–1000w, inventory-backed — **not** thin 60-word dumps |
| fraud checker rank / SERP | **Fraud checker SERP rivals** + Gap scorecard (SSR P0 first) |
| competitor audit + URL(s) | Run **Competitor re-audit protocol**; update roster if they enter top 3 |
| new feature / update features / shipped | **Feature discovery → SEO opportunity loop** |
| keywords / long-tails / new URLs | Inventory decision rule; write rows to inventory file |
| orphaned sitemap / Semrush crawlability orphans | **Orphaned sitemap pages** playbook below — fix links, never only remove from sitemap |
| review / upgrade this skill | **Skill self-maintenance** checklist |

---

## Current state (keep honest)

| Fact | Status (2026-07-30) |
|------|---------------------|
| Money URL | `/bd-fraud-checker` live (200) |
| FAQ hub | `/faq` **live** + 8 inventory question URLs |
| Inventory | live money/tool/pillar + FAQ hub · 8 live FAQ · `/steadfast-return-hub` + `/woocommerce-facebook-messenger` live · planned courier/messenger FAQs+blogs · remaining planned_commercial |
| Product docs | Plugin **v1.5.4** · App v1.1.6 · `FEATURES.md` last analyzed **2026-07-27** (Courier hub + Messenger) |
| Authority campaign | **Active:** SteadFast → `/steadfast-fraud-check`. Steps **1–3 + 5–9** done. **Step 4 YouTube = skipped for now** (pillar `#video` slot stays empty until `video_youtube_id` is set). Run cluster lock + Sunday Step 9; **Step 10** only after SteadFast wins. |

Update this table when `/faq` ships, major gaps close, versions bump, or the active authority cluster changes.

---

## Core strategy (one sentence)

Steal **structure** from courier help centers (topic hub → question URLs), keep the **product angle** (fraud / COD / WooCommerce ops), and win with titles, depth, FAQ schema, and hreflang already on `app.wpsalehub.com`.

Do **not** clone courier-brand support FAQs (payment schedules, warehouse security, BOD booking, franchise ops).

**Language:** Bangladesh SERP → **Bangla (BN) primary** on money/FAQ pages; English titles/meta OK as support. EN mirrors (`/en/...`) only after BN page works.

**Crawlability (Inertia/Vue sites):** Google needs meaningful HTML in the first response — checker `<input>`/`<form>`, H1, FAQs, and courier names must not live only in client JS. FraudShield ranks despite thin body; we must not copy that weakness.

---

## Do not

| Skip | Why |
|------|-----|
| Clone eCourier payment / warehouse / BOD / compensation FAQs | Wrong brand — navigational for *their* courier |
| Dump 50+ thin FAQ pages | Mirrors their 60–140 word answers; dilutes authority |
| Use `/` as BN “vs” separator in body copy | Linkify injects **হোম** → `হলুদহোমলালে` junk |
| Show raw `/path` or `/faq/...` as visible text | Must be **human-labeled** links (never dump bare permalinks in UI or drafts) |
| Blog `body_html` (CKEditor): bare `/bd-fraud-checker` etc. | Blog has **NO linkify** — every internal URL must be `<a href="/path">বাংলা লেবেল</a>` |
| Invent features | Only **Shipped** in `FEATURES.md` |
| Link wooeasylife.com | Canonical is `app.wpsalehub.com` |
| Competitor hate / fake #1 / AggregateRating fakes | Brand + trust rules |
| Duplicate fraud landings | Prefer deepen existing pillars/tools |
| Blog that steals money-page head terms | Soft-link the live money URL instead |
| Claim gated features as free | Note package keys; free tool pages stay honest |
| App lock / Meta AI Bot / Paperfly auto-entry as live | See `FEATURES.md` Do NOT claim |
| Theme-hop mid-cluster (Mon SteadFast → Tue Messenger → Wed AI) | Breaks topical authority; run **cluster lock weeks** instead |
| Expand to Pathao / Fake Order / Fraud Checker BD before active pillar wins | Wait until pillar is top-results + meaningful traffic (**Step 10**) |
| Thin 50–100 FAQ stubs (60–140 words) | Allowed: ~15–20 **deep** cluster FAQs (600–1000w) that strengthen one pillar |
| Claim the tool “guarantees” fake/genuine | Honesty line: informed decision only — see **Trust signals** |

---

## Default priority stack (when unsure)

Do in order — stop when the day's budget is used:

1. **P0 — Deepen** `/bd-fraud-checker` + `/fake-customer-check` FAQs (BN) + keep FAQPage schema *(Result CTAs + SSR form + HowTo **shipped**; WebApplication removed — Semrush Software App invalid without real ratings)*  
2. **Active authority cluster (when running):** finish **Courier pillar authority playbook** for the locked topic (default: SteadFast → `/steadfast-fraud-check`) before rotating themes  
3. **Internal links:** fraud ↔ COD ↔ calculators ↔ courier pillars ↔ `/faq` ↔ `/pricing` — closed loop (guide ↔ FAQ ↔ video ↔ tool ↔ case ↔ calculator ↔ guide)  
4. **Courier pillars:** Pathao / Steadfast / RedX — deepen the **one** active pillar to authority depth; others stay maintenance until Step 10  
5. **GSC Sunday review:** position/CTR/impressions/tool clicks trends — not daily rank obsession *(hub + first 8 FAQs **shipped**; next FAQs from GSC + cluster map)*  
6. **Later:** next courier/fake-order cluster only after active pillar wins; then `planned_commercial` (OTP, blacklist, abandoned, AI order)  
7. **Always available:** 1 theme/day social **inside the active cluster** → live CTA; founder beat weekly

---

## Information architecture

```text
Money tools / pillars              Topic hubs                 Question URLs
─────────────────────              ──────────                 ─────────────
/bd-fraud-checker           →      /faq                →      /faq/{question}
/fake-order-protection      →      /faq  (sections)    →      inventory slugs only
/cod-return-reduction       →      fraud | fake-order | COD | courier | app
/courier-auto-entry         →
/steadfast-return-hub      →      SteadFast Return/Notifications/stuck
/woocommerce-facebook-messenger →  Page Messenger inbox (WP admin)
/return-loss-calculator     →      cross-link tools
blog posts                  →      same hubs (never orphan)
```

**Model:** competitor `service → /kb/{topic} → /knowledgebase/{q}`  
**Ours:** tool/pillar → `/faq` (+ sections) → `/faq/{question}`

**Internal linking rule:** every pillar → ≥2 tools + ≥2 blogs + 1 `/pricing` CTA. No orphan blogs. Prefer linking *to* `/bd-fraud-checker` (not only homepage) from all fraud-cluster pages.

### Orphaned sitemap pages (Semrush / Ahrefs)

**Definition:** URL is in `/sitemap.xml` but no other crawled page links to it with a real `<a href>`.

**WooEasyLife gotcha:** Marketing paths are covered by `SeoPrerenderText::sitemapNavLinks()` (blade `#seo-prerender` + MarketingLayout footer “Site pages”). **Blog posts are appended to the same sitemap in `SitemapController`**, so they must also appear in `sitemapNavLinks()` via `blogSitemapNavLinks()` — Vue-only `/blog` cards are not enough for Semrush if the crawler under-counts Inertia links.

**When Semrush flags orphans:**

1. Confirm the URL is in `/sitemap.xml` and returns 200 (or 301 to a live URL).
2. Prefer **link it** from sitewide nav (`sitemapNavLinks` / footer) and/or relevant pillar/FAQ/blog body with a **human label** (never bare `/blog/slug` as visible text in BN marketing copy).
3. Remove from sitemap only if the page should not be indexed (draft, noindex, junk).
4. Never invent a second duplicate blog landing to “fix” an orphan.
5. After deploy: `optimize:clear` + Semrush re-crawl of `/` and `/blog`.

**Code source of truth:** `App\Support\SeoPrerenderText::sitemapNavLinks()` · `App\Http\Controllers\App\SitemapController` · test `test_home_prerender_links_every_sitemap_blog_post`.

### Content not optimized (Semrush AI Search)

Semrush documents this as **three checks**: poor heading hierarchy, paragraphs that are too long, and low readability — especially on long pages. Our Site Audit often runs with **JS rendering disabled**, so it scores `#seo-prerender` HTML only (not Vue).

**Fix pattern (do not invent a second landing):**

1. Keep one H1; section titles H2; FAQ questions **H3** under an H2 FAQ label (never every FAQ as H2).
2. Long pillar “অংশ N/৩০” chapters → H3 under an H2 “গাইড পর্বসমূহ”.
3. Wrap long-form in `<article>` / `<section>`; keep lists for takeaways.
4. Split long FAQ/body sentences via `SeoPrerenderText::readableParagraphs()` in prerender.
5. Re-crawl after deploy; prefer enabling JS rendering in Semrush for Inertia sites when debugging.

**Content-type picker (one intent → one asset):**

| Intent shape | Asset | Inventory type |
|--------------|-------|----------------|
| Head commercial / free tool | Live money or tool page | `money` / `tool` / `pillar` |
| “What / when / how do I…?” seller question | FAQ URL under `/faq` | `planned_faq` |
| How-to / case / formula story | Blog | `planned_blog` |
| Distinct Shipped feature with its own demand | Feature landing | `planned_commercial` |
| Synonym of existing head term | Expand existing URL | — (no new row) |

---

## Courier pillar authority playbook (90-day one-topic rule)

**Doctrine:** One Topic → One Authority → One Category → Then Expand.

Most sites chase 100 keywords and rank for none. WooEasyLife should **own every important search related to the active courier pillar first**. Once Google treats that URL cluster as the authority resource, expanding into related courier fraud / COD verification topics becomes easier.

**Active campaign (default until Step 10 wins):** SteadFast Fraud Check  
**Pillar URL:** `https://app.wpsalehub.com/steadfast-fraud-check`  
**Free-tool CTA (always):** `https://app.wpsalehub.com/bd-fraud-checker`  
**Expand order after win:** Pathao Fraud Check → Fake Order Check → Fake Customer Check → Fraud Checker BD (biggest long-term head term on `/bd-fraud-checker`)

**Reconcile with existing rules:**
- Inventory still wins on paths/keywords — add cluster supporting pages + FAQs as rows; do not invent money-page duplicates.
- “Do not dump 50+ thin FAQs” still stands. Cluster FAQs are **deep** (600–1000 words), merchant language, inventory-backed — the opposite of eCourier-style stubs.
- Money URL `/bd-fraud-checker` stays the free-tool destination; the SteadFast pillar owns the **courier-intent** SERP and feeds the tool.
- Honesty / Shipped-only claims still apply.

### Step 1 — Create the pillar page

Not 800 words. Not 1200 words. Write the **best article available in Bangladesh** for that courier intent.

| Target | Spec |
|--------|------|
| Length | **3000–5000 words** |
| Tone | Merchant language (BD COD sellers) — not AI fluff |
| Assets | Screenshots, real examples, workflow |
| Language | BN primary body; EN title/H1 support OK |

**Required structure (SteadFast example — adapt per courier):**

1. **H1** — SteadFast Fraud Check (বাংলা) · Complete Guide for WooCommerce Merchants  
2. **Section 1** — What is SteadFast Fraud Check?  
3. **Section 2** — Why should merchants check before shipping?  
4. **Section 3** — Common causes of return  
5. **Section 4** — How customer history helps  
6. **Section 5** — How to use WooEasyLife (**Shipped** only)  
7. **Section 6** — FAQ (on-page; deep answers also get own URLs in Step 3)  
8. **Section 7** — Common mistakes  
9. **Section 8** — Summary  
10. **Section 9** — Free Tool → labeled CTA to `/bd-fraud-checker`

Pillar must also pass **Step 7 landing checklist** (TOC, images, schema, author, last updated, related).

### Step 2 — Build a topic cluster

Do **not** write another random article. Every supporting page must strengthen the pillar.

```text
SteadFast Fraud Check  (/steadfast-fraud-check)
        ↓
Supporting pages (each links back to pillar + to siblings)
        ↓
What is Customer History?
What is Delivery Ratio?
How to reduce returns?
When should you verify?
SteadFast FAQ (hub slice or pillar FAQ block)
Case Study
Common mistakes
```

| Rule | Detail |
|------|--------|
| Back-link | Every supporting page → pillar |
| Cross-link | Supporting pages ↔ each other where natural |
| Soft-link money | Never steal `fraud checker bd` head term onto a blog; CTA to `/bd-fraud-checker` with human label |
| Inventory | Each new URL = row (`planned_blog` / `planned_faq` / expand existing pillar) |

### Step 3 — Build FAQ pages (depth, not volume theater)

This is where most people fail. Instead of one thin FAQ block, create a **cluster FAQ set** (~15–20 intents, not 100 stubs).

**Each FAQ:** 600–1000 words · direct answer first · BD COD math · WooEasyLife how-to (Shipped) · related links · `FAQPage` schema.

**Example intents (map to inventory slugs — do not invent duplicates of live `/faq/*`):**

- Can I identify fake orders?  
- What is delivery history?  
- What is success ratio?  
- Can phone confirmation guarantee delivery?  
- How many deliveries are considered good?  
- Should I verify every customer?  
- What should I do with low success ratio?  
- What if there is no history?  
- What is courier history?  
- Does WooEasyLife predict fraud? → **Honest no / decision-support only**

Prefer extending live `/faq/{slug}` when intent already exists; add new inventory rows only for distinct SteadFast-angled questions.

### Step 4 — YouTube strategy (same topic everywhere)

**Status (2026-07-30):** **Skipped for now** — do not block Steps 5–9 or cluster lock on video. When ready: publish Long + Shorts, set `video_youtube_id` on `steadfast_fraud_check`, re-enable Week 4 record days.

Google should see the brand on web + video for the **same** cluster.

**Long videos:** Complete Guide · Customer History Explained · Success Ratio Explained · Real Demo · Common Mistakes · Case Study · FAQ roundup.

**Shorts:** ~30 Shorts — **not random**. Every Short answers **one** cluster question (30–40s). Examples: “Can phone confirmation prevent returns?” · “What does Success Ratio mean?” · “Should you ship without history?”

Embed the matching long video on the pillar (Step 7 `#video` slot). Social daily plan CTAs stay on `app.wpsalehub.com/...` live paths. YouTube + Google + Facebook reinforce the same SteadFast entity.

### Step 5 — Internal linking (closed loop)

Extremely important. Every page should naturally point to the others:

```text
Guide → FAQ → Video* → Tool → Case Study → Calculator → Guide
```

\*Video node optional while Step 4 is deferred — web loop must still close without it.

Minimum on pillar: ≥2 tools + ≥2 blogs/FAQs + 1 `/pricing` + free checker. No orphan cluster URLs (also satisfy **Orphaned sitemap pages**). Blog bodies: human-labeled `<a href>` only (no bare paths).

### Step 6 — Content calendar (cluster lock weeks)

**Wrong:** Monday SteadFast · Tuesday Messenger · Wednesday AI · Thursday Pricing.

**Right (while campaign is active):** 4 weeks locked on **one** cluster. Source of truth:

`config/seo_authority_calendar.php` · resolver `App\Support\SeoAuthorityCalendar::resolve($date)`

| Week | Focus | Example CTAs |
|------|--------|----------------|
| 1 | Pillar + tool awareness | `/steadfast-fraud-check`, history/ratio blogs, `/bd-fraud-checker` |
| 2 | FAQ depth + mistakes + honesty | Step 3 FAQs, common-mistakes blog |
| 3 | Case + return math + Return Hub | case study, `/return-loss-calculator`, return FAQs |
| 4 | Loop reinforce + YouTube scripts | FAQ index, Long/Shorts outlines, soft `/fake-customer-check` |

**Lock window (current):** `lock_start_date` = **2026-07-27** (Mon) → **4 weeks** through **2026-08-23**. After that: SteadFast **maintenance** until Step 10 win — do **not** auto-start Pathao.

Daily mentor plan still = 1 theme/day, but the theme comes from the calendar day (`theme`, `cta`, `checklist`, `short_hook`). Feature-launch exceptions only if Shipped **and** SteadFast/fraud-cluster adjacent.

**Today's plan agent rule:** if calendar `active`, call `SeoAuthorityCalendar::resolve()` (Asia/Dhaka) and build the day plan from `day` — ignore the generic Mon–Sun rotate table in Phase 3.

Most people will not have the patience — that is why they never become the authority.

### Step 7 — Landing page optimization

The pillar (and major supporting pages) should contain:

- Table of contents  
- Images + screenshots  
- FAQ schema  
- Video embed  
- Internal links (cluster loop)  
- External references where appropriate (courier help docs — not hate)  
- Author (Muhibbullah / WooEasyLife)  
- Last updated  
- Related articles  

Google loves pages that feel **complete**. Keep SSR-critical copy in first HTML (Inertia prerender rules still apply).

**SteadFast pillar checklist (shipped 2026-07-30):** `/steadfast-fraud-check` has TOC (`#guide-section-N` ↔ ItemList), figures, FAQPage + Article (`datePublished`/`dateModified`), author + `last_updated_label`, honesty banner, `external_links` (steadfast.com.bd pricing/terms/contact), cluster related pills, and `#video` slot. **Step 4 skipped for now** — leave `video_youtube_id` null until YouTube ships.

### Step 8 — Build trust signals

Do not only talk about WooEasyLife. Add:

- Real screenshots · real workflow · real order examples  
- Common mistakes  
- Things the tool **cannot** do · limitations · decision tips  

**Mandatory honesty line (use in pillar + FAQs that ask “is it fake?”):**

> This tool helps you make a better-informed decision. It does not guarantee that an order is fake or genuine.

That kind of honesty increases credibility. Aligns with `/fake-customer-check` “history ratio, not fraud verdict” angle. Never fake AggregateRating / “No.1”.

**SteadFast trust checklist (shipped 2026-07-30):** pillar `#trust` block via `trust_signals` (3 anonymized order examples, cannot-do list, decision tips, mistakes → common-mistakes blog); honesty banner on pillar + `/bd-fraud-checker` + KeywordIntent when `honesty_line` set; FAQ answers for predict / “১০০% ফেক চিনা” carry the EN honesty line; long-form “বাস্তব অর্ডার উদাহরণ” + COD/auto-entry figures; no AggregateRating in JSON-LD (asserted in MarketingSeoTest).

### Step 9 — Measure weekly (Sunday)

| Metric | Goal |
|--------|------|
| Position | 10 → 8 → 6 → 4 (trend) |
| CTR | Increasing |
| Impressions | Increasing every week |
| Tool clicks | Increasing (`/bd-fraud-checker` + pillar CTA) |
| Average engagement | Increasing |
| Internal link clicks | Increasing |

Do **not** obsess over rankings daily. Watch trends. Feed winners into next FAQ; kill/merge 0-impression stubs after ~4 weeks (Phase 5).

**Shipped playbook (2026-07-30):**

- Config: `config/seo_authority_metrics.php` (tracked paths, query needles, goals, Sunday SOP, action rules)
- Helper: `App\Support\SeoAuthorityMetrics` → `sundayChecklist()` / `reportMarkdown()`
- Command: `php artisan seo:weekly-report` appends Step 9 cluster section (GSC DB pages/queries when synced)
- Schedule: Sundays **09:00 Asia/Dhaka** (`Kernel`)
- Mentor Sundays: `SeoAuthorityCalendar::resolve()` merges Step 9 checklist into `day.checklist` + `metrics_checklist`

Log template fields live in the metrics config (`log_template`). Compare week-over-week — never invent fake metrics.

### Step 10 — Expand only after winning

Only after **SteadFast Fraud Check** consistently ranks in the top results **and** drives meaningful traffic, move to the next cluster:

1. Pathao Fraud Check (`/pathao-fraud-check`)  
2. Fake Order Check / protection cluster  
3. Fake Customer Check (`/fake-customer-check`)  
4. Fraud Checker BD (`/bd-fraud-checker` — biggest long-term keyword; keep deepening, don’t abandon)

Until then: maintenance-only on non-active pillars (fix broken links, schema, SSR) — no parallel “own Pathao this week” campaigns.

### 90-day “authority done” (active cluster)

- Pillar at 3000–5000w, merchant-grade, screenshots, TOC, video, author, last updated  
- Supporting cluster pages all link ↔ pillar  
- ~15–20 deep FAQs (or live `/faq/*` expanded) — not thin stubs  
- YouTube long + Shorts mapped 1:1 to cluster questions  
- Closed-loop internal links; no sitemap orphans in cluster  
- Sunday GSC trends up on pillar + tool clicks  
- Honesty / limitations copy live  
- Only then unlock next courier/category cluster  

---

## Phased roadmap

### Phase 0 — KPI (1 day)

Pick primary 30-day KPI:

1. Organic clicks to fraud/COD cluster (GSC), **and/or**
2. Free tool usage on `/bd-fraud-checker`, **and/or**
3. Signups / pricing visits from organic

Default: **(1) + (2)**. Validate FAQ shortlist in GSC before writing volume.

### Phase 1 — Strengthen existing (1–2 weeks) — **mandatory while `/faq` is 404**

| Priority | URL | Action |
|----------|-----|--------|
| **P0** | `/bd-fraud-checker` | Deepen FAQs — SSR form, Result CTAs, HowTo **shipped** (no WebApplication / fake AggregateRating) |
| P0 | `/fake-customer-check` | “History ratio, not fraud verdict” honesty angle |
| P0 | `/cod-return-reduction` | Attempt/return math → calculators |
| P1 | `/pathao-fraud-check`, `/steadfast-fraud-check`, `/redx-fraud-check` | Courier-specific FAQs — long-form content **shipped**; keep deepening |
| P1 | `/blog/blacklist-customer-after-returns` | **Already live (200)** — link from fraud pillars (blocker vs courier “we won’t block”); refresh if thin |

**Done when:** clear H1, FAQ schema intact, checker usable without JS-only shell, ≥3 links to pricing or free checker.

### Phase 2 — FAQ layer (2–3 weeks) — **only after Phase 1 P0**

**A — Hub:** `/faq` (BN first); `/en/faq` later if needed.

Hub sections (product topics, not courier ops):

1. Fraud / customer check  
2. Fake order / OTP / blacklist  
3. COD return & loss  
4. Courier auto-entry & status  
5. App / multi-store (light — pair, push, call ID; no empty modules)

**B — First 8 question URLs only** (not 132).

**Do not invent slugs here.** Canonical rows:

```php
app(\App\Services\Seo\SeoKeywordInventory::class)->plannedFaqs();
```

Current intents (paths/CTA from inventory): success-rate reading, low-success decision, COD OTP timing, customer blacklist, duplicate COD block, delivery-history check, fraud-score meaning, return-loss math.

**Question page format (beat thin competitor FAQs):**

1. Direct answer (2–4 sentences — snippet bait, human prose)  
2. Why it matters in BD COD (৳ math)  
3. How WooEasyLife does it (**Shipped** only)  
4. Mini SOP as a **vertical `list`** (3–5 steps) — never one mashed paragraph  
5. Related FAQs + tool CTA via `cluster_links` pills (not a raw path laundry list)  
6. `FAQPage` + `BreadcrumbList` schema  

**FAQ / marketing copy hygiene (mandatory — prevents “হোম” mid-word bugs):**

| Do | Don't |
|----|--------|
| Use `বনাম` · `ও` · `,` between alternatives | Use bare `/` as a word separator (`ক্যানসেল/রিটার্ন`, `হলুদ/লালে`, `কল/OTP`) — linkify turns `/` into **হোম** |
| Put paths only where a human label exists in `SeoPrerenderText::PATH_LABELS` **and** `resources/js/utils/internalPathLinks.js` `LABELS` | Dump raw slugs as visible text (`রেট কম: /faq/...`) |
| Accordion answers: 2–4 full sentences + one labeled path CTA | Telegram stubs (`না। রেট = … লিঙ্ক: /faq/...`) |
| Add every new `/faq/...` path to **both** PHP + JS label maps before publish | Ship a FAQ path with label only on one side (Vue shows raw slug) |
| Related nav = `cluster_links` with Bangla labels | Duplicate related section that only lists paths |

**Blog `body_html` hygiene (CKEditor — mandatory):**

Blog posts are **final HTML**. `SeoPrerenderText` / `internalPathLinks.js` linkify **does not run** on blog bodies. Readers see whatever you type.

| Do | Don't |
|----|--------|
| Every internal URL as `<a href="/path">মানুষের ভাষায় লেবেল</a>` | Bare paths as visible text (`শুরু: /bd-fraud-checker, সেটআপ: /pricing`) |
| Bangla (or clear EN) anchor text: `ফ্রি কুরিয়ার ফ্রড চেকার`, `প্রাইসিং`, `FAQ হাব` | Anchor text that is only the slug (`/fake-order-protection`) |
| Soft-link money pages with labels; keep post ≥ publish word gate with real sections | Pad with repeated slug laundry lists or duplicate AI fluff |
| Before handoff: Ctrl+F for `href="/` and confirm each has a human label between tags; Ctrl+F for ` /` + path and remove bare leftovers | Assume “path in prose will become a nice link later” — it will not |

Example — wrong: `শুরু: /bd-fraud-checker, প্ল্যান: /pricing`
Example — right: `শুরু করতে দেখুন <a href="/bd-fraud-checker">ফ্রি কুরিয়ার ফ্রড চেকার</a>, প্ল্যান: <a href="/pricing">প্রাইসিং</a>`

Add `/faq` + question URLs to sitemap with `lastmod`. Link hub from footer + fraud pillars. When live, flip inventory `status` to `live` and update **Current state**.

### Phase 3 — Content calendar (ongoing)

**If an authority cluster is active** → **Step 6** + `config/seo_authority_calendar.php` (resolver: `SeoAuthorityCalendar::resolve`). Ignore the rotate-by-channel table below until Step 10.

**If no cluster lock** → one theme/day (SEO mentor daily plan). Example week:

| Day | Theme | Asset |
|-----|-------|-------|
| Mon | Fraud check | → `/bd-fraud-checker` |
| Tue | Blacklist / OTP | FAQ or blog |
| Wed | Return loss math | Calculator |
| Thu | Courier auto-entry | `/courier-auto-entry` |
| Fri | Founder Muhibbullah | LinkedIn + `/about` |
| Sat | Competitor-intent (active courier pillar) | Deepen that pillar only |
| Sun | GSC review + 1 FAQ publish or refresh | Single URL + Step 9 metrics |

Rotate in **Feature launch** or **App** (multi-store / call ID) when a scan finds something new and Shipped — unless it breaks an active cluster lock.

### Phase 4 — Technical hygiene (parallel)

- Title, meta, canonical, OG, FAQ schema on every new FAQ  
- No `-2` duplicate slugs  
- GSC URL Inspection after publish  
- Keep sitemap clean (no logout/dashboard junk)  
- **Every sitemap URL must have ≥1 internal `<a href>`** (marketing + blog via `sitemapNavLinks`) — Semrush “Orphaned sitemap pages”  
- SSR critical interactive UI on money tool pages  
- Real hreflang (bn-BD / en / x-default → correct URLs — never all three to one URL)
- Blog posts calling `forPage('blog_index', …)` must pass explicit `content_sections` / `hreflang_paths` (empty `[]` is fine) — never inherit BN hub long-form onto EN posts (Semrush “hreflang language mismatch”)

### Phase 5 — Day-30 review

| Keep | Kill / merge |
|------|----------------|
| Impressions + CTR | 0 impressions after ~4 weeks |
| Used tools | Thin Qs that only restate a pillar |
| Blacklist / fraud angles | Courier payment-schedule style content |

Next 8 FAQs **only** from GSC query gaps — not from any competitor’s full KB list.

---

## Keyword map (one URL per intent)

**Source of truth:** `config/seo_keyword_inventory.php`  
Do not maintain a second conflicting slug list in this skill.

**Rule:** synonyms of a head term = ONE page (expand it). New URL only for a distinct question or distinct commercial feature. No impressions after 4–8 weeks → improve, merge, or remove (`status=hold` if paused).

Quick reference (edit inventory, not this table):

| URL | Primary | Type |
|-----|---------|------|
| `/bd-fraud-checker` | fraud checker bd | live money |
| `/fake-customer-check` | fake customer check bd | live pillar |
| `/bd-courier-ratio-checker` | courier ratio checker | live tool |
| `/pathao-fraud-check` etc. | {courier} fraud check | live pillar |
| `/fake-order-protection` | woocommerce fake order protection | live money |
| `/courier-auto-entry` | woocommerce courier integration bangladesh | live money |
| `/faq` + `/faq/*` (8) | long-tail FAQ intents | live FAQ |
| `/blog/*` planned topics | long-tail howto/case | planned_blog |
| feature landings | OTP / blacklist / abandoned / AI order | planned_commercial |

**Clusters in inventory:** `fraud_checker`, `fake_order`, `checkout_protection`, `return_loss`, `cod`, `courier`, `courier_charge`, `facebook_ads`, `missing_order`, `ai_orders`.

---

## Inventory row schema (when adding)

Every new entry needs:

```php
[
    'cluster' => 'fraud_checker',           // existing cluster or justified new
    'type' => 'planned_faq',              // money|tool|pillar|planned_faq|planned_blog|planned_commercial
    'status' => 'planned',                // live|planned|hold
    'path' => '/faq/...',
    'slug' => '...',
    'primary' => '…',                     // one focus phrase
    'secondary' => ['…'],                 // synonyms / BN variants — do NOT become new URLs
    'cta' => '/bd-fraud-checker',         // live path only
    'notes' => 'Shipped: …; why this URL',
    // optional for blogs:
    // 'article_type' => 'howto'|'case_study'|'listicle',
]
```

Before insert: confirm Shipped in `FEATURES.md`, BD demand (Suggest `gl=bd` and/or GSC), and no clash with `reservedHeadTermsForCluster($cluster)`.

---

## Feature discovery → SEO opportunity loop

**Product source paths (verified):**

| Surface | Path |
|---------|------|
| WP plugin | `/Users/muhibbullah/Desktop/wp/wordpress/wp-content/plugins/woo-easy-life` |
| Flutter app | `/Users/muhibbullah/Desktop/App/WooEasyLife` |
| Backend (this repo) | package/gating keys, routes, `config/seo*` |

**Run when:** user says “new feature / feature update / shipped / update features”, a release goes out, OR no scan in ~30 days.

### Step 1 — Scan for what changed

| Target | Where | Why |
|--------|-------|-----|
| Version | `woo-easy-life.php` header, `readme.json` | Detect release since `FEATURES.md` “Last analyzed” |
| Changelog / sections | `readme.json`, `README.md` | Merchant-visible wording |
| Gating keys | `init/InitClass.php`, `helpers/helpers.php`, `api/Admin/*` | A key = sellable capability = possible landing |
| Admin UI | `vue-project/src/pages`, `components`, `router` | Nav = shipped vs coming-soon |
| Checkout/protection | `frontend/` (`CheckoutFormValidation`, `StoreApiCheckoutProtection`, `IP_block`, `CookieOrderLimiter`, `OrderBlockForBlockedUser`) | Highest-intent SEO cluster |
| Courier | courier services/webhooks | Per-courier long-tails |
| App version | `pubspec.yaml` (`version:`) | Detect app release |
| App features | `lib/features/` (`fraud_check`, `orders`, `stores`, `missing_orders`, `customer_notices`, `company_employees`, `dashboard`, …) | App-only / multistore angles |
| App routes | `lib/app/router` | Merchant-visible vs internal |
| App empty modules | e.g. `app_lock` | Keep in Do NOT claim |

### Step 2 — Classify

| Finding | Action |
|---------|--------|
| Merchant-visible + shipped | Add to `FEATURES.md` **Shipped**; consider SEO asset |
| Behind a package key | Shipped but note gating; never imply free |
| Nav present, no implementation | Keep in **Do NOT claim** |
| Internal/admin only | No SEO page |

### Step 3 — SEO decision → inventory row

| Question | Yes → |
|----------|-------|
| Existing money page already owns this intent? | Expand that page — no new URL |
| Distinct feature + search demand? | `planned_commercial` |
| Seller question? | `planned_faq` |
| Angle/story only? | `planned_blog` |

Write the row (schema above). Blog AI picks it up via seeds / topic picker / LP guard / internal links — no PHP service change needed.

### Step 4 — Validate

1. Really Shipped (code, not nav label).  
2. BD demand: Google Suggest `gl=bd` and/or GSC.  
3. No cannibalization via `reservedHeadTermsForCluster()`.  
4. Next daily mentor theme = this feature.

### Anti-patterns

No landing for: config flags, admin-only tools, Coming-soon nav, empty Flutter modules (`app_lock`), or zero BD demand. Never claim gated features as free. Never over-claim Paperfly auto-entry.

---

## Competitor re-audit protocol

**When:** user pastes rival URL(s), a new domain enters top 3 for `fraud checker bd`, or ~60 days since last fraud SERP audit.

**Fetch:** title, meta, canonical, H1, word count, visible courier names, FAQ count, JSON-LD types, whether checker input is in initial HTML, sitemap/hreflang, thin/AdSense tells.

**Output:** Strength · Weakness · Steal · Never copy → update **Extended rival roster** and **Gap scorecard** (remove closed gaps; add new ones).  
**Never:** competitor hate, fake #1, fake AggregateRating.

---

## Competitor reference (eCourier) — lessons only

| Signal | Their gap | Our edge |
|--------|-----------|----------|
| Titles | Empty/`Page \|` sitewide | Keyword + brand titles |
| Meta / OG | Often missing | Present |
| Schema | Often none | FAQPage, Product, Org, Person, Breadcrumb |
| hreflang | Missing | bn-BD / en / x-default |
| KB volume | ~132 thin Qs | Prefer depth + product fit |
| Structure | `/kb/` + `/knowledgebase/` | Copy IA; not their questions |

**Winnable intents:** fraud alert / customer check, blacklist, COD fake orders, return loss math, courier auto-entry & status sync.  
**Skip intents:** their payment calendar, warehouse insurance, BOD booking, fragile compensation policy.

---

## Fraud checker SERP rivals (audited)

**Query focus:** `fraud checker` / `fraud checker bd` / `courier fraud checker` / `ফ্রড চেকার`  
**Our money URL:** `https://app.wpsalehub.com/bd-fraud-checker`  
**Rule:** steal tactics — never competitor hate, never fake “No.1”, never invent features.

### Rival A — Elite Mart tool page

- **URL:** `https://elitemart.com.bd/fraud-check`
- **Why it ranks:** Exact title `Free Courier Fraud Checker BD by Elite Mart`; interactive phone checker on the ranking URL; `WebApplication` + `FAQPage` schema (3 BN FAQs; answers cite SteadFast/Pathao/RedX/PaperFly); marketplace domain traffic/trust.
- **Weaknesses:** **No H1**; ~366 words; marketplace clutter; courier names weak in visible body; duplicate sibling `/fraud-check-2`; OG image on `fraudchecker.afridihassan.com` (shared ecosystem with Rival B).
- **Steal:** Title lead `Free Courier Fraud Checker BD`; checker above the fold; 3–6 BN FAQs (how it works / free? / low success rate?).

### Rival B — FraudChecker.link homepage

- **URL:** `https://fraudchecker.link/`
- **Why it ranks:** Exact-match domain; title `No.1 Free Courier Fraud Checking System in Bangladesh`; BN benefit H1 + EN SEO title; long sales page (~1280 words); FAQ + HowTo + Service schema; free search → WP plugin → paid API.
- **Weaknesses:** No homepage canonical; `/sitemap.xml` 404; free tool `/free-fraud-checker-bd` **canonicalizes to homepage**; courier names weak in body; unverifiable “No.1”; template leftover blog H3s.
- **Steal:** BN hero promise (fake order → lower return); clear free-search CTA; schema FAQ naming couriers — **not** No.1 or thin free-page canonical tricks.

### Us vs them (cheat sheet)

| Factor | Elite Mart | FraudChecker.link | `/bd-fraud-checker` |
|--------|------------|-------------------|---------------------|
| Title match | Excellent | Strong | Excellent |
| H1 | Missing | Benefit BN | Keyword + BN |
| Depth | Thin | Sales-long | Guide + tool |
| Visible courier names | Weak | Weak | Strong |
| FAQ schema | Yes | Yes | Yes |
| Tool on ranking URL | Yes | Split (home vs free) | Yes |
| Topical purity | Poor | Medium | Strong |

**Gap to close is usually not meta** — it is SERP association, checker-first UX (SSR), FAQ cluster, and links into `/bd-fraud-checker` from `/fake-customer-check`, `/pathao-fraud-check`, `/steadfast-fraud-check`, `/redx-fraud-check`, `/fake-order-protection`.

### Extended rival roster (same cluster)

| Rival | URL | Strength | Weakness / don't copy |
|-------|-----|----------|-----------------------|
| Gamitisa | `gamitisa.com/tools/courier-fraud-checker` | Exact-path keyword, clean H1, SoftwareApplication+Breadcrumb, ~700 words, couriers named | No FAQPage; tools-directory site |
| FraudShield.bd | `fraudshield.bd/` | Rich schema (WebApplication, FAQPage, HowTo…), BN title/meta, Laravel/Inertia like us | Thin Inertia body; broken same-URL hreflang; **fake AggregateRating 4.8/250 — never copy** |
| FraudPeek | `fraudpeek.com/check` | Dedicated `/check` tool URL, real hreflang, checker-first, 5 couriers | No JSON-LD; ~400 words thin |
| eFraudChecker | `efraudchecker.com` | Brand-name match only | Client shell ≈ empty crawl; AdSense-first. **Not a real SEO threat** |

**Also in market (validate topics, not head-term rivals):** CheckoutGuard (WP), Lekhito, BD Commerce delivery-fraud-checker, Bangla Track, official Pathao WooCommerce plugin.

**Steal:** FraudPeek dedicated tool URL + real hreflang; FraudShield BN FAQ intents (mobile check / prevent fraud / fake customer / WP use); Gamitisa plain `Courier Fraud Checker` H1.  
**Never:** fake AggregateRating, "No.1", broken hreflang, AdSense-thin shells.

### Gap scorecard — `/bd-fraud-checker`

Already **wins** on: title, H1, FAQ schema (~8 Qs), visible courier names, ~900-word **guide body** in HTML, hreflang, internal links to pillars, **SSR phone form** (`<form>`/`<input type="tel">`/`<button>` in `#seo-prerender`).  

| Priority | Gap | Fix |
|----------|-----|-----|
| ~~P0~~ | ~~Result → next action weak~~ | **Shipped:** post-check CTAs → protection, return-loss, guide, trial |
| ~~P1~~ | ~~Missing `HowTo`~~ | **Shipped** (3 steps). WebApplication removed — Semrush flags Software App without real AggregateRating; never invent ratings |
| P1 | FAQ can add rival intents | +Qs: mobile-number check, blacklist/fake-customer, WordPress/Woo use (Shipped only) → FAQ ≥10 |
| P2 | "blacklist" weak in body | Add blacklist + WooCommerce protection story in body, not only sidebar |
| P2 | No exact-match domain vs fraudchecker.link | Brand cluster + external links to **tool URL** (not home) |
| P3 | Authority gap | BD FB seller groups, blog embeds, partner links to tool URL; weekly mentor posts |

**Moat line:** rivals = `number → history → (pay API / leave)`; us = `number → history → OTP / blacklist / fake-order protection → less return loss`.

---

## Skill self-maintenance

Update this skill — not only `FEATURES.md` — when:

| Trigger | Update |
|---------|--------|
| New feature shipped | Feature loop + inventory rows + **Current state** versions |
| Rival enters top 3 / audit run | Roster + Gap scorecard |
| Our page changes (e.g. tool goes SSR) | **Remove closed gaps** from scorecard |
| GSC winners/losers | Phase 1 priorities + Phase 5 + Step 9 |
| `/faq` hub ships | Current state → **live** (2026-07-26); first 8 FAQs live; next batch from GSC only |
| Active cluster changes / Step 10 win | Update **Active campaign** pillar URL + expand-order pointer + `seo_authority_calendar.php` |
| Cluster lock calendar shipped / dates shift | `config/seo_authority_calendar.php` + Step 6 lock window + Current state |
| Pillar hits 3k–5k authority draft shipped | Note in Current state; close thin-alias gap for that courier |
| Domain/brand change | Canonical CTA lines |
| This review | Bump **Last reviewed** date |

**Tie-breaker:** inventory wins on paths/keywords · `FEATURES.md` wins on claims · this file wins on strategy/priority. Remove stale gaps; do not accumulate permanent audit history.

---

## 60-day “done” definition

- `/faq` live and linked sitewide  
- ~8–16 strong question URLs sitewide (plus deeper cluster FAQs under **authority playbook** when campaign active — still not 100 thin)  
- `/bd-fraud-checker` checker SSR + result CTAs  
- Fraud/COD cluster tied to free tools  
- Organic → tools/FAQs → `/pricing`  
- No courier-support topical pollution  
- Mentor still 1 theme/day; CTAs on `app.wpsalehub.com` only  
- `FEATURES.md` + inventory still in sync after ≥1 feature scan  
- If SteadFast campaign active: progress against **90-day authority done** (pillar depth, cluster links, Sunday metrics) — not abandoned for Messenger/AI theme-hops
