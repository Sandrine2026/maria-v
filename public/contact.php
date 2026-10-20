<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$titrePage = "Contact - MARIA-V";

if (empty($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}

$erreurs = [];
$succes = '';

$nom = '';
$prenom = '';
$email = '';
$telephone = '';
$sujet = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $jeton = $_POST['csrf_token'] ?? '';

    if (!is_string($jeton) ||
        !hash_equals($_SESSION['csrf_contact'], $jeton)) {
        $erreurs[] = "Votre session a expiré. Rechargez la page et réessayez.";
    }

    if ($nom === '' || mb_strlen($nom) > 100) {
        $erreurs[] = "Veuillez saisir un nom de 100 caractères maximum.";
    }

    if ($prenom === '' || mb_strlen($prenom) > 100) {
        $erreurs[] = "Veuillez saisir un prénom de 100 caractères maximum.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 190) {
        $erreurs[] = "Veuillez saisir une adresse e-mail valide.";
    }

    if (mb_strlen($telephone) > 30) {
        $erreurs[] = "Le numéro de téléphone est trop long.";
    }

    if ($sujet === '' || mb_strlen($sujet) > 150) {
        $erreurs[] = "Veuillez saisir un sujet de 150 caractères maximum.";
    }

    if ($message === '' || mb_strlen($message) > 5000) {
        $erreurs[] = "Le message est obligatoire et ne doit pas dépasser 5000 caractères.";
    }

    if (empty($erreurs)) {
        try {
            $sql = "INSERT INTO contacts
                    (nom, prenom, email, telephone, sujet, message,
                     statut, date_creation)
                    VALUES
                    (:nom, :prenom, :email, :telephone, :sujet, :message,
                     'nouveau', NOW())";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone !== '' ? $telephone : null,
                'sujet' => $sujet,
                'message' => $message
            ]);

            $succes = "Votre message a été envoyé avec succès. Merci d'avoir contacté MARIA-V.";

            $nom = '';
            $prenom = '';
            $email = '';
            $telephone = '';
            $sujet = '';
            $message = '';

            $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));

        } catch (PDOException $e) {
            error_log("Erreur formulaire contact MARIA-V : " . $e->getMessage());
            $erreurs[] = "Une erreur technique empêche l'envoi du message. Veuillez réessayer.";
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="contact-page">

    <section class="contact-hero">
        <div class="contact-hero-content">
            <span class="contact-label">MARIA-V</span>
            <h1>Contactez-nous</h1>
            <p>
                Une question ou besoin d'informations ?
                Envoyez-nous votre message. Notre équipe pourra
                en prendre connaissance.
            </p>
        </div>
    </section>

    <section class="contact-section">

        <div class="contact-intro">
            <h2>Nous sommes à votre écoute</h2>
            <p>
                Remplissez le formulaire ci-dessous en indiquant
                clairement l'objet de votre demande.
            </p>
        </div>

        <?php if ($succes !== ''): ?>
            <div class="contact-alert contact-success" role="status">
                <?= e($succes) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($erreurs)): ?>
            <div class="contact-alert contact-error" role="alert">
                <ul>
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><?= e($erreur) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST"
              action="contact.php"
              class="contact-form">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_contact']) ?>">

            <div class="contact-form-grid">

                <div class="contact-field">
                    <label for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom"
                           maxlength="100" required
                           autocomplete="family-name"
                           value="<?= e($nom) ?>">
                </div>

                <div class="contact-field">
                    <label for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom"
                           maxlength="100" required
                           autocomplete="given-name"
                           value="<?= e($prenom) ?>">
                </div>

                <div class="contact-field">
                    <label for="email">Adresse e-mail *</label>
                    <input type="email" id="email" name="email"
                           maxlength="190" required
                           autocomplete="email"
                           value="<?= e($email) ?>">
                </div>

                <div class="contact-field">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone"
                           maxlength="30"
                           autocomplete="tel"
                           value="<?= e($telephone) ?>">
                </div>

                <div class="contact-field contact-field-full">
                    <label for="sujet">Sujet *</label>
                    <input type="text" id="sujet" name="sujet"
                           maxlength="150" required
                           value="<?= e($sujet) ?>">
                </div>

                <div class="contact-field contact-field-full">
                    <label for="message">Votre message *</label>
                    <textarea id="message" name="message"
                              rows="7" maxlength="5000"
                              required><?= e($message) ?></textarea>
                </div>

            </div>

            <p class="contact-required">* Champs obligatoires</p>

            <button type="submit" class="contact-submit">
                Envoyer mon message
            </button>

        </form>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
