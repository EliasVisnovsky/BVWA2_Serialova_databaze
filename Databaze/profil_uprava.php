<?php
declare(strict_types=1);

/**
 * Úprava osobních údajů přihlášeného uživatele a výměna profilové fotky
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/crypto.php';
require __DIR__ . '/php/layout.php';
require __DIR__ . '/php/validace.php';
require __DIR__ . '/php/foto.php';

$uzivatel = vyzaduj_prihlaseni($pdo);
$uid = (int)$uzivatel['id'];

const POHLAVI_POPISKY = ['male' => 'Muž', 'female' => 'Žena', 'other' => 'Jiné'];

// ---------- aktuální hodnoty z DB ----------
$stmt = $pdo->prepare('SELECT jmeno, prijmeni, email, telefon, pohlavi FROM uzivatele WHERE id = ?');
$stmt->execute([$uid]);
$radek = $stmt->fetch();

$hodnoty = [
    'name'         => (string)$radek['jmeno'],
    'surname'      => (string)$radek['prijmeni'],
    'email'        => '',
    'phone_number' => '',
    'gender'       => (string)$radek['pohlavi'],
];
$chyby = [];
try {
    $hodnoty['email'] = decrypt((string)$radek['email']);
    $hodnoty['phone_number'] = decrypt((string)$radek['telefon']);
} catch (Throwable $e) {
    error_log('profil_uprava.php: ' . $e->getMessage());
    $chyby['_obecna'] = 'Uložený e-mail nebo telefon se nepodařilo načíst. Zadejte je prosím znovu.';
}

// ---------- uložení ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $chyby = ['_obecna' => 'Odeslaná data jsou příliš velká. Zmenšete profilovou fotku (max. 2 MB).'];
    } else {
        csrf_vyzaduj();

        $chyby = [];
        foreach (array_keys($hodnoty) as $pole) {
            $hodnoty[$pole] = registrace_trim((string)($_POST[$pole] ?? ''));
        }
        $chyby = validuj_udaje_uctu($hodnoty);

        $soubor = $_FILES['pfp'] ?? null;
        $fotoVybrana = $soubor !== null && ($soubor['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $fotoInfo = null;
        if ($fotoVybrana) {
            [$fotoChyba, $fotoInfo] = foto_zvaliduj($soubor);
            if ($fotoChyba !== null) {
                $chyby['pfp'] = $fotoChyba;
            }
        }

        if (!$chyby) {
            $novaFoto = null;
            try {
                if ($fotoVybrana) {
                    $novaFoto = foto_uloz($fotoInfo);
                }
                $pdo->prepare(
                    'UPDATE uzivatele
                        SET jmeno = ?, prijmeni = ?, email = ?, telefon = ?, pohlavi = ?,
                            foto_cesta = COALESCE(?, foto_cesta)
                      WHERE id = ?'
                )->execute([
                    $hodnoty['name'],
                    $hodnoty['surname'],
                    encrypt($hodnoty['email']),
                    encrypt($hodnoty['phone_number']),
                    $hodnoty['gender'],
                    $novaFoto,
                    $uid,
                ]);
            } catch (Throwable $e) {
                if ($novaFoto !== null) {
                    @unlink(__DIR__ . '/' . $novaFoto);     
                }
                error_log('profil_uprava.php: ' . get_class($e) . ': ' . $e->getMessage());   
                if ($fotoVybrana && !function_exists('imagecreatefromstring')) {
                    $chyby['pfp'] = 'Na serveru chybí PHP rozšíření GD, takže fotku nelze zpracovat (zapněte extension=gd v php.ini).';
                } elseif ($fotoVybrana && $novaFoto === null && $e instanceof RuntimeException) {
                    $chyby['pfp'] = $e->getMessage();   // zprávy z foto_uloz() jsou psané pro uživatele
                } else {
                    $chyby['_obecna'] = 'Změny se nepodařilo uložit, zkuste to prosím později.';
                }
            }

            if (!$chyby) {
                $stara = (string)$uzivatel['foto_cesta'];
                if ($novaFoto !== null && preg_match('~^uploads/[0-9a-f]{32}\.jpg$~', $stara) === 1) {
                    @unlink(__DIR__ . '/' . $stara);
                }
                flash('ok', 'Údaje byly uloženy.');
                header('Location: profil.php');
                exit;
            }
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

hlavicka('Upravit údaje', $uzivatel, 'profil');
?>
<main class="w-full max-w-4xl mx-auto pt-[150px] pb-20 px-[4vw]">
<h1 class="text-4xl sm:text-6xl font-black uppercase tracking-tighter mb-10">Upravit údaje</h1>

<?php if (isset($chyby['_obecna'])): ?>
    <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-200 px-5 py-4 font-bold" role="alert"><?= h($chyby['_obecna']) ?></div>
<?php endif; ?>

<form id="uprava-formular" class="bg-epilogCard border border-white/10 rounded p-6 sm:p-8" action="profil_uprava.php" method="POST" enctype="multipart/form-data" novalidate>
    <?= csrf_pole() ?>

    <div class="flex flex-wrap gap-6 items-start mb-8">
        <img id="foto-nahled" class="w-28 h-28 rounded-full object-cover bg-epilogBg border border-white/10" src="<?= h($uzivatel['foto_cesta']) ?>" alt="Aktuální profilová fotka">
        <div class="form-group flex-1 min-w-[220px]">
            <label class="form-label" for="pfp">Nová profilová fotka <span class="normal-case font-normal text-white/40">(nepovinné)</span></label>
            <input type="file" name="pfp" id="pfp" accept="image/jpeg,image/png,image/webp"<?= pole_atributy($chyby, 'pfp', 'file:mr-4 file:py-2.5 file:px-4 file:border-0 file:bg-white file:text-epilogBg file:font-bold file:uppercase file:cursor-pointer text-sm text-white/70 w-full') ?>>
            <p class="text-xs text-white/50 mt-2">JPEG, PNG nebo WebP, max. 2 MB, min. 200 × 200 px. Když nic nevyberete, fotka zůstane stejná.</p>
            <?= pole_chyba($chyby, 'pfp') ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
        <div class="form-group mb-5">
            <label class="form-label" for="name">Jméno</label>
            <input type="text" name="name" id="name" maxlength="50" required value="<?= h($hodnoty['name']) ?>"<?= pole_atributy($chyby, 'name', 'brutal-input') ?>>
            <?= pole_chyba($chyby, 'name') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="surname">Příjmení</label>
            <input type="text" name="surname" id="surname" maxlength="50" required value="<?= h($hodnoty['surname']) ?>"<?= pole_atributy($chyby, 'surname', 'brutal-input') ?>>
            <?= pole_chyba($chyby, 'surname') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="email">E-mail</label>
            <input type="text" inputmode="email" autocomplete="email" name="email" id="email" maxlength="254" required value="<?= h($hodnoty['email']) ?>"<?= pole_atributy($chyby, 'email', 'brutal-input') ?>>
            <?= pole_chyba($chyby, 'email') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="phone_number">Telefon</label>
            <input type="text" inputmode="tel" autocomplete="tel" name="phone_number" id="phone_number" required placeholder="+420 123 456 789" value="<?= h($hodnoty['phone_number']) ?>"<?= pole_atributy($chyby, 'phone_number', 'brutal-input') ?>>
            <?= pole_chyba($chyby, 'phone_number') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="gender">Pohlaví</label>
            <select name="gender" id="gender" required<?= pole_atributy($chyby, 'gender', 'brutal-input') ?>>
                <?php foreach (POHLAVI_POPISKY as $klic => $popis): ?>
                    <option value="<?= h($klic) ?>"<?= $hodnoty['gender'] === $klic ? ' selected' : '' ?>><?= h($popis) ?></option>
                <?php endforeach; ?>
            </select>
            <?= pole_chyba($chyby, 'gender') ?>
        </div>
    </div>

    <p class="text-xs text-white/50 mt-2">Login a heslo se mění na samostatné stránce: <a class="text-white underline font-bold" href="profil_prihlaseni.php">Změnit login a heslo</a>.</p>

    <div class="flex flex-wrap gap-3 mt-8">
        <button type="submit" class="tl tl-primarni">Uložit změny</button>
        <a class="tl" href="profil.php">Zrušit</a>
    </div>
</form>

<script src="js/validace.js"></script>
<script src="js/profil_uprava.js"></script>
</main>
<?php
paticka();
