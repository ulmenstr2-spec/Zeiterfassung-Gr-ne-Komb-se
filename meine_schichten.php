<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
if (currentRole() === 'buchhaltung') {
    header('Location: ' . BASE_URL . '/admin_uebersicht.php');
    exit;
}

$pdo    = getPDO();
$userId = currentUserId();
$role   = currentRole();

// Admin kann Schichten anderer Mitarbeiter ansehen
$viewUserId   = $userId;
$viewUserName = currentUserName();
if ($role === 'admin' && !empty($_GET['user_id'])) {
    $viewUserId = (int)$_GET['user_id'];
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$viewUserId]);
    $row = $stmt->fetch();
    $viewUserName = $row ? $row['name'] : 'Unbekannt';
}

// Löschen (nur Admin) – vor HTML-Output
if ($role === 'admin'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_shift_id'])
) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $deleteError = 'Ungültige Anfrage.';
    } else {
        $delId = (int)$_POST['delete_shift_id'];
        $stmtD = $pdo->prepare('SELECT * FROM shifts WHERE id = ?');
        $stmtD->execute([$delId]);
        $delShift = $stmtD->fetch();
        if ($delShift) {
            logShiftChange($pdo, $delId, $userId, 'geloescht', [
                'user_id'       => $delShift['user_id'],
                'datum'         => $delShift['datum'],
                'beginn'        => $delShift['beginn'],
                'ende'          => $delShift['ende'],
                'pause_minuten' => $delShift['pause_minuten'],
                'notiz'         => $delShift['notiz'],
            ], null);
            $pdo->prepare('DELETE FROM shifts WHERE id = ?')->execute([$delId]);
        }
        $qs = 'monat=' . urlencode($_POST['monat'] ?? date('Y-m'));
        if ($viewUserId !== $userId) {
            $qs .= '&user_id=' . $viewUserId;
        }
        header('Location: ' . BASE_URL . '/meine_schichten.php?' . $qs);
        exit;
    }
}

// Monatsfilter (Admin) / 60-Tage-Fenster (Mitarbeiter)
$monat = $_GET['monat'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
    $monat = date('Y-m');
}

if ($role === 'mitarbeiter') {
    $von  = date('Y-m-d', strtotime('-60 days'));
    $bis  = date('Y-m-d');
} else {
    [$y, $m] = explode('-', $monat);
    $von = "$y-$m-01";
    $bis = date('Y-m-t', mktime(0, 0, 0, (int)$m, 1, (int)$y));
}

$stmt = $pdo->prepare(
    'SELECT * FROM shifts WHERE user_id = ? AND datum BETWEEN ? AND ? ORDER BY datum DESC, beginn ASC'
);
$stmt->execute([$viewUserId, $von, $bis]);
$shifts = $stmt->fetchAll();

// Summe pro Monat
$monatsStunden = [];
foreach ($shifts as $s) {
    $mon = substr($s['datum'], 0, 7);
    $monatsStunden[$mon] = ($monatsStunden[$mon] ?? 0)
        + berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']);
}

$deutschen = [
    'January'=>'Januar','February'=>'Februar','March'=>'März','April'=>'April',
    'May'=>'Mai','June'=>'Juni','July'=>'Juli','August'=>'August',
    'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Dezember',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schichten – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>
        <?= ($role === 'admin' && $viewUserId !== $userId)
            ? 'Schichten von ' . h($viewUserName)
            : 'Meine Schichten' ?>
    </h2>

    <?php if (isset($deleteError)): ?>
        <div class="alert alert-error"><?= h($deleteError) ?></div>
    <?php endif; ?>

    <?php if ($role !== 'mitarbeiter'): ?>
    <form method="get" class="filter-form">
        <?php if ($viewUserId !== $userId): ?>
            <input type="hidden" name="user_id" value="<?= h($viewUserId) ?>">
        <?php endif; ?>
        <label>Monat:
            <select name="monat" onchange="this.form.submit()">
                <?= monatOptionen($monat) ?>
            </select>
        </label>
    </form>
    <?php else: ?>
    <p class="info-text">Angezeigt: letzte 60 Tage</p>
    <?php endif; ?>

    <?php if (!empty($monatsStunden)): ?>
    <div class="monatssummen">
        <?php foreach ($monatsStunden as $mon => $std): ?>
            <span class="badge">
                <?= h(strtr(date('F Y', strtotime($mon . '-01')), $deutschen)) ?>:
                <strong><?= h(formatStunden($std)) ?></strong>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($shifts)): ?>
        <p class="empty-state">Keine Schichten im gewählten Zeitraum.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th><th>Beginn</th><th>Ende</th>
                <th>Pause</th><th>Netto</th><th>Notiz</th><th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shifts as $s):
            $kannEdit = ($role === 'admin')
                || ((new DateTime())->diff(new DateTime($s['erstellt_am']))->days <= 7);
        ?>
            <tr>
                <td><?= h(date('d.m.Y', strtotime($s['datum']))) ?></td>
                <td><?= h(substr($s['beginn'], 0, 5)) ?></td>
                <td><?= h(substr($s['ende'],   0, 5)) ?></td>
                <td><?= h($s['pause_minuten']) ?> min</td>
                <td><?= h(formatStunden(berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']))) ?></td>
                <td><?= h($s['notiz'] ?? '–') ?></td>
                <td class="actions">
                    <?php if ($kannEdit): ?>
                        <a href="<?= h(BASE_URL) ?>/schicht_eintragen.php?edit=<?= (int)$s['id'] ?>"
                           class="btn btn-sm btn-secondary">Bearbeiten</a>
                    <?php endif; ?>
                    <?php if ($role === 'admin'): ?>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Schicht vom <?= h(date('d.m.Y', strtotime($s['datum']))) ?> wirklich löschen?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_shift_id" value="<?= (int)$s['id'] ?>">
                            <input type="hidden" name="monat" value="<?= h($monat) ?>">
                            <?php if ($viewUserId !== $userId): ?>
                                <input type="hidden" name="user_id" value="<?= (int)$viewUserId ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-danger">Löschen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div class="page-actions">
        <a href="<?= h(BASE_URL) ?>/schicht_eintragen.php" class="btn btn-primary">+ Schicht eintragen</a>
        <?php if ($role === 'admin'): ?>
            <a href="<?= h(BASE_URL) ?>/admin_uebersicht.php" class="btn btn-secondary">← Zurück zur Übersicht</a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
