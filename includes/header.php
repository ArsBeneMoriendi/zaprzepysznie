<?php
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="/zaprzepysznie/assets/img/favicon.png?v=1">
    <link rel="shortcut icon" type="image/png" href="/zaprzepysznie/assets/img/favicon.png?v=1">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= is_logged_in() ? 'save_recipe.php' : 'login.php' ?>">
        <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
        <span>
            <strong><?= e(APP_NAME) ?></strong>
            <small><?= e(APP_SLOGAN) ?></small>
        </span>
    </a>

    <nav class="main-nav">
        <?php if (is_logged_in()): ?>
            <a href="save_recipe.php">Zapisz przepis</a>
            <a href="my_recipes.php">Moje przepisy</a>
            <?php if (is_admin()): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="logout.php">Wyloguj</a>
        <?php else: ?>
            <a href="login.php">Logowanie</a>
            <a href="register.php">Rejestracja</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">
    <?php show_flash(); ?>
