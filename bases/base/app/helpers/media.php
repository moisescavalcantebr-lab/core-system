<?php
declare(strict_types=1);

function ensureUploadDirectories(): void
{
    $base = PUBLIC_PATH . '/storage/uploads';

    $folders = [
        $base,
        $base . '/media',
        $base . '/system',
        $base . '/avatars'
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
    }
}

/*
|------------------------------------------------------------------
| MEDIA (conteúdo dinâmico)
|------------------------------------------------------------------
*/

function uploadMedia(array $file): ?array
{
    global $pdo;

    ensureUploadDirectories();

    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];

    if (!in_array($file['type'], $allowed, true)) return null;

    $year  = date('Y');
    $month = date('m');

    $relativeFolder = "storage/uploads/media/{$year}/{$month}";
    $absoluteFolder = PUBLIC_PATH . '/' . $relativeFolder;

    if (!is_dir($absoluteFolder)) {
        mkdir($absoluteFolder, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newName   = bin2hex(random_bytes(16)) . '.' . $extension;

    $relativePath = $relativeFolder . '/' . $newName;
    $destination  = PUBLIC_PATH . '/' . $relativePath;

    if (!move_uploaded_file($file['tmp_name'], $destination)) return null;

    $stmt = $pdo->prepare("
        INSERT INTO media_library
        (file_name, file_path, file_type, file_size, uploaded_by)
        VALUES
        (:name, :path, :type, :size, :user)
    ");

    $stmt->execute([
        'name' => $file['name'],
        'path' => $relativePath,
        'type' => $file['type'],
        'size' => $file['size'],
        'user' => $_SESSION['project_user_id'] ?? null
    ]);

    return [
        'id'   => $pdo->lastInsertId(),
        'path' => $relativePath
    ];
}

/*
|------------------------------------------------------------------
| LOGO
|------------------------------------------------------------------
*/

function uploadLogo(array $file): ?string
{
    ensureUploadDirectories();

    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg','image/png','image/webp'];

    if (!in_array($file['type'], $allowed, true)) return null;

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName  = 'logo_' . time() . '.' . $extension;

    $relativePath = "storage/uploads/system/{$fileName}";
    $destination  = PUBLIC_PATH . '/' . $relativePath;

    if (!move_uploaded_file($file['tmp_name'], $destination)) return null;

    return $relativePath;
}

/*
|------------------------------------------------------------------
| AVATAR
|------------------------------------------------------------------
*/

function uploadAvatar(array $file, int $userId): ?string
{
    ensureUploadDirectories();

    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg','image/png','image/webp'];

    if (!in_array($file['type'], $allowed, true)) return null;

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName  = "user-{$userId}." . $extension;

    $relativePath = "storage/uploads/avatars/{$fileName}";
    $destination  = PUBLIC_PATH . '/' . $relativePath;

    if (!move_uploaded_file($file['tmp_name'], $destination)) return null;

    return $relativePath;
}

/*
|------------------------------------------------------------------
| FAVICON
|------------------------------------------------------------------
*/

function uploadFavicon(array $file): ?string
{
    ensureUploadDirectories();

    if ($file['error'] !== UPLOAD_ERR_OK) return null;

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName  = 'favicon_' . time() . '.' . $extension;

    $relativePath = "storage/uploads/system/{$fileName}";
    $destination  = PUBLIC_PATH . '/' . $relativePath;

    if (!move_uploaded_file($file['tmp_name'], $destination)) return null;

    return $relativePath;
}

if (!function_exists('media')) {
    function media(?string $file): string
    {
        if (!$file) {
            return PROJECT_URL . '/assets/img/placeholder.png';
        }

        return PROJECT_URL . '/' . ltrim($file, '/');
    }
}