<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

$titrePage = 'Vérification d’éligibilité';

// Création du jeton de sécurité du formulaire.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$jetonCSRF = $_SESSION['csrf_token'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main>

    <section class="page-banner">
        <div class="container">
            <p class="surtitre">Fondation MARIA-V</p>

            <h1>Vérification d'éligibilité</h1>

            <p>
                Découvrez si votre situation peut correspondre
                aux critères d'accompagnement de la fondation.
            </p>
        </div>
    </section>

    <section class="eligibility-section">
        <div class="container eligibility-layout">

            <div class="eligibility-info">

                <img
                    src="/maria-v/assets/images/solidarite.jpg"
                    alt="Illustration de solidarité et d'entraide"
                    class="eligibility-image"
                    loading="lazy"
                >

                <h2>Comment procéder ?</h2>

                <div class="step-item">
                    <span class="step-number">1</span>

                    <div>
                        <h3>Renseignez vos informations</h3>
                        <p>
                            Indiquez votre identité et vos coordonnées.
                        </p>
                    </div>
                </div>

                <div class="step-item">
                    <span class="step-number">2</span>

                    <div>
                        <h3>Préparez votre document</h3>
                        <p>
                            Un document d'identité sera demandé
                            pour permettre l'examen de votre dossier.
                        </p>
                    </div>
                </div>

                <div class="step-item">
                    <span class="step-number">3</span>

                    <div>
                        <h3>Attendez l'examen</h3>
                        <p>
                            L'équipe de MARIA-V examinera les informations
                            avant de communiquer le résultat.
                        </p>
                    </div>
                </div>

                <p class="privacy-note">
                    Vos informations personnelles doivent être protégées
                    et accessibles uniquement aux personnes autorisées.
                </p>

            </div>

            <div class="eligibility-form-card">

                <h2>Vos informations</h2>

                <p class="form-intro">
                    Les champs marqués d'un astérisque (*) sont obligatoires.
                </p>

                <form
                    action="traitement-eligibilite.php"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($jetonCSRF) ?>"
                    >

                    <div class="form-group">
                        <label for="nom">Nom *</label>

                        <input
                            type="text"
                            id="nom"
                            name="nom"
                            maxlength="100"
                            autocomplete="family-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom(s) *</label>

                        <input
                            type="text"
                            id="prenom"
                            name="prenom"
                            maxlength="100"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse e-mail *</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone *</label>

                        <input
                            type="tel"
                            id="telephone"
                            name="telephone"
                            maxlength="30"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="document_identite">
                            Document d'identité *
                        </label>

                        <input
                            type="file"
                            id="document_identite"
                            name="document_identite"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        >

                        <small>
                            Formats acceptés : PDF, JPG ou PNG.
                            Taille maximale : 5 Mo.
                        </small>
                    </div>

                    <div class="form-group checkbox-group">

                        <input
                            type="checkbox"
                            id="consentement"
                            name="consentement"
                            value="1"
                            required
                        >

                        <label for="consentement">
                            J'accepte que mes informations soient utilisées
                            pour examiner ma demande, conformément à la
                            politique de confidentialité. *
                        </label>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary form-submit"
                    >
                        Soumettre mes informations
                    </button>

                    <p class="form-disclaimer">
                        La soumission de ce formulaire ne garantit pas
                        l'obtention d'une aide. Le dossier doit être examiné
                        par la fondation.
                    </p>

                </form>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
