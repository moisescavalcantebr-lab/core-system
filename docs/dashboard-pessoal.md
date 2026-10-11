# Dashboard pessoal: contratos e indicadores

## Analise dos modulos

- Meu Holerite e o dono dos recibos, rubricas, bases e totais salariais. Competencia nao e data de pagamento. Liquido informado nao e saldo bancario.
- Beneficios e addon do Meu Holerite: VT e VA disponibilizados por empresa/matricula/competencia. Nao controla consumo, nao replica descontos e nao entra no caixa das contas.
- Contas e principal independente: obrigações a pagar/receber e historico de movimentacoes efetivas, sem exigir Participantes nem um holerite. Categorias sao rotulos simples do modulo.
- Financeiro ja possui `finance_entries` e seus proprios paineis, categorias, carteiras e integracoes. Nao foi instalado novamente nesta base nem teve dados copiados para Contas. Se os dois forem instalados em outro projeto, os paineis permanecem separados; qualquer futura integracao precisa declarar uma unica fonte para evitar dupla contagem.
- Categorias e Cartao de Credito continuam vinculados a Financeiro, nao a Contas. Beneficios nao se torna principal por possuir cadastro proprio.
- Participantes continua responsavel por perfis, nao por registros financeiros nem por login obrigatorio em projetos pessoais.
- Os modulos de futebol, Tips Survivor, Cripto e Divulgacao mantem seus paineis de dominio e nao foram adicionados a esta base. Classificacao/Estatisticas e os addons sem `dashboard.php` dependem de consumidores especificos; nao ha indicadores ficticios para recursos ausentes.

## Composicao da base Meu Holerite

A visao geral reune os paineis instalados, com abas especificas para Contas e Holerites/Beneficios. Essa mudanca e da base pessoal; nao altera dashboards de outras bases/projetos automaticamente. Cada painel valida usuario ADMIN ativo e filtra o dono dos dados.

Contas: totais restantes em aberto (incluindo vencidos), vencidos separados, vencimentos em 7 dias, pago/recebido no mes por data efetiva e resultado das movimentacoes. Grafico real de 12 meses, grafico de pendencias de 6 meses por vencimento, principais categorias efetivamente pagas e agenda ordenada. Contas canceladas nao entram em pendencias. A previsao nao inclui automaticamente salario ou beneficios. Documentos PDF nao sao carregados nas listagens.

Holerites/Beneficios: filtro de ano de competencia e empresa, proventos/descontos/liquido, media por competencia, VT e VA separados, graficos mensais de remuneracao e beneficios e tabela detalhada com variacao liquida. Ausencia de registro e exibida como `-`, diferente de valor zero. Comparacao liquida exige registros nos dois meses consecutivos.

## Limites

Nao ha sincronizacao automatica entre holerite, beneficios, Financeiro e Contas. Nao ha saldo bancario inferido, importacao de extratos, calculo de impostos/juros nem agendamento de alertas. Pagamentos parciais ficam em historico proprio e repeticao do mesmo envio nao cria outro movimento. Parcelamento e recorrencia automatica podem virar addons quando houver necessidade real.
