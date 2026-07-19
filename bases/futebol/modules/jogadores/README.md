# Modulo Jogadores

Modulo de teste para bases do segmento esportivo.

## O que ele entrega

- Tabela `players`.
- Listagem de jogadores.
- Cadastro, edicao e exclusao.
- Status ativo/inativo.

## Como deve ser usado

Este modulo fica na biblioteca raiz `modules/jogadores`.
Ao preparar uma base de segmento, o instalador deve copiar os arquivos definidos em `module.json` para a base e executar `database/schema.sql` no banco do projeto/base.

Por enquanto ele esta isolado para teste e nao altera a `bases/base`.
