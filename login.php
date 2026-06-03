<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('save_recipe.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Podaj login i hasło.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            redirect('save_recipe.php');
        }

        $errors[] = 'Nieprawidłowy login lub hasło.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h1>Logowanie</h1>
    <p class="muted">Zaloguj się, aby zapisywać swoje przepisy.</p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>
            Login
            <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
        </label>
        <label>
            Hasło
            <input type="password" name="password" required>
        </label>
        <button class="btn" type="submit">Zaloguj</button>
    </form>

    <p>Nie masz konta? <a href="register.php">Zarejestruj się</a>.</p>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
