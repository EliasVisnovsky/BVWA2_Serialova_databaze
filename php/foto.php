<?php
declare(strict_types=1);

/**
 * Profilová fotka – pravidla a zpracování.
 *
 * Limity jsou zrcadlené v js/validace.js (konstanta FOTO) – při změně upravte oba soubory.
 * Server je ale vždy rozhodující: JS kontrola je jen pohodlí pro uživatele.
 *
 * Co se děje s nahraným souborem:
 *  1. kontrola chyby uploadu, velikosti, skutečného typu (podle obsahu, ne podle přípony)
 *     a rozlišení,
 *  2. obrázek se načte a znovu uloží jako JPEG s delší stranou max. FOTO_VYSTUP_STRANA px
 *     (tím zmizí EXIF/GPS i případný vložený škodlivý obsah),
 *  3. soubor dostane náhodný název, uživatelský název se nepoužívá.
 *
 * Vyžaduje PHP rozšíření: gd, fileinfo (volitelně exif pro správné otočení fotek z mobilu).
 */

const FOTO_FORMATY       = ['image/jpeg', 'image/png', 'image/webp'];
const FOTO_MAX_BAJTU     = 2 * 1024 * 1024;   // 2 MB
const FOTO_MIN_STRANA    = 200;               // každá strana min. 200 px
const FOTO_MAX_STRANA    = 5000;              // každá strana max. 5000 px
const FOTO_MAX_PIXELU    = 16000000;          // a max. 16 Mpx celkem (ochrana paměti při dekódování)
const FOTO_VYSTUP_STRANA = 800;               // delší strana uložené fotky
const FOTO_JPEG_KVALITA  = 85;
const FOTO_SLOZKA        = 'uploads';         // relativně ke kořeni projektu

const FOTO_HLASKY = [
    'chybi'     => 'Vyberte profilovou fotku.',
    'format'    => 'Povolené formáty fotky jsou JPEG, PNG a WebP.',
    'velikost'  => 'Fotka je příliš velká (maximum je 2 MB).',
    'male'      => 'Fotka je příliš malá (minimum je 200 × 200 px).',
    'velke'     => 'Fotka má příliš velké rozlišení (maximum je 5000 px na delší straně a 16 megapixelů).',
    'neplatna'  => 'Soubor není platný obrázek.',
    'upload'    => 'Nahrání fotky se nezdařilo, zkuste to prosím znovu.',
];

/**
 * Zkontroluje položku z $_FILES.
 * @return array{0: ?string, 1: ?array} [hláška chyby | null, informace o obrázku | null]
 */
function foto_zvaliduj(?array $soubor): array
{
    if ($soubor === null || ($soubor['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [FOTO_HLASKY['chybi'], null];
    }
    switch ($soubor['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [FOTO_HLASKY['velikost'], null];
        default:
            return [FOTO_HLASKY['upload'], null];
    }

    $tmp = (string)$soubor['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return [FOTO_HLASKY['upload'], null];
    }
    if (filesize($tmp) > FOTO_MAX_BAJTU) {
        return [FOTO_HLASKY['velikost'], null];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, FOTO_FORMATY, true)) {
        return [FOTO_HLASKY['format'], null];
    }

    $rozmery = @getimagesize($tmp);   // čte jen hlavičku, obrázek se zatím nedekóduje
    if ($rozmery === false || $rozmery['mime'] !== $mime) {
        return [FOTO_HLASKY['neplatna'], null];
    }
    [$sirka, $vyska] = $rozmery;

    if ($sirka < FOTO_MIN_STRANA || $vyska < FOTO_MIN_STRANA) {
        return [FOTO_HLASKY['male'], null];
    }
    if ($sirka > FOTO_MAX_STRANA || $vyska > FOTO_MAX_STRANA || $sirka * $vyska > FOTO_MAX_PIXELU) {
        return [FOTO_HLASKY['velke'], null];
    }

    return [null, ['tmp' => $tmp, 'mime' => $mime, 'sirka' => $sirka, 'vyska' => $vyska]];
}

/**
 * Zpracuje ověřenou fotku a uloží ji do uploads/.
 * @return string cesta pro databázi relativní ke kořeni projektu, např. "uploads/3f9c….jpg"
 * @throws RuntimeException když se obrázek nepodaří zpracovat
 */
function foto_uloz(array $info): string
{
    $data = file_get_contents($info['tmp']);
    $zdroj = $data === false ? false : @imagecreatefromstring($data);
    if ($zdroj === false) {
        throw new RuntimeException(FOTO_HLASKY['neplatna']);
    }

    // Otočení podle EXIF (fotky z mobilu); po překódování by EXIF zmizel a fotka by byla na boku.
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($info['tmp']);
        $uhel = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($uhel !== 0 && ($otoceny = imagerotate($zdroj, $uhel, 0)) !== false) {
            $zdroj = $otoceny;
        }
    }

    $sirka = imagesx($zdroj);
    $vyska = imagesy($zdroj);
    $meritko = min(1.0, FOTO_VYSTUP_STRANA / max($sirka, $vyska));   // zmenšuje, nikdy nezvětšuje
    $novaSirka = max(1, (int)round($sirka * $meritko));
    $novaVyska = max(1, (int)round($vyska * $meritko));

    // JPEG nemá průhlednost – PNG/WebP s alfa kanálem se podkládá bílou.
    $cil = imagecreatetruecolor($novaSirka, $novaVyska);
    imagefill($cil, 0, 0, imagecolorallocate($cil, 255, 255, 255));
    imagecopyresampled($cil, $zdroj, 0, 0, 0, 0, $novaSirka, $novaVyska, $sirka, $vyska);

    $slozka = dirname(__DIR__) . '/' . FOTO_SLOZKA;
    if (!is_dir($slozka) && !mkdir($slozka, 0755, true) && !is_dir($slozka)) {
        throw new RuntimeException('Nelze vytvořit složku ' . FOTO_SLOZKA . '/.');
    }

    $nazev = bin2hex(random_bytes(16)) . '.jpg';
    if (!imagejpeg($cil, $slozka . '/' . $nazev, FOTO_JPEG_KVALITA)) {
        throw new RuntimeException('Fotku se nepodařilo uložit.');
    }
    return FOTO_SLOZKA . '/' . $nazev;
}
