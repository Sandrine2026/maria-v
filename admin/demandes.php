<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$erreur = '';
$demandes = [];

$statuts = [
    'reçue' => 'Reçue',
    'en_cours_examen' => 'En cours d’examen',
    'complement_demande' => 'Complément demandé',
    'acceptee' => 'Acceptée',
    'refusee' => 'Refusée',
];

// Traitement de la modification d'une demande.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole(['gestionnaire', 'super_admin']);

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !verifierJetonCSRF($token)) {
        $erreur = "Jeton de sécurité invalide. Recharge la page.";
    } else {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $nouveauStatut = $_POST['statut'] ?? '';
        $commentaireBrut = $_POST['commentaire'] ?? '';

        $commentaire = is_string($commentaireBrut)
            ? trim($commentaireBrut)
            : '';

        if (
            !$id || $id < 1 ||
            !is_string($nouveauStatut) ||
            !array_key_exists($nouveauStatut, $statuts)
        ) {
            $erreur = "Les informations envoyées sont invalides.";
        } elseif (
            $nouveauStatut === 'complement_demande'
            && $commentaire === ''
        ) {
            $erreur = "Précise les informations complémentaires à demander.";
        } elseif (
            $nouveauStatut === 'refusee'
            && $commentaire === ''
        ) {
            $erreur = "Indique le motif du refus.";
        } elseif (mb_strlen($commentaire) > 2000) {
            $erreur = "Le commentaire ne doit pas dépasser 2 000 caractères.";
        } else {
            try {
                $pdo->beginTransaction();

                // Verrouiller la demande pendant le traitement.
                $verification = $pdo->prepare("
                    SELECT id, statut
                    FROM demandes_aide
                    WHERE id = :id
                    FOR UPDATE
                ");

                $verification->execute(['id' => $id]);
                $demandeExistante = $verification->fetch();

                if (!$demandeExistante) {
                    throw new RuntimeException('Demande introuvable.');
                }

                $ancienStatut = $demandeExistante['statut'];

                if (!array_key_exists($ancienStatut, $statuts)) {
                    throw new RuntimeException(
                        'Le statut actuel de cette demande est inconnu.'
                    );
                }

                if ($ancienStatut !== $nouveauStatut) {
                    // Mettre à jour le statut.
                    $maj = $pdo->prepare("
                        UPDATE demandes_aide
                        SET statut = :statut,
                            date_modification = NOW()
                        WHERE id = :id
                    ");

                    $maj->execute([
                        'statut' => $nouveauStatut,
                        'id' => $id,
                    ]);

                    // Enregistrer la modification dans l'historique.
                    $historique = $pdo->prepare("
                        INSERT INTO historique_statuts (
                            demande_id,
                            ancien_statut,
                            nouveau_statut,
                            commentaire,
                            admin_id,
                            date_creation
                        )
                        VALUES (
                            :demande_id,
                            :ancien_statut,
                            :nouveau_statut,
                            :commentaire,
                            :admin_id,
                            NOW()
                        )
                    ");

                    $historique->execute([
                        'demande_id' => $id,
                        'ancien_statut' => $ancienStatut,
                        'nouveau_statut' => $nouveauStatut,
                        'commentaire' => $commentaire !== ''
                            ? $commentaire
                            : null,
                        'admin_id' => (int) $adminConnecte['id'],
                    ]);

                    $message = "Le statut de la demande a été mis à jour.";
                } else {
                    $message = "La demande possède déjà ce statut. Aucune modification n'était nécessaire.";
                }

                $pdo->commit();

            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erreur = $e->getMessage();

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Erreur admin/demandes.php : ' . $e->getMessage()
                );

                $erreur = "Impossible de mettre à jour la demande. Consulte le journal PHP pour le détail technique.";
            }
        }
    }
}

// Récupération et validation des filtres.
$filtreBrut = $_GET['statut'] ?? 'tous';
$rechercheBrute = $_GET['recherche'] ?? '';

$filtre = is_string($filtreBrut)
    && ($filtreBrut === 'tous' || array_key_exists($filtreBrut, $statuts))
        ? $filtreBrut
        : 'tous';

$recherche = is_string($rechercheBrute)
    ? trim($rechercheBrute)
    : '';

// Recherche des demandes.
try {
    $sql = "
        SELECT
            da.id,
            da.numero_suivi,
            da.statut,
            da.type_aide_id,
            da.description,
            da.date_creation,
            da.email,
            da.telephone,
            d.nom,
            d.prenom,
            ta.nom AS type_aide
        FROM demandes_aide da
        INNER JOIN demandeurs d ON d.id = da.demandeur_id
        LEFT JOIN types_aide ta ON ta.id = da.type_aide_id
        WHERE 1 = 1
    ";

    $parametres = [];

    if ($filtre !== 'tous') {
        $sql .= " AND da.statut = :statut";
        $parametres['statut'] = $filtre;
    }

    if ($recherche !== '') {
        $sql .= "
            AND (
                da.numero_suivi LIKE :numero
                OR d.nom LIKE :nom
                OR d.prenom LIKE :prenom
                OR da.email LIKE :email
            )
        ";

        $terme = '%' . $recherche . '%';

        $parametres['numero'] = $terme;
        $parametres['nom'] = $terme;
        $parametres['prenom'] = $terme;
        $parametres['email'] = $terme;
    }

    $sql .= " ORDER BY da.date_creation DESC, da.id DESC";

    $requete = $pdo->prepare($sql);
    $requete->execute($parametres);
    $demandes = $requete->fetchAll();

} catch (PDOException $e) {
    error_log(
        'Erreur de récupération des demandes : ' . $e->getMessage()
    );

    $erreur = "Impossible de récupérer les demandes pour le moment.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Demandes d’aide | MARIA-V</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="admin-header">
    <div>
        <h1>MARIA-V</h1>
        <p>Gestion des demandes d’aide</p>
    </div>

    <div>
        <a href="dashboard.php">Tableau de bord</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</header>

<main class="admin-dashboard">

    <section class="admin-welcome">
        <h2>Demandes d’aide</h2>
        <p>
            Consulte les dossiers et assure le suivi de leur traitement.
        </p>
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

    <form method="GET" action="demandes.php"
          class="verification-filter">

        <label for="recherche">Rechercher :</label>

        <input
            type="search"
            id="recherche"
            name="recherche"
            placeholder="Numéro, nom ou e-mail"
            value="<?= e($recherche) ?>"
        >

        <label for="statut">Statut :</label>

        <select name="statut" id="statut">
            <option value="tous"
                <?= $filtre === 'tous' ? 'selected' : '' ?>>
                Tous les statuts
            </option>

            <?php foreach ($statuts as $code => $libelle): ?>
                <option
                    value="<?= e($code) ?>"
                    <?= $filtre === $code ? 'selected' : '' ?>
                >
                    <?= e($libelle) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Rechercher</button>
    </form>

    <section class="admin-table-section">

        <h2>Résultats : <?= count($demandes) ?> demande(s)</h2>

        <?php if (empty($demandes)): ?>

            <p>Aucune demande ne correspond à ta recherche.</p>

        <?php else: ?>

            <div class="table-responsive">
                <table class="admin-table">

                    <thead>
                        <tr>
                            <th>Numéro de suivi</th>
                            <th>Demandeur</th>
                            <th>Type d’aide</th>
                            <th>Date de dépôt</th>
                            <th>Statut</th>
                            <th>Détails et traitement</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($demandes as $demande): ?>

                            <tr>
                                <td>
                                    <?= e($demande['numero_suivi']) ?>
                                </td>

                                <td>
                                    <?= e($demande['prenom'] . ' ' . $demande['nom']) ?>
                                    <br>
                                    <small><?= e($demande['email']) ?></small>
                                </td>

                                <td>
                                    <?= e($demande['type_aide'] ?? 'Non précisé') ?>
                                </td>

                                <td>
                                    <?= e($demande['date_creation']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $statuts[$demande['statut']]
                                        ?? $demande['statut']
                                    ) ?>
                                </td>

                                <td>
                                    <details>
                                        <summary>Consulter / traiter</summary>

                                        <div class="demande-details">

                                            <p>
                                                <strong>Téléphone :</strong>
                                                <?= e($demande['telephone']) ?>
                                            </p>

                                            <p>
                                                <strong>Description :</strong>
                                                <?= nl2br(e($demande['description'])) ?>
                                            </p>

                                            <?php if (
                                                in_array(
                                                    $adminConnecte['role'],
                                                    ['gestionnaire', 'super_admin'],
                                                    true
                                                )
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="demandes.php"
                                                    class="verification-actions"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_global']) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $demande['id'] ?>"
                                                    >

                                                    <label for="statut-<?= (int) $demande['id'] ?>">
                                                        Nouveau statut
                                                    </label>

                                                    <select
                                                        id="statut-<?= (int) $demande['id'] ?>"
                                                        name="statut"
                                                        required
                                                    >
                                                        <?php foreach ($statuts as $code => $libelle): ?>
                                                            <option
                                                                value="<?= e($code) ?>"
                                                                <?= $demande['statut'] === $code ? 'selected' : '' ?>
                                                            >
                                                                <?= e($libelle) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <label for="commentaire-<?= (int) $demande['id'] ?>">
                                                        Commentaire ou motif
                                                    </label>

                                                    <textarea
                                                        id="commentaire-<?= (int) $demande['id'] ?>"
                                                        name="commentaire"
                                                        rows="3"
                                                        maxlength="2000"
                                                        placeholder="Motif du refus ou informations complémentaires à demander"
                                                    ></textarea>

                                                    <button
                                                        type="submit"
                                                        onclick="return confirm('Confirmer la modification du statut ?')"
                                                    >
                                                        Enregistrer le statut
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>
                                    </details>
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