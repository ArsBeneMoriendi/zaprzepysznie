<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('save_recipe.php');
}

redirect('login.php');
