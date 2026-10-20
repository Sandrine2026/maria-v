
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Supprimer les données de la session.
$_SESSION = [];

// Supprimer le cookie de session si les cookies sont utilisés.
if (ini_get('session.use_cookies')) {
    $parametres = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametres['path'],
        $parametres['domain'],
        $parametres['secure'],
        $parametres['httponly']
    );
}

// Détruire la session.
session_destroy();

// Rediriger vers la page de connexion.
header('Location: /maria-v/admin/login.php');
exit;
