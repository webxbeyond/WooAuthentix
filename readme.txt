=== WooAuthentix: Product Code Verification ===
Contributors: anisafifi
Tags: woocommerce, authenticity, security, qr code, verification
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate, assign, and verify unique product authenticity codes for WooCommerce with logging, rate limiting, QR label sheets, and a generic code pool.

== Description ==
WooAuthentix adds a full authenticity code lifecycle to your WooCommerce store.

Key features:
* Bulk generation (product-specific or generic pool)
* Automatic assignment at order processing/completion
* First-time verification detection
* Rate limiting + logging with retention pruning
* Privacy controls (buyer masking, date toggle, optional IP hashing)
* REST API endpoint for mobile / external integrations
* QR label sheet generation (download/print)
* Preprinted mode (auto-verify on first scan)
* Pagination, filters, CSV exports

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/ or install the ZIP.
2. Activate the plugin (requires WooCommerce).
3. Configure under WooCommerce > WooAuthentix Settings.
4. Add shortcode [wc_authentic_checker] to a public "Verify" page.

== Frequently Asked Questions ==
=== Does it work with HPOS? ===
Yes, HPOS compatibility is declared.

=== Can I have a generic pool of codes? ===
Yes. Generate without selecting a product and they will be assigned on demand.

=== Is there a REST API? ===
POST /wp-json/wooauthentix/v1/verify with {"code":"HEXCODE"}.

== Screenshots ==
1. Codes table with filters
2. Verification form
3. QR label sheet

== Changelog ==
= 2.3.0 =
* Removed legacy order item meta migration code (cleanup after 2.2.x).
* Internal: version bump & housekeeping.

= 2.2.1 =
* Performance: Added index on qr_label_generated.
* Accessibility: Added aria-live region to verification form.
* CI: Enforced PHPCS and introduced lifecycle test scaffold.

= 2.2.0 =
* Added QR label generation tracking.
* Introduced generic code pool.

== Upgrade Notice ==
= 2.2.1 = Minor performance & accessibility improvements. Update recommended.
