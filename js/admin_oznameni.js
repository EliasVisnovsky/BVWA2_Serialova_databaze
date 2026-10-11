/**
 * Formulář Hromadné oznámení: počítadlo znaků, kontrola prázdných polí a potvrzení před odesláním všem.
 */
(function () {
  'use strict';

  const form = document.getElementById('oznameni-formular');
  if (!form) return;

  const predmet = form.elements.predmet;
  const text = form.elements.text;
  const pocitadlo = document.getElementById('text-pocet');

  function aktualizujPocitadlo() {
    if (pocitadlo) pocitadlo.textContent = String(Array.from(text.value).length);
  }
  text.addEventListener('input', aktualizujPocitadlo);
  aktualizujPocitadlo();

  function ukazChybu(el, zprava) {
    const skupina = el.closest('.form-group');
    let box = skupina.querySelector('.field-error');
    if (!box) {
      box = document.createElement('div');
      box.className = 'field-error';
      box.id = 'err-' + el.name;
      skupina.appendChild(box);
    }
    box.textContent = zprava;
    el.classList.add('invalid');
    el.setAttribute('aria-invalid', 'true');
    el.setAttribute('aria-describedby', box.id);
  }

  function smazChybu(el) {
    const box = el.closest('.form-group').querySelector('.field-error');
    if (box) box.remove();
    el.classList.remove('invalid');
    el.removeAttribute('aria-invalid');
    el.removeAttribute('aria-describedby');
  }

  [predmet, text].forEach((el) => {
    el.addEventListener('input', () => {
      if (el.classList.contains('invalid') && el.value.trim() !== '') smazChybu(el);
    });
  });

  form.addEventListener('submit', (e) => {
    let prvni = null;
    [predmet, text].forEach((el) => {
      if (el.value.trim() === '') {
        ukazChybu(el, 'Toto pole je povinné.');
        if (prvni === null) prvni = el;
      } else {
        smazChybu(el);
      }
    });
    if (prvni !== null) {
      e.preventDefault();
      prvni.focus();
      return;
    }
    const n = form.dataset.prijemcu || '0';
    if (!window.confirm('Opravdu odeslat oznámení všem uživatelům (' + n + ')? Nejde to vzít zpět.')) {
      e.preventDefault();
    }
  });
})();
