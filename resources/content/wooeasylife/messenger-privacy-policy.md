# Privacy Policy — Woo Easy Life Messenger (Facebook App)

**Effective date:** July 27, 2026  
**Last updated:** July 27, 2026

## 1. Introduction

This Privacy Policy describes how **WPSaleHub** (“**we**”, “**us**”, or “**our**”) processes information when merchants use the **Woo Easy Life** WordPress plugin and related WPSaleHub services to connect a **Facebook Page** to Messenger inbox features (the “**Service**”).

This policy applies to our Facebook / Meta application that uses the **Messenger Platform** so merchants can receive and send Page messages, manage conversations, and (if enabled) use automated sales assistance.

By connecting a Facebook Page or interacting with a Page that uses the Service, you acknowledge this Privacy Policy.

For the WooEasyLife Android mobile app, see the separate [mobile app Privacy Policy](https://app.wpsalehub.com/wooeasylife/app/privacy-policy).

## 2. Who we are / contact

**Service provider / platform operator:**  
WPSaleHub  
Website: [https://app.wpsalehub.com](https://app.wpsalehub.com)  
Privacy / data requests: [dev.muhibbullah@gmail.com](mailto:dev.muhibbullah@gmail.com)

**Important roles**

- **Merchant (store owner):** Typically the **data controller** for customer Messenger conversations and any order/customer data collected through chat.
- **WPSaleHub:** Provides the hub, Facebook connection, webhook forwarding, and plugin features as a **service provider / processor** for merchants, and as controller for account/connection data needed to operate the platform.

## 3. What this Facebook app does

The app allows an authorized merchant to:

- Connect their Facebook Page to Woo Easy Life
- Receive Messenger webhook events (messages, delivery/read signals, reactions, and related Page messaging events)
- View and reply to conversations in the Woo Easy Life Messenger inbox
- Optionally import recent conversation history from Facebook Graph
- Optionally use AI-assisted replies / lead capture inside the merchant’s store settings
- Create or update WooCommerce-related order workflows from conversation data when the merchant uses those features

## 4. Information we process (Platform Data)

Depending on features used and Facebook permissions granted, we may process:

### 4.1 Page / merchant connection data

- Facebook Page ID and Page name
- Page access tokens and related OAuth connection metadata
- Merchant website / license identifiers needed to route events to the correct store
- Connection timestamps and status (connected / disconnected)

### 4.2 Messenger conversation data

- Messenger **PSID** (Page-scoped user ID)
- Message content (text)
- Message metadata (message IDs, timestamps, reply-to references, reactions)
- Attachments (images, audio, video, files) and attachment URLs/media processed for inbox display
- Optional sender profile fields available via Graph (for example name and profile picture), when retrieved for inbox display

### 4.3 Data customers may voluntarily send in chat

Customers may provide information in messages, such as:

- Name, phone number, delivery address
- Product interest, quantity, and order details

That information is processed to help the merchant respond and fulfill orders. Merchants should only request information needed for their business.

### 4.4 Technical / security data

- Webhook signatures / request validation data
- Logs needed for delivery reliability, abuse prevention, and troubleshooting
- IP addresses and basic request metadata for security and operations

We do **not** sell personal information. We do **not** use Messenger Platform Data to build advertising profiles for unrelated third parties.

## 5. How we use the information

We process the information above to:

1. Authenticate and maintain Facebook Page connections
2. Deliver inbound/outbound Messenger events between Meta and the merchant’s Woo Easy Life store
3. Display conversations in the merchant inbox and support replies, typing indicators, reactions, and media
4. Import conversation history when requested/queued after connect
5. Power optional AI sales-agent features configured by the merchant
6. Support order assistance / lead capture for the merchant’s WooCommerce store
7. Provide customer support, security monitoring, debugging, and abuse prevention
8. Comply with law and Meta Platform Terms

## 6. Legal bases (where applicable)

Where GDPR/UK GDPR or similar laws apply, processing is typically based on:

- **Contract / legitimate interest:** operating the Service for merchants
- **Merchant instructions:** processing customer chat data on behalf of the merchant
- **Consent:** where required for specific optional features or local law
- **Legal obligation:** when we must retain or disclose information

## 7. Where data is stored

- **Merchant WordPress site:** Conversation copies, contact rows, inbox state, and related store data are primarily stored in the merchant’s Woo Easy Life plugin database on the merchant’s hosting.
- **WPSaleHub servers (`app.wpsalehub.com` and related infrastructure):** Page connection records, tokens, routing metadata, and temporary processing needed to forward webhooks / Graph requests.
- **Meta / Facebook:** Original Messenger messages remain subject to Meta’s own terms and privacy policy.

## 8. How we share information

We may share information only as needed to operate the Service:

- **With Meta/Facebook:** to send/receive Messenger messages and manage Page connections using Meta APIs
- **With the merchant’s connected WordPress store:** to show inbox data and perform merchant-configured actions
- **With infrastructure providers:** hosting, logging, and security vendors under appropriate safeguards
- **When legally required:** court orders, lawful requests, or to protect rights/safety

We do **not** sell Messenger Platform Data. We do **not** transfer Platform Data to unrelated advertisers.

## 9. Data retention

- **Page connection / token data:** retained while the Page remains connected and for a limited period afterward for security, dispute, and audit needs, then deleted or de-identified.
- **Conversation data on the merchant store:** retained according to the merchant’s WordPress settings and business needs. Merchants can delete conversations/messages from the Woo Easy Life inbox (local copy). Deleting from Woo Easy Life does not always delete the original message from Facebook.
- **Operational logs:** retained for a limited period for reliability and security, then deleted or anonymized.

## 10. How to request data deletion

<a id="data-deletion"></a>

### A) Facebook / Messenger end users (customers who messaged a Page)

If you messaged a Facebook Page that uses Woo Easy Life and want your chat data deleted:

1. Contact the **Page / store** you messaged and ask them to delete your conversation from their Woo Easy Life inbox and any related store records; **and/or**
2. Email us at [dev.muhibbullah@gmail.com](mailto:dev.muhibbullah@gmail.com) with:
   - the Facebook Page name (or Page URL),
   - approximate date of the conversation,
   - and any details that help identify the thread (for example your Messenger display name).

We will:

- delete or ask the merchant to delete conversation data under our control, and
- delete related connection/processing records where applicable,

within a reasonable period (generally within **30 days**), unless we must retain limited information for legal, security, or abuse-prevention reasons.

Note: We cannot delete messages solely stored in your personal Facebook/Messenger account; use Facebook’s own settings for that.

### B) Merchants (Page admins)

You can:

- Disconnect the Facebook Page from Woo Easy Life settings
- Delete conversations/messages from the Woo Easy Life Messenger inbox
- Email [dev.muhibbullah@gmail.com](mailto:dev.muhibbullah@gmail.com) to request deletion of hub-side Page connection data associated with your account/site

### C) Meta App Dashboard

You may use this page URL (including the `#data-deletion` section) as the **User Data Deletion Instructions URL** in the Meta App Dashboard.

## 11. Merchant responsibilities

Merchants using the Service must:

- Use Messenger data only to support legitimate customer service / commerce for their Page
- Provide their own store privacy disclosures where required
- Not use the Service to spam, scrape, or unlawfully process personal data
- Honor customer deletion/access requests for data stored in their WordPress site

## 12. Security

We use reasonable technical and organizational measures, including HTTPS, webhook signature verification, access controls, and least-privilege handling of Page tokens. No method of transmission or storage is fully secure.

## 13. Children’s privacy

The Service is for business/commerce use and is not directed to children under 13 (or the minimum age required in your jurisdiction). We do not knowingly collect children’s data through this Facebook app.

## 14. International transfers

Data may be processed in countries where we, our merchants, or our providers operate. Where required, we use appropriate safeguards for cross-border transfers.

## 15. Changes

We may update this Privacy Policy from time to time. The “Last updated” date will change when we do. Continued use of the Service after an update means the updated policy applies.

## 16. Contact

Questions, privacy requests, or deletion requests:  
**Email:** [dev.muhibbullah@gmail.com](mailto:dev.muhibbullah@gmail.com)  
**Website:** [https://app.wpsalehub.com](https://app.wpsalehub.com)
