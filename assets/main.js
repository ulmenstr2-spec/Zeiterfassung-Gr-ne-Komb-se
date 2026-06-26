/**
 * Zeiterfassung Grüne Kombüse – main.js
 */

// ----------------------------------------------------------------
// Netto-Arbeitszeit live berechnen (schicht_eintragen.php)
// ----------------------------------------------------------------
(function () {
    var bH      = document.getElementById('beginn_h');
    var bM      = document.getElementById('beginn_m');
    var eH      = document.getElementById('ende_h');
    var eM      = document.getElementById('ende_m');
    var pause   = document.getElementById('pause_minuten');
    var preview = document.getElementById('nettoPreview');
    var wert    = document.getElementById('nettoWert');

    if (!bH || !bM || !eH || !eM || !preview || !wert) return;

    function updatePreview() {
        if (!bH.value || !bM.value || !eH.value || !eM.value) {
            preview.style.display = 'none';
            return;
        }
        var bMin   = parseInt(bH.value, 10) * 60 + parseInt(bM.value, 10);
        var eMin   = parseInt(eH.value, 10) * 60 + parseInt(eM.value, 10);
        var pauseM = pause ? (parseInt(pause.value, 10) || 0) : 0;
        var nettoM = eMin - bMin - pauseM;

        preview.style.display = 'block';

        if (nettoM <= 0) {
            wert.textContent = '– (Endzeit prüfen)';
            wert.style.color = '#c62828';
        } else {
            var h = Math.floor(nettoM / 60);
            var m = nettoM % 60;
            wert.textContent = h + ':' + (m < 10 ? '0' : '') + m + ' h';
            wert.style.color = '#1b5e20';
        }
    }

    bH.addEventListener('change', updatePreview);
    bM.addEventListener('change', updatePreview);
    eH.addEventListener('change', updatePreview);
    eM.addEventListener('change', updatePreview);
    if (pause) { pause.addEventListener('input', updatePreview); }
    updatePreview();
}());

// ----------------------------------------------------------------
// Passwort-Reset-Bestätigung in admin_mitarbeiter.php
// ----------------------------------------------------------------
function pwReset(form, name) {
    var pw = form.querySelector('input[name="neues_passwort"]');
    if (!pw || pw.value.length < 8) {
        alert('Bitte ein Passwort mit mindestens 8 Zeichen eingeben.');
        if (pw) pw.focus();
        return false;
    }
    return confirm('Passwort für ' + name + ' wirklich zurücksetzen?');
}
