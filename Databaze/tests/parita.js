/**
 * Ověří, že JS (js/validace.js) a PHP (php/validace.php) validují STEJNĚ:
 *   1) text výrazů, limity a hlášky jsou znak po znaku shodné,
 *   2) obě strany dávají na všech testovacích vektorech stejný výsledek,
 *   3) ten výsledek odpovídá očekávání zapsanému v tests/vektory.json.
 *
 * Spuštění (z kořene projektu):  node tests/parita.js     (vyžaduje php i node v PATH)
 */
'use strict';
const { spawnSync } = require('child_process');
const path = require('path');
const Validace = require('../js/validace.js');
const vektory = require('./vektory.json');

const vysl = spawnSync('php', [path.join(__dirname, 'parita.php')], { encoding: 'utf8' });
if (vysl.error || vysl.status !== 0) {
  console.error('Nepodařilo se spustit PHP (tests/parita.php):', vysl.error ? vysl.error.message : vysl.stderr);
  process.exit(2);
}
const php = JSON.parse(vysl.stdout);

let chyb = 0;
const selhani = (zprava) => { chyb++; console.error('  ✗ ' + zprava); };
const zobraz = (s) => JSON.stringify(s);

for (const pole of Object.keys(vektory)) {
  const js = Validace.PRAVIDLA[pole];
  const ph = php.pravidla[pole];
  if (!js || !ph) { selhani(`${pole}: pravidlo chybí v JS nebo v PHP`); continue; }

  if (js.regex.source !== ph.regex) selhani(`${pole}: výraz se liší\n      JS : ${js.regex.source}\n      PHP: ${ph.regex}`);
  if (js.max !== ph.max)            selhani(`${pole}: max se liší (JS ${js.max}, PHP ${ph.max})`);
  if (js.zprava !== ph.zprava)      selhani(`${pole}: hláška se liší`);

  vektory[pole].forEach(([hodnota, ocekavano], i) => {
    const jsOk = Validace.validujPole(pole, hodnota) === null;
    const phpOk = php.vysledky[pole][i];
    if (jsOk !== phpOk) selhani(`${pole} ${zobraz(hodnota)}: JS=${jsOk}, PHP=${phpOk} (neshoda!)`);
    else if (jsOk !== ocekavano) selhani(`${pole} ${zobraz(hodnota)}: obě strany ${jsOk}, očekáváno ${ocekavano}`);
  });
}

const pocet = Object.values(vektory).reduce((s, a) => s + a.length, 0);
if (chyb) {
  console.error(`\nSELHALO: ${chyb} problémů (${pocet} případů).`);
  process.exit(1);
}
console.log(`OK: JS a PHP se shodují na všech ${pocet} případech i ve všech výrazech.`);
