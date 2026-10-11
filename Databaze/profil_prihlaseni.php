<?php
declare(strict_types=1);

/**
 * Změna přihlašovacích údajů přihlášeného uživatele: login a/nebo heslo.
 *
 * - Každá změna vyžaduje zadání SOUČASNÉHO hesla .
 * - Heslo se ukládá jen jako hash (password_hash). Po změně hesla se vygeneruje nové ID session.
 * - Měnit jde jen vlastní účet: ID se bere ze session, nikdy z formuláře.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';
require __DIR__ . '/php/validace.php';

$uzivatel = vyzaduj_prihlaseni($pdo);
$uid = (int)$uzivatel['id'];

const HLASKA_LOGIN_OBSAZEN = 'Tento login je už obsazený, zvolte prosím jiný.';

$hodnoty = ['new_login' => (string)$uzivatel['login']];   
$chyby = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_vyzaduj();

    $aktualni   = (string)($_POST['current_password'] ?? '');
    $novyLogin  = registrace_trim((string)($_POST['new_login'] ?? ''));
    $noveHeslo  = (string)($_POST['new_password'] ?? '');       
    $noveHeslo2 = (string)($_POST['new_password2'] ?? '');
    $hodnoty['new_login'] = $novyLogin;

    $stmt = $pdo->prepare('SELECT login, heslo FROM uzivatele WHERE id = ?');
    $stmt->execute([$uid]);
    $radek = $stmt->fetch();

    $menimLogin = $novyLogin !== (string)$radek['login'];       
    $menimHeslo = $noveHeslo !== '' || $noveHeslo2 !== '';

    // 1) formát vstupů
    if ($aktualni === '') {
        $chyby['current_password'] = HLASKA_POVINNE;
    }
    if ($menimLogin && ($chyba = validuj_pole('reg_login', $novyLogin)) !== null) {
        $chyby['new_login'] = $chyba;
    }
    if ($menimHeslo) {
        if (($chyba = validuj_pole('password', $noveHeslo)) !== null) {
            $chyby['new_password'] = $chyba;
        } elseif (!hash_equals($noveHeslo, $noveHeslo2)) {
            $chyby['new_password2'] = 'Hesla se neshodují.';
        }
    }
    if (!$menimLogin && !$menimHeslo) {
        $chyby['_obecna'] = 'Nezměnili jste login ani heslo.';
    }

    // 2) současné heslo 
    if (!$chyby && !password_verify($aktualni, (string)$radek['heslo'])) {
        usleep(300000);   // drobné zpomalení hádání hesel (stejně jako v login.php)
        $chyby['current_password'] = 'Současné heslo není správné.';
    }
    if (!$chyby && $menimHeslo && password_verify($noveHeslo, (string)$radek['heslo'])) {
        $chyby['new_password'] = 'Nové heslo se musí lišit od současného.';
    }

    // 3) obsazený login 
    if (!$chyby && $menimLogin) {
        $stmt = $pdo->prepare('SELECT 1 FROM uzivatele WHERE login = ? AND id <> ?');
        $stmt->execute([$novyLogin, $uid]);
        if ($stmt->fetchColumn() !== false) {
            $chyby['new_login'] = HLASKA_LOGIN_OBSAZEN;
        }
    }

    // 4) uložení
    if (!$chyby) {
        $set = [];
        $param = [];
        if ($menimLogin) {
            $set[] = 'login = ?';
            $param[] = $novyLogin;
        }
        if ($menimHeslo) {
            $set[] = 'heslo = ?';
            $param[] = password_hash($noveHeslo, PASSWORD_DEFAULT);
        }
        $param[] = $uid;

        try {
            $pdo->prepare('UPDATE uzivatele SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($param);
        } catch (Throwable $e) {
            // Souběh: mezi kontrolou a UPDATE si login mohl zabrat někdo jiný -> zachytí ho UNIQUE v DB.
            if ($e instanceof PDOException && ($e->errorInfo[0] ?? '') === '23000') {
                $chyby['new_login'] = HLASKA_LOGIN_OBSAZEN;
            } else {
                error_log('profil_prihlaseni.php: ' . $e->getMessage());
                $chyby['_obecna'] = 'Změny se nepodařilo uložit, zkuste to prosím později.';
            }
        }

        if (!$chyby) {
            if ($menimHeslo) {
                session_regenerate_id(true);  
            }
            flash('ok', $menimLogin && $menimHeslo ? 'Login i heslo byly změněny.'
                : ($menimLogin ? 'Login byl změněn.' : 'Heslo bylo změněno.'));
            header('Location: profil.php');
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

hlavicka('Změna přihlašovacích údajů', $uzivatel, 'profil');
?>
<main class="w-full max-w-4xl mx-auto pt-[150px] pb-20 px-[4vw]">
<h1 class="text-4xl sm:text-6xl font-black uppercase tracking-tighter mb-10">Změna přihlašovacích údajů</h1>

<?php if (isset($chyby['_obecna'])): ?>
    <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-200 px-5 py-4 font-bold" role="alert"><?= h($chyby['_obecna']) ?></div>
<?php endif; ?>

<form id="prihlaseni-formular" class="bg-epilogCard border border-white/10 rounded p-6 sm:p-8" action="profil_prihlaseni.php" method="POST" novalidate>
    <?= csrf_pole() ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
        <div class="form-group mb-5 md:col-span-2">
            <label class="form-label" for="new_login">Login</label>
            <input type="text" name="new_login" id="new_login" maxlength="20" required autocomplete="username"
                   data-puvodni="<?= h((string)$uzivatel['login']) ?>" value="<?= h($hodnoty['new_login']) ?>"<?= pole_atributy($chyby, 'new_login', 'brutal-input') ?>>
            <p class="text-xs text-white/50 mt-2">4–20 znaků: písmena bez diakritiky, číslice nebo podtržítko.</p>
            <?= pole_chyba($chyby, 'new_login') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="new_password">Nové heslo <span class="normal-case font-normal text-white/40">(nepovinné)</span></label>
            <input type="password" name="new_password" id="new_password" maxlength="64" autocomplete="new-password"<?= pole_atributy($chyby, 'new_password', 'brutal-input') ?>>
            <p class="text-xs text-white/50 mt-2">8–64 znaků bez diakritiky, aspoň jedno písmeno a jedna číslice. Když heslo měnit nechcete, nechte pole prázdná.</p>
            <?= pole_chyba($chyby, 'new_password') ?>
        </div>
        <div class="form-group mb-5">
            <label class="form-label" for="new_password2">Nové heslo znovu</label>
            <input type="password" name="new_password2" id="new_password2" maxlength="64" autocomplete="new-password"<?= pole_atributy($chyby, 'new_password2', 'brutal-input') ?>>
            <?= pole_chyba($chyby, 'new_password2') ?>
        </div>
        <div class="form-group mb-5 md:col-span-2">
            <label class="form-label" for="current_password">Současné heslo</label>
            <input type="password" name="current_password" id="current_password" maxlength="64" required autocomplete="current-password"<?= pole_atributy($chyby, 'current_password', 'brutal-input') ?>>
            <p class="text-xs text-white/50 mt-2">Pro potvrzení změny je potřeba zadat současné heslo.</p>
            <?= pole_chyba($chyby, 'current_password') ?>
        </div>
    </div>

    <div class="flex flex-wrap gap-3 mt-4">
        <button type="submit" class="tl tl-primarni">Uložit změny</button>
        <a class="tl" href="profil.php">Zrušit</a>
    </div>
</form>

<script src="js/validace.js"></script>
<script src="js/profil_prihlaseni.js"></script>
</main>
<?php
paticka();
