<?php
require_once __DIR__ . '/config.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function show_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    foreach ($_SESSION['flash'] as $item) {
        echo '<div class="alert alert-' . e($item['type']) . '">' . e($item['message']) . '</div>';
    }

    unset($_SESSION['flash']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Nieprawidłowy token bezpieczeństwa. Spróbuj ponownie.');
        redirect('index.php');
    }
}

function is_valid_category(string $category): bool
{
    return in_array($category, CATEGORIES, true);
}

function render_category_options(?string $selected = null, bool $withAll = false): void
{
    if ($withAll) {
        echo '<option value="">Wszystkie kategorie</option>';
    }

    foreach (CATEGORIES as $category) {
        $isSelected = $selected === $category ? ' selected' : '';
        echo '<option value="' . e($category) . '"' . $isSelected . '>' . e(ucfirst($category)) . '</option>';
    }
}

function slug_file_extension(string $mime): ?string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
}

function save_uploaded_image(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nie udało się przesłać zdjęcia.');
    }

    if ($file['size'] > MAX_IMAGE_SIZE) {
        throw new RuntimeException('Zdjęcie jest za duże. Maksymalny rozmiar to 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extension = slug_file_extension($mime);

    if ($extension === null) {
        throw new RuntimeException('Dozwolone formaty zdjęć: JPG, PNG, WEBP.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Nie udało się zapisać zdjęcia.');
    }

    return UPLOAD_PUBLIC_PATH . $filename;
}

function delete_recipe_image(?string $imagePath): void
{
    if (!$imagePath) {
        return;
    }

    $fullPath = __DIR__ . '/../' . $imagePath;
    $realUploads = realpath(UPLOAD_DIR);
    $realFile = realpath($fullPath);

    if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
        unlink($realFile);
    }
}

function download_remote_image(?string $imageUrl): ?string
{
    if (!$imageUrl || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return null;
    }

    $ch = curl_init($imageUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'Zaprzepysznie/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/png,image/jpeg,image/*,*/*;q=0.8'],
    ]);

    $data = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    curl_close($ch);

    if ($data === false || $status < 200 || $status >= 300 || strlen($data) > MAX_IMAGE_SIZE) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($data);
    $extension = slug_file_extension($mime);

    if ($extension === null && str_contains($contentType, 'webp')) {
        $extension = 'webp';
    }

    if ($extension === null) {
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    file_put_contents(UPLOAD_DIR . $filename, $data);

    return UPLOAD_PUBLIC_PATH . $filename;
}

function excerpt(string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 3) . '...';
}
