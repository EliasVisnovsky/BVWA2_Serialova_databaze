<?php
declare(strict_types=1);

/**
 * Můj účet: údaje o uživateli, statistiky a přehled toho, co chce uživatel vidět, co sleduje,
 * co už viděl a co ohodnotil. Odkazy vedou na detail seriálu, kde jde stav i hodnocení měnit;
 * stav a odebrání jde upravit i přímo tady.
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
<h1>Můj účet</h1>

<section class="profil-hlavicka">
    <img class="avatar avatar-obri" src="<?= h($uzivatel['foto_cesta']) ?>" alt="Moje profilová fotka">
    <div>
        <h2><?= h($uzivatel['jmeno'] . ' ' . $uzivatel['prijmeni']) ?>
            <?php if ($uzivatel['role'] === 'admin'): ?><span class="stitek stitek-admin">Administrátor</span><?php endif; ?>
        </h2>
        <dl class="udaje udaje-radky">
            <div><dt>Login</dt><dd><?= h($uzivatel['login']) ?></dd></div>
            <div><dt>E-mail</dt><dd><?= h($email) ?></dd></div>
            <div><dt>Telefon</dt><dd><?= h($telefon) ?></dd></div>
            <div><dt>Účet od</dt><dd><?= h(datum_cs($uzivatel['vytvoreno'])) ?></dd></div>
        </dl>
    </div>
</section>

<section class="statistiky" aria-label="Statistiky">
    <div><strong><?= (int)($pocty['chci_videt'] ?? 0) ?></strong><span>chci vidět</span></div>
    <div><strong><?= (int)($pocty['sleduji'] ?? 0) ?></strong><span>sleduji</span></div>
    <div><strong><?= (int)($pocty['videno'] ?? 0) ?></strong><span>viděno</span></div>
    <div><strong><?= $pocty['hodnoceni'] ?></strong><span>hodnocení<?= $pocty['hodnoceni'] ? ' · Ø ' . cislo_cs((float)$mojeStat['prumer']) . ' ★' : '' ?></span></div>
    <div><strong><?= $videnoEpizod ?></strong><span><?= sklonuj($videnoEpizod, 'zhlédnutá epizoda', 'zhlédnuté epizody', 'zhlédnutých epizod') ?></span></div>
</section>

<nav class="zalozky" aria-label="Přehled seriálů">
    <?php foreach (ZALOZKY as $klic => $popis): ?>
        <a href="profil.php?z=<?= h($klic) ?>"<?= $zalozka === $klic ? ' class="aktivni" aria-current="page"' : '' ?>>
            <?= h($popis) ?> <span class="pocitadlo"><?= (int)($pocty[$klic] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$polozky): ?>
    <p class="prazdno">
        <?= $zalozka === 'hodnoceni' ? 'Zatím jste nic neohodnotili.' : 'V této části zatím nic nemáte.' ?>
        <a href="index.php">Procházet katalog</a>
    </p>
<?php else: ?>
    <ul class="seznam">
        <?php foreach ($polozky as $p): ?>
            <li class="polozka">
                <a href="serial.php?id=<?= (int)$p['id'] ?>" class="polozka-plakat" tabindex="-1" aria-hidden="true"><?= plakat_html($p, 'plakat-maly') ?></a>
                <div class="polozka-text">
                    <h3><a href="serial.php?id=<?= (int)$p['id'] ?>"><?= h($p['nazev']) ?></a>
                        <span class="rok">(<?= h((string)$p['rok_vydani']) ?>)</span></h3>
                    <p class="meta"><?= h(epizody_text($p['pocet_serii'] !== null ? (int)$p['pocet_serii'] : null, $p['pocet_epizod'] !== null ? (int)$p['pocet_epizod'] : null)) ?></p>

                    <?php if ($zalozka === 'hodnoceni'): ?>
                        <p><?= hvezdy_html((float)$p['pocet_hvezd']) ?> <span class="meta"><?= h(datum_cs($p['datum_pridani'])) ?></span></p>
                        <?php if ($p['komentar'] !== null && $p['komentar'] !== ''): ?>
                            <p class="komentar"><?= nl2br(h($p['komentar'])) ?></p>
                        <?php endif; ?>
                    <?php elseif ($p['pocet_hvezd']): ?>
                        <p><?= hvezdy_html((float)$p['pocet_hvezd']) ?> <span class="meta">moje hodnocení</span></p>
                    <?php else: ?>
                        <p class="meta">Zatím neohodnoceno · <a href="serial.php?id=<?= (int)$p['id'] ?>#hodnoceni">ohodnotit</a></p>
                    <?php endif; ?>
                </div>

                <div class="polozka-akce">
                    <?php if ($zalozka === 'hodnoceni'): ?>
                        <a class="tl" href="serial.php?id=<?= (int)$p['id'] ?>#hodnoceni">Upravit</a>
                        <form action="hodnoceni.php" method="POST" class="inline" onsubmit="return confirm('Opravdu smazat toto hodnocení?');">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akce" value="smaz">
                            <input type="hidden" name="hodnoceni_id" value="<?= (int)$p['hodnoceni_id'] ?>">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <button type="submit" class="tl-odkaz tl-nebezpecne">Smazat</button>
                        </form>
                    <?php else: ?>
                        <form action="watchlist.php" method="POST" class="stav-form">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="serial_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="akce" value="nastav">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <label class="skryty" for="stav-<?= (int)$p['id'] ?>">Stav seriálu <?= h($p['nazev']) ?></label>
                            <select id="stav-<?= (int)$p['id'] ?>" name="stav" onchange="this.form.submit()">
                                <?php foreach (STAVY_WATCHLISTU as $klic => $popis): ?>
                                    <option value="<?= h($klic) ?>"<?= $p['stav'] === $klic ? ' selected' : '' ?>><?= h($popis) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="tl">Změnit</button></noscript>
                        </form>
                        <form action="watchlist.php" method="POST" class="inline">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="serial_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="akce" value="odeber">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            <button type="submit" class="tl-odkaz tl-nebezpecne">Odebrat</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif;

paticka();
