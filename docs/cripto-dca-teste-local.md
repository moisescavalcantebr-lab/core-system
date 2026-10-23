# Testar Cripto DCA no ambiente local

A base Cripto inclui o modulo DCA Strategy e o addon Binance - leitura. Nao e necessario instalar o addon manualmente ao criar um novo projeto.

## Criar seu projeto

1. Abra https://localhost:8443/web/admin/projects/create.php e entre no Core local.
2. Escolha **Base Cripto**, informe nome, slug, responsavel e email e crie o projeto. Exemplo de slug: `cripto-teste`.
3. Ainda autenticado como administrador no Core local, clique em **Abrir** no projeto. Defina a senha do primeiro administrador e confirme-a. Neste fluxo local nao e necessario enviar email; ele so funciona enquanto o projeto ainda nao tem administrador e nao permite redefinir contas existentes.
4. No menu do projeto, abra **Binance - leitura**.

## Primeiro teste sem credenciais

Clique em **Testar com demonstracao**. A tela deve mostrar valores ficticios identificados como demonstracao, pares, saldos, uma negociacao e a cobertura dos dados. Esses dados nao sao cotacoes atuais nem entram nos ativos/ciclos manuais.

Depois informe um par real (por exemplo BTCUSDT) e clique em **Consultar precos e pares**. Isso consulta a Binance ao clicar; nao exige chave nem realiza operacoes. Erros de rede, regiao ou limite aparecem na cobertura da consulta.

## Conferir a conta

1. Na Binance, prepare uma chave API **HMAC dedicada somente a leitura**, sem negociacao, saque ou transferencia. Mantenha as restricoes de seguranca da sua conta; nao e necessario desabilita-las para este teste.
2. Em **Conexao Binance Spot → Salvar conexao**, digite API Key e Secret Key somente no formulario local. Nao envie pelo chat.
3. Confirme que a chave e dedicada somente a leitura e clique em **Salvar conexao**. Salvar nao consulta a Binance nem comprova a validade da chave.
4. Informe ate tres pares e clique em **Atualizar dados da conta**. Recarregue a pagina: a conexao continua salva e os campos permanecem vazios. Nao e necessario informar as chaves de novo.
5. Confira os valores com o painel Binance. Para trocar as chaves, abra **Atualizar conexao** e informe as duas novamente.
6. Para remover as credenciais locais, clique em **Desconectar** e confirme. O historico continua disponivel. Isso nao revoga a chave na Binance.

API Key e Secret Key ficam criptografadas com XChaCha20-Poly1305 na tabela `crypto_binance_connections`, exclusiva do projeto. O contexto autenticado inclui o banco do projeto, impedindo reutilizar o envelope em outro banco. Somente administrador ativo, autenticado nesse projeto e com CSRF valido, pode gerenciar a conexao. A aplicacao nao exibe chaves salvas, registra URLs assinadas ou respostas de erro brutas.

A chave de protecao fica no volume Docker `docker_crypto_binance_keys`, em `/run/crypto-binance/master.key`, montado somente leitura no PHP e separado do MySQL/site. O preparo faz uma copia com ACL restrita em `../private-crypto-binance/master.key`, fora do workspace/Git. Guarde essa copia: restaurar apenas o banco nao recupera as credenciais. Nao excluir o volume nem gerar outra chave para tentar recuperar conexoes antigas. O preparo recusa gerar uma chave nova se detectar conexoes salvas sem chave/backup.

Conexao e historico persistem entre reinicios. A primeira abertura depois desta atualizacao pode exigir novo login para vincular a sessao ao projeto correto. Chaves informadas na versao anterior nao foram armazenadas e precisam ser cadastradas uma vez.

## Limites desta entrega

- Saldos representam somente a abrangencia retornada pela API Spot. Valores nos bots podem nao estar incluidos.
- Negociacoes: amostra de ate 100 por par na janela das ultimas 24 horas; nao e historico completo. Ordens abertas: ate 100 por par.
- Repetir uma consulta nao duplica as negociacoes importadas da mesma chave/par/ID.
- IDs de bots, ciclos e reservas DCA aparecem como **Nao validado**. Nao inferimos essa identificacao a partir do par ou saldo.
- Nao ha calculo consolidado, associacao automatica de trades a ciclos, agendamento, IA ou execucao de ordens nesta etapa.
- Os cadastros manuais existentes continuam separados.
- O addon esta restrito ao ambiente local/laboratorio. Nenhum deploy e necessario para testar.

## Preparacao e verificacao tecnica

No container PHP local:

```sh
php scripts/cripto-preparar-local.php
php scripts/tests/crypto_binance_test.php
```

A preparacao atualiza somente o produto na base Cripto; nao sincroniza projetos existentes. O teste cria um projeto e banco temporarios usando o provisionador normal, sem enviar emails e sem credenciais reais. A fixture fica em `_deploy/crypto-local-test.json`, ignorada pelo Git. Ao concluir os testes HTTP, limpar com:

```sh
php scripts/tests/crypto_binance_test.php --cleanup
```

Projetos criados antes desta entrega precisam do fluxo de sincronizacao de modulos da base no Core local. Prefira criar um projeto novo para este primeiro teste.

## Links de acesso por token no Docker

O fluxo com link de acesso tambem funciona localmente. A porta `8443` e a porta publicada para o navegador no computador; ela nao e a porta interna do container. No laboratorio com Core e projeto no mesmo MySQL, a base valida o token diretamente no Core, sem fazer uma chamada HTTPS a `localhost:8443` de dentro do container.

A validacao mantem projeto, responsavel, vencimento e uso unico do token. A gravacao da senha e o consumo do token usam uma unica transacao; se houver falha, ambos sao revertidos. O fluxo sem email para o administrador autenticado no Core continua disponivel.

Regressao do fluxo com token, depois de preparar a fixture do teste anterior:

```sh
php scripts/tests/crypto_local_tokens_test.php
```

Esse teste usa somente o projeto temporario `cripto-check-*`, nunca um projeto criado pelo usuario. O teste HTTP complementar esta em `scripts/tests/crypto_local_tokens_ui.cjs` e verifica o formulario, CSRF, uso unico e login.

## Preparar conexao salva no Docker local

O ambiente atual ja foi preparado. Em uma nova instalacao local, primeiro execute no PowerShell da raiz do workspace:

```powershell
./scripts/crypto-binance-preparar-chave-local.ps1
```

O script valida Docker local e PHP corrigido, cria/restaura a chave sem exibi-la, protege o backup e acrescenta `docker/docker-compose.crypto.local.yml` aos arquivos Compose reais. Recria somente PHP, mantendo imagem, portas, mounts anteriores e container MySQL. Nao altera producao. O volume e externo ao Compose; mantenha-o nas recriacoes do PHP.

Atualizacao de projeto Cripto existente, depois de backup privado de seus arquivos e banco:

```sh
php scripts/cripto-preparar-local.php
php scripts/cripto-atualizar-binance-local.php dca-spot
```

Essa atualizacao copia somente o addon e o vinculo de autenticacao ao projeto, adicionando a tabela da conexao com `CREATE TABLE IF NOT EXISTS`. Nao recria o projeto, nao regrava suas senhas e nao remove consultas, trades, ativos ou ciclos. A base tambem fica preparada para novos projetos.

Testes adicionais, com a fixture sintetica criada pelo teste PHP e autenticada por `crypto_binance_ui.cjs`:

```sh
php scripts/tests/crypto_binance_connection_test.php
# No host Windows com Playwright: node scripts/tests/crypto_binance_connection_ui.cjs
```

Os testes usam somente chaves ficticias e transporte Binance simulado para verificar consultas com credenciais salvas; nao usam a conta real nem executam ordens. Incluem adulteracao do envelope, chave ausente/errada, troca entre bancos, CSRF, CLIENT e administrador inativo bloqueados, sessao de outro projeto, concorrencia, confirmacao de desconexao e telas de 1280/390 pixels. Limpar a fixture ao concluir.
