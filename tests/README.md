# Responsivas CLI and release tests

All tests in this directory are development-only. They are not loaded by GLPI
and are excluded from the installation ZIP.

## Local execution

Requirements: PHP 8.2+, the plugin's required PHP extensions
(fileinfo, gd, intl and json), PHPUnit 11.5, GNU gettext, zip and unzip.

Run the feature-specific CLI suite and lifecycle/release checks:

    php tests/run.php
    php tests/test-upgrade-preservation.php
    php tests/validate-release.php

Run the PHPUnit unit/contract suite:

    phpunit --configuration phpunit.xml

Validate every translation catalog:

    for po in locales/*.po; do
        msgfmt --check --check-format -o /tmp/catalog.mo "$po"
    done

Build and inspect the actual installation archive:

    bash scripts/package.sh dist
    unzip -t dist/responsivas-1.6.6.zip
    unzip -Z1 dist/responsivas-1.6.6.zip

## Covered Responsivas behavior

- PDF filename safety, address escaping, entity location validation,
  asset-level authorization and GLPI entity-scope filtering.
- Configured PDF font-size normalization and explicit configuration-import
  allow-list enforcement, including invalid JSON, oversize uploads, unknown
  keys, clamped values and rejected logo MIME types.
- Configuration schema migration, administrator setting preservation,
  manual-form switch preservation, institutional logo persistence and scoped
  Twig-cache invalidation.
- Published manual inspection/return URL slugs and six PDF preview variants.
- User/config permission and CSRF contracts, PDF ticket expiry, POST-only
  mail action, protected logo resource and TCPDF footer-state restoration.
- PO/MO validity, language headers, placeholder formats and bundled PNG assets.
- Release metadata, source layout and exclusion of development tooling from
  the plugin installation archive.

The tests use small GLPI test doubles only for isolated logic and lifecycle
behavior. They do not pretend to be full live-GLPI HTTP/database tests.
