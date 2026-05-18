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
$userId_filter = (int)($_GET['user_id'] ?? 0);
$lohn = isset($_GET['lohn']);

[$y, $m] = explode('-', $monat);
if ($lohn) {
    $von = "$y-$m-21";
    $bis = date('Y-m-d', mktime(0, 0, 0, (int)$m + 1, 20, (int)$y));
} else {
    $von = "$y-$m-01";
    $bis = date('Y-m-t', mktime(0, 0, 0, (int)$m, 1, (int)$y));
}

// Alle Schichten im Zeitraum laden (fuer beide Modi verwendet)
$sql = 'SELECT u.name AS mitarbeiter, s.datum, s.beginn, s.ende, s.pause_minuten, s.notiz
        FROM shifts s JOIN users u ON u.id = s.user_id
        WHERE s.datum BETWEEN ? AND ?';
$params = [$von, $bis];
if ($userId_filter > 0) {
    $sql    .= ' AND s.user_id = ?';
    $params[] = $userId_filter;
}
$sql .= ' ORDER BY u.name ASC, s.datum ASC, s.beginn ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Nach Mitarbeiter gruppieren (fuer Lohnansicht)
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['mitarbeiter']][] = $r;
}

// CSV-Download
if (isset($_GET['download'])) {
    header('Cache-Control: no-store');
    header('Content-Type: text/csv; charset=UTF-8');

    if ($lohn) {
        header('Content-Disposition: attachment; filename="lohn_alle_' . $monat . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Mitarbeiter', 'Zeitraum von', 'Zeitraum bis', 'Gesamtstunden'], ';');

        $vonLabel = date('d.m.Y', strtotime($von));
        $bisLabel = date('d.m.Y', strtotime($bis));
        foreach ($grouped as $name => $schichten) {
            $stunden = 0.0;
            foreach ($schichten as $s) {
                $stunden += berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']);
            }
            fputcsv($out, [
                $name,
                $vonLabel,
                $bisLabel,
                number_format($stunden, 2, ',', '.'),
            ], ';');
        }
        fclose($out);
        exit;
    }

    // Detailexport
    if ($userId_filter > 0) {
        $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $nameStmt->execute([$userId_filter]);
        $maName   = (string)$nameStmt->fetchColumn();
        $namePart = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(
            ["\xc3\xa4","\xc3\xb6","\xc3\xbc","\xc3\x84","\xc3\x96","\xc3\x9c","\xc3\x9f",' '],
            ['ae',       'oe',      'ue',      'ae',      'oe',      'ue',      'ss',     '_'],
            $maName
        )));
        $filename = 'zeiterfassung_' . $namePart . '_' . $monat . '.csv';
    } else {
        $filename = 'zeiterfassung_' . $monat . '.csv';
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Mitarbeiter', 'Datum', 'Beginn', 'Ende', 'Pause (Min)', 'Netto-Stunden', 'Notiz'], ';');

    $gesamt = 0.0;
    foreach ($rows as $r) {
        $netto   = berechneNettoStunden($r['beginn'], $r['ende'], (int)$r['pause_minuten']);
        $gesamt += $netto;
        fputcsv($out, [
            $r['mitarbeiter'],
            date('d.m.Y', strtotime($r['datum'])),
            substr($r['beginn'], 0, 5),
            substr($r['ende'],   0, 5),
            $r['pause_minuten'],
            number_format($netto, 2, ',', '.'),
            $r['notiz'] ?? '',
        ], ';');
    }
    fputcsv($out, ['Gesamt', '', '', '', '', number_format($gesamt, 2, ',', '.'), ''], ';');
    fclose($out);
    exit;
}

$mitarbeiter = $pdo->query('SELECT id, name FROM users WHERE aktiv = 1 ORDER BY name')->fetchAll();
$periodeLabel = date('d.m.Y', strtotime($von)) . ' bis ' . date('d.m.Y', strtotime($bis));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV-Export &#8211; <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2>CSV-Export</h2>

    <div class="card">
        <form method="get" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Monat</label>
                    <select name="monat">
                        <?= monatOptionen($monat) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mitarbeiter</label>
                    <select name="user_id">
                        <option value="0">Alle Mitarbeiter</option>
                        <?php foreach ($mitarbeiter as $ma): ?>
                            <option value="<?= (int)$ma['id'] ?>"
                                <?= ($ma['id'] == $userId_filter) ? ' selected' : '' ?>>
                                <?= h($ma['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="lohn" value="1" <?= $lohn ? 'checked' : '' ?>>
                    Lohnabrechnung (21.&#8211;20.) &#8211; Gesamtstunden pro Mitarbeiter
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-secondary">Vorschau</button>
                <button type="submit" name="download" value="1" class="btn btn-primary">
                    &#8595; CSV herunterladen
                </button>
            </div>
        </form>
    </div>

    <p><strong>Zeitraum: <?= h($periodeLabel) ?></strong></p>

    <?php if ($lohn): ?>

        <?php if (!empty($grouped)): ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th>
                    <th>Gesamtstunden</th>
                    <th>Schichten</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $gesamtAlle = 0.0;
            foreach ($grouped as $name => $schichten):
                $stunden = 0.0;
                foreach ($schichten as $s) {
                    $stunden += berechneNettoStunden($s['beginn'], $s['ende'], (int)$s['pause_minuten']);
                }
                $gesamtAlle += $stunden;
            ?>
                <tr>
                    <td><?= h($name) ?></td>
                    <td><strong><?= h(formatStunden($stunden)) ?></strong></td>
                    <td><?= count($schichten) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Gesamt alle</strong></td>
                    <td><strong><?= h(formatStunden($gesamtAlle)) ?></strong></td>
                    <td><?= count($rows) ?> Schichten</td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php else: ?>
            <p class="empty-state">Keine Daten f&#252;r den gew&#228;hlten Zeitraum.</p>
        <?php endif; ?>

    <?php else: ?>

        <?php if (!empty($rows)): ?>
        <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mitarbeiter</th><th>Datum</th><th>Beginn</th><th>Ende</th>
                    <th>Pause</th><th>Netto-Std.</th><th>Notiz</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $gesamtNetto = 0.0;
            foreach ($rows as $r):
                $netto = berechneNettoStunden($r['beginn'], $r['ende'], (int)$r['pause_minuten']);
                $gesamtNetto += $netto;
            ?>
                <tr>
                    <td><?= h($r['mitarbeiter']) ?></td>
                    <td><?= h(date('d.m.Y', strtotime($r['datum']))) ?></td>
                    <td><?= h(substr($r['beginn'], 0, 5)) ?></td>
                    <td><?= h(substr($r['ende'],   0, 5)) ?></td>
                    <td><?= h($r['pause_minuten']) ?> min</td>
                    <td><?= h(formatStunden($netto)) ?></td>
                    <td><?= $r['notiz'] ? h($r['notiz']) : '&ndash;' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5"><strong>Gesamt</strong></td>
                    <td colspan="2"><strong><?= h(formatStunden($gesamtNetto)) ?></strong></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php else: ?>
            <p class="empty-state">Keine Daten f&#252;r den gew&#228;hlten Zeitraum.</p>
        <?php endif; ?>

    <?php endif; ?>
</main>
</body>
</html>
