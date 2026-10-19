# Outlook pessoal na Central — ambiente local

Outlook adiado pelo proprietário em 18/10/2026. Este guia fica para uma retomada futura; nenhum cadastro adicional Microsoft é necessário agora.

A Central usa a conta moisescavalcante@outlook.com.br. As mensagens continuam no Outlook; a Central organiza, lê e envia pela API oficial Microsoft Graph. A Central de E-mail permanece na aba do Core, com seu menu recolhido apenas nesta área. Abre em Todos os e-mails, oferece seletor de caixa, busca contextual e lista compacta. Contas e pastas ficam em sanfona; configurações no rodapé e leitura na mesma página. A sessão e o controle de proprietário continuam sendo os do Core.

## 1. Registrar o aplicativo Microsoft

1. Abra https://entra.microsoft.com/ e entre na conta que administra seu diretório Microsoft Entra. O registro exige acesso a um diretório e permissão para registrar aplicativos; uma caixa Outlook pessoal, sozinha, pode não oferecer esse acesso. Se aparecer erro de diretório, assinatura ou permissão, informe apenas a mensagem do erro. Não contrate serviços nem altere seu domínio para contornar isso.
2. Entre em **Entra ID > Registros de aplicativo > Novo registro**.
3. Nome: **Central de Contas — MeuProjetoWeb local**.
4. Tipos de conta: **Somente contas Microsoft pessoais**. Se essa opção não estiver disponível, pode usar **Contas em qualquer diretório organizacional e contas Microsoft pessoais**. Não use a opção restrita a um único diretório. A integração local usa o endpoint consumers e aceita somente a caixa pessoal configurada.
5. Em URI de redirecionamento, selecione a plataforma **Web** e informe exatamente:

```
https://localhost:8443/web/admin/central_contas/oauth_outlook_callback.php
```

6. Clique em Registrar. Na Visão geral, identifique **ID do aplicativo (cliente)**. O ID do diretório não é o ID do aplicativo.

## 2. Permissões e segredo

1. Em **Permissões de API > Adicionar uma permissão > Microsoft Graph > Permissões delegadas**, deixe apenas **User.Read**, **Mail.Read**, **Mail.Send** e **offline_access**. User.Read identifica a caixa, Mail.Read permite leitura e anexos, Mail.Send permite envio, offline_access permite renovação. Não adicione permissões de aplicativo nem Mail.ReadWrite. offline_access também é solicitado no consentimento pela integração.
2. Em **Certificados e segredos > Segredos do cliente > Novo segredo do cliente**, escolha uma validade e anote a data de expiração no seu gerenciador externo.
3. Copie o campo **Valor** do segredo, disponível apenas após criá-lo. O **ID do segredo** não serve como valor.
4. Não habilite fluxos de cliente público nem concessão implícita. A plataforma é Web e a Central usa código de autorização com PKCE.

## 3. Guardar localmente

Crie o arquivo abaixo, fora da pasta pública do Core:

```
C:\Users\Moises Cavalcante\Meus Sites\private-mail-center\outlook-client.json
```

Conteúdo (substitua os textos pelos valores do aplicativo):

```json
{
  "client_id": "ID_DO_APLICATIVO_CLIENTE",
  "client_secret": "VALOR_DO_SEGREDO"
}
```

Não cole o segredo no chat. Quando salvar, informe somente que o arquivo outlook-client.json está pronto. O provisionador local valida o JSON, faz uma simulação, preserva Gmail/Zoho/chaves/anexos, cria backup privado e conserva UID/GID e modo 0600. A aplicação depende desse arquivo; nenhuma senha do Outlook é usada ou guardada.

## 4. Conectar e conferir

Após o provisionamento e a remontagem do arquivo privado no Docker:

1. Abra https://localhost:8443/web/admin/central_contas/accounts.php.
2. Na caixa Outlook, escolha **Autorizar no Microsoft** e use **moisescavalcante@outlook.com.br**. Confira as permissões no consentimento.
3. Abra a caixa Outlook e use **Atualizar próximo lote**. A janela inicial cobre 30 dias, com lotes de até 10 mensagens.
4. Confira leitura e anexos. O teste real de envio deve ser revisado e confirmado na Central pelo proprietário; não há envio automático de teste.

O cadastro Microsoft e o consentimento real continuam pendentes até a execução desses passos. Gmail/Zoho não precisam ser reconectados.

## Comportamento e limites

- Recebimento/sincronização manual por consulta; não há atualização instantânea nem serviço em segundo plano. Durante um ciclo longo, o lote seguinte continua a paginação antes de reiniciar pelas mensagens recentes.
- Visão unificada usa somente mensagens já sincronizadas de contas ativas e conectadas; não agrega caixas de outras pessoas.
- IDs imutáveis do Graph e paginação opaca preservada; links de paginação precisam apontar exatamente para a mesma coleção /me no host oficial. Tokens nunca vão para destinos indicados livremente pelo usuário.
- Busca remota usa a sintaxe de busca do Outlook; a busca unificada é local. Pastas listadas pelo seletor são as pastas principais; mensagens de subpastas podem aparecer em Todos.
- Leitura não marca como lido no provedor. Exclusão, movimentação, arquivamento e alteração de regras não estão habilitados. Polling não reflete imediatamente todas as remoções ou mudanças de pasta.
- Envio usa MIME com remetente fixo, cópia, cópia oculta e anexos dentro dos limites locais. Respostas seguem Reply-To e referências da mensagem original.
- Microsoft Graph aceita envio com 202 e sem ID. A Central mostra aceito; só confirma o registro nos enviados ao encontrar uma única cópia com o Message-ID exato. Isso não comprova entrega ao destinatário. Se o provedor reescrever esse identificador ou demorar a indexar, pode continuar aceito mesmo com entrega concluída. Não há reenvio automático.
- Desconectar apaga os tokens locais e bloqueia o uso na Central. A Microsoft não oferece revogação individual de refresh token neste fluxo; para retirar o consentimento, use a área de aplicativos e permissões da sua conta Microsoft. Nenhuma revogação global de sessões é executada.
- Arquivos e conteúdo grandes ou tipos de anexo não suportados devem ser abertos no Outlook. Segredos expiram e precisam ser renovados. Não foi feito deploy; produção exigirá configuração própria de domínio HTTPS, callback, segredos e validação.

Referências oficiais: [registro de aplicativo](https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app), [OAuth com PKCE](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow), [mensagens](https://learn.microsoft.com/en-us/graph/api/user-list-messages?view=graph-rest-1.0), [envio MIME e resposta 202](https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0), [IDs imutáveis](https://learn.microsoft.com/en-us/graph/outlook-immutable-id).
