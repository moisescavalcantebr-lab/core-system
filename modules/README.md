# Contrato de módulos

Os módulos principais adicionam funcionalidades ao projeto. Addons ampliam um ou mais módulos e não devem criar menu próprio quando não houver necessidade.

## Manifesto

Use `kind` para separar o tipo:

```json
{
  "kind": "main"
}
```

ou:

```json
{
  "kind": "addon",
  "attach_to": ["financeiro"]
}
```

`attach_to` aceita um ou mais módulos. Use `["*"]` apenas quando o addon servir de forma geral.

## Dashboard

Um módulo pode expor dados para a dashboard do projeto criando `dashboard.php` na raiz do módulo.

O arquivo deve retornar um painel:

```php
return [
    'type' => 'panel',
    'module' => 'financeiro',
    'order' => 20,
    'html' => $html,
];
```

Também pode retornar vários painéis:

```php
return [
    [
        'type' => 'metric',
        'module' => 'financeiro',
        'order' => 10,
        'title' => 'Saldo',
        'value' => 'R$ 0,00',
    ],
    [
        'type' => 'panel',
        'module' => 'financeiro',
        'order' => 20,
        'html' => $html,
    ],
];
```

Campos aceitos:

- `type`: `panel`, `metric` ou outro tipo futuro.
- `module`: slug do módulo.
- `order`: ordem de exibição.
- `size`: `default`, `wide` ou `compact`.
- `html`: conteúdo pronto para renderizar.
- `title` e `value`: usados quando não houver `html`.
- `class`: classe CSS opcional.

Addons devem preferir influenciar o painel do módulo principal em vez de criar painel próprio.
