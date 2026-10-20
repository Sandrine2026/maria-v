<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
$titrePage = $t['apply_title'] ?? 'Faire ma demande d’aide';
if (empty($_SESSION['csrf_aide'])) $_SESSION['csrf_aide'] = bin2hex(random_bytes(32));
$erreur = '';
$succes = '';
$numeroSuivi = '';
$verification = null;
$typesAide = [];
$paysUE = ['Allemagne','Autriche','Belgique','Chypre','Danemark','Espagne','Estonie','Finlande','France','Grèce','Hongrie','Irlande','Italie','Lettonie','Lituanie','Luxembourg','Malte','Pays-Bas','Pologne','Portugal','République tchèque','Slovaquie','Slovénie','Suède','Royaume-Uni'];
try {
    $typesAide = $pdo->query("SELECT id, nom FROM types_aide WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

    // Les bases créées avant l'ajout des types peuvent avoir une table vide.
    // On insère les choix par défaut uniquement si aucun type n'existe.
    if (!$typesAide) {
        $pdo->beginTransaction();
        $nombreTypes = (int) $pdo->query("SELECT COUNT(*) FROM types_aide")->fetchColumn();
        if ($nombreTypes === 0) {
            $ajoutType = $pdo->prepare('INSERT IGNORE INTO types_aide (nom, description, actif) VALUES (:nom, :description, 1)');
            $typesParDefaut = [
                ['Santé', 'Aide liée aux besoins de santé'],
                ['Éducation', 'Aide liée à la scolarité et à la formation'],
                ['Alimentation', 'Aide liée aux besoins alimentaires'],
                ['Logement', 'Aide liée au logement et à l’hébergement'],
                ['Autre besoin social', 'Autre besoin social à examiner'],
            ];
            foreach ($typesParDefaut as [$nomType, $descriptionType]) {
                $ajoutType->execute(['nom' => $nomType, 'description' => $descriptionType]);
            }
        }
        $pdo->commit();
        $typesAide = $pdo->query("SELECT id, nom FROM types_aide WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('MARIA-V types aide: ' . $e->getMessage());
}
$numeroVerification = '';
$emailVerification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = $_POST['csrf_token'] ?? '';
    if (!is_string($jeton) || !hash_equals($_SESSION['csrf_aide'], $jeton)) {
        $erreur = 'La session du formulaire a expiré. Rechargez la page.';
    } else {
        $numeroVerification = trim((string)($_POST['numero_verification'] ?? ''));
        $emailVerification = trim((string)($_POST['email_verification'] ?? ''));
        try {
            $q = $pdo->prepare("SELECT v.id AS verification_id, v.numero_dossier, v.statut, v.resultat, d.id AS demandeur_id, d.nom, d.prenom, d.email, d.telephone FROM verifications v INNER JOIN demandeurs d ON d.id=v.demandeur_id WHERE v.numero_dossier=:numero AND LOWER(d.email)=LOWER(:email) LIMIT 1");
            $q->execute(['numero'=>$numeroVerification, 'email'=>$emailVerification]);
            $verification = $q->fetch();
        } catch (PDOException $e) { error_log('MARIA-V verification application: '.$e->getMessage()); $erreur = $t['application_error']; }
        if (!$erreur && (!$verification || $verification['statut'] !== 'validee' || $verification['resultat'] !== 'eligible')) {
            $erreur = $t['eligible_required'];
        }
        if (!$erreur && isset($_POST['soumettre_demande'])) {
            $typeAide = filter_var($_POST['type_aide_id'] ?? '', FILTER_VALIDATE_INT);
            $residence = trim((string)($_POST['residence_pays'] ?? ''));
            $situation = trim((string)($_POST['situation_familiale'] ?? ''));
            $besoin = trim((string)($_POST['besoin_estime'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $telephone = trim((string)($_POST['telephone'] ?? $verification['telephone']));
            $consentement = ($_POST['consentement'] ?? '') === '1';
            if (!$typeAide || !in_array($residence, $paysUE, true) || $situation === '' || $description === '' || $telephone === '' || !$consentement) {
                $erreur = 'Veuillez remplir tous les champs obligatoires et accepter le traitement des données.';
            } elseif (mb_strlen($situation, 'UTF-8') > 4000 || mb_strlen($description, 'UTF-8') < 20 || mb_strlen($description, 'UTF-8') > 10000 || mb_strlen($besoin, 'UTF-8') > 500 || mb_strlen($telephone, 'UTF-8') > 30) {
                $erreur = 'Vérifiez la longueur des informations saisies. La description doit contenir au moins 20 caractères.';
            } else {
                try {
                    $typeCheck = $pdo->prepare('SELECT id FROM types_aide WHERE id=:id LIMIT 1');
                    $typeCheck->execute(['id'=>$typeAide]);
                    if (!$typeCheck->fetch()) throw new RuntimeException('Le type d’aide sélectionné est invalide.');
                    $pdo->beginTransaction();
                    $numeroSuivi = genererNumeroSuivi('AIDE');
                    $descriptionAvecResidence = 'Pays de résidence déclaré : ' . $residence . "\n\n" . $description;
                    // La base existante ne possède pas de colonne residence_pays.
                    // Conserver cette information dans description évite toute migration SQL.
                    $insert = $pdo->prepare("INSERT INTO demandes_aide (numero_suivi, demandeur_id, verification_id, type_aide_id, situation_familiale, besoin_estime, description, email, telephone, consentement, statut, date_creation, date_modification) VALUES (:numero, :demandeur, :verification, :type, :situation, :besoin, :description, :email, :telephone, 1, 'reçue', NOW(), NOW())");
                    $insert->execute(['numero'=>$numeroSuivi,'demandeur'=>$verification['demandeur_id'],'verification'=>$verification['verification_id'],'type'=>$typeAide,'situation'=>$situation,'besoin'=>$besoin !== '' ? $besoin : null,'description'=>$descriptionAvecResidence,'email'=>$verification['email'],'telephone'=>$telephone]);
                    $demandeId = (int)$pdo->lastInsertId();
                    $hist = $pdo->prepare("INSERT INTO historique_statuts (demande_id, ancien_statut, nouveau_statut, commentaire, admin_id, date_creation) VALUES (:id, NULL, 'reçue', :commentaire, NULL, NOW())");
                    $hist->execute(['id'=>$demandeId, 'commentaire'=>'Création de la demande par le formulaire public.']);
                    $pdo->commit();
                    $_SESSION['csrf_aide'] = bin2hex(random_bytes(32));
                    $succes = $t['application_success'];
                    $verification = null;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('MARIA-V demande aide: '.$e->getMessage());
                    $erreur = $e instanceof RuntimeException ? $e->getMessage() : $t['application_error'];
                }
            }
        }
    }
}
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main class="container aid-application-page" style="max-width:980px;padding-top:2rem;padding-bottom:3rem">
  <section class="section-heading">
    <p class="eyebrow">Fondation MARIA-V</p>
    <h1><?= e($t['apply_title']) ?></h1>
    <p><?= e($t['apply_intro']) ?></p>
  </section>
  <div class="aid-process-note"><strong>Comment procéder ?</strong><ol><li>Entrez le numéro de votre dossier d’éligibilité et l’adresse e-mail utilisée lors de l’inscription.</li><li>Si votre éligibilité a été validée, le formulaire de demande d’aide s’affichera.</li><li>Après l’envoi, conservez votre numéro de suivi. L’administrateur examinera ensuite votre demande d’aide.</li></ol></div>
  <?php if ($erreur !== ''): ?><div class="alert alert-error" role="alert"><p><?= e($erreur) ?></p></div><?php endif; ?>
  <?php if ($succes !== ''): ?><div class="alert alert-success" role="status"><p><?= e($succes) ?></p><strong><?= e($numeroSuivi) ?></strong><p><a href="/maria-v/public/tracking.php?lang=<?= e($langue) ?>"><?= e($t['suivre_demande']) ?></a></p></div>
  <?php else: ?>
  <form method="post" class="contact-form aid-application-form" action="/maria-v/public/faire-demande-aide.php?lang=<?= e($langue) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_aide']) ?>">
    <fieldset class="aid-form-section"><legend>1. Vérification de votre éligibilité</legend>
      <p class="aid-section-help">Renseignez les informations exactement comme lors de votre vérification.</p>
      <label for="numero_verification"><?= e($t['tracking_number']) ?> *</label>
      <input id="numero_verification" name="numero_verification" required maxlength="80" value="<?= e($numeroVerification) ?>">
      <label for="email_verification"><?= e($t['email']) ?> *</label>
      <input id="email_verification" type="email" name="email_verification" required maxlength="150" value="<?= e($emailVerification) ?>">
      <button class="btn btn-primary" type="submit" name="verifier_dossier" value="1">Vérifier mon éligibilité</button>
    </fieldset>
    <?php if ($verification && $verification['statut'] === 'validee' && $verification['resultat'] === 'eligible'): ?>
    <fieldset class="aid-form-section"><legend>2. Informations sur votre demande</legend>
      <p class="aid-section-help">Éligibilité validée pour : <strong><?= e($verification['prenom'] . ' ' . $verification['nom']) ?></strong></p>
      <label for="type_aide_id"><?= e($t['aid_type']) ?> *</label>
      <select id="type_aide_id" name="type_aide_id" required><option value=""><?= e($t['select_type']) ?></option><?php foreach ($typesAide as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e($type['nom']) ?></option><?php endforeach; ?></select>
      <?php if (!$typesAide): ?><p><?= e($t['no_types']) ?></p><?php endif; ?>
      <label for="residence_pays">Pays de résidence (Union européenne) *</label>
      <select id="residence_pays" name="residence_pays" required><option value="">Sélectionnez votre pays de résidence</option><?php foreach ($paysUE as $pays): ?><option value="<?= e($pays) ?>" <?= (($_POST['residence_pays'] ?? '') === $pays) ? 'selected' : '' ?>><?= e($pays) ?></option><?php endforeach; ?></select>
      <label for="situation_familiale"><?= e($t['family_situation']) ?> *</label>
      <textarea id="situation_familiale" name="situation_familiale" required maxlength="4000" rows="4"><?= e($_POST['situation_familiale'] ?? '') ?></textarea>
      <label for="besoin_estime"><?= e($t['estimated_need']) ?></label>
      <input id="besoin_estime" name="besoin_estime" maxlength="500" value="<?= e($_POST['besoin_estime'] ?? '') ?>">
      <label for="description"><?= e($t['description']) ?> *</label>
      <textarea id="description" name="description" required minlength="20" maxlength="10000" rows="6"><?= e($_POST['description'] ?? '') ?></textarea>
      <label for="telephone"><?= e($t['telephone']) ?> *</label>
      <input id="telephone" type="tel" name="telephone" required maxlength="30" value="<?= e($_POST['telephone'] ?? $verification['telephone']) ?>">
      <label><input type="checkbox" name="consentement" value="1" required> <?= e($t['consent']) ?> *</label>
      <button class="btn btn-primary" type="submit" name="soumettre_demande" value="1" <?= !$typesAide ? 'disabled' : '' ?>><?= e($t['submit_application']) ?></button>
    </fieldset>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</main>
<style>
.aid-application-page .section-heading { margin-bottom: 1.5rem; }
.aid-application-page .eyebrow { color: #b18a3b; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.aid-process-note { margin: 1.5rem 0 2rem; padding: 1.25rem 1.5rem; border-left: 4px solid #c6a45c; border-radius: .5rem; background: #f7f4ec; }
.aid-process-note ol { margin: .75rem 0 0; padding-left: 1.4rem; }
.aid-process-note li { margin: .4rem 0; }
.aid-application-form { display: grid; gap: 1.25rem; }
.aid-form-section { min-width: 0; margin: 0; padding: 1.5rem; border: 1px solid #ded8ca; border-radius: .75rem; background: #fff; }
.aid-form-section legend { padding: 0 .5rem; color: #183d32; font-size: 1.15rem; font-weight: 700; }
.aid-section-help { margin: .2rem 0 1rem; color: #555; }
.aid-form-section label { display: block; margin-top: 1rem; font-weight: 600; }
.aid-form-section input:not([type=checkbox]), .aid-form-section select, .aid-form-section textarea { box-sizing: border-box; width: 100%; margin-top: .35rem; }
.aid-form-section input[type=checkbox] { margin-right: .45rem; }
.aid-form-section button { margin-top: 1.25rem; }
@media (max-width: 600px) { .aid-form-section { padding: 1rem; } }
</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>
