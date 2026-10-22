# Binance - leitura (laboratorio)

Addon do `crypto_dca_manager` para conferir a cobertura dos dados Binance.

- Atualizacao somente por POST administrativo com CSRF.
- Consultas GET limitadas a endpoints oficiais de leitura; sem ordens, cancelamentos ou transferencias.
- Modo publico sem chave, demonstracao com valores ficticios e consulta de conta com chave/segredo HMAC fornecidos a cada atualizacao.
- Chaves nao persistidas, nem exibidas novamente ou incluidas em logs da aplicacao.
- Amostra de trades das ultimas 24 horas, ate 100 por par; ate tres pares. Nao e importacao do historico completo.
- Negociacoes reais sao identificadas por chave da conexao, simbolo e ID externo, sem duplicar na repeticao. Demonstracao nao entra na tabela de trades reais.
- Dados Spot nao sao associados automaticamente a bots DCA, ciclos ou reservas e nao alteram os cadastros manuais.
- Restrito ao ambiente local/laboratorio. A versao atual nao e liberada para producao.

Preparacao da base: `php scripts/cripto-preparar-local.php` no container local.
Guia: [teste local](../../docs/cripto-dca-teste-local.md).
