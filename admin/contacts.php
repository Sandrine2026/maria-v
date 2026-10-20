<?php
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Autoriser uniquement les gestionnaires et super administrateurs.
exigerRole(['gestionnaire', 'super_admin']);

// Générer le jeton CSRF.
if (empty($_SESSION['csrf_contacts'])) {
    $_SESSION['csrf_contacts'] = bin2hex(random_bytes(32));
}

$messageSucces = '';
$messageErreur = '';

// =====================================================
// TRAITEMENT DES ACTIONS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigerRole(['gestionnaire', 'super_admin']);

    $jeton = $_POST['csrf_token'] ?? '';

    if (
        !is_string($jeton) ||
        !isset($_SESSION['csrf_contacts']) ||
        !is_string($_SESSION['csrf_contacts']) ||
        !hash_equals($_SESSION['csrf_contacts'], $jeton)
    ) {
        $messageErreur = "Formulaire expiré. Rechargez la page.";
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        try {
            if (!is_string($action) || !$id || $id < 1) {
                throw new InvalidArgumentException(
                    "Les informations envoyées sont invalides."
                );
            }

            // ---------------------------------------------
            // MODIFICATION DU STATUT
            // ---------------------------------------------

            if ($action === 'statut') {
                $statut = $_POST['statut'] ?? '';

                $statutsAutorises = [
                    'nouveau',
                    'lu',
                    'traite'
                ];

                if (
                    !is_string($statut) ||
                    !in_array($statut, $statutsAutorises, true)
                ) {
                    throw new InvalidArgumentException(
                        "Le statut sélectionné n'est pas valide."
                    );
                }

                // Vérifier que le message existe.
                $verification = $pdo->prepare(
                    "SELECT id FROM contacts WHERE id = :id LIMIT 1"
                );

                $verification->execute(['id' => $id]);

                if (!$verification->fetch()) {
                    throw new RuntimeException(
                        "Le message demandé est introuvable."
                    );
                }

                // Adapter la date de traitement au statut.
                if ($statut === 'nouveau') {
                    $sql = "UPDATE contacts
                            SET statut = :statut,
                                date_traitement = NULL
                            WHERE id = :id";
                } elseif ($statut === 'lu') {
                    $sql = "UPDATE contacts
                            SET statut = :statut
                            WHERE id = :id";
                } else {
                    $sql = "UPDATE contacts
                            SET statut = :statut,
                                date_traitement = NOW()
                            WHERE id = :id";
                }

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    'statut' => $statut,
                    'id' => $id
                ]);

                $messageSucces =
                    "Le statut du message a été mis à jour.";

            // ---------------------------------------------
            // SUPPRESSION D'UN MESSAGE
            // ---------------------------------------------

            } elseif ($action === 'supprimer') {
                $stmt = $pdo->prepare(
                    "DELETE FROM contacts WHERE id = :id"
                );

                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException(
                        "Le message est introuvable ou a déjà été supprimé."
                    );
                }

                $messageSucces = "Le message a été supprimé.";

            } else {
                throw new InvalidArgumentException(
                    "L'action demandée n'est pas valide."
                );
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());

            $messageErreur =
                "Une erreur de base de données est survenue.";

        } catch (InvalidArgumentException | RuntimeException $e) {
            $messageErreur = $e->getMessage();

        } catch (Throwable $e) {
            error_log($e->getMessage());

            $messageErreur =
                "Une erreur inattendue est survenue.";
        }
    }
}

// =====================================================
// FILTRAGE DES MESSAGES
// =====================================================

$filtre = $_GET['statut'] ?? 'tous';

$statutsAutorises = [
    'tous',
    'nouveau',
    'lu',
    'traite'
];

if (
    !is_string($filtre) ||
    !in_array($filtre, $statutsAutorises, true)
) {
    $filtre = 'tous';
}

try {
    if ($filtre === 'tous') {
        $stmt = $pdo->query(
            "SELECT *
             FROM contacts
             ORDER BY date_creation DESC"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM contacts
             WHERE statut = :statut
             ORDER BY date_creation DESC"
        );

        $stmt->execute(['statut' => $filtre]);
    }

    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------------------------------------------
    // COMPTEURS DES MESSAGES
    // ---------------------------------------------

    $compteurs = [
        'nouveau' => 0,
        'lu' => 0,
        'traite' => 0
    ];

    $stmt = $pdo->query(
        "SELECT statut, COUNT(*) AS total
         FROM contacts
         GROUP BY statut"
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
        if (array_key_exists($ligne['statut'], $compteurs)) {
            $compteurs[$ligne['statut']] = (int) $ligne['total'];
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    $contacts = [];

    $compteurs = [
        'nouveau' => 0,
        'lu' => 0,
        'traite' => 0
    ];

    $messageErreur =
        "Impossible de charger les messages pour le moment.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Messages de contact - MARIA-V</title>

    <link rel="stylesheet"
          href="/maria-v/assets/css/style.css">

    <style>
        .contacts-admin {
            max-width: 1200px;
            margin: 35px auto;
            padding: 20px;
        }

        .contacts-admin h1,
        .contacts-admin h2 {
            color: #183D32;
        }

        .contacts-compteurs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .contact-compteur {
            padding: 20px;
            background: #FAF8F2;
            border-radius: 9px;
            border-left: 4px solid #C6A45C;
        }

        .contact-compteur strong {
            display: block;
            font-size: 1.8rem;
            color: #183D32;
        }

        .contacts-filtres {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 25px 0;
        }

        .contacts-filtres a,
        .contact-bouton {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 5px;
            background: #183D32;
            color: #fff;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .contacts-filtres a.actif {
            background: #C6A45C;
            color: #183D32;
        }

        .contact-carte {
            padding: 22px;
            margin-bottom: 20px;
            border: 1px solid #e5e2da;
            border-radius: 10px;
            background: #fff;
            overflow-wrap: anywhere;
        }

        .contact-carte h2 {
            margin-top: 0;
        }

        .contact-meta {
            color: #555;
            line-height: 1.8;
        }

        .contact-message {
            margin: 18px 0;
            padding: 15px;
            background: #FAF8F2;
            border-radius: 6px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .contact-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .contact-actions form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .contact-actions select {
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .contact-supprimer {
            background: #a12626;
        }

        .contact-succes,
        .contact-erreur {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .contact-succes {
            background: #e4f5e8;
        }

        .contact-erreur {
            background: #ffe6e6;
        }

        .contact-statut {
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .contacts-admin {
                padding: 12px;
            }

            .contact-carte {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<main class="contacts-admin">

    <h1>Gestion des messages de contact</h1>

    <p>
        <a class="contact-bouton" href="dashboard.php">
            Retour au tableau de bord
        </a>
    </p>

    <?php if ($messageSucces !== ''): ?>
        <div class="contact-succes" role="status">
            <?= e($messageSucces) ?>
        </div>
    <?php endif; ?>

    <?php if ($messageErreur !== ''): ?>
        <div class="contact-erreur" role="alert">
            <?= e($messageErreur) ?>
        </div>
    <?php endif; ?>

    <section class="contacts-compteurs">

        <div class="contact-compteur">
            <strong><?= $compteurs['nouveau'] ?></strong>
            Nouveaux messages
        </div>

        <div class="contact-compteur">
            <strong><?= $compteurs['lu'] ?></strong>
            Messages lus
        </div>

        <div class="contact-compteur">
            <strong><?= $compteurs['traite'] ?></strong>
            Messages traités
        </div>

    </section>

    <h2>Boîte de réception</h2>

    <?php
    $filtres = [
        'tous' => 'Tous',
        'nouveau' => 'Nouveaux',
        'lu' => 'Lus',
        'traite' => 'Traités'
    ];
    ?>

    <nav class="contacts-filtres" aria-label="Filtrer les messages">

        <?php foreach ($filtres as $valeur => $libelle): ?>

            <a
                href="contacts.php?statut=<?= e($valeur) ?>"
                class="<?= $filtre === $valeur ? 'actif' : '' ?>"
                <?= $filtre === $valeur ? 'aria-current="page"' : '' ?>
            >
                <?= e($libelle) ?>
            </a>

        <?php endforeach; ?>

    </nav>

    <?php if (empty($contacts)): ?>

        <p>Aucun message dans cette catégorie.</p>

    <?php else: ?>

        <?php foreach ($contacts as $contact): ?>

            <article class="contact-carte">

                <h2>
                    <?= e($contact['sujet'] ?? 'Sans sujet') ?>
                </h2>

                <div class="contact-meta">

                    <strong>Nom :</strong>
                    <?= e($contact['nom'] ?? '') ?>
                    <?= e($contact['prenom'] ?? '') ?>
                    <br>

                    <strong>E-mail :</strong>
                    <?= e($contact['email'] ?? '') ?>
                    <br>

                    <strong>Téléphone :</strong>
                    <?= e($contact['telephone'] ?? 'Non renseigné') ?>
                    <br>

                    <strong>Date de réception :</strong>
                    <?= e($contact['date_creation'] ?? '') ?>
                    <br>

                    <strong>Statut :</strong>

                    <span class="contact-statut">
                        <?= e(ucfirst($contact['statut'] ?? 'inconnu')) ?>
                    </span>

                    <?php if (!empty($contact['date_traitement'])): ?>
                        <br>

                        <strong>Date de traitement :</strong>
                        <?= e($contact['date_traitement']) ?>
                    <?php endif; ?>

                </div>

                <div class="contact-message"><?= e($contact['message'] ?? '') ?></div>

                <div class="contact-actions">

                    <!-- FORMULAIRE DE CHANGEMENT DE STATUT -->

                    <form method="POST" action="contacts.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_contacts']) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="statut"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $contact['id'] ?>"
                        >

                        <label for="statut-<?= (int) $contact['id'] ?>">
                            Changer le statut :
                        </label>

                        <select
                            name="statut"
                            id="statut-<?= (int) $contact['id'] ?>"
                        >

                            <option
                                value="nouveau"
                                <?= ($contact['statut'] ?? '') === 'nouveau'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Nouveau
                            </option>

                            <option
                                value="lu"
                                <?= ($contact['statut'] ?? '') === 'lu'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Lu
                            </option>

                            <option
                                value="traite"
                                <?= ($contact['statut'] ?? '') === 'traite'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Traité
                            </option>

                        </select>

                        <button class="contact-bouton" type="submit">
                            Enregistrer
                        </button>

                    </form>

                    <!-- FORMULAIRE DE SUPPRESSION -->

                    <form
                        method="POST"
                        action="contacts.php"
                        onsubmit="return confirm('Voulez-vous vraiment supprimer ce message ?');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_contacts']) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="supprimer"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $contact['id'] ?>"
                        >

                        <button
                            class="contact-bouton contact-supprimer"
                            type="submit"
                        >
                            Supprimer
                        </button>

                    </form>

                </div>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

</body>
</html>