<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

class PageRenderService
{
    private string $blocksPath;

    public function __construct()
    {
        $this->blocksPath = STORAGE_PATH . '/paginas/blocks/';
    }

    /* =========================
    RENDER PáGINA
    ========================= */

public function render(array $blocks, array $globalData = []): void
{
    if (empty($blocks)) {
        return;
    }

    $media = $globalData['media'] ?? [];

    foreach ($blocks as $block) {

        if (!is_array($block)) {
            continue;
        }

        if (empty($block['enabled'])) {
            continue;
        }

        $type = $block['type'] ?? null;

        if (!$type) {
            continue;
        }

        $file = $this->getBlockFile($type);

        if (!$file) {
            echo "<!-- bloco {$type} não encontrado -->";
            continue;
        }

        $config = $block;
        unset($config['type'], $config['enabled']);

        //  RESOLVE MEDIA
        $config = $this->parsePlaceholders($config, $media);

        $this->renderBlock($file, $type, $config, $media);
    }
}
	private function parsePlaceholders(array $config, array $media): array
{
    foreach ($config as $key => $value) {

        // string simples
        if (is_string($value)) {
            $config[$key] = $this->replaceMedia($value, $media);
        }

        // array (ex: cards, items)
        elseif (is_array($value)) {
            $config[$key] = $this->parsePlaceholders($value, $media);
        }
    }

    return $config;
}
	
	
	private function replaceMedia(string $value, array $media): string
{
    return preg_replace_callback(
        '/\{\{media\.([a-z0-9_\-]+)\}\}/i',
        function ($matches) use ($media) {

            $key = $matches[1];

            return $media[$key] ?? '';
        },
        $value
    );
}
    /* =========================
    RESOLVER BLOCO
    ========================= */

    private function getBlockFile(string $type): ?string
    {
        $type = preg_replace('/[^a-z0-9_\-]/i', '', $type);

        if (!$type) {
            return null;
        }

        $path = $this->blocksPath . $type . '.php';

        if (file_exists($path)) {
            return $path;
        }

        /* fallback (opcional) */
        $fallback = APP_PATH . '/views/blocks/' . $type . '.php';

        if (file_exists($fallback)) {
            return $fallback;
        }

        return null;
    }

    /* =========================
    RENDER BLOCO
    ========================= */

    private function renderBlock(string $file, string $type, array $config, array $media): void
    {
        // variaveis disponiveis dentro do bloco
        $block = $config;
        $blockType = $type;
        $blockClass = 'block block-' . $type;

        // debug opcional
        // echo "<!-- render: {$type} -->";

        include $file;
    }
}