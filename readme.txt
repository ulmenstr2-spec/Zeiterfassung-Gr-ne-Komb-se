============================================================
  Zeiterfassung Grüne Kombüse – Installationsanleitung
============================================================

VORAUSSETZUNGEN
---------------
- PHP 7.4 oder höher
- MySQL 5.7 oder höher
- HTTPS aktiviert (SSL-Zertifikat auf Ihrer Domain)
- FTP-Zugang zum IONOS-Webspace


SCHRITT 1: DATEIEN HOCHLADEN
------------------------------
Laden Sie alle Dateien und Ordner per FTP auf Ihren Server.

Empfohlener Pfad: /zeiterfassung/ im Webroot
(oder direkt im Webroot, dann BASE_URL entsprechend anpassen)

Struktur auf dem Server:
  zeiterfassung/
  ├── config.php
  ├── index.php
  ├── install.php
  ├── dashboard.php
  ├── schicht_eintragen.php
  ├── meine_schichten.php
  ├── admin_uebersicht.php
  ├── admin_mitarbeiter.php
  ├── admin_export.php
  ├── passwort_vergessen.php
  ├── passwort_reset.php
  ├── logout.php
  ├── .htaccess
  ├── includes/
  │   ├── auth.php
  │   ├── db.php
  │   ├── functions.php
  │   ├── nav.php
  │   └── .htaccess
  └── assets/
      ├── style.css
      └── main.js


SCHRITT 2: DATENBANK IN IONOS ANLEGEN
---------------------------------------
1. IONOS Control Panel → Datenbanken → Neue Datenbank
2. Datenbank anlegen und folgende Werte notieren:
   - Hostname    (z.B. db12345.hosting-data.io)
   - Datenbankname
   - Benutzername
   - Passwort


SCHRITT 3: CONFIG.PHP ANPASSEN
--------------------------------
Öffnen Sie config.php und tragen Sie Ihre Werte ein:

  define('DB_HOST', 'db12345.hosting-data.io');
  define('DB_NAME', 'dbs12345');
  define('DB_USER', 'dbs12345');
  define('DB_PASS', 'IhrPasswort');
  define('BASE_URL', 'https://ihredomain.de/zeiterfassung');
                       ↑ Pfad anpassen, kein abschließender /


SCHRITT 4: INSTALL.PHP ANPASSEN
---------------------------------
Öffnen Sie install.php und passen Sie oben im Script
die Admin-Zugangsdaten an:

  $adminName     = 'Josef';
  $adminEmail    = 'josef@gruenekombuese.de';
  $adminPasswort = 'SicheresPasswort123!';

  ⚠️  Wählen Sie ein starkes Passwort!


SCHRITT 5: INSTALLATION AUSFÜHREN
------------------------------------
Rufen Sie im Browser auf:
  https://ihredomain.de/zeiterfassung/install.php

Bei Erfolg erscheint eine Bestätigung mit grünem Text.
Alle 4 Tabellen werden angelegt und der Admin-Account erstellt.


SCHRITT 6: INSTALL.PHP SOFORT LÖSCHEN
---------------------------------------
  ⚠️  WICHTIG: Löschen Sie install.php nach der Installation
  sofort von Ihrem Server (per FTP)!

  Die automatisch erstellte install.lock schützt vor
  erneutem Aufruf, ersetzt aber NICHT das Löschen der Datei.


SCHRITT 7: ANMELDEN
---------------------
Rufen Sie auf:
  https://ihredomain.de/zeiterfassung/index.php

  → Mit den Admin-Zugangsdaten aus Schritt 4 anmelden.


SCHRITT 8: MITARBEITER ANLEGEN
--------------------------------
  → Menü: Mitarbeiter → Neuen Mitarbeiter anlegen

  Rollen:
    admin       – Vollzugriff (Josef, Inhaber)
    buchhaltung – Nur lesen + CSV-Export (z.B. Sigrid Weber)
    mitarbeiter – Eigene Schichten eintragen und einsehen


VERWENDUNG – KURZÜBERSICHT
-----------------------------
Mitarbeiter:
  - Schicht eintragen: Datum, Beginn, Ende, Pause (optional)
  - Eigene Schichten: letzte 60 Tage sichtbar
  - Bearbeiten möglich: innerhalb 7 Tage nach Eintrag

Admin (Josef):
  - Monatsübersicht aller Mitarbeiter
  - Schichten aller Mitarbeiter eintragen und bearbeiten
  - Schichten löschen (mit Protokoll)
  - Mitarbeiter anlegen, deaktivieren, Passwort zurücksetzen
  - CSV-Export für die Buchhaltung

Buchhaltung (Sigrid):
  - Monatsübersicht lesen
  - CSV-Export herunterladen
  - Keine Schichteintragung oder -änderung


AUFBEWAHRUNG DER DATEN
------------------------
Daten werden nie automatisch gelöscht.
Gesetzliche Aufbewahrungspflichten:
  - Arbeitszeitdokumentation (MiLoG): 2 Jahre
  - Lohnunterlagen (Steuerrecht AO): 10 Jahre

Die App löscht keine Daten automatisch.
Mitarbeiter sehen nur die letzten 60 Tage.
Admin/Buchhaltung kann alle Monate abrufen.


PASSWORT VERGESSEN
-------------------
Mitarbeiter können über "Passwort vergessen?" auf der
Login-Seite einen Reset-Link anfordern (gültig 1 Stunde).
Der Link wird an die hinterlegte E-Mail-Adresse gesendet.

Alternativ: Admin setzt Passwort manuell zurück unter
Mitarbeiter → PW setzen.


SICHERHEITSHINWEISE
---------------------
✓ Ausschließlich HTTPS verwenden
✓ install.php nach Installation löschen
✓ Starke Passwörter verwenden (mind. 8 Zeichen)
✓ Regelmäßige Datensicherung der MySQL-Datenbank empfohlen
  (IONOS → Datenbanken → Backup erstellen)

============================================================
