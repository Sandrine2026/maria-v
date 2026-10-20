<?php

/**
 * Échapper une donnée avant de l'afficher dans HTML
 */
function e($valeur)
{
    return htmlspecialchars(
        $valeur ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Générer un numéro de suivi unique
 */
function genererNumeroSuivi($prefixe = 'MARIA')
{
    return $prefixe . '-' . date('Ymd') . '-' . strtoupper(
        substr(bin2hex(random_bytes(4)), 0, 8)
    );
}

/**
 * Rediriger vers une autre page
 */
function rediriger($url)
{
    header('Location: ' . $url);
    exit;
}