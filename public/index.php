<?php

$titrePage = 'Accueil';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

?>

<main>

    <!-- Section principale -->
    <section class="hero">
        <div class="container">

            <p class="surtitre">Bienvenue chez MARIA-V</p>

            <h1>
                Ensemble, construisons un avenir meilleur
            </h1>

            <p>
                MARIA-V est une fondation sociale qui œuvre pour
                accompagner les personnes en difficulté et favoriser
                un avenir plus digne et solidaire.
            </p>

            <div class="hero-actions">
                <a href="eligibility.php" class="btn btn-primary">
                    Vérifier mon éligibilité
                </a>

                <a href="actions.php" class="btn btn-secondary">
                    Découvrir nos actions
                </a>
            </div>

        </div>
    </section>

    <!-- Présentation de la fondation -->
    <section class="section">
        <div class="container">

            <div class="section-heading">
                <p class="surtitre">Qui sommes-nous ?</p>

                <h2>Une fondation tournée vers la solidarité</h2>

                <p>
                    Nous souhaitons contribuer à l'amélioration des
                    conditions de vie des personnes qui ont besoin
                    d'accompagnement, grâce à des actions solidaires
                    et à un engagement humain.
                </p>
            </div>

        </div>
    </section>

    <!-- Nos missions -->
    <section class="section section-light">
        <div class="container">

            <div class="section-heading">
                <p class="surtitre">Notre engagement</p>
                <h2>Nos missions</h2>
                <p>
                    Découvrez les domaines dans lesquels la fondation
                    souhaite apporter son soutien.
                </p>
            </div>

            <div class="missions-grid">

                <article class="mission-card">
                    <div class="mission-icon">♥</div>
                    <h3>Solidarité</h3>
                    <p>
                        Placer l'entraide et la dignité humaine au cœur
                        de notre engagement.
                    </p>
                </article>

                <article class="mission-card">
                    <div class="mission-icon">✦</div>
                    <h3>Accompagnement</h3>
                    <p>
                        Étudier les situations et orienter les personnes
                        vers les dispositifs d'aide adaptés.
                    </p>
                </article>

                <article class="mission-card">
                    <div class="mission-icon">❀</div>
                    <h3>Espoir</h3>
                    <p>
                        Encourager des perspectives d'avenir plus
                        positives grâce à des initiatives solidaires.
                    </p>
                </article>

            </div>

        </div>
    </section>

    <!-- Accès aux démarches -->
    <section class="section">
        <div class="container cta-section">

            <h2>Vous souhaitez solliciter une aide ?</h2>

            <p>
                Consultez les critères d'éligibilité et découvrez
                comment soumettre votre dossier à la fondation.
            </p>

            <a href="eligibility.php" class="btn btn-primary">
                Commencer ma démarche
            </a>

        </div>
    </section>

</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

