<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/functions.php';
exigerRole(['gestionnaire', 'super_admin']);

$id = filter_input(INPUT_GET, 'demande_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Demande invalide.'); }
$q = $pdo->prepare("SELECT da.id, da.numero_suivi, da.statut, da.email, da.telephone, da.residence_pays,
        da.description, da.besoin_estime, da.date_creation, ta.nom AS type_aide,
        d.nom, d.prenom, d.email AS email_demandeur, d.telephone AS telephone_demandeur,
        c.numero_contrat, c.montant_aide, c.pourcentage_apport, c.montant_apport,
        c.notes, c.date_creation AS date_contrat, c.document_data
    FROM demandes_aide da
    INNER JOIN demandeurs d ON d.id=da.demandeur_id
    INNER JOIN types_aide ta ON ta.id=da.type_aide_id
    INNER JOIN contrats_aide c ON c.demande_id=da.id
    WHERE da.id=:id AND da.statut='acceptee' LIMIT 1");
$q->execute(['id'=>$id]);
$c = $q->fetch(PDO::FETCH_ASSOC);
if (!$c) { http_response_code(404); exit('Aucun contrat enregistré pour cette demande acceptée. Enregistrez d’abord le contrat dans la page Contrats et apports.'); }
$savedDocument = [];
if (!empty($c['document_data'])) {
    $decoded = json_decode((string)$c['document_data'], true);
    if (is_array($decoded)) $savedDocument = $decoded;
}
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function euros($v): string { return number_format((float)$v, 2, ',', ' ') . ' €'; }
$nomComplet = trim($c['prenom'].' '.$c['nom']);
$montantAide = euros($c['montant_aide']);
$montantApport = euros($c['montant_apport']);
$pourcentage = number_format((float)$c['pourcentage_apport'], 2, ',', ' ');
$aujourdhui = date('d/m/Y');
$edit = $_SERVER['REQUEST_METHOD'] === 'POST';
$documentFields = ['adresse_fondation','numero_enregistrement','representant','adresse_beneficiaire','nature_aide','prestataire','reference','periode','montant_depense','autres_aides','mode_versement','motif_apport','echeance_apport','lieu','contact_donnees','droit_applicable'];
if ($edit) {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !function_exists('verifierJetonCSRF') || !verifierJetonCSRF($token)) {
        http_response_code(403); exit('Session de sécurité expirée. Rechargez la page et réessayez.');
    }
    $toSave = [];
    foreach ($documentFields as $field) {
        $value = trim((string)($_POST[$field] ?? ''));
        if (mb_strlen($value, 'UTF-8') > 5000) { http_response_code(400); exit('Un champ est trop long.'); }
        $toSave[$field] = $value;
    }
    $json = json_encode($toSave, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $save = $pdo->prepare('UPDATE contrats_aide SET document_data = :data, date_modification = NOW() WHERE demande_id = :id');
    $save->execute(['data'=>$json, 'id'=>$id]);
    $savedDocument = $toSave;
}
$val = static function(string $key, string $default = '') use (&$savedDocument): string {
    return (string)($_POST[$key] ?? $savedDocument[$key] ?? $default);
};
$natureAide = $val('nature_aide', 'À préciser et à faire valider juridiquement');
$adresseFondation = $val('adresse_fondation', '1 avenue Marcel Pagnol, 13090 Aix-en-Provence, France');
$numeroEnregistrement = $val('numero_enregistrement', 'SIREN : 783 227 176 ; SIRET du siège : 783 227 176 00022 ; TVA intracommunautaire : FR03 783 227 176');
$representant = $val('representant', '[À compléter : nom et fonction du représentant autorisé]');
$prestataire = $val('prestataire', '[À compléter : créancier / prestataire]');
$reference = $val('reference', '[À compléter : référence de facture ou dette]');
$periode = $val('periode', '[À compléter : période concernée]');
$montantDepense = $val('montant_depense', '[À compléter : montant total justifié]');
$autresAides = $val('autres_aides', 'À confirmer par le bénéficiaire');
$modeVersement = $val('mode_versement', '[À compléter après validation]');
$motifApport = $val('motif_apport', '[Ne pas activer cette clause sans validation juridique préalable]');
$echeanceApport = $val('echeance_apport', '[À compléter uniquement si la clause est légalement validée]');
$lieu = $val('lieu', '[Lieu à compléter]');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Convention <?= h($c['numero_contrat']) ?> — Fondation MARIA - V</title>
<style>
:root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#eee;color:#222;font:14px/1.55 Arial,sans-serif}.toolbar{position:sticky;top:0;background:#183b32;color:white;padding:12px 18px;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}.toolbar button{border:0;padding:10px 16px;border-radius:5px;cursor:pointer;font-weight:bold}.toolbar .print{background:#e2c46f}.toolbar .back{background:#fff;color:#183b32;text-decoration:none;padding:10px 16px;border-radius:5px}.notice{max-width:900px;margin:16px auto;padding:12px 18px;background:#fff3cd;border:1px solid #e3ce8b}.sheet{max-width:900px;margin:20px auto;background:white;padding:48px 58px;box-shadow:0 2px 12px #bbb}.sheet h1{text-align:center;font-size:23px;margin-bottom:4px}.sheet h2{font-size:17px;margin-top:24px;border-bottom:1px solid #ddd;padding-bottom:5px}.sheet h3{font-size:14px;margin-bottom:4px}.brand{text-align:center;color:#8b6c1d;font-weight:bold;font-size:18px}.muted{color:#666;font-size:12px}.field{border:0;border-bottom:1px dashed #999;min-width:150px;font:inherit;color:#222;background:#fffde9;padding:1px 3px}.field.long{width:100%}textarea.field{width:100%;min-height:45px;border:1px dashed #aaa}.warning{border:1px solid #b68b31;background:#fff8e5;padding:12px;margin:18px 0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:35px;margin-top:35px}.signature{border:1px solid #aaa;min-height:150px;padding:12px}.signline{margin-top:70px;border-top:1px solid #777;padding-top:6px}table{border-collapse:collapse;width:100%;margin:15px 0}td,th{border:1px solid #bbb;padding:8px;text-align:left}td:first-child{width:38%;font-weight:bold}@media print{body{background:#fff;font-size:11pt}.toolbar,.notice{display:none}.sheet{margin:0;max-width:none;padding:12mm;box-shadow:none}.field{background:transparent;border-bottom:1px solid #777}.field,textarea{print-color-adjust:exact}.sheet h2{break-after:avoid}.signatures{break-inside:avoid}}@media(max-width:650px){.sheet{padding:22px}.grid,.signatures{grid-template-columns:1fr}}
</style></head><body>
<div class="toolbar"><strong>Document de travail — <?= h($c['numero_contrat']) ?></strong><button type="submit" form="contractForm" class="print">Enregistrer les corrections</button><button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button><a class="back" href="contrats.php?demande_id=<?= (int)$c['id'] ?>">Retour à l’administration</a></div>
<div class="notice"><strong>Attention :</strong> ce document est un modèle à vérifier. Les champs jaunes sont modifiables avant impression. Les coordonnées officielles de la Fondation, le droit applicable, la nature juridique de l’aide et toute contribution éventuelle doivent être validés par un conseil compétent. Ne pas envoyer pour signature avant validation. Cette page n’envoie pas automatiquement de courriel. Le document doit être relu et validé avant signature.</div>
<form method="post" id="contractForm"><input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_global'] ?? '') ?>"><article class="sheet">
<div class="brand">Fondation MARIA - V</div><h1>CONVENTION D’AIDE FINANCIÈRE</h1><p style="text-align:center">Convention n° <strong><?= h($c['numero_contrat']) ?></strong> — Dossier n° <strong><?= h($c['numero_suivi']) ?></strong></p>
<div class="warning"><strong>Modèle de travail — validation préalable obligatoire.</strong> Les informations entre crochets doivent être vérifiées. Ne pas exiger de contribution financière ni signer cette convention avant validation de la clause correspondante et des éléments juridiques applicables.</div>
<h2>Entre les soussignés</h2><p>La <strong>Fondation MARIA - V</strong>, dont le siège est situé <input class="field" name="adresse_fondation" value="<?= h($adresseFondation) ?>" />, enregistrée sous le numéro <input class="field" name="numero_enregistrement" value="<?= h($numeroEnregistrement) ?>" />, représentée par <input class="field" name="representant" value="<?= h($representant) ?>" />, ci-après « la Fondation » ;</p><p>et <strong><?= h($nomComplet) ?></strong>, adresse : <input class="field" name="adresse_beneficiaire" value="<?= h($val('adresse_beneficiaire', '[Adresse non disponible dans le dossier actuel]')) ?>" />, pays de résidence : <strong><?= h($c['residence_pays']) ?></strong>, téléphone : <strong><?= h($c['telephone'] ?: $c['telephone_demandeur']) ?></strong>, courriel : <strong><?= h($c['email'] ?: $c['email_demandeur']) ?></strong>, titulaire du dossier n° <strong><?= h($c['numero_suivi']) ?></strong>, ci-après « le Bénéficiaire ».</p><p>Ensemble désignés « les Parties ».</p>
<h2>Préambule</h2><p>Le Bénéficiaire a sollicité une aide concernant <strong><?= h($c['type_aide']) ?></strong>. Après examen du dossier, la Fondation décide d’accorder l’aide décrite dans la présente convention, sous réserve des conditions qui y sont précisées. Le préambule et l’annexe font partie intégrante de la convention.</p>
<h2>Article 1 — Objet et nature de l’aide</h2><p>La présente convention définit les conditions d’attribution, de versement et de suivi d’une aide financière ponctuelle. Nature juridique de l’aide : <input class="field long" name="nature_aide" value="<?= h($natureAide) ?>" />. Cette nature doit être confirmée et juridiquement validée avant signature. Aucun terme ne doit être interprété comme créant une obligation de remboursement qui n’est pas explicitement prévue et légalement valable. L’aide est affectée exclusivement à la dépense décrite au présent contrat. Toute modification de destination doit faire l’objet d’un accord écrit de la Fondation.</p>
<h2>Article 2 — Dépense prise en charge</h2><table><tr><td>Type d’aide</td><td><?= h($c['type_aide']) ?></td></tr><tr><td>Créancier / prestataire</td><td><input class="field long" name="prestataire" value="<?= h($prestataire) ?>" /></td></tr><tr><td>Référence de facture ou de dette</td><td><input class="field long" name="reference" value="<?= h($reference) ?>" /></td></tr><tr><td>Période concernée</td><td><input class="field long" name="periode" value="<?= h($periode) ?>" /></td></tr><tr><td>Montant total justifié</td><td><input class="field long" name="montant_depense" value="<?= h($montantDepense) ?>" /></td></tr><tr><td>Autres aides ou paiements déjà reçus</td><td><input class="field long" name="autres_aides" value="<?= h($autresAides) ?>" /></td></tr></table><p>La Fondation ne prend en charge que la dépense expressément approuvée dans cette convention.</p>
<h2>Article 3 — Montant et modalités de versement</h2><p>Montant maximal de l’aide approuvée : <strong><?= h($montantAide) ?></strong>. Mode de versement approuvé : <input class="field long" name="mode_versement" value="<?= h($modeVersement) ?>" />. Les modalités exactes, le calendrier et les justificatifs attendus sont communiqués par écrit. Les coordonnées bancaires ne doivent être ajoutées qu’après vérification indépendante.</p>
<h2>Article 4 — Conditions et déclarations du Bénéficiaire</h2><p>Le Bénéficiaire déclare que les informations et justificatifs transmis sont exacts et complets, que la dépense déclarée est réelle et qu’il a signalé les autres aides ou paiements couvrant cette dépense. Il informe la Fondation de tout changement important avant le versement. La Fondation peut demander les justificatifs strictement nécessaires à l’instruction, dans le respect des règles applicables de protection des données.</p>
<h2>Article 5 — Contribution financière éventuelle : clause à valider</h2><div class="warning">Aucune contribution financière ne doit être exigée sur la seule base de ce modèle. Toute contribution doit être justifiée, transparente et validée juridiquement avant d’être proposée au bénéficiaire.</div><p>Taux saisi dans le dossier administratif : <strong><?= h($pourcentage) ?> %</strong> ; montant calculé : <strong><?= h($montantApport) ?></strong>. Motif et base juridique : <input class="field long" name="motif_apport" value="<?= h($motifApport) ?>" />. Échéance éventuelle : <input class="field long" name="echeance_apport" value="<?= h($echeanceApport) ?>" />. Ces valeurs administratives ne signifient pas que la contribution est légalement exigible. Ne pas demander de paiement sur la base de cette convention sans validation préalable.</p>
<h2>Article 6 — Obligations de la Fondation et décision</h2><p>La Fondation examine le dossier selon des critères documentés et informe le Bénéficiaire de la décision et des éventuelles pièces manquantes. L’aide dépend des conditions de la présente convention et des moyens effectivement disponibles. La Fondation ne garantit aucune aide future.</p>
<h2>Article 7 — Confidentialité et protection des données</h2><p>Les données sont utilisées uniquement pour instruire, administrer, verser, contrôler et archiver l’aide et pour satisfaire aux obligations légales applicables. Elles sont accessibles aux seules personnes autorisées et conservées pendant la durée légalement nécessaire. Contact officiel relatif aux données : <input class="field long" name="contact_donnees" value="<?= h($val('contact_donnees', '[À compléter : contact officiel]')) ?>" />. La notice de confidentialité applicable doit être remise ou liée au présent contrat.</p>
<h2>Article 8 — Suspension et restitution</h2><p>Un versement non encore effectué peut être suspendu si des incohérences importantes ou des vérifications complémentaires le justifient. Une restitution ne peut être demandée que pour un motif prévu par la convention et permis par le droit applicable, après notification des motifs et possibilité raisonnable pour le Bénéficiaire de présenter ses observations.</p>
<h2>Article 9 — Égalité de traitement</h2><p>Les demandes sont examinées selon des critères objectifs et dans le respect des règles applicables en matière de non-discrimination.</p>
<h2>Article 10 — Notifications et modifications</h2><p>Toute notification est adressée aux coordonnées confirmées par les Parties. Toute modification substantielle doit être consignée par écrit et acceptée par les Parties.</p>
<h2>Article 11 — Droit applicable et règlement des différends</h2><p>Droit applicable : <input class="field long" name="droit_applicable" value="<?= h($val('droit_applicable', '[À confirmer par un conseil juridique]')) ?>" />. En cas de différend, les Parties recherchent d’abord une solution amiable. Les juridictions compétentes sont celles déterminées par le droit applicable.</p>
<h2>Article 12 — Entrée en vigueur et signature</h2><p>La convention prend effet à la date de signature des Parties. En cas de signature électronique, le procédé choisi doit permettre d’identifier les signataires, de recueillir leur consentement et de préserver l’intégrité du document. Chaque Partie reçoit une copie du document signé.</p><p>Fait à <input class="field" name="lieu" value="<?= h($lieu) ?>" />, le <?= h($aujourdhui) ?>.</p>
<h2>Annexe — Fiche synthétique de l’aide</h2><table><tr><td>Numéro de dossier</td><td><?= h($c['numero_suivi']) ?></td></tr><tr><td>Nom du bénéficiaire</td><td><?= h($nomComplet) ?></td></tr><tr><td>Type d’aide</td><td><?= h($c['type_aide']) ?></td></tr><tr><td>Pays de résidence</td><td><?= h($c['residence_pays']) ?></td></tr><tr><td>Montant de l’aide accordée</td><td><?= h($montantAide) ?></td></tr><tr><td>Contribution éventuelle (non validée)</td><td><?= h($pourcentage) ?> % — <?= h($montantApport) ?></td></tr><tr><td>Date de décision / préparation</td><td><?= h($aujourdhui) ?></td></tr><tr><td>Responsable autorisé</td><td><?= h($representant) ?></td></tr></table>
<p class="muted">Coordonnées de la Fondation : 1 avenue Marcel Pagnol, 13090 Aix-en-Provence, France — SIREN 783 227 176 — SIRET 783 227 176 00022 — TVA intracommunautaire FR03 783 227 176.</p><p class="muted">Avant signature : vérifier l’identité juridique de la Fondation, les pouvoirs du représentant, le droit applicable, la nature juridique de l’aide, la protection des données et toute contribution éventuelle. Le cachet officiel et la signature de la personne habilitée doivent être apposés par la Fondation ; aucun cachet ou paraphe fictif n’est généré par le site.</p>
<h2>Contrôles préalables à la signature</h2><ul><li>Identité juridique, adresse et numéro d’enregistrement de la Fondation vérifiés.</li><li>Pouvoir du représentant signataire vérifié.</li><li>Droit applicable, fiscalité, protection des données et procédure de recours validés localement.</li><li>Nature juridique de l’aide clarifiée.</li><li>Toute contribution éventuelle revue par un conseil juridique indépendant avant de demander un paiement au bénéficiaire.</li><li>Cachet officiel et signature apposés par la Fondation : aucun cachet ou paraphe fictif ne doit être utilisé.</li><li>Version linguistique choisie par le bénéficiaire vérifiée et remise avec les coordonnées officielles.</li></ul>
<div class="signatures"><div class="signature"><strong>POUR LA FONDATION MARIA-V</strong><p>Nom et fonction : <?= h($representant) ?></p><div class="signline">Signature autorisée et cachet officiel à apposer</div></div><div class="signature"><strong>LE / LA BÉNÉFICIAIRE</strong><p><?= h($nomComplet) ?></p><p>« Lu et approuvé »</p><div class="signline">Signature du bénéficiaire et date</div></div></div>
</article></form>
</body></html>
