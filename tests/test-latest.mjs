import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;
const dir = process.argv[2];
let passed = 0, failed = 0;
const ok = (l, c, d='') => { if (c) passed++; else { failed++; console.log(`  NIEPOWODZENIE: ${l}${d?'\n    '+d:''}`); } };

const browser = await chromium.launch();

async function look(width) {
  const page = await browser.newPage({ viewport: { width, height: 1100 } });
  await page.goto(`file://${dir}/page-latest.html`);
  await page.waitForLoadState('networkidle');
  const r = await page.evaluate(() => {
    const grid = document.querySelector('.modohome-catalog-latest-grid');
    const cols = getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length;
    const cards = [...document.querySelectorAll('.modohome-catalog-teaser')].map((card) => {
      const cb = card.getBoundingClientRect();
      const badge = card.querySelector('.modohome-catalog-teaser-badge').getBoundingClientRect();
      const media = card.querySelector('.modohome-catalog-teaser-media').getBoundingClientRect();
      const title = card.querySelector('.modohome-catalog-teaser-title');
      const tb = title.getBoundingClientRect();
      const cta = card.querySelector('.modohome-catalog-teaser-cta');
      return {
        title: title.textContent.trim().slice(0, 24),
        badgeSpansMedia: Math.abs(badge.width - media.width) < 2,
        badgeAtTop: Math.abs(badge.top - media.top) < 2,
        titleOverflows: tb.right - cb.right > 1,
        titleClipped: title.scrollWidth > title.clientWidth + 1,
        titleUppercase: getComputedStyle(title).textTransform === 'uppercase',
        ctaColor: getComputedStyle(cta).color,
        mediaRatio: +(media.width / media.height).toFixed(2),
      };
    });
    return { cols, cards, pageScroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
  });
  await page.close();
  return r;
}

for (const [name, w, expectCols] of [['telefon', 390, 1], ['tablet', 800, 2], ['komputer', 1400, 4]]) {
  const r = await look(w);
  console.log(`\n== ${name} (${w} px) — ${r.cols} kolumn ==`);
  for (const c of r.cards) {
    const bad = [];
    if (!c.badgeSpansMedia) bad.push('pasek etykiety nie na całą szerokość');
    if (!c.badgeAtTop) bad.push('etykieta nie przy górnej krawędzi');
    if (c.titleOverflows) bad.push('nazwa wystaje');
    if (c.titleClipped) bad.push('nazwa ucięta');
    console.log(`  ${c.title.padEnd(26)} ${bad.length ? bad.join(', ') : 'OK'}`);
  }
  ok(`${name}: ${expectCols} kolumny`, r.cols === expectCols, `jest ${r.cols}`);
  ok(`${name}: pasek etykiety na całą szerokość zdjęcia`, r.cards.every((c) => c.badgeSpansMedia));
  ok(`${name}: etykieta przy górnej krawędzi`, r.cards.every((c) => c.badgeAtTop));
  ok(`${name}: nazwy nie wystają i nie są ucięte`, r.cards.every((c) => !c.titleOverflows && !c.titleClipped));
  ok(`${name}: nazwy wersalikami zgodnie z projektem`, r.cards.every((c) => c.titleUppercase));
  // Testowy CSS ustawia kolor akcentu na #00aa55 — sprawdzamy, że wezwanie
  // bierze kolor z ustawień, a nie z zaszytej wartości.
  ok(`${name}: wezwanie w kolorze akcentu z ustawień`, r.cards.every((c) => c.ctaColor === 'rgb(0, 170, 85)'), r.cards[0].ctaColor);
  ok(`${name}: proporcje zdjęcia 4:3`, r.cards.every((c) => Math.abs(c.mediaRatio - 1.33) < 0.03), String(r.cards[0].mediaRatio));
  ok(`${name}: brak poziomego przewijania`, r.pageScroll <= 0, `${r.pageScroll} px`);
}

// Zrzut do podglądu.
const page = await browser.newPage({ viewport: { width: 1400, height: 800 }, deviceScaleFactor: 2 });
await page.goto(`file://${dir}/page-latest.html`);
await page.waitForLoadState('networkidle');
await page.locator('section').screenshot({ path: `${dir}/shot-latest.png` });
await page.close();

await browser.close();
console.log(`\nZaliczone: ${passed}   Niepowodzenia: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
