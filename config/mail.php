<?php
declare(strict_types=1);
/**
 * Configuration de messagerie — modèle sécurisé.
 * Renseignez les valeurs dans les variables d'environnement du serveur.
 * Ne placez jamais un vrai mot de passe SMTP dans un fichier partagé ou un ZIP.
 * La fonction d'envoi par SMTP n'est pas activée dans cette version : voir CONFIGURATION-EMAIL-CONTRAT.txt.
 */
return [
    'enabled' => filter_var(getenv('MARIA_V_MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'host' => getenv('MARIA_V_SMTP_HOST') ?: '',
    'port' => (int)(getenv('MARIA_V_SMTP_PORT') ?: 587),
    'encryption' => getenv('MARIA_V_SMTP_ENCRYPTION') ?: 'tls',
    'username' => getenv('MARIA_V_SMTP_USERNAME') ?: '',
    'password' => getenv('MARIA_V_SMTP_PASSWORD') ?: '',
    'from_email' => getenv('MARIA_V_FROM_EMAIL') ?: 'www.sandrine93@gmail.com',
    'from_name' => getenv('MARIA_V_FROM_NAME') ?: 'Fondation MARIA - V',
];
