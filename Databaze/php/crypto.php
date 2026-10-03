<?php

const CRYPTO_CIPHER  = 'aes-256-gcm';
const CRYPTO_IV_LEN  = 12;   
const CRYPTO_TAG_LEN = 16;   

/**
 * Načte šifrovací klíč z config.php
 */
function crypto_key(): string
{
    static $key = null;                       
    if ($key === null) {
        $config = require __DIR__ . '/config.php';
        $key = base64_decode($config['encryption_key'] ?? '', true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('Neplatný šifrovací klíč v config.php (musí být 32 bajtů v base64).');
        }
    }
    return $key;
}

/**
 * Zašifruje text
 */
function encrypt(string $plain): string
{
    $iv  = random_bytes(CRYPTO_IV_LEN);       
    $tag = '';
    $cipher = openssl_encrypt(
        $plain, CRYPTO_CIPHER, crypto_key(),
        OPENSSL_RAW_DATA, $iv, $tag, '', CRYPTO_TAG_LEN
    );
    if ($cipher === false) {
        throw new RuntimeException('Šifrování selhalo.');
    }
    return base64_encode($iv . $tag . $cipher);
}

/**
 * Dešifruje
 */
function decrypt(string $data): string
{
    $raw = base64_decode($data, true);
    if ($raw === false || strlen($raw) < CRYPTO_IV_LEN + CRYPTO_TAG_LEN) {
        throw new RuntimeException('Neplatná šifrovaná data.');
    }
    $iv     = substr($raw, 0, CRYPTO_IV_LEN);
    $tag    = substr($raw, CRYPTO_IV_LEN, CRYPTO_TAG_LEN);
    $cipher = substr($raw, CRYPTO_IV_LEN + CRYPTO_TAG_LEN);

    $plain = openssl_decrypt($cipher, CRYPTO_CIPHER, crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Dešifrování selhalo (špatný klíč nebo poškozená data).');
    }
    return $plain;
}
