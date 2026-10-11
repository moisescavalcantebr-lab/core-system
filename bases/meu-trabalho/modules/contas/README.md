# Contas a Pagar e Receber

Modulo principal independente, sem dependencia de Financeiro, Meu Holerite, Beneficios ou Participantes. `accounts_entries` guarda contas pessoais por dono; `accounts_payments` guarda pagamentos/recebimentos reais totais e parciais. Categorias sao rotulos da propria conta, nao tabelas compartilhadas.

Valores positivos em centavos, vencimento obrigatorio, situacao derivada do historico e do dia atual em America/Sao_Paulo. Movimentacoes futuras, valores maiores que o restante e alteracao de tipo de conta ja movimentada sao rejeitados. Cancelamento e exclusao exigem ausencia de movimentacoes. Exclusao explicita de uma movimentacao corrige o restante em aberto. Transacoes bloqueiam a conta para impedir pagamentos concorrentes excedentes.

Documento PDF opcional de ate 2 MB no banco do projeto, sem URL publica. CRUD, movimentacoes, consultas e downloads exigem ADMIN ativo e ficam isolados por dono.

Dashboard: valores em aberto, vencidos, vencimentos em 7 dias, valores pagos/recebidos no mes, grafico real de 12 meses por data da movimentacao, pendencias de 6 meses por vencimento, principais categorias pagas e agenda. Resultado das movimentacoes nao e saldo bancario. Previsoes nao sao dinheiro recebido.

Nao copia `finance_entries`, nao gera receitas a partir de holerites, nao soma VT/VA ao caixa e nao agrega automaticamente um Financeiro instalado no mesmo projeto. Nao faz calculo de impostos, juros ou importacao bancaria. Recorrencias e parcelamento automatico podem ser addons futuros.
