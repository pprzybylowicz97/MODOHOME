import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;
const dir = process.argv[2];
let passed = 0, failed = 0;
const ok = (l, c, d='') => { if (c) passed++; else { failed++; console.log(`  NIEPOWODZENIE: ${l}${d?'\n    '+d:''}`); } };

const browser = await chromium.launch();

/** Szuka elementów, których treść wychodzi poza własne pudełko lub poza kartę. */
async function inspect(width) {
  const page = await browser.newPage({ viewport: { width, height: 1100 } });
  await page.goto(`file://${dir}/page-theme.html`);
  await page.waitForLoadState('networkidle');
  const r = await page.evaluate(() => {
    const cat = document.getElementById('cat').getBoundingClientRect();
    const cards = [...document.querySelectorAll('.modohome-catalog-card')].map((card) => {
      const cb = card.getBoundingClientRect();
      const title = card.querySelector('.modohome-catalog-card-title');
      const tb = title.getBoundingClientRect();
      return {
        title: title.textContent.trim().slice(0, 28),
        cardOverflowsCatalog: +(cb.right - cat.right).toFixed(1),
        titleOverflowsCard: +(tb.right - cb.right).toFixed(1),
        titleClipped: title.scrollWidth > title.clientWidth + 1,
        cardScrollOverflow: card.scrollWidth > card.clientWidth + 1,
      };
    });
    return {
      cards,
      pageScroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
  await page.close();
  return r;
}

for (const [name, w] of [['telefon', 390], ['tablet', 768], ['komputer', 1400]]) {
  const r = await inspect(w);
  console.log(`\n== ${name} (${w} px) ==`);
  console.log(`  poziome przewijanie strony: ${r.pageScroll} px`);
  for (const c of r.cards) {
    const flags = [];
    if (c.titleOverflowsCard > 1) flags.push(`nazwa wystaje o ${c.titleOverflowsCard} px`);
    if (c.titleClipped) flags.push('nazwa ucięta');
    if (c.cardScrollOverflow) flags.push('treść szersza niż karta');
    if (c.cardOverflowsCatalog > 1) flags.push(`karta wystaje o ${c.cardOverflowsCatalog} px`);
    console.log(`  ${c.title.padEnd(30)} ${flags.length ? flags.join(', ') : 'OK'}`);
  }
  ok(`${name}: brak poziomego przewijania strony`, r.pageScroll <= 0, `${r.pageScroll} px`);
  ok(`${name}: żadna nazwa nie wystaje poza kartę`, r.cards.every((c) => c.titleOverflowsCard <= 1));
  ok(`${name}: żadna nazwa nie jest ucięta`, r.cards.every((c) => !c.titleClipped));
  ok(`${name}: treść mieści się w kartach`, r.cards.every((c) => !c.cardScrollOverflow));
  ok(`${name}: karty mieszczą się w katalogu`, r.cards.every((c) => c.cardOverflowsCatalog <= 1));
}

await browser.close();
console.log(`\nZaliczone: ${passed}   Niepowodzenia: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
