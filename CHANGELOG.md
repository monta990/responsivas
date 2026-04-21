# Changelog — Responsivas

All notable changes to this project are documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.4.2] — 2026-04-21

### Fixed
- **Missing schema fields caused undefined defaults on fresh install** — `pc_show_comodato_sigs`, `pri_show_comodato_sigs`, `watermark_text`, `watermark_opacity`, `pdf_compression`, and `pdf_protection` were saved and read by the plugin but never declared in `plugin_responsivas_getSchemaFields()`. Fresh installations would have no default values for these fields until the admin manually saved the config form. All six are now registered in the schema with `migrate=keep`.
- **`pri_font_size` and `pho_font_size` not clamped on save** — printer and phone font sizes were stored raw from POST (`(int)$_POST[...]`) with no bounds check. A value of `0` would crash TCPDF on the next PDF generation. Now clamped to `6–72 pt` matching the existing computer font size guard.
- **`lbl()` static cache frozen on early call** — the shared label cache used `static $cache = null` keyed to nothing. If called before GLPI loaded the user's locale (e.g. from a background job or early hook), all PDF labels (CLAUSES, LENDER, BORROWER, WITNESS, table headers) would freeze as English for the entire request. Cache now keyed on `$_SESSION['glpilanguage']`.
- **Hardcoded Spanish "Fuente usada" in Computer tab** — the font-name display field in the Computers settings tab used a hardcoded Spanish label. Now uses `__('Font used', 'responsivas')` matching the identical label already translated in the Printers and Phones tabs.
- **Hardcoded Spanish "Dimensiones:" on logo display** — the logo dimensions line in the General tab used the hardcoded Spanish word. Now uses the existing translatable string `__('Preview dimensions: ', 'responsivas')`.
- **`plugin_responsivas_check()` wrong function name for GLPI 11** — GLPI 11 calls `plugin_{name}_check_prerequisites()` to verify install requirements. The plugin defined `plugin_responsivas_check()` which GLPI silently ignores, meaning PHP version and TCPDF checks were never executed. Renamed to `plugin_responsivas_check_prerequisites()`.
- **Redundant `method_exists($mailer, 'getEmail')` guard in `send_mail.php`** — `$mailer->getEmail()` was already called unconditionally two lines earlier; if the method didn't exist, PHP would have thrown before reaching the guard. Removed the dead check and simplified the sender assignment to call `$email->from(...)` directly.
- **Dead `{condicion}` variable in phone demo PDF** — `buildDemoPdf()` defined `'{condicion}' => $demo_state` in `$pho_vars` but no template references `{condicion}` (templates use `{estado}`). Removed.
- **Typo in comment** — `config.class.php`: `Vista precia de logo` → `Vista previa de logo`.
- **`user.class.php` missing `declare(strict_types=1)`** — all other PHP files in the plugin declare strict types; `user.class.php` was the only exception.

### Changed
- **`responsivasFooterFields()` now calls `responsivasFormatToolbar()`** — the three-button Bold/Italic/Underline toolbar HTML was duplicated inline inside `responsivasFooterFields()` despite the dedicated `responsivasFormatToolbar()` function existing. Footer fields now call the shared function.

### Locales
- All 4 `.mo` binaries recompiled (`es_MX`, `fr_FR`, `de_DE`, `it_IT`).
- Total: **250 strings** per locale. No new strings added.

---

## [1.4.1] — 2026-04-16

### Fixed
- **Extra blank line below employee number in signature block** — `$employee_line` was built with a trailing `<br>` that leaked into the signature cell. The `<br>` separator is now added conditionally only when the employee line is non-empty, in PC, printer, and phone documents.
- **Preview PDF title missing "Preview" prefix when user has real assets** — `buildPreview` was calling `buildComputerPdf` (title: `"Responsiva Computadora - Name"`) instead of overriding it. Title is now updated to `"Vista Previa - Responsiva Computadora - Name"` after building, consistent with the printer and phone previews.
- **Dead markdown processing in `responsivasRenderTemplate`** — `**bold**`, `*italic*`, and `__underline__` markers were processed a second time inside the template renderer, but `responsivasApplyTemplate` already converts them to `<span>` tags before `renderTemplate` runs, making the regex unreachable. Removed the dead code.
- **Silent swallowing of unexpected errors in `buildPreview`** — the try/catch around real-asset PDF builds caught all `Throwable`, hiding DB failures and PHP errors. Now catches only `RuntimeException` (the expected errors from build methods) and lets other exceptions propagate.
- **Open redirect via unvalidated `HTTP_REFERER` in `responsivasErrorAndBack`** — referer is now validated against `$CFG_GLPI['url_base']`; if it does not start with the configured base URL, the redirect falls back to `root_doc`.
- **Dead `$creator` variable in three build methods** — `$creator = self::getCreator()` was assigned but never used in `buildComputerPdf`, `buildPrinterPdf`, and `buildPhonePdf`. Removed.

---

## [1.4.0] — 2026-04-16

### Added
- **Lender/borrower dual-signature block on computer and printer documents** — new toggle per document type (`Show lender/borrower signatures`) in the Computers and Printers configuration tabs. When enabled, the single borrower signature line is replaced with a two-column table showing `LENDER` (legal representative configured in General settings) and `BORROWER` (assigned user). Disabled by default; enabling requires the legal representative to be configured.

### Fixed
- **Signature blocks splitting across pages** — lender/borrower and witness signature tables now use TCPDF's `nobr="true"` attribute so they are never broken mid-page. Signatures that previously appeared split between the bottom of one page and the top of the next now stay together.
- **Paragraphs splitting mid-page** — body text paragraphs generated by the template renderer (`responsivasRenderTemplate`) now use `<table nobr="true">` wrappers instead of bare `<p>` tags. TCPDF respects `nobr` on tables, preventing short paragraphs from being cut at the page boundary.
- **Intro paragraph splitting** — the introduction paragraph in computer and printer documents was rendered as a plain `<p>` tag and could be split across pages. Now uses a `nobr` table wrapper for consistent behavior.

---

## [1.3.3] — 2026-04-10

### Changed
- **English locale files removed** — `en_US.po`, `en_US.mo`, `en_GB.po`, and `en_GB.mo` removed from the locales directory. Since English is the base language (msgid strings), these files were redundant. GLPI's gettext fallback automatically uses the English msgid when no translation exists.
- **Hardcoded Spanish strings in config form replaced with translatable English** — all UI strings in `config.class.php` and `helpers.php` that were hardcoded in Spanish are now wrapped with `__('string', 'responsivas')` in English. This includes: section headers, footer field labels, font size labels, button tooltips (Bold, Italic, Underline), help texts, and JavaScript comments.

### Added
- **22 new translatable strings** for configuration form UI elements, fully translated in all non-English locales (`es_MX`, `fr_FR`, `de_DE`, `it_IT`).

### Fixed
- **Config form UI now respects user locale** — previously, some labels and tooltips in the configuration form were hardcoded in Spanish and would not translate even for users with English, French, German, or Italian locales. All strings are now properly translatable.

### Removed
- `locales/en_US.po` / `locales/en_US.mo`
- `locales/en_GB.po` / `locales/en_GB.mo`
- References to English locales from `plugin.xml` and `README.md` (both EN and ES sections)

### Locales
- **4 active locales**: `es_MX`, `fr_FR`, `de_DE`, `it_IT` (English is the base language, no `.po` files needed).
- Total: **233 strings** per locale.
- All locales fully translated (0 empty msgstr).

---

## [1.3.2] — 2026-04-06

### Fixed
- **Full i18n audit — all strings now translated in all locales** — comprehensive audit across all PHP files and all 6 locales. Fixed: (1) `_n()` plural form had Spanish hardcoded as second argument; (2) `hook.php` Event::log message was hardcoded Spanish with no `__()` call; (3) `fr_FR` and `de_DE` had singular PDF-count string untranslated (msgstr === msgid); (4) two orphan strings removed from POT (`Available variables`, `Send all responsibility documents to the user email`). Result: `es_MX`, `fr_FR`, `de_DE`, `it_IT` all at 0 empty translations (211 strings each).
- **Hardcoded Spanish labels in PDF documents** — all table headers (`Brand`, `Model`, `Serial`, `Processor`, `Speed`, `RAM`, `OS`, `Storage`, `Type`, `Condition`, `Comments`, `Device`, `Serial / Asset`), signature labels (`LENDER`, `BORROWER`, `WITNESS`, `CLAUSES`), fallback values (`Not specified`, `No comments`, `N/A`, `In use`), and the employee number prefix (`Employee No.:`) were hardcoded in Spanish inside `pdfbuilder.class.php`. All moved to the translation system via a shared `lbl()` method and `__()` calls. Any language now gets fully translated PDF content.

### Added
- **Italian locale (it_IT)** — new `it_IT.po` / `it_IT.mo` with PDF labels translated. Other UI strings are present as empty msgstr (standard practice; gettext falls back to English msgid). Registered in `plugin.xml`.

### Changed
- **Plugin display name localized** — the name `Responsivas` was hardcoded in the tab, configuration page title, and all locale files. Now fully translated: `Responsibility Documents` (en_US/en_GB), `Documents de responsabilité` (fr_FR), `Verantwortungsdokumente` (de_DE), `Documenti di responsabilità` (it_IT), `Responsivas` (es_MX unchanged). The plugin identifier key remains `responsivas`.
- **Version removed from .po/.pot headers** — `Project-Id-Version` now reads `Responsivas` without a version number, following gettext convention (the version belongs in the changelog, not in locale file headers).

### Locales
- Added Italian (`it_IT`) — fully translated (212/212 strings).
- Added 19 new strings for PDF table and signature labels.
- **All non-English locales now have 0 empty translations** (`es_MX`, `fr_FR`, `de_DE`, `it_IT` fully translated).
- Total: **211 strings** per locale × 6 locales. All non-English locales fully translated (0 empty).

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

[1.4.2]: ../../compare/v1.4.1...v1.4.2
[1.4.1]: ../../compare/v1.4.0...v1.4.1
[1.4.0]: ../../compare/v1.3.3...v1.4.0
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
