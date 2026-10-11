/**
 * Registrační formulář: validace při psaní, odeslání na register.php bez přenačtení stránky
 * a zobrazení chyb (i těch, které zná jen server – např. "login už existuje") pod příslušným polem.
 * Pravidla jsou v js/validace.js (musí být načtený dřív).
 */
(function () {
  'use strict';

  const form = document.getElementById('registerFormEl');
  if (!form || !window.Validace) return;

  const V = window.Validace;
  const obecnaChyba = document.getElementById('registerAlert');
  const loginInfo = document.getElementById('loginInfo');
  const tlacitko = form.querySelector('button[type="submit"]');
  const textTlacitka = tlacitko.textContent;

  // Pořadí odpovídá pořadí polí ve formuláři (podle něj se vybírá, na které pole se zaměřit).
  const PORADI = ['name', 'surname', 'email', 'phone_number', 'gender', 'pfp', 'reg_login', 'password'];
  let odesilani = false;

  // ---------- zobrazení chyb ----------

  function vstup(pole) {
    return form.elements[pole];
  }

  function ukazChybu(pole, zprava) {
    const el = vstup(pole);
    if (!el) return;
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
    if (!el) return;
    const box = el.closest('.form-group').querySelector('.field-error');
    if (box) box.remove();
    el.classList.remove('invalid');
    el.removeAttribute('aria-invalid');
    el.removeAttribute('aria-describedby');
  }

  function ukazObecnou(zprava) {
    obecnaChyba.textContent = zprava;
    obecnaChyba.hidden = false;
  }

  function skryjObecnou() {
    obecnaChyba.hidden = true;
    obecnaChyba.textContent = '';
  }

  function ukazChyby(chyby) {
    const obecne = [];
    for (const [pole, zprava] of Object.entries(chyby)) {
      if (PORADI.includes(pole)) ukazChybu(pole, zprava);
      else obecne.push(zprava);              // "_obecna" nebo neznámé pole
    }
    if (obecne.length) ukazObecnou(obecne.join(' '));
    const prvni = PORADI.find((p) => chyby[p]);
    if (prvni) vstup(prvni).focus();
  }

  // ---------- kontrola jednoho pole ----------

  async function zkontroluj(pole) {
    const el = vstup(pole);
    if (pole === 'pfp') return V.validujFoto(el.files[0]);
    if (pole === 'gender') return V.validujPohlavi(el.value);
    return V.validujPole(pole, el.value);
  }

  async function zkontrolujAZobraz(pole) {
    const chyba = await zkontroluj(pole);
    if (chyba) ukazChybu(pole, chyba);
    else smazChybu(pole);
    return chyba;
  }

  // Při opuštění pole se zkontroluje; když už chyba svítí, mizí hned při opravě (i serverová).
  PORADI.forEach((pole) => {
    const el = vstup(pole);
    if (!el) return;
    el.addEventListener(pole === 'pfp' || pole === 'gender' ? 'change' : 'blur', () => {
      // prázdné pole, ve kterém uživatel ještě nic nepsal, nehlásíme hned při průchodu tabulátorem
      if ((pole !== 'pfp' && pole !== 'gender') && el.value === '' && !el.classList.contains('invalid')) return;
      zkontrolujAZobraz(pole);
    });
    el.addEventListener('input', () => {
      if (el.classList.contains('invalid')) zkontrolujAZobraz(pole);
    });
  });

  // ---------- odeslání ----------

  function hotovo(zprava) {
    const login = vstup('reg_login').value;
    form.reset();
    PORADI.forEach(smazChybu);
    skryjObecnou();
    document.getElementById('login_user').value = login;     // předvyplní přihlašovací formulář
    zobrazUspech(zprava);
    // přepnout na přihlášení jen když je právě vidět registrace (toggleForms jen přehazuje stav)
    const reg = document.getElementById('registerForm');
    if (reg && reg.classList.contains('active') && typeof window.toggleForms === 'function') window.toggleForms();
  }

  function zobrazUspech(zprava) {
    loginInfo.textContent = zprava;
    loginInfo.hidden = false;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (odesilani) return;
    skryjObecnou();

    const chyby = {};
    for (const pole of PORADI) {
      const chyba = await zkontrolujAZobraz(pole);
      if (chyba) chyby[pole] = chyba;
    }
    if (Object.keys(chyby).length) {
      vstup(PORADI.find((p) => chyby[p])).focus();
      return;
    }

    odesilani = true;
    tlacitko.disabled = true;
    tlacitko.textContent = '…';
    try {
      const odpoved = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      });
      let data = null;
      try { data = await odpoved.json(); } catch (_) { /* odpověď nebyla JSON */ }

      if (odpoved.ok && data && data.ok) hotovo(data.zprava);
      else if (data && data.chyby) ukazChyby(data.chyby);
      else ukazObecnou('Server vrátil neočekávanou odpověď (' + odpoved.status + '). Zkuste to prosím později.');
    } catch (_) {
      ukazObecnou('Nepodařilo se spojit se serverem. Zkontrolujte připojení a zkuste to znovu.');
    } finally {
      odesilani = false;
      tlacitko.disabled = false;
      tlacitko.textContent = textTlacitka;
    }
  });

  // Přesměrování zpět z register.php (varianta bez JS): ?registrace=ok
  if (new URLSearchParams(location.search).get('registrace') === 'ok') {
    zobrazUspech('Registrace proběhla úspěšně. Nyní se můžete přihlásit.');
    history.replaceState(null, '', location.pathname);
  }
})();
