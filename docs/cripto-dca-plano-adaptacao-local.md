# Cripto DCA: plano de adaptacao local

Data: 2026-10-22.
Estado: conexao Spot local validada, com credenciais criptografadas salvas; cobertura dos bots DCA nativos ainda pendente. Pesquisa: [cobertura dos bots nativos](cripto-dca-cobertura-bots-nativos.md). Guia: [teste local](cripto-dca-teste-local.md).

## Escopo aprovado

Evoluir a base Cripto para acompanhar operacoes DCA Spot e seu resultado consolidado. O trabalho fica no laboratorio local, com codigo e documentacao no Git. Publicacao no servidor depende de nova decisao do responsavel.

A primeira entrega funcional sera uma conexao somente de leitura, atualizada por acao manual. Atualizar dados nao autoriza compras, vendas, cancelamentos, transferencias ou encerramento de bots. Agendamento de sincronizacao, alertas automaticos e IA ficam para etapas posteriores.

Referencias da analise:

- Conversa: https://chatgpt.com/share/6ada272f-bc0c-83e9-ad85-f153746f27be
- Agent OS: https://www.binance.com/pt-BR/agent-os
- MCP: https://developers.binance.com/en/docs/agent-native/mcp-server/agentic
- Parametros DCA: https://www.binance.com/en-AU/support/faq/detail/797f6e465186474fac2add2f7ce0474a

As referencias descrevem a proposta e os produtos consultados; nao comprovam acesso aos bots DCA da conta. A cobertura sera verificada na etapa 1. O Agent OS agrega APIs, MCP e ferramentas para agentes; nao se presume que ofereca todo o historico dos bots existentes. Conectar um cliente de IA ao MCP tambem nao cria automaticamente uma integracao no projeto PHP.

## Composicao do produto

| Componente | Responsabilidade |
| --- | --- |
| Base `cripto` existente | Compor identidade, layout, menu e modulos do projeto |
| Modulo principal `crypto_dca_manager` | Contas logicas, ativos, operacoes, ciclos, movimentos, metas, historico e calculos consolidados |
| Addon proposto `crypto_binance` | Conexao, consultas, importacao manualmente acionada, conciliacao e diagnostico da cobertura |
| Addon futuro `crypto_dca_simulator` | Cenarios de novas entradas e encerramento, sem modificar operacoes reais |
| Addon futuro `crypto_dca_ai` | Explicacoes e comparacoes sobre dados conferidos, sem executar operacoes |

Os nomes dos novos addons sao propostas; nao representam componentes instalados. Usar manifests `kind: addon`, `attach_to`, schema e rotas conforme os padroes do Core. Declarar dependencias efetivas: os addons dependem do modulo principal; IA nao deve exigir Binance se houver dados manuais suficientes.

O modulo manual permanece utilizavel sem os addons. Financeiro pessoal, Financeiro do Time e carteira comercial do Core nao representam saldos da corretora e nao sao dependencias deste projeto.

## Etapa 0: preparar o laboratorio

1. Conferir bootstrap, instalador, isolamento do banco do projeto e regras de instalacao de addons.
2. Criar um projeto de teste a partir da base Cripto, com nome e identificacao explicitos de laboratorio. Nao reutilizar banco de outro projeto.
3. Validar instalacao atual, login, menu, painel e cadastros manuais com dados ficticios.
4. Registrar a situacao anterior e preparar migracoes que preservem os registros existentes.

Aceite: projeto local funcional e isolado, sem comunicacao com o servidor de producao.

## Etapa 1: validar leitura Binance

Prioridade: descobrir quais dados reais podem ser obtidos antes de redesenhar toda a estrategia.

Implementar o menor addon de diagnostico necessario, com conexao e botao `Atualizar dados`. Comecar pela API oficial se os endpoints atenderem; avaliar MCP como alternativa somente se houver vantagem concreta de cobertura e compatibilidade com o projeto. Nao exigir subconta Agentic nem movimentacao de capital para validar os bots ja existentes.

Validar separadamente:

| Dado | O que comprovar |
| --- | --- |
| Mercado | Simbolos, pares disponiveis, filtros e cotacoes com horario |
| Conta | Saldos e abrangencia: Spot, bots ou outra conta; nao confundir saldo ausente com zero |
| Ordens e execucoes | IDs, simbolo, horario, lado, quantidade, valor e taxas |
| Bots DCA | Identificador, par, estado e parametros efetivamente acessiveis |
| Ciclos | Vinculo das execucoes ao bot e a rodada, incluindo custos e resultados |
| Reservas | Capital aplicado, saldo reservado para compras futuras e capital livre |

Produzir uma matriz com `confirmado`, `parcial`, `indisponivel` ou `nao testado`, informando fonte, periodo coberto e limitacoes. Comparar uma amostra com o painel Binance. Uma lista de trades nao comprova a identidade do bot nem dos ciclos.

Se nao houver vinculo confiavel com os bots, seguir com importacao e associacao manual explicita. Nao atribuir automaticamente todos os trades de um par a um unico DCA. Dados nao identificados ficam pendentes de conciliacao, fora dos totais confirmados.

Aceite: relatorio da cobertura real e amostra conciliada. E aceitavel concluir que parte dos dados exige cadastro manual; nao e aceitavel apresentar cobertura incompleta como sincronizacao completa.

## Etapa 2: adaptar operacoes e grupos

Modelo conceitual:

```text
Conexao da corretora
  Grupo de acompanhamento de um ativo
    Operacao em um par (ex.: BTC/USDT)
      Ciclos
        Compras, vendas e taxas
    Outra operacao (ex.: BTC/USDC)
      Ciclos
        Compras, vendas e taxas
```

- Preservar `crypto_groups` como classificacao setorial; criar estrutura separada para grupos de acompanhamento.
- Preservar a distincao entre carteira logica (`crypto_wallets`) e conta/conexao real da corretora.
- Substituir a dependencia de campos fixos `pair_usdt`/`pair_usdc` por cadastro extensivel de pares. Migrar sem apagar os campos/dados antigos antes de conferir a conversao.
- Vincular ciclos a operacoes; a atual unicidade por ativo e numero do ciclo precisa ser revista para permitir operacoes simultaneas.
- Registrar origem manual/importada, IDs externos, horarios e situacao de conciliacao.
- Usar decimais adequados para preco, quantidade, taxas e cambio, sem depender de `float` para calculos financeiros definitivos.
- Separar ordem de execucao: uma ordem pode ter varias execucoes e taxas em moedas diferentes.

Aceite: mesmo ativo com duas operacoes e ciclos independentes, registros antigos preservados e importacao repetida sem duplicacao.

## Etapa 3: acompanhamento consolidado

Exibir separadamente:

- Lucro realizado dos ciclos, liquido dos custos conhecidos.
- Resultado nao realizado das posicoes abertas.
- Resultado liquido estimado caso o grupo seja encerrado, com custos de saida e horario da cotacao.
- Capital aplicado, reservado e livre, com a abrangencia da conta identificada.
- Aportes, retiradas e reinvestimentos, evitando contar lucro duas vezes.
- Resultado do grupo atual e historico de grupos anteriores.

Escolher e exibir a moeda de referencia e o denominador da rentabilidade. Converter stablecoins por cotacao identificada; nao assumir paridade exata nem misturar unidades. Dados incompletos ou cotacoes antigas devem aparecer como tais e limitar os indicadores que dependem deles.

Metas sao referencias de acompanhamento. Registrar encerramento no Core nao encerra bots: conferir vendas, ordens pendentes e saldos remanescentes antes de marcar resultado como realizado. Prever encerramento parcial e posicao residual.

Aceite: exemplos conferidos com lucro realizado, perda aberta, taxas, reinvestimento e encerramento parcial, sem dupla contagem.

## Etapa 4: simulacao e metas

- Adicionar stablecoins conforme necessidade, com preferencia configuravel e verificacao da disponibilidade do par.
- Permitir +25%, +50%, 2x ou valor livre por nova operacao; registrar qual valor anterior serve de referencia e distinguir entrada inicial de reserva total.
- Separar simulacao de compra realizada.
- Permitir metas de 3%, 5%, 7%, 10% ou valor personalizado, sem impedir encerramento antes ou depois.
- Registrar limites de exposicao e mostrar capital adicional necessario. Trocar a stablecoin nao reduz por si so a exposicao ao mesmo ativo.
- Explicitar se a quantidade de compras representa total ou ordens adicionais a ordem inicial; nao fixar quatro na implementacao.

Aceite: simulacoes nao alteram saldo ou operacoes reais; escolhas e alteracoes de meta possuem historico.

## Etapa 5: automacao de leitura e IA

Somente apos a leitura e os calculos passarem pela conciliacao:

1. Sincronizacao periodica limitada, com bloqueio de execucoes simultaneas, paginacao, retomada e tratamento de limites da API.
2. Alertas sem repeticao excessiva, sobre metas e qualidade/atualidade dos dados.
3. IA opcional, com acesso a dados estruturados e explicacao da origem dos numeros.

IA interpreta; o calculo financeiro permanece deterministico e verificavel. Negociacao automatica e um escopo futuro separado, sem autorizacao nesta adaptacao.

## Isolamento, credenciais e sincronizacao

- Credenciais e dados da conta pertencem ao projeto correspondente. O Core gerencia o produto e a instalacao, sem concentrar historicos privados de todos os projetos.
- Guardar segredos fora do Git, dos manifests, das URLs e dos logs. Reutilizar protecao de credenciais existente somente apos conferir sua adequacao.
- Habilitar apenas leitura nesta fase; nunca solicitar permissao de saque ou negociacao.
- Rotas administrativas com autenticacao e validacao do projeto; atualizacoes via POST com CSRF.
- Importacao com chave unica por origem/conexao/ID externo; atualizacao idempotente e transacional quando aplicavel.
- Registrar ultimo sucesso, periodo importado, cobertura parcial, erro resumido e horario das cotacoes, sem expor credenciais.
- Trabalhar com amostras pequenas inicialmente e dados ficticios nos testes versionados.

## VS Code, Git e servidor

Seguir `docs/fluxo-bases-github-servidor.md`.

- Editar o produto neste workspace e testar com Docker local.
- Manter branch de trabalho `codex/cripto-dca-local`, com commits por etapa validada.
- Sincronizar codigo, schemas, manifests e documentacao pertinentes; nao incluir projetos de runtime, chaves, saldos, historicos reais ou dumps.
- Respeitar alteracoes preexistentes do usuario, inclusive arquivos ja preparados no indice do Git.
- Ao evoluir o produto, conferir consistencia do modulo fonte, copia instalada na base e migracao do projeto de teste, conforme o fluxo do instalador. Nao copiar configuracoes ou bancos de runtime sobre modelos da base.
- Publicar a branch no repositorio privado existente. Push de Git nao equivale a deploy; conferir eventuais gatilhos antes do primeiro push desta branch.
- Nao executar deploy, migracao remota ou alterar producao. Preparacao de release e publicacao ficam para decisao posterior.

## Proxima entrega funcional

Etapas 0 e 1: projeto Cripto local isolado, addon inicial somente de leitura, atualizacao manual e matriz da cobertura real dos DCAs. A estrutura final de sincronizacao sera definida pelo resultado dessa verificacao.
