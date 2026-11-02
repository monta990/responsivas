# Universal GLPI plugin testing and code-quality standard

This policy applies to every GLPI plugin maintained in this account. Validation
is adapted to each plugin's features, supported GLPI branches, declared PHP
minimum, and owned frontend assets.

## Compatibility matrix

1. Every stable PHP minor version from the plugin's declared minimum through
   the newest stable release is a blocking CI target. Do not skip stable
   intermediate minors.
2. Newer available PHP development builds (alpha, beta, RC, nightly or
   development snapshots) are visible, non-blocking compatibility signals
   until they become stable.
3. Every GLPI version declared by the plugin is tested against its matching
   upstream branch and a PHP version supported by that GLPI branch.
4. Update the matrix and release metadata together when compatibility changes.
   Never claim an untested runtime combination.

## Required validation layers

- **PHP syntax:** lint all first-party PHP files, including tests and CI helper
  scripts. Run deprecated-syntax reporting where GLPI tooling supports it.
- **PHP style:** use PHP-CS-Fixer with GLPI's @PER-CS3x0 rules and the
  additional rules in .github/php-cs-fixer-plugin.php. The changed-runtime
  PHP gate is blocking and requires zero fixable changes. The whole-tree
  formatter audit uses the measured per-plugin baseline in
  .github/PHP-STYLE-BASELINE.md and blocks any increase.
- **PSR-4:** class-like files under src/ must match their case-sensitive
  directory namespace and filename. Validate resolution with Composer's
  ClassLoader using GLPI's namespace-to-src mapping. This is a loader-contract
  check; it does not claim that a full database-backed GLPI installation was
  booted.
- **Twig:** lint all owned Twig templates with GLPI's TwigCS rules. Any legacy
  baseline must be explicit, measured, visible, non-increasing and reduced in
  follow-up work. Templates with no baseline are blocking.
- **JavaScript:** run node --check over first-party JavaScript. Exclude
  third-party/minified bundles from first-party style checks. Add ESLint when
  the plugin owns substantial JavaScript logic.
- **CSS/SCSS:** run Stylelint with a committed configuration when the plugin
  owns CSS/SCSS source. Do not add a meaningless CSS lint job when there are
  no CSS/SCSS source files.
- **Static analysis:** run GLPI-aware PHPStan and security/taint analysis where
  available. Non-blocking diagnostics require a committed, quantified
  baseline and must never be described as clean.
- **Functional/lifecycle:** run feature-specific business tests, invalid-input
  and permission cases, install/update/preservation tests, UI/route contracts,
  and real integrations (such as PDF rendering) where applicable.
- **Translations:** validate every PO catalog and placeholders; verify
  compiled MO files when the repository distributes them.
- **Release/package:** validate version and compatibility metadata, build the
  actual installation ZIP from an explicit allow-list, verify archive
  integrity/layout, and prevent tests, tooling, caches or development files
  from entering the plugin package.
- **Workflow security:** pin third-party actions to reviewed commit SHAs, keep
  permissions minimal, do not run untrusted PR code with secrets, and audit
  workflows with zizmor. Use existing CodeQL default setup rather than
  duplicating it.

## Blocking and baseline policy

- Stable PHP syntax, changed-runtime PHP style, PSR-4 mapping, deterministic
  tests, lifecycle checks, release metadata, translations and package
  validation are blocking.
- PHP prerelease failures are informational.
- Historical PHP/Twig style debt is recorded in
  .github/PHP-STYLE-BASELINE.md, and CI fails if the recorded count increases.
  When a baseline is reduced, lower the committed value in the same change.
- Existing static-analysis debt may be diagnostic-only while being fixed, but
  it must be quantified, scoped to exact tools/runtime/GLPI branches and never
  described as clean.
- No findings may be added in changed runtime files. A baseline is not
  permission to introduce or hide new issues.

## Production compatibility boundaries

CI tooling belongs only in development files and GitHub Actions. Do not add
Composer manifests, private vendor directories, extra production dependencies,
new entry points or autoloaders to installation ZIPs. Keep published plugin
versions, folder names, routes, namespaces, stored data, upgrade behavior and
installation paths unchanged unless explicitly approved.
