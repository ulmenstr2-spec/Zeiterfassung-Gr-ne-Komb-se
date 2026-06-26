<?php
// ============================================================
// config.sample.php – Vorlage fuer config.php
// Datei kopieren: cp config.sample.php config.php
// Dann alle Platzhalter mit echten Werten ersetzen.
// config.php darf NICHT ins Git-Repository!
// ============================================================

define('DB_HOST', 'IHR_DATENBANKHOST');       // z.B. db12345.hosting-data.io
define('DB_NAME', 'IHR_DATENBANKNAME');        // Datenbankname aus IONOS-Verwaltung
define('DB_USER', 'IHR_DATENBANKBENUTZER');    // Datenbankbenutzer
define('DB_PASS', 'IHR_DATENBANKPASSWORT');    // Datenbankpasswort

define('APP_NAME', 'Zeiterfassung Grüne Kombüse');
define('TIMEZONE',  'Europe/Berlin');
define('APP_EMAIL',      'info@beispiel.de');
define('BREVO_API_KEY',  'xkeysib-DEIN-KEY-HIER');  // Brevo API Key (brevo.com)
define('BASE_URL',       'https://beispiel.de/zeiterfassung'); // ohne abschliesenden Slash

date_default_timezone_set(TIMEZONE);
