<?php
declare(strict_types=1);

/** Odhlášení (POST s CSRF tokenem, aby uživatele nemohl odhlásit cizí odkaz/obrázek). */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_vyzaduj();
odhlas();
header('Location: ' . STRANA_PRIHLASENI . '?login=odhlaseno');
exit;
