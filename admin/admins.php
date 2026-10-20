<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$messageSucces = '';
$messageErreur = '';

if (empty($_SESSION['csrf_admins'])) {
    $_SESSION['csrf_admins'] = bin2hex(random_bytes(32));
}

// Vérifier le rôle réel de l'utilisateur connecté dans la base.
$stmt = $pdo->prepare(
    "SELECT id, nom, email, role, actif
     FROM admins
     WHERE id = :id
     LIMIT 1"
);
$stmt->execute(['id' => $_SESSION['admin_id']]);
$adminConnecte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminConnecte || (int) $adminConnecte['actif'] !== 1) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($adminConnecte['role'] !== 'super_admin') {
    http_response_code(403);
    exit("Accès refusé : cette page est réservée au super administrateur.");
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_admins'], $jeton)) {
        $messageErreur = "Le formulaire a expiré. Rechargez la page.";
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'ajouter') {
                $nom = trim($_POST['nom'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $motDePasse = $_POST['mot_de_passe'] ?? '';
                $role = $_POST['role'] ?? 'lecteur';

                if ($nom === '' || strlen($nom) > 100) {
                    throw new Exception("Le nom est obligatoire et doit contenir au maximum 100 caractères.");
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
                    strlen($email) > 150) {
                    throw new Exception("L'adresse e-mail n'est pas valide.");
                }

                if (strlen($motDePasse) < 12) {
                    throw new Exception("Le mot de passe doit contenir au moins 12 caractères.");
                }

                $rolesAutorises = [
                    'lecteur',
                    'gestionnaire',
                    'super_admin'
                ];

                if (!in_array($role, $rolesAutorises, true)) {
                    throw new Exception("Le rôle sélectionné est invalide.");
                }

                $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    "INSERT INTO admins (nom, email, mot_de_passe, role)
                     VALUES (:nom, :email, :mot_de_passe, :role)"
                );

                $stmt->execute([
                    'nom' => $nom,
                    'email' => $email,
                    'mot_de_passe' => $hash,
                    'role' => $role
                ]);

                $messageSucces = "Le compte administrateur a été créé.";

            } elseif ($action === 'role') {
                $id = filter_input(
                    INPUT_POST,
                    'id',
                    FILTER_VALIDATE_INT
                );
                $role = $_POST['role'] ?? '';

                $rolesAutorises = [
                    'lecteur',
                    'gestionnaire',
                    'super_admin'
                ];

                if (!$id || !in_array($role, $rolesAutorises, true)) {
                    throw new Exception("Les informations fournies sont invalides.");
                }

                if ($id === (int) $adminConnecte['id']) {
                    throw new Exception("Vous ne pouvez pas modifier votre propre rôle.");
                }

                $stmt = $pdo->prepare(
                    "UPDATE admins SET role = :role WHERE id = :id"
                );
                $stmt->execute([
                    'role' => $role,
                    'id' => $id
                ]);

                $messageSucces = "Le rôle a été mis à jour.";

            } elseif ($action === 'actif') {
                $id = filter_input(
                    INPUT_POST,
                    'id',
                    FILTER_VALIDATE_INT
                );

                $actif = isset($_POST['actif'])
                    ? (int) $_POST['actif']
                    : -1;

                if (!$id || !in_array($actif, [0, 1], true)) {
                    throw new Exception("Les informations fournies sont invalides.");
                }

                if ($id === (int) $adminConnecte['id'] && $actif === 0) {
                    throw new Exception("Vous ne pouvez pas désactiver votre propre compte.");
                }

                // Empêcher la désactivation du dernier super administrateur actif.
                $stmt = $pdo->prepare(
                    "SELECT role, actif FROM admins WHERE id = :id"
                );
                $stmt->execute(['id' => $id]);
                $cible = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cible) {
                    throw new Exception("Administrateur introuvable.");
                }

                if ($cible['role'] === 'super_admin'
                    && (int) $cible['actif'] === 1
                    && $actif === 0) {

                    $stmt = $pdo->query(
                        "SELECT COUNT(*) FROM admins
                         WHERE role = 'super_admin' AND actif = 1"
                    );

                    if ((int) $stmt->fetchColumn() <= 1) {
                        throw new Exception(
                            "Impossible de désactiver le dernier super administrateur actif."
                        );
                    }
                }

                $stmt = $pdo->prepare(
                    "UPDATE admins SET actif = :actif WHERE id = :id"
                );
                $stmt->execute([
                    'actif' => $actif,
                    'id' => $id
                ]);

                $messageSucces = $actif
                    ? "Le compte a été activé."
                    : "Le compte a été désactivé.";

            } elseif ($action === 'mot_de_passe') {
                $id = filter_input(
                    INPUT_POST,
                    'id',
                    FILTER_VALIDATE_INT
                );
                $nouveauMotDePasse = $_POST['nouveau_mot_de_passe'] ?? '';

                if (!$id || strlen($nouveauMotDePasse) < 12) {
                    throw new Exception(
                        "Le nouveau mot de passe doit contenir au moins 12 caractères."
                    );
                }

                $hash = password_hash(
                    $nouveauMotDePasse,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "UPDATE admins
                     SET mot_de_passe = :mot_de_passe,
                         tentatives_echouees = 0,
                         bloque_jusqu_a = NULL
                     WHERE id = :id"
                );

                $stmt->execute([
                    'mot_de_passe' => $hash,
                    'id' => $id
                ]);

                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare(
                        "SELECT id FROM admins WHERE id = :id"
                    );
                    $stmt->execute(['id' => $id]);

                    if (!$stmt->fetch()) {
                        throw new Exception("Administrateur introuvable.");
                    }
                }

                $messageSucces = "Le mot de passe a été réinitialisé.";

            } else {
                throw new Exception("Action inconnue.");
            }

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $messageErreur =
                    "Cette adresse e-mail est déjà utilisée.";
            } else {
                $messageErreur =
                    "Une erreur de base de données est survenue.";
            }
        } catch (Exception $e) {
            $messageErreur = $e->getMessage();
        }
    }
}

// Recharger la liste après une action
$stmt = $pdo->query(
    "SELECT id, nom, email, role, actif, date_creation
     FROM admins
     ORDER BY date_creation DESC"
);
$administrateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des administrateurs - MARIA-V</title>

    <link rel="stylesheet" href="/maria-v/assets/css/style.css">

    <style>
        .admins-page {
            max-width: 1200px;
            margin: 35px auto;
            padding: 20px;
        }

        .admins-page h1,
        .admins-page h2 {
            color: #183D32;
        }

        .admins-form,
        .admin-card {
            background: #FAF8F2;
            border: 1px solid #e5e2da;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .admins-form label,
        .admin-card label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
        }

        .admins-form input,
        .admins-form select,
        .admin-card input,
        .admin-card select {
            box-sizing: border-box;
            width: 100%;
            max-width: 500px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .admins-btn {
            display: inline-block;
            padding: 10px 14px;
            margin-top: 12px;
            background: #183D32;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }

        .admins-btn-danger {
            background: #a12626;
        }

        .admins-success,
        .admins-error {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .admins-success {
            background: #e4f5e8;
        }

        .admins-error {
            background: #ffe6e6;
        }

        .admin-card {
            background: #fff;
        }

        .admin-card h3 {
            color: #183D32;
            margin-top: 0;
        }

        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .admin-action {
            padding: 14px;
            border: 1px solid #e5e2da;
            border-radius: 8px;
        }

        .admin-etat {
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .admins-page {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<main class="admins-page">

    <h1>Gestion des administrateurs</h1>

    <p>
        Connecté en tant que
        <strong><?= e($adminConnecte['nom']) ?></strong>
        — Super administrateur
    </p>

    <p>
        <a class="admins-btn" href="dashboard.php">
            Retour au tableau de bord
        </a>
    </p>

    <?php if ($messageSucces !== ''): ?>
        <div class="admins-success">
            <?= e($messageSucces) ?>
        </div>
    <?php endif; ?>

    <?php if ($messageErreur !== ''): ?>
        <div class="admins-error">
            <?= e($messageErreur) ?>
        </div>
    <?php endif; ?>

    <section class="admins-form">
        <h2>Ajouter un administrateur</h2>

        <form method="POST" action="admins.php">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION['csrf_admins']) ?>">

            <input type="hidden" name="action" value="ajouter">

            <label for="nom">Nom complet *</label>
            <input type="text" id="nom" name="nom"
                   maxlength="100" required>

            <label for="email">Adresse e-mail *</label>
            <input type="email" id="email" name="email"
                   maxlength="150" required>

            <label for="mot_de_passe">Mot de passe temporaire *</label>
            <input type="password" id="mot_de_passe"
                   name="mot_de_passe" minlength="12"
                   autocomplete="new-password" required>

            <label for="role">Rôle *</label>
            <select id="role" name="role" required>
                <option value="lecteur">Lecteur</option>
                <option value="gestionnaire">Gestionnaire</option>
                <option value="super_admin">Super administrateur</option>
            </select>

            <button class="admins-btn" type="submit">
                Créer le compte
            </button>
        </form>
    </section>

    <section>
        <h2>Comptes existants</h2>

        <?php if (empty($administrateurs)): ?>
            <p>Aucun administrateur enregistré.</p>
        <?php else: ?>

            <?php foreach ($administrateurs as $admin): ?>

                <article class="admin-card">
                    <h3><?= e($admin['nom']) ?></h3>

                    <p><strong>E-mail :</strong>
                        <?= e($admin['email']) ?>
                    </p>

                    <p><strong>Rôle :</strong>
                        <?= e($admin['role']) ?>
                    </p>

                    <p>
                        <strong>État :</strong>
                        <span class="admin-etat">
                            <?= (int) $admin['actif'] === 1
                                ? 'Actif'
                                : 'Désactivé' ?>
                        </span>
                    </p>

                    <p><strong>Créé le :</strong>
                        <?= e($admin['date_creation']) ?>
                    </p>

                    <?php if ((int) $admin['id'] !== (int) $adminConnecte['id']): ?>

                        <div class="admin-actions">

                            <form class="admin-action"
                                  method="POST" action="admins.php">

                                <h4>Modifier le rôle</h4>

                                <input type="hidden" name="csrf_token"
                                       value="<?= e($_SESSION['csrf_admins']) ?>">

                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="id"
                                       value="<?= (int) $admin['id'] ?>">

                                <label for="role-<?= (int) $admin['id'] ?>">
                                    Nouveau rôle
                                </label>

                                <select name="role"
                                        id="role-<?= (int) $admin['id'] ?>">
                                    <option value="lecteur"
                                        <?= $admin['role'] === 'lecteur' ? 'selected' : '' ?>>
                                        Lecteur
                                    </option>

                                    <option value="gestionnaire"
                                        <?= $admin['role'] === 'gestionnaire' ? 'selected' : '' ?>>
                                        Gestionnaire
                                    </option>

                                    <option value="super_admin"
                                        <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>
                                        Super administrateur
                                    </option>
                                </select>

                                <button class="admins-btn" type="submit">
                                    Enregistrer le rôle
                                </button>
                            </form>

                            <form class="admin-action"
                                  method="POST" action="admins.php"
                                  onsubmit="return confirm('Confirmer le changement d’état de ce compte ?');">

                                <h4>État du compte</h4>

                                <input type="hidden" name="csrf_token"
                                       value="<?= e($_SESSION['csrf_admins']) ?>">

                                <input type="hidden" name="action" value="actif">
                                <input type="hidden" name="id"
                                       value="<?= (int) $admin['id'] ?>">

                                <input type="hidden" name="actif"
                                       value="<?= (int) $admin['actif'] === 1 ? 0 : 1 ?>">

                                <button class="admins-btn <?= (int) $admin['actif'] === 1 ? 'admins-btn-danger' : '' ?>"
                                        type="submit">
                                    <?= (int) $admin['actif'] === 1
                                        ? 'Désactiver le compte'
                                        : 'Activer le compte' ?>
                                </button>
                            </form>

                            <form class="admin-action"
                                  method="POST" action="admins.php"
                                  onsubmit="return confirm('Réinitialiser le mot de passe de ce compte ?');">

                                <h4>Réinitialiser le mot de passe</h4>

                                <input type="hidden" name="csrf_token"
                                       value="<?= e($_SESSION['csrf_admins']) ?>">

                                <input type="hidden" name="action"
                                       value="mot_de_passe">

                                <input type="hidden" name="id"
                                       value="<?= (int) $admin['id'] ?>">

                                <label for="nouveau-mdp-<?= (int) $admin['id'] ?>">
                                    Nouveau mot de passe
                                </label>

                                <input type="password"
                                       id="nouveau-mdp-<?= (int) $admin['id'] ?>"
                                       name="nouveau_mot_de_passe"
                                       minlength="12"
                                       autocomplete="new-password"
                                       required>

                                <button class="admins-btn" type="submit">
                                    Réinitialiser
                                </button>
                            </form>

                        </div>

                    <?php else: ?>

                        <p>
                            Il s'agit de votre compte actuel. Pour éviter
                            un blocage accidentel, les modifications de
                            rôle et d'état sont désactivées ici.
                        </p>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>
    </section>

</main>

</body>
</html>
