<?php
$titrePage = "Mentions légales - MARIA-V";

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
?>

<?php include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="legal-page">

    <section class="legal-hero">
        <div class="legal-hero-content">
            <span class="legal-label">MARIA-V</span>
            <h1>Mentions légales</h1>
            <p>
                Retrouvez les informations relatives à l'éditeur,
                à l'utilisation et à la confidentialité de ce site.
            </p>
        </div>
    </section>

    <section class="legal-content">

        <article class="legal-card">
            <h2>1. Éditeur du site</h2>
            <p>
                Le présent site est édité par la fondation MARIA-V.
            </p>
            <ul>
                <li><strong>Nom de l'organisme :</strong> MARIA-V</li>
                <li><strong>Forme juridique :</strong> à compléter</li>
                <li><strong>Adresse officielle :</strong> à compléter</li>
                <li><strong>Numéro d'enregistrement :</strong> à compléter, si applicable</li>
                <li><strong>Adresse e-mail officielle :</strong> à compléter</li>
                <li><strong>Responsable de publication :</strong> à compléter</li>
            </ul>
        </article>

        <article class="legal-card">
            <h2>2. Hébergement</h2>
            <p>
                Les informations concernant l'hébergeur du site doivent
                être renseignées par le responsable de MARIA-V.
            </p>
            <ul>
                <li><strong>Nom de l'hébergeur :</strong> à compléter</li>
                <li><strong>Adresse :</strong> à compléter</li>
                <li><strong>Site Internet de l'hébergeur :</strong> à compléter</li>
            </ul>
        </article>

        <article class="legal-card">
            <h2>3. Objet du site</h2>
            <p>
                Le site MARIA-V a pour objectif de présenter les activités
                de la fondation, de diffuser ses actualités et de permettre
                aux visiteurs de transmettre des messages et des dossiers
                dans le cadre des démarches proposées.
            </p>
            <p>
                La transmission d'un dossier ne garantit pas son acceptation.
                Chaque dossier est examiné selon les procédures et critères
                applicables à la fondation.
            </p>
        </article>

        <article class="legal-card">
            <h2>4. Utilisation du site</h2>
            <p>
                Les utilisateurs s'engagent à fournir des informations
                exactes, à utiliser les formulaires de manière responsable
                et à ne pas tenter de perturber le fonctionnement du site.
            </p>
            <p>
                En cas de difficulté technique, l'utilisateur peut
                contacter la fondation au moyen de la page Contact.
            </p>
        </article>

        <article class="legal-card">
            <h2>5. Données personnelles</h2>
            <p>
                Les formulaires du site peuvent recueillir des données
                telles que le nom, le prénom, l'adresse e-mail, le numéro
                de téléphone, les messages et les documents nécessaires
                au traitement des dossiers.
            </p>
            <p>
                Ces informations doivent être utilisées uniquement pour
                les finalités annoncées aux personnes concernées, avec
                un accès limité aux personnes autorisées et une durée
                de conservation adaptée.
            </p>
            <p>
                Avant la mise en ligne, la fondation doit préciser les
                modalités applicables : responsable du traitement,
                base juridique, durée de conservation, droits des personnes
                et coordonnées permettant d'exercer ces droits.
            </p>
        </article>

        <article class="legal-card">
            <h2>6. Sécurité</h2>
            <p>
                MARIA-V met en place des mesures techniques destinées
                à protéger les informations transmises sur le site.
                Aucun système informatique ne pouvant garantir une
                sécurité absolue, les mesures doivent être régulièrement
                vérifiées et adaptées.
            </p>
        </article>

        <article class="legal-card">
            <h2>7. Propriété intellectuelle</h2>
            <p>
                Les textes, éléments graphiques, logos et autres contenus
                du site sont soumis aux droits applicables. Toute
                reproduction ou réutilisation doit respecter les droits
                de leurs titulaires et les autorisations nécessaires.
            </p>
        </article>

        <article class="legal-card">
            <h2>8. Mise à jour des mentions légales</h2>
            <p>
                Les présentes informations doivent être complétées et
                actualisées lorsque les coordonnées, les activités ou
                les modalités de fonctionnement du site changent.
            </p>
            <p>
                <strong>Dernière mise à jour :</strong>
                <?= date('d/m/Y') ?>
            </p>
        </article>

        <div class="legal-contact">
            <h2>Une question ?</h2>
            <p>
                Pour toute question relative au site, rendez-vous sur
                notre page de contact.
            </p>
            <a href="/maria-v/public/contact.php" class="legal-button">
                Contacter MARIA-V
            </a>
        </div>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
