<?php
require_once __DIR__ . '/../includes/language.php';
header('Location: /maria-v/public/actualites.php?lang=' . rawurlencode($langue), true, 302);
exit;
