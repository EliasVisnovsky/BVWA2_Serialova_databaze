<?php
declare(strict_types=1);

/**
 * Můj účet: údaje o uživateli, statistiky a přehled toho, co chce uživatel vidět, co sleduje,
 * co už viděl a co ohodnotil. Odkazy vedou na detail seriálu, kde jde stav i hodnocení měnit;
 * stav a odebrání jde upravit i přímo tady. Osobní údaje a fotka se mění na profil_uprava.php.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/crypto.php';
require __DIR__ . '/php/layout.php';

$uzivatel = vyzaduj_prihlaseni($pdo);
$uid = (int)$uzivatel['id'];

// ---------- záložka ----------
const ZALOZKY = ['chci_videt' => 'Chci vidět', 'sleduji' => 'Sleduji', 'videno' => 'Viděno', 'hodnoceni' => 'Moje hodnocení'];
$zalozka = isset(ZALOZKY[$_GET['z'] ?? '']) ? (string)$_GET['z'] : 'chci_videt';

// ---------- kontakt (v DB šifrovaný) ----------
$stmt = $pdo->prepare('SELECT email, telefon FROM uzivatele WHERE id = ?');
$stmt->execute([$uid]);
$kontakt = $stmt->fetch();
try {
    $email = decrypt((string)$kontakt['email']);
    $telefon = decrypt((string)$kontakt['telefon']);
} catch (Throwable $e) {
    error_log('profil.php: ' . $e->getMessage());
    $email = $telefon = 'nelze zobrazit';
}

// ---------- statistiky ----------
$stmt = $pdo->prepare('SELECT stav, COUNT(*) AS pocet FROM watchlist WHERE uzivatel_id = ? GROUP BY stav');
$stmt->execute([$uid]);
$pocty = array_column($stmt->fetchAll(), 'pocet', 'stav');

$stmt = $pdo->prepare('SELECT COUNT(*) AS pocet, AVG(pocet_hvezd) AS prumer FROM hodnoceni WHERE uzivatel_id = ?');
$stmt->execute([$uid]);
$mojeStat = $stmt->fetch();
$pocty['hodnoceni'] = (int)$mojeStat['pocet'];

// epizody, které má uživatel u seriálů ve stavu "Viděno"
$stmt = $pdo->prepare("SELECT COALESCE(SUM(s.pocet_epizod), 0) FROM watchlist w JOIN serialy s ON s.id = w.serial_id WHERE w.uzivatel_id = ? AND w.stav = 'videno'");
$stmt->execute([$uid]);
$videnoEpizod = (int)$stmt->fetchColumn();

// ---------- obsah záložky ----------
if ($zalozka === 'hodnoceni') {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nazev, s.rok_vydani, s.pocet_serii, s.pocet_epizod, s.plakat_url,
                h.id AS hodnoceni_id, h.pocet_hvezd, h.komentar, h.datum_pridani
           FROM hodnoceni h JOIN serialy s ON s.id = h.serial_id
          WHERE h.uzivatel_id = ?
          ORDER BY h.datum_pridani DESC, h.id DESC'
    );
    $stmt->execute([$uid]);
} else {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nazev, s.rok_vydani, s.pocet_serii, s.pocet_epizod, s.plakat_url,
                w.stav, w.pridano,
                h.pocet_hvezd
           FROM watchlist w
           JOIN serialy s ON s.id = w.serial_id
           LEFT JOIN hodnoceni h ON h.serial_id = s.id AND h.uzivatel_id = w.uzivatel_id
          WHERE w.uzivatel_id = ? AND w.stav = ?
          ORDER BY w.pridano DESC, s.nazev COLLATE NOCASE'
    );
    $stmt->execute([$uid, $zalozka]);
}
$polozky = $stmt->fetchAll();

$navrat = 'profil.php?z=' . $zalozka;

hlavicka('Můj účet', $uzivatel, 'profil');
?>
<main class="w-full max-w-5xl mx-auto pt-[150px] pb-20 px-[4vw]">
<h1 class="text-4xl sm:text-6xl font-black uppercase tracking-tighter mb-10">Můj účet</h1>

<section class="flex flex-wrap gap-8 items-start mb-10">
    <img class="w-32 h-32 rounded-full object-cover bg-epilogCard border border-white/10" src="<?= h($uzivatel['foto_cesta']) ?>" alt="Moje profilová fotka">
    <div class="flex-1 min-w-[260px]">
        <h2 class="text-2xl font-black uppercase">
            <?= h($uzivatel['jmeno'] . ' ' . $uzivatel['prijmeni']) ?>
            <?php if ($uzivatel['role'] === 'admin'): ?><span class="ml-2 align-middle bg-white text-epilogBg text-xs font-bold uppercase px-2 py-0.5">Administrátor</span><?php endif; ?>
        </h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-3 mt-4">
            <div><dt class="text-xs uppercase font-bold tracking-wider text-white/50">Login</dt><dd class="mt-0.5 break-all"><?= h($uzivatel['login']) ?></dd></div>
            <div><dt class="text-xs uppercase font-bold tracking-wider text-white/50">E-mail</dt><dd class="mt-0.5 break-all"><?= h($email) ?></dd></div>
            <div><dt class="text-xs uppercase font-bold tracking-wider text-white/50">Telefon</dt><dd class="mt-0.5"><?= h($telefon) ?></dd></div>
            <div><dt class="text-xs uppercase font-bold tracking-wider text-white/50">Účet od</dt><dd class="mt-0.5"><?= h(datum_cs($uzivatel['vytvoreno'])) ?></dd></div>
        </dl>
        <p class="flex flex-wrap gap-3 mt-6">
            <a class="tl" href="profil_uprava.php">Upravit údaje a fotku</a>
            <a class="tl" href="profil_prihlaseni.php">Změnit login a heslo</a>
        </p>
    </div>
</section>

<section class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-10" aria-label="Statistiky">
    <div class="bg-epilogCard border border-white/10 rounded p-4"><strong class="block text-3xl font-black"><?= (int)($pocty['chci_videt'] ?? 0) ?></strong><span class="text-xs uppercase font-bold tracking-wider text-white/50">chci vidět</span></div>
    <div class="bg-epilogCard border border-white/10 rounded p-4"><strong class="block text-3xl font-black"><?= (int)($pocty['sleduji'] ?? 0) ?></strong><span class="text-xs uppercase font-bold tracking-wider text-white/50">sleduji</span></div>
    <div class="bg-epilogCard border border-white/10 rounded p-4"><strong class="block text-3xl font-black"><?= (int)($pocty['videno'] ?? 0) ?></strong><span class="text-xs uppercase font-bold tracking-wider text-white/50">viděno</span></div>
    <div class="bg-epilogCard border border-white/10 rounded p-4"><strong class="block text-3xl font-black"><?= $pocty['hodnoceni'] ?></strong><span class="text-xs uppercase font-bold tracking-wider text-white/50">hodnocení<?= $pocty['hodnoceni'] ? ' · Ø ' . cislo_cs((float)$mojeStat['prumer']) . ' ★' : '' ?></span></div>
    <div class="bg-epilogCard border border-white/10 rounded p-4"><strong class="block text-3xl font-black"><?= $videnoEpizod ?></strong><span class="text-xs uppercase font-bold tracking-wider text-white/50"><?= sklonuj($videnoEpizod, 'zhlédnutá epizoda', 'zhlédnuté epizody', 'zhlédnutých epizod') ?></span></div>
</section>

<nav class="flex flex-wrap gap-x-8 gap-y-2 border-b border-white/15 mb-8" aria-label="Přehled seriálů">
    <?php foreach (ZALOZKY as $klic => $popis): ?>
        <a href="profil.php?z=<?= h($klic) ?>" class="pb-3 -mb-px text-sm font-bold uppercase tracking-widest border-b-2 transition-colors <?= $zalozka === $klic ? 'border-white text-white' : 'border-transparent text-white/50 hover:text-white' ?>"<?= $zalozka === $klic ? ' aria-current="page"' : '' ?>>
            <?= h($popis) ?> <span class="ml-1 text-white/40"><?= (int)($pocty[$klic] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$polozky): ?>
    <p class="text-white/50 py-8">
        <?= $zalozka === 'hodnoceni' ? 'Zatím jste nic neohodnotili.' : 'V této části zatím nic nemáte.' ?>
        <a class="text-white underline font-bold" href="index.php">Procházet katalog</a>
    </p>
<?php else: ?>
    <ul class="list-none p-0 m-0">
        <?php foreach ($polozky as $p): ?>
            <li class="flex flex-wrap sm:flex-nowrap gap-5 py-5 border-b border-white/10">
                <a href="serial.php?id=<?= (int)$p['id'] ?>" class="block w-16 shrink-0 aspect-[2/3] overflow-hidden rounded bg-epilogCard" tabindex="-1" aria-hidden="true"><?= plakat_html($p, 'w-full h-full object-cover') ?></a>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-black uppercase"><a class="hover:underline" href="serial.php?id=<?= (int)$p['id'] ?>"><?= h($p['nazev']) ?></a>
                        <span class="font-normal text-white/50">(<?= h((string)$p['rok_vydani']) ?>)</span></h3>
                    <p class="text-xs text-white/50 mt-1"><?= h(epizody_text($p['pocet_serii'] !== null ? (int)$p['pocet_serii'] : null, $p['pocet_epizod'] !== null ? (int)$p['pocet_epizod'] : null)) ?></p>

                    <?php if ($zalozka === 'hodnoceni'): ?>
                        <p class="mt-2"><?= hvezdy_html((float)$p['pocet_hvezd']) ?> <span class="text-xs text-white/50"><?= h(datum_cs($p['datum_pridani'])) ?></span></p>
                        <?php if ($p['komentar'] !== null && $p['komentar'] !== ''): ?>
                            <p class="mt-2 text-white/70 break-words"><?= nl2br(h($p['komentar'])) ?></p>
                        <?php endif; ?>
                    <?php elseif ($p['pocet_hvezd']): ?>
                        <p class="mt-2"><?= hvezdy_html((float)$p['pocet_hvezd']) ?> <span class="text-xs text-white/50">moje hodnocení</span></p>
                    <?php else: ?>
                        <p class="text-xs text-white/50 mt-2">Zatím neohodnoceno · <a class="text-white underline" href="serial.php?id=<?= (int)$p['id'] ?>#hodnoceni">ohodnotit</a></p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-row sm:flex-col items-start sm:items-end gap-2">
                    <?php if ($zalozka === 'hodnoceni'): ?>
                        <a class="tl text-xs !px-3 !py-1.5" href="serial.php?id=<?= (int)$p['id'] ?>#hodnoceni">Upravit</a>
                        <form action="hodnoceni.php" method="POST" onsubmit="return confirm('Opravdu smazat toto hodnocení?');">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akce" value="smaz">
                            <input type="hidden" name="hodnoceni_id" value="<?= (int)$p['hodnoceni_id'] ?>">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <button type="submit" class="tl-odkaz tl-nebezpecne text-xs">Smazat</button>
                        </form>
                    <?php else: ?>
                        <form action="watchlist.php" method="POST">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="serial_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="akce" value="nastav">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <label class="sr-only" for="stav-<?= (int)$p['id'] ?>">Stav seriálu <?= h($p['nazev']) ?></label>
                            <select id="stav-<?= (int)$p['id'] ?>" name="stav" onchange="this.form.submit()" class="brutal-input !w-auto !py-1.5 !px-3 text-xs">
                                <?php foreach (STAVY_WATCHLISTU as $klic => $popis): ?>
                                    <option value="<?= h($klic) ?>"<?= $p['stav'] === $klic ? ' selected' : '' ?>><?= h($popis) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="tl text-xs">Změnit</button></noscript>
                        </form>
                        <form action="watchlist.php" method="POST">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="serial_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="akce" value="odeber">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <button type="submit" class="tl-odkaz tl-nebezpecne text-xs">Odebrat</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
</main>
<?php
paticka();
