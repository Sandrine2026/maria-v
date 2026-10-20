<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';

// Seuls les gestionnaires et les super-administrateurs
// peuvent consulter la liste des demandeurs.
exigerRole(['gestionnaire', 'super_admin']);

// Vérifier et récupérer le terme de recherche.
$rechercheBrute = $_GET['recherche'] ?? '';

$recherche = is_string($rechercheBrute)
    ? trim($rechercheBrute)
    : '';

$demandeurs = [];
$erreur = null;

try {
    // Construire la requête SQL.
    $sql = "
        SELECT
            d.id,
            d.nom,
            d.prenom,
            d.email,
            d.telephone,
            d.date_creation,
            (
                SELECT COUNT(*)
                FROM verifications v
                WHERE v.demandeur_id = d.id
            ) AS nombre_verifications,
            (
                SELECT COUNT(*)
                FROM demandes_aide da
                WHERE da.demandeur_id = d.id
            ) AS nombre_demandes
        FROM demandeurs d
        WHERE 1 = 1
    ";

    $parametres = [];

    // Ajouter les critères de recherche si nécessaire.
    if ($recherche !== '') {
        $sql .= "
            AND (
                d.nom LIKE :nom
                OR d.prenom LIKE :prenom
                OR d.email LIKE :email
                OR d.telephone LIKE :telephone
            )
        ";

        $terme = '%' . $recherche . '%';

        $parametres = [
            'nom' => $terme,
            'prenom' => $terme,
            'email' => $terme,
            'telephone' => $terme,
        ];
    }

    // Afficher les inscriptions les plus récentes en premier.
    $sql .= " ORDER BY d.date_creation DESC, d.id DESC";

    $requete = $pdo->prepare($sql);
    $requete->execute($parametres);

    $demandeurs = $requete->fetchAll();

} catch (PDOException $e) {
    error_log('Erreur admin/demandeurs.php : ' . $e->getMessage());
    $erreur = "Impossible de récupérer la liste des demandeurs pour le moment.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des demandeurs | MARIA-V</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="admin-header">
    <div>
        <h1>MARIA-V</h1>
        <p>Gestion des demandeurs</p>
    </div>

    <div>
        <span>
            Connecté(e) : <?= e($adminConnecte['nom']) ?>
        </span>

        <a href="dashboard.php">Tableau de bord</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</header>

<main class="admin-dashboard">

    <section class="admin-welcome">
        <h2>Liste des demandeurs</h2>

        <p>
            Consulte les personnes enregistrées et retrouve leurs dossiers.
        </p>
    </section>

    <section class="admin-table-section">

        <?php if ($erreur !== null): ?>

            <p role="alert">
                <?= e($erreur) ?>
            </p>

        <?php else: ?>

            <form method="GET" action="demandeurs.php"
                  class="verification-filter">

                <label for="recherche">
                    Rechercher un demandeur :
                </label>

                <input
                    type="search"
                    id="recherche"
                    name="recherche"
                    placeholder="Nom, prénom, e-mail ou téléphone"
                    value="<?= e($recherche) ?>"
                >

                <button type="submit">Rechercher</button>

                <?php if ($recherche !== ''): ?>
                    <a href="demandeurs.php">
                        Effacer la recherche
                    </a>
                <?php endif; ?>

            </form>

            <h3>
                <?= count($demandeurs) ?>
                résultat(s)
            </h3>

            <?php if (empty($demandeurs)): ?>

                <p>Aucun demandeur trouvé.</p>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="admin-table">

                        <thead>
                            <tr>
                                <th>Identifiant</th>
                                <th>Nom complet</th>
                                <th>Coordonnées</th>
                                <th>Date d'inscription</th>
                                <th>Vérifications</th>
                                <th>Demandes d'aide</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($demandeurs as $demandeur): ?>
                                <tr>
                                    <td>
                                        <?= (int) $demandeur['id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($demandeur['prenom']) ?>
                                            <?= e($demandeur['nom']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($demandeur['email']) ?>

                                        <?php if (!empty($demandeur['telephone'])): ?>
                                            <br>
                                            <?= e($demandeur['telephone']) ?>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= e($demandeur['date_creation']) ?>
                                    </td>

                                    <td>
                                        <?= (int) $demandeur['nombre_verifications'] ?>
                                    </td>

                                    <td>
                                        <?= (int) $demandeur['nombre_demandes'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

