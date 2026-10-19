# Ações de mensagens — preparação local

Implementadas para Gmail e Zoho: marcar lida/não lida, mover para pasta da mesma conta, enviar para lixeira com confirmação e restaurar escolhendo um destino em Mover. Controles ficam na mensagem aberta, inclusive no painel de Todos os e-mails. Não há exclusão permanente nem ação automática ao abrir uma mensagem. Outlook e operações de criação/renomeação/exclusão de pastas continuam fora desta etapa.

## Ativação local e consentimentos pendentes

O código usa `mail_center.remote_organization_enabled`, ausente/desabilitado por padrão. Em produção nada foi alterado nesta etapa. Com a opção desabilitada, os controles ficam ocultos, a rota retorna 403 e a autorização continua solicitando somente os escopos anteriores. Ativar por configuração privada apenas depois de revisar/publicar o conjunto completo de arquivos e informações de privacidade, com backup e verificação de saúde.

Em 19/10/2026 a opção foi habilitada somente no ENV ignorado do Docker HTTPS local. O procedimento confere a URL local, compara o hash do ENV do container com o workspace e salva uma cópia anterior na pasta privada externa, em `local-config-backups`. Sintaxe e carregamento aprovados; Gmail e Zoho prontos, com os novos escopos presentes na próxima autorização, mas ainda não concedidos às conexões no momento da conferência. Nenhuma chamada aos provedores ou alteração nas mensagens foi executada nessa ativação. Produção permanece sem as ações de organização.

Ferramenta: `scripts/central-contas-organizacao-local.ps1 -Mode Enable`; para desabilitar apenas a opção, usar `-Mode Disable`. Não restaura automaticamente o ENV inteiro sobre eventuais alterações posteriores. O consentimento é feito pelo proprietário no navegador; a ativação de código não o substitui.

Após o proprietário concluir as duas reconexões, a conferência local confirmou Gmail e Zoho conectados e autorizados para organização. Essa conferência leu somente estados e permissões locais, sem chamadas aos provedores ou alterações nas mensagens. Gmail tinha um destino disponível no cache e Zoho sete. Posteriormente, o proprietário relatou que testou localmente e funcionou, inclusive o envio para a Lixeira. O agente não executou alterações reais nas mensagens: o assunto indicado não foi localizado na consulta recente das duas contas.

Decisão posterior de 19/10/2026: manter apenas envio para Lixeira e restauração pela Central, deixando a limpeza definitiva para o provedor. Foram retirados a operação de exclusão definitiva, os botões correspondentes e a opção local de pedir acesso completo ao Gmail. Não é necessário conceder `https://mail.google.com/`. Dentro da Lixeira não aparece **Enviar para lixeira**; permanecem lida/não lida e **Mover**, inclusive para restaurar à Caixa de entrada. Não há tarefa de exclusão automática executada pelo Core.

O destaque de leitura foi mantido: não lidas têm ponto colorido, remetente/assunto em negrito e fundo discreto; lidas usam texto normal. A identificação acessível informa Lida/Não lida e o destaque de sinalização preserva a cor configurada.

Retenção no provedor: Gmail limpa a lixeira após 30 dias. Zoho permite ajustar **Configurações → Sistema → Intervalo de limpeza de lixo**; sua documentação descreve o padrão de 30 dias com base na data de recebimento da mensagem, não garante 30 dias desde a ida para a lixeira. Essas configurações não foram alteradas pelo agente. A Central continua com atualização manual em lotes. A reconciliação local da Lixeira do Zoho foi acrescentada posteriormente: verifica metadados, procura mensagens restauradas nas outras pastas e só oculta ausências confirmadas em todas elas. O processo é limitado e retomável, sem exclusão remota nem promessa de espelhamento automático integral. Ver [reconciliação da Lixeira](central-de-email-reconciliacao-lixeira.md).

Fontes: [Gmail](https://support.google.com/mail/answer/7401), [Zoho](https://www.zoho.com/mail/help/deleting-mail.html).

Validação do modo somente Lixeira: 482 verificações na suíte piloto e oito cenários de navegador, com provedores/páginas simulados. Exclusão definitiva rejeitada antes de qualquer chamada de escrita; restauração continua disponível. Nenhuma mensagem real foi alterada e nenhuma configuração de retenção do provedor foi aplicada.

Quando habilitado, contas conectadas sem os novos escopos exibem em Configurações **Autorizar organização de mensagens**. Leitura e envio existentes continuam disponíveis; os botões de organização só aparecem após conferir as permissões efetivamente registradas na conexão. A renovação de consentimento não copia tokens entre ambientes.

1. Google Cloud → Google Auth Platform → Acesso a dados: acrescentar `https://www.googleapis.com/auth/gmail.modify`, mantendo os escopos já usados. Revisar consentimento e condição de verificação para uso pessoal. Não solicitar `https://mail.google.com/`.
2. Com o código local habilitado para a validação, entrar na Central → Configurações → Gmail → **Autorizar organização de mensagens** e consentir no site do Google.
3. Repetir no Zoho. O OAuth solicitará `ZohoMail.messages.UPDATE` e `ZohoMail.messages.DELETE`, além das permissões atuais. DELETE é necessário para a operação de lixeira documentada; a implementação sempre envia `expunge=false` e não oferece exclusão permanente.
4. Validar com uma mensagem e uma pasta de teste da mesma conta: lida/não lida, mover, lixeira e restauração. Conferir cada resultado no webmail. Não usar mensagens importantes nessa validação. O teste real ainda depende de autorização do proprietário para os itens escolhidos.
5. Publicar e repetir a renovação em produção numa etapa própria. Os tokens locais não serão reaproveitados.

## Comportamento e limites

- Gmail: mover remove a origem selecionada quando ela é Caixa de entrada, Spam ou marcador próprio, e adiciona o destino; preserva outros marcadores. Em Todos os e-mails remove Caixa de entrada ou Spam, mantendo os demais marcadores. Pastas de sistema não são destinos, exceto Caixa de entrada. Restauração usa untrash antes de aplicar o destino.
- Zoho: mover usa PUT updatemessage; marcar usa markAsRead/markAsUnread. Lixeira usa DELETE com expunge=false e recusa uma mensagem já na lixeira. A pasta Lixeira precisa ser identificada de forma inequívoca pelo tipo informado pelo Zoho.
- A lista local só muda após confirmação e nova consulta de metadados no provedor. Em falha depois da tentativa de escrita, mostra resultado potencialmente incerto, sem repetir automaticamente. Conferir o webmail e atualizar a caixa antes de repetir. Uma operação de múltiplas chamadas, como restaurar e mover no Gmail, pode ficar parcialmente aplicada.
- As operações compartilham o bloqueio por caixa da sincronização, validam proprietário/conta/pasta e geração da conexão. A rota exige POST e CSRF. Auditoria registra somente ação e resultado, sem conteúdo, endereços ou tokens. Nenhuma migração nova é necessária.
- Favoritos, sinalizadores e fixação permanecem locais e são preservados. Busca e filtros continuam respeitando o contexto selecionado.

## Referências oficiais

Validação local concluída: 460 verificações na suíte piloto, incluindo 135 de organização remota, com banco descartável e provedores simulados. Interface: seis cenários de ações em desktop/celular e 12 combinações de leitura existentes aprovados; capturas sintéticas revisadas sem overflow. Cobertura inclui permissões antigas, opção desabilitada, CSRF/POST, contexto de conta, confirmação, bloqueio de sincronização, preservação de marcadores, restauração, falhas antes/depois da escrita, resposta sem alteração efetiva, reconexão concorrente e espera por quota. Nenhuma conta real foi acessada pelos testes.

- [Modificar uma mensagem Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/modify)
- [Lixeira Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/trash)
- [Mover Zoho](https://www.zoho.com/mail/help/api/move-email.html)
- [Marcar lida Zoho](https://www.zoho.com/mail/help/api/put-mark-email-as-read.html)
- [Marcar não lida Zoho](https://www.zoho.com/mail/help/api/put-mark-email-as-unread.html)
- [Lixeira Zoho e parâmetro expunge](https://www.zoho.com/mail/help/api/delete-email.html)
