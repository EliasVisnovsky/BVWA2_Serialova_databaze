/**
 * Formulář Změna přihlašovacích údajů: kontrola polí při opuštění / při odeslání.
 * Pravidla loginu a hesla jsou v js/validace.js
 */
(function () {
  'use strict';

  const form = document.getElementById('prihlaseni-formular');
  if (!form || !window.Validace) return;

  const V = window.Validace;
  const PORADI = ['new_login', 'new_password', 'new_password2', 'current_password'];
  const puvodniLogin = form.elements.new_login.dataset.puvodni || '';

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

  function menimLogin() {
    return V.phpTrim(vstup('new_login').value) !== puvodniLogin;
  }

  function menimHeslo() {
    return vstup('new_password').value !== '' || vstup('new_password2').value !== '';
  }

  function zkontroluj(pole) {
    switch (pole) {
      case 'new_login':
        return menimLogin() ? V.validujPole('reg_login', vstup(pole).value) : null;
      case 'new_password':
        return menimHeslo() ? V.validujPole('password', vstup(pole).value) : null;
      case 'new_password2':
        return menimHeslo() && !V.validujPole('password', vstup('new_password').value)
          && vstup('new_password').value !== vstup(pole).value
          ? 'Hesla se neshodují.' : null;
      case 'current_password':
        return vstup(pole).value === '' ? 'Toto pole je povinné.' : null;
      default:
        return null;
    }
  }

  function zkontrolujAZobraz(pole) {
    const chyba = zkontroluj(pole);
    if (chyba) ukazChybu(pole, chyba);
    else smazChybu(pole);
    return chyba;
  }

  PORADI.forEach((pole) => {
    const el = vstup(pole);
    el.addEventListener('blur', () => {
      zkontrolujAZobraz(pole);
      if (pole === 'new_password' && vstup('new_password2').value !== '') zkontrolujAZobraz('new_password2');
    });
    el.addEventListener('input', () => {
      if (el.classList.contains('invalid')) zkontrolujAZobraz(pole);
    });
  });

  form.addEventListener('submit', (e) => {
    let prvni = null;
    PORADI.forEach((pole) => {
      if (zkontrolujAZobraz(pole) && prvni === null) prvni = pole;
    });

    let obecna = form.querySelector('.alert-obecna');
    if (prvni === null && !menimLogin() && !menimHeslo()) {
      if (!obecna) {
        obecna = document.createElement('div');
        obecna.className = 'alert alert-error alert-obecna';
        obecna.setAttribute('role', 'alert');
        form.insertBefore(obecna, form.firstChild);
      }
      obecna.textContent = 'Nezměnili jste login ani heslo.';
      e.preventDefault();
      return;
    }
    if (obecna) obecna.remove();

    if (prvni !== null) {
      e.preventDefault();
      vstup(prvni).focus();
    }
  });
})();
