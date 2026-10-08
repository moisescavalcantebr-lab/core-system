# Contrato de módulos

Os módulos principais fornecem uma funcionalidade central ao projeto. Addons ampliam uma funcionalidade existente e identificam seu vínculo em `attach_to`, inclusive quando o recurso ampliado também é um addon. Ter menu ou tabelas próprias não transforma um addon em módulo principal.

## Identidade e ordem

O nome da pasta é o slug estável do módulo e deve corresponder ao campo `name` do manifesto. Ele identifica o recurso nas bases, nos projetos e nas configurações. Não é necessário criar uma coluna ou um número sequencial para identificar módulos e addons.

Ordem de apresentação é independente da identidade: use `menu.order` no menu, `order` na dashboard e `display_order` na configuração comercial da base.

## Papéis e dados

- Módulo principal: fornece o fluxo central e define seus dados essenciais.
- Addon: fornece uma extensão do fluxo e define os dados adicionais que mantém. Pode consultar dados do recurso ao qual está vinculado sem se tornar responsável por eles.
- Configuração funcional: define como o recurso funciona em uma base ou projeto; é diferente de preço, plano e visibilidade comercial.
- Core: mantém o pacote original, o manifesto e o esquema de instalação.
- Base: reúne cópias adaptadas dos recursos e os padrões para novos projetos.
- Projeto: mantém os registros reais no seu próprio banco e arquivos de armazenamento.

Cada tabela deve ter um responsável definido. Um addon pode estender uma tabela do principal, mas essa extensão deve ser documentada. Atualização de código e desinstalação devem preservar registros existentes; eliminação de dados exige uma operação específica.

### Classificação atual

| Papel | Recursos | Vínculo |
| --- | --- | --- |
| Principal | Financeiro, Participantes, Meu Time, DCA Strategy, Divulgação, Tips Survivor, Classificação, Estatísticas | Funcionalidades centrais |
| Addon | Categorias, Cartão de Crédito | Financeiro |
| Addon | Jogadores | Participantes |
| Addon | Competições, Treino | Meu Time |
| Addon | Partidas, Treinos Internos | Competições |
| Addon | Escalação, Placar, Placar Ao Vivo, Resultados | Partidas |

Classificação e Estatísticas permanecem principais: possuem fluxos próprios e vínculos opcionais com o contexto esportivo. Treino organiza o elenco em dois times; Treinos Internos libera o recurso `internal_competitions` de Competições.

### Separação de dados pendente

Esta classificação define papéis; não migra dados nem altera os esquemas existentes. Financeiro ainda fornece tabelas de tags também declaradas por Categorias, e Jogadores ainda declara `participants`, que pertence a Participantes. Configurações funcionais ainda usam `base_module_prices.settings_json` junto com dados comerciais.

A separação desses contratos deve ser feita por família de módulos, ajustando os consumidores antes de retirar tabelas de um esquema. No Financeiro, o destino proposto é manter lançamentos e categorias básicas no principal e atribuir subcategorias e tags ao addon Categorias. Adaptações das bases devem ser comparadas antes de qualquer sincronização de código.

## Manifesto

Declare `kind` explicitamente para separar o tipo:

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
