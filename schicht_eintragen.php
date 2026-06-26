<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
// Buchhaltung darf keine Schichten eintragen
if (currentRole() === 'buchhaltung') {
    header('Location: ' . BASE_URL . '/admin_uebersicht.php');
    exit;
}

$pdo    = getPDO();
$userId = currentUserId();
$role   = currentRole();
$heute  = date('Y-m-d');
$vor60  = date('Y-m-d', strtotime('-60 days'));

// Edit-Modus?
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$shift  = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM shifts WHERE id = ?');
    $stmt->execute([$editId]);
    $shift = $stmt->fetch();
    if (!$shift) {
        die('Schicht nicht gefunden.');
    }
    // Mitarbeiter darf nur eigene Schichten innerhalb 7 Tage bearbeiten
    if ($role === 'mitarbeiter') {
        if ((int)$shift['user_id'] !== $userId) {
            die('Zugriff verweigert.');
        }
        $diff = (new DateTime())->diff(new DateTime($shift['erstellt_am']))->days;
        if ($diff > 7) {
            die('Diese Schicht kann nicht mehr bearbeitet werden (Frist: 7 Tage).');
        }
    }
}

$fehler      = [];
$erfolg      = '';
$zielUserId  = ($editId && $shift) ? (int)$shift['user_id'] : $userId;

// Formularwerte initialisieren
$fDatum  = ($editId && $shift) ? $shift['datum']         : $heute;
$fBeginn = ($editId && $shift) ? substr($shift['beginn'], 0, 5) : '';
$fEnde   = ($editId && $shift) ? substr($shift['ende'],   0, 5) : '';
$fPause  = ($editId && $shift) ? (int)$shift['pause_minuten']   : 0;
$fNotiz  = ($editId && $shift) ? ($shift['notiz'] ?? '')        : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $fehler[] = 'Ungültige Anfrage.';
    } else {
        $fDatum  = trim($_POST['datum']          ?? '');
        $_bH = $_POST['beginn_h'] ?? '';
        $_bM = $_POST['beginn_m'] ?? '';
        $fBeginn = ($_bH !== '' && $_bM !== '') ? sprintf('%02d:%02d', (int)$_bH, (int)$_bM) : '';
        $_eH = $_POST['ende_h'] ?? '';
        $_eM = $_POST['ende_m'] ?? '';
        $fEnde = ($_eH !== '' && $_eM !== '') ? sprintf('%02d:%02d', (int)$_eH, (int)$_eM) : '';
        $fPause  = max(0, (int)($_POST['pause_minuten'] ?? 0));
        $fNotiz  = trim($_POST['notiz']          ?? '');

        if ($role === 'admin' && !empty($_POST['target_user_id'])) {
            $zielUserId = (int)$_POST['target_user_id'];
        }

        // Validierung
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fDatum)) {
            $fehler[] = 'Ungültiges Datum.';
        } elseif ($fDatum > $heute) {
            $fehler[] = 'Datum darf nicht in der Zukunft liegen.';
        } elseif ($role === 'mitarbeiter' && $fDatum < $vor60) {
            $fehler[] = 'Datum darf nicht älter als 60 Tage sein.';
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $fBeginn)) {
            $fehler[] = 'Ungültige Beginnzeit.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $fEnde)) {
            $fehler[] = 'Ungültige Endzeit.';
        }
        if (empty($fehler)) {
            $bMin = (int)substr($fBeginn, 3, 2);
            $eMin = (int)substr($fEnde, 3, 2);
            if ($bMin % 15 !== 0 || $eMin % 15 !== 0) {
                $fehler[] = 'Bitte nur Zeiten in 15-Minuten-Schritten eintragen, z.B. 16:30 statt 16:34.';
            }
        }
        if (empty($fehler) && $fEnde <= $fBeginn) {
            $fehler[] = 'Endzeit muss nach Beginnzeit liegen.';
        }
        if (empty($fehler)) {
            $netto = berechneNettoStunden($fBeginn, $fEnde, $fPause);
            if ($netto <= 0) {
                $fehler[] = 'Nettoarbeitszeit muss positiv sein (Beginn, Ende und Pause prüfen).';
            }
        }
        if (empty($fehler) && hatUeberschneidung($pdo, $zielUserId, $fDatum, $fBeginn, $fEnde, $editId)) {
            $fehler[] = 'Diese Schicht überschneidet sich mit einer bereits eingetragenen Schicht an diesem Tag.';
        }

        if (empty($fehler)) {
            if ($editId) {
                $alteWerte = [
                    'datum'         => $shift['datum'],
                    'beginn'        => $shift['beginn'],
                    'ende'          => $shift['ende'],
                    'pause_minuten' => $shift['pause_minuten'],
                    'notiz'         => $shift['notiz'],
                ];
                $pdo->prepare(
                    'UPDATE shifts SET datum=?, beginn=?, ende=?, pause_minuten=?, notiz=?, geaendert_von=? WHERE id=?'
                )->execute([$fDatum, $fBeginn, $fEnde, $fPause, $fNotiz ?: null, $userId, $editId]);
                logShiftChange($pdo, $editId, $userId, 'geaendert', $alteWerte, [
                    'datum' => $fDatum, 'beginn' => $fBeginn, 'ende' => $fEnde,
                    'pause_minuten' => $fPause, 'notiz' => $fNotiz,
                ]);
                $erfolg = 'Schicht erfolgreich aktualisiert.';
            } else {
                $pdo->prepare(
                    'INSERT INTO shifts (user_id, datum, beginn, ende, pause_minuten, notiz, geaendert_von) VALUES (?,?,?,?,?,?,?)'
                )->execute([$zielUserId, $fDatum, $fBeginn, $fEnde, $fPause, $fNotiz ?: null, $userId]);
                $newId = (int)$pdo->lastInsertId();
                logShiftChange($pdo, $newId, $userId, 'erstellt', null, [
                    'datum' => $fDatum, 'beginn' => $fBeginn, 'ende' => $fEnde,
                    'pause_minuten' => $fPause, 'notiz' => $fNotiz,
                ]);
                $erfolg  = 'Schicht erfolgreich eingetragen.';
                $fBeginn = $fEnde = $fNotiz = '';
                $fPause  = 0;
                $fDatum  = $heute;
            }
        }
    }
}

// Mitarbeiterliste für Admin
$mitarbeiter = [];
if ($role === 'admin') {
    $mitarbeiter = $pdo->query('SELECT id, name FROM users WHERE aktiv = 1 ORDER BY name')->fetchAll();
}
$pageTitle = $editId ? 'Schicht bearbeiten' : 'Schicht eintragen';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="container">
    <h2><?= h($pageTitle) ?></h2>

    <?php if ($fehler): ?>
        <div class="alert alert-error">
            <?php foreach ($fehler as $f): ?><p><?= h($f) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($erfolg): ?>
        <div class="alert alert-success"><?= h($erfolg) ?></div>
    <?php endif; ?>

    <form method="post" action="" class="form-card">
        <?= csrfField() ?>

        <?php if ($role === 'admin'): ?>
        <div class="form-group">
            <label for="target_user_id">Mitarbeiter</label>
            <select name="target_user_id" id="target_user_id">
                <?php foreach ($mitarbeiter as $ma): ?>
                    <option value="<?= h($ma['id']) ?>"
                        <?= ((int)$ma['id'] === $zielUserId) ? ' selected' : '' ?>>
                        <?= h($ma['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="datum">Datum</label>
            <input type="date" id="datum" name="datum" required
                   value="<?= h($fDatum) ?>"
                   max="<?= h($heute) ?>"
                   <?= ($role === 'mitarbeiter') ? 'min="' . h($vor60) . '"' : '' ?>>
        </div>
        <?php
            $_selBH = $fBeginn !== '' ? substr($fBeginn, 0, 2) : '';
            $_selBM = $fBeginn !== '' ? substr($fBeginn, 3, 2) : '';
            $_selEH = $fEnde   !== '' ? substr($fEnde,   0, 2) : '';
            $_selEM = $fEnde   !== '' ? substr($fEnde,   3, 2) : '';
        ?>
        <div class="form-row">
            <div class="form-group">
                <label>Beginn</label>
                <div class="time-split">
                    <select id="beginn_h" name="beginn_h" required>
                        <option value="">HH</option>
                        <?php for ($_h = 0; $_h < 24; $_h++): $_v = sprintf('%02d', $_h); ?>
                        <option value="<?= $_v ?>"<?= $_v === $_selBH ? ' selected' : '' ?>><?= $_v ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="time-sep">:</span>
                    <select id="beginn_m" name="beginn_m" required>
                        <option value="">MM</option>
                        <?php foreach ([0,15,30,45] as $_m): $_v = sprintf('%02d', $_m); ?>
                        <option value="<?= $_v ?>"<?= $_v === $_selBM ? ' selected' : '' ?>><?= $_v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Ende</label>
                <div class="time-split">
                    <select id="ende_h" name="ende_h" required>
                        <option value="">HH</option>
                        <?php for ($_h = 0; $_h < 24; $_h++): $_v = sprintf('%02d', $_h); ?>
                        <option value="<?= $_v ?>"<?= $_v === $_selEH ? ' selected' : '' ?>><?= $_v ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="time-sep">:</span>
                    <select id="ende_m" name="ende_m" required>
                        <option value="">MM</option>
                        <?php foreach ([0,15,30,45] as $_m): $_v = sprintf('%02d', $_m); ?>
                        <option value="<?= $_v ?>"<?= $_v === $_selEM ? ' selected' : '' ?>><?= $_v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="pause_minuten">Pause (Minuten)</label>
            <input type="number" id="pause_minuten" name="pause_minuten"
                   min="0" max="480" value="<?= h($fPause) ?>">
        </div>
        <div class="form-group">
            <label for="notiz">Notiz (optional)</label>
            <input type="text" id="notiz" name="notiz" maxlength="500" value="<?= h($fNotiz) ?>">
        </div>

        <div class="netto-preview" id="nettoPreview" style="display:none">
            Nettoarbeitszeit: <strong id="nettoWert">–</strong>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $editId ? 'Speichern' : 'Eintragen' ?>
            </button>
            <a href="<?= h(BASE_URL) ?>/<?= ($role === 'mitarbeiter') ? 'meine_schichten' : 'admin_uebersicht' ?>.php"
               class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</main>
<script src="<?= h(BASE_URL) ?>/assets/main.js"></script>
</body>
</html>
