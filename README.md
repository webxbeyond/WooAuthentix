# WooAuthentix: Product Code Verification

A WooCommerce extension for generating, assigning, and verifying unique product authenticity codes with privacy, security, and logging features.

## Key Features
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

## Status & Pool Lifecycle
Codes can exist either as product-specific or in the generic pool (product_id NULL) until assignment.
1. Unassigned (0): Generated (product-specific or generic) and waiting to be attached to an order line item.
2. Assigned (1): Reserved for a specific order/item (generic codes are tagged with product_id at this moment atomically).
3. Verified (2): First successful verification occurred (transition handled on first check or automatically in preprinted mode).

Assignment Order:
1. Product-specific unassigned codes (status=0, matching product_id)
2. Generic unassigned codes (status=0, product_id IS NULL) are atomically updated to adopt the product_id
3. (Fallback) On-demand generation (generic first, then product-specific) if inventory exhausted

## Shortcode
Place the shortcode on a page:
```
[wc_authentic_checker]
```
Users can enter a 12-character hex code to verify.

## REST API
Endpoint: `POST /wp-json/wooauthentix/v1/verify`
Payload: `{ "code": "ABCDEF123456" }`
Response:
```
{
  "success": true,
  "first_time": true,
  "product": "Sample Product",
  "verified_at": "2025-08-10 12:34:56",
  "html": "<p>...</p>"
}
```
HTTP 400 on errors.

## Settings
WooCommerce > WooAuthentix Settings
- Show Buyer Name (with masking option)
- Show Purchase Date
- Enable Rate Limiting (configurable max + window seconds)
- Enable Logging & retention in days
- Code Length (hex) adjustable

## Logs Page
WooCommerce > Verification Logs lets you filter by code and result type, paginate, and export the current page to CSV.

## Logging Table
`{prefix}wc_authentic_logs` stores attempts with minimal data (code, result, ip, user agent, timestamp). Periodic pruning via daily WP-Cron.

## Security Considerations
- Codes are random 12-char uppercase hex (≈ 48 bits). Increase length if needed by editing generator.
- Rate limiting mitigates brute force; consider enabling a WAF for additional protection.
- CSV export protected by nonce and capability check.
- Verification no longer auto-marks via GET without interaction.

## Upgrading From Older Version
Version 2.2.0 introduces a generic code pool. Migration automatically makes `product_id` nullable so existing product-tagged codes remain; new codes can omit product selection.
Activation / migration routine now:
- Ensures tables & indexes
- Makes `product_id` NULLable (>=2.2.0) to support generic pool
- Leaves existing codes untouched

## Extending
### Available Hooks
- `wooauthentix_code_generated` (string $code, int|null $product_id) — product_id null for generic codes
- `wooauthentix_batch_generated` (int|null $product_id, int $requested, array $codes)
- `wooauthentix_after_verification` (string $code, array $context) — context includes first_time, status, product_id, order_id, verified_at, context

## Development Notes
- Code generation uses batched multi-value INSERT for performance.
- Adjust length/pattern in `wc_apc_generate_batch_codes()`.
- Add translations under `languages/` folder.

## Installation
1. Upload the plugin folder to `wp-content/plugins/` or install via ZIP in the WordPress admin.
2. Activate the plugin. Ensure WooCommerce is active and minimum requirements (PHP 7.4+, WP 6.0+).
3. Visit WooCommerce > WooAuthentix Settings to adjust privacy, logging, rate limiting, code length, and notifications.
4. (Optional) Place the shortcode `[wc_authentic_checker]` on a public “Verify Authenticity” page.

## Contributing
Contributions are welcome! Please:
1. Fork the repository & create a feature branch.
2. Follow WordPress coding standards (PHPCS) where possible.
3. Keep changes focused; one feature/fix per pull request.
4. Add/update inline docblocks for new public functions or hooks.
5. Update the translation template if you add new strings (see Translating section).

### Development (Suggested Workflow)
```
git clone <repo-url>
cd wooauthentix
# (Optional) Run PHPCS if configured
```
Activate the plugin in a local WP environment (e.g., wp-env, Local, or Docker) and test code generation & verification flows.

## Translating
1. Use the provided POT file under `languages/wooauthentix.pot` as a base.
2. Generate/refresh POT (example using WP-CLI i18n tools):
```
wp i18n make-pot . languages/wooauthentix.pot --exclude=node_modules,vendor
```
3. Create `.po`/`.mo` pairs per locale (e.g. `languages/wooauthentix-fr_FR.po`).

## Security Policy
Please report vulnerabilities privately. See `SECURITY.md` for details.

## Roadmap (Indicative)
- Automated unit tests for generation & verification lifecycle
- PHPCS ruleset + CI
- Optional asynchronous bulk export jobs
- GUI warnings for low inventory with regenerate button
- Extended analytics / reporting widgets

## License
Released under the GPLv2 (or later). See `LICENSE` file for full text.

## Disclaimer
This plugin helps deter counterfeits but cannot guarantee full product authenticity without comprehensive supply chain controls.

## Additional Features (Extended)
- QR label sheet generation with PDF export (html2canvas + jsPDF)
- Branding controls: logo, brand text, visibility toggles (code, site host, brand, logo position)
- Label layout: columns (auto or fixed), margins, border thickness, center logo overlay with scale %
- Preprinted mode: unassigned codes auto-verify on first scan
- Concurrency-safe automatic & manual code assignment (atomic status transition with retry) including generic → product tagging
- Low-stock email notifications with threshold per product (generic pool excluded)
- HPOS compatibility declared via FeaturesUtil

## Uninstall Cleanup
To fully remove all plugin data (tables + options), set:
```
update_option('wooauthentix_full_uninstall', 1);
```
Then uninstall via WordPress Plugins screen.

## Testing & CI (Planned)
A forthcoming `.github/workflows/ci.yml` will run:
- PHP lint + PHPCS (WordPress-Core standard)
- PHPUnit integration tests (wp-env) covering:
  - Code generation uniqueness & length bounds
  - Concurrent assignment race safety
  - Verification transitions (unassigned / preprinted / assigned / verified)
  - Rate limiting counters & lockout
  - Privacy exporter / eraser behavior

## Maintainer Tools
Current Tools page actions:
- Recount status aggregates
- Audit orphaned & generic anomalies
- Repair status/is_used mismatches
- Force log pruning
- Flush rate limit transients (values + timeouts)
- Repair generic assignment anomalies (edge-case reclassification)

## WP-CLI
Generate product-specific codes:
`wp wooauthentix generate 123 100`

Generate generic (pool) codes:
`wp wooauthentix generate generic 500`

Export verified codes:
`wp wooauthentix export verified.csv --status=verified`

Summary report:
`wp wooauthentix report`

## Changelog
See CHANGELOG.md for detailed version history.
