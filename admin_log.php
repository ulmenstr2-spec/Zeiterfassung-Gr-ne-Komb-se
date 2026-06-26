<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pdo = getPDO();

$PRO_SEITE = 25;
$seite     = max(1, (int)($_GET['seite'] ?? 1));

$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterAktion = $_GET['aktion'] ?? '';
$filterMonat  = $_GET['monat'] ?? date('Y-m');

if (!in_array($filterAktion, ['', 'erstellt', 'geaendert', 'geloescht'], true)) {
    $filterAktion = '';
}
if (!preg_match('/^\d{4}-\d{2}$/', $filterMonat)) {
    $filterMonat = date('Y-m');
}

// Datumsbereich fuer Monatsfilter
[$fy, $fm] = explode('-', $filterMonat);
$vonDatum  = "$fy-$fm-01 00:00:00";
$bisDatum  = date('Y-m-t 23:59:59', mktime(0, 0, 0, (int)$fm, 1, (int)$fy));

// WHERE aufbauen
$where  = ['sl.zeitstempel BETWEEN ? AND ?'];
$params = [$vonDatum, $bisDatum];

if ($filterUser > 0) {
    $where[]  = 'sl.geaendert_von = ?';
    $params[] = $filterUser;
}
if ($filterAktion !== '') {
    $where[]  = 'sl.aktion = ?';
    $params[] = $filterAktion;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Gesamtanzahl
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM shift_log sl $whereClause");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$seiten = max(1, (int)ceil($total / $PRO_SEITE));
$seite  = min($seite, $seiten);
$offset = ($seite - 1) * $PRO_SEITE;

// Log-Eintraege laden
$sql = "SELECT sl.id, sl.shift_id, sl.aktion, sl.alte_werte, sl.neue_werte, sl.zeitstempel,
               u.name AS geaendert_von_name
        FROM shift_log sl
        JOIN users u ON u.id = sl.geaendert_von
        $whereClause
        ORDER BY sl.zeitstempel DESC, sl.id DESC
        LIMIT $PRO_SEITE OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Mitarbeiterliste fuer Filter-Dropdown
$mitarbeiter = $pdo->query('SELECT id, name FROM users ORDER BY name')->fetchAll();

// Hilfsfunktion: Aenderungen lesbar darstellen
function formatAenderungen(string $aktion, ?string $alteJson, ?string $neueJson): string
{
    $feldnamen = [
        'datum'          => 'Datum',
        'beginn'         => 'Beginn',
        'ende'           => 'Ende',
        'pause_minuten'  => 'Pause',
        'notiz'          => 'Notiz',
    ];
    if ($aktion === 'erstellt') {
        $neu  = $neueJson ? json_decode($neueJson, true) : [];
        $teile = [];
        foreach ($feldnamen as $k => $label) {
            if (isset($neu[$k]) && $neu[$k] !== '') {
                $teile[] = $label . ': ' . htmlspecialchars((string)$neu[$k], ENT_QUOTES, 'UTF-8');
            }
        }
        return implode(', ', $teile);
    }
    if ($aktion === 'geloescht') {
        $alt  = $alteJson ? json_decode($alteJson, true) : [];
        $teile = [];
        foreach ($feldnamen as $k => $label) {
            if (isset($alt[$k]) && $alt[$k] !== '') {
                $teile[] = $label . ': ' . htmlspecialchars((string)$alt[$k], ENT_QUOTES, 'UTF-8');
            }
        }
        return implode(', ', $teile);
    }
    // geaendert: nur geaenderte Felder zeigen
    $alt  = $alteJson ? json_decode($alteJson, true) : [];
    $neu  = $neueJson ? json_decode($neueJson, true) : [];
    $teile = [];
    foreach ($feldnamen as $k => $label) {
        $altVal = (string)($alt[$k] ?? '');
        $neuVal = (string)($neu[$k] ?? '');
        if ($altVal !== $neuVal) {
            $teile[] = $label . ': '
                . htmlspecialchars($altVal, ENT_QUOTES, 'UTF-8')
                . ' &rarr; '
                . htmlspecialchars($neuVal, ENT_QUOTES, 'UTF-8');
        }
    }
    return $teile ? implode(', ', $teile) : '&ndash;';
}

// Paginierungs-URL bauen
function pagUrl(int $s): string
{
    $p = $_GET;
    $p['seite'] = $s;
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>&#196;nderungslog &ndash; <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>&#196;nderungslog</h2>

    <div class="card">
        <form method="get" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Monat</label>
                    <select name="monat">
                        <?= monatOptionen($filterMonat) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mitarbeiter</label>
                    <select name="user_id">
                        <option value="0">Alle</option>
                        <?php foreach ($mitarbeiter as $ma): ?>
                            <option value="<?= (int)$ma['id'] ?>"
                                <?= ($ma['id'] == $filterUser) ? ' selected' : '' ?>>
                                <?= h($ma['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Aktion</label>
                    <select name="aktion">
                        <option value="">Alle</option>
                        <option value="erstellt"  <?= $filterAktion === 'erstellt'  ? 'selected' : '' ?>>Erstellt</option>
                        <option value="geaendert" <?= $filterAktion === 'geaendert' ? 'selected' : '' ?>>Ge&#228;ndert</option>
                        <option value="geloescht" <?= $filterAktion === 'geloescht' ? 'selected' : '' ?>>Gel&#246;scht</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Filtern</button>
            </div>
        </form>
    </div>

    <p>
        <?= $total ?> Eintr&#228;ge gefunden
        <?php if ($seiten > 1): ?>
            &ndash; Seite <?= $seite ?> von <?= $seiten ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($logs)): ?>
    <div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Zeitstempel</th>
                <th>Ge&#228;ndert von</th>
                <th>Schicht-Datum</th>
                <th>Aktion</th>
                <th>&#196;nderungen</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <?php
            // Schicht-Datum aus neue_werte oder alte_werte ermitteln
            $werte = $log['neue_werte'] ?? $log['alte_werte'] ?? null;
            $schichtDatum = '';
            if ($werte) {
                $arr = json_decode($werte, true);
                if (!empty($arr['datum'])) {
                    $schichtDatum = date('d.m.Y', strtotime($arr['datum']));
                }
            }
            $aktionLabels = ['erstellt' => 'Erstellt', 'geaendert' => 'Ge&auml;ndert', 'geloescht' => 'Gel&ouml;scht'];
            $aktionLabel  = $aktionLabels[$log['aktion']] ?? h($log['aktion']);
            $aktionKlass  = 'aktion-' . $log['aktion'];
            ?>
            <tr>
                <td><?= h(date('d.m.Y H:i', strtotime($log['zeitstempel']))) ?></td>
                <td><?= h($log['geaendert_von_name']) ?></td>
                <td><?= $schichtDatum ? h($schichtDatum) : '&ndash;' ?></td>
                <td><span class="<?= $aktionKlass ?>"><?= $aktionLabel ?></span></td>
                <td><?= formatAenderungen($log['aktion'], $log['alte_werte'], $log['neue_werte']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($seiten > 1): ?>
    <div class="pagination">
        <?php if ($seite > 1): ?>
            <a href="<?= h(pagUrl($seite - 1)) ?>" class="btn btn-secondary">&laquo; Zur&uuml;ck</a>
        <?php endif; ?>
        <?php for ($s = max(1, $seite - 2); $s <= min($seiten, $seite + 2); $s++): ?>
            <?php if ($s === $seite): ?>
                <span class="btn btn-primary"><?= $s ?></span>
            <?php else: ?>
                <a href="<?= h(pagUrl($s)) ?>" class="btn btn-secondary"><?= $s ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($seite < $seiten): ?>
            <a href="<?= h(pagUrl($seite + 1)) ?>" class="btn btn-secondary">Weiter &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <p class="empty-state">Keine Eintr&#228;ge f&#252;r den gew&#228;hlten Zeitraum.</p>
    <?php endif; ?>
</main>
</body>
</html>
