# Trafego: primeira entrega

O modulo principal `trafego` envia solicitacoes ao Content Studio. Landing Pages continua independente e os leads ficam no banco do projeto. Nao ha cobranca, reserva de saldo, integracao bancaria, proposta comercial ou veiculacao automatica nesta entrega.

A etapa operacional manual seguinte esta documentada em `marketing-trafego-operacao.md`; inclui planejamento, registro de estados observados nas plataformas e resultados diarios, sem financeiro.

## Projeto

- Instalar/sincronizar o modulo Trafego na base Marketing.
- Menu Trafego: criar briefing, salvar rascunho e enviar para analise.
- Destinos: pagina publicada do proprio projeto, link externo ou link oficial do WhatsApp.
- Contato usa nome/e-mail da conta administrativa ativa; telefone e negocio sao informados no briefing.
- Confirmar autorizacao para representar o negocio antes de enviar. Este registro nao e verificacao documental ou fiscal.
- Materiais sao referencias/textos/links nesta etapa, sem upload de anexos.
- Ao escolher "Preciso de orientacao", aparece o campo de duvida. A pergunta e obrigatoria para enviar, mas pode ficar vazia em um rascunho. A duvida enviada aparece no briefing e no historico compartilhado.
- O cliente pode editar rascunhos e pedidos devolvidos para ajustes. Solicitacoes em analise ficam bloqueadas para edicao.

## Core

- Content Studio > Solicitacoes recebe apenas pedidos enviados, nunca rascunhos.
- Iniciar analise, solicitar ajustes com motivo, ou informar que o pedido nao sera atendido.
- Apos iniciar analise, preparar campanha cria uma unica campanha em rascunho, vinculada ao projeto e ao destino recebido.
- Datas sao preferencias do cliente e nao confirmam agendamento na plataforma.
- O retorno do operador e o historico sao compartilhados com o projeto.
- "Enviar resposta" registra uma mensagem sem mudar o status ou criar campanha. Funciona em pedidos enviados, em analise, aguardando ajustes e preparados. O cliente consulta a resposta no painel; nao ha notificacao por e-mail nesta etapa.
- Mensagens predefinidas sao selecionadas e editadas antes do envio. Selecionar um modelo nao envia mensagem nem altera o andamento; texto ja digitado so e substituido apos confirmacao.
- "Iniciar analise" continua separado, podendo registrar uma resposta junto da mudanca de status.
- Cada resposta do Studio abre a conversa para o cliente responder no painel. Solicitar ajustes tambem abre essa troca. As respostas de ambos os lados permanecem no historico, sem alterar o status ou criar campanhas.
- Apenas o Studio pode encerrar a conversa. Enquanto estiver aberta, iniciar analise ou preparar campanha fica bloqueado. Uma nova resposta do Studio reabre a conversa; cancelamento ou recusa encerra a troca.
- Pedidos antigos recebem o controle de conversa fechado por padrao; enviar uma nova resposta abre a conversa sem apagar mensagens anteriores.
- Campanha preparada nao significa campanha aprovada, paga ou em veiculacao. A aprovacao do planejamento e um ajuste complementar (`marketing-trafego-aprovacao.md`). A terceira entrega cobre relatorios, encerramento e preparacao comercial (`marketing-trafego-entrega.md`). Fluxo financeiro e proposta comercial continuam adiados.

## Dados e seguranca

As tabelas `content_studio_traffic_requests` e `content_studio_traffic_events` vivem somente no Core. O schema e criado de forma idempotente ao abrir o fluxo. O modulo nao cria tabelas no banco do projeto.

O ID de projeto e resolvido pelo slug da pasta instalada e conferido com `project.json`; nao vem de formularios. Apenas projetos ativos podem acessar o fluxo. Consultas e alteracoes do cliente sao filtradas pelo projeto. Rotas de alteracao exigem POST, administrador ativo e CSRF. Revisoes e bloqueios transacionais impedem sobrescrita de analises e preparacao duplicada de campanhas.

A comunicacao reutiliza o bridge local projeto-Core. Hospedagem separada exigira uma API autenticada futura; nao oferecer acesso remoto ao banco. Links externos nao sao buscados pelo servidor. Nao compartilhar leads ou credenciais de anuncios com o projeto.

## Deploy e teste

Enviar Core e base juntos. Em projetos existentes, sincronizar os modulos da base Marketing pelo Core para copiar o novo modulo e suas rotas. Isso disponibiliza Trafego; nao altera Landing Pages nem registros anteriores.

A configuracao `app_url` do Core deve ser o endereco publico correto para gerar destinos das paginas do projeto. Recebimentos/devolucoes continuam manuais e nenhuma carteira e alterada por este modulo.

Teste: `docker exec app_php php /var/www/html/scripts/tests/traffic_requests_test.php`.
