# Changelog
All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and adheres to Semantic Versioning where practical.

# [2.3.0] - 2025-08-10
### Removed
- Legacy order item meta key `_authentic_code` migration routine (was executed in <=2.2.x; safe to drop).

### Changed
- Version bump; cleanup of deprecated compatibility layer code.

# [2.2.1] - 2025-08-10
### Added
- Index on qr_label_generated for faster label filtering.
- Aria-live region for verification form accessibility.
- Lifecycle PHPUnit test scaffold.

### Changed
- Enforce PHPCS in CI & release workflow.
- Synchronized Stable tag with version constant automation.

### Security
- Minor hardening groundwork (preparing for IP hashing toggle enforcement).

# [2.2.0] - 2025-08-10
### Added
- Generic code pool (codes can be generated without product/category and tagged at assignment time).
- Admin Codes page updated (optional product/category; generic generation option).
- Tools page operational actions (recount, audit, repair, prune logs, flush rate limits).
- API key Generate/Clear buttons (secure random hex) in settings.
 - WP-CLI generic pool support (use keyword `generic` for generation).
 - Tools action to repair generic assignment anomalies.

### Changed
- Assignment logic: product-specific first, then generic pool atomic claim, then on-demand generation.
- Migration makes `product_id` nullable (schema version 2.1.0) for generic pool support.
- README updated with pool lifecycle & hook parameter nullability.
 - CI workflow ordering (start DB before installing WP tests).
 - Rate limit flush now also clears timeout transients.

### Fixed
- Audit now distinguishes orphaned order references vs untagged assigned/verified codes.

### Notes
- Generic codes excluded from low-stock per-product notifications (only tagged/unassigned product-specific monitored).

## [2.1.0] - 2025-08-10
### Added
- Modular architecture (codes, verification, admin pages, labels, privacy, CLI, REST placeholder).
- Concurrency-safe manual & automatic assignment logic.
- HPOS compatibility declaration.
- Activation routine with table creation & indexes.
- Uninstall conditional cleanup script.
- QR label generation page with PDF export & dynamic layout.
- Branding and visibility settings for labels.
- Center logo overlay options & border controls.
- Rate limiting + hashing option for IP addresses.
- Low-stock notification emails.
- Privacy exporter and eraser integration.
- README / SECURITY / CONTRIBUTING / CODE_OF_CONDUCT docs.

### Changed
- Admin UI consolidated under single WooAuthentix top-level menu with tabbed settings.
- Code generation deduplicated & moved into dedicated module.
- Verification and logging consolidated; improved shortcode.

### Removed
- Legacy inline admin page functions from main plugin file.

### Pending / Roadmap
- Automated test suite & CI workflow.
- Admin Tools maintenance page.
- Advanced REST endpoints & capability hardening.

## [2.0.0] - 2025-07-??
- Internal refactors (historical; details condensed).

## [1.x] - 2024-2025
- Initial releases (legacy structure, basic generation & shortcode verification).
