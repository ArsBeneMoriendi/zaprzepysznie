<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id === current_user()['id']) {
    flash('error', 'Nie możesz usunąć własnego konta administratora.');
    redirect('admin');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'Nie znaleziono użytkownika.');
    redirect('admin');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stmt = $pdo->prepare('SELECT image_path FROM recipes WHERE user_id = ? AND image_path IS NOT NULL');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $recipe) {
        delete_recipe_image($recipe['image_path']);
    }

    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    flash('success', 'Użytkownik i jego przepisy zostały usunięte.');
    redirect('admin.php');
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="card confirm-card">
    <h1>Usunąć użytkownika?</h1>
    <p>Czy na pewno chcesz usunąć użytkownika <strong><?= e($user['username']) ?></strong> oraz wszystkie jego przepisy?</p>

    <form method="post" class="actions">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <button class="btn danger" type="submit">Tak, usuń użytkownika</button>
        <a class="btn ghost" href="admin">Anuluj</a>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
