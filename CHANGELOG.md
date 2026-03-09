# Changelog

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.4] — 2026-03-08

### Fixed
- **Badge spacing in mobile dropdown** — tab name now returns a plain `Responsivas <badge>` string instead of a wrapping `<span>` element. The mobile dropdown in GLPI renders raw HTML differently from the desktop sidebar, causing "Responsivas3" without space. The fix is consistent with how GLPI core renders tab counts.
- **`setup.php` default logo path** — corrected `pics/logo.png` → `logo.png` (at plugin root). On fresh installs the logo was never copied to GLPI's files directory because the source path did not exist.
- **`pluginDir()` no longer throws** — `PluginResponsivasPaths::pluginDir()` now falls back to `dirname(__DIR__)` instead of throwing `RuntimeException` when the plugin is not found in `GLPI_PLUGINS_DIRECTORIES`, preventing unhandled exceptions from reaching users.
- **CRLF line endings** — `front/resource.send.php` and `front/config.form.php` had Windows CRLF line terminators; converted to LF for consistency with the rest of the plugin and Linux servers.
- **Missing `'responsivas'` i18n domain** — all `__()` and `_n()` calls across every PHP file now include the plugin domain. Affected files: `setup.php`, `inc/config.class.php`, `inc/pdfbuilder.class.php`, `inc/helpers.php`, `inc/pdf.class.php`, `inc/user.class.php`, `front/computer.php`, `front/printer.php`, `front/phone.php`, `front/send_mail.php`. Without the domain, strings fell through to GLPI's built-in locale and were not translatable via the plugin's own `.po` files.

### Changed
- **`getCounts()` GLPI-compliant queries** — replaced direct `$DB->query()` SQL with 3 `$DB->request()` calls as required by GLPI's query policy (`Executing direct queries is not allowed`). The per-request cache remains in place.
- **PDF button loading feedback** — clicking a Computadoras / Impresoras / Teléfonos button now immediately replaces the button icon with a spinner and disables the button for ~5 seconds, providing visual feedback while the PDF is generated in the new tab. Spinner is implemented via a `data-resp-pdf-btn` attribute and a JS event listener, avoiding PHP string escaping issues.
- **Syntax error in `user.class.php`** — the previous spinner implementation embedded a JS `onclick` attribute inside a double-quoted PHP string with conflicting escape sequences, causing a fatal parse error on the user tab. Fixed by moving the JS blocks to PHP/HTML interleaving (`?>...<?php`) which eliminates all string escaping issues.
- **Syntax error in `helpers.php`** — the clickable variable tags implementation had `"` inside a double-quoted PHP echo string without proper escaping, causing a fatal parse error on the configuration page. Fixed by switching all `echo` calls to single-quoted strings and using `?>...<?php` for the inline JS block.
- **Removed debug mode banner** — the `if (glpi_use_mode & 2)` block in `config.class.php` that displayed a GLPI debug warning is removed. GLPI already shows its own debug indicator globally; the plugin-level duplicate was redundant and added unnecessary strings to the locale files.

### Added
- **Clickable variable tags in configuration** — all `{variable}` tags in every template hints panel are now clickable buttons. Clicking a tag while a textarea is focused inserts it at the cursor position. If no textarea is focused, the tag is copied to the clipboard. Consistent with the behavior in the Email Signatures plugin.

### Locales
- Added 2 new translatable strings: `'Abrir ficha del activo en GLPI'` and `'No tienes permiso para generar responsivas de este usuario.'`.
- Total: **167 strings** across es_MX, en_US, fr_FR, de_DE.

---

## [1.2.3] — 2026-03-07

### Added
- **Plugin icon for GLPI Marketplace** — added `logo.png` (128×128 px) at the plugin root. GLPI reads the icon from this location to display it in Setup → Plugins and the Marketplace browser.

### Changed
- `responsivas.xml` updated to reference version `1.2.3` and the correct GitHub release download URL.
- **README consolidated** — merged `README.md` (English) and `README.es.md` (Spanish) into a single `README.md` file (English first, Spanish below). `README.es.md` removed.
- **File structure section added to README** — reflects actual plugin layout including `front/`, `inc/`, `locales/`, and root files.
- **`misc/` directory removed** — placeholder screenshots folder and its contents are no longer included in the package.

---

## [1.2.2] — 2025-03-08

### Added
- **Template validation before PDF generation** — `validateTemplates()` is called at the start of every `buildComputerPdf`, `buildPrinterPdf`, and `buildPhonePdf`. If any required field is empty, the operation is aborted and an error message lists exactly which fields need to be filled in Configuration.
- **Schema-versioned configuration system** — introduced `plugin_responsivas_getSchemaFields()` which documents all 31 configuration fields with `type`, `since` (schema version when introduced), `group` (logical grouping), and `migrate` strategy (`reset` or `keep`). The new `plugin_responsivas_migrateConfig()` function centralizes all migration logic. Future field changes only require editing the schema and incrementing `PLUGIN_RESPONSIVAS_SCHEMA_VERSION`.

### Changed
- `plugin_responsivas_getDefaults()` is now a thin wrapper that extracts default values from the schema definition.
- `plugin_responsivas_install()` and `plugin_responsivas_update()` now delegate entirely to `migrateConfig()`.
- Replaced the legacy `template_version` migration key with the formal `config_schema_version` field.

### Locales
- Added 11 new translatable strings for template field labels used in validation error messages.
- Total: **166 strings** across es_MX, en_US, fr_FR, de_DE.

---

## [1.2.1] — 2025-03-07

### Fixed
- **CSRF token conflict after test email** — The test email button was a `fetch()` call that consumed the page CSRF token. Saving the form after sending a test email would fail with `AccessDeniedHttpException`. The button is now a fully independent HTML `<form>` with its own token, rendered outside the main configuration form to avoid HTML form nesting (browsers silently discard nested forms).
- **Nested form bug** — The main configuration form had no closing `</form>` tag, causing the test email form to be nested inside it. The browser discarded the inner form silently, making the button appear to do nothing. Both issues are now resolved.
- **`send_test_mail.php` syntax error** — An unclosed PHP string in the `$footer_safe` block caused a parse error on line 72.
- **`config.class.php` syntax error** — Unescaped double quotes inside a `querySelector` call within an `echo "..."` PHP string caused a parse error on line 944.

### Changed
- Test email result now appears as a standard GLPI flash message (green/red alert) after redirect, instead of an inline JavaScript span. Consistent with GLPI's native UI patterns.

---

## [1.2.0] — 2025-03-06

### Fixed
- **Bold text not rendering in PDFs** — Root cause: GLPI 10/11 `Sanitizer` converts `<strong>` and all HTML tags in POST data to HTML entities before the plugin receives them, making it impossible to store HTML via textarea config fields.
  - **Solution:** Switched to `**text**` Markdown-style syntax. `responsivasRenderTemplate()` and `responsivasApplyTemplate()` now convert `**text**` → `<strong>text</strong>` via `preg_replace` at render time only.
  - All default templates updated to use `**texto**` syntax.
  - Template editor display converts stored `<strong>` → `**text**` before showing in textarea.
  - `template_version` bumped to `'3'` to trigger automatic reset of templates on install/update.
- **Plugin uninstall did not clean the database** — `hook.php` now runs `$DB->delete('glpi_configs', ['context' => 'plugin_responsivas'])` on uninstall, ensuring a completely clean reinstall.
- **Install kept old incompatible templates** — `plugin_responsivas_install()` used `array_merge($defaults, $existing)` where existing values always won, preventing migration from applying. Fixed: schema migration check now runs before `array_merge` and force-resets template fields when `template_version !== '3'`.

### Added
- Bold support in email body and footer fields using `**text**` syntax (escape → bold conversion → `nl2br` pipeline).

---

## [1.1.0] — 2025-03-04

### Added
- **Email Signatures integration** — option to include user's GLPI profile photo and WhatsApp QR code in email signature.
- **Variable hints panel** — vertical list of available template variables shown next to each textarea, with description and bold usage note.
- CSRF protection on all POST endpoints.
- Proper file validation on logo upload.

### Changed
- Config UI redesigned to match GLPI 11 visual standards (Bootstrap 5 tabs, cards, icons).
- Variable hints layout changed from inline overflow to vertical list with icons.

---

## [1.0.0] — 2025-02-20

### Added
- Initial release.
- PDF generation for computers (carta responsiva), printers (carta responsiva), and phones (contrato de comodato).
- Email delivery via GLPI's native `GLPIMailer`.
- Fully customizable templates stored in `glpi_configs` under the `plugin_responsivas` namespace.
- QR codes on PDFs linking to the asset in GLPI.
- Legal witness and representative configuration.
- Auto-generated useful life clause for phone contracts.
- Asset summary table (brand, model, serial, asset tag, condition, specs).
- Footer with left/right text and document reference code.
- Multi-language support: Spanish (Mexico), English, French, German.
- Logo upload for PDF header.
- Font size configuration per asset type.
- Employee number display toggle.
- QR code display toggle.

---

[1.2.4]: ../../compare/v1.2.3...v1.2.4
[1.2.3]: ../../compare/v1.2.2...v1.2.3
[1.2.2]: ../../compare/v1.2.1...v1.2.2
[1.2.1]: ../../compare/v1.2.0...v1.2.1
[1.2.0]: ../../compare/v1.1.0...v1.2.0
[1.1.0]: ../../compare/v1.0.0...v1.1.0
[1.0.0]: ../../releases/tag/v1.0.0
