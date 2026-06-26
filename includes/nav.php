<?php
// Navigation – wird auf jeder geschützten Seite eingebunden
// Setzt voraus: requireLogin() wurde bereits aufgerufen
?>
<nav class="navbar">
    <div class="nav-inner">
        <a href="<?= h(BASE_URL) ?>/dashboard.php" class="nav-brand"><?= h(APP_NAME) ?></a>
        <div class="nav-links">
            <?php if (in_array(currentRole(), ['admin', 'buchhaltung'], true)): ?>
                <a href="<?= h(BASE_URL) ?>/admin_uebersicht.php">&#220;bersicht</a>
                <a href="<?= h(BASE_URL) ?>/admin_export.php">CSV-Export</a>
            <?php endif; ?>
            <?php if (currentRole() === 'admin'): ?>
                <a href="<?= h(BASE_URL) ?>/admin_log.php">&#196;nderungslog</a>
                <a href="<?= h(BASE_URL) ?>/admin_mitarbeiter.php">Mitarbeiter</a>
            <?php endif; ?>
            <?php if (currentRole() !== 'buchhaltung'): ?>
                <a href="<?= h(BASE_URL) ?>/schicht_eintragen.php">Schicht eintragen</a>
            <?php endif; ?>
            <?php if (currentRole() === 'mitarbeiter'): ?>
                <a href="<?= h(BASE_URL) ?>/meine_schichten.php">Meine Schichten</a>
            <?php endif; ?>
            <a href="<?= h(BASE_URL) ?>/passwort_aendern.php">Passwort &#228;ndern</a>
            <span class="nav-user"><?= h(currentUserName()) ?> <span class="nav-role">(<?= h(currentRole()) ?>)</span></span>
            <a href="<?= h(BASE_URL) ?>/logout.php" class="btn-logout">Abmelden</a>
        </div>
    </div>
</nav>
