<?php
declare(strict_types=1);

/**
 * Přihlášení uživatele (session), automatické odhlášení po nečinnosti, CSRF ochrana, flash zprávy.
 * Vyžaduje, aby byl před ním načtený php/db.php ($pdo).
 */

const SESSION_NECINNOST = 20 * 60;   // 20 minut bez aktivity = odhlášení
const STRANA_PRIHLASENI = 'prihlaseni_a_registr.html';

function session_spust(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,            // cookie zmizí se zavřením prohlížeče
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,         // JS se k session cookie nedostane
        'samesite' => 'Lax',
    ]);
    session_name('serialy_sid');
    ini_set('session.gc_maxlifetime', (string)SESSION_NECINNOST);
    session_start();

    // Automatické odhlášení po 20 minutách nečinnosti
    if (isset($_SESSION['uid'], $_SESSION['posledni_aktivita'])
        && time() - (int)$_SESSION['posledni_aktivita'] > SESSION_NECINNOST) {
        odhlas();
        session_start();
        $_SESSION['timeout'] = true;
    }
    if (isset($_SESSION['uid'])) {
        $_SESSION['posledni_aktivita'] = time();
    }
}

/** Zruší session (včetně cookie). */
function odhlas(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $c['path'], $c['domain'] ?? '', (bool)$c['secure'], (bool)$c['httponly']);
    }
    session_destroy();
}

/** Přihlášený uživatel (řádek z DB) nebo null. Načítá se při každém požadavku, ať se změna role projeví hned. */
function prihlaseny_uzivatel(PDO $pdo): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    session_spust();
    $cache = null;
    if (isset($_SESSION['uid'])) {
        $stmt = $pdo->prepare('SELECT id, jmeno, prijmeni, login, role, foto_cesta, vytvoreno FROM uzivatele WHERE id = ?');
        $stmt->execute([(int)$_SESSION['uid']]);
        $cache = $stmt->fetch() ?: null;
        if ($cache === null) {       // účet mezitím zmizel
            odhlas();
        }
    }
    return $cache;
}

/** Přesměruje na přihlášení, pokud není nikdo přihlášený. Po přihlášení vrátí uživatele na původní stránku. */
function vyzaduj_prihlaseni(PDO $pdo): array
{
    $u = prihlaseny_uzivatel($pdo);
    if ($u === null) {
        session_spust();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['po_prihlaseni'] = basename($_SERVER['SCRIPT_NAME'])
                . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
        header('Location: ' . STRANA_PRIHLASENI . '?login=nutne');
        exit;
    }
    return $u;
}

/** Povolí jen odkazy na vlastní stránky aplikace (ochrana proti open redirect). */
function bezpecny_navrat(?string $cil, string $vychozi): string
{
    if ($cil !== null && preg_match('~^(index|serial|profil)\.php(\?[A-Za-z0-9=&_%.\-]*)?(#[A-Za-z0-9_\-]*)?$~', $cil)) {
        return $cil;
    }
    return $vychozi;
}

// ---------- CSRF ----------

function csrf_token(): string
{
    session_spust();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_pole(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Ověří CSRF token z POST. Při chybě ukončí požadavek. */
function csrf_vyzaduj(): void
{
    session_spust();
    $poslany = (string)($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $poslany)) {
        http_response_code(403);
        exit('Neplatný bezpečnostní token. Vraťte se zpět, obnovte stránku a zkuste to znovu.');
    }
}

// ---------- flash zprávy (jednorázové hlášky po přesměrování) ----------

function flash(string $typ, string $zprava): void
{
    session_spust();
    $_SESSION['flash'] = ['typ' => $typ, 'zprava' => $zprava];
}

function flash_vyber(): ?array
{
    session_spust();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
