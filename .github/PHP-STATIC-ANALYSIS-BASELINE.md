# Responsivas 1.6.6 — PHP static-analysis baseline

Reviewed against the published 1.6.6 source on 2026-11-02 using the GLPI 11
and GLPI 12 bugfix branches. This is a transparent diagnostic baseline, not
a declaration that the findings are false positives or harmless.

The workflow keeps PHPStan and Psalm non-blocking while the existing source
is triaged in focused follow-up changes. Their results remain visible in the
Actions logs. Do not suppress or dismiss findings merely to obtain a green
check.

## PHPStan baseline

| GLPI core | PHP used | Findings |
|---|---:|---:|
| 11.0 bugfixes | 8.2 | 62 |
| 12.0 bugfixes | 8.3 | 76 |

The higher GLPI 12 count includes diagnostics that differ because the two GLPI
branches ship different APIs/stubs (including TCPDF 6/7 integration). The
baseline includes these recurring families:

- Missing return types and generic array value types in plugin lifecycle
  functions and methods in hook.php, setup.php, Generator.php, PdfBuilder.php,
  ConfigService.php, UserTab.php, and Utils.php.
- Dynamic object instantiation in GlpiPlugin\Responsivas\Pdf\PdfBuilder.
- Incomplete match expressions, dead catches, and flow-analysis findings
  such as a possibly undefined $table, redundant conditions and comparisons.
- Hard-coded GLPI right names (user, config) where the GLPI analyzer
  recommends class right-name properties.
- Deprecated Session::getNewCSRFToken() calls.
- GLPI/TCPDF branch-specific API diagnostics, including TCPDF file-allowlist
  metadata and generic inference differences.
- The direct-access die guard and plugin bootstrap functions whose return
  and parameter types follow the GLPI plugin-hook contract but are not fully
  described to PHPStan.

These findings are not all equivalent in severity. The dynamic-instantiation,
taint, authorization, and deprecated-API items require explicit security and
compatibility review; type-documentation and branch-stub issues should be
handled separately rather than mixed with behavior changes.

The PHPStan command uses table output so file/line details remain readable in
the workflow log. Re-baseline the counts only after reviewing the difference;
never silently accept an increase.

## Psalm security / taint baseline

Psalm 6.16.1 reported 36 findings in each GLPI branch. Type inference was
89.7464% for the GLPI 11 run and 89.5991% for the GLPI 12 run.

All reported findings were in these published source locations:

| File | Reported line(s) | Finding family |
|---|---|---|
| hook.php | 244 | TaintedHtml |
| setup.php | 68, 77, 89 | TaintedHtml; TaintedTextWithQuotes at the same output sites |
| src/Controller/PreviewController.php | 80 | TaintedHtml |
| src/Service/ConfigService.php | 136, 140, 151, 307, 357, 365, 475, 494, 499, 514, 543, 548, 558 | TaintedHtml |
| src/Service/MailService.php | 22, 32, 47, 60, 120, 125, 132, 152, 161, 174, 192, 233, 294, 315, 326 | TaintedHtml |

These are taint-flow diagnostics in HTML output paths. They have not been
reviewed as false positives. Before turning Psalm into a blocking check, each
source-to-sink path must be inspected, the escaping/context guarantees must be
verified, and any real unsafe output must be fixed in a dedicated security PR.

## TwigCS baseline

GLPI TwigCS reports 127 pre-existing formatting findings in the published
1.6.6 Twig templates. The security workflow prints this count and fails if it
increases. Templates are intentionally not reformatted in this test-only PR.

## Remediation status

- [x] Capture a reproducible GLPI 11/12 analysis run.
- [x] Commit tool versions, branch targets and a visible baseline.
- [x] Keep output visible; do not mark the diagnostics as clean.
- [ ] Review and remediate tainted HTML output paths.
- [ ] Add missing array shapes/return types and restrict dynamic instantiation.
- [ ] Resolve compatibility, deprecated API and control-flow diagnostics.
- [ ] Reduce TwigCS findings to zero in a separate formatting-only PR.
- [ ] Promote PHPStan/Psalm/TwigCS to blocking checks after their reviewed
      baselines reach the agreed threshold.

No production source changes are included in this baseline document.
