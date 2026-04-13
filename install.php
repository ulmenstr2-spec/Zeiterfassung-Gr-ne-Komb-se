<?php
// ============================================================
// install.php – EINMALIG AUSFÜHREN, DANACH VOM SERVER LÖSCHEN
// ============================================================

require_once __DIR__ . '/config.php';

// Schutz: nach erstem Durchlauf existiert install.lock
if (file_exists(__DIR__ . '/install.lock')) {
    die('<h2 style="font-family:sans-serif;color:#c62828">Installation bereits abgeschlossen.</h2><p style="font-family:sans-serif">Bitte <strong>install.php</strong> von Ihrem Server löschen.</p>');
}

// ================================================================
// Admin-Zugangsdaten – VOR DER INSTALLATION HIER ANPASSEN
// ================================================================
$adminName     = 'Josef';
$adminEmail    = 'josef@gruenekombuese.de';
$adminPasswort = 'BitteSicheresPasswortSetzen123!';
// ================================================================

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Installation – <?= htmlspecialchars(APP_NAME) ?></title>
<style>body{font-family:sans-serif;max-width:600px;margin:3rem auto;padding:0 1rem}h1{color:#2e7d32}.ok{color:#2e7d32}.err{color:#c62828}.warn{background:#fff3cd;border:1px solid #ffc107;padding:1rem;border-radius:5px;margin-top:1rem}</style>
</head><body>
<h1><?= htmlspecialchars(APP_NAME) ?></h1>
<h2>Installation</h2>
<?php

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // --- Tabellen anlegen ---

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role          ENUM('admin','buchhaltung','mitarbeiter') NOT NULL DEFAULT 'mitarbeiter',
        aktiv         TINYINT(1) NOT NULL DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p class="ok">&#10003; Tabelle <strong>users</strong> bereit.</p>';

    $pdo->exec("CREATE TABLE IF NOT EXISTS shifts (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        user_id         INT NOT NULL,
        datum           DATE NOT NULL,
        beginn          TIME NOT NULL,
        ende            TIME NOT NULL,
        pause_minuten   INT NOT NULL DEFAULT 0,
        notiz           VARCHAR(500),
        erstellt_am     DATETIME DEFAULT CURRENT_TIMESTAMP,
        geaendert_am    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        geaendert_von   INT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (geaendert_von) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p class="ok">&#10003; Tabelle <strong>shifts</strong> bereit.</p>';

    // shift_log ohne CASCADE – damit Logs auch nach Schicht-Löschung erhalten bleiben
    $pdo->exec("CREATE TABLE IF NOT EXISTS shift_log (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        shift_id       INT NOT NULL,
        geaendert_von  INT NOT NULL,
        aktion         ENUM('erstellt','geaendert','geloescht') NOT NULL,
        alte_werte     TEXT,
        neue_werte     TEXT,
        zeitstempel    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p class="ok">&#10003; Tabelle <strong>shift_log</strong> bereit.</p>';

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT NOT NULL,
        token_hash  VARCHAR(255) NOT NULL UNIQUE,
        erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo '<p class="ok">&#10003; Tabelle <strong>password_reset_tokens</strong> bereit.</p>';

    // --- Admin anlegen ---
    $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $check->execute([$adminEmail]);
    if ((int)$check->fetchColumn() === 0) {
        $hash = password_hash($adminPasswort, PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
            ->execute([$adminName, $adminEmail, $hash, 'admin']);
        echo '<p class="ok">&#10003; Admin-Account angelegt: <strong>' . htmlspecialchars($adminEmail) . '</strong></p>';
    } else {
        echo '<p class="ok">&#10003; Admin-Account war bereits vorhanden.</p>';
    }

    // --- Lock-Datei anlegen ---
    file_put_contents(__DIR__ . '/install.lock', date('c') . ' – Installation abgeschlossen');

    echo '<hr>';
    echo '<p class="ok"><strong>Installation erfolgreich abgeschlossen!</strong></p>';
    echo '<div class="warn">';
    echo '<strong>&#9888; Wichtig:</strong> Bitte <strong>install.php</strong> jetzt sofort von Ihrem Server löschen!';
    echo '</div>';
    echo '<p style="margin-top:1rem"><a href="' . htmlspecialchars(BASE_URL) . '/index.php">&#8594; Zur Anmeldung</a></p>';

} catch (PDOException $e) {
    echo '<p class="err"><strong>Fehler:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Bitte <code>config.php</code> prüfen.</p>';
}
?>
</body></html>
