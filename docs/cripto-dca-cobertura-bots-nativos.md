# DCA Spot nativo Binance: cobertura e caminho de integracao

Data da verificacao: 22/10/2026 (America/Sao_Paulo).
Escopo: pesquisa de fontes oficiais e analise da integracao local. Nenhuma chamada nova com credenciais, negociacao, transferencia ou alteracao no servidor.

## Resultado

A conexao atual le saldo, ordens abertas e negociacoes da conta Spot pela API oficial. Ela nao consulta o cadastro, as rodadas ou os resultados dos bots DCA nativos. Consultas Spot bem-sucedidas nao comprovam cobertura da carteira de Trading Bots.

A Binance documenta que os ativos dos DCAs sao transferidos da conta Spot para a conta Trading Bots. A documentacao orienta abrir os detalhes de um bot para consultar lucro, parametros, rodada atual e historico de negociacoes.

No indice publico oficial de documentacao/API consultado nesta data, nao foi localizada uma rota documentada para listar os bots DCA Spot nativos, suas rodadas e PnL. Isso e uma limitacao da evidencia encontrada, nao prova de que nenhum acesso exista para qualquer conta, parceiro ou produto. Nao prometer sincronizacao nativa sem contrato oficial e amostra valida.

## Distincoes importantes

- Spot DCA nativo nao e Auto-Invest. Um bot externo que faz DCA via API tambem nao e o bot nativo criado no site.
- As rotas documentadas `/sapi/v1/algo/spot/openOrders`, `historicalOrders` e `subOrders` pertencem a Algo Trading. O material consultado descreve TWAP/VP; nao comprova que essas rotas retornem os DCAs nativos. Nao acrescenta-las ao produto como se essa cobertura estivesse confirmada.
- A API `myTrades` consulta conta e par. O intervalo de uma requisicao por `startTime`/`endTime` nao pode exceder 24 horas; o projeto escolheu apenas a ultima janela. Consultar outras janelas pode ampliar o historico Spot, mas nao garante acesso aos bots ou vinculo de operacoes a rodadas.
- O numero de rodadas concluidas ao longo da vida do bot nao e o numero de trades das ultimas 24 horas.
- Lucro DCA, lucro flutuante e lucro total sao indicadores distintos. Nao substituir um pelo outro nem reconstruir resultado completo a partir do saldo Spot.
- MCP/Agent Native e um meio de acesso a ferramentas/documentacao. Os materiais consultados nao comprovam uma ferramenta com cobertura adicional desses bots.

## Matriz atual

| Informacao | Situacao |
| --- | --- |
| Mercado/par/cotacao | API Spot implementada |
| Saldos Spot | API Spot implementada; nao equivale ao capital nos bots |
| Ordens e trades Spot | Amostra limitada por par e periodo |
| Identidade e parametros dos bots nativos | Sem rota oficial confirmada nesta pesquisa |
| Rodadas e negociacoes de cada bot | Visiveis nos detalhes da interface; importacao ainda nao validada |
| PnL DCA/flutuante/total dos bots | Visivel na interface; nao retornado pela integracao atual |
| Exportacao dos detalhes/historico | Precisa ser verificada na interface da conta do usuario |

## Proximo passo concreto

1. Abrir os detalhes de um unico DCA nativo pelo icone de ordem da lista. A FAQ oficial confirma esse caminho de leitura.
2. Conferir identificador do bot, par, parametros, rodada atual e historico. Verificar se a interface oferece exportacao; a disponibilidade/formato nao foi confirmada. Nao afirmar que existe CSV universal.
3. Se houver arquivo oficial, inspecionar primeiro o cabecalho/formato, sem gravar dados no Core. Definir importacao com previa, origem, horario, identificador e confirmacao.
4. Se nao houver exportacao, escolher entre registro manual identificado no Core e consulta ao suporte Binance sobre rota oficial disponivel para a conta. Preparar o texto da pergunta nao autoriza envia-la automaticamente.
5. Uma eventual rota oficial so entra no addon depois de documentar autenticacao, permissao somente leitura, campos, paginacao, limites e amostra conferida com a tela.

Nao criar bots ficticios, completar valores ausentes, atribuir trades apenas pelo par ou registrar os numeros de uma captura como se tivessem sido sincronizados. Capturas podem orientar a verificacao, mas nao fornecem identidade/historico completos.

## Adaptacao local proposta apos validar a fonte

- Preservar `crypto_binance` como addon de conexao/importacao e `crypto_dca_manager` como gestor de operacoes/ciclos.
- Manter leitura Spot e dados dos bots nativos com origens e coberturas distintas.
- Registrar bot/operacao com identidade estavel; rodadas, execucoes e taxas vinculadas explicitamente.
- Importar primeiro em area de conciliacao, com previa e deduplicacao. Nao somar snapshots acumulados como se fossem movimentos financeiros novos.
- Separar o PnL informado pela Binance do resultado calculado pelo Core. Indicar data, fonte, moeda e eventuais custos ainda nao conciliados.
- Antes de usar os calculos existentes para dados reais importados, adequar precisao decimal, taxas, vendas parciais e conversao de moedas. O encerramento manual atual e simplificado.

## Fontes oficiais verificadas

- FAQ DCA Spot (detalhes, rodadas e carteira separada): https://www.binance.com/en/support/faq/detail/27713d3ddb3c406da52f36b9aaaa1360
- Parametros e tipos de lucro: https://www.binance.com/en-JP/support/faq/detail/797f6e465186474fac2add2f7ce0474a
- Indice publico oficial, inclusive catalogo Algo: https://developers.binance.com/en/docs/llms.txt
- Introducao Algo Trading: https://developers.binance.com/en/docs/products/algo/Introduction
- API Spot, conta e myTrades: https://developers.binance.com/en/docs/catalog/core-trading-spot-trading/api/rest-api/account
- Agent Native: https://developers.binance.com/en/docs/agent-native/overview
