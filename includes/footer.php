<?php require_once __DIR__ . '/language.php'; ?>
<footer class="site-footer">
  <div class="container footer-content">
    <div class="footer-brand">
      <h3>MARIA-V</h3>
      <p><?= e($t['tagline']) ?></p>
    </div>
    <div class="footer-links">
      <h4><?= e($t['contact']) ?></h4>
      <a href="/maria-v/public/contact.php?lang=<?= e($langue) ?>"><?= e($t['contact']) ?></a>
      <a href="/maria-v/public/legal.php?lang=<?= e($langue) ?>#mentions-legales"><?= e($t['mentions_legales']) ?></a>
      <a href="/maria-v/public/legal.php?lang=<?= e($langue) ?>#confidentialite"><?= e($t['politique_confidentialite']) ?></a>
    </div>
  </div>
  <div class="footer-bottom"><p>&copy; <?= date('Y') ?> MARIA-V. <?= e($t['droits_reserves']) ?></p></div>
</footer>
</body>
</html>
