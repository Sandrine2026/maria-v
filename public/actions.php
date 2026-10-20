
<?php

$titrePage = 'Nos actions';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main>

    <!-- Bannière -->
    <section class="page-banner">
        <div class="container">
            <p class="surtitre">Fondation MARIA-V</p>

            <h1>Nos actions solidaires</h1>

            <p>
                Découvrir nos engagements et notre démarche
                d'accompagnement des personnes.
            </p>
        </div>
    </section>

    <!-- Introduction -->
    <section class="actions-intro">
        <div class="container">

            <p class="surtitre">Notre engagement</p>

            <h2>Agir avec solidarité et responsabilité</h2>

            <p>
                MARIA-V souhaite placer l'écoute, le respect et
                l'accompagnement au cœur de ses démarches.
                Chaque situation mérite une attention particulière
                et un examen sérieux.
            </p>

        </div>
    </section>

    <!-- Domaines d'action -->
    <section class="actions-section">
        <div class="container">

            <div class="section-heading">
                <p class="surtitre">Nos domaines d'intervention</p>
                <h2>Des actions tournées vers l'humain</h2>

                <p>
                    Découvrez les formes d'accompagnement que la
                    fondation pourra présenter selon ses programmes
                    effectivement disponibles.
                </p>
            </div>

            <div class="actions-grid">

                <article class="action-card">
                    <div class="action-icon" aria-hidden="true">♡</div>

                    <h3>Accompagnement des personnes</h3>

                    <p>
                        Accueillir les demandes, écouter les besoins
                        exprimés et orienter les personnes vers les
                        démarches adaptées à leur situation.
                    </p>
                </article>

                <article class="action-card">
                    <div class="action-icon" aria-hidden="true">✚</div>

                    <h3>Examen des demandes</h3>

                    <p>
                        Recueillir les informations nécessaires,
                        vérifier les dossiers et étudier les situations
                        selon les critères applicables.
                    </p>
                </article>

                <article class="action-card">
                    <div class="action-icon" aria-hidden="true">◎</div>

                    <h3>Information et orientation</h3>

                    <p>
                        Informer les demandeurs sur les étapes
                        de traitement de leur dossier et leur permettre
                        de suivre son évolution.
                    </p>
                </article>

            </div>

        </div>
    </section>

    <!-- Notre démarche -->
    <section class="actions-approach">
        <div class="container">

            <div class="actions-approach-content">

                <div>
                    <p class="surtitre">Notre démarche</p>

                    <h2>Un processus clair et attentif</h2>

                    <p>
                        Chaque dossier suit un processus d'examen.
                        Les informations transmises sont étudiées
                        afin de déterminer les suites appropriées.
                    </p>
                </div>

                <div class="actions-steps">

                    <div class="actions-step">
                        <span class="step-number">1</span>

                        <div>
                            <h3>Soumission</h3>
                            <p>
                                Le demandeur transmet les informations
                                requises pour son dossier.
                            </p>
                        </div>
                    </div>

                    <div class="actions-step">
                        <span class="step-number">2</span>

                        <div>
                            <h3>Vérification</h3>
                            <p>
                                Les informations et les pièces
                                justificatives sont examinées.
                            </p>
                        </div>
                    </div>

                    <div class="actions-step">
                        <span class="step-number">3</span>

                        <div>
                            <h3>Suivi</h3>
                            <p>
                                Le demandeur consulte l'état de
                                sa vérification à l'aide de son numéro
                                de dossier.
                            </p>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- Appel à l'action -->
    <section class="actions-cta">
        <div class="container">

            <h2>Vous souhaitez faire examiner votre situation ?</h2>

            <p>
                Consultez les informations nécessaires avant
                de soumettre votre dossier.
            </p>

            <div class="actions-buttons">

                <a
                    href="/maria-v/public/eligibility.php"
                    class="btn btn-primary"
                >
                    Vérifier mon éligibilité
                </a>

                <a
                    href="/maria-v/public/contact.php"
                    class="btn btn-secondary"
                >
                    Contacter la fondation
                </a>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
