<?php
/**
 * update_db.php – Einmalige DB-Migration
 * Fuegt die Spalte "notiz" zur shifts-Tabelle hinzu, falls noch nicht vorhanden.
 * Nach dem Ausfuehren kann diese Datei geloescht werden.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireRole('admin');

header('Content-Type: text/plain; charset=UTF-8');

$pdo = getPDO();

$cols = $pdo->query("SHOW COLUMNS FROM shifts LIKE 'notiz'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE shifts ADD COLUMN notiz TEXT NULL DEFAULT NULL AFTER pause_minuten");
    echo "OK: Spalte 'notiz' wurde zur Tabelle 'shifts' hinzugefuegt.\n";
} else {
    echo "Spalte 'notiz' existiert bereits - nichts zu tun.\n";
}

echo "Danach bitte loeschen!\n";
