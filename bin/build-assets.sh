#!/usr/bin/env bash
#
# Render the WordPress.org plugin-page assets from their HTML sources.
#
# The sources live in assets/src/ and the rendered PNGs land in
# .wordpress-org/, which is what the assets workflow uploads to SVN.
# Neither directory ships inside the plugin - see .distignore.
#
# Usage:
#   bin/build-assets.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/assets/src"
OUT="$ROOT/.wordpress-org"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"

if [ ! -x "$CHROME" ]; then
	echo "Chrome not found at: $CHROME" >&2
	echo "Set CHROME to a Chrome or Chromium binary and retry." >&2
	exit 1
fi

# shot <source.html> <css width> <css height> <scale> <output name>
shot() {
	local page="$1" w="$2" h="$3" scale="$4" name="$5"
	"$CHROME" --headless --disable-gpu --hide-scrollbars \
		--force-device-scale-factor="$scale" \
		--window-size="$w,$h" \
		--screenshot="$OUT/$name" \
		"file://$SRC/$page" 2>/dev/null
	echo "  $name  ($(( w * scale ))x$(( h * scale )))"
}

mkdir -p "$OUT"
echo "rendering:"
shot icon.html   256 256 1 icon-256x256.png
shot banner.html 772 250 1 banner-772x250.png
shot banner.html 772 250 2 banner-1544x500.png

# Headless Chrome enforces a minimum window size, so a 128x128 window renders
# the page at a larger size and crops. Downscale the 256 render instead.
python3 - "$OUT" <<'PY2'
import sys, pathlib
from PIL import Image
out = pathlib.Path(sys.argv[1])
Image.open(out / 'icon-256x256.png').resize((128, 128), Image.LANCZOS).save(out / 'icon-128x128.png')
print('  icon-128x128.png  (128x128)')
PY2

# Palette-compress. The art is flat colour over a two-stop gradient, so 256
# colours is visually lossless here and cuts the files by ~4x.
python3 - "$OUT" <<'PY'
import sys, pathlib
from PIL import Image

for f in sorted(pathlib.Path(sys.argv[1]).glob('*.png')):
	before = f.stat().st_size
	img = Image.open(f).convert('RGB')
	img.quantize(colors=256, method=Image.MEDIANCUT, dither=Image.Dither.FLOYDSTEINBERG).save(f, optimize=True)
	after = f.stat().st_size
	print(f"  {f.name}  {before // 1024}K -> {after // 1024}K")
PY
