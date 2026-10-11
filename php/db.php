<?php
$config = require __DIR__ . '/config.php';

$pdo = new PDO('sqlite:' . $config['db_path']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);   
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);           
$pdo->exec('PRAGMA foreign_keys = ON');                          
