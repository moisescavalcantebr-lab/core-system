<?php
declare(strict_types=1);

class MediaService {

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================
    LISTAR
    ========================= */

    public function all(): array
    {
        $stmt = $this->pdo->query("
            SELECT * FROM core_media ORDER BY id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
    UPLOAD
    ========================= */

    public function upload(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload');
        }

        /* validar extensão */
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            throw new Exception('Tipo de arquivo inválido');
        }

        /* gerar nome */
        $name = uniqid('img_', true) . '.' . $ext;

        $mediaDir  = STORAGE_PATH . '/media/';
        $thumbDir  = STORAGE_PATH . '/media/thumb/';

        $originalPath = $mediaDir . $name;
        $thumbPath    = $thumbDir . $name;

        /* garantir pastas */
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        /* mover arquivo */
        if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
            throw new Exception('Falha ao mover arquivo');
        }

        /* gerar thumbnail */
        ImageService::createThumbnail(
            $originalPath,
            $thumbPath,
            400
        );

        /* salvar no banco */
        $stmt = $this->pdo->prepare("
            INSERT INTO core_media (file_name)
            VALUES (?)
        ");

        $stmt->execute([$name]);
    }

    /* =========================
    DELETE
    ========================= */

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("
            SELECT file_name FROM core_media WHERE id = ?
        ");
        $stmt->execute([$id]);

        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$file) {
            return;
        }

        $original = STORAGE_PATH . '/media/' . $file['file_name'];
        $thumb    = STORAGE_PATH . '/media/thumb/' . $file['file_name'];

        if (file_exists($original)) {
            unlink($original);
        }

        if (file_exists($thumb)) {
            unlink($thumb);
        }

        $del = $this->pdo->prepare("
            DELETE FROM core_media WHERE id = ?
        ");
        $del->execute([$id]);
    }
}