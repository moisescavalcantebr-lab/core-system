# Plano Gratis: amistoso e time principal

- O amistoso externo permanece disponivel no Gratis e no Start, respeitando os limites de competicoes e partidas ja existentes.
- Seu resumo mostra partidas, resultados e gols, sem o indicador de pontos. Campeonatos continuam com pontos e posicao oficial.
- Criacao e edicao fixam `lineup_mode=team_roster`, inclusive em POST direto. Os formularios identificam Meu Time principal, sem seletor de modo.
- A escala e o campo continuam sendo copiados ao criar a partida, sem remontar partidas antigas ao alterar Meu Time.
- O segundo time de Meu Time exige Start ou superior. A verificacao segue os metadados de plano do projeto e o helper de plano quando disponivel; sem identificacao de plano pago, nao libera.
- No Gratis, so o primeiro campo e as vagas dele aparecem. Ativar ou escalar no segundo time por POST e bloqueado no servidor. A configuracao antiga e preservada no banco, sem desativacao ou exclusao automatica.
- Criar um treino com dois times tambem exige essa permissao, mesmo quando um segundo time antigo permanece registrado. Partidas anteriores continuam consultaveis.

Nenhuma definicao financeira, limite comercial ou outro beneficio do Start foi reformulado nesta entrega.

Depois do deploy, sincronizar Meu Time, Competicoes e Partidas na base Futebol e no projeto.

Testes: `team_formations_test.php` e `team_formations_ui.cjs` cobrem Gratis/Start, POST direto e preservacao. `football_tracking_test.php` e `football_tracking_ui.cjs` cobrem amistoso no Gratis, modo fixo na criacao/edicao, ausencia de pontos e regressoes de campeonato/treino.
