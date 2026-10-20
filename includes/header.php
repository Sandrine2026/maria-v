<?php
require_once __DIR__ . '/language.php';
require_once __DIR__ . '/functions.php';
$titrePage = $titrePage ?? 'MARIA-V';
$descriptionPage = $descriptionPage ?? ($t['tagline'] ?? ' MARIA-V');
?>
<!DOCTYPE html>
<html lang="<?= e($langue) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titrePage) ?> | MARIA-V</title>
    <meta name="description" content="<?= e($descriptionPage) ?>">
    <meta name="robots" content="index,follow">
    <link rel="stylesheet" href="/maria-v/assets/css/style.css">
    <link rel="stylesheet" href="/maria-v/assets/css/navbar-fixes.css">
</head>
<body>
