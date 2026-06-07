<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

if (is_admin()) {
    $stmt = $pdo->prepare('SELECT r.*, u.username FROM recipes r JOIN users u ON u.id = r.user_id WHERE r.id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('SELECT r.*, u.username FROM recipes r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.user_id = ?');
    $stmt->execute([$id, current_user()['id']]);
}

$recipe = $stmt->fetch();

if (!$recipe) {
    flash('error', 'Nie znaleziono przepisu albo nie masz do niego dostępu.');
    redirect('przepisy');
}

require_once __DIR__ . '/includes/header.php';
?>

<article class="card recipe-detail">
    <div class="page-title-row">
        <div>
            <span class="badge"><?= e($recipe['category']) ?></span>
            <h1><?= e($recipe['title']) ?></h1>
            <p class="muted">
                Dodano: <?= e(date('d.m.Y H:i', strtotime($recipe['created_at']))) ?>
                <?php if (is_admin()): ?>
                    przez <?= e($recipe['username']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="edytuj/<?= (int) $recipe['id'] ?>">Edytuj</a>
            <a class="btn danger" href="usun-przepis/<?= (int) $recipe['id'] ?>">Usuń</a>
        </div>
    </div>

    <?php if ($recipe['image_path']): ?>
        <img class="detail-image" src="<?= e($recipe['image_path']) ?>" alt="<?= e($recipe['title']) ?>">
    <?php endif; ?>

    <?php if ($recipe['source_url']): ?>
        <p>Źródło: <a href="<?= e($recipe['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($recipe['source_url']) ?></a></p>
    <?php endif; ?>

    <section class="recipe-section">
        <h2>Składniki</h2>
        <pre><?= e($recipe['ingredients']) ?></pre>
    </section>

    <section class="recipe-section">
        <h2>Proces przygotowania</h2>
        <pre><?= e($recipe['instructions']) ?></pre>
    </section>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>