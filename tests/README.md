# Responsivas CLI tests

The test suite is development-only. It does not add runtime dependencies to the
plugin and is not included in the plugin installation package.

## Requirements

- PHP 8.2 or newer.
- The PHP extensions required by the plugin: fileinfo, gd, intl and json.
- PHPUnit 11.5 available as a CLI executable.

No Composer manifest, Composer autoloader or production dependency is required.

## Run the tests

From the repository root:

    phpunit --configuration phpunit.xml

Run PHP syntax validation for the complete repository:

    find . -type f -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l

The PHPUnit bootstrap provides only the small GLPI globals required by isolated
utility tests. It does not mock controllers, database behavior, permissions,
TCPDF or Symfony. Those runtime paths require an integration environment with
a real GLPI installation and are intentionally not reported as covered by
this unit/contract suite.

## CI PHP policy

The GitHub Actions workflow tests every PHP minor version from the plugin's
declared minimum through the latest stable version available in the matrix.
Pre-release/nightly builds are included as experimental jobs and are marked
non-blocking so that upstream PHP or extension regressions remain visible
without hiding failures on supported stable PHP versions.
