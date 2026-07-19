# Cartao de Credito

Addon do modulo Financeiro para organizar cartoes, compras, parcelas e faturas.

## Objetivo

O financeiro continua sendo o caixa principal. Esta addon controla o ciclo do cartao antes da fatura virar um lancamento financeiro.

Fluxo previsto:

1. Cadastrar cartoes.
2. Registrar compras a vista ou parceladas.
3. Agrupar parcelas em faturas.
4. Fechar a fatura.
5. Lancar a fatura no Financeiro como saida pendente.
6. Marcar como paga pelo Financeiro.

## Plano

Disponivel a partir do Plano Start.

## Tabelas

- `finance_credit_cards`
- `finance_credit_card_purchases`
- `finance_credit_card_invoices`
- `finance_credit_card_installments`
