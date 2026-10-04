<?php
declare(strict_types=1);

/**
 * Pomocník pro tests/parita.js: spustí testovací vektory přes SKUTEČNOU serverovou validaci
 * (validuj_registraci) a vypíše JSON s výsledky a s texty výrazů.
 */
require __DIR__ . '/../php/validace.php';

$vektory = json_decode(file_get_contents(__DIR__ . '/vektory.json'), true, 512, JSON_THROW_ON_ERROR);

// Základ, který projde celou validací; testované pole se vždy přepíše.
$zaklad = [
    'name' => 'Jan', 'surname' => 'Novák', 'email' => 'jan@example.com', 'phone_number' => '+420 123 456 789',
    'gender' => 'male', 'reg_login' => 'jan_n', 'password' => 'Heslo1234',
];
if (validuj_registraci($zaklad) !== []) {
    fwrite(STDERR, "Základní vstup neprošel validací.\n");
    exit(2);
}

$vysledky = [];
foreach ($vektory as $pole => $pripady) {
    foreach ($pripady as [$hodnota]) {
        $chyby = validuj_registraci([$pole => $hodnota] + $zaklad);
        $vysledky[$pole][] = !isset($chyby[$pole]);
    }
}

$pravidla = [];
foreach (REGISTRACE_PRAVIDLA as $pole => $p) {
    $pravidla[$pole] = ['regex' => $p['regex'], 'max' => $p['max'], 'zprava' => $p['zprava']];
}

echo json_encode(['vysledky' => $vysledky, 'pravidla' => $pravidla], JSON_UNESCAPED_UNICODE);
