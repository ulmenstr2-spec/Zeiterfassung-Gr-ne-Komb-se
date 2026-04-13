<?php
/**
 * smtp_test.php – Diagnose-Skript fuer E-Mail-Versand
 * NUR ZUM TESTEN – danach sofort loeschen!
 */
require_once __DIR__ . '/config.php';

// Nur lokal ausfuehren oder mit Schutztoken
$secret = 'test123';
if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    die('Kein Zugriff. Benutze: smtp_test.php?key=' . $secret);
}

$testEmail = defined('APP_EMAIL') ? APP_EMAIL : 'info@gruenekombuese.de';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>SMTP-Test</title>
<style>
body { font-family: monospace; font-size:14px; padding:2em; background:#111; color:#eee; }
h2 { color:#4fc3f7; }
.ok   { color:#81c784; }
.fail { color:#e57373; }
.info { color:#fff176; }
pre  { background:#222; padding:1em; border-radius:4px; white-space:pre-wrap; word-break:break-all; }
hr   { border-color:#444; margin: 1.5em 0; }
</style>
</head>
<body>
<h2>SMTP-Diagnose &ndash; <?= htmlspecialchars(APP_NAME) ?></h2>
<p class="info">Test-E-Mail wird an: <strong><?= htmlspecialchars($testEmail) ?></strong> gesendet</p>

<?php

// -----------------------------------------------------------------------
// 1) PHP-Version & relevante Funktionen
// -----------------------------------------------------------------------
echo '<hr><h3>1) Umgebung</h3><pre>';
echo 'PHP-Version : ' . PHP_VERSION . "\n";
echo 'fsockopen   : ' . (function_exists('fsockopen')   ? '<span class="ok">verfuegbar</span>' : '<span class="fail">NICHT verfuegbar</span>') . "\n";
echo 'stream_socket_client: ' . (function_exists('stream_socket_client') ? '<span class="ok">verfuegbar</span>' : '<span class="fail">NICHT verfuegbar</span>') . "\n";
echo 'mail()      : ' . (function_exists('mail')        ? '<span class="ok">verfuegbar</span>' : '<span class="fail">NICHT verfuegbar</span>') . "\n";
echo 'SMTP_PASS   : ' . (defined('SMTP_PASS') && SMTP_PASS !== '' && SMTP_PASS !== 'IHR_SMTP_PASSWORT' ? '<span class="ok">gesetzt</span>' : '<span class="fail">NICHT gesetzt (in config.php eintragen)</span>') . "\n";
echo '</pre>';

// -----------------------------------------------------------------------
// 2) TCP-Verbindung zu smtp.ionos.de:465 und :587
// -----------------------------------------------------------------------
echo '<hr><h3>2) TCP-Verbindungstest</h3><pre>';

foreach ([465 => 'ssl://', 587 => 'tcp://'] as $port => $prefix) {
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);
    $start = microtime(true);
    $sock  = @stream_socket_client($prefix . 'smtp.ionos.de:' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    $ms    = round((microtime(true) - $start) * 1000);
    if ($sock) {
        fclose($sock);
        echo 'Port ' . $port . ' (' . ltrim($prefix, '/') . '): <span class="ok">VERBUNDEN</span> (' . $ms . ' ms)' . "\n";
    } else {
        echo 'Port ' . $port . ' (' . ltrim($prefix, '/') . '): <span class="fail">FEHLGESCHLAGEN</span> – ' . $errno . ': ' . htmlspecialchars($errstr) . "\n";
    }
}
echo '</pre>';

// -----------------------------------------------------------------------
// 3) Vollstaendiger SMTP-Handshake (Port 465)
// -----------------------------------------------------------------------
echo '<hr><h3>3) SMTP-Handshake Port 465 (SSL)</h3><pre>';

$sslCtx = stream_context_create([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
]);
$sock465 = @stream_socket_client('ssl://smtp.ionos.de:465', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $sslCtx);

if (!$sock465) {
    echo '<span class="fail">Verbindung fehlgeschlagen: ' . $errno . ': ' . htmlspecialchars($errstr) . '</span>' . "\n";
} else {
    stream_set_timeout($sock465, 10);
    $log = '';

    $r = function () use ($sock465, &$log): string {
        $out = '';
        while (!feof($sock465)) {
            $line = fgets($sock465, 512);
            if ($line === false) { break; }
            $out .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') { break; }
        }
        $log .= 'S: ' . trim($out) . "\n";
        return $out;
    };
    $w = function (string $cmd) use ($sock465, &$log): void {
        $log .= 'C: ' . $cmd . "\n";
        fwrite($sock465, $cmd . "\r\n");
    };

    $greeting = $r();
    $ok465 = (substr($greeting, 0, 3) === '220');

    if ($ok465) {
        $w('EHLO gruenekombuese.de'); $r();

        $smtpUser = defined('APP_EMAIL') ? APP_EMAIL : '';
        $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';

        if ($smtpPass !== '' && $smtpPass !== 'IHR_SMTP_PASSWORT') {
            $w('AUTH LOGIN'); $r();
            $w(base64_encode($smtpUser)); $r();
            $w(base64_encode($smtpPass));
            $authR = $r();
            if (substr($authR, 0, 3) === '235') {
                $log .= "==> AUTH LOGIN: ERFOLGREICH\n";
            } else {
                $log .= "==> AUTH LOGIN: FEHLGESCHLAGEN\n";
            }
        } else {
            $log .= "==> Kein SMTP_PASS gesetzt – AUTH-Test uebersprungen\n";
        }

        $w('QUIT'); $r();
    }

    fclose($sock465);
    echo htmlspecialchars($log);
    if ($ok465) {
        echo '<span class="ok">Verbindung erfolgreich.</span>' . "\n";
    } else {
        echo '<span class="fail">Server hat mit Fehler geantwortet.</span>' . "\n";
    }
}
echo '</pre>';

// -----------------------------------------------------------------------
// 4) SMTP-Handshake Port 587 (STARTTLS)
// -----------------------------------------------------------------------
echo '<hr><h3>4) SMTP-Handshake Port 587 (STARTTLS)</h3><pre>';

$sock587 = @stream_socket_client('tcp://smtp.ionos.de:587', $errno, $errstr, 15);

if (!$sock587) {
    echo '<span class="fail">Verbindung fehlgeschlagen: ' . $errno . ': ' . htmlspecialchars($errstr) . '</span>' . "\n";
} else {
    stream_set_timeout($sock587, 10);
    $log2 = '';

    $r2 = function () use ($sock587, &$log2): string {
        $out = '';
        while (!feof($sock587)) {
            $line = fgets($sock587, 512);
            if ($line === false) { break; }
            $out .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') { break; }
        }
        $log2 .= 'S: ' . trim($out) . "\n";
        return $out;
    };
    $w2 = function (string $cmd) use ($sock587, &$log2): void {
        $log2 .= 'C: ' . $cmd . "\n";
        fwrite($sock587, $cmd . "\r\n");
    };

    $r2(); // Greeting
    $w2('EHLO gruenekombuese.de'); $r2();
    $w2('STARTTLS');
    $starttlsR = $r2();
    if (substr($starttlsR, 0, 3) === '220') {
        stream_context_set_option($sock587, 'ssl', 'verify_peer',      false);
        stream_context_set_option($sock587, 'ssl', 'verify_peer_name', false);
        stream_context_set_option($sock587, 'ssl', 'allow_self_signed', true);
        $tls = @stream_socket_enable_crypto($sock587, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($tls) {
            $log2 .= "==> TLS-Aushandlung: ERFOLGREICH\n";
            $w2('EHLO gruenekombuese.de'); $r2();

            $smtpUser = defined('APP_EMAIL') ? APP_EMAIL : '';
            $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : '';
            if ($smtpPass !== '' && $smtpPass !== 'IHR_SMTP_PASSWORT') {
                $w2('AUTH LOGIN'); $r2();
                $w2(base64_encode($smtpUser)); $r2();
                $w2(base64_encode($smtpPass));
                $authR2 = $r2();
                if (substr($authR2, 0, 3) === '235') {
                    $log2 .= "==> AUTH LOGIN: ERFOLGREICH\n";
                } else {
                    $log2 .= "==> AUTH LOGIN: FEHLGESCHLAGEN\n";
                }
            }
        } else {
            $log2 .= "==> TLS-Aushandlung: FEHLGESCHLAGEN\n";
        }
    } else {
        $log2 .= "==> STARTTLS nicht unterstuetzt oder abgelehnt\n";
    }
    $w2('QUIT'); $r2();
    fclose($sock587);
    echo htmlspecialchars($log2);
}
echo '</pre>';

// -----------------------------------------------------------------------
// 5) Test via PHP mail()
// -----------------------------------------------------------------------
echo '<hr><h3>5) PHP mail() Test</h3><pre>';
if (function_exists('mail')) {
    $subj  = 'SMTP-Test mail() - ' . date('H:i:s');
    $msg   = "Dies ist ein automatischer Test-E-Mail.\r\nGesendet am: " . date('d.m.Y H:i:s');
    $hdrs  = 'From: ' . APP_NAME . ' <' . APP_EMAIL . ">\r\n";
    $hdrs .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $sent  = @mail($testEmail, $subj, $msg, $hdrs, '-f ' . APP_EMAIL);
    if ($sent) {
        echo '<span class="ok">mail() hat TRUE zurueckgegeben</span>' . "\n";
        echo 'Bitte E-Mail-Postfach pruefen: ' . htmlspecialchars($testEmail) . "\n";
    } else {
        echo '<span class="fail">mail() hat FALSE zurueckgegeben</span>' . "\n";
    }
} else {
    echo '<span class="fail">mail() nicht verfuegbar</span>' . "\n";
}
echo '</pre>';

// -----------------------------------------------------------------------
// 6) Test vollstaendiger smtpSend() (wenn SMTP_PASS gesetzt)
// -----------------------------------------------------------------------
if (defined('SMTP_PASS') && SMTP_PASS !== '' && SMTP_PASS !== 'IHR_SMTP_PASSWORT') {
    echo '<hr><h3>6) Vollstaendiger SMTP-Versand (smtpSend)</h3><pre>';
    require_once __DIR__ . '/includes/functions.php';
    $ok = smtpSend(
        $testEmail,
        'Test-Empfaenger',
        'Test smtpSend() - ' . date('H:i:s'),
        "Hallo,\r\n\r\nDies ist ein Test des SMTP-Versands.\r\n\r\nGesendet: " . date('d.m.Y H:i:s')
    );
    if ($ok) {
        echo '<span class="ok">smtpSend() erfolgreich!</span>' . "\n";
        echo 'Bitte E-Mail-Postfach pruefen: ' . htmlspecialchars($testEmail) . "\n";
    } else {
        echo '<span class="fail">smtpSend() fehlgeschlagen – details im PHP-Fehlerlog</span>' . "\n";
    }
    echo '</pre>';
}
?>

<hr>
<p class="fail"><strong>WICHTIG:</strong> Diese Datei nach dem Test sofort loeschen!</p>
</body>
</html>
