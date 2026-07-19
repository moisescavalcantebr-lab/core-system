# Manual do Content Studio

O Content Studio e um modulo nativo do Core para organizar producao de conteudo, campanhas, midias, calendario e leads. Ele nao gera conteudo automaticamente nesta fase; ele organiza o fluxo para voce produzir, publicar e medir.

## 1. Dashboard

Caminho: `/web/admin/content_studio/index.php`

Use para acompanhar a visao geral:

- campanhas ativas e rascunhos
- ideias abertas
- publicacoes planejadas, publicadas e atrasadas
- roteiros em producao
- prompts ativos
- midias vinculadas
- leads atribuidos ao Content Studio

O dashboard e apenas leitura. Os botoes levam para as telas de trabalho.

## 2. Campanhas

Caminho: `/web/admin/content_studio/campaigns.php`

Use campanhas para organizar uma acao de divulgacao ou venda.

Campos principais:

- Nome: nome interno da campanha.
- Slug: identificador amigavel.
- Chave: usada para rastrear leads.
- Objetivo: resumo do que a campanha deve gerar.
- Projeto: opcional, quando a campanha esta ligada a um projeto do Core.
- Pagina: opcional, quando usa uma landing/page do Core.
- URL externa: opcional, para checkout, WhatsApp, pagina de afiliado ou destino fora do Core.
- Status: rascunho, ativa, pausada ou finalizada.

Quando a campanha tem pagina ou URL externa, o sistema gera links rastreaveis com `cs_campaign` e `cs_source`.

## 3. Configuracoes

Caminho: `/web/admin/content_studio/settings.php`

Use para cadastrar a base de organizacao:

- Canais: Instagram, TikTok, YouTube, Blog, trafego pago, WhatsApp etc.
- Nichos: segmentos de conteudo ou mercado.
- Personagens: voz, persona, avatar editorial ou tipo de comunicacao.

O sistema evita duplicidade simples em canais, nichos e personagens.

## 4. Ideias

Caminho: `/web/admin/content_studio/ideas.php`

Use para registrar ideias antes de virar roteiro.

Campos principais:

- Campanha
- Nicho
- Personagem
- Titulo
- Gancho
- Formato
- Prioridade
- Status
- Notas

Uma ideia pode existir sem campanha, mas o ideal e vincular quando ela fizer parte de uma acao especifica.

## 5. Producao

Caminho: `/web/admin/content_studio/production.php`

Use para transformar ideias em roteiros e prompts.

Roteiros:

- vinculam uma ideia
- guardam texto, CTA e status de producao
- servem para video, post, anuncio, email ou pagina

Prompts:

- podem ser vinculados a uma ideia ou roteiro
- guardam instrucoes reutilizaveis
- preparam a estrutura para futura integracao com IA

## 6. Calendario

Caminho: `/web/admin/content_studio/calendar.php`

Use para planejar publicacoes.

Campos principais:

- Titulo
- Campanha
- Canal
- Ideia
- Destino
- Data agendada
- Status
- Notas

Se escolher uma campanha e deixar o destino vazio, o sistema tenta usar o destino rastreavel da campanha.

Indicadores:

- planejadas
- publicadas
- atrasadas
- canceladas

## 7. Midia

Caminho: `/web/admin/content_studio/media.php`

Use para vincular imagens da Biblioteca de Imagens ao Content Studio.

Importante:

- O upload continua em `/web/admin/media/`.
- O Content Studio nao duplica arquivos.
- A tela apenas vincula uma imagem existente a campanha, ideia ou publicacao.

Tipos de uso:

- referencia
- criativo
- capa
- publicacao
- prova

## 8. Leads

Os leads continuam no fluxo normal do Core.

Quando um formulario recebe os parametros `cs_campaign` e `cs_source`, o lead fica atribuido a campanha e origem.

Fluxo recomendado:

1. Criar campanha.
2. Vincular landing page ou URL externa.
3. Copiar link rastreavel da campanha.
4. Usar o link em trafego pago, rede social, WhatsApp ou bio.
5. Acompanhar os leads no Content Studio e na tela de Leads do Core.

## 9. Fluxo Completo

Ordem recomendada para usar:

1. Configurar canais, nichos e personagens.
2. Criar campanha.
3. Criar ideias vinculadas a campanha.
4. Criar roteiro e prompts quando precisar.
5. Vincular imagens da biblioteca.
6. Planejar publicacoes no calendario.
7. Usar os links rastreaveis na divulgacao.
8. Acompanhar leads e publicacoes no dashboard.
9. Refinar campanhas conforme o resultado.

## Observacoes Para Evolucao

O modulo ficou preparado para futuras etapas:

- integracao com IA
- APIs de redes sociais
- automacoes de publicacao
- analytics por campanha
- metricas de trafego pago
- relatorios por nicho/personagem/canal

Essas evolucoes devem usar a estrutura atual em vez de criar um segundo modulo paralelo.
