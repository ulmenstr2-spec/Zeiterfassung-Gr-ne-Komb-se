<?php
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function berechneNettoStunden(string $beginn, string $ende, int $pauseMinuten): float
{
    $diffMin = (strtotime($ende) - strtotime($beginn)) / 60;
    return round(($diffMin - $pauseMinuten) / 60, 4);
}

function formatStunden(float $stunden): string
{
    $sign    = $stunden < 0 ? '-' : '';
    $stunden = abs($stunden);
    $h       = (int)$stunden;
    $m       = (int)round(($stunden - $h) * 60);
    return sprintf('%s%d:%02d h', $sign, $h, $m);
}

function hatUeberschneidung(
    PDO $pdo,
    int $userId,
    string $datum,
    string $beginn,
    string $ende,
    ?int $excludeId = null
): bool {
    $sql = 'SELECT COUNT(*) FROM shifts
            WHERE user_id = :uid AND datum = :datum
              AND beginn < :ende AND ende > :beginn';
    if ($excludeId !== null) {
        $sql .= ' AND id != :excl';
    }
    $stmt   = $pdo->prepare($sql);
    $params = [':uid' => $userId, ':datum' => $datum, ':beginn' => $beginn, ':ende' => $ende];
    if ($excludeId !== null) {
        $params[':excl'] = $excludeId;
    }
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function logShiftChange(
    PDO $pdo,
    int $shiftId,
    int $geaendertVon,
    string $aktion,
    ?array $alteWerte,
    ?array $neueWerte
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO shift_log (shift_id, geaendert_von, aktion, alte_werte, neue_werte)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $shiftId,
        $geaendertVon,
        $aktion,
        $alteWerte !== null ? json_encode($alteWerte, JSON_UNESCAPED_UNICODE) : null,
        $neueWerte !== null ? json_encode($neueWerte, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool
{
    $resetUrl = BASE_URL . '/passwort_reset.php?token=' . urlencode($token);
    $subject  = '=?UTF-8?B?' . base64_encode('Passwort zurücksetzen – ' . APP_NAME) . '?=';

    $body  = 'Hallo ' . $toName . ",\n\n";
    $body .= "Sie haben eine Passwort-Zurücksetzung für Ihren Account angefordert.\n\n";
    $body .= "Klicken Sie auf den folgenden Link, um ein neues Passwort zu setzen:\n";
    $body .= $resetUrl . "\n\n";
    $body .= "Dieser Link ist eine Stunde gültig.\n\n";
    $body .= "Falls Sie keine Zurücksetzung angefordert haben, ignorieren Sie diese E-Mail.\n\n";
    $body .= 'Viele Grüße' . "\n" . APP_NAME;

    $headers  = 'From: ' . APP_NAME . ' <' . APP_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . APP_EMAIL . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    return mail($toEmail, $subject, $body, $headers);
}

/**
 * Gibt <option>-Tags für einen Monatsfilter aus (aktuelle Monat + 10 Jahre zurück).
 */
function monatOptionen(string $selected = ''): string
{
    $html  = '';
    $jetzt = new DateTime();
    $deutschen = [
        'January'   => 'Januar',   'February' => 'Februar', 'March'    => 'März',
        'April'     => 'April',    'May'      => 'Mai',     'June'     => 'Juni',
        'July'      => 'Juli',     'August'   => 'August',  'September'=> 'September',
        'October'   => 'Oktober',  'November' => 'November','December' => 'Dezember',
    ];
    for ($i = 0; $i < 120; $i++) {
        $val   = $jetzt->format('Y-m');
        $label = strtr($jetzt->format('F Y'), $deutschen);
        $sel   = ($val === $selected) ? ' selected' : '';
        $html .= '<option value="' . h($val) . '"' . $sel . '>' . h($label) . '</option>';
        $jetzt->modify('-1 month');
    }
    return $html;
}
