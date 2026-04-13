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

// Rate Limiting: max. 10 Fehlversuche, 15 Min. Sperre
// Hinweis: Vergleich mit < statt > (IONOS-Editor-Kompatibilitaet)
$maxVersuche  = 10;
$sperrzeitSek = 900;
if (empty($_SESSION['login_versuche']))     { $_SESSION['login_versuche']     = 0; }
if (empty($_SESSION['login_gesperrt_bis'])) { $_SESSION['login_gesperrt_bis'] = 0; }
$jetzt    = time();
$gesperrt = ($jetzt < $_SESSION['login_gesperrt_bis']);

$fehler = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($gesperrt) {
        $rest   = (int)ceil(($_SESSION['login_gesperrt_bis'] - $jetzt) / 60);
        $fehler = 'Zu viele Fehlversuche. Bitte warte noch ' . $rest . ' Minute(n).';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
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
            $_SESSION['login_versuche']     = 0;
            $_SESSION['login_gesperrt_bis'] = 0;
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role']      = $user['role'];
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $_SESSION['login_versuche']++;
            if ($_SESSION['login_versuche'] >= $maxVersuche) {
                $_SESSION['login_gesperrt_bis'] = time() + $sperrzeitSek;
                $fehler = 'Zu viele Fehlversuche. Anmeldung fuer 15 Minuten gesperrt.';
            } else {
                $fehler = 'E-Mail oder Passwort falsch, oder Account deaktiviert.';
            }
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
        <button type="submit" class="btn btn-primary btn-block"
                <?php if ($gesperrt): ?>disabled<?php endif; ?>>Anmelden</button>
    </form>
    <p class="login-forgot">
        <a href="<?= h(BASE_URL) ?>/passwort_vergessen.php">Passwort vergessen?</a>
    </p>
</div>
</body>
</html>
