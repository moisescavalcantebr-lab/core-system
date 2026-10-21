# Financeiro do Time

Primeira etapa implementada localmente em 20/10/2026, com addon `financeiro_futebol` (`kind=addon`, vinculos Financeiro/Jogadores), instalado na base Futebol. Especificacao e limites em `modules/financeiro_futebol/README.md`.

Segunda etapa local, versao 0.2.0: cobrancas individuais, recebimentos parciais, pagador separado do beneficiario e cancelamento de recebimento reabrindo a obrigacao. Somente os recebimentos entram no caixa. Registros e configuracoes reais nao foram migrados.

Terceira etapa local, versao 0.3.0: criacao atomica de cobrancas em grupo, valor por pessoa ou rateio de total em centavos, com selecao explicita de ate trinta jogadores e previa obrigatoria. Confirmacao vinculada a sessao e idempotencia impedem adulteracao da distribuicao e duplicacao em reenvios. Cada jogador recebe uma obrigacao individual no banco, sem envio de mensagens ou entrada de dinheiro.

## Integracao

O Financeiro geral expoe hooks opcionais de validacao e historico, ativados somente pela presenca do manifesto instalado e das rotas do addon. As copias do Financeiro nas bases Futebol e Financeiro ficam alinhadas ao catalogo; somente Futebol recebe o addon. Configuracoes de planos, cadastros existentes e valores financeiros nao sao alterados.

A preparacao de categorias usa cache por conexao na requisicao e ocorre antes da transacao. Isso impede que `CREATE TABLE IF NOT EXISTS` em chamadas repetidas de tags provoque commit implicito no MySQL. Teste com falha induzida no historico confirma rollback de criacao e confirmacao.

Dados antigos nao sao marcados retroativamente como identificados. Telas gerais preservam o acesso ao legado; a nova visao usa somente entradas com trilha no addon. Formulario novo permite jogador cadastrado ou pessoa/empresa identificada, sem escolher patrocinio/doacao automaticamente. O historico e administrativo nesta etapa.

`time_cobrancas.php` e `time_charge_action.php` oferecem o fluxo individual. `time_charge_helpers.php` trata centavos inteiros, validacao, transacoes, limites e chaves idempotentes. `time_cancel.php` usa a mesma ordem de bloqueio (cobranca antes do movimento) e atualiza ambos os historicos. Cobranças antigas/manuais nao sao convertidas automaticamente. Os schemas sao aditivos e as copias da base seguem o catalogo.

## Validacao

- `scripts/tests/football_finance_test.php`: banco descartavel; origem/destino ausente, jogador inexistente, jogador sem login/inativo, datas, responsavel, CSRF e perfil, snapshots de nomes, pendente/confirmado, cancelamento idempotente com motivo, bloqueio de exclusao/reescrita, legado, credito identificado, series e rollback atomico; projeto sem addon mantem o fluxo geral.
- `scripts/tests/football_finance_ui.cjs`: formulario em 1280 e 390 pixels, opcoes sem selecao implicita, grupos obrigatorios, datas, escape de nomes e ausencia de rolagem horizontal.
- `scripts/tests/football_modules_test.php`: pais do addon instalados, copia catalogo/base/rotas consistente, schema idempotente e dados anteriores preservados.

Na segunda etapa, os testes cobrem cobranca de R$ 100 recebida em R$ 30 e R$ 70, pagador externo ou outro jogador, reenvios identicos/alterados, cancelamento reabrindo saldo, limites, somas exatas de centavos e falha induzida no historico sem gravacao parcial. Duas requisicoes concorrentes de R$ 70 para uma cobranca de R$ 100 resultam em apenas um recebimento. Os totais visiveis do caixa e dashboard sao conferidos para impedir duplicar cobranca como receita. A interface de criacao e recebimento e conferida em 1280 e 390 pixels, com selecoes explicitas e limite do restante.

Na terceira etapa, a suite cobre R$ 100/3 distribuido como 33,34 + 33,33 + 33,33; valor por pessoa; selecao vazia, duplicada, inexistente ou acima do limite; alteracao de valores, jogadores e cadastro entre previa e confirmacao; reenvio e duas confirmacoes simultaneas; falha no segundo item sem grupo parcial; trinta cobrancas de um centavo; caixa inalterado na criacao e recebimento posterior vinculado ao grupo. A interface confere os trinta checkboxes, selecao opcional de ativos, limpeza, modos de calculo, confirmacao e resultado em 1280 e 390 pixels.

Previews sinteticos em `_deploy/football-finance-*.html` e `_deploy/football-finance-*.png`. Nenhuma sincronizacao ou publicacao de producao executada nesta etapa.
