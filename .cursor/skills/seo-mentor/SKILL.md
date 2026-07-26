---
name: seo-mentor
description: >
  WooEasyLife SEO mentor strategy, content IA, FAQ roadmap, and competitive
  playbook (eCourier hubs; fraud-checker SERP rivals Elite Mart and
  FraudChecker.link). Use for today's plan, SEO next steps, FAQ architecture,
  sitemap/internal links, GSC-driven content, fraud checker ranking tactics,
  competitor audits, and organic growth vs BD courier/COD competitors. Also use
  when a new plugin or app feature ships, to scan the product and turn it into
  keyword/FAQ/landing rows in config/seo_keyword_inventory.php. Pair with
  wooeasylife-brand and FEATURES.md. Canonical domain: app.wpsalehub.com.
---

# WooEasyLife SEO Mentor Skill

**Last reviewed:** 2026-07-26
**Rule (daily plan format):** `.cursor/rules/seo-mentor.mdc`  
**Brand + creatives:** `.cursor/skills/wooeasylife-brand/SKILL.md`  
**Features (claims only if Shipped):** `.cursor/skills/wooeasylife-brand/FEATURES.md`  
**Keyword / slug inventory (wins on conflicts):** `config/seo_keyword_inventory.php` · `App\Services\Seo\SeoKeywordInventory`  
**Canonical CTAs:** `https://app.wpsalehub.com/...` only — never wooeasylife.com

When planning FAQs, blogs, or commercial pages: **read the inventory first**. Do not invent duplicate slugs for money-page head terms. Blog AI already merges this inventory into seed queries, research, topic picker, and LP head-term guards.

---

## Agent routing (read first)

| User asks… | Do this |
|------------|---------|
| আজকের প্ল্যান / today's plan | Follow `.cursor/rules/seo-mentor.mdc` format; CTAs only from **live** inventory paths |
| next steps / SEO plan / organic roadmap | Re-read this skill → give **Default priority stack** below (not a week dump) |
| FAQ / `/faq` | Phase 2 — but only after Phase 1 P0s; invent no slugs (use `plannedFaqs()`) |
| fraud checker rank / SERP | **Fraud checker SERP rivals** + Gap scorecard (SSR P0 first) |
| competitor audit + URL(s) | Run **Competitor re-audit protocol**; update roster if they enter top 3 |
| new feature / update features / shipped | **Feature discovery → SEO opportunity loop** |
| keywords / long-tails / new URLs | Inventory decision rule; write rows to inventory file |
| orphaned sitemap / Semrush crawlability orphans | **Orphaned sitemap pages** playbook below — fix links, never only remove from sitemap |
| review / upgrade this skill | **Skill self-maintenance** checklist |

---

## Current state (keep honest)

| Fact | Status (2026-07-26) |
|------|---------------------|
| Money URL | `/bd-fraud-checker` live (200) |
| FAQ hub | `/faq` **live** + 8 inventory question URLs |
| Inventory | live money/tool/pillar + FAQ hub · 8 live FAQ · 5 planned_blog · 8 planned_commercial |
| Product docs | Plugin v1.5.3 · App v1.1.6 · `FEATURES.md` last analyzed 2026-07-23 |

Update this table when `/faq` ships, major gaps close, or versions bump.

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

---

## Default priority stack (when unsure)

Do in order — stop when the day's budget is used:

1. **P0 — Deepen** `/bd-fraud-checker` + `/fake-customer-check` FAQs (BN) + keep FAQPage schema *(Result CTAs + SSR form + HowTo **shipped**; WebApplication removed — Semrush Software App invalid without real ratings)*  
2. **Internal links:** fraud ↔ COD ↔ calculators ↔ courier pillars ↔ `/faq` ↔ `/pricing`  
3. **Courier pillars:** Pathao / Steadfast / RedX FAQ blocks + links into money URL *(thin alias long-form **shipped**)*  
4. **GSC day-30:** keep/kill; next FAQs from real query gaps only *(hub + first 8 FAQs **shipped**)*  
5. **Later:** `planned_commercial` landings (OTP, blacklist, abandoned, AI order) — after fraud cluster is strong  
6. **Always available:** 1 theme/day social → live CTA; founder beat weekly

---

## Information architecture

```text
Money tools / pillars              Topic hubs                 Question URLs
─────────────────────              ──────────                 ─────────────
/bd-fraud-checker           →      /faq                →      /faq/{question}
/fake-order-protection      →      /faq  (sections)    →      inventory slugs only
/cod-return-reduction       →      fraud | fake-order | COD | courier | app
/courier-auto-entry         →
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

**Content-type picker (one intent → one asset):**

| Intent shape | Asset | Inventory type |
|--------------|-------|----------------|
| Head commercial / free tool | Live money or tool page | `money` / `tool` / `pillar` |
| “What / when / how do I…?” seller question | FAQ URL under `/faq` | `planned_faq` |
| How-to / case / formula story | Blog | `planned_blog` |
| Distinct Shipped feature with its own demand | Feature landing | `planned_commercial` |
| Synonym of existing head term | Expand existing URL | — (no new row) |

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

One theme/day (SEO mentor daily plan). Example week:

| Day | Theme | Asset |
|-----|-------|-------|
| Mon | Fraud check | → `/bd-fraud-checker` |
| Tue | Blacklist / OTP | FAQ or blog |
| Wed | Return loss math | Calculator |
| Thu | Courier auto-entry | `/courier-auto-entry` |
| Fri | Founder Muhibbullah | LinkedIn + `/about` |
| Sat | Competitor-intent (Pathao / Steadfast) | Existing pillar |
| Sun | 1 FAQ publish or refresh | Single URL |

Rotate in **Feature launch** or **App** (multi-store / call ID) when a scan finds something new and Shipped.

### Phase 4 — Technical hygiene (parallel)

- Title, meta, canonical, OG, FAQ schema on every new FAQ  
- No `-2` duplicate slugs  
- GSC URL Inspection after publish  
- Keep sitemap clean (no logout/dashboard junk)  
- **Every sitemap URL must have ≥1 internal `<a href>`** (marketing + blog via `sitemapNavLinks`) — Semrush “Orphaned sitemap pages”  
- SSR critical interactive UI on money tool pages  
- Real hreflang (bn-BD / en / x-default → correct URLs — never all three to one URL)

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
| GSC winners/losers | Phase 1 priorities + Phase 5 |
| `/faq` hub ships | Current state → **live** (2026-07-26); first 8 FAQs live; next batch from GSC only |
| Domain/brand change | Canonical CTA lines |
| This review | Bump **Last reviewed** date |

**Tie-breaker:** inventory wins on paths/keywords · `FEATURES.md` wins on claims · this file wins on strategy/priority. Remove stale gaps; do not accumulate permanent audit history.

---

## 60-day “done” definition

- `/faq` live and linked sitewide  
- ~8–16 strong question URLs (not 100 thin)  
- `/bd-fraud-checker` checker SSR + result CTAs  
- Fraud/COD cluster tied to free tools  
- Organic → tools/FAQs → `/pricing`  
- No courier-support topical pollution  
- Mentor still 1 theme/day; CTAs on `app.wpsalehub.com` only  
- `FEATURES.md` + inventory still in sync after ≥1 feature scan
