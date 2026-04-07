# Changelog — Responsivas

All notable changes to this project are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.3.2] — 2026-04-06

### Fixed
- **Hardcoded Spanish labels in PDF documents** — all table headers (`Brand`, `Model`, `Serial`, `Processor`, `Speed`, `RAM`, `OS`, `Storage`, `Type`, `Condition`, `Comments`, `Device`, `Serial / Asset`), signature labels (`LENDER`, `BORROWER`, `WITNESS`, `CLAUSES`), fallback values (`Not specified`, `No comments`, `N/A`, `In use`), and the employee number prefix (`Employee No.:`) were hardcoded in Spanish inside `pdfbuilder.class.php`. All moved to the translation system via a shared `lbl()` method and `__()` calls. Any language now gets fully translated PDF content.

### Added
- **Italian locale (it_IT)** — new `it_IT.po` / `it_IT.mo` with PDF labels translated. Other UI strings are present as empty msgstr (standard practice; gettext falls back to English msgid). Registered in `plugin.xml`.

### Changed
- **Plugin display name localized** — the name `Responsivas` was hardcoded in the tab, configuration page title, and all locale files. Now fully translated: `Responsibility Documents` (en_US/en_GB), `Documents de responsabilité` (fr_FR), `Verantwortungsdokumente` (de_DE), `Documenti di responsabilità` (it_IT), `Responsivas` (es_MX unchanged). The plugin identifier key remains `responsivas`.
- **Version removed from .po/.pot headers** — `Project-Id-Version` now reads `Responsivas` without a version number, following gettext convention (the version belongs in the changelog, not in locale file headers).

### Locales
- Added Italian (`it_IT`). Total locales: `es_MX`, `en_US`, `en_GB`, `fr_FR`, `de_DE`, `it_IT`.
- Added 19 new translated strings (PDF table and signature labels).
- Total: **212 strings** per locale.

---

## [1.3.1] — 2026-04-03

### Added
- **Selective document sending** — the "Send by email" modal now shows a checkbox for each asset type the user has assigned (Computers, Printers, Phones). Each checkbox displays the asset count and is checked by default. The user can uncheck any type to exclude it from the email. Attempting to confirm with no type selected shows an inline warning without closing the modal. Backwards-compatible: direct POST calls without type fields still send all available documents.

### Locales
- Added 3 new strings for the type-selection UI.
- Total: **193 strings** per locale.

---

## [1.3.0] — 2026-03-23

### Changed
- **English as base language** — the plugin now uses English as the fallback language for all translatable strings. Previously, Spanish (es_MX) was the base: any user without a supported locale would see Spanish. Now users with unsupported locales see English, which is the standard GLPI plugin convention.
- All 191 msgid strings in PHP source files changed from Spanish to English.
- **POT rebuilt** from scratch with English msgids.
- **es_MX** locale updated to full Spanish translations for all 191 strings (no functional change for Spanish users).
- **en_US / en_GB** locale files updated — msgstr left empty (gettext falls back to the English msgid automatically).
- **fr_FR / de_DE** translations remapped to the new English msgids; no content loss.
- **License updated to GPL v3+** — aligns with GLPI 11 which is distributed under GPL v3. All license references updated in `setup.php`, `plugin.xml`, `README.md`, and `LICENSE` file replaced with the full GPL v3 text.

### Fixed
- **PHP minimum version corrected to 8.2** — GLPI 11 requires PHP 8.2; the plugin incorrectly declared `minphpversion = 8.1`. Updated in `setup.php` and all locale error strings.
- **Syntax errors from apostrophes in string literals** — six PHP source strings containing unescaped apostrophes (`'`) inside single-quoted `__()` calls caused fatal parse errors. All occurrences replaced with apostrophe-free equivalents.

### Locales
- Base language: **English** (msgid = English text)
- Total: **191 strings** per locale

---

## [1.2.7] — 2026-03-22

### Fixed
- **Font size bounds** — `pc_font_size`, `pri_font_size` and `pho_font_size` are now clamped to `6–72 pt` on save. Previously a value of `0` could be stored and passed to TCPDF, causing a crash or blank PDF on the next generation.
- **Watermark text length** — enforced server-side 40-character limit with `mb_substr()`. The HTML `maxlength=40` attribute was the only guard before; a crafted POST could bypass it and overflow the watermark drawing area in TCPDF.
- **Timezone validation** — the timezone value is now validated against `DateTimeZone::listIdentifiers()` before being saved. An invalid value now falls back to `America/Hermosillo` instead of being stored and causing silent date errors on every document.
- **IDOR on send_mail.php** — added `$user->canView()` check after `getFromDB()`. GLPI evaluates entity-level permissions in `canView()`, preventing an authenticated technician from triggering a PDF send for a user in an entity they do not have access to.

---

## [1.2.6] — 2026-03-17

### Added
- **PDF preview with watermark** — each asset type tab (Computadoras, Impresoras, Teléfonos) has a "Vista previa" button at the bottom. Generates a full PDF using the current saved templates. If the logged-in admin has real assets of that type they are used; otherwise realistic demo data is substituted (Dell Latitude 5540 / HP LaserJet Pro M404n / Samsung Galaxy A54 5G). A diagonal "VISTA PREVIA" watermark appears on every page at 25% opacity. The demo uses real configured witnesses, representative, entity location, and a real GLPI state name; only asset-specific fields are demo values.
- **Compression and protection toggles** — two new switches in the "Opciones de la responsiva" card (General tab): **Comprimir PDF** and **Proteger PDF** (restrict copy/edit). Both active by default. Changes apply to all generated PDFs via the shared `makePdf()` factory.
- **Watermark customization** — two new fields below the compression/protection toggles: **Texto de marca de agua** (the diagonal text shown on previews, defaults to "VISTA PREVIA") and **Opacidad de marca de agua** (5–100%, defaults to 25%). Both values are stored in config and applied via `makePdf()` to all preview PDFs.
- **Editable useful-life clauses** — two new template editors at the bottom of the Teléfonos tab: **Cláusula de vida útil (con factura)** (used when the phone has an invoice and supplier; supports `{fecha_compra}`, `{factura}`, `{proveedor}`) and **Cláusula de vida útil (sin factura)** (used when no invoice data exists). Both pre-filled on install/update via `setup.php` with `migrate=keep`; never overwritten if customised.

### Changed
- **Shared PDF factory `makePdf()`** — all PDF creation (real and preview) now goes through a single private method. `SetTitle`, `SetSubject`, `SetKeywords`, `SetPDFVersion`, `SetProtection`, `SetCompression`, margins, fonts — all configured in one place.
- **Shared render methods** — `renderPcPage()`, `renderPriPage()`, `renderPhoPage()` hold the single source of HTML for each document type. Real builds and preview demo both call them; a layout change in any render method applies to both automatically.
- **Plugin title** — removed the word "plugin" from the configuration page heading. Now reads **"Configuración de Responsivas"** (es_MX), **"Responsivas Settings"** (en_US/en_GB), **"Configuration de Responsivas"** (fr_FR), **"Responsivas Einstellungen"** (de_DE).

### Locales
- Added 4 new strings: `Comprimir PDF`, `Comprimir el archivo PDF generado`, `Proteger PDF`, `Restringir copia y edición del PDF`.
- Updated title string across all 5 locales.
- Added 4 strings for watermark customization fields.
- Added 4 strings for editable useful-life clause editors.
- Total: **188 strings** per locale.

---

## [1.2.5] — 2026-03-13

### Added
- **Italic and underline formatting** — introduced `*cursiva*` (italic) and `__subrayado__` (underline) syntax alongside the existing `**negrita**`. Rendering applies in PDFs, PDF footer corner fields, and email body/footer. All three formats can be combined and nested (e.g. `*__**texto**__*`). Evaluation order is always `**` → `*` → `__` to avoid capture conflicts.
- **Format toolbar (B / I / U)** — toggle buttons appear above every editable text field in the configuration: email body/footer, all document template fields (title, intro, body, clauses), and all four PDF footer corner fields. Clicking a button wraps selected text with the appropriate markers, or inserts a placeholder. Clicking again on already-marked text removes the markers (toggle).
- **Clickable variable hints** — the hint panel now shows `**negrita**`, `*cursiva*`, `__subrayado__` inline and a "click to insert" instruction. All `{variable}` tags are clickable and insert at the cursor position in the last focused field.
- **Friendly sender name in outgoing mail** — `send_mail.php` reads `from_email_name` / `from_email` from `$CFG_GLPI` (falling back to `admin_email_name` / `admin_email`) and sets it via `new Symfony\Component\Mime\Address($fromEmail, $fromName)`, matching how GLPI native notifications work. Applies to both real and test sends.
- **en_GB locale** — added `en_GB.po` and `en_GB.mo` (170 strings). British English is now a fully supported locale alongside `es_MX`, `en_US`, `fr_FR`, `de_DE`. Registered in `plugin.xml`.

### Fixed
- **Dark theme: read-only font inputs** — replaced `disabled` with `readonly` + `bg-body text-body` Bootstrap classes so the active theme applies correctly.
- **Dark theme: hardcoded border colors** — replaced with `var(--tblr-border-color)` for theme-aware rendering.
- **Dark theme: helper text contrast** — `.form-text` now uses `var(--bs-secondary-color, var(--bs-body-color))` at 80% opacity.
- **Email formatting not applied** — all three format markers now render correctly through the shared `responsivasApplyTemplate()` helper.
- **Email subject `null` on real send** — fixed `TypeError` from Symfony Mailer.
- **Format button toggling backwards** — replaced with proper toggle detection that strips markers when already present.
- **PDF footer corner fields rendered as plain text** — replaced with `writeHTMLCell()` and a new private `fmtCell()` method.
- **PDF footer row Y-coordinate desync** — fixed by capturing `$y1 = $this->GetY()` once before writing both cells.
- **RFC 2822 sender address error** — fixed to use `new Symfony\Component\Mime\Address()`.
- **XSS in email HTML** — variable values now HTML-escaped before substitution.
- **i18n: four new strings missing from locales** — added to all five locale files and recompiled.

### Changed
- **Merged `send_test_mail.php` into `send_mail.php`** via hidden `mode=test` POST field.
- **Email rendering unified** through `responsivasApplyTemplate()` with inline CSS styles.
- **Format toolbar: `data-wrap` + event delegation** — single delegated listener replaces inline `onclick` handlers.

### Locales
- Added `en_GB` (170 strings). Total: **170 strings** per locale.

---

## [1.2.4] — 2026-03-08

### Fixed
- **Badge spacing in mobile dropdown** — tab name returns plain `Responsivas <badge>` string.
- **`setup.php` default logo path** — corrected `pics/logo.png` → `logo.png`.
- **`pluginDir()` no longer throws** — falls back to `dirname(__DIR__)` instead of throwing.
- **CRLF line endings** — converted to LF in affected files.
- **Missing `responsivas` i18n domain** — all `__()` and `_n()` calls now include the plugin domain.

### Changed
- **`getCounts()` GLPI-compliant queries** — replaced `$DB->query()` with `$DB->request()`.
- **PDF button loading feedback** — spinner via `data-resp-pdf-btn` attribute and delegated JS listener.
- **Syntax errors fixed** in `user.class.php` and `helpers.php`.
- **Removed debug mode banner** from `config.class.php`.

### Added
- **Italic and underline formatting** in PDFs and email.
- **Format toolbar for email fields**.
- **Friendly sender name in outgoing mail**.
- **Clickable variable tags in configuration**.

### Locales
- Total: **167 strings** across es_MX, en_US, fr_FR, de_DE.

---

## [1.2.3] — 2026-03-07

### Added
- **Plugin icon for GLPI Marketplace** — `logo.png` (128×128 px) at plugin root.

### Changed
- `responsivas.xml` updated to version `1.2.3`.
- **README consolidated** — merged English and Spanish into single file.
- **File structure section added to README**.
- **`misc/` directory removed**.

---

## [1.2.2] — 2025-03-07

### Added
- **Template validation before PDF generation** — `validateTemplates()` called at start of every build method.
- **Schema-versioned configuration system** — `plugin_responsivas_getSchemaFields()` with `type`, `since`, `group`, `migrate` per field.

### Changed
- `plugin_responsivas_getDefaults()` is now a thin wrapper over the schema.
- Install and update delegate entirely to `migrateConfig()`.

### Locales
- Total: **166 strings** across es_MX, en_US, fr_FR, de_DE.

---

## [1.2.1] — 2025-03-07

### Fixed
- **CSRF token conflict after test email** — test button is now an independent `<form>` with its own token.
- **Nested form bug** — main config form now properly closed before test email form.
- **`send_test_mail.php` syntax error** on line 72.
- **`config.class.php` syntax error** on line 944.

### Changed
- Test email result shown as GLPI flash message after redirect.

---

## [1.2.0] — 2025-03-06

### Fixed
- **Bold text not rendering in PDFs** — switched to `**text**` Markdown-style syntax processed at render time. All default templates updated.
- **Plugin uninstall did not clean the database** — `hook.php` now runs `$DB->delete('glpi_configs', ...)` on uninstall.
- **Install kept old incompatible templates** — schema migration now force-resets template fields on `template_version` mismatch.

### Added
- Bold support in email body and footer fields.

---

## [1.1.0] — 2025-03-04

### Added
- CSRF protection on all POST endpoints.
- Proper file validation on logo upload.

### Changed
- Config UI redesigned to match GLPI 11 visual standards (Bootstrap 5 tabs, cards, icons).
- Variable hints layout changed to vertical list with icons.

---

## [1.0.0] — 2025-02-20

### Added
- Initial release.
- PDF generation for computers, printers, and phones.
- Email delivery via GLPI native `GLPIMailer`.
- Fully customizable templates stored in `glpi_configs`.
- QR codes on PDFs linking to the asset in GLPI.
- Legal witness and representative configuration.
- Auto-generated useful life clause for phone contracts.
- Asset summary table with brand, model, serial, asset tag, condition, specs.
- Footer with left/right text and document reference code.
- Multi-language support: Spanish (Mexico), English, French, German.
- Logo upload for PDF header.
- Font size configuration per asset type.
- Employee number display toggle.
- QR code display toggle.

---

[1.3.2]: ../../compare/v1.3.1...v1.3.2
[1.3.1]: ../../compare/v1.3.0...v1.3.1
[1.3.0]: ../../compare/v1.2.7...v1.3.0
[1.2.7]: ../../compare/v1.2.6...v1.2.7
[1.2.6]: ../../compare/v1.2.5...v1.2.6
[1.2.5]: ../../compare/v1.2.4...v1.2.5
[1.2.4]: ../../compare/v1.2.3...v1.2.4
[1.2.3]: ../../compare/v1.2.2...v1.2.3
[1.2.2]: ../../compare/v1.2.1...v1.2.2
[1.2.1]: ../../compare/v1.2.0...v1.2.1
[1.2.0]: ../../compare/v1.1.0...v1.2.0
[1.1.0]: ../../compare/v1.0.0...v1.1.0
[1.0.0]: ../../releases/tag/v1.0.0
