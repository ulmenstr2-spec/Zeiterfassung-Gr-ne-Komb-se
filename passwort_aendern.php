<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$pdo    = getPDO();
$fehler = [];
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler[] = 'Ungueltige Anfrage.';
    } else {
        $altPw  = $_POST['altes_passwort']  ?? '';
        $neuPw  = $_POST['neues_passwort']  ?? '';
        $neuPw2 = $_POST['neues_passwort2'] ?? '';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([currentUserId()]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($altPw, $hash)) {
            $fehler[] = 'Das aktuelle Passwort ist falsch.';
        }
        if (strlen($neuPw) < 8) {
            $fehler[] = 'Das neue Passwort muss mindestens 8 Zeichen haben.';
        }
        if ($neuPw !== $neuPw2) {
            $fehler[] = 'Die Passwoerter stimmen nicht ueberein.';
        }

        if (empty($fehler)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($neuPw, PASSWORD_BCRYPT), currentUserId()]);
            header('Location: ' . BASE_URL . '/passwort_aendern.php?erfolg=1');
            exit;
        }
    }
}

if (!empty($_GET['erfolg'])) {
    $erfolg = 'Passwort erfolgreich geaendert.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort &#228;ndern &#8211; <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>Passwort &#228;ndern</h2>

    <?php if ($fehler): ?>
        <div class="alert alert-error">
            <?php foreach ($fehler as $f): ?><p><?= h($f) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($erfolg): ?>
        <div class="alert alert-success"><?= h($erfolg) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="altes_passwort">Aktuelles Passwort</label>
                <input type="password" id="altes_passwort" name="altes_passwort"
                       required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label for="neues_passwort">Neues Passwort</label>
                <input type="password" id="neues_passwort" name="neues_passwort"
                       required minlength="8" autocomplete="new-password"
                       placeholder="mind. 8 Zeichen">
            </div>
            <div class="form-group">
                <label for="neues_passwort2">Neues Passwort wiederholen</label>
                <input type="password" id="neues_passwort2" name="neues_passwort2"
                       required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Passwort speichern</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
