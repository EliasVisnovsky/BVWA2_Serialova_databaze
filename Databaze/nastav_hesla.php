<?php
require __DIR__ . '/php/db.php';
$set = $pdo->prepare('UPDATE uzivatele SET heslo = ? WHERE login = ?');
foreach (['admin' => 'Admin1234', 'tereza_n' => 'Demo1234'] as $login => $heslo) {
    $set->execute([password_hash($heslo, PASSWORD_DEFAULT), $login]);
    echo "$login: změněno řádků = {$set->rowCount()}\n";
}
echo "\nLoginy v databázi:\n";
foreach ($pdo->query('SELECT id, login, role FROM uzivatele ORDER BY id') as $r) {
    echo "{$r['id']}  {$r['login']}  ({$r['role']})\n";
}