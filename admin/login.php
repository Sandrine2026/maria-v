<?php

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$erreur = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $motDePasse === '') {
        $erreur = 'Veuillez saisir une adresse e-mail valide et un mot de passe.';
    } else {

        $requete = $pdo->prepare(
            'SELECT id, nom, email, mot_de_passe, role,
                    tentatives_echouees, bloque_jusqu_a, actif
             FROM admins
             WHERE email = :email
             LIMIT 1'
        );

        $requete->execute([':email' => $email]);
        $admin = $requete->fetch();

        if (!$admin || !$admin['actif']) {
            $erreur = 'Identifiants incorrects ou compte indisponible.';
        } else {

            $maintenant = new DateTimeImmutable();

            if (
                !empty($admin['bloque_jusqu_a']) &&
                new DateTimeImmutable($admin['bloque_jusqu_a']) > $maintenant
            ) {
                $erreur = 'Compte temporairement bloqué. Réessayez plus tard.';

            } elseif (password_verify($motDePasse, $admin['mot_de_passe'])) {

                session_regenerate_id(true);

                $pdo->prepare(
                    'UPDATE admins
                     SET tentatives_echouees = 0,
                         bloque_jusqu_a = NULL
                     WHERE id = :id'
                )->execute([':id' => $admin['id']]);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_nom'] = $admin['nom'];
                $_SESSION['admin_role'] = $admin['role'];

                header('Location: dashboard.php');
                exit;

            } else {

                $tentatives = (int) $admin['tentatives_echouees'] + 1;

                if ($tentatives >= 5) {
                    $bloqueJusqua = date(
                        'Y-m-d H:i:s',
                        time() + 15 * 60
                    );

                    $pdo->prepare(
                        'UPDATE admins
                         SET tentatives_echouees = 0,
                             bloque_jusqu_a = :blocage
                         WHERE id = :id'
                    )->execute([
                        ':blocage' => $bloqueJusqua,
                        ':id' => $admin['id']
                    ]);
                } else {
                    $pdo->prepare(
                        'UPDATE admins
                         SET tentatives_echouees = :tentatives
                         WHERE id = :id'
                    )->execute([
                        ':tentatives' => $tentatives,
                        ':id' => $admin['id']
                    ]);
                }

                $erreur = 'Identifiants incorrects ou compte indisponible.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion administrateur | MARIA-V</title>
    <link rel="stylesheet" href="/maria-v/assets/css/style.css">
</head>
<body>

<main class="admin-login-page">
    <div class="admin-login-card">

        <div class="admin-logo">
            <img
                src="/maria-v/assets/images/logo-maria-v.png"
                alt="Logo MARIA-V"
            >
        </div>

        <p class="surtitre">Espace sécurisé</p>
        <h1>Administration MARIA-V</h1>

        <p class="login-description">
            Connectez-vous pour gérer les activités de la fondation.
        </p>

        <?php if ($erreur !== ''): ?>
            <div class="alert-error" role="alert">
                <?= e($erreur) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="username"
                    value="<?= e($email) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input
                    type="password"
                    id="mot_de_passe"
                    name="mot_de_passe"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary form-submit">
                Se connecter
            </button>

        </form>

        <a href="/maria-v/public/index.php" class="back-home">
            ← Retour au site
        </a>

    </div>
</main>

</body>
</html>

