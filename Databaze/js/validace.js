/**
 * Pravidla validace registrace – KLIENTSKÁ STRANA.
 *
 * !!! Výrazy, limity i hlášky musí být stejné jako v php/validace.php a php/foto.php.
 * !!! Při jakékoli změně upravte OBA místa a spusťte:  node tests/parita.js
 *
 * Server je vždy rozhodující – tahle kontrola jen šetří uživateli čekání.
 * Výrazy mají příznak "u" (Unicode); PHP má /uD. Zápis výrazu je v obou jazycích stejný.
 */
(function (root) {
  'use strict';

  const PRAVIDLA = {
    name: {
      regex: /^[A-Za-zÀ-ÖØ-öø-ž]+(?:[ \-][A-Za-zÀ-ÖØ-öø-ž]+)*$/u,
      max: 50,
      zprava: 'Jméno smí obsahovat jen písmena, mezery a pomlčky (max. 50 znaků).',
    },
    surname: {
      regex: /^[A-Za-zÀ-ÖØ-öø-ž]+(?:[ \-][A-Za-zÀ-ÖØ-öø-ž]+)*$/u,
      max: 50,
      zprava: 'Příjmení smí obsahovat jen písmena, mezery a pomlčky (max. 50 znaků).',
    },
    email: {
      regex: /^[A-Za-z0-9]+(?:[._%+\-][A-Za-z0-9]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/u,
      max: 254,
      zprava: 'Zadejte platnou e-mailovou adresu (např. jmeno@example.com).',
    },
    phone_number: {
      regex: /^\+?(?:[0-9] ?){8,14}[0-9]$/u,
      max: null,
      zprava: 'Telefon musí mít 9–15 číslic, volitelně s předvolbou +; číslice smí oddělovat jen jedna mezera.',
    },
    reg_login: {
      regex: /^[A-Za-z0-9_]{4,20}$/u,
      max: null,
      zprava: 'Login musí mít 4–20 znaků: písmena bez diakritiky, číslice nebo podtržítko.',
    },
    password: {
      regex: /^(?=[\x20-\x7E]*[A-Za-z])(?=[\x20-\x7E]*[0-9])[\x20-\x7E]{8,64}$/u,
      max: null,
      zprava: 'Heslo musí mít 8–64 znaků bez diakritiky a obsahovat alespoň jedno písmeno a jednu číslici.',
    },
  };

  const POHLAVI = ['male', 'female', 'other'];
  const HLASKA_POVINNE = 'Toto pole je povinné.';
  const HLASKA_POHLAVI = 'Vyberte pohlaví.';

  const FOTO = {
    formaty: ['image/jpeg', 'image/png', 'image/webp'],
    maxBajtu: 2 * 1024 * 1024,
    minStrana: 200,
    maxStrana: 5000,
    maxPixelu: 16000000,
    hlasky: {
      chybi: 'Vyberte profilovou fotku.',
      format: 'Povolené formáty fotky jsou JPEG, PNG a WebP.',
      velikost: 'Fotka je příliš velká (maximum je 2 MB).',
      male: 'Fotka je příliš malá (minimum je 200 × 200 px).',
      velke: 'Fotka má příliš velké rozlišení (maximum je 5000 px na delší straně a 16 megapixelů).',
      neplatna: 'Soubor není platný obrázek.',
    },
  };

  /** Stejné ořezání jako PHP trim(): mezera, \t, \n, \r, \0, \x0B (JS trim() by ořezával i NBSP apod.). */
  function phpTrim(s) {
    return s.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
  }

  /** Chybová hláška, nebo null. Heslo se neořezává (stejně jako na serveru). */
  function validujPole(pole, hodnota) {
    const p = PRAVIDLA[pole];
    if (!p) return null;
    const v = pole === 'password' ? String(hodnota) : phpTrim(String(hodnota));
    if (v === '') return HLASKA_POVINNE;
    if (!p.regex.test(v)) return p.zprava;
    if (p.max !== null && [...v].length > p.max) return p.zprava;
    return null;
  }

  function validujPohlavi(hodnota) {
    return POHLAVI.includes(hodnota) ? null : HLASKA_POHLAVI;
  }

  /** Načte rozměry obrázku v prohlížeči. */
  function nactiRozmery(soubor) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(soubor);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        resolve({ sirka: img.naturalWidth, vyska: img.naturalHeight });
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('nelze nacist'));
      };
      img.src = url;
    });
  }

  /** Kontrola fotky (typ, velikost, rozlišení). Vrací Promise s hláškou, nebo null. */
  async function validujFoto(soubor) {
    const H = FOTO.hlasky;
    if (!soubor) return H.chybi;
    if (!FOTO.formaty.includes(soubor.type)) return H.format;
    if (soubor.size > FOTO.maxBajtu) return H.velikost;

    let r;
    try {
      r = await nactiRozmery(soubor);
    } catch (e) {
      return H.neplatna;
    }
    if (r.sirka < FOTO.minStrana || r.vyska < FOTO.minStrana) return H.male;
    if (r.sirka > FOTO.maxStrana || r.vyska > FOTO.maxStrana || r.sirka * r.vyska > FOTO.maxPixelu) return H.velke;
    return null;
  }

  const Validace = { PRAVIDLA, FOTO, phpTrim, validujPole, validujPohlavi, validujFoto };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = Validace;        // pro tests/parita.js (Node)
  } else {
    root.Validace = Validace;         // v prohlížeči
  }
})(typeof window !== 'undefined' ? window : globalThis);
