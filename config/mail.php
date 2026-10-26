<?php
declare(strict_types=1);

/**
 * Configuration SMTP. Les secrets doivent rester dans config/mail.local.php,
 * fichier local non partagé, ou dans les variables d'environnement du serveur.
 */
$config = [
    'enabled' => filter_var(getenv('MARIA_V_MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'host' => getenv('MARIA_V_SMTP_HOST') ?: 'smtp.gmail.com',
    'port' => (int)(getenv('MARIA_V_SMTP_PORT') ?: 587),
    'encryption' => getenv('MARIA_V_SMTP_ENCRYPTION') ?: 'tls',
    'username' => getenv('MARIA_V_SMTP_USERNAME') ?: '',
    'password' => getenv('MARIA_V_SMTP_PASSWORD') ?: '',
    'from_email' => getenv('MARIA_V_FROM_EMAIL') ?: '',
    'from_name' => getenv('MARIA_V_FROM_NAME') ?: 'Fondation MARIA-V',
];

$localFile = __DIR__ . '/mail.local.php';
if (is_file($localFile)) {
    $localConfig = require $localFile;
    if (is_array($localConfig)) $config = array_merge($config, $localConfig);
}
return $config;
