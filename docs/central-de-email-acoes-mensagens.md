# Ações de mensagens — preparação local

Implementadas para Gmail e Zoho: marcar lida/não lida, mover para pasta da mesma conta, enviar para lixeira com confirmação e restaurar escolhendo um destino em Mover. Controles ficam na mensagem aberta, inclusive no painel de Todos os e-mails. Exclusão definitiva foi preparada na etapa posterior descrita abaixo, somente para mensagens na Lixeira e com confirmação. Não há ação automática ao abrir uma mensagem. Outlook e operações de criação/renomeação/exclusão de pastas continuam fora desta etapa.

## Ativação local e consentimentos pendentes

O código usa `mail_center.remote_organization_enabled`, ausente/desabilitado por padrão. Em produção nada foi alterado nesta etapa. Com a opção desabilitada, os controles ficam ocultos, a rota retorna 403 e a autorização continua solicitando somente os escopos anteriores. Ativar por configuração privada apenas depois de revisar/publicar o conjunto completo de arquivos e informações de privacidade, com backup e verificação de saúde.

Em 19/10/2026 a opção foi habilitada somente no ENV ignorado do Docker HTTPS local. O procedimento confere a URL local, compara o hash do ENV do container com o workspace e salva uma cópia anterior na pasta privada externa, em `local-config-backups`. Sintaxe e carregamento aprovados; Gmail e Zoho prontos, com os novos escopos presentes na próxima autorização, mas ainda não concedidos às conexões no momento da conferência. Nenhuma chamada aos provedores ou alteração nas mensagens foi executada nessa ativação. Produção permanece sem as ações de organização.

Ferramenta: `scripts/central-contas-organizacao-local.ps1 -Mode Enable`; para desabilitar apenas a opção, usar `-Mode Disable`. Não restaura automaticamente o ENV inteiro sobre eventuais alterações posteriores. O consentimento é feito pelo proprietário no navegador; a ativação de código não o substitui.

Após o proprietário concluir as duas reconexões, a conferência local confirmou Gmail e Zoho conectados e autorizados para organização. Essa conferência leu somente estados e permissões locais, sem chamadas aos provedores ou alterações nas mensagens. Gmail tinha um destino disponível no cache e Zoho sete. Posteriormente, o proprietário relatou que testou localmente e funcionou, inclusive o envio para a Lixeira. O agente não executou alterações reais: o assunto indicado não foi localizado na consulta recente das duas contas. Não foi realizado envio de e-mail nem foram escolhidas mensagens comuns para alteração.

Quando habilitado, contas conectadas sem os novos escopos exibem em Configurações **Autorizar organização de mensagens**. Leitura e envio existentes continuam disponíveis; os botões de organização só aparecem após conferir as permissões efetivamente registradas na conexão. A renovação de consentimento não copia tokens entre ambientes.

1. Para organização sem exclusão definitiva no Gmail, Google Cloud → Google Auth Platform → Acesso a dados: acrescentar `https://www.googleapis.com/auth/gmail.modify`, mantendo os escopos já usados. Revisar consentimento e condição de verificação para uso pessoal. Exclusão definitiva exige autorização separada, descrita abaixo.
2. Com o código local habilitado para a validação, entrar na Central → Configurações → Gmail → **Autorizar organização de mensagens** e consentir no site do Google.
3. Repetir no Zoho. O OAuth solicitará `ZohoMail.messages.UPDATE` e `ZohoMail.messages.DELETE`, além das permissões atuais. Enviar para a lixeira usa `expunge=false`; a operação separada de exclusão definitiva usa `expunge=true`, apenas após confirmação.
4. Validar com uma mensagem e uma pasta de teste da mesma conta: lida/não lida, mover, lixeira e restauração. Conferir cada resultado no webmail. Não usar mensagens importantes nessa validação. O teste real ainda depende de autorização do proprietário para os itens escolhidos.
5. Publicar e repetir a renovação em produção numa etapa própria. Os tokens locais não serão reaproveitados.

## Comportamento e limites

Atualização local de 19/10/2026: não lidas têm ponto colorido, remetente/assunto em negrito e fundo discreto; lidas mantêm peso normal. A cor de sinalização escolhida pelo usuário continua prioritária. A indicação acessível do item informa Lida/Não lida.

Na Lixeira, o menu de envio para lixeira é substituído por **Excluir definitivamente**. A confirmação informa que a mensagem não poderá ser recuperada. O servidor verifica a conta, o proprietário, as permissões e a presença na Lixeira novamente no provedor antes de tentar excluir. O cache só passa a ausente depois do reconhecimento da exclusão e de uma consulta que confirme mensagem não encontrada. Resultado incerto não gera repetição automática. Não há esvaziamento em massa nem exclusão de pastas nesta função.

Zoho usa a permissão DELETE já concedida. Gmail exige `https://mail.google.com/`, conforme a API delete; gmail.modify não basta. A opção separada `mail_center.gmail_permanent_delete_enabled` prepara a próxima autorização somente quando explicitamente habilitada. Nesse modo o OAuth pede apenas o escopo completo, que já cobre leitura/envio/organização; não soma escopos redundantes. O botão de exclusão no Gmail fica desabilitado enquanto a conexão não tiver esse escopo.

O procedimento `central-contas-organizacao-local.ps1 -Mode Enable -GmailPermanentDelete` foi aplicado com backup somente no ENV local para preparar a reconexão solicitada pelo recurso. Isso não concede acesso: o proprietário ainda precisa cadastrar o escopo no Google Cloud e aceitar nova autorização em **Configurações → Autorizar exclusão definitiva no Gmail**. O Zoho não precisa reconectar para esta alteração. Produção continua sem essas ações.

Validação dessa atualização: 516 verificações em banco descartável, com provedores simulados, e 12 cenários de navegador (temas claro/escuro, desktop/celular, contraste de estados, Gmail sem permissão e confirmação obrigatória). Nenhuma exclusão real foi executada pelo agente.

- Gmail: mover remove a origem selecionada quando ela é Caixa de entrada, Spam ou marcador próprio, e adiciona o destino; preserva outros marcadores. Em Todos os e-mails remove Caixa de entrada ou Spam, mantendo os demais marcadores. Pastas de sistema não são destinos, exceto Caixa de entrada. Restauração usa untrash antes de aplicar o destino.
- Zoho: mover usa PUT updatemessage; marcar usa markAsRead/markAsUnread. Lixeira usa DELETE com expunge=false e recusa uma mensagem já na lixeira. A pasta Lixeira precisa ser identificada de forma inequívoca pelo tipo informado pelo Zoho.
- A lista local só muda após confirmação e nova consulta de metadados no provedor. Em falha depois da tentativa de escrita, mostra resultado potencialmente incerto, sem repetir automaticamente. Conferir o webmail e atualizar a caixa antes de repetir. Uma operação de múltiplas chamadas, como restaurar e mover no Gmail, pode ficar parcialmente aplicada.
- As operações compartilham o bloqueio por caixa da sincronização, validam proprietário/conta/pasta e geração da conexão. A rota exige POST e CSRF. Auditoria registra somente ação e resultado, sem conteúdo, endereços ou tokens. Nenhuma migração nova é necessária.
- Favoritos, sinalizadores e fixação permanecem locais e são preservados. Busca e filtros continuam respeitando o contexto selecionado.

## Referências oficiais

Validação local concluída: 460 verificações na suíte piloto, incluindo 135 de organização remota, com banco descartável e provedores simulados. Interface: seis cenários de ações em desktop/celular e 12 combinações de leitura existentes aprovados; capturas sintéticas revisadas sem overflow. Cobertura inclui permissões antigas, opção desabilitada, CSRF/POST, contexto de conta, confirmação, bloqueio de sincronização, preservação de marcadores, restauração, falhas antes/depois da escrita, resposta sem alteração efetiva, reconexão concorrente e espera por quota. Nenhuma conta real foi acessada pelos testes.

- [Modificar uma mensagem Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/modify)
- [Lixeira Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/trash)
- [Exclusão definitiva Gmail e escopo exigido](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/delete)
- [Mover Zoho](https://www.zoho.com/mail/help/api/move-email.html)
- [Marcar lida Zoho](https://www.zoho.com/mail/help/api/put-mark-email-as-read.html)
- [Marcar não lida Zoho](https://www.zoho.com/mail/help/api/put-mark-email-as-unread.html)
- [Lixeira Zoho e parâmetro expunge](https://www.zoho.com/mail/help/api/delete-email.html)
