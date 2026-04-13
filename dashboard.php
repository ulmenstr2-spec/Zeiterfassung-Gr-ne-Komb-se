<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

switch (currentRole()) {
    case 'admin':
    case 'buchhaltung':
        header('Location: ' . BASE_URL . '/admin_uebersicht.php');
        break;
    default:
        header('Location: ' . BASE_URL . '/meine_schichten.php');
        break;
}
exit;
