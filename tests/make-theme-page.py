"""Strona testowa z motywem, który celowo nadpisuje style wtyczki —
tak jak robią to prawdziwe motywy WordPressa."""
import sys, pathlib

scratch, plugin = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
catalog = (plugin/'assets/css/catalog.css').read_text(encoding='utf-8')
inline  = (scratch/'css-auto.css').read_text(encoding='utf-8')

# Typowe zachowania motywów: wersaliki na nagłówkach, brak zawijania,
# duża czcionka, przyciski bez łamania wiersza.
THEME = """
h1, h2, h3, h4 { text-transform: uppercase; font-size: 1.6rem; letter-spacing: .01em; margin: 0; }
button { white-space: nowrap; font-family: inherit; }
p { margin: 0 0 1em; }
body { margin: 0; font-family: system-ui, sans-serif; }
"""

TITLES = [
    'Wentylator wieżowy 65 cm',
    'Automatyczna samoczyszcząca kuweta dla kota',
    'Bezprzewodowyodkurzaczdobasenuzczujnikiempoziomuwody',
    'Mini drukarka WiFi',
]

cards = '\n'.join(f'''<article class="modohome-catalog-card">
  <button type="button" class="modohome-catalog-card-link">
    <div class="modohome-catalog-card-media"><img class="modohome-catalog-card-image" src="wide.png" alt=""></div>
    <div class="modohome-catalog-card-body">
      <p class="modohome-catalog-card-category">Elektronika i AGD małe</p>
      <h3 class="modohome-catalog-card-title">{t}</h3>
      <p class="modohome-catalog-card-prices">
        <span class="modohome-catalog-price">350 zł</span>
        <span class="modohome-catalog-price-old">450 zł</span>
      </p>
    </div>
  </button>
</article>''' for t in TITLES)

html = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{THEME}</style>
<style>{catalog}</style><style>{inline}</style></head><body>
<div class="modohome-catalog modohome-catalog--media-auto" id="cat" style="--modohome-catalog-cols-desktop:4">
  <div class="modohome-catalog-grid" id="grid">{cards}</div>
</div></body></html>"""

(scratch/'page-theme.html').write_text(html, encoding='utf-8')
print('  strona: page-theme.html (motyw nadpisujący style)')
