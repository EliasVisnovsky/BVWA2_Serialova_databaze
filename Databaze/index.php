<?php
declare(strict_types=1);

/**
 * Katalog seriálů: hledání podle názvu, filtr podle žánru, řazení.
 * Přihlášenému uživateli ukazuje u každého seriálu jeho stav ve watchlistu a jeho vlastní hodnocení.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

$uzivatel = prihlaseny_uzivatel($pdo);

// ---------- vstupy (GET) ----------
$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q, 'UTF-8') > 100) {
    $q = mb_substr($q, 0, 100, 'UTF-8');
}
$zanrId = filter_var($_GET['zanr'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

const RAZENI = [
    'nazev'     => ['Názvu',            's.nazev COLLATE NOCASE ASC'],
    'hodnoceni' => ['Hodnocení',        'prumer DESC, pocet_hodnoceni DESC, s.nazev COLLATE NOCASE ASC'],
    'rok'       => ['Roku (nejnovější)', 's.rok_vydani DESC, s.nazev COLLATE NOCASE ASC'],
];
$razeni = isset(RAZENI[$_GET['razeni'] ?? '']) ? $_GET['razeni'] : 'nazev';

$zanry = $pdo->query('SELECT id, nazev FROM zanry ORDER BY nazev COLLATE NOCASE')->fetchAll();

// ---------- dotaz ----------
$uid = $uzivatel ? (int)$uzivatel['id'] : 0;
$sql = "SELECT s.id, s.nazev, s.rok_vydani, s.pocet_serii, s.pocet_epizod, s.plakat_url,
               (SELECT AVG(pocet_hvezd) FROM hodnoceni WHERE serial_id = s.id) AS prumer,
               (SELECT COUNT(*) FROM hodnoceni WHERE serial_id = s.id)          AS pocet_hodnoceni,
               (SELECT GROUP_CONCAT(z.nazev, ', ') FROM serialy_zanry sz JOIN zanry z ON z.id = sz.zanr_id
                 WHERE sz.serial_id = s.id)                                     AS zanry,
               (SELECT stav FROM watchlist WHERE serial_id = s.id AND uzivatel_id = :uid) AS muj_stav,
               (SELECT pocet_hvezd FROM hodnoceni WHERE serial_id = s.id AND uzivatel_id = :uid2) AS moje_hvezdy
          FROM serialy s
         WHERE (:q = '' OR s.nazev LIKE '%' || :qlike || '%' ESCAPE '\\')
           AND (:zanr = 0 OR EXISTS (SELECT 1 FROM serialy_zanry WHERE serial_id = s.id AND zanr_id = :zanr2))
         ORDER BY " . RAZENI[$razeni][1];
$stmt = $pdo->prepare($sql);
// Čísla se vážou jako INTEGER – jinak je SQLite porovná jako text ('0' = 0 neplatí) a filtr by nikdy neprošel.
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':uid2', $uid, PDO::PARAM_INT);
$stmt->bindValue(':q', $q);
$stmt->bindValue(':qlike', addcslashes($q, '%_\\'));
$stmt->bindValue(':zanr', $zanrId, PDO::PARAM_INT);
$stmt->bindValue(':zanr2', $zanrId, PDO::PARAM_INT);
$stmt->execute();
$serialy = $stmt->fetchAll();

hlavicka('Katalog seriálů', $uzivatel, 'katalog');
?>
<h1>Katalog seriálů</h1>

<form class="filtr" method="GET" action="index.php">
    <label class="skryty" for="q">Hledat podle názvu</label>
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Hledat podle názvu…" maxlength="100">

    <label class="skryty" for="zanr">Žánr</label>
    <select id="zanr" name="zanr">
        <option value="0">Všechny žánry</option>
        <?php foreach ($zanry as $z): ?>
            <option value="<?= (int)$z['id'] ?>"<?= $zanrId === (int)$z['id'] ? ' selected' : '' ?>><?= h($z['nazev']) ?></option>
        <?php endforeach; ?>
    </select>

    <label class="skryty" for="razeni">Řadit podle</label>
    <select id="razeni" name="razeni">
        <?php foreach (RAZENI as $klic => [$popis]): ?>
            <option value="<?= h($klic) ?>"<?= $razeni === $klic ? ' selected' : '' ?>>Řadit: <?= h($popis) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="tl tl-primarni">Filtrovat</button>
    <?php if ($q !== '' || $zanrId || $razeni !== 'nazev'): ?>
        <a class="tl" href="index.php">Zrušit filtr</a>
    <?php endif; ?>
</form>

<?php if (!$serialy): ?>
    <p class="prazdno">Nic jsme nenašli. Zkuste jiný název nebo žánr.</p>
<?php else: ?>
    <p class="pocet"><?= count($serialy) ?> <?= sklonuj(count($serialy), 'seriál', 'seriály', 'seriálů') ?></p>
    <ul class="mrizka">
        <?php foreach ($serialy as $s): ?>
            <li class="karta">
                <a class="karta-odkaz" href="serial.php?id=<?= (int)$s['id'] ?>">
                    <?= plakat_html($s) ?>
                    <div class="karta-text">
                        <h2><?= h($s['nazev']) ?></h2>
                        <p class="meta"><?= h((string)$s['rok_vydani']) ?> · <?= h(epizody_text($s['pocet_serii'] !== null ? (int)$s['pocet_serii'] : null, $s['pocet_epizod'] !== null ? (int)$s['pocet_epizod'] : null)) ?></p>
                        <p class="meta"><?= h($s['zanry']) ?></p>
                        <p class="hodnoceni-radek">
                            <?php if ((int)$s['pocet_hodnoceni'] > 0): ?>
                                <?= hvezdy_html((float)$s['prumer']) ?>
                                <strong><?= cislo_cs((float)$s['prumer']) ?></strong>
                                <span class="meta">(<?= (int)$s['pocet_hodnoceni'] ?>)</span>
                            <?php else: ?>
                                <span class="meta">Zatím bez hodnocení</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
                <?php if ($s['muj_stav'] || $s['moje_hvezdy']): ?>
                    <p class="karta-moje">
                        <?php if ($s['muj_stav']): ?>
                            <span class="stitek stitek-<?= h($s['muj_stav']) ?>"><?= h(STAVY_WATCHLISTU[$s['muj_stav']] ?? '') ?></span>
                        <?php endif; ?>
                        <?php if ($s['moje_hvezdy']): ?>
                            <span class="meta">Vaše hodnocení: <?= (int)$s['moje_hvezdy'] ?>/5</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif;

paticka();
