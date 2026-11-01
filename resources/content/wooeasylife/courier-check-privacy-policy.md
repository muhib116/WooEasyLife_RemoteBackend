# Privacy Policy — Courier Check

**Effective date:** 1 November 2026  
**Last updated:** 1 November 2026

## 1. Who we are

Courier Check is a free WordPress plugin published by WPSaleHub. This policy covers the plugin and the check service at `https://app.wpsalehub.com`. It does not replace the privacy policy of your WooCommerce store or of the courier companies whose delivery history is shown.

**Contact:** dev.muhibbullah@gmail.com  
**Website:** [WPSaleHub](https://app.wpsalehub.com)

## 2. What the plugin sends

When you check a customer from the WooCommerce order screen, the plugin sends:

- the customer billing phone number
- your site URL
- the site key this service gave your site when the plugin was activated

The plugin does not send the customer’s name, address, email, or the order total for this check.

## 3. Why it is sent

The phone number is used to read courier delivery history through the same check the public checker on this website uses. The result is a count of delivered and returned parcels and a risk label: low, medium, high, or unknown.

The site URL and site key are used to apply the free daily allowance to that site, and to the site’s domain if a new key is created for the same domain.

## 4. What is stored

The plugin site table stores the site key, the site’s domain (the hostname only), the time the key was created, and the time the key was revoked if we revoke it. It does not store the phone number.

The daily allowance is a counter of site key, domain, date, and count. It is kept until the next midnight in Asia/Dhaka, then it expires. The counter does not include the phone number.

The history lookup is the same one the public website checker uses. That lookup can keep courier delivery counts for the normalized phone number so the same number does not have to be fetched from every courier on every later check. The site key is not stored on that history.

We do not sell this information.

## 5. How long it is kept

- Daily counts expire at the next midnight in Asia/Dhaka.
- The site key and domain stay until the key is revoked or the site record is deleted.
- Courier delivery counts kept by the shared history lookup follow the retention of that existing website feature.

## 6. Who can see it

The check result is returned to the WordPress site that sent the request. We do not publish the phone number. Courier companies receive the number only as part of the same delivery-history request the public checker already makes.

## 7. Your responsibilities

You need a lawful reason to send your customer’s billing phone to this service. Your store’s own privacy notice should tell customers that you check courier delivery history.

## 8. Contact

Questions about this policy: [dev.muhibbullah@gmail.com](mailto:dev.muhibbullah@gmail.com).

The terms for this plugin are on the [Courier Check terms of service](/wooeasylife/courier-check/terms-of-service).
