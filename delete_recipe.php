<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user()['id']]);
}

$recipe = $stmt->fetch();

if (!$recipe) {
    flash('error', 'Nie znaleziono przepisu albo nie masz do niego dostępu.');
    redirect('przepisy');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    delete_recipe_image($recipe['image_path']);
    $stmt = $pdo->prepare('DELETE FROM recipes WHERE id = ?');
    $stmt->execute([$id]);

    flash('success', 'Przepis został usunięty.');
    redirect(is_admin() ? 'admin' : 'przepisy');
}

require_once __DIR__ . '/includes/header.php';
?>
<section class="card confirm-card">
    <h1>Usunąć przepis?</h1>
    <p>Czy na pewno chcesz usunąć przepis <strong><?= e($recipe['title']) ?></strong>? Tej operacji nie da się cofnąć.</p>

    <form method="post" class="actions">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <button class="btn danger" type="submit">Tak, usuń</button>
        <a class="btn ghost" href="przepis/<?= (int) $id ?>">Anuluj</a>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


