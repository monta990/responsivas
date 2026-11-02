# Responsivas testing and release checks

This document describes the development-only validation suite. It is not part
of the GLPI installation package.

## Run locally

Requirements: PHP 8.2+, fileinfo, gd, intl and json, PHPUnit 11.5,
GNU gettext (msgfmt/msgunfmt), and zip/unzip.

    php tests/run.php
    php tests/test-upgrade-preservation.php
    php tests/validate-release.php
    phpunit --configuration phpunit.xml

Validate translation catalogs:

    for po in locales/*.po; do
        msgfmt --check --check-format -o /tmp/catalog.mo "$po"
    done

Build and inspect the exact installation archive:

    bash scripts/package.sh dist
    unzip -t dist/responsivas-1.6.6.zip
    unzip -Z1 dist/responsivas-1.6.6.zip

## Coverage areas

- Business logic: safe PDF filenames, address escaping, entity location rules,
  asset visibility and entity scope, template formatting, date handling,
  configuration font-size normalization and configuration export allow-list.
- PDF runtime: a real in-memory PDF generation smoke test against the TCPDF
  version bundled in each supported GLPI branch, including footer page-break
  restoration and the GLPI 12 local-file allowlist.
- Upgrade lifecycle: schema keep/reset rules, missing-default initialization,
  preservation of the administrator's logo, and scoped Twig-cache cleanup.
- HTTP/security contracts: user/config permissions, entity authorization,
  CSRF-bearing forms, POST-only mail endpoint, short-lived PDF tickets,
  private resource access, strict logo import validation and output headers.
- UI/templates: all configuration tabs, user-tab actions, manual inspection
  and return flows, preview variants, localized route slugs and Twig linting.
- Localization: PO syntax and format placeholders, compiled MO validity,
  required language catalogs and metadata.
- Release: version/manifest/changelog consistency, production allow-list,
  required runtime files, image integrity and ZIP structure.
- Static analysis: PHP lint with deprecated-syntax detection, GLPI-aware
  PHPStan/Psalm diagnostics and GLPI's TwigCS ruleset. The current 1.6.6
  template baseline is 127 formatting findings; CI reports them and rejects
  any increase until the templates are cleaned in a separate behavior-neutral change.
  PHPStan and Psalm findings are recorded per GLPI branch in
  .github/PHP-STATIC-ANALYSIS-BASELINE.md and remain diagnostic-only until
  triaged; they are not a clean analysis result.
- Security automation: secret scanning, workflow auditing and CodeQL analysis
  of GitHub Actions definitions.

## Scope boundaries

The isolated CLI tests do not claim to boot a full GLPI database, execute
Symfony controllers over HTTP, or render a complete user-specific PDF from
production GLPI records. GLPI 11 and GLPI 12 are included as separate
static-analysis targets. Full live HTTP/PDF integration tests require a
provisioned GLPI database and fixtures with real users, entities and assets.
