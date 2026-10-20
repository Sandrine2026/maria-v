<?php
// Charger la langue et démarrer la session avant tout affichage HTML.
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$titrePage = "Actualités | MARIA-V";
$articles = [];
$erreur = false;

try {
    $sql = "SELECT id, titre, contenu, image, date_publication, date_creation
            FROM articles
            WHERE publie = 1
            ORDER BY date_publication DESC, date_creation DESC";

    $stmt = $pdo->query($sql);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erreur = true;
}

function afficherDate($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '';
    }

    return date('d/m/Y', $timestamp);
}

function cheminImageValide($image)
{
    if (empty($image)) {
        return false;
    }

    // Les images doivent utiliser un chemin relatif local.
    if (strpos($image, '..') !== false ||
        strpos($image, '/') === 0 ||
        strpos($image, '\\') === 0 ||
        preg_match('/^[a-z][a-z0-9+.-]*:/i', $image)) {
        return false;
    }

    return (bool) preg_match(
        '/\.(jpg|jpeg|png|webp|gif)$/i',
        $image
    );
}

$titrePage = $t['actualites'] ?? 'Actualités';
?>
<!DOCTYPE html>
<html lang="<?= e($langue) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="Découvrez les actualités et les initiatives de la fondation MARIA-V.">

    <title><?= e($titrePage) ?></title>

    <link rel="stylesheet" href="/maria-v/assets/css/style.css">

    <style>
        .actualites-page {
            max-width: 1150px;
            margin: 40px auto;
            padding: 20px;
            min-height: 60vh;
        }

        .actualites-entete {
            text-align: center;
            margin-bottom: 40px;
        }

        .actualites-entete h1 {
            color: #183D32;
            font-size: 2.3rem;
            margin-bottom: 15px;
        }

        .actualites-entete p {
            max-width: 720px;
            margin: auto;
            color: #555;
            line-height: 1.8;
        }

        .actualites-grille {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(min(100%, 280px), 1fr)
            );
            gap: 25px;
        }

        .actualite-carte {
            background: #FFFFFF;
            border: 1px solid #e8e5dc;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        }

        .actualite-image {
            display: block;
            width: 100%;
            height: 230px;
            object-fit: cover;
        }

        .actualite-image-placeholder {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #183D32;
            color: #C6A45C;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .actualite-corps {
            padding: 22px;
        }

        .actualite-corps h2 {
            color: #183D32;
            font-size: 1.35rem;
            line-height: 1.4;
            margin: 0 0 12px;
            overflow-wrap: anywhere;
        }

        .actualite-date {
            color: #8a713a;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        .actualite-texte {
            color: #444;
            line-height: 1.8;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .actualites-message {
            padding: 25px;
            background: #FAF8F2;
            border-radius: 10px;
            text-align: center;
            line-height: 1.8;
        }

        .actualites-erreur {
            color: #8b2020;
            background: #fff0f0;
            border: 1px solid #e8bcbc;
        }

        .actualites-navigation {
            background: #183D32;
            padding: 16px 20px;
        }

        .actualites-navigation a {
            color: #FFFFFF;
            text-decoration: none;
            margin-right: 22px;
        }

        .actualites-navigation a:hover {
            color: #C6A45C;
        }

        .actualites-pied {
            margin-top: 45px;
            padding: 22px;
            text-align: center;
            background: #183D32;
            color: #FFFFFF;
        }

        @media (max-width: 600px) {
            .actualites-page {
                margin: 20px auto;
                padding: 15px;
            }

            .actualites-entete h1 {
                font-size: 1.8rem;
            }

            .actualite-corps {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<nav class="actualites-navigation">
    <a href="index.php">Accueil</a>
    <a href="actualites.php">Actualités</a>
    <a href="eligibility.php">Vérification d'éligibilité</a>
    <a href="tracking.php">Suivre mon dossier</a>
</nav>

<main class="actualites-page">

    <header class="actualites-entete">
        <h1>Actualités de MARIA-V</h1>

        <p>
            Découvrez les nouvelles, les initiatives et les actions de notre
            fondation pour construire ensemble un avenir meilleur.
        </p>
    </header>

    <?php if ($erreur): ?>

        <div class="actualites-message actualites-erreur">
            <h2>Impossible de charger les actualités</h2>
            <p>
                Une erreur est survenue lors de la lecture des articles.
                Vérifiez la connexion à la base de données et la structure
                de la table articles.
            </p>
        </div>

    <?php elseif (empty($articles)): ?>

        <div class="actualites-message">
            <h2>Aucune actualité pour le moment</h2>
            <p>
                Les articles apparaîtront ici dès qu'ils auront été publiés
                depuis l'espace d'administration.
            </p>
        </div>

    <?php else: ?>

        <section class="actualites-grille"
                 aria-label="Actualités publiées">

            <?php foreach ($articles as $article): ?>

                <article class="actualite-carte">

                    <?php if (cheminImageValide($article['image'] ?? '')): ?>

                        <img
                            class="actualite-image"
                            src="/maria-v/<?= e($article['image']) ?>"
                            alt="<?= e($article['titre']) ?>"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <div class="actualite-image-placeholder">
                            MARIA-V
                        </div>

                    <?php endif; ?>

                    <div class="actualite-corps">

                        <h2><?= e($article['titre']) ?></h2>

                        <?php
                        $date = $article['date_publication']
                            ?? $article['date_creation']
                            ?? '';
                        ?>

                        <?php if ($date !== ''): ?>
                            <p class="actualite-date">
                                Publié le <?= e(afficherDate($date)) ?>
                            </p>
                        <?php endif; ?>

                        <div class="actualite-texte"><?= e($article['contenu']) ?></div>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<footer class="actualites-pied">
    <p>&copy; <?= date('Y') ?> MARIA-V. Tous droits réservés.</p>
</footer>

</body>
</html>
