<?php
declare(strict_types=1);

/**
 * Pravidla validace registrace – SERVEROVÁ STRANA.
 *
 * !!! Stejné výrazy, limity a hlášky jsou v js/validace.js. Při jakékoli změně
 * !!! upravte OBA soubory a spusťte:  node tests/parita.js
 *
 * Výrazy jsou zapsané bez oddělovačů a bez příznaků (v PHP se přidává /uD,
 * v JS /u), aby byl text výrazu v obou souborech znak po znaku stejný.
 *  - u: řetězce se berou jako Unicode (stejně jako v JS),
 *  - D: "$" platí jen na úplném konci (bez D by PHP "$" povolilo i koncový \n).
 */

const REGISTRACE_PRAVIDLA = [
    'name' => [
        'regex' => '^[A-Za-zÀ-ÖØ-öø-ž]+(?:[ \-][A-Za-zÀ-ÖØ-öø-ž]+)*$',
        'max'   => 50,
        'zprava' => 'Jméno smí obsahovat jen písmena, mezery a pomlčky (max. 50 znaků).',
    ],
    'surname' => [
        'regex' => '^[A-Za-zÀ-ÖØ-öø-ž]+(?:[ \-][A-Za-zÀ-ÖØ-öø-ž]+)*$',
        'max'   => 50,
        'zprava' => 'Příjmení smí obsahovat jen písmena, mezery a pomlčky (max. 50 znaků).',
    ],
    'email' => [
        'regex' => '^[A-Za-z0-9]+(?:[._%+\-][A-Za-z0-9]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$',
        'max'   => 254,
        'zprava' => 'Zadejte platnou e-mailovou adresu (např. jmeno@example.com).',
    ],
    'phone_number' => [
        'regex' => '^\+?(?:[0-9] ?){8,14}[0-9]$',
        'max'   => null,
        'zprava' => 'Telefon musí mít 9–15 číslic, volitelně s předvolbou +; číslice smí oddělovat jen jedna mezera.',
    ],
    'reg_login' => [
        'regex' => '^[A-Za-z0-9_]{4,20}$',
        'max'   => null,
        'zprava' => 'Login musí mít 4–20 znaků: písmena bez diakritiky, číslice nebo podtržítko.',
    ],
    'password' => [
        'regex' => '^(?=[\x20-\x7E]*[A-Za-z])(?=[\x20-\x7E]*[0-9])[\x20-\x7E]{8,64}$',
        'max'   => null,
        'zprava' => 'Heslo musí mít 8–64 znaků bez diakritiky a obsahovat alespoň jedno písmeno a jednu číslici.',
    ],
];

const REGISTRACE_POHLAVI = ['male', 'female', 'other'];
const HLASKA_POVINNE     = 'Toto pole je povinné.';
const HLASKA_POHLAVI     = 'Vyberte pohlaví.';

/** Stejné ořezání jako v JS (phpTrim): mezera, \t, \n, \r, \0, \x0B. */
function registrace_trim(string $v): string
{
    return trim($v);
}

/** Vrátí chybovou hlášku, nebo null, když je hodnota v pořádku. Hodnota už musí být oříznutá. */
function validuj_pole(string $pole, string $hodnota): ?string
{
    $p = REGISTRACE_PRAVIDLA[$pole] ?? null;
    if ($p === null) {
        return null;
    }
    if ($hodnota === '') {
        return HLASKA_POVINNE;
    }
    if (preg_match('/' . $p['regex'] . '/uD', $hodnota) !== 1) {   // 0 = nesedí, false = neplatné UTF-8
        return $p['zprava'];
    }
    if ($p['max'] !== null && mb_strlen($hodnota, 'UTF-8') > $p['max']) {
        return $p['zprava'];
    }
    return null;
}

/** Validace textových polí registrace. Vrací [pole => hláška]; prázdné pole = vše v pořádku. */
function validuj_registraci(array $vstup): array
{
    $chyby = [];
    foreach (array_keys(REGISTRACE_PRAVIDLA) as $pole) {
        $hodnota = (string)($vstup[$pole] ?? '');
        if ($pole !== 'password') {          // heslo se neořezává
            $hodnota = registrace_trim($hodnota);
        }
        if (($chyba = validuj_pole($pole, $hodnota)) !== null) {
            $chyby[$pole] = $chyba;
        }
    }
    if (!in_array((string)($vstup['gender'] ?? ''), REGISTRACE_POHLAVI, true)) {
        $chyby['gender'] = HLASKA_POHLAVI;
    }
    return $chyby;
}
