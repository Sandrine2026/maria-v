<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';

// Seuls les gestionnaires et les super-administrateurs
// peuvent modifier les vérifications.
$peutTraiter = in_array(
    $adminConnecte['role'],
    ['gestionnaire', 'super_admin'],
    true
);

$message = '';
$erreur = '';
$dossiers = [];

// Traitement des actions envoyées par formulaire.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole(['gestionnaire', 'super_admin']);

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !verifierJetonCSRF($token)) {
        $erreur = "La session de sécurité a expiré. Recharge la page.";
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $action = $_POST['action'] ?? '';
        $motifBrut = $_POST['motif_refus'] ?? '';

        $motif = is_string($motifBrut) ? trim($motifBrut) : '';

        $actionsAutorisees = ['en_cours', 'valider', 'refuser'];

        if (!$id || $id < 1 ||
            !is_string($action) ||
            !in_array($action, $actionsAutorisees, true)) {
            $erreur = "Les informations envoyées sont invalides.";
        } elseif ($action === 'refuser' && $motif === '') {
            $erreur = "Indique le motif du refus.";
        } elseif ($action === 'refuser' && mb_strlen($motif) > 2000) {
            $erreur = "Le motif du refus ne doit pas dépasser 2 000 caractères.";
        } else {
            try {
                if ($action === 'en_cours') {
                    $requete = $pdo->prepare("
                        UPDATE verifications
                        SET statut = 'en_cours'
                        WHERE id = :id
                          AND statut = 'reçue'
                    ");

                    $requete->execute(['id' => $id]);

                } elseif ($action === 'valider') {
                    // Récupérer les coordonnées avant la validation afin d'envoyer
                    // une notification lorsque l'équipe confirme l'éligibilité.
                    $infoNotification = $pdo->prepare("SELECT v.numero_dossier, d.nom, d.prenom, d.email FROM verifications v INNER JOIN demandeurs d ON d.id = v.demandeur_id WHERE v.id = :id LIMIT 1");
                    $infoNotification->execute(['id' => $id]);
                    $destinataire = $infoNotification->fetch(PDO::FETCH_ASSOC);

                    $requete = $pdo->prepare("
                        UPDATE verifications
                        SET statut = 'validee',
                            resultat = 'eligible',
                            date_traitement = NOW(),
                            motif_refus = NULL
                        WHERE id = :id
                          AND statut IN ('reçue', 'en_cours')
                    ");

                    $requete->execute(['id' => $id]);

                } else {
                    $requete = $pdo->prepare("
                        UPDATE verifications
                        SET statut = 'refusee',
                            resultat = 'non_eligible',
                            date_traitement = NOW(),
                            motif_refus = :motif
                        WHERE id = :id
                          AND statut IN ('reçue', 'en_cours')
                    ");

                    $requete->execute([
                        'motif' => $motif,
                        'id' => $id,
                    ]);
                }

                if ($requete->rowCount() > 0) {
                    $message = "Le dossier a été mis à jour.";
                    if ($action === 'valider' && !empty($destinataire['email']) && filter_var($destinataire['email'], FILTER_VALIDATE_EMAIL)) {
                        $sujet = 'MARIA-V : votre dossier est éligible';
                        $corps = "Bonjour " . ($destinataire['prenom'] ?? '') . " " . ($destinataire['nom'] ?? '') . ",\r\n\r\n";
                        $corps .= "Après examen par l'équipe MARIA-V, votre dossier a été déclaré éligible.\r\n";
                        $corps .= "Votre numéro de dossier : " . ($destinataire['numero_dossier'] ?? '') . "\r\n\r\n";
                        $corps .= "Vous pouvez maintenant déposer votre demande d'aide depuis le site MARIA-V, en utilisant votre numéro de dossier et la même adresse e-mail.\r\n\r\nL'équipe MARIA-V";
                        $entetes = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                        $entetes .= "From: MARIA-V <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\n";
                        if (!@mail($destinataire['email'], '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps, $entetes)) {
                            error_log('MARIA-V : notification d’éligibilité non envoyée; configurer le serveur mail PHP.');
                            error_log('MARIA-V : échec d’envoi du courriel de notification; vérifier la configuration SMTP/PHP mail.');
                        } else {
                            // Le résultat est communiqué au demandeur par e-mail; le panneau admin reste centré sur le traitement du dossier.

                        }
                    }
                } else {
                    $erreur = "Aucune modification effectuée. Le dossier existe peut-être déjà dans ce statut ou n'est plus modifiable.";
                }

            } catch (PDOException $e) {
                error_log(
                    'Erreur de mise à jour des vérifications : '
                    . $e->getMessage()
                );

                $erreur = "Une erreur est survenue lors de la mise à jour.";
            }
        }
    }
}

// Lire et valider le filtre.
$statutsAutorises = [
    'tous',
    'reçue',
    'en_cours',
    'validee',
    'refusee',
];

$filtreBrut = $_GET['statut'] ?? 'tous';

$filtre = is_string($filtreBrut)
    && in_array($filtreBrut, $statutsAutorises, true)
        ? $filtreBrut
        : 'tous';

// Récupérer les dossiers.
try {
    $sql = "
        SELECT
            v.id,
            v.numero_dossier,
            v.statut,
            v.resultat,
            v.motif_refus,
            v.date_creation,
            v.date_traitement,
            v.fichier_identite,
            d.nom,
            d.prenom,
            d.email,
            d.telephone
        FROM verifications v
        INNER JOIN demandeurs d ON d.id = v.demandeur_id
    ";

    if ($filtre !== 'tous') {
        $sql .= " WHERE v.statut = :statut";
    }

    $sql .= " ORDER BY v.date_creation DESC, v.id DESC";

    $requete = $pdo->prepare($sql);

    if ($filtre !== 'tous') {
        $requete->execute(['statut' => $filtre]);
    } else {
        $requete->execute();
    }

    $dossiers = $requete->fetchAll();

} catch (PDOException $e) {
    error_log(
        'Erreur de récupération des vérifications : '
        . $e->getMessage()
    );

    $erreur = "Impossible de récupérer les dossiers pour le moment.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vérifications d'identité | MARIA-V</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="admin-header">
    <div>
        <h1>MARIA-V</h1>
        <p>Gestion des vérifications d'identité</p>
    </div>

    <div>
        <a href="dashboard.php">Tableau de bord</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</header>

<main class="admin-dashboard">

    <section class="admin-welcome">
        <h2>Vérifications d'identité</h2>

        <p>
            Consulte les dossiers et enregistre les décisions de vérification.
        </p>

        <?php if (!$peutTraiter): ?>
            <p>
                Votre compte permet la consultation, mais pas la modification
                des dossiers.
            </p>
        <?php endif; ?>
    </section>

    <?php if ($message !== ''): ?>
        <p class="notice-success" role="status">
            <?= e($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($erreur !== ''): ?>
        <p class="notice-error" role="alert">
            <?= e($erreur) ?>
        </p>
    <?php endif; ?>

    <form method="GET" action="verifications.php"
          class="verification-filter">

        <label for="statut">Filtrer par statut :</label>

        <select name="statut" id="statut">
            <option value="tous"
                <?= $filtre === 'tous' ? 'selected' : '' ?>>
                Tous les dossiers
            </option>

            <option value="reçue"
                <?= $filtre === 'reçue' ? 'selected' : '' ?>>
                Reçus
            </option>

            <option value="en_cours"
                <?= $filtre === 'en_cours' ? 'selected' : '' ?>>
                En cours
            </option>

            <option value="validee"
                <?= $filtre === 'validee' ? 'selected' : '' ?>>
                Vérifiés
            </option>

            <option value="refusee"
                <?= $filtre === 'refusee' ? 'selected' : '' ?>>
                Refusés
            </option>
        </select>

        <button type="submit">Filtrer</button>
    </form>

    <section class="admin-table-section">

        <h2>Liste des dossiers (<?= count($dossiers) ?>)</h2>

        <?php if (empty($dossiers)): ?>

            <p>Aucun dossier trouvé pour ce filtre.</p>

        <?php else: ?>

            <div class="verification-list">

                <?php foreach ($dossiers as $dossier): ?>

                    <article class="verification-card">

                        <div class="verification-card-heading">
                            <h3>
                                <?= e($dossier['numero_dossier']) ?>
                            </h3>

                            <span class="verification-status">
                                <?= e($dossier['statut']) ?>
                            </span>
                        </div>

                        <p>
                            <strong>Demandeur :</strong>
                            <?= e($dossier['prenom']) ?>
                            <?= e($dossier['nom']) ?>
                        </p>

                        <p>
                            <strong>E-mail :</strong>
                            <?= e($dossier['email']) ?>
                        </p>

                        <p>
                            <strong>Téléphone :</strong>
                            <?= e($dossier['telephone']) ?>
                        </p>

                        <p>
                            <strong>Reçu le :</strong>
                            <?= e($dossier['date_creation']) ?>
                        </p>

                        <p>
                            <strong>Résultat d'éligibilité :</strong>
                            <?= e($dossier['resultat'] ?: 'En attente') ?>
                        </p>

                        <?php if (!empty($dossier['date_traitement'])): ?>
                            <p>
                                <strong>Traité le :</strong>
                                <?= e($dossier['date_traitement']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($dossier['motif_refus'])): ?>
                            <p>
                                <strong>Motif du refus :</strong>
                                <?= e($dossier['motif_refus']) ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($dossier['fichier_identite'])): ?>
                            <p>
                                <a
                                    href="voir-identite.php?id=<?= (int) $dossier['id'] ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Consulter le document d'identité
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if (
                            $peutTraiter
                            && in_array(
                                $dossier['statut'],
                                ['reçue', 'en_cours'],
                                true
                            )
                        ): ?>

                            <form method="POST" action="verifications.php"
                                  class="verification-actions">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_global']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $dossier['id'] ?>"
                                >

                                <?php if ($dossier['statut'] === 'reçue'): ?>
                                    <button
                                        type="submit"
                                        name="action"
                                        value="en_cours"
                                    >
                                        Commencer l'examen
                                    </button>
                                <?php endif; ?>

                                <button
                                    type="submit"
                                    name="action"
                                    value="valider"
                                    onclick="return confirm('Confirmer la validation de cette identité ?')"
                                >
                                    Valider l'identité
                                </button>

                                <label for="motif-<?= (int) $dossier['id'] ?>">
                                    Motif du refus (obligatoire pour refuser)
                                </label>

                                <textarea
                                    id="motif-<?= (int) $dossier['id'] ?>"
                                    name="motif_refus"
                                    rows="2"
                                    maxlength="2000"
                                    placeholder="Explique le motif du refus"
                                ></textarea>

                                <button
                                    type="submit"
                                    name="action"
                                    value="refuser"
                                    onclick="return confirm('Confirmer le refus de cette vérification ?')"
                                >
                                    Refuser
                                </button>

                            </form>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
