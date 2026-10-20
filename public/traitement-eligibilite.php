<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php'; 

/**
 * Affiche un message d'erreur puis arrête le traitement.
 */
function afficherErreur(string $message, int $code = 400): void
{
    http_response_code($code);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Vérification du dossier | MARIA-V</title>
        <link rel="stylesheet" href="/maria-v/assets/css/style.css">
    </head>
    <body>
        <main class="section">
            <div class="container">
                <h1>Impossible de soumettre le dossier</h1>
                <p><?= e($message) ?></p>
                <p>
                    <a class="btn btn-primary" href="eligibility.php">
                        Retour au formulaire
                    </a>
                </p>
            </div>
        </main>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| 1. Vérifier la méthode HTTP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    afficherErreur('Méthode de soumission non autorisée.', 405);
}

/*
|--------------------------------------------------------------------------
| 2. Vérifier le jeton CSRF
|--------------------------------------------------------------------------
*/

$jetonRecu = $_POST['csrf_token'] ?? '';
$jetonSession = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($jetonRecu) ||
    !is_string($jetonSession) ||
    $jetonSession === '' ||
    !hash_equals($jetonSession, $jetonRecu)
) {
    afficherErreur(
        'La session du formulaire a expiré. Rechargez la page et réessayez.',
        403
    );
}

/*
|--------------------------------------------------------------------------
| 3. Vérifier les champs du formulaire
|--------------------------------------------------------------------------
*/

$champs = ['nom', 'prenom', 'email', 'telephone'];

foreach ($champs as $champ) {
    if (!isset($_POST[$champ]) || !is_string($_POST[$champ])) {
        afficherErreur('Les informations du formulaire sont invalides.');
    }
}

$nom = trim($_POST['nom']);
$prenom = trim($_POST['prenom']);
$email = trim($_POST['email']);
$telephone = trim($_POST['telephone']);

if (
    $nom === '' ||
    $prenom === '' ||
    $email === '' ||
    $telephone === ''
) {
    afficherErreur('Veuillez remplir tous les champs obligatoires.');
}

if (
    mb_strlen($nom, 'UTF-8') > 100 ||
    mb_strlen($prenom, 'UTF-8') > 100 ||
    mb_strlen($email, 'UTF-8') > 150 ||
    mb_strlen($telephone, 'UTF-8') > 30
) {
    afficherErreur(
        'Une ou plusieurs informations dépassent la longueur autorisée.'
    );
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    afficherErreur('Veuillez saisir une adresse e-mail valide.');
}

/*
|--------------------------------------------------------------------------
| 4. Vérifier le consentement
|--------------------------------------------------------------------------
*/

$consentement = $_POST['consentement'] ?? '';

if (!is_string($consentement) || $consentement !== '1') {
    afficherErreur(
        'Vous devez accepter la politique de confidentialité.'
    );
}

/*
|--------------------------------------------------------------------------
| 5. Vérifier le document d'identité
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES['document_identite']) ||
    !is_array($_FILES['document_identite'])
) {
    afficherErreur('Veuillez sélectionner un document d’identité.');
}

$fichier = $_FILES['document_identite'];

foreach (['error', 'size', 'tmp_name'] as $cle) {
    if (!isset($fichier[$cle]) || !is_scalar($fichier[$cle])) {
        afficherErreur('Les informations du fichier transmis sont invalides.');
    }
}

$erreurUpload = filter_var(
    $fichier['error'],
    FILTER_VALIDATE_INT
);

$tailleFichier = filter_var(
    $fichier['size'],
    FILTER_VALIDATE_INT
);

$fichierTemporaire = $fichier['tmp_name'];

if ($erreurUpload === false || $tailleFichier === false) {
    afficherErreur('Les informations du fichier sont invalides.');
}

if ($erreurUpload !== UPLOAD_ERR_OK) {
    if ($erreurUpload === UPLOAD_ERR_INI_SIZE ||
        $erreurUpload === UPLOAD_ERR_FORM_SIZE) {
        afficherErreur('Le document dépasse la taille autorisée de 5 Mo.');
    }

    afficherErreur(
        'Le document n’a pas pu être reçu. Vérifiez le fichier et réessayez.'
    );
}

$tailleMax = 5 * 1024 * 1024;

if ($tailleFichier <= 0 || $tailleFichier > $tailleMax) {
    afficherErreur('Le document doit avoir une taille maximale de 5 Mo.');
}

if (
    !is_string($fichierTemporaire) ||
    $fichierTemporaire === '' ||
    !is_uploaded_file($fichierTemporaire)
) {
    afficherErreur('Le fichier transmis est invalide.');
}

/*
 * Détection du type MIME réel.
 * L'extension fournie par le navigateur n'est pas utilisée.
 */

if (!class_exists('finfo')) {
    error_log('MARIA-V : extension Fileinfo indisponible.');
    afficherErreur(
        'La vérification des documents est momentanément indisponible.',
        500
    );
}

$mimeDetecteur = new finfo(FILEINFO_MIME_TYPE);
$typeMime = $mimeDetecteur->file($fichierTemporaire);

$typesAutorises = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (
    !is_string($typeMime) ||
    !isset($typesAutorises[$typeMime])
) {
    afficherErreur(
        'Format non accepté. Utilisez un fichier PDF, JPG ou PNG.'
    );
}

/*
|--------------------------------------------------------------------------
| 6. Préparer le stockage sécurisé
|--------------------------------------------------------------------------
*/

$dossierStockage = dirname(__DIR__) . '/uploads/identity';

$dossierReel = realpath($dossierStockage);

if (
    $dossierReel === false ||
    !is_dir($dossierReel) ||
    !is_writable($dossierReel)
) {
    error_log('MARIA-V : dossier de stockage absent ou inaccessible.');

    afficherErreur(
        'Le stockage des documents est indisponible. Veuillez réessayer plus tard.',
        500
    );
}

$nomStocke = bin2hex(random_bytes(24))
    . '.'
    . $typesAutorises[$typeMime];

$cheminComplet = $dossierReel
    . DIRECTORY_SEPARATOR
    . $nomStocke;

$fichierDeplace = false;

/*
|--------------------------------------------------------------------------
| 7. Enregistrer le demandeur et sa vérification
|--------------------------------------------------------------------------
*/

try {
    if (!move_uploaded_file($fichierTemporaire, $cheminComplet)) {
        throw new RuntimeException(
            'Le déplacement du document vers le stockage a échoué.'
        );
    }

    $fichierDeplace = true;

    $pdo->beginTransaction();

    // Enregistrement du demandeur.
    $requeteDemandeur = $pdo->prepare(
        'INSERT INTO demandeurs
            (nom, prenom, email, telephone)
         VALUES
            (:nom, :prenom, :email, :telephone)'
    );

    $requeteDemandeur->execute([
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'telephone' => $telephone
    ]);

    $demandeurId = (int) $pdo->lastInsertId();

    // Génération du numéro de dossier.
    $numeroDossier = genererNumeroSuivi('MARIA');

    // Enregistrement de la vérification.
    $requeteVerification = $pdo->prepare(
        'INSERT INTO verifications
            (
                numero_dossier,
                demandeur_id,
                fichier_identite,
                statut,
                resultat
            )
         VALUES
            (
                :numero,
                :demandeur_id,
                :fichier,
                :statut,
                :resultat
            )'
    );

    $requeteVerification->execute([
        'numero' => $numeroDossier,
        'demandeur_id' => $demandeurId,
        'fichier' => $nomStocke,
        'statut' => 'reçue',
        'resultat' => 'en_attente'
    ]);

    $pdo->commit();

    // Renouveler le jeton après une soumission réussie.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Supprimer le document si l'enregistrement a échoué.
    if ($fichierDeplace && is_file($cheminComplet)) {
        if (!unlink($cheminComplet)) {
            error_log(
                'MARIA-V : impossible de supprimer un document après échec.'
            );
        }
    }

    error_log(
        'MARIA-V : erreur de soumission : ' . $e->getMessage()
    );

    afficherErreur(
        'Une erreur technique empêche actuellement l’enregistrement. Veuillez réessayer plus tard.',
        500
    );
}

/*
|--------------------------------------------------------------------------
| 8. Afficher la confirmation
|--------------------------------------------------------------------------
*/
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dossier reçu | MARIA-V</title>
    <link rel="stylesheet" href="/maria-v/assets/css/style.css">
</head>
<body>

<main class="section">
    <div class="container" style="max-width: 720px; text-align: center;">

        <p class="surtitre">Fondation MARIA-V</p>

        <h1 style="color: #183d32;">
            Votre dossier a bien été reçu
        </h1>

        <p>
            Vos informations ont été enregistrées.
            Votre dossier est en attente d’examen par la fondation.
        </p>

        <div style="margin: 30px 0; padding: 25px; background: #ffffff; border-radius: 10px;">

            <p>Votre numéro de dossier :</p>

            <h2 style="color: #183d32; margin: 12px 0;">
                <?= e($numeroDossier) ?>
            </h2>

            <p>
                Conservez ce numéro pour pouvoir retrouver votre dossier.
            </p>

            <button
                type="button"
                class="btn btn-primary"
                onclick="copierNumero()"
            >
                Copier le numéro
            </button>

            <p id="message-copie" role="status" aria-live="polite"></p>

        </div>

        <p>
            Cette confirmation ne signifie pas que votre éligibilité
            est validée ni qu’une aide a été accordée.
        </p>

        <p style="margin-top: 25px;">
            <a href="/maria-v/index.php" class="btn btn-primary">
                Retour à l’accueil
            </a>
        </p>

        <p style="margin-top: 15px;">
            <a href="tracking.php">
                Consulter le suivi de mon dossier
            </a>
        </p>

    </div>
</main>

<script>
function copierNumero() {
    const numero = <?= json_encode(
        $numeroDossier,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const message = document.getElementById('message-copie');

    if (!navigator.clipboard || !window.isSecureContext) {
        message.textContent =
            'Veuillez sélectionner et copier le numéro manuellement : ' + numero;
        return;
    }

    navigator.clipboard.writeText(numero)
        .then(() => {
            message.textContent = 'Numéro de dossier copié.';
        })
        .catch(() => {
            message.textContent =
                'Copie impossible. Veuillez copier le numéro manuellement.';
        });
}
</script>

</body>
</html>
