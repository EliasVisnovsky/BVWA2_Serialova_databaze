<?php
declare(strict_types=1);

/**
 * Detail seriálu: základní informace (žánry, počet sérií a epizod), průměrné hodnocení s rozložením hvězdiček,
 * tlačítka watchlistu, formulář vlastního hodnocení a hodnocení ostatních uživatelů.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

$uzivatel = prihlaseny_uzivatel($pdo);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$serial = false;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM serialy WHERE id = ?');
    $stmt->execute([$id]);
    $serial = $stmt->fetch();
}
if (!$serial) {
    http_response_code(404);
    hlavicka('Seriál nenalezen', $uzivatel, 'katalog');
    echo '<h1>Seriál nenalezen</h1><p><a href="index.php">Zpět do katalogu</a></p>';
    paticka();
    exit;
}

// žánry
$stmt = $pdo->prepare('SELECT z.id, z.nazev FROM serialy_zanry sz JOIN zanry z ON z.id = sz.zanr_id WHERE sz.serial_id = ? ORDER BY z.nazev COLLATE NOCASE');
$stmt->execute([$id]);
$zanry = $stmt->fetchAll();

// souhrn hodnocení + rozložení po hvězdičkách
$stmt = $pdo->prepare('SELECT pocet_hvezd, COUNT(*) AS pocet FROM hodnoceni WHERE serial_id = ? GROUP BY pocet_hvezd');
$stmt->execute([$id]);
$rozlozeni = array_fill(1, 5, 0);
foreach ($stmt->fetchAll() as $r) {
    $rozlozeni[(int)$r['pocet_hvezd']] = (int)$r['pocet'];
}
$pocetHodnoceni = array_sum($rozlozeni);
$prumer = $pocetHodnoceni ? array_sum(array_map(fn($h, $n) => $h * $n, array_keys($rozlozeni), $rozlozeni)) / $pocetHodnoceni : 0.0;

// kolik lidí má seriál v seznamu
$stmt = $pdo->prepare('SELECT stav, COUNT(*) AS pocet FROM watchlist WHERE serial_id = ? GROUP BY stav');
$stmt->execute([$id]);
$vSeznamech = array_column($stmt->fetchAll(), 'pocet', 'stav');
$vSeznamechCelkem = array_sum($vSeznamech);

// moje data (watchlist + hodnocení)
$mujStav = null;
$mojeHodnoceni = null;
if ($uzivatel) {
    $stmt = $pdo->prepare('SELECT stav FROM watchlist WHERE uzivatel_id = ? AND serial_id = ?');
    $stmt->execute([$uzivatel['id'], $id]);
    $mujStav = $stmt->fetchColumn() ?: null;

    $stmt = $pdo->prepare('SELECT id, pocet_hvezd, komentar FROM hodnoceni WHERE uzivatel_id = ? AND serial_id = ?');
    $stmt->execute([$uzivatel['id'], $id]);
    $mojeHodnoceni = $stmt->fetch() ?: null;
}

// hodnocení ostatních (nejnovější nahoře; vlastní je nahoře zvlášť ve formuláři, takže tady je vynecháme)
$stmt = $pdo->prepare(
    'SELECT h.id, h.pocet_hvezd, h.komentar, h.datum_pridani, u.login, u.foto_cesta
       FROM hodnoceni h JOIN uzivatele u ON u.id = h.uzivatel_id
      WHERE h.serial_id = ? AND h.uzivatel_id <> ?
      ORDER BY h.datum_pridani DESC, h.id DESC'
);
$stmt->execute([$id, $uzivatel ? (int)$uzivatel['id'] : 0]);
$hodnoceniOstatnich = $stmt->fetchAll();

$jeAdmin = $uzivatel && $uzivatel['role'] === 'admin';
$navrat = 'serial.php?id=' . $id;

hlavicka($serial['nazev'], $uzivatel, 'katalog');
?>
<p class="drobky"><a href="index.php">← Katalog</a></p>

<section class="detail">
    <?= plakat_html($serial, 'plakat-velky') ?>
    <div class="detail-text">
        <h1><?= h($serial['nazev']) ?> <span class="rok">(<?= h((string)$serial['rok_vydani']) ?>)</span></h1>

        <p class="zanry">
            <?php foreach ($zanry as $z): ?>
                <a class="stitek" href="index.php?zanr=<?= (int)$z['id'] ?>"><?= h($z['nazev']) ?></a>
            <?php endforeach; ?>
        </p>

        <dl class="udaje">
            <div><dt>Počet sérií</dt><dd><?= $serial['pocet_serii'] ? (int)$serial['pocet_serii'] : 'neuvedeno' ?></dd></div>
            <div><dt>Počet epizod</dt><dd><?= $serial['pocet_epizod'] ? (int)$serial['pocet_epizod'] : 'neuvedeno' ?></dd></div>
            <div><dt>Rok vydání</dt><dd><?= h((string)$serial['rok_vydani']) ?></dd></div>
            <div><dt>V seznamech uživatelů</dt><dd><?= $vSeznamechCelkem ?></dd></div>
        </dl>

        <p class="popis"><?= nl2br(h($serial['popis'])) ?></p>
    </div>
</section>

<div class="dva-sloupce">
    <section class="panel" aria-labelledby="h-hodnoceni-souhrn">
        <h2 id="h-hodnoceni-souhrn">Hodnocení</h2>
        <?php if ($pocetHodnoceni): ?>
            <div class="souhrn">
                <div class="souhrn-cislo"><?= cislo_cs($prumer) ?><small>/5</small></div>
                <div>
                    <?= hvezdy_html($prumer) ?>
                    <p class="meta"><?= $pocetHodnoceni ?> <?= sklonuj($pocetHodnoceni, 'hodnocení', 'hodnocení', 'hodnocení') ?></p>
                </div>
            </div>
            <ul class="rozlozeni">
                <?php for ($h = 5; $h >= 1; $h--):
                    $podil = (int)round($rozlozeni[$h] / $pocetHodnoceni * 100); ?>
                    <li>
                        <span class="rozlozeni-popis"><?= $h ?> ★</span>
                        <span class="pruh" aria-hidden="true"><span style="width: <?= $podil ?>%"></span></span>
                        <span class="rozlozeni-pocet"><?= $rozlozeni[$h] ?></span>
                    </li>
                <?php endfor; ?>
            </ul>
        <?php else: ?>
            <p class="meta">Seriál zatím nikdo nehodnotil.</p>
        <?php endif; ?>
    </section>

    <section class="panel" id="watchlist" aria-labelledby="h-watchlist">
        <h2 id="h-watchlist">Můj seznam</h2>
        <?php if ($uzivatel): ?>
            <p class="meta"><?= $mujStav ? 'Aktuální stav: <strong>' . h(STAVY_WATCHLISTU[$mujStav]) . '</strong>' : 'Seriál zatím nemáte v seznamu.' ?></p>
            <form action="watchlist.php" method="POST" class="stavy">
                <?= csrf_pole() ?>
                <input type="hidden" name="serial_id" value="<?= $id ?>">
                <input type="hidden" name="akce" value="nastav">
                <input type="hidden" name="navrat" value="<?= h($navrat . '#watchlist') ?>">
                <?php foreach (STAVY_WATCHLISTU as $klic => $popis): ?>
                    <button type="submit" name="stav" value="<?= h($klic) ?>"
                            class="tl<?= $mujStav === $klic ? ' tl-primarni' : '' ?>"
                            <?= $mujStav === $klic ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>><?= h($popis) ?></button>
                <?php endforeach; ?>
            </form>
            <?php if ($mujStav): ?>
                <form action="watchlist.php" method="POST" class="inline">
                    <?= csrf_pole() ?>
                    <input type="hidden" name="serial_id" value="<?= $id ?>">
                    <input type="hidden" name="akce" value="odeber">
                    <input type="hidden" name="navrat" value="<?= h($navrat . '#watchlist') ?>">
                    <button type="submit" class="tl-odkaz">Odebrat ze seznamu</button>
                </form>
            <?php endif; ?>
            <p class="meta"><a href="profil.php">Zobrazit celý můj seznam →</a></p>
        <?php else: ?>
            <p><a href="<?= h(STRANA_PRIHLASENI) ?>">Přihlaste se</a> a můžete si seriál uložit do seznamu.</p>
        <?php endif; ?>
    </section>
</div>

<section class="panel" id="hodnoceni" aria-labelledby="h-moje-hodnoceni">
    <h2 id="h-moje-hodnoceni">Moje hodnocení</h2>
    <?php if ($uzivatel): ?>
        <form action="hodnoceni.php" method="POST" class="hodnoceni-form">
            <?= csrf_pole() ?>
            <input type="hidden" name="akce" value="uloz">
            <input type="hidden" name="serial_id" value="<?= $id ?>">
            <input type="hidden" name="navrat" value="<?= h($navrat . '#hodnoceni') ?>">

            <fieldset class="hvezdicky">
                <legend>Počet hvězdiček</legend>
                <div class="hvezdicky-radek">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="hvezdy" id="hv<?= $i ?>" value="<?= $i ?>"
                           <?= $mojeHodnoceni && (int)$mojeHodnoceni['pocet_hvezd'] === $i ? 'checked' : '' ?> required>
                    <label for="hv<?= $i ?>" title="<?= $i ?> z 5"><span class="skryty"><?= $i ?> z 5</span>★</label>
                <?php endfor; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="komentar">Komentář (nepovinný)</label>
                <textarea id="komentar" name="komentar" rows="4" maxlength="1000" placeholder="Co se vám na seriálu líbilo?"><?= h($mojeHodnoceni['komentar'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="tl tl-primarni"><?= $mojeHodnoceni ? 'Upravit hodnocení' : 'Uložit hodnocení' ?></button>
        </form>
        <?php if ($mojeHodnoceni): ?>
            <form action="hodnoceni.php" method="POST" class="inline" onsubmit="return confirm('Opravdu smazat vaše hodnocení?');">
                <?= csrf_pole() ?>
                <input type="hidden" name="akce" value="smaz">
                <input type="hidden" name="hodnoceni_id" value="<?= (int)$mojeHodnoceni['id'] ?>">
                <input type="hidden" name="navrat" value="<?= h($navrat . '#hodnoceni') ?>">
                <button type="submit" class="tl-odkaz tl-nebezpecne">Smazat moje hodnocení</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p><a href="<?= h(STRANA_PRIHLASENI) ?>">Přihlaste se</a> a můžete seriál ohodnotit.</p>
    <?php endif; ?>
</section>

<section class="panel" aria-labelledby="h-ostatni">
    <h2 id="h-ostatni">Hodnocení ostatních</h2>
    <?php if (!$hodnoceniOstatnich): ?>
        <p class="meta">Zatím tu nejsou žádná další hodnocení.</p>
    <?php else: ?>
        <ul class="recenze">
            <?php foreach ($hodnoceniOstatnich as $r): ?>
                <li>
                    <img class="avatar avatar-velky" src="<?= h($r['foto_cesta']) ?>" alt="">
                    <div class="recenze-text">
                        <p class="recenze-hlava">
                            <strong><?= h($r['login']) ?></strong>
                            <?= hvezdy_html((float)$r['pocet_hvezd']) ?>
                            <span class="meta"><?= h(datum_cs($r['datum_pridani'])) ?></span>
                        </p>
                        <?php if ($r['komentar'] !== null && $r['komentar'] !== ''): ?>
                            <p><?= nl2br(h($r['komentar'])) ?></p>
                        <?php endif; ?>
                        <?php if ($jeAdmin): ?>
                            <form action="hodnoceni.php" method="POST" class="inline" onsubmit="return confirm('Smazat toto hodnocení?');">
                                <?= csrf_pole() ?>
                                <input type="hidden" name="akce" value="smaz">
                                <input type="hidden" name="hodnoceni_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="navrat" value="<?= h($navrat . '#hodnoceni') ?>">
                                <button type="submit" class="tl-odkaz tl-nebezpecne">Smazat (admin)</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
paticka();
