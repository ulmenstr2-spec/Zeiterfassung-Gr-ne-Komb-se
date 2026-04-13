<?php
require_once __DIR__ . '/config.php';
if (($_GET['key'] ?? '') !== 'test123') { die('kein Zugriff: ?key=test123'); }
header('Content-Type: text/plain; charset=UTF-8');

$host = 'smtp.ionos.de';
$user = APP_EMAIL;
$pass = defined('SMTP_PASS') ? SMTP_PASS : '';

echo "=== SMTP DIAGNOSE ===\n\n";
echo "E-Mail  : $user\n";
echo "Passwort: " . (($pass === '' || $pass === 'IHR_SMTP_PASSWORT') ? "!!! NICHT GESETZT !!!" : "gesetzt (Laenge: " . strlen($pass) . ")") . "\n\n";

// --- Port 465 SSL ---
echo "--- Port 465 (SSL) ---\n";
$ctx = stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]]);
$s = @stream_socket_client('ssl://smtp.ionos.de:465', $e, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
if (!$s) {
    echo "VERBINDUNG FEHLGESCHLAGEN: $e $es\n\n";
} else {
    stream_set_timeout($s, 10);
    $rd = function() use($s){ $o=''; while(!feof($s)){ $l=fgets($s,512); if($l===false)break; $o.=$l; if(strlen($l)>=4&&$l[3]==' ')break; } return trim($o); };
    $wr = function($c) use($s){ fwrite($s,$c."\r\n"); };
    echo "Greeting : " . $rd() . "\n";
    $wr('EHLO gruenekombuese.de'); echo "EHLO     : " . substr($rd(),0,60) . "\n";
    $wr('AUTH LOGIN'); echo "AUTH REQ : " . $rd() . "\n";
    $wr(base64_encode($user)); echo "USER     : " . $rd() . "\n";
    $wr(base64_encode($pass));
    $ar = $rd();
    echo "AUTH RES : $ar\n";
    if (substr($ar,0,3)==='235') {
        echo ">>> LOGIN ERFOLGREICH <<<\n";
        // Jetzt wirklich eine E-Mail senden
        $wr('MAIL FROM:<'.$user.'>'); echo "MAIL FROM: " . $rd() . "\n";
        $wr('RCPT TO:<'.$user.'>');  echo "RCPT TO  : " . $rd() . "\n";
        $wr('DATA'); $rd();
        $msg  = "From: $user\r\nTo: $user\r\nSubject: SMTP-Test ".date('H:i:s')."\r\n";
        $msg .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        $msg .= "Test-Nachricht vom SMTP-Diagnose-Skript.\r\nDatum: ".date('d.m.Y H:i:s')."\r\n.";
        $wr($msg);
        $dr = $rd();
        echo "DATA END : $dr\n";
        echo (substr($dr,0,3)==='250') ? ">>> E-MAIL GESENDET <<<\n" : ">>> SENDEN FEHLGESCHLAGEN <<<\n";
    } else {
        echo ">>> LOGIN FEHLGESCHLAGEN <<<\n";
    }
    $wr('QUIT'); fclose($s);
}

echo "\n--- PHP mail() ---\n";
$ok = @mail($user, 'Test mail() '.date('H:i:s'), 'Test', 'From: '.$user."\r\n", '-f '.$user);
echo "mail() Ergebnis: " . ($ok ? "TRUE (akzeptiert)" : "FALSE (abgelehnt)") . "\n";

echo "\n=== ENDE ===\n";
echo "WICHTIG: Diese Datei danach sofort loeschen!\n";
