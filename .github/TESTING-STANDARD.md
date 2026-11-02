# Universal plugin testing standard

This standard applies to every GLPI plugin maintained in this account. Test
coverage must be adapted to each plugin's actual features; a PHP syntax-only
workflow or a generic smoke test is not sufficient.

## Runtime and compatibility matrix

1. Every stable PHP minor version from the plugin's declared minimum through
   the newest stable PHP release must run the blocking test suite. Do not skip
   intermediate minor versions.
2. Include every available newer PHP development channel that can be installed
   in CI: Alpha, Beta, RC, nightly and development snapshots. These jobs are
   visible, non-blocking compatibility signals until the relevant PHP version
   becomes stable.
3. When a prerelease becomes stable, move it into the blocking stable matrix.
   Remove obsolete snapshots and add the next available development version.
4. Validate all GLPI major/minor versions the plugin declares as supported,
   using real matching GLPI core branches for GLPI-aware linting and static
   analysis. Use valid PHP/GLPI combinations; do not imply a combination the
   upstream GLPI release does not support.

## Required test layers

Every plugin CI must include the applicable layers below:

- Syntax and runtime prerequisites: all plugin PHP files, declared extensions,
  deprecated PHP syntax, and required CLI tools.
- Feature-specific CLI tests: real plugin methods and meaningful boundary,
  invalid-input, permission, failure and security cases. Use lightweight GLPI
  test doubles only for isolated logic; do not label mocks as full integration.
  For PDF plugins, render a real in-memory PDF against the TCPDF versions
  shipped by each supported GLPI branch when practical.
- Lifecycle and preservation tests: install, update, schema/config migration,
  retained administrator settings, external files and cache scope where used.
- UI and presentation checks: Twig lint with GLPI rules, route contracts,
  form/CSRF behavior, localized labels, static resources and relevant frontend
  or artwork checks. Use browser/UI automation when the plugin has meaningful
  interactive UI and the repository can run it reliably. A pre-existing
  TwigCS baseline may be temporarily non-blocking only when its current count
  and source of findings are documented and CI forbids any increase. Reduce
  the baseline to zero and make TwigCS fully blocking as a follow-up.
- Localization checks: every PO catalog, placeholders/format strings, language
  metadata, POT consistency where applicable and compiled MO files.
- Release validation: setup/plugin manifest/changelog version agreement,
  compatibility declarations, required production files and expected
  installation archive layout.
- Package verification: build the real install ZIP from an explicit allow-list,
  test archive integrity, confirm the single expected plugin root directory,
  and reject tests, scripts, CI files, caches, private vendor directories and
  other development-only content.
- GLPI-aware static analysis: PHPStan/Psalm or equivalent, parallel PHP lint
  and TwigCS using the relevant GLPI core version.
- Security checks: secret scanning, GitHub Actions workflow auditing, and
  CodeQL or an equivalent supported static security analyzer.

## Blocking policy

- Stable PHP jobs, deterministic feature tests, lifecycle tests, release
  validation, translation checks, package checks, and blocking GLPI/Twig
  validation must pass before merge or release.
- PHP prerelease/nightly failures remain visible but do not block stable
  compatibility results. Clearly distinguish upstream toolchain failures from
  plugin defects.
- Static-analysis diagnostics may be temporarily non-blocking only when a
  committed baseline records, per supported GLPI branch, the tool/version,
  finding counts, affected files/symbols or line ranges, diagnostic families,
  and remediation status. They must not be represented as clean. New findings
  must not silently increase the baseline; review and reduce existing findings
  in separate, behavior-focused changes. If repository-level CodeQL default
  setup is already enabled, rely on it instead of creating a duplicate
  advanced CodeQL upload workflow.
- Never merge or publish a release ZIP that has not passed the package
  validation for the exact source commit being released.

## Production compatibility guardrails

Test infrastructure is development-only. It must not require a Composer
manifest, private vendor directory, extra runtime dependency, new entry point,
or changed plugin installation path unless the plugin owner explicitly
approves that production change. Existing published routes, folder names,
file names, namespaces, data and upgrade behavior are treated as compatibility
contracts and must be tested rather than casually changed.
