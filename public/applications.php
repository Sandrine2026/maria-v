<?php
$titrePage = "Demandes d'aide - MARIA-V";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
?>

<?php include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="applications-page">

    <section class="applications-hero">
        <div class="applications-hero-content">
            <span class="applications-label">MARIA-V</span>

            <h1>Déposer une demande d'aide</h1>

            <p>
                Découvrez les étapes à suivre pour soumettre votre dossier,
                transmettre les documents nécessaires et consulter son état
                de traitement.
            </p>

            <a href="/maria-v/public/eligibility.php"
               class="applications-btn">
                Commencer ma démarche
            </a>
        </div>
    </section>

    <section class="applications-section">
        <div class="section-heading">
            <span>Votre démarche</span>
            <h2>Comment procéder ?</h2>
            <p>
                Suivez ces étapes pour transmettre votre dossier à
                la fondation MARIA-V.
            </p>
        </div>

        <div class="applications-grid">

            <article class="application-card">
                <div class="application-number">01</div>
                <h3>Préparez vos informations</h3>
                <p>
                    Préparez vos coordonnées et le document d'identité
                    demandé afin de faciliter l'enregistrement de votre dossier.
                </p>
            </article>

            <article class="application-card">
                <div class="application-number">02</div>
                <h3>Soumettez votre dossier</h3>
                <p>
                    Remplissez le formulaire de vérification, joignez
                    le document demandé et validez votre soumission.
                </p>
            </article>

            <article class="application-card">
                <div class="application-number">03</div>
                <h3>Conservez votre numéro</h3>
                <p>
                    Après l'envoi, conservez le numéro de suivi affiché.
                    Il vous permettra de retrouver les informations relatives
                    à votre dossier.
                </p>
            </article>

            <article class="application-card">
                <div class="application-number">04</div>
                <h3>Suivez le traitement</h3>
                <p>
                    Consultez la page de suivi pour vérifier l'état
                    de votre dossier avec votre numéro de suivi et
                    l'adresse e-mail utilisée lors de la demande.
                </p>
            </article>

        </div>
    </section>

    <section class="applications-notice">
        <div>
            <h2>Avant de commencer</h2>
            <p>
                Veillez à fournir des informations exactes et un document
                lisible. La soumission d'un dossier ne signifie pas
                automatiquement que la demande sera acceptée.
            </p>
        </div>
    </section>

    <section class="applications-cta">
        <h2>Vous avez déjà soumis un dossier ?</h2>
        <p>Consultez son état à l'aide de vos informations de suivi.</p>

        <div class="applications-buttons">
            <a href="/maria-v/public/eligibility.php"
               class="applications-btn">
                Déposer un dossier
            </a>

            <a href="/maria-v/public/tracking.php"
               class="applications-btn applications-btn-outline">
                Suivre mon dossier
            </a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
