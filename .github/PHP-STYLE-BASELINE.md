# PHP and Twig style baselines

Measured on 2026-11-02 with the GLPI/PHP versions configured in the security
workflow. PHP-CS-Fixer counts **files with fixable formatting changes**, not
individual rule violations.

## PHP-CS-Fixer — GLPI @PER-CS3x0

| GLPI | PHP | PHP-CS-Fixer | Files needing formatting |
|---|---:|---:|---:|
| 11.0 | 8.2 | 3.94.2 | 32 / 32 |
| 12.0 | 8.3 | 3.95.27 | 32 / 32 |

CI fails if more than 32 files need formatting. Changed runtime PHP files are
checked separately and must have zero fixable changes.

## TwigCS

The published templates have a baseline of 127 findings. The existing TwigCS
workflow counts them and fails if the total increases.

This is historical formatting debt, not a waiver for new or changed code.
Reduce the baseline in focused, reviewable changes until all files are clean.
