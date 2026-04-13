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

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler = 'Ungueltige Anfrage. Bitte Seite neu laden.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $passwort = $_POST['passwort'] ?? '';

        $stmt = getPDO()->prepare(
            'SELECT id, name, password_hash, role, aktiv FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['aktiv'] && password_verify($passwort, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $fehler = 'E-Mail oder Passwort falsch, oder Account deaktiviert.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmelden &ndash; <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1 class="login-title"><?= h(APP_NAME) ?></h1>
    <?php if ($fehler): ?>
        <div class="alert alert-error"><?= h($fehler) ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="email">E-Mail</label>
            <input type="email" id="email" name="email" required autofocus
                   value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="passwort">Passwort</label>
            <input type="password" id="passwort" name="passwort" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
    </form>
    <p class="login-forgot">
        <a href="<?= h(BASE_URL) ?>/passwort_vergessen.php">Passwort vergessen?</a>
    </p>
</div>
</body>
</html>
