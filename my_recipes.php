<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$where = ['user_id = ?'];
$params = [current_user()['id']];

if ($search !== '') {
    $where[] = 'title LIKE ?';
    $params[] = '%' . $search . '%';
}

if ($category !== '' && is_valid_category($category)) {
    $where[] = 'category = ?';
    $params[] = $category;
}

$orderBy = match ($sort) {
    'title_asc' => 'title ASC',
    'title_desc' => 'title DESC',
    'oldest' => 'created_at ASC',
    default => 'created_at DESC',
};

$sql = 'SELECT * FROM recipes WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';

?>
<div class="page-title-row">
    <div>
        <h1>Moje przepisy</h1>
        <p class="muted">Wyszukuj, filtruj, sortuj, edytuj i usuwaj swoje przepisy.</p>
    </div>
    <a class="btn" href="zapisz-przepis">Dodaj przepis</a>
</div>

<section class="card">
    <form method="get" class="filters">
        <label>
            Nazwa
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Szukaj po nazwie">
        </label>
        <label>
            Kategoria
            <select name="category">
                <?php render_category_options($category, true); ?>
            </select>
        </label>
        <label>
            Sortowanie
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Od najnowszych</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Od najstarszych</option>
                <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Alfabetycznie A-Z</option>
                <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Alfabetycznie Z-A</option>
            </select>
        </label>
        <button class="btn secondary" type="submit">Zastosuj</button>
        <a class="btn ghost" href="przepisy">Wyczyść</a>
    </form>
</section>

<?php if (!$recipes): ?>

    <section class="empty-state">
        <h2>Brak przepisów</h2>
        <p>Dodaj pierwszy przepis ręcznie albo z linku.</p>
    </section>

<?php else: ?>
    <section class="recipe-grid">
        <?php foreach ($recipes as $recipe): ?>
            <article class="recipe-card">
                <?php if ($recipe['image_path']): ?>
                    <img src="<?= e($recipe['image_path']) ?>" alt="<?= e($recipe['title']) ?>">
                <?php else: ?>
                    <div class="image-placeholder">Brak zdjęcia</div>
                <?php endif; ?>

                <div class="recipe-card-body">
                    <span class="badge"><?= e($recipe['category']) ?></span>
                    <h2><?= e($recipe['title']) ?></h2>
                    <p><?= e(excerpt($recipe['ingredients'])) ?></p>
                    <small>Dodano: <?= e(date('d.m.Y H:i', strtotime($recipe['created_at']))) ?></small>
                    <div class="actions">
                        <a class="btn small" href="przepis/<?= (int) $recipe['id'] ?>">Zobacz</a>
                        <a class="btn small secondary" href="edytuj/<?= (int) $recipe['id'] ?>">Edytuj</a>
                        <a class="btn small danger" href="usun-przepis/<?= (int) $recipe['id'] ?>">Usuń</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


