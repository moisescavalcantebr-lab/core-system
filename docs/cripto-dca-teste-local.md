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
2. Em **Consultar minha conta Spot**, digite API Key e Secret Key somente no formulario local. Nao envie pelo chat.
3. Informe ate tres pares, confirme que a chave e de leitura e clique em **Atualizar dados da conta**.
4. Confira os valores com o painel Binance e informe apenas quais categorias apareceram ou ficaram indisponiveis, sem expor dados privados.

As chaves nao ficam salvas: sao usadas durante o POST e descartadas. A aplicacao nao registra URLs assinadas ou respostas de erro brutas. O historico de consultas e as amostras retornadas ficam no banco exclusivo do projeto, fora do Git.

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
