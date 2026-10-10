<?php
declare(strict_types=1);

/**
 * Migrace existující databáze: přidá do tabulky serialy sloupce pocet_serii a pocet_epizod
 * a vyplní je pro ukázkové seriály (podle názvu). Data uživatelů, hodnocení ani watchlist se nemění.
 *
 * Spuštění z kořene projektu:  php sql/migrace_epizody.php
 * Skript jde pustit opakovaně – už existující sloupce přeskočí.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Tento skript jde spustit jen z příkazové řádky.\n");
}

require __DIR__ . '/../php/db.php';   // $pdo

$sloupce = array_column($pdo->query('PRAGMA table_info(serialy)')->fetchAll(), 'name');

$pdo->beginTransaction();
try {
    if (!in_array('pocet_serii', $sloupce, true)) {
        $pdo->exec('ALTER TABLE serialy ADD COLUMN pocet_serii INTEGER CHECK (pocet_serii IS NULL OR pocet_serii > 0)');
        echo "Přidán sloupec pocet_serii.\n";
    }
    if (!in_array('pocet_epizod', $sloupce, true)) {
        $pdo->exec('ALTER TABLE serialy ADD COLUMN pocet_epizod INTEGER CHECK (pocet_epizod IS NULL OR pocet_epizod > 0)');
        echo "Přidán sloupec pocet_epizod.\n";
    }

    $upravit = $pdo->prepare('UPDATE serialy SET pocet_serii = ?, pocet_epizod = ? WHERE nazev = ? AND pocet_epizod IS NULL');
    $celkem = 0;
    foreach (require __DIR__ . '/epizody.php' as $nazev => [$serii, $epizod]) {
        $upravit->execute([$serii, $epizod, $nazev]);
        $celkem += $upravit->rowCount();
    }
    $pdo->commit();
    echo "Vyplněno seriálů: $celkem.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Migrace selhala, změny byly vráceny: ' . $e->getMessage() . "\n");
    exit(1);
}
