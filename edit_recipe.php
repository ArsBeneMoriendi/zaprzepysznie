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

$errors = [];
$form = $recipe;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form['title'] = trim($_POST['title'] ?? '');
    $form['category'] = trim($_POST['category'] ?? '');
    $form['ingredients'] = trim($_POST['ingredients'] ?? '');
    $form['instructions'] = trim($_POST['instructions'] ?? '');
    $form['source_url'] = trim($_POST['source_url'] ?? '');

    if ($form['title'] === '') {
        $errors[] = 'Podaj nazwę przepisu.';
    }
    if (!is_valid_category($form['category'])) {
        $errors[] = 'Wybierz poprawną kategorię.';
    }
    if ($form['ingredients'] === '') {
        $errors[] = 'Podaj składniki.';
    }
    if ($form['instructions'] === '') {
        $errors[] = 'Podaj proces przygotowania.';
    }
    if ($form['source_url'] !== '' && !filter_var($form['source_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Link źródłowy jest nieprawidłowy.';
    }

    if (!$errors) {
        try {
            $imagePath = $recipe['image_path'];
            $newImage = save_uploaded_image($_FILES['image'] ?? []);

            if ($newImage) {
                delete_recipe_image($recipe['image_path']);
                $imagePath = $newImage;
            }

            if (!empty($_POST['remove_image']) && $imagePath) {
                delete_recipe_image($imagePath);
                $imagePath = null;
            }

            $stmt = $pdo->prepare('UPDATE recipes SET title = ?, category = ?, ingredients = ?, instructions = ?, image_path = ?, source_url = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([
                $form['title'],
                $form['category'],
                $form['ingredients'],
                $form['instructions'],
                $imagePath,
                $form['source_url'] ?: null,
                $id,
            ]);

            flash('success', 'Przepis został zaktualizowany.');
            redirect('przepis/' . $id);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';

?>
<h1>Edytuj przepis</h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="card wide-card">
    <form method="post" enctype="multipart/form-data" class="form recipe-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <label>
            Nazwa przepisu
            <input type="text" name="title" value="<?= e($form['title']) ?>" required>
        </label>

        <label>
            Kategoria
            <select name="category" required>
                <?php render_category_options($form['category']); ?>
            </select>
        </label>

        <label>
            Składniki
            <textarea name="ingredients" rows="9" required><?= e($form['ingredients']) ?></textarea>
        </label>

        <label>
            Proces przygotowania
            <textarea name="instructions" rows="12" required><?= e($form['instructions']) ?></textarea>
        </label>

        <?php if ($recipe['image_path']): ?>
            <div class="current-image">
                <p>Aktualne zdjęcie:</p>
                <img src="<?= e($recipe['image_path']) ?>" alt="<?= e($recipe['title']) ?>">
                <label class="checkbox-label">
                    <input type="checkbox" name="remove_image" value="1">
                    Usuń aktualne zdjęcie
                </label>
            </div>

        <?php endif; ?>

        <label>
            Nowe zdjęcie
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small>Wgranie nowego zdjęcia zastąpi aktualne.</small>
        </label>

        <label>
            Link źródłowy
            <input type="url" name="source_url" value="<?= e($form['source_url']) ?>" placeholder="Opcjonalnie">
        </label>

        <div class="actions">
            <button class="btn" type="submit">Zapisz zmiany</button>
            <a class="btn ghost" href="przepis/<?= (int) $id ?>">Anuluj</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
