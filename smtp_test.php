<?php
require_once __DIR__ . '/config.php';
if (($_GET['key'] ?? '') !== 'test123') { die('kein Zugriff: ?key=test123'); }
header('Content-Type: text/plain; charset=UTF-8');

$zielEmail = trim($_GET['to'] ?? APP_EMAIL);

echo "=== MAIL() TEST ===\n\n";
echo "VON : " . APP_EMAIL . "\n";
echo "AN  : $zielEmail\n\n";

$betreff = 'Test Zeiterfassung Passwort-Reset ' . date('H:i:s');
$nachricht  = 'Hallo,' . "\r\n\r\n";
$nachricht .= 'Dies ist ein Test-E-Mail.' . "\r\n";
$nachricht .= 'Gesendet: ' . date('d.m.Y H:i:s') . "\r\n";

$headers  = 'From: ' . APP_NAME . ' <' . APP_EMAIL . ">\r\n";
$headers .= 'Reply-To: ' . APP_EMAIL . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$ok = mail($zielEmail, $betreff, $nachricht, $headers, '-f ' . APP_EMAIL);

echo "mail() Ergebnis: " . ($ok ? "TRUE - akzeptiert" : "FALSE - abgelehnt") . "\n\n";

if ($ok) {
    echo ">>> E-MAIL ABGESCHICKT <<<\n";
    echo "Bitte Postfach pruefen: $zielEmail\n";
    echo "Auch Spam-Ordner checken!\n";
    echo "(Kann bis zu 5 Minuten dauern)\n";
} else {
    echo ">>> mail() wurde abgelehnt <<<\n";
    echo "IONOS mail() ist auf diesem Hosting nicht freigeschaltet.\n";
}

echo "\n=== ENDE ===\n";
echo "Danach bitte loeschen!\n";
