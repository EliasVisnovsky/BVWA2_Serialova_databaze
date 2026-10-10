<?php
declare(strict_types=1);

/** Společný vzhled stránek (hlavička s navigací, patička) a drobné pomocné funkce pro výpis. */

function h(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

const STAVY_WATCHLISTU = [
    'chci_videt' => 'Chci vidět',
    'sleduji'    => 'Sleduji',
    'videno'     => 'Viděno',
];

/** "4.0" -> "4,0" (české desetinné číslo). */
function cislo_cs(float $c, int $mista = 1): string
{
    return number_format($c, $mista, ',', '');
}

/** Datum z DB (UTC) -> "12. 9. 2026". */
function datum_cs(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    return $d ? $d->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. Y') : '';
}

/** Správný tvar slova podle počtu: sklonuj(5, 'epizoda', 'epizody', 'epizod'). */
function sklonuj(int $n, string $jedna, string $dve_ctyri, string $pet): string
{
    if ($n === 1) {
        return $jedna;
    }
    return ($n >= 2 && $n <= 4) ? $dve_ctyri : $pet;
}

/** "62 epizod v 5 sériích" / "neuvedeno". */
function epizody_text(?int $serii, ?int $epizod): string
{
    if (!$epizod) {
        return 'neuvedeno';
    }
    $t = $epizod . ' ' . sklonuj($epizod, 'epizoda', 'epizody', 'epizod');
    if ($serii) {
        $t .= ' · ' . $serii . ' ' . sklonuj($serii, 'série', 'série', 'sérií');
    }
    return $t;
}

/** Hvězdičky pro zobrazení (celé hvězdy, zaokrouhleno). Přístupný popisek je v aria-label. */
function hvezdy_html(float $hodnota): string
{
    $plne = (int)round($hodnota);
    $plne = max(0, min(5, $plne));
    return '<span class="stars" role="img" aria-label="' . h(cislo_cs($hodnota)) . ' z 5 hvězdiček">'
        . str_repeat('★', $plne) . '<span class="stars-off">' . str_repeat('★', 5 - $plne) . '</span></span>';
}

/** Náhradní plakát (když seriál nemá plakat_url): barevná dlaždice s počátečním písmenem. */
function plakat_html(array $serial, string $trida = ''): string
{
    $url = (string)($serial['plakat_url'] ?? '');
    if (preg_match('~^https?://~i', $url)) {
        return '<img class="plakat ' . h($trida) . '" src="' . h($url) . '" alt="Plakát seriálu ' . h($serial['nazev']) . '" loading="lazy">';
    }
    $odstin = ((int)$serial['id'] * 47) % 360;
    $pismeno = mb_strtoupper(mb_substr((string)$serial['nazev'], 0, 1, 'UTF-8'), 'UTF-8');
    return '<div class="plakat plakat-nahrada ' . h($trida) . '" style="--odstin:' . $odstin . '" aria-hidden="true">' . h($pismeno) . '</div>';
}

function hlavicka(string $titulek, ?array $uzivatel, string $aktivni = ''): void
{
    $flash = flash_vyber();
    ?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulek) ?> – Seriálová databáze</title>
    <link rel="stylesheet" href="css/styl.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-in">
        <a class="logo" href="index.php">Seriálová databáze</a>
        <nav class="nav" aria-label="Hlavní navigace">
            <a href="index.php"<?= $aktivni === 'katalog' ? ' class="aktivni" aria-current="page"' : '' ?>>Katalog</a>
            <?php if ($uzivatel): ?>
                <a href="profil.php"<?= $aktivni === 'profil' ? ' class="aktivni" aria-current="page"' : '' ?>>Můj účet</a>
            <?php endif; ?>
        </nav>
        <div class="uzivatel">
            <?php if ($uzivatel): ?>
                <a class="uzivatel-odkaz" href="profil.php">
                    <img class="avatar" src="<?= h($uzivatel['foto_cesta']) ?>" alt="">
                    <span><?= h($uzivatel['jmeno']) ?></span>
                </a>
                <form action="logout.php" method="POST" class="inline">
                    <?= csrf_pole() ?>
                    <button type="submit" class="tl-odkaz">Odhlásit</button>
                </form>
            <?php else: ?>
                <a class="tl tl-primarni" href="<?= h(STRANA_PRIHLASENI) ?>">Přihlásit se</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="obsah">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['typ'] === 'chyba' ? 'error' : 'success' ?>" role="status"><?= h($flash['zprava']) ?></div>
<?php endif;
}

function paticka(): void
{
    ?></main>
<footer class="paticka">BVWA2 – Seriálová databáze</footer>
</body>
</html>
<?php
}
