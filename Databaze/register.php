<?php
session_start();

// Připojení k databázi
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'serial_database';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Chyba připojení k databázi: " . $e->getMessage());
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $login = trim($_POST['reg_login'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    // Backend validace údajů
    if (empty($name) || !preg_match('/^[A-Za-zÀ-ž\s\-]+$/u', $name)) {
        $errors[] = "Neplatné křestní jméno.";
    }
    if (empty($surname) || !preg_match('/^[A-Za-zÀ-ž\s\-]+$/u', $surname)) {
        $errors[] = "Neplatné příjmení.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Neplatná e-mailová adresa.";
    }
    if (empty($phone) || !preg_match('/^[0-9+\s]{9,15}$/', $phone)) {
        $errors[] = "Neplatné telefonní číslo.";
    }
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $errors[] = "Neplatná volba pohlaví.";
    }
    if (empty($login) || !preg_match('/^[a-zA-Z0-9_]{4,20}$/', $login)) {
        $errors[] = "Login musí mít 4-20 znaků a obsahovat pouze písmena, číslice a podtržítka.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Heslo musí mít alespoň 8 znaků.";
    }

    // Kontrola profilové fotky (formát, velikost max 2MB)
    if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['pfp']['tmp_name'];
        $fileName = $_FILES['pfp']['name'];
        $fileSize = $_FILES['pfp']['size'];
        $fileType = mime_content_type($fileTmpPath);

        // Povolené MIME typy obrázků
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2 MB

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Profilová fotka musí být ve formátu JPEG, PNG nebo WebP.";
        }
        if ($fileSize > $maxSize) {
            $errors[] = "Maximální povolená velikost profilové fotky je 2 MB.";
        }
    } else {
        $errors[] = "Profilová fotka je povinná.";
    }

    // Kontrola duplicity v databázi
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM uzivatele WHERE login = ? OR email = ?");
        $stmt->execute([$login, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Uživatelské jméno nebo e-mail již je obsazen.";
        }
    }

    // Pokud nenastaly žádné chyby, pokračujeme v uložení
    if (empty($errors)) {
        $uploadFileDir = './uploads/pfp/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = md5(time() . $login) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            // Hashování hesla dle bezpečnostních požadavků
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Vložení uživatele do databáze (výchozí role např. 'user')
            $insertStmt = $pdo->prepare("INSERT INTO uzivatele (jmeno, prijmeni, email, telefon, pohlavie, foto_cesta, login, heslo, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
            
            // Poznámka: Sloupec 'pohlavi' odpovídá schématu databáze
            $insertStmt = $pdo->prepare("INSERT INTO uzivatele (jmeno, prijmeni, email, telefon, pohlavi, foto_cesta, login, heslo, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user')");
            
            if ($insertStmt->execute([$name, $surname, $email, $phone, $gender, $dest_path, $login, $hashedPassword])) {
                // Úspěšná registrace, přesměrování na přihlášení
                $_SESSION['success_message'] = "Registrace proběhla úspěšně. Nyní se můžete přihlásit.";
                header("Location: prihlaseni_a_registr.html");
                exit;
            } else {
                $errors[] = "Chyba při ukládání do databáze.";
            }
        } else {
            $errors[] = "Chyba při nahrávání souboru na server.";
        }
    }

    // Pokud nastaly chyby, vypíšeme je (v reálném projektu je vhodné je předat zpět do formuláře)
    if (!empty($errors)) {
        echo "<div style='color: red; font-family: sans-serif; padding: 20px;'>";
        echo "<h3>Při registraci došlo k chybám:</h3><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul><p><a href='javascript:history.back()'>Zpět na formulář</a></p>";
        echo "</div>";
    }
} else {
    header("Location: prihlaseni_a_registr.html");
    exit;
}
?>