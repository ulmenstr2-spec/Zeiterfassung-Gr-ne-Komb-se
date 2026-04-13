<?php
// ============================================================
// config.php – VOR DER INSTALLATION ANPASSEN
// ============================================================

define('DB_HOST', 'IHR_DATENBANKHOST');       // z.B. db12345.hosting-data.io
define('DB_NAME', 'dbs665846');                // Datenbankname aus IONOS-Verwaltung
define('DB_USER', 'dbs665846');                // Datenbankbenutzer
define('DB_PASS', 'IHR_DATENBANKPASSWORT');    // Datenbankpasswort

define('APP_NAME', 'Zeiterfassung Grüne Kombüse');
define('TIMEZONE',  'Europe/Berlin');
define('APP_EMAIL', 'info@gruenekombuese.de');
define('BASE_URL',  'https://gruenekombuese.de/zeiterfassung'); // ohne abschließenden Slash

date_default_timezone_set(TIMEZONE);
