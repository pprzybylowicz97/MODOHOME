#!/usr/bin/env bash
# Uruchamia komplet testów wtyczki. Wymaga PHP 8.1+ i Node 18+.
set -u
cd "$(dirname "$0")"
PLUGIN="../modohome-katalog-produktow"
fail=0

echo "== Składnia PHP =="
n=0
while IFS= read -r f; do
  n=$((n+1))
  php -l "$f" >/dev/null 2>&1 || { echo "  BŁĄD: $f"; fail=1; }
done < <(find "$PLUGIN" -name '*.php')
echo "  $n plików sprawdzonych"

echo "== Składnia JavaScript =="
for f in "$PLUGIN"/assets/js/*.js; do
  node --check "$f" >/dev/null 2>&1 || { echo "  BŁĄD: $f"; fail=1; }
done
echo "  $(ls "$PLUGIN"/assets/js/*.js | wc -l) plików sprawdzonych"

echo "== Zmienne CSS =="
php check-css-vars.php || fail=1

for t in test-plugin.php test-render.php test-optimizer.php; do
  echo "== $t =="
  php "$t" | tail -1 | sed 's/^/  /'
  php "$t" >/dev/null 2>&1 || fail=1
done

echo
[ $fail -eq 0 ] && echo "WSZYSTKO PRZESZŁO" || echo "SĄ NIEPOWODZENIA"
exit $fail
