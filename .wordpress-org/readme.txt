== WooAuthentix: Product Code Verification ==
Contributors: (add your wp.org username)
Tags: authenticity, woocommerce, verification, product codes, qr, rest-api, privacy
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 2.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WooCommerce extension for generating, assigning, and verifying unique product authenticity codes with privacy, security, and logging features.

== Description ==
Features:
- Bulk code generation per product, per category, OR generic pool (no product tag)
- Automatic code assignment on order completion (status lifecycle)
- First-time verification detection (assigned -> verified)
- Privacy controls (buyer name masking & purchase date toggle)
- REST API endpoint for remote/app integration
- Rate limiting & attempt logging (optional)
- Verification logs viewer & CSV export (page level)
- Configurable code length (8–32 hex chars, even values)
- CSV export with nonce protection
- Pagination & filtering in admin (Unassigned, Assigned, Verified, Logs)
- Internationalization ready (text domain: `wooauthentix`)

== Installation ==
1. Upload the plugin folder to `wp-content/plugins/` or install via ZIP in the WordPress admin.
2. Activate the plugin. Ensure WooCommerce is active and minimum requirements (PHP 7.4+, WP 6.0+).
3. Visit WooCommerce > WooAuthentix Settings to adjust privacy, logging, rate limiting, code length, and notifications.
4. (Optional) Place the shortcode `[wc_authentic_checker]` on a public “Verify Authenticity” page.

== Changelog ==
# [2.3.0] - 2025-08-10
### Removed

# [2.2.1] - 2025-08-10
### Added
