#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST_DIR="$ROOT/dist"
if [[ $# -ge 1 ]]; then
    DIST_DIR="$1"
fi

if ! command -v php >/dev/null 2>&1; then
    echo "PHP CLI is required to read the plugin version." >&2
    exit 1
fi
if ! command -v zip >/dev/null 2>&1 || ! command -v unzip >/dev/null 2>&1; then
    echo "zip and unzip are required to build and validate the installation archive." >&2
    exit 1
fi

VERSION="$(awk -F"'" '/PLUGIN_RESPONSIVAS_VERSION/ { print $4; exit }' "$ROOT/setup.php")"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Could not read a valid semantic version from setup.php." >&2
    exit 1
fi

if ! grep -Fq "<num>$VERSION</num>" "$ROOT/plugin.xml"; then
    echo "plugin.xml does not contain version $VERSION." >&2
    exit 1
fi
if ! grep -Fq "responsivas-$VERSION.zip" "$ROOT/plugin.xml"; then
    echo "plugin.xml download URL is not synchronized with version $VERSION." >&2
    exit 1
fi

mkdir -p "$DIST_DIR"
DIST_DIR="$(cd "$DIST_DIR" && pwd)"
WORK_DIR="$(mktemp -d /tmp/responsivas-package.XXXXXX)"
trap 'rm -rf "$WORK_DIR"' EXIT

PACKAGE_ROOT="$WORK_DIR/responsivas"
mkdir -p "$PACKAGE_ROOT"

# Explicit runtime allow-list: do not archive the repository root. Test suites,
# CI workflows, local tooling and development documents must never be installed.
cp -a \
    "$ROOT/CHANGELOG.md" \
    "$ROOT/LICENSE" \
    "$ROOT/README.md" \
    "$ROOT/config.png" \
    "$ROOT/hook.php" \
    "$ROOT/logo.png" \
    "$ROOT/plugin.xml" \
    "$ROOT/setup.php" \
    "$ROOT/user.png" \
    "$PACKAGE_ROOT/"

cp -a \
    "$ROOT/assets" \
    "$ROOT/locales" \
    "$ROOT/src" \
    "$ROOT/templates" \
    "$PACKAGE_ROOT/"

ARCHIVE="$DIST_DIR/responsivas-$VERSION.zip"
rm -f "$ARCHIVE"
(
    cd "$WORK_DIR"
    zip -qr "$ARCHIVE" responsivas
)

unzip -tq "$ARCHIVE"

if unzip -Z1 "$ARCHIVE" | grep -Evq '^responsivas(/|$)'; then
    echo "Unexpected archive entry outside the responsivas/ root." >&2
    exit 1
fi

if unzip -Z1 "$ARCHIVE" | grep -Eq '^responsivas/(tests|scripts|[.]github|vendor)(/|$)|^responsivas/(phpunit[.]xml|TESTING[.]md|composer[.](json|lock))$'; then
    echo "Development tooling or configuration was included in the installation archive." >&2
    exit 1
fi

if unzip -Z1 "$ARCHIVE" | grep -Eiq '(^|/)([.]DS_Store|Thumbs[.]db)$|[.](log|cache|tmp)$'; then
    echo "Unexpected local or temporary file found in the installation archive." >&2
    exit 1
fi

for entry in \
    responsivas/setup.php \
    responsivas/hook.php \
    responsivas/plugin.xml \
    responsivas/src/Pdf/PdfBuilder.php \
    responsivas/src/Controller/PdfController.php \
    responsivas/templates/user/tab.html.twig \
    responsivas/locales/es_MX.mo \
    responsivas/assets/schematics/computer_general.png
do
    if ! unzip -Z1 "$ARCHIVE" | grep -qx "$entry"; then
        echo "Required installation file missing from archive: $entry" >&2
        exit 1
    fi
done

echo "Created and validated $ARCHIVE"
