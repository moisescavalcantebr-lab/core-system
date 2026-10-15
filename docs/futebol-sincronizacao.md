# Futebol: atualizar os modulos existentes

Atualizacao das copias de Participantes, Jogadores, Meu Time, Treino, Competicoes, Partidas e Financeiro com o codigo ja existente no catalogo. Sem instalar addons novos ou reformular responsabilidades. Copias extras antigas aninhadas foram preservadas para revisao posterior.

## Correcao tecnica

A atualizacao do catalogo para a base agora fica dentro do tratamento de erros da sincronizacao. Falhas de permissao ou manifesto geram registro de erro e mensagem no painel, sem stack trace fatal. A substituicao de cada arquivo prepara uma copia temporaria na mesma pasta e so depois troca o arquivo original; nunca apaga o original antes de conseguir preparar a nova copia.

Manifestos recebem o contrato e a versao atuais do catalogo, preservando a politica `participant_access` da base e campos adicionais locais que nao existem no catalogo. O arquivo `database/schema.sql` da base continua preservado, assim como configuracoes comerciais no Core. Os esquemas dos modulos continuam sendo aplicados ao projeto como no fluxo anterior; esta entrega nao cria migracoes novas nem apaga bancos ou registros.

A operacao completa nao e uma transacao de arquivos: uma falha pode ocorrer apos atualizar parte deles. Corrigir a permissao e repetir sincroniza os restantes. Cada substituicao individual preserva o original em caso de falha, e conteudo ja atualizado nao e regravado sem necessidade. Nao executar simultaneamente com um deploy.

## Permissoes no servidor

O PHP precisa escrever nas pastas da base e do projeto. A rotina web nao executa chown nem amplia permissoes. O deploy ja possui uma etapa de ajuste para www-data; se a pasta continuar pertencendo a root ou estiver sem escrita, corrigir pelo SSH depois de publicar a correcao.

No terminal do servidor, para o erro especifico da base Futebol:

```bash
docker exec app_php chown -R www-data:www-data /var/www/html/bases/futebol
docker exec app_php chmod -R u+rwX /var/www/html/bases/futebol
docker exec -u www-data app_php test -w /var/www/html/bases/futebol/modules/financeiro
```

Se algum comando falhar, conferir o volume/mount e os logs antes de repetir. Nao usar chmod 777. Se o erro indicar uma pasta de projeto, conferir e corrigir somente o projeto indicado. A pasta local /opt/workspace pode ser uma copia separada; o PHP executa dentro de app_php.

Depois, abrir o projeto no Core e sincronizar todos os modulos ja instalados na base. O acesso opcional de Futebol permanece optional, sem alterar contas vinculadas.

## Testes

`scripts/tests/module_sync_test.php`: rota real como UID 33, arquivos/pastas readonly, erro registrado sem fatal, copia segura, repeticao, manifestos/configuracoes/esquemas e dados preservados. Bancos e pastas temporarios no Docker local.

`scripts/tests/participant_access_test.php`: regressao da politica de Futebol. A permissao e o resultado da sincronizacao de producao ainda precisam ser validados no servidor.

`scripts/tests/football_modules_test.php`: sete modulos atuais, arquivos/rotas comparados ao catalogo, instalacao dos esquemas em banco temporario, repeticao preservando dados esportivos/financeiros e consulta de categorias.
