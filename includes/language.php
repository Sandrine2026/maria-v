<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$languesDisponibles = ['fr', 'en', 'de', 'es', 'el'];
if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], $languesDisponibles, true)) {
    $_SESSION['langue'] = $_GET['lang'];
}
$langue = $_SESSION['langue'] ?? 'fr';
$fichierLangue = __DIR__ . '/../languages/' . $langue . '.php';
$t = is_file($fichierLangue) ? require $fichierLangue : require __DIR__ . '/../languages/fr.php';
if (!is_array($t)) {
    $t = require __DIR__ . '/../languages/fr.php';
}
