
<?php

$titrePage = 'À propos de MARIA-V';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main>

    <!-- Bannière de présentation -->
    <section class="page-banner">
        <div class="container">

            <p class="surtitre">Fondation MARIA-V</p>

            <h1>À propos de nous</h1>

            <p>
                Ensemble, construisons un avenir plus solidaire,
                plus humain et porteur d'espoir.
            </p>

        </div>
    </section>

    <!-- Présentation de la fondation -->
    <section class="about-section">
        <div class="container">

            <div class="about-content">

                <div class="about-text">

                    <p class="surtitre">Qui sommes-nous ?</p>

                    <h2>La solidarité au cœur de notre engagement</h2>

                    <p>
                        La fondation MARIA-V est présentée ici comme
                        une organisation engagée dans la solidarité
                        et l'accompagnement des personnes qui ont besoin
                        de soutien.
                    </p>

                    <p>
                        À travers ses actions, MARIA-V souhaite offrir
                        un cadre d'écoute, d'orientation et d'examen des
                        demandes, dans le respect de la dignité de chaque
                        personne.
                    </p>

                    <p>
                        Chaque demande reçue doit être étudiée avec
                        attention afin de permettre un traitement
                        transparent et responsable des dossiers.
                    </p>

                </div>

                <div class="about-image">
                    <img
                        src="/maria-v/assets/images/about-fondation.jpg"
                        alt="Illustration de solidarité et d'entraide"
                        loading="lazy"
                    >
                </div>

            </div>

        </div>
    </section>

    <!-- Mission, vision et valeurs -->
    <section class="about-values">
        <div class="container">

            <div class="section-heading">
                <p class="surtitre">Notre engagement</p>

                <h2>Notre mission, notre vision et nos valeurs</h2>

                <p>
                    Des principes qui guident notre approche de
                    l'accompagnement et de la solidarité.
                </p>
            </div>

            <div class="values-grid">

                <article class="value-card">
                    <div class="value-icon" aria-hidden="true">♡</div>

                    <h3>Notre mission</h3>

                    <p>
                        Écouter, orienter et examiner les demandes
                        d'accompagnement avec sérieux, attention
                        et respect.
                    </p>
                </article>

                <article class="value-card">
                    <div class="value-icon" aria-hidden="true">◎</div>

                    <h3>Notre vision</h3>

                    <p>
                        Contribuer à un environnement plus solidaire
                        dans lequel chaque personne peut être écoutée
                        et considérée avec dignité.
                    </p>
                </article>

                <article class="value-card">
                    <div class="value-icon" aria-hidden="true">✦</div>

                    <h3>Nos valeurs</h3>

                    <p>
                        Solidarité, respect, confidentialité,
                        responsabilité et transparence dans le
                        traitement des demandes.
                    </p>
                </article>

            </div>

        </div>
    </section>

    <!-- Fonctionnement -->
    <section class="about-process">
        <div class="container">

            <div class="section-heading">
                <p class="surtitre">Notre démarche</p>

                <h2>Comment nous procédons</h2>
            </div>

            <div class="process-grid">

                <article class="process-item">
                    <span class="step-number">1</span>

                    <h3>Recevoir</h3>

                    <p>
                        Recueillir les informations nécessaires
                        à l'étude du dossier.
                    </p>
                </article>

                <article class="process-item">
                    <span class="step-number">2</span>

                    <h3>Examiner</h3>

                    <p>
                        Étudier les informations transmises et
                        procéder aux vérifications nécessaires.
                    </p>
                </article>

                <article class="process-item">
                    <span class="step-number">3</span>

                    <h3>Informer</h3>

                    <p>
                        Communiquer au demandeur l'évolution
                        de son dossier et les suites données.
                    </p>
                </article>

            </div>

        </div>
    </section>

    <!-- Appel à l'action -->
    <section class="about-cta">
        <div class="container">

            <h2>Vous souhaitez en savoir plus ?</h2>

            <p>
                Découvrez nos actions ou contactez la fondation
                pour obtenir des informations complémentaires.
            </p>

            <div class="about-cta-buttons">

                <a
                    href="/maria-v/public/actions.php"
                    class="btn btn-primary"
                >
                    Découvrir nos actions
                </a>

                <a
                    href="/maria-v/public/contact.php"
                    class="btn btn-secondary"
                >
                    Nous contacter
                </a>

            </div>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
