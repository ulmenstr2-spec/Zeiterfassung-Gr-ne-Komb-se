<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pdo    = getPDO();
$fehler = [];
$erfolg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler[] = 'Ungültige Anfrage.';
    } else {
        $aktion = $_POST['aktion'] ?? '';

        if ($aktion === 'anlegen') {
            $name     = trim($_POST['name']     ?? '');
            $email    = trim($_POST['email']    ?? '');
            $passwort = $_POST['passwort']       ?? '';
            $role     = $_POST['role']           ?? 'mitarbeiter';

            if (mb_strlen($name) < 2)                          $fehler[] = 'Name zu kurz (mind. 2 Zeichen).';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $fehler[] = 'Ungültige E-Mail-Adresse.';
            if (strlen($passwort) < 8)                         $fehler[] = 'Passwort muss mindestens 8 Zeichen haben.';
            if (!in_array($role, ['admin','buchhaltung','mitarbeiter'], true))
                                                               $fehler[] = 'Ungültige Rolle.';
            if (empty($fehler)) {
                try {
                    $pdo->prepare(
                        'INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)'
                    )->execute([$name, $email, password_hash($passwort, PASSWORD_BCRYPT), $role]);
                    $erfolg = "Mitarbeiter „{$name}" wurde angelegt.";
                } catch (PDOException $e) {
                    $fehler[] = 'Diese E-Mail-Adresse ist bereits vergeben.';
                }
            }

        } elseif ($aktion === 'deaktivieren') {
            $id = (int)$_POST['user_id'];
            if ($id === currentUserId()) {
                $fehler[] = 'Sie können sich nicht selbst deaktivieren.';
            } else {
                $pdo->prepare('UPDATE users SET aktiv = 0 WHERE id = ?')->execute([$id]);
                $erfolg = 'Mitarbeiter wurde deaktiviert.';
            }

        } elseif ($aktion === 'aktivieren') {
            $id = (int)$_POST['user_id'];
            $pdo->prepare('UPDATE users SET aktiv = 1 WHERE id = ?')->execute([$id]);
            $erfolg = 'Mitarbeiter wurde reaktiviert.';

        } elseif ($aktion === 'passwort_reset') {
            $id    = (int)$_POST['user_id'];
            $neuPw = $_POST['neues_passwort'] ?? '';
            if (strlen($neuPw) < 8) {
                $fehler[] = 'Neues Passwort muss mindestens 8 Zeichen haben.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($neuPw, PASSWORD_BCRYPT), $id]);
                $erfolg = 'Passwort wurde zurueckgesetzt.';
            }

        } elseif ($aktion === 'email_aendern') {
            $id       = (int)$_POST['user_id'];
            $neuEmail = trim($_POST['neue_email'] ?? '');
            if (!filter_var($neuEmail, FILTER_VALIDATE_EMAIL)) {
                $fehler[] = 'Ungueltige E-Mail-Adresse.';
            } else {
                try {
                    $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
                        ->execute([$neuEmail, $id]);
                    $erfolg = 'E-Mail-Adresse wurde aktualisiert.';
                } catch (PDOException $e) {
                    $fehler[] = 'Diese E-Mail-Adresse ist bereits vergeben.';
                }
            }
        }
    }
}

$alle = $pdo->query(
    'SELECT id, name, email, role, aktiv, created_at FROM users ORDER BY aktiv DESC, name ASC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitarbeiterverwaltung – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>Mitarbeiterverwaltung</h2>

    <?php if ($fehler): ?>
        <div class="alert alert-error">
            <?php foreach ($fehler as $f): ?><p><?= h($f) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($erfolg): ?>
        <div class="alert alert-success"><?= h($erfolg) ?></div>
    <?php endif; ?>

    <!-- Neuen Mitarbeiter anlegen -->
    <div class="card">
        <h3>Neuen Mitarbeiter anlegen</h3>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="aktion" value="anlegen">
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required maxlength="100"
                           placeholder="Vor- und Nachname">
                </div>
                <div class="form-group">
                    <label>E-Mail</label>
                    <input type="email" name="email" required maxlength="150"
                           placeholder="email@beispiel.de">
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="passwort" required minlength="8"
                           placeholder="mind. 8 Zeichen">
                </div>
                <div class="form-group">
                    <label>Rolle</label>
                    <select name="role">
                        <option value="mitarbeiter">Mitarbeiter</option>
                        <option value="buchhaltung">Buchhaltung</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Anlegen</button>
        </form>
    </div>

    <!-- Mitarbeiterliste -->
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Rolle</th>
                <th>Status</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alle as $ma): ?>
            <tr class="<?= $ma['aktiv'] ? '' : 'row-inactive' ?>">
                <td><?= h($ma['name']) ?></td>
                <td><?= h($ma['email']) ?></td>
                <td><?= h($ma['role']) ?></td>
                <td>
                    <?php if ($ma['aktiv']): ?>
                        <span class="badge badge-green">Aktiv</span>
                    <?php else: ?>
                        <span class="badge badge-grey">Inaktiv</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($ma['aktiv']): ?>
                        <!-- E-Mail aendern -->
                        <form method="post" action="" style="display:inline"
                              onsubmit="return confirm('E-Mail-Adresse fuer <?= h(addslashes($ma['name'])) ?> aendern?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="aktion" value="email_aendern">
                            <input type="hidden" name="user_id" value="<?= (int)$ma['id'] ?>">
                            <input type="email" name="neue_email"
                                   placeholder="Neue E-Mail" maxlength="150"
                                   class="input-inline">
                            <button type="submit" class="btn btn-sm btn-secondary">E-Mail</button>
                        </form>
                        <!-- Passwort zuruecksetzen -->
                        <form method="post" action="" style="display:inline"
                              onsubmit="return pwReset(this, '<?= h(addslashes($ma['name'])) ?>')">
                            <?= csrfField() ?>
                            <input type="hidden" name="aktion" value="passwort_reset">
                            <input type="hidden" name="user_id" value="<?= (int)$ma['id'] ?>">
                            <input type="password" name="neues_passwort"
                                   placeholder="Neues Passwort" minlength="8"
                                   class="input-inline">
                            <button type="submit" class="btn btn-sm btn-secondary">PW setzen</button>
                        </form>
                        <!-- Deaktivieren (nicht sich selbst) -->
                        <?php if ((int)$ma['id'] !== currentUserId()): ?>
                        <form method="post" action="" style="display:inline"
                              onsubmit="return confirm('<?= h(addslashes($ma['name'])) ?> wirklich deaktivieren?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="aktion" value="deaktivieren">
                            <input type="hidden" name="user_id" value="<?= (int)$ma['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Deaktivieren</button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" action="" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="aktion" value="aktivieren">
                            <input type="hidden" name="user_id" value="<?= (int)$ma['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Reaktivieren</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</main>
<script src="<?= h(BASE_URL) ?>/assets/main.js"></script>
</body>
</html>
