<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pdo          = getPDO();
$tokenRaw     = $_GET['token'] ?? '';
$fehler       = '';
$erfolg       = '';
$tokenGueltig = false;
$userId       = null;

if ($tokenRaw) {
    $tokenHash = hash('sha256', $tokenRaw);
    $stmt = $pdo->prepare(
        'SELECT user_id, erstellt_am FROM password_reset_tokens WHERE token_hash = ?'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if ($row) {
        // 1 Stunde Gültigkeit
        $expiry = (new DateTime($row['erstellt_am']))->modify('+1 hour');
        if (new DateTime() < $expiry) {
            $tokenGueltig = true;
            $userId       = (int)$row['user_id'];
        } else {
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')
                ->execute([$tokenHash]);
            $fehler = 'Dieser Link ist abgelaufen (gültig für 1 Stunde). Bitte fordern Sie einen neuen an.';
        }
    } else {
        $fehler = 'Ungültiger oder bereits verwendeter Link.';
    }
} else {
    $fehler = 'Kein Token angegeben.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenGueltig) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültige Anfrage.';
    } else {
        $pw1 = $_POST['passwort']  ?? '';
        $pw2 = $_POST['passwort2'] ?? '';

        if (strlen($pw1) < 8) {
            $fehler = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($pw1 !== $pw2) {
            $fehler = 'Die Passwörter stimmen nicht überein.';
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pw1, PASSWORD_BCRYPT), $userId]);
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')
                ->execute([hash('sha256', $tokenRaw)]);
            $erfolg       = 'Passwort erfolgreich geändert. Sie können sich jetzt anmelden.';
            $tokenGueltig = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Passwort – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1 class="login-title"><?= h(APP_NAME) ?></h1>
    <h2 class="login-subtitle">Neues Passwort vergeben</h2>

    <?php if ($erfolg): ?>
        <div class="alert alert-success"><?= h($erfolg) ?></div>
        <a href="<?= h(BASE_URL) ?>/index.php" class="btn btn-primary btn-block" style="margin-top:.5rem">
            Zur Anmeldung
        </a>

    <?php elseif ($fehler && !$tokenGueltig): ?>
        <div class="alert alert-error"><?= h($fehler) ?></div>
        <p class="login-forgot">
            <a href="<?= h(BASE_URL) ?>/passwort_vergessen.php">Neuen Reset-Link anfordern</a>
        </p>

    <?php elseif ($tokenGueltig): ?>
        <?php if ($fehler): ?>
            <div class="alert alert-error"><?= h($fehler) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= h(BASE_URL) ?>/passwort_reset.php?token=<?= urlencode($tokenRaw) ?>">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="passwort">Neues Passwort</label>
                <input type="password" id="passwort" name="passwort"
                       required minlength="8" autofocus placeholder="mind. 8 Zeichen">
            </div>
            <div class="form-group">
                <label for="passwort2">Passwort wiederholen</label>
                <input type="password" id="passwort2" name="passwort2"
                       required minlength="8" placeholder="mind. 8 Zeichen">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Passwort speichern</button>
        </form>
    <?php endif; ?>

    <p class="login-forgot"><a href="<?= h(BASE_URL) ?>/index.php">← Zurück zur Anmeldung</a></p>
</div>
</body>
</html>
