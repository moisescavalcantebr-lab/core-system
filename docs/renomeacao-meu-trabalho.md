# Meu Trabalho e Leituras

A base passa de `meu-holerite` para `meu-trabalho`. Seu projeto pessoal recebe o nome Meu Trabalho e o endereco `/projects/meu-trabalho/web/`. O modulo `equatorial` passa para `leituras`, com o rotulo Leituras. O modulo `meu_holerite` continua responsavel pelos contracheques.

## Atualizacao do projeto existente

O deploy executa `app/console/migrate_work_identity.php --apply` antes do bootstrap do Core. Sem `--apply`, o comando somente verifica a situacao e mostra as acoes previstas. Precisa do mesmo acesso MySQL utilizado pelo Core e pelo projeto.

- Preserva IDs da base e do projeto, proprietario, plano, cobranca e configuracao de conexao.
- Mantem o nome fisico do banco: nao recria o banco nem altera senhas.
- Renomeia as quatro tabelas `equatorial_*` para `leituras_*`, mantendo registros, razoes, meses de trabalho e arquivos mensais.
- Atualiza os arquivos do modulo, esquema e metadados do projeto. Holerites, beneficios, contas e PDFs nao sao modificados.
- Redireciona o endereco antigo do projeto com HTTP 308 e mantem a antiga entrada do modulo funcionando.
- Guarda os metadados, arquivos substituidos e dados das tabelas de leituras em `_backups/work-identity-*`, com acesso HTTP negado.
- Interrompe antes de migrar quando encontra bases ou tabelas antigas e novas em conflito, destino ocupado ou mais de um projeto ativo na base.

A declaracao `previous_slug` no manifesto permite ao guard de deploy reconhecer a renomeacao sem dispensar a protecao das demais bases. O cadastro da base e atualizado no mesmo ID, preservando as referencias dos planos e projetos.

## Falha e verificacao

Durante a atualizacao dos arquivos do projeto, seu acesso fica temporariamente em manutencao (503). Uma falha conserva essa manutencao e o backup. O marcador privado `.identity-migration.json` permite retomar o comando com os mesmos IDs e restaurar o `.htaccess` original. Nao apague o backup ou recrie a base/projeto para resolver uma falha.

Depois do deploy, confira login, nome do projeto, menu Leituras, razoes, lancamentos, meses arquivados, graficos e PDFs. Confira tambem o endereco antigo. A migracao pode ser executada novamente sem renumerar registros.

Teste do modulo: `docker exec -e LEITURAS_MYSQL_TEST=1 app_php php /var/www/html/modules/leituras/tests/check.php`. Inclui preservacao das quatro tabelas e rejeicao de conflito. Teste integrado da identidade: `docker exec -e WORK_IDENTITY_MYSQL_TEST=1 app_php php /var/www/html/app/tests/work_identity.php`.
