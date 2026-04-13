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

/**
 * Sendet eine Passwort-Reset-E-Mail via SMTP (oder PHP mail() als Fallback).
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool
{
    $resetUrl = BASE_URL . '/passwort_reset.php?token=' . urlencode($token);

    $subject = 'Passwort zuruecksetzen - ' . APP_NAME;

    $body  = 'Hallo ' . $toName . "\r\n\r\n";
    $body .= "Sie haben eine Passwort-Zuruecksetzung angefordert.\r\n\r\n";
    $body .= "Link:\r\n";
    $body .= $resetUrl . "\r\n\r\n";
    $body .= "Dieser Link ist eine Stunde gueltig.\r\n\r\n";
    $body .= "Falls Sie keine Zuruecksetzung angefordert haben,\r\n";
    $body .= "ignorieren Sie diese E-Mail einfach.\r\n\r\n";
    $body .= "Viele Gruesse\r\n";
    $body .= APP_NAME;

    // SMTP bevorzugen, falls Passwort hinterlegt
    if (defined('SMTP_PASS') && SMTP_PASS !== '' && SMTP_PASS !== 'IHR_SMTP_PASSWORT') {
        $ok = smtpSend($toEmail, $toName, $subject, $body);
        if ($ok) { return true; }
        error_log('sendPasswordResetEmail: SMTP fehlgeschlagen, versuche mail()');
    }

    // Fallback: PHP mail()
    $headers  = 'From: ' . APP_NAME . ' <' . APP_EMAIL . ">\r\n";
    $headers .= 'Reply-To: ' . APP_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";

    $sent = @mail($toEmail, $subject, $body, $headers, '-f ' . APP_EMAIL);
    if (!$sent) {
        error_log('sendPasswordResetEmail: mail() fehlgeschlagen');
    }
    return $sent;
}

/**
 * Sendet eine E-Mail via SMTP mit AUTH LOGIN.
 * Versucht zuerst Port 465 (SSL), dann Port 587 (STARTTLS).
 */
function smtpSend(string $toEmail, string $toName, string $subject, string $body): bool
{
    $smtpHost = 'smtp.ionos.de';
    $smtpUser = APP_EMAIL;
    $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';

    $sslCtx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ]);

    // 1. Versuch: Port 465 mit direktem SSL
    $sock = @stream_socket_client(
        'ssl://' . $smtpHost . ':465',
        $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT,
        $sslCtx
    );
    $starttls = false;

    if (!$sock) {
        error_log('SMTP 465 fehlgeschlagen (' . $errno . ': ' . $errstr . '), versuche 587');

        // 2. Versuch: Port 587 mit STARTTLS
        $sock = @stream_socket_client(
            'tcp://' . $smtpHost . ':587',
            $errno, $errstr, 20
        );
        if (!$sock) {
            error_log('SMTP 587 fehlgeschlagen (' . $errno . ': ' . $errstr . ')');
            return false;
        }
        $starttls = true;
    }

    stream_set_timeout($sock, 15);

    // Liest eine vollstaendige SMTP-Antwort (auch mehrzeilig)
    $read = function () use ($sock): string {
        $out = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) { break; }
            $out .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') { break; }
        }
        return $out;
    };

    $write = function (string $cmd) use ($sock): void {
        fwrite($sock, $cmd . "\r\n");
    };

    // Begruessing
    $r = $read();
    if (substr($r, 0, 3) !== '220') {
        error_log('SMTP: schlechte Begruessung: ' . trim($r));
        fclose($sock);
        return false;
    }

    $write('EHLO gruenekombuese.de');
    $read();

    if ($starttls) {
        $write('STARTTLS');
        $r = $read();
        if (substr($r, 0, 3) !== '220') {
            error_log('SMTP: STARTTLS abgelehnt: ' . trim($r));
            fclose($sock);
            return false;
        }
        stream_context_set_option($sock, 'ssl', 'verify_peer',      false);
        stream_context_set_option($sock, 'ssl', 'verify_peer_name', false);
        stream_context_set_option($sock, 'ssl', 'allow_self_signed', true);
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log('SMTP: TLS-Aushandlung fehlgeschlagen');
            fclose($sock);
            return false;
        }
        $write('EHLO gruenekombuese.de');
        $read();
    }

    // AUTH LOGIN
    $write('AUTH LOGIN');
    $read();
    $write(base64_encode($smtpUser));
    $read();
    $write(base64_encode($smtpPass));
    $r = $read();
    if (substr($r, 0, 3) !== '235') {
        error_log('SMTP: AUTH fehlgeschlagen: ' . trim($r));
        fclose($sock);
        return false;
    }

    // Absender / Empfaenger
    $write('MAIL FROM:<' . $smtpUser . '>');
    $read();
    $write('RCPT TO:<' . $toEmail . '>');
    $r = $read();
    if (substr($r, 0, 3) !== '250' && substr($r, 0, 3) !== '251') {
        error_log('SMTP: RCPT abgelehnt: ' . trim($r));
        fclose($sock);
        return false;
    }

    $write('DATA');
    $read();

    $msgId   = '<' . time() . '.' . mt_rand(1000, 9999) . '@gruenekombuese.de>';
    $dateStr = date('r');

    $msg  = 'From: ' . APP_NAME . ' <' . $smtpUser . ">\r\n";
    $msg .= 'To: '   . $toName  . ' <' . $toEmail  . ">\r\n";
    $msg .= 'Subject: ' . $subject . "\r\n";
    $msg .= 'Date: '    . $dateStr . "\r\n";
    $msg .= 'Message-ID: ' . $msgId . "\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n";
    $msg .= "\r\n";
    $msg .= $body;
    $msg .= "\r\n.";

    $write($msg);
    $r = $read();

    $write('QUIT');
    fclose($sock);

    if (substr($r, 0, 3) !== '250') {
        error_log('SMTP: DATA-Ende fehlgeschlagen: ' . trim($r));
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
    $deutschen = [
        'January'    => 'Januar',    'February' => 'Februar',  'March'     => 'Maerz',
        'April'      => 'April',     'May'      => 'Mai',      'June'      => 'Juni',
        'July'       => 'Juli',      'August'   => 'August',   'September' => 'September',
        'October'    => 'Oktober',   'November' => 'November', 'December'  => 'Dezember',
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
