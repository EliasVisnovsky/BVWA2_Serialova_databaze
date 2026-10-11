<?php
declare(strict_types=1);

/**
 * Změny watchlistu přihlášeného uživatele (POST + CSRF).
 *   akce=nastav  serial_id, stav (chci_videt | sleduji | videno)  – přidá seriál nebo změní stav
 *   akce=odeber  serial_id                                         – odebere seriál ze seznamu
 * Volitelné pole "navrat" = stránka aplikace, na kterou se po akci vrátíme.
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$uzivatel = vyzaduj_prihlaseni($pdo);
csrf_vyzaduj();

$serialId = filter_var($_POST['serial_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$navrat = bezpecny_navrat($_POST['navrat'] ?? null, $serialId ? 'serial.php?id=' . $serialId . '#watchlist' : 'index.php');

$stmt = $pdo->prepare('SELECT nazev FROM serialy WHERE id = ?');
$stmt->execute([$serialId ?: 0]);
$nazev = $stmt->fetchColumn();
if ($nazev === false) {
    flash('chyba', 'Tento seriál neexistuje.');
    header('Location: index.php');
    exit;
}

$akce = (string)($_POST['akce'] ?? '');

if ($akce === 'nastav') {
    $stav = (string)($_POST['stav'] ?? '');
    if (!isset(STAVY_WATCHLISTU[$stav])) {
        flash('chyba', 'Neplatný stav seriálu.');
    } else {
        $pdo->prepare(
            'INSERT INTO watchlist (uzivatel_id, serial_id, stav) VALUES (?, ?, ?)
             ON CONFLICT (uzivatel_id, serial_id) DO UPDATE SET stav = excluded.stav'
        )->execute([$uzivatel['id'], $serialId, $stav]);
        flash('ok', '„' . $nazev . '“ je nově ve stavu: ' . STAVY_WATCHLISTU[$stav] . '.');
    }
} elseif ($akce === 'odeber') {
    $pdo->prepare('DELETE FROM watchlist WHERE uzivatel_id = ? AND serial_id = ?')
        ->execute([$uzivatel['id'], $serialId]);
    flash('ok', '„' . $nazev . '“ byl odebrán z vašeho seznamu.');
} else {
    flash('chyba', 'Neznámá akce.');
}

header('Location: ' . $navrat);
exit;
