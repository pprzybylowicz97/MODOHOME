import pw from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pw;

const dir = process.argv[2];
let passed = 0, failed = 0;

function ok(label, cond, detail = '') {
  if (cond) { passed++; return; }
  failed++;
  console.log(`  NIEPOWODZENIE: ${label}${detail ? '\n    ' + detail : ''}`);
}

const browser = await chromium.launch();

/** Mierzy, ile ze zdjęcia faktycznie widać w kafelku. */
async function measure(mode, width = 1200) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  await page.goto(`file://${dir}/page-${mode}.html`);
  await page.waitForLoadState('networkidle');
  const data = await page.evaluate(() => {
    return [...document.querySelectorAll('.modohome-catalog-card-image')].map((img) => {
      const box = img.getBoundingClientRect();
      const media = img.closest('.modohome-catalog-card-media').getBoundingClientRect();
      const natural = img.naturalWidth / img.naturalHeight;
      const fit = getComputedStyle(img).objectFit;

      // Rzeczywisty obszar zajęty przez treść obrazka wewnątrz jego pudełka.
      let drawnW, drawnH;
      const boxRatio = box.width / box.height;
      if (fit === 'cover') {
        drawnW = box.width; drawnH = box.height;          // kadr wypełniony, reszta przycięta
      } else {
        if (natural > boxRatio) { drawnW = box.width; drawnH = box.width / natural; }
        else { drawnH = box.height; drawnW = box.height * natural; }
      }

      return {
        alt: img.alt,
        natural: +natural.toFixed(3),
        fit,
        // Jaka część zdjęcia jest widoczna (1 = całe).
        visible: fit === 'cover'
          ? +Math.min(1, (natural > boxRatio ? boxRatio / natural : natural / boxRatio)).toFixed(3)
          : 1,
        // Jaka część kafelka to puste tło.
        waste: +(1 - (drawnW * drawnH) / (media.width * media.height)).toFixed(3),
        drawnW: Math.round(drawnW),
        drawnH: Math.round(drawnH),
      };
    });
  });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  await page.close();
  return { data, overflow };
}

for (const mode of ['fixed', 'contain', 'auto']) {
  const { data, overflow } = await measure(mode);
  console.log(`\n== tryb: ${mode} ==`);
  for (const d of data) {
    console.log(`  ${d.alt.padEnd(16)} proporcje ${d.natural}  widać ${(d.visible*100).toFixed(0)}%  puste tło ${(d.waste*100).toFixed(0)}%  rozmiar ${d.drawnW}x${d.drawnH}`);
  }
  ok(`${mode}: brak poziomego przewijania`, !overflow);

  if (mode === 'fixed') {
    const tall = data.find((d) => d.alt === 'Zrzut ekranu');
    ok('tryb 3:4 + cover faktycznie przycina zrzut ekranu', tall.visible < 0.7,
       `widać tylko ${(tall.visible*100).toFixed(0)}% zdjęcia`);
  }

  if (mode === 'contain') {
    const tall = data.find((d) => d.alt === 'Zrzut ekranu');
    ok('tryb contain pokazuje całe zdjęcie', tall.visible === 1);
    ok('ale zostawia dużo pustego tła przy zrzucie ekranu', tall.waste > 0.3,
       `puste tło ${(tall.waste*100).toFixed(0)}%`);
  }

  if (mode === 'auto') {
    for (const d of data) {
      ok(`auto: całe zdjęcie widoczne — ${d.alt}`, d.visible === 1);
      ok(`auto: bez pustego tła — ${d.alt}`, d.waste <= 0.02,
         `puste tło ${(d.waste*100).toFixed(0)}%`);
    }
  }
}

// Telefon — ten sam układ przy jednej kolumnie.
const phone = await measure('auto', 390);
console.log('\n== tryb auto, telefon 390 px ==');
for (const d of phone.data) {
  console.log(`  ${d.alt.padEnd(16)} rozmiar ${d.drawnW}x${d.drawnH}, puste tło ${(d.waste*100).toFixed(0)}%`);
}
ok('telefon: brak poziomego przewijania', !phone.overflow);
ok('telefon: całe zdjęcia widoczne', phone.data.every((d) => d.visible === 1));

await browser.close();
console.log('\n----------------------------------------');
console.log(`Zaliczone: ${passed}   Niepowodzenia: ${failed}`);
process.exit(failed > 0 ? 1 : 0);
