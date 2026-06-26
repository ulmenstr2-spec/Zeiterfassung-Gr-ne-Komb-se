<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin', 'buchhaltung');

$pdo = getPDO();

// Modus: 'lohn' (Standard, fuer die Gehaltsabrechnung) oder 'detail' (Kontrolle)
$modus = (($_GET['modus'] ?? 'lohn') === 'detail') ? 'detail' : 'lohn';

$monat = $_GET['monat'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monat)) {
    $monat = date('Y-m');
}
$userId_filter = (int)($_GET['user_id'] ?? 0);

[$y, $m] = explode('-', $monat);
if ($modus === 'lohn') {
    // Abrechnungsmonat = Auszahlungsmonat. Zeitraum: 21. Vormonat bis 20. des gewaehlten Monats.
    $von = date('Y-m-d', mktime(0, 0, 0, (int)$m - 1, 21, (int)$y));
    $bis = "$y-$m-20";
} else {
    // Kalendermonat
    $von = "$y-$m-01";
    $bis = date('Y-m-t', mktime(0, 0, 0, (int)$m, 1, (int)$y));
}

$vonLabel = date('d.m.Y', strtotime($von));
$bisLabel = date('d.m.Y', strtotime($bis));

// Schichten im Zeitraum laden
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

    if ($modus === 'lohn') {
        header('Content-Disposition: attachment; filename="Lohnabrechnung_' . $monat . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Mitarbeiter', 'Zeitraum von', 'Zeitraum bis', 'Gesamtstunden'], ';');

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

    // Detailexport (Kontrolle)
    if ($userId_filter > 0) {
        $nameStmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $nameStmt->execute([$userId_filter]);
        $maName   = (string)$nameStmt->fetchColumn();
        $namePart = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(
            ["\xc3\xa4","\xc3\xb6","\xc3\xbc","\xc3\x84","\xc3\x96","\xc3\x9c","\xc3\x9f",' '],
            ['ae',       'oe',      'ue',      'ae',      'oe',      'ue',      'ss',     '_'],
            $maName
        )));
        $filename = 'Einzelschichten_' . $namePart . '_' . $monat . '.csv';
    } else {
        $filename = 'Einzelschichten_' . $monat . '.csv';
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

    <!-- ============ LOHNABRECHNUNG (Standard) ============ -->
    <div class="card export-lohn">
        <h3>&#128181; Lohnabrechnung f&#252;r die Gehaltsabrechnung</h3>
        <p>Gesamtstunden pro Mitarbeiter im Lohnzeitraum (21. bis 20.). Das ist die Datei f&#252;r die Lohnabrechnung.</p>
        <form method="get" action="">
            <input type="hidden" name="modus" value="lohn">
            <div class="form-row">
                <div class="form-group">
                    <label>Abrechnungsmonat (= Auszahlungsmonat)</label>
                    <select name="monat" onchange="this.form.submit()">
                        <?= monatOptionen($monat) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mitarbeiter (optional)</label>
                    <select name="user_id" onchange="this.form.submit()">
                        <option value="0">Alle Mitarbeiter</option>
                        <?php foreach ($mitarbeiter as $ma): ?>
                            <option value="<?= (int)$ma['id'] ?>"
                                <?= ($ma['id'] == $userId_filter && $modus === 'lohn') ? ' selected' : '' ?>>
                                <?= h($ma['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p class="export-zeitraum">
                Zeitraum: <strong><?= h($vonLabel) ?> &#8211; <?= h($bisLabel) ?></strong>
            </p>
            <div class="form-actions">
                <button type="submit" name="download" value="1" class="btn btn-primary btn-block">
                    &#8595; Lohnabrechnung herunterladen (<?= h($vonLabel) ?> &#8211; <?= h($bisLabel) ?>)
                </button>
            </div>
        </form>
    </div>

    <?php if ($modus === 'lohn'): ?>
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
    <?php endif; ?>

    <!-- ============ DETAILEXPORT (nur Kontrolle) ============ -->
    <div class="card export-detail">
        <h3>Detailexport (nur zur Kontrolle)</h3>
        <p>Alle Einzelschichten eines Kalendermonats (1. bis Monatsende).
           <strong>Nicht f&#252;r die Lohnabrechnung verwenden</strong> &#8211; der Lohnzeitraum (21.&#8211;20.) fehlt hier.</p>
        <form method="get" action="">
            <input type="hidden" name="modus" value="detail">
            <div class="form-row">
                <div class="form-group">
                    <label>Kalendermonat</label>
                    <select name="monat" onchange="this.form.submit()">
                        <?= monatOptionen($monat) ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mitarbeiter (optional)</label>
                    <select name="user_id" onchange="this.form.submit()">
                        <option value="0">Alle Mitarbeiter</option>
                        <?php foreach ($mitarbeiter as $ma): ?>
                            <option value="<?= (int)$ma['id'] ?>"
                                <?= ($ma['id'] == $userId_filter && $modus === 'detail') ? ' selected' : '' ?>>
                                <?= h($ma['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="download" value="1" class="btn btn-secondary">
                    &#8595; Einzelschichten herunterladen
                </button>
            </div>
        </form>
    </div>

    <?php if ($modus === 'detail'): ?>
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
