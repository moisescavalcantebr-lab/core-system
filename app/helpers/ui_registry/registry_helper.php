<?php
declare(strict_types=1);

function registry_get_all_grouped(): array
{
    $base = APP_PATH . '/ui_registry/components';
    $result = [];

    if (!is_dir($base)) {
        return $result;
    }

    foreach (scandir($base) as $category) {

        if ($category === '.' || $category === '..') {
            continue;
        }

        $categoryPath = $base . '/' . $category;

        if (!is_dir($categoryPath)) {
            continue;
        }

        foreach (scandir($categoryPath) as $component) {

            if ($component === '.' || $component === '..') {
                continue;
            }

            $componentPath = $categoryPath . '/' . $component;
            $metaFile = $componentPath . '/meta.json';

            if (!is_file($metaFile)) {
                continue;
            }

            $metaRaw = file_get_contents($metaFile);
            $meta = json_decode($metaRaw, true);

            if (!is_array($meta)) {
                continue;
            }

            $result[$category][] = [
                'name' => $meta['name'] ?? $component,
                'path' => $category . '/' . $component,
                'full_path' => $componentPath
            ];
        }

        /* ordenar componentes dentro da categoria */
        if (isset($result[$category])) {
            usort($result[$category], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
    }

    /* ordenar categorias */
    ksort($result);

    return $result;
}