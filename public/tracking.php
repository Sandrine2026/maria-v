
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$titrePage = 'Suivi de dossier';

$numeroSuivi = '';
$email = '';
$demande = null;
$erreur = '';
$erreurTechnique = false;

// Création du jeton CSRF
if (empty($_SESSION['csrf_tracking'])) {
    $_SESSION['csrf_tracking'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $numeroSuivi = trim($_POST['numero_suivi'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jeton = $_POST['csrf_token'] ?? '';

    // Vérification du jeton CSRF
    if (
        !is_string($jeton) ||
        !hash_equals($_SESSION['csrf_tracking'], $jeton)
    ) {
        $erreur = 'Votre session a expiré. Rechargez la page et réessayez.';
    } elseif ($numeroSuivi === '' || $email === '') {
        $erreur = 'Veuillez remplir tous les champs.';
    } elseif (
        strlen($numeroSuivi) > 50 ||
        strlen($email) > 254 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $erreur = 'Les informations saisies sont invalides.';
    } else {
        try {
            $requete = $pdo->prepare(
                'SELECT
                    v.numero_dossier,
                    v.statut AS statut_verification,
                    v.resultat,
                    v.date_creation AS date_verification,
                    d.nom,
                    d.prenom,
                    a.numero_suivi,
                    a.statut AS statut_aide
                 FROM verifications v
                 INNER JOIN demandeurs d
                    ON d.id = v.demandeur_id
                 LEFT JOIN demandes_aide a
                    ON a.verification_id = v.id
                 WHERE (v.numero_dossier = :numero_verification OR a.numero_suivi = :numero_aide)
                   AND LOWER(d.email) = LOWER(:email)
                 LIMIT 1'
            );

            $requete->execute([
                'numero_verification' => $numeroSuivi,
                'numero_aide' => $numeroSuivi,
                'email' => $email
            ]);

            $demande = $requete->fetch();

            if (!$demande) {
                // Message identique pour éviter de révéler
                // si un numéro de dossier existe.
                $erreur = 'Aucun dossier trouvé avec les informations fournies.';
            }
        } catch (PDOException $e) {
            error_log('Erreur de suivi MARIA-V : ' . $e->getMessage());
            $erreur = 'Le suivi est momentanément indisponible. Veuillez réessayer plus tard.';
            $erreurTechnique = true;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main>

    <section class="page-banner">
        <div class="container">
            <p class="surtitre">Fondation MARIA-V</p>
            <h1>Suivi de votre dossier</h1>
            <p>
                Consultez l'état de votre vérification
                à l'aide de vos informations de suivi.
            </p>
        </div>
    </section>

    <section class="tracking-section">
        <div class="container tracking-container">

            <div class="tracking-card">

                <div class="tracking-icon" aria-hidden="true">⌕</div>

                <h2>Où en est votre dossier ?</h2>

                <p>
                    Saisissez le numéro de dossier et l'adresse e-mail
                    utilisés lors de votre demande.
                </p>

                <?php if ($erreur !== ''): ?>
                    <div class="alert-error" role="alert">
                        <?= e($erreur) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="tracking.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_tracking']) ?>"
                    >

                    <div class="form-group">
                        <label for="numero_suivi">
                            Numéro de dossier
                        </label>

                        <input
                            type="text"
                            id="numero_suivi"
                            name="numero_suivi"
                            maxlength="50"
                            value="<?= e($numeroSuivi) ?>"
                            placeholder="Ex. MARIA-20261009-AB12CD34"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">
                            Adresse e-mail utilisée lors de la demande
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="254"
                            value="<?= e($email) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary form-submit"
                    >
                        Consulter mon dossier
                    </button>

                </form>

            </div>

            <?php if ($demande): ?>

                <?php
                $statuts = [
                    'reçue' => 'Dossier reçu',
                    'en_cours' => 'Vérification en cours',
                    'validee' => 'Vérification validée',
                    'refusee' => 'Vérification refusée'
                ];

                $statut = $demande['statut_verification'];
                $libelleStatut = $statuts[$statut] ?? 'Statut en cours de vérification';

                $dateSoumission = 'Non disponible';

                if (!empty($demande['date_verification'])) {
                    $timestamp = strtotime($demande['date_verification']);

                    if ($timestamp !== false) {
                        $dateSoumission = date('d/m/Y', $timestamp);
                    }
                }
                ?>

                <div class="tracking-result">

                    <p class="surtitre">Résultat de la recherche</p>

                    <h2>
                        Bonjour <?= e($demande['prenom']) ?>
                    </h2>

                    <p>
                        Numéro de dossier :
                        <strong><?= e($demande['numero_dossier']) ?></strong>
                    </p>

                    <div class="status-box">
                        <span class="status-label">Statut actuel</span>

                        <strong class="status-value">
                            <?= e($libelleStatut) ?>
                        </strong>
                    </div>

                    <p class="result-detail">
                        <strong>Date de soumission :</strong>
                        <?= e($dateSoumission) ?>
                    </p>

                    <?php if (!empty($demande['numero_suivi'])): ?>
                        <div class="status-box">
                            <span class="status-label">Numéro de suivi de la demande d’aide</span>
                            <strong class="status-value"><?= e($demande['numero_suivi']) ?></strong>
                        </div>
                        <?php
                        $statutsAide = [
                            'reçue' => 'Demande reçue',
                            'en_cours_examen' => 'Demande en cours d’analyse',
                            'complement_demande' => 'Des informations complémentaires sont demandées',
                            'acceptee' => 'Demande acceptée',
                            'refusee' => 'Demande refusée'
                        ];
                        ?>
                        <div class="status-box">
                            <span class="status-label">Statut de la demande d’aide</span>
                            <strong class="status-value"><?= e($statutsAide[$demande['statut_aide']] ?? 'Statut en cours de traitement') ?></strong>
                        </div>
                    <?php else: ?>
                        <?php if (($demande['statut_verification'] ?? '') === 'validee' && ($demande['resultat'] ?? '') === 'eligible'): ?>
                            <p class="alert alert-success">Votre dossier est éligible. Vous pouvez maintenant <a href="/maria-v/public/faire-demande-aide.php">faire votre demande d’aide</a>.</p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="result-detail">
                        <strong>Résultat :</strong>

                        <?php if ($statut === 'validee' && $demande['resultat'] === 'eligible'): ?>
                            La vérification a conclu à une éligibilité.
                        <?php elseif ($statut === 'validee' && $demande['resultat'] === 'non_eligible'): ?>
                            La vérification a conclu à une non-éligibilité.
                        <?php elseif ($statut === 'refusee'): ?>
                            La vérification a été refusée. Pour connaître les informations complémentaires, contactez la fondation.
                        <?php else: ?>
                            Le résultat est en attente de la fin de la vérification.
                        <?php endif; ?>
                    </p>

                    <p class="privacy-note">
                        Cette page affiche uniquement les informations
                        générales de suivi. Pour toute question,
                        contactez la fondation.
                    </p>

                </div>

            <?php endif; ?>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

