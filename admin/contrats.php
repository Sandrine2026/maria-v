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

        if ($action === 'enregistrer') {
            $montantAide = filter_var($_POST['montant_aide'] ?? '', FILTER_VALIDATE_FLOAT);
            $pourcentage = filter_var($_POST['pourcentage_apport'] ?? '', FILTER_VALIDATE_FLOAT);
            $statutContrat = (string)($_POST['statut_contrat'] ?? 'prepare');
            $statutApport = (string)($_POST['statut_apport'] ?? 'non_du');
            $montantRecu = filter_var($_POST['montant_apport_recu'] ?? '0', FILTER_VALIDATE_FLOAT);
            $notes = trim((string)($_POST['notes'] ?? ''));
            if ($montantAide === false || $montantAide <= 0) throw new RuntimeException('Le montant de l’aide doit être supérieur à zéro.');
            if ($pourcentage === false || $pourcentage < 0 || $pourcentage > 100) throw new RuntimeException('Le pourcentage d’apport doit être compris entre 0 et 100.');
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
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Contrats et apports | MARIA-V</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<header class="admin-header"><div><h1>MARIA-V</h1><p>Gestion des contrats et apports</p></div><div><a class="btn btn-outline" href="dashboard.php">Tableau de bord</a> <a class="btn btn-outline" href="logout.php">Déconnexion</a></div></header>
<main class="admin-dashboard">
    <section class="admin-welcome"><h2>Contrats d’aide et apports</h2><p>Cette page permet de préparer un contrat pour une demande acceptée et de suivre l’apport de manière administrative. Aucun paiement en ligne n’est effectué ici.</p></section>
    <?php if ($message !== ''): ?><div class="alert alert-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($erreur !== ''): ?><div class="alert alert-error" role="alert"><?= e($erreur) ?></div><?php endif; ?>
    <section class="admin-panel"><h3>Créer ou modifier un contrat</h3>
    <?php if (!$demandes): ?><p>Aucune demande d’aide acceptée n’est disponible pour le moment.</p><?php else: ?>
    <form method="get" class="contact-form"><label for="demande_id">Demande acceptée</label><select name="demande_id" id="demande_id" required><option value="">Choisir une demande</option><?php foreach ($demandes as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($demandeSelectionnee['id'] ?? 0)===(int)$d['id'])?'selected':'' ?>><?= e($d['numero_suivi'].' — '.$d['prenom'].' '.$d['nom'].' — '.$d['email']) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Charger la demande</button></form>
    <?php endif; ?>
    <?php if ($demandeSelectionnee && ($demandeSelectionnee['statut'] ?? '') === 'acceptee'): ?>
    <form method="post" class="contact-form" id="form-contrat"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_global']) ?>"><input type="hidden" name="demande_id" value="<?= (int)$demandeSelectionnee['id'] ?>"><input type="hidden" name="action" value="enregistrer">
      <p><strong>Bénéficiaire :</strong> <?= e($demandeSelectionnee['prenom'].' '.$demandeSelectionnee['nom']) ?><br><strong>Numéro de suivi :</strong> <?= e($demandeSelectionnee['numero_suivi']) ?><br><strong>E-mail :</strong> <?= e($demandeSelectionnee['email']) ?></p>
      <label for="montant_aide">Montant de l’aide (€)</label><input type="number" id="montant_aide" name="montant_aide" min="0.01" step="0.01" required value="<?= e(valeurFormulaire($demandeSelectionnee,'montant_aide')) ?>">
      <label for="pourcentage_apport">Pourcentage d’apport choisi par l’administrateur (%)</label><input type="number" id="pourcentage_apport" name="pourcentage_apport" min="0" max="100" step="0.01" required value="<?= e(valeurFormulaire($demandeSelectionnee,'pourcentage_apport','5')) ?>">
      <p>Montant d’apport calculé : <strong id="montant-apport-preview">—</strong></p>
      <label for="statut_contrat">État du contrat</label><select name="statut_contrat" id="statut_contrat"><?php foreach ($statutsContrat as $v=>$label): ?><option value="<?= e($v) ?>" <?= valeurFormulaire($demandeSelectionnee,'statut_contrat','prepare')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <label for="statut_apport">État administratif de l’apport</label><select name="statut_apport" id="statut_apport"><?php foreach ($statutsApport as $v=>$label): ?><option value="<?= e($v) ?>" <?= valeurFormulaire($demandeSelectionnee,'statut_apport','non_du')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <label for="montant_apport_recu">Montant de l’apport confirmé reçu (€)</label><input type="number" name="montant_apport_recu" id="montant_apport_recu" min="0" step="0.01" value="<?= e(valeurFormulaire($demandeSelectionnee,'montant_apport_recu','0')) ?>"><small>À renseigner uniquement après confirmation administrative hors ligne. Aucun paiement n’est collecté sur le site.</small>
      <label for="notes">Notes internes / modalités convenues</label><textarea name="notes" id="notes" rows="4" maxlength="5000"><?= e(valeurFormulaire($demandeSelectionnee,'notes')) ?></textarea>
      <button class="btn btn-primary" type="submit">Enregistrer le contrat</button>
    </form>
    <?php endif; ?></section>
    <section class="admin-panel"><h3>Contrats enregistrés</h3><?php if (!$contrats): ?><p>Aucun contrat enregistré pour le moment.</p><?php else: ?><div class="table-responsive"><table><thead><tr><th>Contrat</th><th>Bénéficiaire</th><th>Aide</th><th>Apport prévu</th><th>État du contrat</th><th>État de l’apport</th><th>Action</th></tr></thead><tbody><?php foreach ($contrats as $c): ?><tr><td><?= e($c['numero_contrat']) ?></td><td><?= e($c['prenom'].' '.$c['nom']) ?><br><small><?= e($c['numero_suivi']) ?></small></td><td><?= number_format((float)$c['montant_aide'],2,',',' ') ?> €</td><td><?= e((string)$c['pourcentage_apport']) ?> % — <?= number_format((float)$c['montant_apport'],2,',',' ') ?> €</td><td><?= e($statutsContrat[$c['statut_contrat']] ?? $c['statut_contrat']) ?></td><td><?= e($statutsApport[$c['statut_apport']] ?? $c['statut_apport']) ?></td><td><a href="?demande_id=<?= (int)$c['demande_id'] ?>">Modifier</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</main>
<script>
(function(){const aide=document.getElementById('montant_aide');const pct=document.getElementById('pourcentage_apport');const out=document.getElementById('montant-apport-preview');function calc(){if(!aide||!pct||!out)return;const a=parseFloat(aide.value),p=parseFloat(pct.value);out.textContent=Number.isFinite(a)&&Number.isFinite(p)&&a>=0&&p>=0&&p<=100?(Math.round(a*p)/100).toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2})+' €':'—';}if(aide&&pct){aide.addEventListener('input',calc);pct.addEventListener('input',calc);calc();}})();
</script>
</body></html>
