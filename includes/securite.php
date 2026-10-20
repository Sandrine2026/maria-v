<?php

// Démarrer la session uniquement si elle n'existe pas.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'administrateur est connecté.
if (!isset($_SESSION['admin_id'])) {
    header('Location: /maria-v/admin/login.php');
    exit;
}

// Charger la connexion à la base de données.
require_once __DIR__ . '/../config/database.php';

// Vérifier que le compte existe toujours et qu'il est actif.
$stmt = $pdo->prepare(
    "SELECT id, nom, email, role, actif
     FROM admins
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute([
    'id' => $_SESSION['admin_id']
]);

$adminConnecte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminConnecte || (int) $adminConnecte['actif'] !== 1) {
    $_SESSION = [];

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

    session_destroy();

    header('Location: /maria-v/admin/login.php');
    exit;
}

// Mettre à jour la session avec les informations vérifiées.
$_SESSION['admin_id'] = (int) $adminConnecte['id'];
$_SESSION['admin_role'] = $adminConnecte['role'];

/**
 * Vérifier si l'administrateur possède l'un des rôles autorisés.
 */
function exigerRole(array $rolesAutorises): void
{
    global $adminConnecte;

    if (!in_array($adminConnecte['role'], $rolesAutorises, true)) {
        http_response_code(403);
        exit(
            "Accès refusé : vous ne disposez pas des autorisations nécessaires."
        );
    }
}

/**
 * Vérifier le jeton CSRF d'un formulaire.
 */
function verifierJetonCSRF(string $jeton): bool
{
    if (empty($_SESSION['csrf_global'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_global'], $jeton);
}

/**
 * Générer un jeton CSRF si nécessaire.
 */
if (empty($_SESSION['csrf_global'])) {
    $_SESSION['csrf_global'] = bin2hex(random_bytes(32));
}