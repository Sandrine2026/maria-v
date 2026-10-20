<?php
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../config/database.php';

// Seuls les gestionnaires et super administrateurs
// peuvent consulter les documents d'identité.
exigerRole(['gestionnaire', 'super_admin']);

// Vérifier que l'identifiant du dossier est valide.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Identifiant invalide.');
}

// Récupérer le document dans la base de données.
$stmt = $pdo->prepare(
    "SELECT fichier_identite
     FROM verifications
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute(['id' => $id]);
$verification = $stmt->fetch();

if (!$verification || empty($verification['fichier_identite'])) {
    http_response_code(404);
    exit('Document introuvable.');
}

// Le nom enregistré doit être un simple nom de fichier.
$nomFichier = basename($verification['fichier_identite']);

$chemin = realpath(
    __DIR__ . '/../uploads/identity/' . $nomFichier
);

$dossierAutorise = realpath(
    __DIR__ . '/../uploads/identity'
);

// Vérifier que le fichier existe et reste dans le dossier prévu.
if (
    $chemin === false ||
    $dossierAutorise === false ||
    !is_file($chemin) ||
    dirname($chemin) !== $dossierAutorise
) {
    http_response_code(404);
    exit('Document introuvable.');
}

// Déterminer le type MIME réel du fichier.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$typeMime = $finfo->file($chemin);

$typesAutorises = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (!isset($typesAutorises[$typeMime])) {
    http_response_code(415);
    exit('Format de document non autorisé.');
}

// Empêcher la mise en cache du document confidentiel.
header('Content-Type: ' . $typeMime);
header('Content-Disposition: inline; filename="document.' .
    $typesAutorises[$typeMime] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

readfile($chemin);
exit;