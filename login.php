<?php
declare(strict_types=1);

/**
 * Přihlášení. Očekává POST z prihlaseni_a_registr.html (pole login, password).
 * Úspěch -> přesměrování na stránku, ze které uživatel přišel (nebo na katalog).
 * Chyba  -> zpět na formulář s ?login=chyba (hláška je záměrně obecná, ať se nedá zjistit, které loginy existují).
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . STRANA_PRIHLASENI);
    exit;
}

session_spust();

$login = trim((string)($_POST['login'] ?? ''));
$heslo = (string)($_POST['password'] ?? '');

$stmt = $pdo->prepare('SELECT id, heslo FROM uzivatele WHERE login = ?');   // login je COLLATE NOCASE
$stmt->execute([$login]);
$radek = $stmt->fetch();

// I pro neexistující login se ověřuje hash, ať odpověď netrvá znatelně kratší (zjišťování loginů podle času).
$hash = $radek['heslo'] ?? password_hash('neexistujici-uzivatel', PASSWORD_DEFAULT);
$ok = password_verify($heslo, $hash) && $radek !== false && $login !== '';

if (!$ok) {
    usleep(300000);   // drobné zpomalení hádání hesel
    header('Location: ' . STRANA_PRIHLASENI . '?login=chyba');
    exit;
}

if (password_needs_rehash($radek['heslo'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE uzivatele SET heslo = ? WHERE id = ?')
        ->execute([password_hash($heslo, PASSWORD_DEFAULT), $radek['id']]);
}

$cil = bezpecny_navrat($_SESSION['po_prihlaseni'] ?? null, 'index.php');

session_regenerate_id(true);          // nové ID session po přihlášení (ochrana proti session fixation)
$_SESSION = ['uid' => (int)$radek['id'], 'posledni_aktivita' => time()];

header('Location: ' . $cil);
exit;
