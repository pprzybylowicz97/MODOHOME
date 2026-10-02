import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;

const url = 'file://' + process.argv[2] + '/page.html';
let passed = 0, failed = 0;

function check(label, actual, expected) {
  if (actual === expected) { passed++; return; }
  failed++;
  console.log(`  NIEPOWODZENIE: ${label}\n    oczekiwano: ${expected}\n    otrzymano:  ${actual}`);
}

const browser = await chromium.launch();

/** Zwraca liczbę kolumn siatki i kilka innych wyliczonych wartości. */
async function measure(width, height = 900) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto(url);
  const data = await page.evaluate(() => {
    const grid = document.getElementById('grid');
    const cs = getComputedStyle(grid);
    const price = document.querySelector('.modohome-catalog-price');
    const media = document.querySelector('.modohome-catalog-card-media');
    return {
      columns: cs.gridTemplateColumns.split(' ').filter(Boolean).length,
      gap: cs.gap,
      priceColor: getComputedStyle(price).color,
      mediaRatio: (media.getBoundingClientRect().width / media.getBoundingClientRect().height).toFixed(2),
      bodyBg: getComputedStyle(document.getElementById('cat')).backgroundColor,
    };
  });
  await page.close();
  return data;
}

console.log('== Liczba kolumn przy ustawieniu telefon=1, tablet=2, komputer=4 ==');

const phone = await measure(390);
console.log(`  telefon  390 px -> ${phone.columns} kolumna(y)`);
check('telefon: 1 kolumna', phone.columns, 1);

const phoneWide = await measure(560);
check('telefon szerszy 560 px: nadal 1 kolumna', phoneWide.columns, 1);

const tablet = await measure(800);
console.log(`  tablet   800 px -> ${tablet.columns} kolumny`);
check('tablet: 2 kolumny', tablet.columns, 2);

const desktop = await measure(1400);
console.log(`  komputer 1400 px -> ${desktop.columns} kolumny`);
check('komputer: 4 kolumny', desktop.columns, 4);

console.log('== Pozostałe ustawienia wyglądu ==');

check('odstęp z ustawień (33 px)', phone.gap, '33px');
check('kolor akcentu z ustawień (#00aa55)', phone.priceColor, 'rgb(0, 170, 85)');
check('tło katalogu z ustawień (#f4f2ee)', phone.bodyBg, 'rgb(244, 242, 238)');
check('proporcje zdjęcia 3:4', phone.mediaRatio, '0.75');

await browser.close();

console.log('\n----------------------------------------');
console.log(`Zaliczone: ${passed}   Niepowodzenia: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
