# Plano Gratis: amistoso e time principal

- O amistoso externo permanece disponivel no Gratis e no Start, respeitando os limites de competicoes e partidas ja existentes.
- Seu resumo mostra partidas, resultados e gols, sem o indicador de pontos. Campeonatos continuam com pontos e posicao oficial.
- Criacao e edicao fixam `lineup_mode=team_roster`, inclusive em POST direto. Os formularios identificam Meu Time principal, sem seletor de modo.
- A escala e o campo continuam sendo copiados ao criar a partida, sem remontar partidas antigas ao alterar Meu Time.
- O segundo time de Meu Time exige Start ou superior. A verificacao segue os metadados de plano do projeto e o helper de plano quando disponivel; sem identificacao de plano pago, nao libera.
- No Gratis, so o primeiro campo e as vagas dele aparecem. Ativar ou escalar no segundo time por POST e bloqueado no servidor. A configuracao antiga e preservada no banco, sem desativacao ou exclusao automatica.
- Criar um treino com dois times tambem exige essa permissao, mesmo quando um segundo time antigo permanece registrado. Partidas anteriores continuam consultaveis.

Nenhuma definicao financeira, limite comercial ou outro beneficio do Start foi reformulado nesta entrega.

## Ajuste do Grátis em 19/10/2026

- Classificação deixa de aparecer no menu e no dashboard do Grátis. O bootstrap da base bloqueia também acesso direto às rotas de Classificação nesse plano. O módulo e suas tabelas permanecem instalados para preservar dados e o funcionamento do Start.
- Competições mostra somente o cartão Amistoso no Grátis, sem cartões de Campeonato/Treino ou chamada de outros planos no subtítulo. A seleção do formulário fica restrita a Amistoso externo; criação e alteração por POST rejeitam outros tipos, inclusive tentativa de converter uma competição antiga em Amistoso.
- A identificação usa o plano/ciclo do `project.json`, com suporte a Gratis/Grátis e fallback conservador. Start mensal/anual conserva seu menu e recursos existentes.
- Catálogo, cópia instalada da base e rotas da base foram alinhados. Sem alteração de schema, exclusão de módulos ou dados. Não havia projeto Futebol instalado no Docker local para sincronizar; o único projeto existente era de outra base, preservado. A implantação no servidor e a sincronização de projetos existentes não foram executadas nesta etapa.

Validação: `football_tracking_test.php` com banco descartável e verificações de menu Grátis/Start, tipos bloqueados via POST, preservação de competições e formulário restrito; `football_modules_test.php` com paridade de arquivos e schemas idempotentes. `football_tracking_ui.cjs` verificou vinte combinações de tela/viewport, incluindo Grátis com um único cartão, sem overflow ou erros JavaScript. Nenhuma configuração financeira foi alterada.

Depois do deploy, sincronizar Meu Time, Competicoes e Partidas na base Futebol e no projeto.

Testes: `team_formations_test.php` e `team_formations_ui.cjs` cobrem Gratis/Start, POST direto e preservacao. `football_tracking_test.php` e `football_tracking_ui.cjs` cobrem amistoso no Gratis, modo fixo na criacao/edicao, ausencia de pontos e regressoes de campeonato/treino.
