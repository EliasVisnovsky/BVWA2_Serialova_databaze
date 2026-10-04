<?php
declare(strict_types=1);

/**
 * Registrace nového uživatele.
 *
 * Očekává POST (multipart/form-data) z prihlaseni_a_registr.html.
 *  - Požadavek z JS (hlavička Accept: application/json) dostane JSON:
 *      úspěch  -> 200 {"ok": true,  "zprava": "..."}
 *      chyba   -> 422 {"ok": false, "chyby": {"pole": "hláška", ...}}   (klíč "_obecna" = chyba bez pole)
 *  - Bez JS se uživatel přesměruje zpět na formulář (úspěch), nebo uvidí jednoduchý výpis chyb.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/crypto.php';
require __DIR__ . '/php/validace.php';
require __DIR__ . '/php/foto.php';

const FORMULAR = 'prihlaseni_a_registr.html';

$jeJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function odpovez_json(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function odpovez_chyby(array $chyby, bool $jeJson, int $status = 422): void
{
    if ($jeJson) {
        odpovez_json($status, ['ok' => false, 'chyby' => $chyby]);
    }
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>Chyba registrace</title>'
       . '<link rel="stylesheet" href="css/prihlaseni_a_registr_styl.css"></head><body><div class="container">'
       . '<h2>Registraci se nepodařilo dokončit</h2><ul>';
    foreach ($chyby as $hlaska) {
        echo '<li>' . htmlspecialchars($hlaska, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p class="under-text"><a href="javascript:history.back()">Zpět na formulář</a></p></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . FORMULAR);
    exit;
}

// ---------- 1) Validace ----------
// Když přijde víc dat než post_max_size, PHP zahodí $_POST i $_FILES – ať uživatel nevidí jen "pole je povinné".
if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    odpovez_chyby(['_obecna' => 'Odeslaná data jsou příliš velká. Zmenšete profilovou fotku (max. 2 MB).'], $jeJson, 413);
}

$jmeno    = registrace_trim((string)($_POST['name'] ?? ''));
$prijmeni = registrace_trim((string)($_POST['surname'] ?? ''));
$email    = registrace_trim((string)($_POST['email'] ?? ''));
$telefon  = registrace_trim((string)($_POST['phone_number'] ?? ''));
$pohlavi  = (string)($_POST['gender'] ?? '');
$login    = registrace_trim((string)($_POST['reg_login'] ?? ''));
$heslo    = (string)($_POST['password'] ?? '');

$chyby = validuj_registraci($_POST);

[$fotoChyba, $fotoInfo] = foto_zvaliduj($_FILES['pfp'] ?? null);
if ($fotoChyba !== null) {
    $chyby['pfp'] = $fotoChyba;
}

// Duplicitu loginu hlásíme hned, i když jsou jinde chyby – uživatel uvidí všechno najednou.
// Sloupec login má COLLATE NOCASE, takže "Karel" a "karel" jsou stejný login.
if (!isset($chyby['reg_login'])) {
    $stmt = $pdo->prepare('SELECT 1 FROM uzivatele WHERE login = ?');
    $stmt->execute([$login]);
    if ($stmt->fetchColumn() !== false) {
        $chyby['reg_login'] = 'Tento login je už obsazený, zvolte prosím jiný.';
    }
}

if ($chyby) {
    odpovez_chyby($chyby, $jeJson);
}

// ---------- 2) Uložení ----------
$fotoCesta = null;
try {
    $fotoCesta = foto_uloz($fotoInfo);

    $pdo->prepare(
        'INSERT INTO uzivatele (jmeno, prijmeni, email, telefon, pohlavi, foto_cesta, login, heslo, role)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'user\')'
    )->execute([
        $jmeno,
        $prijmeni,
        encrypt($email),                                // e-mail a telefon se ukládají šifrovaně
        encrypt($telefon),
        $pohlavi,
        $fotoCesta,
        $login,
        password_hash($heslo, PASSWORD_DEFAULT),
    ]);
} catch (Throwable $e) {
    if ($fotoCesta !== null) {
        @unlink(__DIR__ . '/' . $fotoCesta);            // ať po neúspěchu nezůstane osiřelá fotka
    }
    // Souběh: mezi kontrolou a INSERTem si login mohl zabrat někdo jiný -> zachytí ho UNIQUE v DB.
    if ($e instanceof PDOException && ($e->errorInfo[0] ?? '') === '23000' && stripos($e->getMessage(), 'login') !== false) {
        odpovez_chyby(['reg_login' => 'Tento login je už obsazený, zvolte prosím jiný.'], $jeJson);
    }
    error_log('register.php: ' . $e->getMessage());     // detail jen do logu, uživateli ne
    odpovez_chyby(['_obecna' => 'Registraci se nepodařilo dokončit, zkuste to prosím později.'], $jeJson, 500);
}

// ---------- 3) Hotovo ----------
if ($jeJson) {
    odpovez_json(200, ['ok' => true, 'zprava' => 'Registrace proběhla úspěšně. Nyní se můžete přihlásit.']);
}
header('Location: ' . FORMULAR . '?registrace=ok');
exit;
