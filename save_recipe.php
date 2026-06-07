<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scraper.php';

require_login();

$mode = $_POST['mode'] ?? 'manual';
$scraped = null;
$errors = [];

$form = [
    'title' => '',
    'category' => 'obiad',
    'ingredients' => '',
    'instructions' => '',
    'source_url' => '',
    'scraped_image_url' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($mode === 'scrape') {
        $sourceUrl = trim($_POST['source_url'] ?? '');

        try {
            $scraped = scrape_recipe_from_url($sourceUrl);
            $form['title'] = $scraped['title'];
            $form['ingredients'] = $scraped['ingredients'];
            $form['instructions'] = $scraped['instructions'];
            $form['source_url'] = $scraped['source_url'];
            $form['scraped_image_url'] = $scraped['image_url'];

            if ($form['ingredients'] === '' || $form['instructions'] === '') {
                flash('warning', 'Część danych mogła nie zostać rozpoznana. Uzupełnij brakujące pola ręcznie przed zapisem.');
            } else {
                flash('success', 'Dane z przepisu zostały pobrane. Sprawdź je i zapisz.');
            }
        } catch (Throwable $e) {
            flash('error', 'Błąd scrapingu: ' . $e->getMessage() . ' Przełączono na wpis ręczny.');
            $form['source_url'] = $sourceUrl;
            $mode = 'manual';
        }
    }

    if ($mode === 'save') {
        $form['title'] = trim($_POST['title'] ?? '');
        $form['category'] = trim($_POST['category'] ?? '');
        $form['ingredients'] = trim($_POST['ingredients'] ?? '');
        $form['instructions'] = trim($_POST['instructions'] ?? '');
        $form['source_url'] = trim($_POST['source_url'] ?? '');
        $form['scraped_image_url'] = trim($_POST['scraped_image_url'] ?? '');

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
                $imagePath = save_uploaded_image($_FILES['image'] ?? []);

                if (!$imagePath && $form['scraped_image_url'] !== '') {
                    $imagePath = download_remote_image($form['scraped_image_url']);
                }

                $stmt = $pdo->prepare('INSERT INTO recipes (user_id, title, category, ingredients, instructions, image_path, source_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    current_user()['id'],
                    $form['title'],
                    $form['category'],
                    $form['ingredients'],
                    $form['instructions'],
                    $imagePath,
                    $form['source_url'] ?: null,
                ]);

                flash('success', 'Przepis został zapisany.');
                redirect('przepisy');
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';

?>
<h1>Zapisz przepis</h1>
<p class="muted">Dodaj przepis ręcznie albo pobierz dane z jednej z obsługiwanych stron.</p>

<section class="grid two-columns">
    <div class="card">
        <h2>Dodaj z linku</h2>
        <p>Obsługiwane strony: kwestiasmaku.com, poprostupycha.com.pl.</p>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="scrape">
            <label>
                Link do przepisu
                <input type="url" name="source_url" placeholder="https://..." value="<?= e($form['source_url']) ?>">
            </label>
            <button class="btn secondary" type="submit">Pobierz dane</button>
        </form>
    </div>

    <div class="card">
        <h2>Dodaj ręcznie</h2>
        <p>Wypełnij formularz samodzielnie albo popraw dane pobrane ze strony.</p>
    </div>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<section class="card wide-card">
    <h2>Dane przepisu</h2>

    <?php if (!empty($form['scraped_image_url'])): ?>
        <div class="scraped-preview">
            <p>Zdjęcie pobrane ze strony:</p>
            <img src="<?= e($form['scraped_image_url']) ?>" alt="Zdjęcie przepisu">
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form recipe-form">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="save">
        <input type="hidden" name="scraped_image_url" value="<?= e($form['scraped_image_url']) ?>">

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
            <textarea name="ingredients" rows="9" required placeholder="Np.&#10;- 2 jajka&#10;- 1 szklanka mleka"><?= e($form['ingredients']) ?></textarea>
        </label>

        <label>
            Proces przygotowania
            <textarea name="instructions" rows="12" required placeholder="Np.&#10;1. Wymieszaj składniki.&#10;2. Piecz 30 minut."><?= e($form['instructions']) ?></textarea>
        </label>

        <label>
            Zdjęcie z komputera
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            <small>JPG, PNG lub WEBP, maksymalnie 2 MB. Jeśli nic nie wybierzesz, przy scrapingu aplikacja spróbuje zapisać zdjęcie ze strony.</small>
        </label>

        <label>
            Link źródłowy
            <input type="url" name="source_url" value="<?= e($form['source_url']) ?>" placeholder="Opcjonalnie">
        </label>

        <button class="btn" type="submit">Zapisz przepis</button>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
