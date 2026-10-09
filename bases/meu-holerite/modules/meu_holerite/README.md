# Meu Holerite

Modulo principal para acompanhamento pessoal, historico, comparacao entre competencias e graficos. Nao calcula folha, impostos ou direitos trabalhistas.

## Dados e acesso

- `holerite_receipts`: identificacao historica do vinculo, competencia, tipo, sequencia, datas, salario contratual, bases informadas e totais.
- `holerite_items`: rubricas, referencias e valores de proventos/descontos de cada holerite.
- Os valores monetarios sao validados e somados em centavos; totais sao derivados dos itens.
- Cada holerite aceita ate 100 itens. Envios incompletos sao rejeitados antes de salvar.
- Cada registro pertence ao usuario que o criou. Telas, alteracoes e dashboard exigem ADMIN ativo e filtram por esse usuario.
- A competencia e independente das datas de emissao e pagamento. O campo recebido e uma confirmacao pessoal, nao uma inferencia da data.
- Salario-base e bases tributarias sao valores informados, nao entradas de caixa. O indice da rubrica e texto, pois pode representar dias, horas ou percentual.
- O conjunto usuario/empresa/competencia/tipo/sequencia evita duplicacao e permite recibos adicionais no mesmo mes.

## Instalacao

Use a instalacao de modulos do core para copiar o pacote a uma base e a sincronizacao de modulos para instalar no projeto. O manifesto declara o esquema e os destinos; `dashboard.php` fornece um resumo do ano da ultima competencia cadastrada.

Este pacote nao instala automaticamente em bases ou projetos, nao inclui dados pessoais e nao depende do Financeiro. Nao ha PDF anexado, importacao automatica nem lancamento financeiro nesta versao. Esses recursos podem ser adicionados posteriormente por addons.

## Verificacao

`php modules/meu_holerite/tests/check.php` valida dinheiro, datas, itens, totais e agrupamento mensal. A verificacao integrada deve usar banco e projeto temporarios, sem dados reais.
