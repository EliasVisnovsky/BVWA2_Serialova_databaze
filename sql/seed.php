<?php
declare(strict_types=1);

/**
 * Naplnění databáze ukázkovými daty (admin, uživatelé, seriály, hodnocení, watchlist, zprávy, notifikace).
 *
 * Spuštění z kořene projektu:
 *   php sql/seed.php                     jen do prázdné databáze
 *   php sql/seed.php --force             smaže stávající uživatele a seriály (včetně jejich dat) a nahraje znovu
 *   php sql/seed.php --admin-pass=Tajne1234   vlastní heslo admina (jinak se vygeneruje a vypíše)
 *
 * Tabulka zanry zůstává beze změny. Ukázkoví uživatelé mají všichni heslo uvedené v DEMO_HESLO.
 * Skript jde spustit jen z příkazové řádky (vytváří účet admina).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Tento skript jde spustit jen z příkazové řádky.\n");
}

require __DIR__ . '/../php/db.php';          // $pdo
require __DIR__ . '/../php/crypto.php';      // encrypt()
require __DIR__ . '/../php/validace.php';    // ať ukázková data odpovídají pravidlům registrace

const DEMO_HESLO = 'Demo1234';
$koren = dirname(__DIR__);

// ---------- argumenty ----------
$force = in_array('--force', $argv, true);
$adminHeslo = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--admin-pass=')) {
        $adminHeslo = substr($arg, strlen('--admin-pass='));
    }
}
if ($adminHeslo === null) {
    do {   // náhodné heslo, které splní pravidla (písmeno + číslice)
        $adminHeslo = substr(strtr(base64_encode(random_bytes(24)), '+/=', 'xyz'), 0, 16);
    } while (!preg_match('/[A-Za-z]/', $adminHeslo) || !preg_match('/[0-9]/', $adminHeslo));
    $adminHesloVygenerovano = true;
}

// ---------- data ----------
// login, jméno, příjmení, e-mail, telefon, pohlaví, barva avatara, stáří účtu ve dnech
$uzivatele = [
    ['tereza_n',   'Tereza',   'Nováková',    'tereza.novakova@example.com',    '+420 777 000 101', 'female', [196, 92, 122], 120],
    ['jakub_s',    'Jakub',    'Svoboda',     'jakub.svoboda@example.com',      '+420 777 000 102', 'male',   [64, 105, 153], 100],
    ['lucie_d',    'Lucie',    'Dvořáková',   'lucie.dvorakova@example.com',    '+420 777 000 103', 'female', [84, 140, 110], 90],
    ['martin_c',   'Martin',   'Černý',       'martin.cerny@example.com',       '+420 777 000 104', 'male',   [150, 110, 70], 75],
    ['eliska_p',   'Eliška',   'Procházková', 'eliska.prochazkova@example.com', '+420 777 000 105', 'female', [120, 90, 160], 60],
    ['ondra_h',    'Ondřej',   'Horák',       'ondrej.horak@example.com',       '+420 777 000 106', 'male',   [70, 130, 140], 45],
    ['karolina_v', 'Karolína', 'Veselá',      'karolina.vesela@example.com',    '+420 777 000 107', 'female', [190, 120, 70], 30],
    ['alex_k',     'Alex',     'Kříž',        'alex.kriz@example.com',          '+420 777 000 108', 'other',  [100, 100, 110], 14],
];
$admin = ['admin', 'Admin', 'Správce', 'admin@example.com', '+420 777 000 100', 'other', [60, 60, 70], 200];

// název, rok, žánry, popis
$serialy = [
    ['Breaking Bad', 2008, ['Drama', 'Krimi', 'Thriller'],
        'Středoškolský učitel chemie dostane diagnózu rakoviny a aby zajistil rodinu, začne vařit metamfetamin. Příběh postupného úpadku a touhy po moci.'],
    ['Better Call Saul', 2015, ['Drama', 'Krimi'],
        'Příběh právníka Jimmyho McGilla a jeho cesty k přezdívce Saul Goodman; děj navazuje na svět Breaking Bad.'],
    ['Dark', 2017, ['Sci-Fi', 'Drama', 'Thriller'],
        'Německý seriál o zmizení dětí v malém městě Winden, které odhaluje tajemství čtyř rodin a cestování časem.'],
    ['Chernobyl', 2019, ['Drama', 'Thriller'],
        'Minisérie o havárii jaderné elektrárny v Černobylu v roce 1986 a o lidech, kteří řešili její následky.'],
    ['Stranger Things', 2016, ['Sci-Fi', 'Horor', 'Drama'],
        'V městečku Hawkins v 80. letech zmizí chlapec a skupina přátel odhalí tajný vládní experiment a paralelní dimenzi.'],
    ['Black Mirror', 2011, ['Sci-Fi', 'Thriller', 'Drama'],
        'Antologie samostatných příběhů o tom, jak moderní technologie mění lidské životy – často temně a znepokojivě.'],
    ['Game of Thrones', 2011, ['Fantasy', 'Drama'],
        'Šlechtické rody bojují o Železný trůn v královstvích Westerosu, zatímco za Zdí se probouzí starší hrozba.'],
    ['The Witcher', 2019, ['Fantasy', 'Drama'],
        'Lovec netvorů Geralt z Rivie hledá své místo ve světě plném politiky a magie; jeho osud se propojí s princeznou Ciri.'],
    ['Arcane', 2021, ['Animovaný', 'Fantasy', 'Sci-Fi', 'Drama'],
        'Animovaný příběh ze světa League of Legends o sestrách Vi a Jinx a o rozdělených městech Piltover a Zaun.'],
    ['Rick and Morty', 2013, ['Animovaný', 'Komedie', 'Sci-Fi'],
        'Výstřední vědec Rick bere svého vnuka Mortyho na nebezpečná dobrodružství napříč vesmíry a dimenzemi.'],
    ['The Office', 2005, ['Komedie'],
        'Mockumentární sitcom o všedních dnech zaměstnanců papírenské firmy Dunder Mifflin ve Scrantonu.'],
    ['Sherlock', 2010, ['Krimi', 'Drama', 'Thriller'],
        'Moderní adaptace příběhů Arthura Conana Doyla – Sherlock Holmes a doktor Watson řeší případy v dnešním Londýně.'],
    ['Planet Earth II', 2016, ['Dokumentární'],
        'Přírodopisný dokument BBC o životě zvířat v různých koutech světa, natočený v mimořádné obrazové kvalitě.'],
    ['The Mandalorian', 2019, ['Sci-Fi'],
        'Osamělý lovec odměn se v době po pádu Impéria stará o záhadné dítě; příběh z univerza Star Wars.'],
    ['Fleabag', 2016, ['Komedie', 'Drama'],
        'Černá komedie o neklidné mladé ženě v Londýně, která se vyrovnává se ztrátou, rodinou i láskou.'],
];

// seriál => [[login, hvězdy, komentář|null], ...]
$hodnoceni = [
    'Breaking Bad'     => [['tereza_n', 5, 'Jeden z nejlepších seriálů vůbec, finále sedělo.'], ['jakub_s', 5, null], ['martin_c', 5, 'Proměna Waltera je fascinující.'], ['ondra_h', 4, null]],
    'Better Call Saul' => [['jakub_s', 5, 'Podle mě ještě lepší než Breaking Bad.'], ['martin_c', 4, 'Pomalejší rozjezd, ale stojí to za to.'], ['lucie_d', 4, null]],
    'Dark'             => [['lucie_d', 5, 'Chce to tužku a papír na rodokmeny, ale je to skvělé.'], ['eliska_p', 5, null], ['alex_k', 4, 'Zamotané, ale uspokojivě uzavřené.'], ['tereza_n', 4, null]],
    'Chernobyl'        => [['ondra_h', 5, 'Mrazivě realistické.'], ['karolina_v', 5, null], ['jakub_s', 4, null]],
    'Stranger Things'  => [['eliska_p', 4, 'Skvělá nostalgie po 80. letech.'], ['karolina_v', 4, null], ['alex_k', 3, 'První série nejlepší, další už slabší.'], ['tereza_n', 4, null]],
    'Black Mirror'     => [['alex_k', 5, 'Některé epizody se hodí vidět jen jednou, ale zůstanou s vámi.'], ['ondra_h', 4, null], ['martin_c', 4, null]],
    'Game of Thrones'  => [['martin_c', 4, 'Prvních pět sérií výborných, závěr zklamal.'], ['karolina_v', 5, null], ['tereza_n', 4, null]],
    'The Witcher'      => [['ondra_h', 4, 'Henry Cavill jako Geralt sedí.'], ['lucie_d', 3, null], ['eliska_p', 4, null]],
    'Arcane'           => [['lucie_d', 5, 'Animace i hudba jsou naprosto úžasné.'], ['alex_k', 5, null], ['eliska_p', 5, 'Nemusíte znát hru, aby vás to vtáhlo.'], ['jakub_s', 5, null]],
    'Rick and Morty'   => [['jakub_s', 4, null], ['alex_k', 5, 'Absurdní humor, který mi sedí.'], ['ondra_h', 4, null]],
    'The Office'       => [['tereza_n', 5, 'Seriál na odpočinek, pokaždé mě rozesměje.'], ['lucie_d', 4, null], ['karolina_v', 4, null]],
    'Sherlock'         => [['eliska_p', 4, null], ['martin_c', 5, 'Rychlé střihy a skvělé dialogy.'], ['karolina_v', 4, null]],
    'Planet Earth II'  => [['ondra_h', 5, 'Záběry z přírody jsou dechberoucí.'], ['lucie_d', 5, null]],
    'The Mandalorian'  => [['martin_c', 4, null], ['jakub_s', 4, 'Grogu ukradl celou show.']],
    'Fleabag'          => [['karolina_v', 5, 'Krátké, ale dokonale napsané.'], ['tereza_n', 4, null], ['alex_k', 4, 'Prolamování čtvrté stěny funguje skvěle.']],
];

// Watchlist: každé hodnocení = "videno"; tady jsou navíc rozkoukané a plánované seriály.
$watchlistNavic = [
    ['tereza_n', 'The Witcher', 'chci_videt'],       ['tereza_n', 'Arcane', 'sleduji'],
    ['jakub_s', 'Dark', 'chci_videt'],               ['jakub_s', 'Planet Earth II', 'chci_videt'],
    ['lucie_d', 'Black Mirror', 'chci_videt'],       ['lucie_d', 'Fleabag', 'sleduji'],
    ['martin_c', 'Dark', 'sleduji'],                 ['martin_c', 'Arcane', 'chci_videt'],
    ['eliska_p', 'Chernobyl', 'chci_videt'],         ['eliska_p', 'Breaking Bad', 'sleduji'],
    ['ondra_h', 'Better Call Saul', 'sleduji'],      ['ondra_h', 'The Mandalorian', 'chci_videt'],
    ['karolina_v', 'Dark', 'chci_videt'],            ['karolina_v', 'Planet Earth II', 'sleduji'],
    ['alex_k', 'The Office', 'chci_videt'],          ['alex_k', 'Sherlock', 'sleduji'],
];

// od, komu, předmět, text, před kolika hodinami, přečteno
$zpravy = [
    ['tereza_n', 'jakub_s',    'Tip na seriál',          'Ahoj, už jsi viděl Dark? Úplně mě to pohltilo, určitě mrkni.', 70, 1],
    ['jakub_s', 'tereza_n',    'Re: Tip na seriál',      'Díky, dám to na watchlist! Ty jsi viděla Better Call Saul?', 68, 1],
    ['tereza_n', 'jakub_s',    'Re: Tip na seriál',      'Ještě ne, ale Breaking Bad mám za sebou, tak to bude další na řadě.', 40, 0],
    ['lucie_d', 'eliska_p',    'Arcane',                 'Koukla jsem na Arcane podle tvého doporučení – paráda!', 30, 1],
    ['eliska_p', 'lucie_d',    'Re: Arcane',             'Já věděla, že se ti bude líbit. Druhou sérii musíš taky.', 28, 0],
    ['martin_c', 'ondra_h',    'Víkendový maraton',      'Nechceš o víkendu dát maraton Sherlocka? Já mám čas v sobotu.', 20, 0],
    ['ondra_h', 'martin_c',    'Re: Víkendový maraton',  'Sobota klidně, jen ať to není dřív než odpoledne.', 18, 1],
    ['alex_k', 'karolina_v',   'Fleabag',                'Doporučuju Fleabag, je krátká, ale skvěle napsaná.', 8, 0],
];

// ---------- pomocné funkce ----------
function cas_pred(int $sekund): string
{
    return gmdate('Y-m-d H:i:s', time() - $sekund);   // SQLite CURRENT_TIMESTAMP je v UTC
}

/** Jednoduchý avatar: barevné pozadí + světlá silueta (hlava a ramena), JPEG. */
function vytvor_avatar(string $soubor, array $rgb, int $v = 400): void
{
    $img = imagecreatetruecolor($v, $v);
    imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));
    $svetla = imagecolorallocate($img, min(255, $rgb[0] + 80), min(255, $rgb[1] + 80), min(255, $rgb[2] + 80));
    imagefilledellipse($img, intdiv($v, 2), (int)($v * 0.38), (int)($v * 0.36), (int)($v * 0.36), $svetla);   // hlava
    imagefilledellipse($img, intdiv($v, 2), (int)($v * 0.98), (int)($v * 0.80), (int)($v * 0.70), $svetla);   // ramena
    if (!imagejpeg($img, $soubor, 85)) {
        throw new RuntimeException("Nelze uložit avatar $soubor");
    }
}

// ---------- kontroly ----------
$neprazdne = (int)$pdo->query('SELECT (SELECT COUNT(*) FROM uzivatele) + (SELECT COUNT(*) FROM serialy)')->fetchColumn();
if ($neprazdne > 0 && !$force) {
    fwrite(STDERR, "Databáze už obsahuje uživatele nebo seriály. Pro smazání a nové naplnění spusťte s --force.\n");
    exit(1);
}

foreach (array_merge($uzivatele, [$admin]) as [$login, $jmeno, $prijmeni, $email, $telefon, $pohlavi]) {
    $chyby = validuj_registraci([
        'name' => $jmeno, 'surname' => $prijmeni, 'email' => $email, 'phone_number' => $telefon,
        'gender' => $pohlavi, 'reg_login' => $login, 'password' => DEMO_HESLO,
    ]);
    if ($chyby) {
        fwrite(STDERR, "Ukázkový uživatel $login nesplňuje pravidla validace: " . implode(' ', $chyby) . "\n");
        exit(1);
    }
}

$slozka = $koren . '/uploads';
if (!is_dir($slozka) && !mkdir($slozka, 0755, true)) {
    fwrite(STDERR, "Nelze vytvořit složku uploads/.\n");
    exit(1);
}

// ---------- vložení ----------
$pdo->beginTransaction();
try {
    if ($force) {
        // fotky starých uživatelů pryč (jen soubory v uploads/, které vytvořila aplikace nebo tento skript)
        foreach ($pdo->query('SELECT foto_cesta FROM uzivatele')->fetchAll(PDO::FETCH_COLUMN) as $cesta) {
            if (preg_match('~^uploads/[A-Za-z0-9_]+\.jpg$~', $cesta)) {
                @unlink($koren . '/' . $cesta);
            }
        }
        foreach (['notifikace', 'zpravy', 'watchlist', 'hodnoceni', 'serialy_zanry', 'serialy', 'uzivatele'] as $tabulka) {
            $pdo->exec("DELETE FROM $tabulka");
        }
        $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('notifikace','zpravy','hodnoceni','serialy','uzivatele')");
    }

    // uživatelé (admin první, ať má id 1)
    $vlozUzivatele = $pdo->prepare(
        'INSERT INTO uzivatele (jmeno, prijmeni, email, telefon, pohlavi, foto_cesta, login, heslo, role, vytvoreno)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $idUzivatele = [];
    foreach (array_merge([$admin], $uzivatele) as $u) {
        [$login, $jmeno, $prijmeni, $email, $telefon, $pohlavi, $barva, $dnu] = $u;
        $jeAdmin = $login === 'admin';
        $foto = 'uploads/seed_' . $login . '.jpg';
        vytvor_avatar($koren . '/' . $foto, $barva);
        $vlozUzivatele->execute([
            $jmeno, $prijmeni, encrypt($email), encrypt($telefon), $pohlavi, $foto, $login,
            password_hash($jeAdmin ? $adminHeslo : DEMO_HESLO, PASSWORD_DEFAULT),
            $jeAdmin ? 'admin' : 'user',
            cas_pred($dnu * 86400),
        ]);
        $idUzivatele[$login] = (int)$pdo->lastInsertId();
    }

    // seriály + žánry
    $idZanru = $pdo->query('SELECT nazev, id FROM zanry')->fetchAll(PDO::FETCH_KEY_PAIR);
    $epizody = require __DIR__ . '/epizody.php';   // název => [počet sérií, počet epizod]
    $vlozSerial = $pdo->prepare('INSERT INTO serialy (nazev, popis, rok_vydani, pocet_serii, pocet_epizod, vytvoril_uzivatel_id) VALUES (?, ?, ?, ?, ?, ?)');
    $vlozZanr = $pdo->prepare('INSERT INTO serialy_zanry (serial_id, zanr_id) VALUES (?, ?)');
    $idSerialu = [];
    foreach ($serialy as [$nazev, $rok, $zanry, $popis]) {
        [$serii, $epizod] = $epizody[$nazev] ?? [null, null];
        $vlozSerial->execute([$nazev, $popis, $rok, $serii, $epizod, $idUzivatele['admin']]);
        $idSerialu[$nazev] = (int)$pdo->lastInsertId();
        foreach ($zanry as $zanr) {
            if (!isset($idZanru[$zanr])) {
                throw new RuntimeException("Žánr '$zanr' není v tabulce zanry.");
            }
            $vlozZanr->execute([$idSerialu[$nazev], $idZanru[$zanr]]);
        }
    }

    // hodnocení + watchlist ("videno" ke každému hodnocení)
    mt_srand(2026);   // stejná "náhodná" data při každém spuštění
    $vlozHodnoceni = $pdo->prepare(
        'INSERT INTO hodnoceni (serial_id, uzivatel_id, pocet_hvezd, komentar, datum_pridani) VALUES (?, ?, ?, ?, ?)'
    );
    $vlozWatchlist = $pdo->prepare(
        'INSERT OR IGNORE INTO watchlist (uzivatel_id, serial_id, stav, pridano) VALUES (?, ?, ?, ?)'
    );
    $pocetHodnoceni = 0;
    foreach ($hodnoceni as $nazev => $radky) {
        foreach ($radky as [$login, $hvezdy, $komentar]) {
            $kdy = cas_pred(mt_rand(1, 25) * 86400 + mt_rand(0, 86399));
            $vlozHodnoceni->execute([$idSerialu[$nazev], $idUzivatele[$login], $hvezdy, $komentar, $kdy]);
            $vlozWatchlist->execute([$idUzivatele[$login], $idSerialu[$nazev], 'videno', $kdy]);
            $pocetHodnoceni++;
        }
    }
    foreach ($watchlistNavic as [$login, $nazev, $stav]) {
        $vlozWatchlist->execute([$idUzivatele[$login], $idSerialu[$nazev], $stav, cas_pred(mt_rand(1, 30) * 86400)]);
    }

    // zprávy (předmět i text šifrovaně) + k nim notifikace pro příjemce
    $jmena = [];
    foreach ($uzivatele as $u) {
        $jmena[$u[0]] = $u[1] . ' ' . $u[2];
    }
    $vlozZpravu = $pdo->prepare(
        'INSERT INTO zpravy (odesilatel_id, prijemce_id, predmet, sifrovany_text, cas_odeslani, precteno)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $vlozNotifikaci = $pdo->prepare(
        'INSERT INTO notifikace (uzivatel_id, zprava_id, obsah_notifikace, cas_vytvoreni, precteno) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($zpravy as [$od, $komu, $predmet, $text, $hodinZpet, $precteno]) {
        $kdy = cas_pred($hodinZpet * 3600);
        $vlozZpravu->execute([$idUzivatele[$od], $idUzivatele[$komu], encrypt($predmet), encrypt($text), $kdy, $precteno]);
        $idZpravy = (int)$pdo->lastInsertId();
        $vlozNotifikaci->execute([$idUzivatele[$komu], $idZpravy, 'Nová zpráva od ' . $jmena[$od], $kdy, $precteno]);
    }
    foreach ($uzivatele as [$login, , , , , , , $dnu]) {
        $vlozNotifikaci->execute([$idUzivatele[$login], null, 'Vítejte v databázi seriálů!', cas_pred($dnu * 86400), 1]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Naplnění databáze selhalo, změny byly vráceny: ' . $e->getMessage() . "\n");
    exit(1);
}

// ---------- výpis ----------
echo "Hotovo.\n";
foreach (['uzivatele', 'serialy', 'serialy_zanry', 'hodnoceni', 'watchlist', 'zpravy', 'notifikace'] as $t) {
    printf("  %-14s %d\n", $t, $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn());
}
echo "\nPřihlášení:\n";
echo "  admin / $adminHeslo" . (isset($adminHesloVygenerovano) ? "   (vygenerované heslo – uložte si ho, znovu se nezobrazí)\n" : "\n");
echo '  ostatní ukázkoví uživatelé (' . implode(', ', array_column($uzivatele, 0)) . ') / ' . DEMO_HESLO . "\n";
