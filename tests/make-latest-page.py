"""Strona testowa zajawki najnowszych produktów, z motywem nadpisującym style."""
import sys, pathlib

scratch, plugin = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
catalog = (plugin/'assets/css/catalog.css').read_text(encoding='utf-8')
inline  = (scratch/'css-fixed.css').read_text(encoding='utf-8')

THEME = """
h1,h2,h3,h4 { text-transform: uppercase; font-size: 1.7rem; margin: 0; }
button { white-space: nowrap; font-family: inherit; }
a { color: #0066cc; }
body { margin: 0; padding: 24px; background: #eceae6; font-family: system-ui, sans-serif; }
"""

ITEMS = [
    ('Ekspozycja', 'exposition', 'AGD duże', '[Marka] pralka [model], 8 kg', 'Drobna rysa z boku obudowy'),
    ('Nowość', 'new', 'Meble', 'Krzesło tapicerowane [nazwa], 4 szt.', 'Nowe, uszkodzone opakowanie'),
    ('Ostatnia sztuka', 'last', 'Pro-Craft', 'Szlifierka kątowa [model], 1200 W', 'Nowa, 3 lata gwarancji'),
    ('Ekspozycja', 'exposition', 'Dom i ogród', 'Grill gazowy [marka], 3 palniki', 'Stało na wystawie, komplet'),
]

cards = '\n'.join(f'''<article class="modohome-catalog-teaser">
  <a class="modohome-catalog-teaser-link" href="#">
    <div class="modohome-catalog-teaser-media">
      <span class="modohome-catalog-teaser-badge modohome-catalog-teaser-badge--{slug}">{badge}</span>
      <img class="modohome-catalog-teaser-image" src="wide.png" alt="">
    </div>
    <div class="modohome-catalog-teaser-body">
      <p class="modohome-catalog-teaser-category">{cat}</p>
      <h3 class="modohome-catalog-teaser-title">{title}</h3>
      <p class="modohome-catalog-teaser-description">{desc}</p>
      <span class="modohome-catalog-teaser-cta">Cena w katalogu <span class="modohome-catalog-teaser-arrow">&rarr;</span></span>
    </div>
  </a>
</article>''' for badge, slug, cat, title, desc in ITEMS)

html = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{THEME}</style><style>{catalog}</style><style>{inline}</style></head><body>
<section class="modohome-catalog modohome-catalog-latest" data-modohome-teaser
  style="--modohome-catalog-cols-desktop:4;--modohome-catalog-ratio:4 / 3">
  <div class="modohome-catalog-grid modohome-catalog-latest-grid">{cards}</div>
</section></body></html>"""

(scratch/'page-latest.html').write_text(html, encoding='utf-8')
print('  strona: page-latest.html')
