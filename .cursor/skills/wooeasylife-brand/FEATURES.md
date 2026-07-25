# WooEasyLife — Living Feature Inventory

**Last analyzed:** 2026-07-23  
**Sources (re-scan when features change):**
- Plugin: `/Users/muhibbullah/Desktop/wp/wordpress/wp-content/plugins/woo-easy-life` (v1.5.3)
- App: `/Users/muhibbullah/Desktop/App/WooEasyLife` (Flutter v1.1.6+)
- Backend packages: `WooEasyLife_RemoteBackend` / WPSaleHub gating keys

**Rule:** Marketing, SEO mentor posts, and creatives may only claim features listed here as **Shipped**. Never claim **Coming soon** as live.

---

## Hero claims (safe for ads / social)

1. BD **fraud / courier history check** (phone) before confirm  
2. **Fake-order protection:** OTP, duplicate block, blacklist (phone/email/IP/device), daily limits, BD-IP option  
3. **Courier auto-entry + status sync:** Pathao, Steadfast, RedX (+ webhooks)  
4. **Missing / abandoned order recovery** → create WooCommerce order  
5. **AI order from image/text** + address Fix with AI (OpenAI)  
6. **Mobile app:** multi-store, push, call ID / inbound match, approve on the go  
7. **SMS** status + bulk; **Meta Pixel + CAPI**  
8. **Team / employees** + performance; invoices / POS stickers (package-gated)

**Founder personal brand:** Muhibbullah · `dev.muhibbullah@gmail.com` · WooEasyLife  

**Domains:** canonical public site `https://app.wpsalehub.com` only (never wooeasylife.com in CTAs)  
**Founder About (photos + entity):** `/about` · `/en/about` · LinkedIn `https://www.linkedin.com/in/dev-muhib`

---

## Plugin (WordPress) — shipped modules

### Orders
Enhanced order list · details modal · bulk/quick status · custom COD statuses · manual create · clone (gated) · invoice codes · custom fields → courier/invoice · address completeness + Fix with AI · status history · click-to-call + call log · label/POS sticker print (gated) · repeat-customer signals

### Fraud / protection
Phone fraud checker (gated) · delivery history on order · customer behavior / fraud score · blacklist CRUD + CSV · daily order limit · duplicate same-cart block · checkout validation · checkout OTP · IP/phone/email/device block · BD-IP-only · Store API parity · Fake Order status

### Courier
Steadfast · Pathao · RedX — send, bulk entry, refresh, webhooks · inline consignment edit · Steadfast parcel notes history (newer) · Steadfast ask-to-return · RedX in-app track · Pathao AI location enrich · rider-callback push (Steadfast, newer)

### AI / automation
AI Order from Image · AI Order from Text · address enrich · status SMS · bulk SMS + recharge · new-order sound/toast · Missing Orders / abandoned cart (gated) · Meta Pixel + CAPI (+ CartFlows hooks)

### Mobile link
Connect App QR · FCM push · store profile sync · caller ID → order (with app)

### Team / billing / analytics
License · subscription/tokens · employees + performance report · dashboard (status, courier perf, recovery, sales, team, etc.)

### Admin tools
DB migration · indexing · backup/snapshots · status logs · dark mode · URL replacer · remote license update

### Package keys (gating)
`fake_order_protection` · `fraud_customer_checker` · `customer_delivery_history` · `customer_behavior` · `customer_blacklist` · `courier_automation` · `parcel_note_history` · `missing_orders` · `order_cloning` · `label_and_pos_sticker_print` · `custom_status_management` · `employee_management` · `app_connect`

---

## App (Flutter) — shipped / unique

### Unique vs WP browser
QR multi-store pairing · Common Dashboard across stores · cross-store same-phone banner + push · native Android call tracking (outbound log + inbound match) · FCM away-from-desk · on-device PDF label/invoice print · camera QR + image AI extract · coach marks

### Mirrors plugin (mobile)
Orders inbox · DSP / stuck / follow-up filters · create/clone · AI fill · fraud check · blacklist · missing orders · courier assign/refresh (Steadfast/RedX/Pathao) · Steadfast notes · SMS · employees · Meta Pixel settings · security toggles · analytics

### Couriers in app settings
Steadfast · RedX · Pathao · **Paperfly** (settings list; live assign/refresh evidenced for Steadfast/RedX/Pathao — do not over-claim Paperfly auto-entry until confirmed)

---

## Do NOT claim (yet)

- **Meta AI Bot** — Coming soon in plugin nav  
- AI call-summary as a product feature (config key only)  
- Extra unnamed couriers beyond Steadfast / Pathao / RedX (Paperfly = settings-only until assign shipped)  
- iOS as primary ship target  
- App lock / PIN (empty module)  
- Shopper-facing storefront app  

---

## How to update this file (Muhibbullah)

When you say **“new feature”**, **“feature update”**, or ship a release:

1. Re-scan plugin + app paths above (README, changelog, Vue/Flutter feature folders, package keys).  
2. Update this file: add under the right group; mark **Newer** if just shipped; move out of Do NOT claim.  
3. Update hero claims only if the feature is merchant-visible and package-safe.  
4. Next **আজকের প্ল্যান** may use the new feature as the social theme.  
5. Add/update rows in `config/seo_keyword_inventory.php` (FAQ / blog / commercial) when the feature creates a new search intent — see seo-mentor skill **Feature discovery loop**.
