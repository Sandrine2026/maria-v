<?php
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Seuls les gestionnaires et super administrateurs
// peuvent gérer les actualités.
exigerRole(['gestionnaire', 'super_admin']);

// Génération du jeton CSRF.
if (empty($_SESSION['csrf_articles'])) {
    $_SESSION['csrf_articles'] = bin2hex(random_bytes(32));
}

$message = '';
$erreur = '';
$articleAModifier = null;

// =====================================================
// TRAITEMENT DES FORMULAIRES
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole(['gestionnaire', 'super_admin']);

    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !isset($_SESSION['csrf_articles']) ||
        !is_string($_SESSION['csrf_articles']) ||
        !hash_equals($_SESSION['csrf_articles'], $jeton)
    ) {
        $erreur = "La session du formulaire a expiré. Rechargez la page.";
    } else {
        $action = $_POST['action'] ?? '';

        try {
            // ---------------------------------------------
            // AJOUT OU MODIFICATION D'UN ARTICLE
            // ---------------------------------------------

            if ($action === 'ajouter' || $action === 'modifier') {
                $titre = trim($_POST['titre'] ?? '');
                $contenu = trim($_POST['contenu'] ?? '');
                $image = trim($_POST['image'] ?? '');
                $publie = isset($_POST['publie']) ? 1 : 0;

                if (
                    !is_string($titre) ||
                    !is_string($contenu) ||
                    !is_string($image)
                ) {
                    throw new RuntimeException(
                        "Les données envoyées sont invalides."
                    );
                }

                if ($titre === '' || $contenu === '') {
                    throw new RuntimeException(
                        "Le titre et le contenu sont obligatoires."
                    );
                }

                if (
                    strlen($titre) > 255 ||
                    strlen($image) > 255
                ) {
                    throw new RuntimeException(
                        "Le titre ou le chemin de l'image est trop long."
                    );
                }

                // Validation du chemin de l'image.
                if ($image !== '') {
                    if (
                        !preg_match(
                            '~^[a-zA-Z0-9_/-]+(?:\.[a-zA-Z0-9_-]+)*$~',
                            $image
                        ) ||
                        str_starts_with($image, '/') ||
                        str_contains($image, '..') ||
                        !preg_match(
                            '~\.(jpg|jpeg|png|webp|gif)$~i',
                            $image
                        )
                    ) {
                        throw new RuntimeException(
                            "Le chemin de l'image n'est pas valide."
                        );
                    }
                }

                $image = $image !== '' ? $image : null;

                // -----------------------------------------
                // AJOUT
                // -----------------------------------------

                if ($action === 'ajouter') {
                    $datePublication = $publie
                        ? date('Y-m-d H:i:s')
                        : null;

                    $sql = "INSERT INTO articles
                        (
                            titre,
                            contenu,
                            image,
                            auteur_id,
                            publie,
                            date_publication
                        )
                        VALUES
                        (
                            :titre,
                            :contenu,
                            :image,
                            :auteur,
                            :publie,
                            :date_publication
                        )";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        'titre' => $titre,
                        'contenu' => $contenu,
                        'image' => $image,
                        'auteur' => (int) $_SESSION['admin_id'],
                        'publie' => $publie,
                        'date_publication' => $datePublication
                    ]);

                    $message = "L'article a été ajouté.";
                }

                // -----------------------------------------
                // MODIFICATION
                // -----------------------------------------

                elseif ($action === 'modifier') {
                    $id = filter_input(
                        INPUT_POST,
                        'id',
                        FILTER_VALIDATE_INT
                    );

                    if (!$id || $id < 1) {
                        throw new RuntimeException(
                            "Identifiant d'article invalide."
                        );
                    }

                    $stmt = $pdo->prepare(
                        "SELECT publie, date_publication
                         FROM articles
                         WHERE id = :id
                         LIMIT 1"
                    );

                    $stmt->execute(['id' => $id]);
                    $ancienArticle = $stmt->fetch();

                    if (!$ancienArticle) {
                        throw new RuntimeException(
                            "Article introuvable."
                        );
                    }

                    // Conserver la date d'origine si l'article
                    // reste publié. Enregistrer une date lors
                    // d'une nouvelle publication.
                    if ($publie) {
                        $datePublication = !empty(
                            $ancienArticle['publie']
                        )
                            ? $ancienArticle['date_publication']
                            : date('Y-m-d H:i:s');
                    } else {
                        $datePublication = null;
                    }

                    $sql = "UPDATE articles
                            SET titre = :titre,
                                contenu = :contenu,
                                image = :image,
                                publie = :publie,
                                date_publication = :date_publication,
                                date_modification = NOW()
                            WHERE id = :id";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        'titre' => $titre,
                        'contenu' => $contenu,
                        'image' => $image,
                        'publie' => $publie,
                        'date_publication' => $datePublication,
                        'id' => $id
                    ]);

                    $message = "L'article a été modifié.";
                }
            }

            // ---------------------------------------------
            // SUPPRESSION
            // ---------------------------------------------

            elseif ($action === 'supprimer') {
                $id = filter_input(
                    INPUT_POST,
                    'id',
                    FILTER_VALIDATE_INT
                );

                if (!$id || $id < 1) {
                    throw new RuntimeException(
                        "Identifiant d'article invalide."
                    );
                }

                $stmt = $pdo->prepare(
                    "DELETE FROM articles WHERE id = :id"
                );

                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException(
                        "Article introuvable."
                    );
                }

                $message = "L'article a été supprimé.";
            }

            else {
                throw new RuntimeException(
                    "Action inconnue."
                );
            }
        } catch (Throwable $e) {
            // Les messages de validation sont affichés.
            // Les détails techniques ne sont pas exposés.
            if ($e instanceof RuntimeException) {
                $erreur = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $erreur = "Une erreur est survenue lors de l'enregistrement.";
            }
        }
    }
}

// =====================================================
// CHARGEMENT D'UN ARTICLE À MODIFIER
// =====================================================

if (isset($_GET['modifier'])) {
    $id = filter_input(
        INPUT_GET,
        'modifier',
        FILTER_VALIDATE_INT
    );

    if (!$id || $id < 1) {
        $erreur = "Identifiant d'article invalide.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, titre, contenu, image, publie
             FROM articles
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $articleAModifier = $stmt->fetch();

        if (!$articleAModifier) {
            $erreur = "Article introuvable.";
        }
    }
}

// =====================================================
// LISTE DES ARTICLES
// =====================================================

$stmt = $pdo->query(
    "SELECT id, titre, image, publie, date_creation, date_publication
     FROM articles
     ORDER BY date_creation DESC"
);

$articles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des actualités - MARIA-V</title>

    <link rel="stylesheet" href="/maria-v/assets/css/style.css">

    <style>
        .articles-admin {
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
        }

        .articles-admin h1,
        .articles-admin h2 {
            color: #183D32;
        }

        .article-form {
            background: #FAF8F2;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .article-form label {
            display: block;
            margin: 14px 0 6px;
            font-weight: bold;
        }

        .article-form input[type="text"],
        .article-form textarea {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .article-form textarea {
            min-height: 180px;
        }

        .article-form button,
        .btn-article {
            display: inline-block;
            padding: 9px 14px;
            margin-top: 12px;
            border: 0;
            border-radius: 5px;
            background: #183D32;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .article-table {
            width: 100%;
            border-collapse: collapse;
        }

        .article-table th,
        .article-table td {
            padding: 11px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .article-table th {
            background: #183D32;
            color: white;
        }

        .article-table tr:nth-child(even) {
            background: #FAF8F2;
        }

        .message-ok {
            padding: 12px;
            background: #e4f5e8;
            margin-bottom: 15px;
        }

        .message-erreur {
            padding: 12px;
            background: #ffe6e6;
            margin-bottom: 15px;
        }

        .actions-article {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .btn-supprimer {
            background: #a12626;
            color: white;
            padding: 9px;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }

        @media (max-width: 700px) {
            .article-table {
                font-size: 13px;
            }

            .article-table th,
            .article-table td {
                padding: 6px;
            }
        }
    </style>
</head>

<body>

<main class="articles-admin">

    <h1>Gestion des actualités MARIA-V</h1>

    <p>
        <a class="btn-article" href="dashboard.php">
            Retour au tableau de bord
        </a>
    </p>

    <?php if ($message !== ''): ?>
        <div class="message-ok" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($erreur !== ''): ?>
        <div class="message-erreur" role="alert">
            <?= e($erreur) ?>
        </div>
    <?php endif; ?>

    <section class="article-form">

        <h2>
            <?= $articleAModifier
                ? "Modifier un article"
                : "Ajouter une actualité" ?>
        </h2>

        <form method="POST" action="articles.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_articles']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= $articleAModifier ? 'modifier' : 'ajouter' ?>"
            >

            <?php if ($articleAModifier): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $articleAModifier['id'] ?>"
                >
            <?php endif; ?>

            <label for="titre">Titre de l'article *</label>

            <input
                type="text"
                id="titre"
                name="titre"
                maxlength="255"
                required
                value="<?= e($articleAModifier['titre'] ?? '') ?>"
            >

            <label for="contenu">Contenu *</label>

            <textarea
                id="contenu"
                name="contenu"
                required
            ><?= e($articleAModifier['contenu'] ?? '') ?></textarea>

            <label for="image">Chemin de l'image (facultatif)</label>

            <input
                type="text"
                id="image"
                name="image"
                maxlength="255"
                placeholder="assets/images/actualite.jpg"
                value="<?= e($articleAModifier['image'] ?? '') ?>"
            >

            <p>
                <label>
                    <input
                        type="checkbox"
                        name="publie"
                        value="1"
                        <?= !empty($articleAModifier['publie'])
                            ? 'checked'
                            : '' ?>
                    >
                    Publier cet article
                </label>
            </p>

            <button type="submit">
                <?= $articleAModifier
                    ? "Enregistrer les modifications"
                    : "Ajouter l'article" ?>
            </button>

            <?php if ($articleAModifier): ?>
                <a class="btn-article" href="articles.php">
                    Annuler
                </a>
            <?php endif; ?>

        </form>
    </section>

    <section>

        <h2>Liste des actualités</h2>

        <?php if (empty($articles)): ?>

            <p>Aucun article enregistré pour le moment.</p>

        <?php else: ?>

            <table class="article-table">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($articles as $article): ?>

                    <tr>
                        <td><?= (int) $article['id'] ?></td>

                        <td><?= e($article['titre']) ?></td>

                        <td>
                            <?= $article['publie']
                                ? 'Publié'
                                : 'Brouillon' ?>
                        </td>

                        <td><?= e($article['date_creation']) ?></td>

                        <td>
                            <div class="actions-article">

                                <a
                                    class="btn-article"
                                    href="articles.php?modifier=<?= (int) $article['id'] ?>"
                                >
                                    Modifier
                                </a>

                                <form
                                    method="POST"
                                    action="articles.php"
                                    onsubmit="return confirm('Supprimer définitivement cet article ?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_articles']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="supprimer"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $article['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-supprimer"
                                    >
                                        Supprimer
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
