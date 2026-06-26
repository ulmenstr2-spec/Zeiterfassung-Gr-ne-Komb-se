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

$meldung = '';
$fehler  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pdo   = getPDO();
        $stmt  = $pdo->prepare('SELECT id, name FROM users WHERE email = ? AND aktiv = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Immer dieselbe Meldung – verhindert User-Enumeration
        $meldung = 'Falls diese E-Mail-Adresse bekannt ist, wurde ein Reset-Link gesendet. Bitte auch den Spam-Ordner prüfen.';

        if ($user) {
            // Alte Token dieses Nutzers löschen
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')
                ->execute([$user['id']]);

            // Neuen Token erstellen (sicher, 64 Hex-Zeichen)
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash) VALUES (?, ?)')
                ->execute([$user['id'], $tokenHash]);

            sendPasswordResetEmail($email, $user['name'], $token);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1 class="login-title"><?= h(APP_NAME) ?></h1>
    <h2 class="login-subtitle">Passwort zurücksetzen</h2>

    <?php if ($fehler): ?>
        <div class="alert alert-error"><?= h($fehler) ?></div>
    <?php endif; ?>

    <?php if ($meldung): ?>
        <div class="alert alert-success"><?= h($meldung) ?></div>
        <p class="login-forgot" style="margin-top:1.5rem">
            <a href="<?= h(BASE_URL) ?>/index.php">← Zurück zur Anmeldung</a>
        </p>
    <?php else: ?>
        <p style="font-size:.9rem;color:#616161;margin-bottom:1.25rem">
            Geben Sie Ihre E-Mail-Adresse ein. Sie erhalten einen Link zum Zurücksetzen Ihres Passworts.
        </p>
        <form method="post" action="">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="ihre@email.de">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset-Link senden</button>
        </form>
        <p class="login-forgot">
            <a href="<?= h(BASE_URL) ?>/index.php">← Zurück zur Anmeldung</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
