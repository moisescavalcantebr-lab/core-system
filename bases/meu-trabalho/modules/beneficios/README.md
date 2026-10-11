# Beneficios

Addon do Meu Holerite para registrar o que a empresa disponibiliza de vale-transporte e vale-alimentacao. Nao controla gastos, saldo de cartoes, direitos trabalhistas ou lancamentos financeiros.

Cada credito pertence ao usuario e ao vinculo empresa/matricula, com competencia, tipo, valor previsto opcional, valor disponibilizado, data de credito e observacoes. Um registro mensal por beneficio/vinculo evita duplicidade; se houver creditos complementares, edite o total mensal. Nao exige holerite da mesma competencia e nao replica seus descontos.

Comprovante PDF opcional de ate 2 MB armazenado no banco do projeto, com download privado, substituicao e remocao. Registros e comprovantes ficam isolados por dono ADMIN ativo. Historico com filtros, comparacao mensal e totais anuais de VT e VA separados. O addon complementa o painel do Meu Holerite quando instalado, sem somar beneficios ao salario liquido.

Instale Meu Holerite antes de Beneficios na base e sincronize os modulos no projeto. `database/schema.sql` e idempotente e nao modifica tabelas do principal. `tests/check.php` verifica validacao e, com `BENEFITS_MYSQL_TEST=1`, persistencia, duplicidade, isolamento e comprovantes em banco temporario.
