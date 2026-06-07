<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$userSearch = trim($_GET['user_search'] ?? '');
$recipeSearch = trim($_GET['recipe_search'] ?? '');

$userParams = [];
$userWhere = '';
if ($userSearch !== '') {
    $userWhere = 'WHERE username LIKE ?';
    $userParams[] = '%' . $userSearch . '%';
}

$stmt = $pdo->prepare("SELECT u.*, COUNT(r.id) AS recipe_count FROM users u LEFT JOIN recipes r ON r.user_id = u.id $userWhere GROUP BY u.id ORDER BY u.created_at DESC");
$stmt->execute($userParams);
$users = $stmt->fetchAll();

$recipeParams = [];
$recipeWhere = '';
if ($recipeSearch !== '') {
    $recipeWhere = 'WHERE r.title LIKE ? OR u.username LIKE ?';
    $recipeParams[] = '%' . $recipeSearch . '%';
    $recipeParams[] = '%' . $recipeSearch . '%';
}

$stmt = $pdo->prepare("SELECT r.*, u.username FROM recipes r JOIN users u ON u.id = r.user_id $recipeWhere ORDER BY r.created_at DESC");
$stmt->execute($recipeParams);
$recipes = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';


?>
<h1>Panel administratora</h1>
<p class="muted">Administrator widzi wszystkich użytkowników i wszystkie przepisy. Może usuwać przepisy i konta użytkowników.</p>

<section class="card">
    <h2>Użytkownicy</h2>
    <form method="get" class="filters compact">
        <label>
            Szukaj użytkownika
            <input type="text" name="user_search" value="<?= e($userSearch) ?>">
        </label>
        <button class="btn secondary" type="submit">Szukaj</button>
        <a class="btn ghost" href="admin">Wyczyść</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Login</th>
                <th>Rola</th>
                <th>Przepisy</th>
                <th>Data utworzenia</th>
                <th>Akcje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['id'] ?></td>
                    <td><?= e($user['username']) ?></td>
                    <td><?= $user['is_admin'] ? 'admin' : 'użytkownik' ?></td>
                    <td><?= (int) $user['recipe_count'] ?></td>
                    <td><?= e(date('d.m.Y H:i', strtotime($user['created_at']))) ?></td>
                    <td>
                        <?php if ((int) $user['id'] === current_user()['id']): ?>
                            <span class="muted">To Ty</span>
                        <?php else: ?>
                            <a class="btn small danger" href="usun-uzytkownika/<?= (int) $user['id'] ?>">Usuń</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Wszystkie przepisy</h2>
    <form method="get" class="filters compact">
        <label>
            Szukaj po nazwie przepisu lub użytkowniku
            <input type="text" name="recipe_search" value="<?= e($recipeSearch) ?>">
        </label>
        <button class="btn secondary" type="submit">Szukaj</button>
        <a class="btn ghost" href="admin">Wyczyść</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Nazwa</th>
                <th>Kategoria</th>
                <th>Autor</th>
                <th>Dodano</th>
                <th>Akcje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recipes as $recipe): ?>
                <tr>
                    <td><?= (int) $recipe['id'] ?></td>
                    <td><?= e($recipe['title']) ?></td>
                    <td><?= e($recipe['category']) ?></td>
                    <td><?= e($recipe['username']) ?></td>
                    <td><?= e(date('d.m.Y H:i', strtotime($recipe['created_at']))) ?></td>
                    <td class="actions inline">
                        <a class="btn small" href="przepis/<?= (int) $recipe['id'] ?>">Zobacz</a>
                        <a class="btn small secondary" href="edytuj/<?= (int) $recipe['id'] ?>">Edytuj</a>
                        <a class="btn small danger" href="usun-przepis/<?= (int) $recipe['id'] ?>">Usuń</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
