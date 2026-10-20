# Financeiro do Time

Primeira etapa implementada localmente em 20/10/2026, com addon `financeiro_futebol` (`kind=addon`, vinculos Financeiro/Jogadores), instalado na base Futebol. Especificacao e limites em `modules/financeiro_futebol/README.md`.

## Integracao

O Financeiro geral expoe hooks opcionais de validacao e historico, ativados somente pela presenca do manifesto instalado e das rotas do addon. As copias do Financeiro nas bases Futebol e Financeiro ficam alinhadas ao catalogo; somente Futebol recebe o addon. Configuracoes de planos, cadastros existentes e valores financeiros nao sao alterados.

A preparacao de categorias usa cache por conexao na requisicao e ocorre antes da transacao. Isso impede que `CREATE TABLE IF NOT EXISTS` em chamadas repetidas de tags provoque commit implicito no MySQL. Teste com falha induzida no historico confirma rollback de criacao e confirmacao.

Dados antigos nao sao marcados retroativamente como identificados. Telas gerais preservam o acesso ao legado; a nova visao usa somente entradas com trilha no addon. Formulario novo permite jogador cadastrado ou pessoa/empresa identificada, sem escolher patrocinio/doacao automaticamente. O historico e administrativo nesta etapa.

## Validacao

- `scripts/tests/football_finance_test.php`: banco descartavel; origem/destino ausente, jogador inexistente, jogador sem login/inativo, datas, responsavel, CSRF e perfil, snapshots de nomes, pendente/confirmado, cancelamento idempotente com motivo, bloqueio de exclusao/reescrita, legado, credito identificado, series e rollback atomico; projeto sem addon mantem o fluxo geral.
- `scripts/tests/football_finance_ui.cjs`: formulario em 1280 e 390 pixels, opcoes sem selecao implicita, grupos obrigatorios, datas, escape de nomes e ausencia de rolagem horizontal.
- `scripts/tests/football_modules_test.php`: pais do addon instalados, copia catalogo/base/rotas consistente, schema idempotente e dados anteriores preservados.

Previews sinteticos em `_deploy/football-finance-*.html` e `_deploy/football-finance-form-*.png`. Nenhuma sincronizacao ou publicacao de producao executada nesta etapa.
