<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    return !empty($_SESSION['user']['is_admin']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Musisz się zalogować.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        flash('error', 'Brak dostępu do panelu administratora.');
        redirect('save_recipe.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'is_admin' => (int) $user['is_admin'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
