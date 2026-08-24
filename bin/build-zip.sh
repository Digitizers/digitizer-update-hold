#!/usr/bin/env bash
#
# Build an installable plugin ZIP from the current working tree.
#
# WordPress requires the archive to contain exactly one folder named after the
# plugin, so the tracked files are staged into digitizer-update-hold/ first.
#
# Usage:
#   bin/build-zip.sh                 # -> dist/digitizer-update-hold.zip
#
set -euo pipefail

SLUG="digitizer-update-hold"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_NAME="${1:-$SLUG.zip}"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Ship only what git tracks, minus the development-only paths.
cd "$ROOT"
mkdir -p "$STAGE/$SLUG"
while IFS= read -r -d '' f; do
	case "$f" in
		.github/*|bin/*|dist/*|docs/*|tests/*|.gitignore|*.code-workspace) continue ;;
	esac
	mkdir -p "$STAGE/$SLUG/$(dirname "$f")"
	cp "$f" "$STAGE/$SLUG/$f"
done < <( git ls-files -z )

mkdir -p "$DIST"
rm -f "$DIST/$OUT_NAME"
( cd "$STAGE" && zip -rq "$DIST/$OUT_NAME" "$SLUG" -x '*.DS_Store' )

VERSION="$(grep -m1 "^ \* Version:" "$ROOT/$SLUG.php" | tr -d ' ' | cut -d: -f2)"
echo "built  : dist/$OUT_NAME"
echo "version: $VERSION"
echo "files  : $(unzip -l "$DIST/$OUT_NAME" | tail -1 | awk '{print $2}')"
echo "size   : $(du -h "$DIST/$OUT_NAME" | cut -f1)"
