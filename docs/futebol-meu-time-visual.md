# Meu Time: apresentação dos campos e do elenco

Atualização visual local em 19/10/2026. Configurações, quantidade de jogadores por campo, quantidade de times, contadores, vagas, coordenadas das posições e regras de escalação foram preservados. Não houve alteração de schema, limite de elenco ou configurações do usuário.

## Distribuição

- PC com um campo: campo à esquerda e disponíveis à direita.
- PC com dois campos: campos lado a lado, com disponíveis abaixo. A faixa de disponíveis acompanha a quantidade de jogadores, preservando uma altura legível para os campos em janelas baixas.
- Celular: botões para Time 1, Time 2 quando existente, e Disponíveis, alternando apenas a apresentação na mesma página. Nome dos times e contadores continuam vindo das configurações existentes.
- O tamanho do quadro considera a altura visível, o cabeçalho e o rodapé. A grade calcula sua capacidade e pagina os jogadores quando necessário, inclusive com trinta disponíveis. A paginação não altera o elenco e preserva os formulários e suas escolhas. Registros legados acima de trinta continuam acessíveis.

O campo recebeu faixas de gramado, contorno, áreas proporcionais, pequenas áreas, arcos e redes nos gols. Nomes têm fundo de contraste; posições aparecem em etiquetas junto ao avatar. Fotos existentes continuam funcionando e iniciais são usadas quando não há foto. Estilos de salão, society e areia mantêm suas cores próprias. Resumo e botões existentes continuam disponíveis.

O seletor de jogador abre sobre a página, sem empurrar os campos. A apresentação neutraliza o movimento de hover dos cards apenas nesta tela, evitando que o efeito global do Core desloque esse seletor.

## Arquivos e sincronização

Apresentação em `modules/meu_time/web/admin/meu_time/appearance.php`, incluída pela tela index. As cópias instaladas em `bases/futebol/modules/meu_time` e nas rotas de `bases/futebol/web/admin/meu_time` estão alinhadas ao catálogo. O manifesto existente já copia toda essa pasta, incluindo a nova apresentação.

Alterações salvas na branch local `codex/futebol-gratis`. Não havia projeto Futebol instalado no Docker local a sincronizar. Não foi feita publicação no servidor nesta etapa; para atualizar uma instalação existente, publicar os arquivos e sincronizar a base/projeto, incluindo ambos index.php e appearance.php.

Publicação concluída em 19/10/2026 (20/10 às 01:16 UTC), pelo publicador limitado `scripts/futebol-publicar.ps1`: catálogo, base e um projeto Futebol instalado atualizados. Baselines, sintaxe PHP, hashes finais e saúde do Core aprovados. Banco de dados e configurações dos projetos preservados. Backup privado: `/opt/private-futebol/release-20261020T011650Z-1SWZQt30`. Relatório local: `_deploy/futebol-publication.txt`.

Correção dos avatares: a proteção global de `storage` retornava 403 para `/projects/<slug>/web/storage/uploads/avatars/<arquivo>`. Exceção restrita a esse caminho e a extensões raster permite as fotos públicas, mantendo arquivos privados, PHP e SVG bloqueados. Teste HTTP local confirmou 403 antes e 200 depois. Meu Time mostra iniciais se o arquivo estiver ausente; teste de navegador `scripts/tests/team_avatar_ui.cjs` cobre falha imediata/tardia e preservação de fotos válidas. Publicação limitada: `scripts/futebol-publicar.ps1 -AvatarFix`; inclui sete arquivos de origem e suas cópias nos projetos, valida imagens existentes por HTTP e mantém backup/reversão.

## Validação

`team_formations_test.php`: banco descartável, regras Grátis/Start, configurações e dados preservados, nomes, vagas, alteração opcional da posição principal e cenários com trinta disponíveis e dois campos completos. `football_modules_test.php`: paridade entre catálogo/cópias e preservação dos schemas/dados existentes.

`team_formations_ui.cjs`: cenários de um/dois times, nomes personalizados, elenco cheio, Grátis e configurações nos tamanhos 1280×900, 1024×768, 1194×634, 360×800 e 390×667. Conferidos navegação por abas, paginação, seleção de vagas, abertura/fechamento do seletor, ausência de erros JavaScript, sobreposição na grade e rolagem horizontal/vertical da página de elenco. Configuração continua com seu fluxo anterior. Capturas com dados sintéticos em `_deploy/team-*-preview.html` e `_deploy/team-*.png`.
