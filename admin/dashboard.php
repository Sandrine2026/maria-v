<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';

// La connexion à la base de données et la vérification
// de la session sont déjà assurées par securite.php.

$totalVerifications = 0;
$verificationsEnAttente = 0;
$totalDemandes = 0;
$demandesEnAttente = 0;
$dernieresVerifications = [];
$erreur = null;

try {
    // Nombre total de vérifications d'identité.
    $totalVerifications = (int) $pdo
        ->query("SELECT COUNT(*) FROM verifications")
        ->fetchColumn();

    // Vérifications reçues et non encore traitées.
    $verificationsEnAttente = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM verifications
            WHERE statut = 'reçue'
        ")
        ->fetchColumn();

    // Nombre total de demandes d'aide.
    $totalDemandes = (int) $pdo
        ->query("SELECT COUNT(*) FROM demandes_aide")
        ->fetchColumn();

    // Nouvelles demandes d'aide à examiner.
    $demandesEnAttente = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM demandes_aide
            WHERE statut = 'reçue'
        ")
        ->fetchColumn();

    // Les dix vérifications les plus récentes.
    $requete = $pdo->query("
        SELECT
            v.numero_dossier,
            v.statut,
            v.date_creation,
            d.nom,
            d.prenom
        FROM verifications v
        INNER JOIN demandeurs d ON d.id = v.demandeur_id
        ORDER BY v.date_creation DESC, v.id DESC
        LIMIT 10
    ");

    $dernieresVerifications = $requete->fetchAll();

} catch (PDOException $e) {
    error_log('Erreur admin/dashboard.php : ' . $e->getMessage());

    $erreur = "Impossible de charger les statistiques pour le moment.";
}

// Vérifier le rôle de l'administrateur connecté.
$roleAdmin = $adminConnecte['role'] ?? 'lecteur';

$peutGerer = in_array(
    $roleAdmin,
    ['gestionnaire', 'super_admin'],
    true
);

$estSuperAdmin = $roleAdmin === 'super_admin';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tableau de bord | MARIA-V</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="admin-header">
    <div>
        <h1>MARIA-V</h1>
        <p>Administration</p>
    </div>

    <div>
        <span>
            Bonjour,
            <?= e($adminConnecte['nom'] ?? 'Administrateur') ?>
        </span>

        <a href="logout.php" class="btn btn-outline">
            Déconnexion
        </a>
    </div>
</header>

<main class="admin-dashboard">

    <section class="admin-welcome">
        <h2>Tableau de bord</h2>

        <p>
            Bienvenue dans l'espace d'administration de la fondation MARIA-V.
        </p>

        <p>
            Votre rôle :
            <strong><?= e($roleAdmin) ?></strong>
        </p>
    </section>

    <?php if ($erreur !== null): ?>

        <section class="admin-table-section">
            <p role="alert"><?= e($erreur) ?></p>
        </section>

    <?php else: ?>

        <section class="admin-stats">

            <article class="stat-card">
                <h3>Vérifications d'identité</h3>
                <p class="stat-number">
                    <?= $totalVerifications ?>
                </p>
                <p>Total des dossiers reçus</p>
            </article>

            <article class="stat-card">
                <h3>Vérifications à traiter</h3>
                <p class="stat-number">
                    <?= $verificationsEnAttente ?>
                </p>
                <p>Dossiers ayant le statut « reçue »</p>
            </article>

            <article class="stat-card">
                <h3>Demandes d'aide</h3>
                <p class="stat-number">
                    <?= $totalDemandes ?>
                </p>
                <p>Total des demandes enregistrées</p>
            </article>

            <article class="stat-card">
                <h3>Demandes à examiner</h3>
                <p class="stat-number">
                    <?= $demandesEnAttente ?>
                </p>
                <p>Nouvelles demandes ayant le statut « reçue »</p>
            </article>

        </section>

    <?php endif; ?>

    <section class="admin-menu">
        <h2>Gestion de la plateforme</h2>

        <div class="admin-links">

            <?php if ($peutGerer): ?>

                <a href="verifications.php">
                    Vérifications d'identité
                </a>

                <a href="demandes.php">
                    Demandes d'aide
                </a>

                <a href="contrats.php">
                    Contrats et apports
                </a>

                <a href="demandeurs.php">
                    Demandeurs
                </a>

                <a href="articles.php">
                    Actualités et contenus
                </a>

                <a href="contacts.php">
                    Messages de contact
                </a>

            <?php endif; ?>

            <?php if ($estSuperAdmin): ?>

                <a href="admins.php">
                    Administrateurs
                </a>

            <?php endif; ?>

            <?php if (!$peutGerer && !$estSuperAdmin): ?>

                <p>
                    Votre rôle ne permet pas de gérer ces éléments.
                    Contactez un administrateur autorisé si nécessaire.
                </p>

            <?php endif; ?>

        </div>
    </section>

    <section class="admin-table-section">
        <h2>Dernières vérifications d'identité</h2>

        <?php if ($erreur !== null): ?>

            <p>Les dernières vérifications ne sont pas disponibles.</p>

        <?php elseif (empty($dernieresVerifications)): ?>

            <p>
                Aucune vérification enregistrée pour le moment.
            </p>

        <?php else: ?>

            <div class="table-responsive">
                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Numéro de dossier</th>
                            <th>Demandeur</th>
                            <th>Date de réception</th>
                            <th>Statut</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($dernieresVerifications as $verification): ?>

                            <tr>
                                <td>
                                    <?= e($verification['numero_dossier']) ?>
                                </td>

                                <td>
                                    <?= e($verification['prenom']) ?>
                                    <?= e($verification['nom']) ?>
                                </td>

                                <td>
                                    <?= e($verification['date_creation']) ?>
                                </td>

                                <td>
                                    <?= e($verification['statut']) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

        <?php endif; ?>
    </section>

</main>

</body>
</html>
