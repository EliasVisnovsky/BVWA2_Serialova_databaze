/**
 * Formulář Upravit údaje: kontrola polí při opuštění / při odeslání a náhled nové fotky.
 * Pravidla jsou v js/validace.js (musí být načtený dřív). 
 */
(function () {
  'use strict';

  const form = document.getElementById('uprava-formular');
  if (!form || !window.Validace) return;

  const V = window.Validace;
  const PORADI = ['pfp', 'name', 'surname', 'email', 'phone_number', 'gender'];
  const nahled = document.getElementById('foto-nahled');
  const puvodniFoto = nahled ? nahled.src : '';

  function vstup(pole) {
    return form.elements[pole];
  }

  function ukazChybu(pole, zprava) {
    const el = vstup(pole);
    const skupina = el.closest('.form-group');
    let box = skupina.querySelector('.field-error');
    if (!box) {
      box = document.createElement('div');
      box.className = 'field-error';
      box.id = 'err-' + pole;
      skupina.appendChild(box);
    }
    box.textContent = zprava;
    el.classList.add('invalid');
    el.setAttribute('aria-invalid', 'true');
    el.setAttribute('aria-describedby', box.id);
  }

  function smazChybu(pole) {
    const el = vstup(pole);
    const box = el.closest('.form-group').querySelector('.field-error');
    if (box) box.remove();
    el.classList.remove('invalid');
    el.removeAttribute('aria-invalid');
    el.removeAttribute('aria-describedby');
  }

  async function zkontroluj(pole) {
    const el = vstup(pole);
    if (pole === 'pfp') return el.files.length ? V.validujFoto(el.files[0]) : null;  
    if (pole === 'gender') return V.validujPohlavi(el.value);
    return V.validujPole(pole, el.value);
  }

  async function zkontrolujAZobraz(pole) {
    const chyba = await zkontroluj(pole);
    if (chyba) ukazChybu(pole, chyba);
    else smazChybu(pole);
    return chyba;
  }

  PORADI.forEach((pole) => {
    const el = vstup(pole);
    el.addEventListener(pole === 'pfp' || pole === 'gender' ? 'change' : 'blur', () => zkontrolujAZobraz(pole));
    el.addEventListener('input', () => {
      if (el.classList.contains('invalid')) zkontrolujAZobraz(pole);
    });
  });

  // Náhled fotky 
  vstup('pfp').addEventListener('change', async (e) => {
    const soubor = e.target.files[0];
    if (!nahled) return;
    if (soubor && !(await V.validujFoto(soubor))) {
      nahled.src = URL.createObjectURL(soubor);
    } else {
      nahled.src = puvodniFoto;
    }
  });

  let kontroluje = false;
  form.addEventListener('submit', async (e) => {
    if (kontroluje) return;
    e.preventDefault();
    kontroluje = true;

    const chyby = {};
    for (const pole of PORADI) {
      const chyba = await zkontrolujAZobraz(pole);
      if (chyba) chyby[pole] = chyba;
    }
    const prvni = PORADI.find((p) => chyby[p]);
    if (prvni) {
      vstup(prvni).focus();
      kontroluje = false;
      return;
    }
    HTMLFormElement.prototype.submit.call(form);   
  });
})();
