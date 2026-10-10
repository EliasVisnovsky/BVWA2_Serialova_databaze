<?php
declare(strict_types=1);

/**
 * Počet sérií a epizod ukázkových seriálů: název => [počet sérií, počet epizod].
 * Používá sql/seed.php (nová databáze) i sql/migrace_epizody.php (už existující databáze).
 * Čísla jsou orientační (stav k datu vytvoření ukázkových dat).
 */
return [
    'Breaking Bad'     => [5, 62],
    'Better Call Saul' => [6, 63],
    'Dark'             => [3, 26],
    'Chernobyl'        => [1, 5],
    'Stranger Things'  => [5, 42],
    'Black Mirror'     => [7, 33],
    'Game of Thrones'  => [8, 73],
    'The Witcher'      => [4, 32],
    'Arcane'           => [2, 18],
    'Rick and Morty'   => [7, 71],
    'The Office'       => [9, 201],
    'Sherlock'         => [4, 13],
    'Planet Earth II'  => [1, 6],
    'The Mandalorian'  => [3, 24],
    'Fleabag'          => [2, 12],
];
