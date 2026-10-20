<?php
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/functions.php';
$pageActuelle = basename($_SERVER['PHP_SELF'] ?? '');
// Conserver les autres paramètres de l'URL lorsque la langue change.
$parametresLangue = $_GET;
unset($parametresLangue['lang']);
$liensMenu = [
    'index.php' => ['accueil', '/maria-v/public/index.php'],
    'about.php' => ['a_propos', '/maria-v/public/about.php'],
    'actions.php' => ['nos_actions', '/maria-v/public/actions.php'],
    'actualites.php' => ['actualites', '/maria-v/public/actualites.php'],
    'contact.php' => ['contact', '/maria-v/public/contact.php'],
];
?>
<header class="site-header">
  <div class="container navbar">
    <a href="/maria-v/public/index.php?lang=<?= e($langue) ?>" class="logo" aria-label="MARIA-V - <?= e($t['accueil']) ?>">
      <img src="/maria-v/assets/images/logo-maria-v.png" alt="Logo MARIA-V">
      <span>MARIA-V</span>
    </a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" aria-label="Menu">☰</button>
    <nav class="nav-menu" id="menu-principal" aria-label="Navigation principale">
      <?php foreach ($liensMenu as $fichier => [$cle, $url]): ?>
        <a href="<?= e($url) ?>?lang=<?= e($langue) ?>" class="<?= $pageActuelle === $fichier ? 'active' : '' ?>" <?= $pageActuelle === $fichier ? 'aria-current="page"' : '' ?>><?= e($t[$cle] ?? $cle) ?></a>
      <?php endforeach; ?>
      <a class="nav-cta" href="/maria-v/public/eligibility.php?lang=<?= e($langue) ?>"><?= e($t['verifier_eligibilite']) ?></a>
      <a href="/maria-v/public/faire-demande-aide.php?lang=<?= e($langue) ?>"><?= e($t['faire_demande']) ?></a>
      <a href="/maria-v/public/tracking.php?lang=<?= e($langue) ?>"><?= e($t['suivre_demande']) ?></a>
    </nav>
    <div class="language-selector" aria-label="<?= e($t['language']) ?>">
      <?php foreach (['fr'=>'FR','en'=>'EN','de'=>'DE','es'=>'ES','el'=>'EL'] as $code => $label): ?>
        <a href="<?= e(basename($_SERVER['PHP_SELF'] ?? 'index.php')) ?>?<?= e(http_build_query(array_merge($parametresLangue, ['lang' => $code]))) ?>" lang="<?= e($code) ?>" <?= $langue === $code ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const button = document.querySelector('.nav-toggle');
  const menu = document.querySelector('#menu-principal');
  if (button && menu) button.addEventListener('click', function () {
    const expanded = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!expanded));
    menu.classList.toggle('is-open', !expanded);
  });
});
</script>
