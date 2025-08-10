# Contributing to WooAuthentix

Thanks for your interest in contributing!

## Ways to Help
- Bug reports / issues
- Feature requests (with clear use cases)
- Pull requests (code, docs, tests, translations)
- Security disclosures (see SECURITY.md)

## Pull Request Guidelines
1. Fork and create a feature branch: `feature/short-descriptor`.
2. Keep PRs focused; unrelated changes should be separate.
3. Follow WordPress coding standards (spacing, naming, escaping APIs).
4. Add/update inline docblocks for new public functions or hooks.
5. Sanitize and escape all input/output.
6. Run basic manual tests: activation, code generation, verification, REST endpoint.
7. Update README / POT if user-visible strings or features change.

## Commit Messages
Use conventional style when possible:
- feat: add low stock email alert
- fix: sanitize code length option properly
- docs: update README with translation instructions
- chore: initial PHPCS ruleset

## Translations
Update POT after adding/changing translatable strings:
```
wp i18n make-pot . languages/wooauthentix.pot --exclude=node_modules,vendor
```

## Coding Style
- Escape output: `esc_html`, `esc_attr`, `wp_kses` where needed.
- Nonces for state‑changing actions.
- Prepared statements for all dynamic SQL.
- Use filters/actions for extensibility.

## Questions?
Open an issue with the label `question`.
