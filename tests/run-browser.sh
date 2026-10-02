#!/usr/bin/env bash
# Testy układu w prawdziwej przeglądarce (Chromium przez Playwright).
# Wymaga: PHP 8.1+, Python 3 z Pillow, Node 18+ i zainstalowanego Playwrighta.
set -u
cd "$(dirname "$0")"
WORK="${TMPDIR:-/tmp}/modohome-browser-tests"
mkdir -p "$WORK"
fail=0

python3 make-fixtures.py
cp fixtures/*.png "$WORK/"

php build-inline-css.php '{"image_ratio":"3:4","image_fit":"cover"}'   css-fixed.css
php build-inline-css.php '{"image_ratio":"3:4","image_fit":"contain"}' css-contain.css
php build-inline-css.php '{"image_ratio":"auto"}'                      css-auto.css
php build-inline-css.php '{"columns_mobile":1}'                        inline.css
mv css-*.css inline.css "$WORK/"

python3 make-pages.py "$WORK" ../modohome-katalog-produktow
python3 make-theme-page.py "$WORK" ../modohome-katalog-produktow

echo "== Liczba kolumn i ustawienia wyglądu =="
node test-layout.mjs "$WORK" || fail=1
echo "== Kadrowanie zdjęć =="
node test-fit.mjs "$WORK" || fail=1
echo "== Okno modalne =="
node test-modal.mjs "$WORK" || fail=1
echo "== Odporność na style motywu =="
node test-theme.mjs "$WORK" || fail=1

[ $fail -eq 0 ] && echo "WSZYSTKO PRZESZŁO" || echo "SĄ NIEPOWODZENIA"
exit $fail
