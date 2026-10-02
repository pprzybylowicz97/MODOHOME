import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;
const dir = process.argv[2];
let passed = 0, failed = 0;
const ok = (l, c, d='') => { if (c) passed++; else { failed++; console.log(`  NIEPOWODZENIE: ${l}${d?'\n    '+d:''}`); } };

const browser = await chromium.launch();

for (const [name, w, h] of [['komputer', 1400, 900], ['telefon', 390, 844]]) {
  const page = await browser.newPage({ viewport: { width: w, height: h } });
  await page.goto(`file://${dir}/page-modal.html`);
  await page.waitForLoadState('networkidle');
  const r = await page.evaluate(() => {
    const img = document.getElementById('mimg');
    const b = img.getBoundingClientRect();
    const dialog = document.querySelector('.modohome-catalog-modal-dialog').getBoundingClientRect();
    return {
      natural: +(img.naturalWidth / img.naturalHeight).toFixed(3),
      shown: +(b.width / b.height).toFixed(3),
      h: Math.round(b.height),
      w: Math.round(b.width),
      insideViewport: b.top >= -1 && b.bottom <= window.innerHeight + 1,
      dialogFits: dialog.height <= window.innerHeight + 1,
    };
  });
  console.log(`  ${name}: zdjęcie ${r.w}x${r.h}, proporcje ${r.shown} (naturalne ${r.natural})`);
  ok(`${name}: proporcje zdjęcia zachowane`, Math.abs(r.shown - r.natural) < 0.02, `${r.shown} vs ${r.natural}`);
  ok(`${name}: zdjęcie mieści się w oknie`, r.insideViewport);
  ok(`${name}: okno modalne nie wychodzi poza ekran`, r.dialogFits);
  await page.screenshot({ path: `${dir}/shot-modal-${name}.png` });
  await page.close();
}

await browser.close();
console.log(`\nZaliczone: ${passed}   Niepowodzenia: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
