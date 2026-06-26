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
    $pdo->prepare(
        'INSERT INTO shift_log (shift_id, geaendert_von, aktion, alte_werte, neue_werte)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $shiftId,
        $geaendertVon,
        $aktion,
        $alteWerte !== null ? json_encode($alteWerte, JSON_UNESCAPED_UNICODE) : null,
        $neueWerte !== null ? json_encode($neueWerte, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

/**
 * Sendet eine Passwort-Reset-E-Mail ueber Brevo API.
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool
{
    $resetUrl = BASE_URL . '/passwort_reset.php?token=' . urlencode($token);
    $subject  = 'Passwort zuruecksetzen - ' . APP_NAME;
    $body     = 'Hallo ' . $toName . "\r\n\r\n"
              . "Du hast eine Passwort-Zuruecksetzung angefordert.\r\n\r\n"
              . "Link:\r\n" . $resetUrl . "\r\n\r\n"
              . "Dieser Link ist eine Stunde gueltig.\r\n\r\n"
              . "Falls du keine Zuruecksetzung angefordert hast,\r\n"
              . "ignoriere diese E-Mail einfach.\r\n\r\n"
              . "Viele Gruesse\r\n" . APP_NAME;

    return brevoSend($toEmail, $toName, $subject, $body);
}

/**
 * Sendet eine E-Mail ueber die Brevo-API (ehemals Sendinblue).
 * Benoetigt: define('BREVO_API_KEY', 'xkeysib-...') in config.php
 */
function brevoSend(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (!defined('BREVO_API_KEY') || BREVO_API_KEY === '' || BREVO_API_KEY === 'xkeysib-DEIN-KEY-HIER') {
        error_log('brevoSend: BREVO_API_KEY nicht gesetzt');
        return false;
    }

    $payload = json_encode([
        'sender'      => ['name' => APP_NAME, 'email' => APP_EMAIL],
        'to'          => [['email' => $toEmail, 'name'  => $toName]],
        'subject'     => $subject,
        'textContent' => $body,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('brevoSend curl Fehler: ' . $curlErr);
        return false;
    }
    if ($httpCode !== 201) {
        error_log('brevoSend HTTP ' . $httpCode . ': ' . $result);
        return false;
    }
    return true;
}

/**
 * Gibt <option>-Tags fuer einen Monatsfilter aus (aktueller Monat + 10 Jahre zurueck).
 */
function monatOptionen(string $selected = ''): string
{
    $html  = '';
    $jetzt = new DateTime();
    $de    = [
        'January'   => 'Januar',   'February' => 'Februar',  'March'    => 'Maerz',
        'April'     => 'April',    'May'      => 'Mai',      'June'     => 'Juni',
        'July'      => 'Juli',     'August'   => 'August',   'September'=> 'September',
        'October'   => 'Oktober',  'November' => 'November', 'December' => 'Dezember',
    ];
    for ($i = 0; $i < 120; $i++) {
        $val   = $jetzt->format('Y-m');
        $label = strtr($jetzt->format('F Y'), $de);
        $sel   = ($val === $selected) ? ' selected' : '';
        $html .= '<option value="' . h($val) . '"' . $sel . '>' . h($label) . '</option>';
        $jetzt->modify('-1 month');
    }
    return $html;
}
