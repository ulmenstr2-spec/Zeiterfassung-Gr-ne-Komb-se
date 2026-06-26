<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin', 'buchhaltung');

$pdo   = getPDO();
$monat = $_GET['monat'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
    $monat = date('Y-m');
}
[$y, $m] = explode('-', $monat);
$von = "$y-$m-01";
$bis = date('Y-m-t', mktime(0, 0, 0, (int)$m, 1, (int)$y));

// Alle aktiven Mitarbeiter mit Gesamtstunden im gewählten Monat
$stmt = $pdo->prepare("
    SELECT
        u.id, u.name, u.role,
        COUNT(s.id) AS schichtanzahl,
        COALESCE(SUM(
            (TIME_TO_SEC(s.ende) - TIME_TO_SEC(s.beginn)) / 3600.0
            - s.pause_minuten / 60.0
        ), 0) AS gesamtstunden
    FROM users u
    LEFT JOIN shifts s ON s.user_id = u.id AND s.datum BETWEEN ? AND ?
    WHERE u.aktiv = 1
    GROUP BY u.id, u.name, u.role
    ORDER BY u.name
");
$stmt->execute([$von, $bis]);
$mitarbeiter    = $stmt->fetchAll();
$gesamtStunden  = array_sum(array_column($mitarbeiter, 'gesamtstunden'));

$deutschen = [
    'January'=>'Januar','February'=>'Februar','March'=>'März','April'=>'April',
    'May'=>'Mai','June'=>'Juni','July'=>'Juli','August'=>'August',
    'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Dezember',
];
$monatLabel = strtr(date('F Y', strtotime($monat . '-01')), $deutschen);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monatsübersicht – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>Monatsübersicht</h2>

    <form method="get" class="filter-form">
        <label>Monat:
            <select name="monat" onchange="this.form.submit()">
                <?= monatOptionen($monat) ?>
            </select>
        </label>
    </form>

    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th>Rolle</th>
                <th>Schichten</th>
                <th>Gesamtstunden</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mitarbeiter as $ma): ?>
            <tr>
                <td><?= h($ma['name']) ?></td>
                <td><?= h($ma['role']) ?></td>
                <td><?= (int)$ma['schichtanzahl'] ?></td>
                <td><strong><?= h(formatStunden((float)$ma['gesamtstunden'])) ?></strong></td>
                <td>
                    <a href="<?= h(BASE_URL) ?>/meine_schichten.php?user_id=<?= (int)$ma['id'] ?>&amp;monat=<?= h($monat) ?>"
                       class="btn btn-sm btn-secondary">Details</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Gesamt <?= h($monatLabel) ?></strong></td>
                <td colspan="2"><strong><?= h(formatStunden($gesamtStunden)) ?></strong></td>
            </tr>
        </tfoot>
    </table>
    </div>
</main>
</body>
</html>
