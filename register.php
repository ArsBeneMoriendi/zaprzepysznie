<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('zapisz-przepis');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordRepeat = $_POST['password_repeat'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_ąćęłńóśźżĄĆĘŁŃÓŚŹŻ.-]{3,30}$/u', $username)) {
        $errors[] = 'Login powinien mieć 3-30 znaków. Dozwolone: litery, cyfry, _, ., -.';
    }

    if (mb_strlen($password) < 6) {
        $errors[] = 'Hasło powinno mieć minimum 6 znaków.';
    }

    if ($password !== $passwordRepeat) {
        $errors[] = 'Hasła nie są takie same.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $errors[] = 'Taki login jest już zajęty.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, $hash]);

            flash('success', 'Konto utworzone. Możesz się zalogować.');
            redirect('logowanie');
        }
    }
}

$body_class = 'auth-page';
require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
    <h1>Rejestracja</h1>
    <p class="muted">Utwórz konto i zacznij budować swoją książkę kucharską.</p>

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
        <label>
            Powtórz hasło
            <input type="password" name="password_repeat" required>
        </label>
        <button class="btn" type="submit">Zarejestruj</button>
    </form>

    <p>Masz już konto? <a href="logowanie">Zaloguj się</a>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
