<?php
declare(strict_types=1);

/**
 * Hromadné oznámení (jen pro administrátora): jedna zpráva všem ostatním uživatelům, např. o odstávce.
 *
 * Schéma ukládá zprávu pro každého příjemce zvlášť (zpravy.prijemce_id), takže se pro každého
 * Příjemci si oznámení přečtou na zpravy.php; počet nepřečtených je vidět v navigaci.
 *
 * Oprávnění se ověřuje na serveru (role z DB, ne z formuláře), odeslání chrání CSRF token.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/crypto.php';
require __DIR__ . '/php/layout.php';
require __DIR__ . '/php/validace.php';

const OZNAMENI_PREDMET_MAX = 100;
const OZNAMENI_TEXT_MAX    = 2000;

$uzivatel = vyzaduj_prihlaseni($pdo);
if ($uzivatel['role'] !== 'admin') {
    flash('chyba', 'Tato stránka je jen pro administrátory.');
    header('Location: index.php');
    exit;
}
$adminId = (int)$uzivatel['id'];

$stmt = $pdo->prepare('SELECT id FROM uzivatele WHERE id <> ? ORDER BY id');
$stmt->execute([$adminId]);
$prijemci = $stmt->fetchAll(PDO::FETCH_COLUMN);

$hodnoty = ['predmet' => '', 'text' => ''];
$chyby = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_vyzaduj();

    $hodnoty['predmet'] = registrace_trim((string)($_POST['predmet'] ?? ''));
    $hodnoty['text']    = registrace_trim(str_replace("\r\n", "\n", (string)($_POST['text'] ?? '')));

    if ($hodnoty['predmet'] === '') {
        $chyby['predmet'] = HLASKA_POVINNE;
    } elseif (mb_strlen($hodnoty['predmet'], 'UTF-8') > OZNAMENI_PREDMET_MAX) {
        $chyby['predmet'] = 'Předmět může mít nejvýše ' . OZNAMENI_PREDMET_MAX . ' znaků.';
    }
    if ($hodnoty['text'] === '') {
        $chyby['text'] = HLASKA_POVINNE;
    } elseif (mb_strlen($hodnoty['text'], 'UTF-8') > OZNAMENI_TEXT_MAX) {
        $chyby['text'] = 'Text může mít nejvýše ' . OZNAMENI_TEXT_MAX . ' znaků.';
    }
    if (!$prijemci) {
        $chyby['_obecna'] = 'Zatím není komu oznámení poslat (neexistují žádní jiní uživatelé).';
    }

    if (!$chyby) {
        try {
            $pdo->beginTransaction();
            $vlozZpravu = $pdo->prepare(
                'INSERT INTO zpravy (odesilatel_id, prijemce_id, predmet, sifrovany_text) VALUES (?, ?, ?, ?)'
            );
            $vlozNotifikaci = $pdo->prepare(
                'INSERT INTO notifikace (uzivatel_id, zprava_id, obsah_notifikace) VALUES (?, ?, ?)'
            );
            foreach ($prijemci as $idPrijemce) {
                $vlozZpravu->execute([$adminId, (int)$idPrijemce, encrypt($hodnoty['predmet']), encrypt($hodnoty['text'])]);
                $vlozNotifikaci->execute([(int)$idPrijemce, (int)$pdo->lastInsertId(), 'Nové oznámení od správce']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('admin_oznameni.php: ' . $e->getMessage());
            $chyby['_obecna'] = 'Oznámení se nepodařilo odeslat, nikdo ho nedostal. Zkuste to prosím později.';
        }

        if (!$chyby) {
            $n = count($prijemci);
            flash('ok', 'Oznámení bylo odesláno ' . $n . ' ' . sklonuj($n, 'uživateli', 'uživatelům', 'uživatelům') . '.');
            header('Location: admin_oznameni.php');
            exit;
        }
    }
}

// ---------- výpis ----------
function pole_chyba(array $chyby, string $pole): string
{
    return isset($chyby[$pole])
        ? '<div class="field-error" id="err-' . h($pole) . '">' . h($chyby[$pole]) . '</div>'
        : '';
}

function pole_atributy(array $chyby, string $pole, string $trida = ''): string
{
    $t = trim($trida . (isset($chyby[$pole]) ? ' invalid' : ''));
    return ($t !== '' ? ' class="' . h($t) . '"' : '')
        . (isset($chyby[$pole]) ? ' aria-invalid="true" aria-describedby="err-' . h($pole) . '"' : '');
}

$pocet = count($prijemci);
hlavicka('Hromadné oznámení', $uzivatel, 'admin');
?>
<main class="w-full max-w-4xl mx-auto pt-[150px] pb-20 px-[4vw]">
<h1 class="text-4xl sm:text-6xl font-black uppercase tracking-tighter mb-10">Hromadné oznámení</h1>

<?php if (isset($chyby['_obecna'])): ?>
    <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-200 px-5 py-4 font-bold" role="alert"><?= h($chyby['_obecna']) ?></div>
<?php endif; ?>

<form id="oznameni-formular" class="bg-epilogCard border border-white/10 rounded p-6 sm:p-8" action="admin_oznameni.php" method="POST" data-prijemcu="<?= $pocet ?>" novalidate>
    <?= csrf_pole() ?>

    <p class="text-sm text-white/60 mb-6">Oznámení dostane každý jiný uživatel (<?= $pocet ?>) do zpráv a uvidí upozornění v navigaci. Po odeslání ho nejde vzít zpět.</p>

    <div class="form-group mb-5">
        <label class="form-label" for="predmet">Předmět</label>
        <input type="text" name="predmet" id="predmet" maxlength="<?= OZNAMENI_PREDMET_MAX ?>" required value="<?= h($hodnoty['predmet']) ?>"<?= pole_atributy($chyby, 'predmet', 'brutal-input') ?>>
        <?= pole_chyba($chyby, 'predmet') ?>
    </div>

    <div class="form-group mb-5">
        <label class="form-label" for="text">Text oznámení</label>
        <textarea name="text" id="text" rows="8" maxlength="<?= OZNAMENI_TEXT_MAX ?>" required<?= pole_atributy($chyby, 'text', 'brutal-input resize-y') ?>><?= h($hodnoty['text']) ?></textarea>
        <p class="text-xs text-white/50 mt-2"><span id="text-pocet">0</span> / <?= OZNAMENI_TEXT_MAX ?> znaků</p>
        <?= pole_chyba($chyby, 'text') ?>
    </div>

    <div class="flex flex-wrap gap-3 mt-4">
        <button type="submit" class="tl tl-primarni"<?= $pocet === 0 ? ' disabled' : '' ?>>Odeslat všem</button>
        <a class="tl" href="index.php">Zrušit</a>
    </div>
</form>

<script src="js/admin_oznameni.js"></script>
</main>
<?php
paticka();
