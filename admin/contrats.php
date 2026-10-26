<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['gestionnaire', 'super_admin']);

$message = '';
$erreur = '';
$contrats = [];
$demandes = [];
$demandeSelectionnee = null;
$statutsContrat = [
    'prepare' => 'Préparé', 'envoye' => 'Envoyé au bénéficiaire',
    'signe' => 'Signé', 'cloture' => 'Clôturé'
];
$statutsApport = [
    'non_du' => 'Pas encore dû', 'en_attente' => 'En attente',
    'partiel' => 'Partiellement reçu', 'recu' => 'Reçu (confirmé manuellement)',
    'dispense' => 'Dispensé'
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !verifierJetonCSRF($token)) {
            throw new RuntimeException('Session de sécurité expirée. Recharge la page puis réessaie.');
        }
        $action = (string)($_POST['action'] ?? '');
        $demandeId = filter_var($_POST['demande_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$demandeId || $demandeId < 1) {
            throw new RuntimeException('Demande d’aide invalide.');
        }
        $qDemande = $pdo->prepare("SELECT da.id, da.numero_suivi, da.statut, da.email, da.date_creation,
                d.nom, d.prenom
            FROM demandes_aide da INNER JOIN demandeurs d ON d.id = da.demandeur_id
            WHERE da.id = :id LIMIT 1");
        $qDemande->execute(['id' => $demandeId]);
        $demandeSelectionnee = $qDemande->fetch(PDO::FETCH_ASSOC);
        if (!$demandeSelectionnee) throw new RuntimeException('Demande d’aide introuvable.');
        if ($demandeSelectionnee['statut'] !== 'acceptee') {
            throw new RuntimeException('Un contrat ne peut être préparé que pour une demande acceptée.');
        }

        if ($action === 'envoyer_pdf') {
            // L'envoi ne peut se faire qu'après création du contrat et avec un PDF fourni par l'administrateur.
            $qContrat = $pdo->prepare('SELECT id, numero_contrat FROM contrats_aide WHERE demande_id = :id LIMIT 1');
            $qContrat->execute(['id' => $demandeId]);
            $contratMail = $qContrat->fetch(PDO::FETCH_ASSOC);
            if (!$contratMail) {
                throw new RuntimeException('Enregistre d’abord le contrat avant de l’envoyer.');
            }
            $destinataire = trim((string)($demandeSelectionnee['email'] ?? ''));
            if (!filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('L’adresse e-mail du bénéficiaire est absente ou invalide. Corrige-la dans le dossier avant l’envoi.');
            }
            if (!isset($_FILES['contrat_pdf']) || !is_array($_FILES['contrat_pdf'])) {
                throw new RuntimeException('Sélectionne le PDF du contrat à envoyer.');
            }
            $fichier = $_FILES['contrat_pdf'];
            if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($fichier['tmp_name'] ?? ''))) {
                throw new RuntimeException('Le téléversement du PDF a échoué. Choisis de nouveau le fichier PDF.');
            }
            if ((int)$fichier['size'] < 1 || (int)$fichier['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Le PDF doit peser moins de 10 Mo.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$fichier['tmp_name']);
            if ($mime !== 'application/pdf') {
                throw new RuntimeException('Le fichier choisi ne semble pas être un PDF valide.');
            }
            $mailConfig = require __DIR__ . '/../config/mail.php';
            if (empty($mailConfig['enabled'])) {
                throw new RuntimeException('L’envoi e-mail n’est pas encore activé. Configure config/mail.local.php selon le guide puis active le test.');
            }
            foreach (['host', 'username', 'password', 'from_email'] as $cleMail) {
                if (empty($mailConfig[$cleMail])) throw new RuntimeException('Configuration SMTP incomplète : vérifie le fichier local de configuration e-mail.');
            }
            if (!filter_var($mailConfig['from_email'], FILTER_VALIDATE_EMAIL) || !filter_var($mailConfig['username'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('L’adresse Gmail de configuration est invalide. Vérifie son orthographe exacte.');
            }
            require_once __DIR__ . '/../vendor/autoload.php';
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = (string)$mailConfig['host'];
                $mailer->SMTPAuth = true;
                $mailer->Username = (string)$mailConfig['username'];
                $mailer->Password = (string)$mailConfig['password'];
                $mailer->Port = (int)($mailConfig['port'] ?? 587);
                $mailer->CharSet = 'UTF-8';
                $mailer->SMTPSecure = ((string)($mailConfig['encryption'] ?? 'tls') === 'ssl')
                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->setFrom((string)$mailConfig['from_email'], (string)($mailConfig['from_name'] ?? 'Fondation MARIA - V'));
                $mailer->addAddress($destinataire, trim((string)$demandeSelectionnee['prenom'] . ' ' . (string)$demandeSelectionnee['nom']));
                $mailer->Subject = 'Convention d’aide financière — Fondation MARIA-V — ' . (string)$contratMail['numero_contrat'];
                $mailer->Body = "Bonjour " . trim((string)$demandeSelectionnee['prenom'] . ' ' . (string)$demandeSelectionnee['nom']) . ",\n\nVeuillez trouver en pièce jointe le contrat préparé pour votre dossier " . (string)$demandeSelectionnee['numero_suivi'] . ".\n\nVeuillez lire attentivement le document et nous contacter via les coordonnées officielles de la Fondation pour toute question. Ne procédez à aucun paiement sur la seule base de cet e-mail.\n\nCordialement,\nFondation MARIA-V";
                $mailer->AltBody = $mailer->Body;
                $nomFichier = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)($fichier['name'] ?? 'contrat.pdf')));
                $mailer->addAttachment((string)$fichier['tmp_name'], $nomFichier !== '' ? $nomFichier : 'contrat.pdf', 'base64', 'application/pdf');
                $mailer->send();
            } catch (Throwable $mailException) {
                error_log('MARIA-V envoi contrat ' . (string)$contratMail['numero_contrat'] . ' : ' . $mailException->getMessage());
                throw new RuntimeException('L’envoi n’a pas été confirmé par le serveur de messagerie. Le contrat n’a pas été marqué comme envoyé. Vérifie la configuration SMTP et réessaie.');
            }
            $majStatut = $pdo->prepare("UPDATE contrats_aide SET statut_contrat='envoye', admin_id=:admin WHERE demande_id=:demande");
            $majStatut->execute(['admin'=>(int)$adminConnecte['id'], 'demande'=>$demandeId]);
            $message = 'Contrat envoyé à ' . $destinataire . ' avec le PDF en pièce jointe.';
        } elseif ($action === 'enregistrer') {
            $montantAide = filter_var($_POST['montant_aide'] ?? '', FILTER_VALIDATE_FLOAT);
            $pourcentage = filter_var($_POST['pourcentage_apport'] ?? '', FILTER_VALIDATE_FLOAT);
            $statutContrat = (string)($_POST['statut_contrat'] ?? 'prepare');
            $statutApport = (string)($_POST['statut_apport'] ?? 'non_du');
            $montantRecu = filter_var($_POST['montant_apport_recu'] ?? '0', FILTER_VALIDATE_FLOAT);
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($montantAide === false || $montantAide <= 0) throw new RuntimeException('Le montant de l’aide doit être supérieur à zéro.');
            if ($pourcentage === false || $pourcentage < 3 || $pourcentage > 10) throw new RuntimeException('Le pourcentage d’apport doit être compris entre 3 % et 10 %. Vérifie aussi que cette clause a été validée juridiquement avant de l’appliquer.');
            if ($montantRecu === false || $montantRecu < 0) throw new RuntimeException('Le montant reçu doit être positif ou nul.');
            if ($montantRecu > round($montantAide * $pourcentage / 100, 2)) throw new RuntimeException('Le montant reçu ne peut pas dépasser l’apport prévu.');
            if (!array_key_exists($statutContrat, $statutsContrat) || !array_key_exists($statutApport, $statutsApport)) throw new RuntimeException('Statut de contrat ou d’apport invalide.');
            if (mb_strlen($notes, 'UTF-8') > 5000) throw new RuntimeException('Les notes ne doivent pas dépasser 5 000 caractères.');
            $montantApport = round($montantAide * $pourcentage / 100, 2);
            $qExiste = $pdo->prepare('SELECT id, numero_contrat FROM contrats_aide WHERE demande_id = :id LIMIT 1');
            $qExiste->execute(['id' => $demandeId]);
            $existant = $qExiste->fetch(PDO::FETCH_ASSOC);
            if ($existant) {
                $upd = $pdo->prepare("UPDATE contrats_aide SET montant_aide=:aide, pourcentage_apport=:pct,
                    montant_apport=:apport, statut_contrat=:sc, statut_apport=:sa,
                    montant_apport_recu=:recu, notes=:notes, admin_id=:admin WHERE demande_id=:demande");
                $upd->execute(['aide'=>$montantAide, 'pct'=>$pourcentage, 'apport'=>$montantApport,
                    'sc'=>$statutContrat, 'sa'=>$statutApport, 'recu'=>$montantRecu,
                    'notes'=>$notes !== '' ? $notes : null, 'admin'=>(int)$adminConnecte['id'], 'demande'=>$demandeId]);
                $numeroContrat = $existant['numero_contrat'];
                $message = 'Contrat mis à jour : ' . $numeroContrat;
            } else {
                $numeroContrat = genererNumeroSuivi('CTR');
                $ins = $pdo->prepare("INSERT INTO contrats_aide
                    (demande_id, numero_contrat, montant_aide, pourcentage_apport, montant_apport,
                     statut_contrat, statut_apport, montant_apport_recu, notes, admin_id)
                    VALUES (:demande, :numero, :aide, :pct, :apport, :sc, :sa, :recu, :notes, :admin)");
                $ins->execute(['demande'=>$demandeId, 'numero'=>$numeroContrat, 'aide'=>$montantAide,
                    'pct'=>$pourcentage, 'apport'=>$montantApport, 'sc'=>$statutContrat, 'sa'=>$statutApport,
                    'recu'=>$montantRecu, 'notes'=>$notes !== '' ? $notes : null, 'admin'=>(int)$adminConnecte['id']]);
                $message = 'Contrat créé : ' . $numeroContrat;
            }
        } elseif ($action !== 'charger') {
            throw new RuntimeException('Action inconnue.');
        }
    }

    $demandes = $pdo->query("SELECT da.id, da.numero_suivi, da.email, da.date_creation, d.nom, d.prenom
        FROM demandes_aide da INNER JOIN demandeurs d ON d.id=da.demandeur_id
        WHERE da.statut='acceptee' ORDER BY da.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    $idCharge = filter_input(INPUT_GET, 'demande_id', FILTER_VALIDATE_INT);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $idCharge = (int)($_POST['demande_id'] ?? 0);
    if ($idCharge) {
        $q = $pdo->prepare("SELECT da.id, da.numero_suivi, da.statut, da.email, d.nom, d.prenom,
            c.numero_contrat, c.montant_aide, c.pourcentage_apport, c.montant_apport,
            c.statut_contrat, c.statut_apport, c.montant_apport_recu, c.notes
            FROM demandes_aide da INNER JOIN demandeurs d ON d.id=da.demandeur_id
            LEFT JOIN contrats_aide c ON c.demande_id=da.id WHERE da.id=:id AND da.statut='acceptee' LIMIT 1");
        $q->execute(['id'=>$idCharge]);
        $demandeSelectionnee = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $contrats = $pdo->query("SELECT c.*, da.numero_suivi, da.email, d.nom, d.prenom
        FROM contrats_aide c INNER JOIN demandes_aide da ON da.id=c.demande_id
        INNER JOIN demandeurs d ON d.id=da.demandeur_id ORDER BY c.date_modification DESC, c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('MARIA-V contrats: ' . $e->getMessage());
    $erreur = $e instanceof RuntimeException ? $e->getMessage() : 'Impossible de charger les contrats. Vérifie que la migration database/migration_contrats_aide.sql a été exécutée dans phpMyAdmin.';
    error_log('MARIA-V détail erreur contrats : ' . $e->getMessage());
}
function valeurFormulaire(array|null $ligne, string $cle, string $defaut = ''): string {
    return (string)($ligne[$cle] ?? $defaut);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Contrats et apports | Fondation MARIA - V</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<header class="admin-header"><div><h1>Fondation MARIA - V</h1><p>Gestion des contrats et apports</p></div><div><a class="btn btn-outline" href="dashboard.php">Tableau de bord</a> <a class="btn btn-outline" href="logout.php">Déconnexion</a></div></header>
<main class="admin-dashboard">
    <section class="admin-welcome"><h2>Contrats d’aide et apports</h2><p>Cette page permet de préparer un contrat pour une demande acceptée et de suivre l’apport de manière administrative. Aucun paiement en ligne n’est effectué ici. Le taux saisi doit rester entre 3 % et 10 % et la clause de contribution doit être validée juridiquement avant toute demande au bénéficiaire.</p></section>
    <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($erreur !== ''): ?><div class="alert alert-error" role="alert"><?= e($erreur) ?></div><?php endif; ?>
    <section class="admin-panel"><h3>Créer ou modifier un contrat</h3>
    <?php if (!$demandes): ?><p>Aucune demande d’aide acceptée n’est disponible pour le moment.</p><?php else: ?>
    <form method="get" class="contact-form"><label for="demande_id">Demande acceptée</label><select name="demande_id" id="demande_id" required><option value="">Choisir une demande</option><?php foreach ($demandes as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($demandeSelectionnee['id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= e($d['numero_suivi'].' — '.$d['prenom'].' '.$d['nom'].' — '.$d['email']) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Charger la demande</button></form>
    <?php endif; ?>
    <?php if ($demandeSelectionnee && ($demandeSelectionnee['statut'] ?? '') === 'acceptee'): ?>
    <form method="post" class="contact-form" id="form-contrat"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_global']) ?>"><input type="hidden" name="demande_id" value="<?= (int)$demandeSelectionnee['id'] ?>"><input type="hidden" name="action" value="enregistrer">
      <p><strong>Bénéficiaire :</strong> <?= e($demandeSelectionnee['prenom'].' '.$demandeSelectionnee['nom']) ?><br><strong>Numéro de suivi :</strong> <?= e($demandeSelectionnee['numero_suivi']) ?><br><strong>E-mail :</strong> <?= e(str_replace('\\', '', (string)$demandeSelectionnee['email'])) ?></p>
      <label for="montant_aide">Montant de l’aide (€)</label><input type="number" id="montant_aide" name="montant_aide" min="0.01" step="0.01" required value="<?= e(valeurFormulaire($demandeSelectionnee,'montant_aide')) ?>">
      <label for="pourcentage_apport">Pourcentage d’apport choisi par l’administrateur (%)</label><input type="number" id="pourcentage_apport" name="pourcentage_apport" min="3" max="10" step="0.01" required value="<?= e(valeurFormulaire($demandeSelectionnee,'pourcentage_apport','5')) ?>">
      <p>Montant d’apport calculé : <strong id="montant-apport-preview">—</strong></p>
      <label for="statut_contrat">État du contrat</label><select name="statut_contrat" id="statut_contrat"><?php foreach ($statutsContrat as $v=>$label): ?><option value="<?= e($v) ?>" <?= valeurFormulaire($demandeSelectionnee,'statut_contrat','prepare')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <label for="statut_apport">État administratif de l’apport</label><select name="statut_apport" id="statut_apport"><?php foreach ($statutsApport as $v=>$label): ?><option value="<?= e($v) ?>" <?= valeurFormulaire($demandeSelectionnee,'statut_apport','non_du')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <label for="montant_apport_recu">Montant de l’apport confirmé reçu (€)</label><input type="number" name="montant_apport_recu" id="montant_apport_recu" min="0" step="0.01" value="<?= e(valeurFormulaire($demandeSelectionnee,'montant_apport_recu','0')) ?>"><small>À renseigner uniquement après confirmation administrative hors ligne. Aucun paiement n’est collecté sur le site.</small>
      <label for="notes">Notes internes / modalités convenues</label><textarea name="notes" id="notes" rows="4" maxlength="5000"><?= e(valeurFormulaire($demandeSelectionnee,'notes')) ?></textarea>
      <button class="btn btn-primary" type="submit">Enregistrer le contrat</button>
    </form>
    <?php endif; ?>
    <?php if ($demandeSelectionnee && ($demandeSelectionnee['statut'] ?? '') === 'acceptee' && !empty($demandeSelectionnee['numero_contrat'])): ?>
    <div class="admin-panel">
      <h3>Envoyer le contrat par e-mail</h3>
      <p>Prépare le contrat dans l’onglet du document, imprime-le en PDF, puis sélectionne ce PDF ci-dessous. L’envoi est réservé aux demandes acceptées et le statut ne passera à « Envoyé » qu’après confirmation SMTP.</p>
      <p><a class="btn btn-outline" target="_blank" rel="noopener" href="contrat-document.php?demande_id=<?= (int)$demandeSelectionnee['id'] ?>">Ouvrir / préparer le contrat</a></p>
      <form method="post" enctype="multipart/form-data" class="contact-form" onsubmit="return confirm('Envoyer le PDF sélectionné au bénéficiaire ? Vérifie le destinataire et le document avant de confirmer.');">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_global']) ?>">
        <input type="hidden" name="demande_id" value="<?= (int)$demandeSelectionnee['id'] ?>">
        <input type="hidden" name="action" value="envoyer_pdf">
        <label for="contrat_pdf">PDF du contrat (10 Mo maximum)</label>
        <input type="file" id="contrat_pdf" name="contrat_pdf" accept="application/pdf,.pdf" required>
        <p>Destinataire : <strong><?= e((string)$demandeSelectionnee['email']) ?></strong></p>
        <button class="btn btn-primary" type="submit">Envoyer le contrat par e-mail</button>
      </form>
    </div>
    <?php endif; ?>
    </section>
    <section class="admin-panel"><h3>Contrats enregistrés</h3><?php if (!$contrats): ?><p>Aucun contrat enregistré pour le moment.</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>Contrat</th><th>Bénéficiaire</th><th>Aide</th><th>Apport prévu</th><th>État du contrat</th><th>État de l’apport</th><th>Action</th></tr></thead><tbody><?php foreach ($contrats as $c): ?><tr><td><?= e($c['numero_contrat']) ?></td><td><?= e($c['prenom'].' '.$c['nom']) ?><br><small><?= e($c['numero_suivi']) ?></small><br><small><?= e(str_replace('\\', '', (string)$c['email'])) ?></small></td><td><?= number_format((float)$c['montant_aide'],2,',',' ') ?> €</td><td><?= e((string)$c['pourcentage_apport']) ?> % — <?= number_format((float)$c['montant_apport'],2,',',' ') ?> €</td><td><?= e($statutsContrat[$c['statut_contrat']] ?? $c['statut_contrat']) ?></td><td><?= e($statutsApport[$c['statut_apport']] ?? $c['statut_apport']) ?></td><td><a href="?demande_id=<?= (int)$c['demande_id'] ?>">Modifier</a><br><a href="contrat-document.php?demande_id=<?= (int)$c['demande_id'] ?>">Préparer le contrat</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main>
<script>
(function(){const aide=document.getElementById('montant_aide');const pct=document.getElementById('pourcentage_apport');const out=document.getElementById('montant-apport-preview');function calc(){if(!aide||!pct||!out)return;const a=parseFloat(aide.value),p=parseFloat(pct.value);out.textContent=Number.isFinite(a)&&Number.isFinite(p)&&a>0&&p>=3&&p<=10?(Math.round(a*p)/100).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+' €':'—';}if(aide&&pct){aide.addEventListener('input',calc);pct.addEventListener('input',calc);calc();}})();
</script>
</body></html>
