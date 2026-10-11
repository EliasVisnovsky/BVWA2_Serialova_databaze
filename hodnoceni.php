<?php
declare(strict_types=1);

/**
 * Hodnocení seriálů (POST + CSRF).
 *   akce=uloz  serial_id, hvezdy (1–5), komentar (volitelný, max 1000 znaků)
 *              – vytvoří nebo upraví vlastní hodnocení. Seriál se zároveň zařadí do watchlistu jako
 *                "Viděno" (kromě stavu "Sleduji" – rozkoukaný seriál lze hodnotit a dál zůstává rozkoukaný).
 *   akce=smaz  hodnoceni_id – smaže hodnocení; své může každý, cizí jen administrátor
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

const KOMENTAR_MAX = 1000;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$uzivatel = vyzaduj_prihlaseni($pdo);
csrf_vyzaduj();

$akce = (string)($_POST['akce'] ?? '');
$navratPost = $_POST['navrat'] ?? null;

if ($akce === 'uloz') {
    $serialId = filter_var($_POST['serial_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $navrat = bezpecny_navrat($navratPost, $serialId ? 'serial.php?id=' . $serialId . '#hodnoceni' : 'index.php');

    $stmt = $pdo->prepare('SELECT nazev FROM serialy WHERE id = ?');
    $stmt->execute([$serialId ?: 0]);
    $nazev = $stmt->fetchColumn();
    if ($nazev === false) {
        flash('chyba', 'Tento seriál neexistuje.');
        header('Location: index.php');
        exit;
    }

    $hvezdy = filter_var($_POST['hvezdy'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $komentar = trim((string)($_POST['komentar'] ?? ''));
    if ($hvezdy === false || $hvezdy === null) {
        flash('chyba', 'Vyberte počet hvězdiček (1–5).');
    } elseif (mb_strlen($komentar, 'UTF-8') > KOMENTAR_MAX) {
        flash('chyba', 'Komentář může mít nejvýše ' . KOMENTAR_MAX . ' znaků.');
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO hodnoceni (serial_id, uzivatel_id, pocet_hvezd, komentar) VALUES (?, ?, ?, ?)
                 ON CONFLICT (serial_id, uzivatel_id)
                 DO UPDATE SET pocet_hvezd = excluded.pocet_hvezd, komentar = excluded.komentar, datum_pridani = CURRENT_TIMESTAMP'
            )->execute([$serialId, $uzivatel['id'], $hvezdy, $komentar === '' ? null : $komentar]);

            $pdo->prepare(
                "INSERT INTO watchlist (uzivatel_id, serial_id, stav) VALUES (?, ?, 'videno')
                 ON CONFLICT (uzivatel_id, serial_id) DO UPDATE SET stav = 'videno' WHERE watchlist.stav = 'chci_videt'"
            )->execute([$uzivatel['id'], $serialId]);
            $pdo->commit();
            flash('ok', 'Hodnocení seriálu „' . $nazev . '“ bylo uloženo.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('hodnoceni.php: ' . $e->getMessage());
            flash('chyba', 'Hodnocení se nepodařilo uložit, zkuste to prosím později.');
        }
    }
    header('Location: ' . $navrat);
    exit;
}

if ($akce === 'smaz') {
    $hodnoceniId = filter_var($_POST['hodnoceni_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stmt = $pdo->prepare('SELECT h.id, h.serial_id, h.uzivatel_id, s.nazev FROM hodnoceni h JOIN serialy s ON s.id = h.serial_id WHERE h.id = ?');
    $stmt->execute([$hodnoceniId ?: 0]);
    $h = $stmt->fetch();

    $navrat = bezpecny_navrat($navratPost, $h ? 'serial.php?id=' . $h['serial_id'] . '#hodnoceni' : 'index.php');

    if (!$h) {
        flash('chyba', 'Hodnocení neexistuje.');
    } elseif ((int)$h['uzivatel_id'] !== (int)$uzivatel['id'] && $uzivatel['role'] !== 'admin') {
        flash('chyba', 'Cizí hodnocení může mazat jen administrátor.');
    } else {
        $pdo->prepare('DELETE FROM hodnoceni WHERE id = ?')->execute([$h['id']]);
        flash('ok', 'Hodnocení seriálu „' . $h['nazev'] . '“ bylo smazáno.');
    }
    header('Location: ' . $navrat);
    exit;
}

flash('chyba', 'Neznámá akce.');
header('Location: index.php');
exit;
