import sys, pathlib
scratch, plugin = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
catalog = (plugin/'assets/css/catalog.css').read_text(encoding='utf-8')

imgs = [('tall.png','Zrzut ekranu'), ('wide.png','Zdjęcie poziome'), ('square.png','Kwadrat')]

def cards():
    out = []
    for i, (src, label) in enumerate(imgs, 1):
        out.append(f'''<article class="modohome-catalog-card">
  <button type="button" class="modohome-catalog-card-link">
    <div class="modohome-catalog-card-media">
      <img class="modohome-catalog-card-image" id="img{i}" src="{src}" alt="{label}">
    </div>
    <div class="modohome-catalog-card-body">
      <h3 class="modohome-catalog-card-title">{label}</h3>
      <p class="modohome-catalog-card-prices"><span class="modohome-catalog-price">{i}00 zł</span></p>
    </div>
  </button>
</article>''')
    return '\n'.join(out)

for mode, cssfile, extra_class in [
    ('fixed', 'css-fixed.css', ''),
    ('contain', 'css-contain.css', ''),
    ('auto', 'css-auto.css', ' modohome-catalog--media-auto'),
]:
    inline = (scratch/cssfile).read_text(encoding='utf-8')
    html = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{catalog}</style><style>{inline}</style></head><body>
<div class="modohome-catalog{extra_class}" id="cat" style="--modohome-catalog-cols-desktop:3">
  <div class="modohome-catalog-grid" id="grid">{cards()}</div>
</div></body></html>"""
    (scratch/f'page-{mode}.html').write_text(html, encoding='utf-8')
    print(f'  strona: page-{mode}.html')

# Strona okna modalnego z pionowym zrzutem ekranu.
inline = (scratch/'css-auto.css').read_text(encoding='utf-8')
modal = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{catalog}</style><style>{inline}</style></head><body>
<div class="modohome-catalog modohome-catalog-modal" role="dialog" aria-modal="true">
  <button class="modohome-catalog-modal-backdrop"></button>
  <div class="modohome-catalog-modal-dialog">
    <button class="modohome-catalog-modal-close">&times;</button>
    <div class="modohome-catalog-modal-body"><div class="modohome-catalog-modal-layout">
      <div class="modohome-catalog-modal-gallery"><div class="modohome-catalog-modal-stage">
        <img class="modohome-catalog-modal-image" id="mimg" src="tall.png" alt="Zrzut ekranu">
      </div></div>
      <div class="modohome-catalog-modal-details">
        <h2 class="modohome-catalog-modal-title">Produkt</h2>
        <p class="modohome-catalog-modal-prices"><span class="modohome-catalog-price">199 zł</span></p>
      </div>
    </div></div>
  </div>
</div></body></html>"""
(scratch/'page-modal.html').write_text(modal, encoding='utf-8')
print('  strona: page-modal.html')

# Strona do testu kolumn — karty bez zdjęć, pełna struktura.
inline = (scratch/'inline.css').read_text(encoding='utf-8')
simple = '\n'.join(
    f'''<article class="modohome-catalog-card"><button type="button" class="modohome-catalog-card-link">
    <div class="modohome-catalog-card-media"><span class="modohome-catalog-card-placeholder"></span></div>
    <div class="modohome-catalog-card-body"><h3 class="modohome-catalog-card-title">P{i}</h3>
    <p class="modohome-catalog-card-prices"><span class="modohome-catalog-price">{i}00 zł</span></p>
    </div></button></article>''' for i in range(1, 7))
page = f"""<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>{catalog}</style><style>{inline}</style></head><body>
<div class="modohome-catalog" id="cat" style="--modohome-catalog-cols-desktop:4">
  <div class="modohome-catalog-grid" id="grid">{simple}</div>
</div></body></html>"""
(scratch/'page.html').write_text(page, encoding='utf-8')
print('  strona: page.html')
