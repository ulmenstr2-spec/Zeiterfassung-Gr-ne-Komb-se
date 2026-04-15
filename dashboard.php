<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

// Admin und Buchhaltung: direkt zur Uebersicht
if (in_array(currentRole(), ['admin', 'buchhaltung'], true)) {
    header('Location: ' . BASE_URL . '/admin_uebersicht.php');
    exit;
}

// Mitarbeiter-Dashboard
$pdo    = getPDO();
$userId = currentUserId();
$heute  = date('Y-m-d');
$monat  = date('Y-m');

// Heute Schicht eingetragen?
$stmtHeute = $pdo->prepare('SELECT COUNT(*) FROM shifts WHERE user_id = ? AND datum = ?');
$stmtHeute->execute([$userId, $heute]);
$heuteEingetragen = (int)$stmtHeute->fetchColumn() > 0;

// Gesamtstunden diesen Monat
$stmtMonat = $pdo->prepare(
    'SELECT beginn, ende, pause_minuten FROM shifts
     WHERE user_id = ? AND datum BETWEEN ? AND ?'
);
$stmtMonat->execute([$userId, $monat . '-01', date('Y-m-t')]);
$monatsSchichten = $stmtMonat->fetchAll();

$gesamtStunden = 0.0;
foreach ($monatsSchichten as $s) {
    $gesamtStunden += berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']);
}

// Letzte 5 Schichten
$stmtLetzte = $pdo->prepare(
    'SELECT datum, beginn, ende, pause_minuten, notiz
     FROM shifts WHERE user_id = ?
     ORDER BY datum DESC, beginn DESC
     LIMIT 5'
);
$stmtLetzte->execute([$userId]);
$letzteSchichten = $stmtLetzte->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard &ndash; <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>Hallo, <?= h(currentUserName()) ?>!</h2>

    <div class="dashboard-grid">

        <!-- Heute -->
        <div class="card dashboard-card">
            <h3>Heute</h3>
            <?php if ($heuteEingetragen): ?>
                <p class="dashboard-ok">&#10003; Schicht eingetragen</p>
            <?php else: ?>
                <p class="dashboard-hint">Noch keine Schicht f&#252;r heute eingetragen.</p>
                <a href="<?= h(BASE_URL) ?>/schicht_eintragen.php" class="btn btn-primary">
                    Jetzt eintragen
                </a>
            <?php endif; ?>
        </div>

        <!-- Dieser Monat -->
        <div class="card dashboard-card">
            <h3>Dieser Monat</h3>
            <p class="dashboard-stunden"><?= h(formatStunden($gesamtStunden)) ?></p>
            <p class="dashboard-sub">
                <?= count($monatsSchichten) ?> Schicht<?= count($monatsSchichten) !== 1 ? 'en' : '' ?>
            </p>
        </div>

    </div>

    <!-- Letzte Schichten -->
    <?php if (!empty($letzteSchichten)): ?>
    <h3>Letzte Schichten</h3>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Datum</th><th>Beginn</th><th>Ende</th>
                <th>Pause</th><th>Netto-Std.</th><th>Notiz</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($letzteSchichten as $s): ?>
            <?php $netto = berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']); ?>
            <tr>
                <td><?= h(date('d.m.Y', strtotime($s['datum']))) ?></td>
                <td><?= h(substr($s['beginn'], 0, 5)) ?></td>
                <td><?= h(substr($s['ende'],   0, 5)) ?></td>
                <td><?= h($s['pause_minuten']) ?> min</td>
                <td><?= h(formatStunden($netto)) ?></td>
                <td><?= $s['notiz'] ? h($s['notiz']) : '&ndash;' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p>
        <a href="<?= h(BASE_URL) ?>/meine_schichten.php">Alle Schichten anzeigen &rarr;</a>
    </p>
    <?php endif; ?>
</main>
</body>
</html>
