<?php

/* =========================
RENDER FIELD
========================= */

function renderField(string $name, array $config, $value = null): void
{
    $type  = $config['type'] ?? 'text';
    $label = $config['label'] ?? $name;

    echo "<div class='c-form-group'>";

    echo "<label>" . htmlspecialchars($label) . "</label>";

    switch ($type) {

        case 'textarea':

            echo "<textarea class='c-input' name='{$name}'>"
                . htmlspecialchars((string)($value ?? ''))
                . "</textarea>";

            break;

        case 'select':

            echo "<select class='c-input' name='{$name}'>";

            foreach (($config['options'] ?? []) as $k => $v) {

                $selected = ((string)$value === (string)$k) ? 'selected' : '';

                echo "<option value='" . htmlspecialchars($k) . "' {$selected}>"
                    . htmlspecialchars($v)
                    . "</option>";
            }

            echo "</select>";

            break;

        case 'group':

            renderGroup($name, $config, $value);

            break;

        default:

            echo "<input type='text' class='c-input' name='{$name}' value='"
                . htmlspecialchars((string)($value ?? ''))
                . "'>";

    }

    echo "</div>";
}

/* =========================
RENDER GROUP (REPEATER)
========================= */

function renderGroup(string $name, array $config, $value): void
{
    $value = is_array($value) ? $value : [];

    $fields = $config['fields'] ?? [];

    echo "<div class='group-container' data-name='{$name}'>";

    /* =========================
    EXISTENTES
    ========================= */

    foreach ($value as $i => $item) {

        echo "<div class='group-item'>";

        foreach ($fields as $subName => $subConfig) {

            $fullName = "{$name}[{$i}][{$subName}]";
            $subValue = $item[$subName] ?? null;

            renderField($fullName, $subConfig, $subValue);
        }

        echo "<button type='button' class='btn-remove' onclick='removeGroupItem(this)'>Remover</button>";

        echo "</div>";
    }

    echo "</div>";

    /* =========================
    BOTÃO ADD
    ========================= */

    echo "<button type='button' class='btn-add' onclick='addGroupItem(this)'>+ Adicionar</button>";

    /* =========================
    TEMPLATE
    ========================= */

    echo "<template class='group-template'>";

    echo "<div class='group-item'>";

    foreach ($fields as $subName => $subConfig) {

        $fullName = "{$name}[__INDEX__][{$subName}]";

        ob_start();
        renderField($fullName, $subConfig, null);
        $fieldHtml = ob_get_clean();

        echo $fieldHtml;
    }

    echo "<button type='button' class='btn-remove' onclick='removeGroupItem(this)'>Remover</button>";

    echo "</div>";

    echo "</template>";
}