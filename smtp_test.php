<?php
require_once __DIR__ . '/config.php';
if (($_GET['key'] ?? '') !== 'test123') { die('kein Zugriff: ?key=test123'); }
header('Content-Type: text/plain; charset=UTF-8');

$zielEmail = trim($_GET['to'] ?? APP_EMAIL);
$host = 'smtp.ionos.de';
$user = APP_EMAIL;
$pass = defined('SMTP_PASS') ? SMTP_PASS : '';

echo "=== SMTP DIREKTTEST ===\n\n";
echo "VON : $user\n";
echo "AN  : $zielEmail\n";
echo "PW  : " . (($pass === '' || $pass === 'IHR_SMTP_PASSWORT') ? "!!! NICHT GESETZT !!!" : "gesetzt (".strlen($pass)." Zeichen)") . "\n\n";

$ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
$sock = @stream_socket_client('ssl://' . $host . ':465', $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo "VERBINDUNG FEHLGESCHLAGEN: $errno $errstr\n";
    exit;
}

stream_set_timeout($sock, 15);
$rd = function() use($sock) {
    $o = '';
    while (!feof($sock)) { $l = fgets($sock, 512); if ($l === false) break; $o .= $l; if (strlen($l) >= 4 && $l[3] === ' ') break; }
    return trim($o);
};
$wr = function($c) use($sock) { fwrite($sock, $c . "\r\n"); };

echo "Greeting : " . $rd() . "\n";
$wr('EHLO gruenekombuese.de'); echo "EHLO     : " . substr($rd(), 0, 50) . "\n";
$wr('AUTH LOGIN'); $rd();
$wr(base64_encode($user)); $rd();
$wr(base64_encode($pass));
$ar = $rd();
echo "AUTH     : $ar\n";

if (substr($ar, 0, 3) !== '235') {
    echo "\n>>> FEHLER: Login fehlgeschlagen <<<\n";
    $wr('QUIT'); fclose($sock); exit;
}

$wr('MAIL FROM:<' . $user . '>'); echo "MAIL FROM: " . $rd() . "\n";
$wr('RCPT TO:<' . $zielEmail . '>');
$rcpt = $rd();
echo "RCPT TO  : $rcpt\n";

if (substr($rcpt, 0, 3) !== '250' && substr($rcpt, 0, 3) !== '251') {
    echo "\n>>> FEHLER: Empfaenger abgelehnt <<<\n";
    $wr('QUIT'); fclose($sock); exit;
}

$wr('DATA'); $rd();
$betreff = 'Test Zeiterfassung ' . date('H:i:s');
$msg  = "From: " . APP_NAME . " <$user>\r\n";
$msg .= "To: $zielEmail\r\n";
$msg .= "Subject: $betreff\r\n";
$msg .= "Date: " . date('r') . "\r\n";
$msg .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
$msg .= "Hallo,\r\n\r\nDies ist ein Test-E-Mail der Zeiterfassung.\r\nGesendet: " . date('d.m.Y H:i:s') . "\r\n.";
$wr($msg);
$dr = $rd();
echo "DATA END : $dr\n";

$wr('QUIT'); fclose($sock);

if (substr($dr, 0, 3) === '250') {
    echo "\n>>> E-MAIL ERFOLGREICH GESENDET <<<\n";
    echo "Bitte Postfach pruefen: $zielEmail\n";
    echo "(auch Spam-Ordner!)\n";
} else {
    echo "\n>>> SENDEN FEHLGESCHLAGEN <<<\n";
}

echo "\n=== ENDE ===\n";
echo "WICHTIG: Diese Datei danach sofort loeschen!\n";
