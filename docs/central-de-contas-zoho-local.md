# Zoho no Core — Etapa 5 local

O proprietário autorizou iniciar a Etapa 5 pelo Zoho, preservando o Outlook pessoal para depois. Caixa escolhida: `moises.cavalcante@meuprojetoweb.com`; webmail informado: `https://mail.zoho.com/zm/#mail/folder/inbox`. A implementação usa a região US (`accounts.zoho.com` e `mail.zoho.com`), sem deduzir a região pelo endereço do domínio personalizado. Não houve implantação em produção.

## Cliente de autorização no Zoho

Este cliente é o registro da integração no provedor. A Central continua dentro do Core, com o mesmo login e banco; não criar outro projeto na seção Projetos do Core.

1. Abra [Zoho API Console](https://api-console.zoho.com/) com a conta administradora da integração na mesma região do webmail informado.
2. Selecione **Add Client / Adicionar cliente** e **Server-based Applications**.
3. Nome: `Central de Contas — Local`. Homepage URL: `https://localhost:8443`.
4. Authorized Redirect URI, exatamente: `https://localhost:8443/web/admin/central_contas/oauth_zoho_callback.php`.
5. Crie o cliente. Salve o Client ID e o Client Secret em um arquivo privado fora do workspace, como `C:\Users\Moises Cavalcante\Meus Sites\private-mail-center\zoho-client.json`. Não colar segredo, código OAuth, tokens ou URL completa do callback no chat. Não usar Self Client nem gerar token manual para contornar o fluxo.

Formato do arquivo privado (substituir os marcadores somente no arquivo externo):

```json
{
  "client_id": "SEU_CLIENT_ID",
  "client_secret": "SEU_CLIENT_SECRET"
}
```

O exemplo público `env/mail-center.zoho-client.example.json` serve apenas de molde; não inserir credenciais reais nele.

O conector pede `ZohoMail.accounts.READ`, `ZohoMail.folders.READ`, `ZohoMail.messages.READ` e `ZohoMail.messages.CREATE`. A API deve estar permitida pelo plano e pelas políticas da conta; isso ainda será confirmado no consentimento e no teste real. Não contratar plano ou ampliar privilégios automaticamente para resolver bloqueio.

Fontes oficiais: [OAuth Zoho Mail](https://www.zoho.com/mail/help/api/using-oauth-2.html), [Server-based e PKCE](https://www.zoho.com/developer/oauth/web-server-apps/overview.html), [permissão de contas](https://www.zoho.com/mail/help/api/get-all-users-accounts.html), [pastas](https://www.zoho.com/mail/help/api/get-all-folder-details.html).

## Provisionamento privado

Após salvar o cliente, informar apenas o nome do arquivo ao responsável técnico. O utilitário `app/console/mail_center_zoho_setup.php` valida o arquivo, simula por padrão e acrescenta a seção Zoho ao `secrets.json` existente com `--apply`. Ele preserva os dados Google, as chaves e o diretório de anexos, cria backup privado externo e recusa substituir uma configuração Zoho já existente.

Executar no ambiente de provisionamento sem rede, com a pasta privada externa montada para escrita e o código para leitura. Exemplo de caminhos internos:

```sh
php app/console/mail_center_zoho_setup.php \
  --client-json=/run/mail-setup/zoho-client.json \
  --private-dir=/run/mail-setup \
  --region=us \
  --email=moises.cavalcante@meuprojetoweb.com \
  --callback=https://localhost:8443/web/admin/central_contas/oauth_zoho_callback.php
```

Revisar a simulação antes de acrescentar `--apply`. Depois recriar somente o app com `powershell -File scripts/central-contas-local-https.ps1 -WithGoogleSecrets`; esse nome de opção preexistente monta o arquivo privado compartilhado, incluindo Zoho. O segredo permanece somente leitura no app. Não trocar/gerar novamente as chaves do Gmail nem copiar os tokens para outro ambiente.

O callback Zoho também exige remoção dos parâmetros nos logs. A regra Apache versionada cobre os dois callbacks. Conferir a resposta HTTP real e os logs antes de oferecer a autorização. Dados `accounts-server`, `location` e `api_domain` recebidos do provedor não selecionam livremente os destinos de rede: os servidores são fixados pela região privada.

## Uso e diferenças de capacidade

- Contas e categorias: **Autorizar no Zoho**, autorizar a conta que contém o endereço configurado e conferir as permissões. O proprietário escolheu moises.cavalcante@meuprojetoweb.com para identificar a conexão e enviar pelo Core. A API precisa confirmar esse endereço como principal ou como membro confirmado da mesma caixa com remetente ativo. Outros aliases não são habilitados automaticamente para envio.
- Caixas de e-mail: selecionar Gmail ou Zoho e atualizar lotes manualmente. Gmail mantém seu histórico incremental. Zoho consulta novamente a janela recente de 30 dias com paginação; não há cron, push ou histórico Gmail adaptado artificialmente. Movimentos e exclusões podem permanecer no cache; uma leitura pode informar que a mensagem está indisponível. A consulta recente atualiza mensagens reencontradas, mas não equivale a um histórico de exclusões. Não prometer espelho instantâneo do provedor.
- Pesquisa direta usa a sintaxe do provedor escolhido. Pesquisa unificada é local, em metadados já sincronizados, por assunto/remetente/endereço da conta.
- Caixa unificada mostra Gmail/Zoho com o endereço de origem em cada linha; abrir ou responder resolve a conta pelo recurso autenticado. Nova composição começa pela escolha de uma caixa. Mensagens com IDs remotos iguais em caixas diferentes permanecem distintas.
- Leitura HTML passa pelo sanitizador e iframe protegido existentes. Não são carregadas imagens externas. Downloads limitados a 2 MB, com attachment/nosniff; anexos enviados mantêm os limites e criptografia existentes. MIME e extensão não equivalem a antivírus.
- Zoho usa envio JSON e upload prévio de anexo, não aceita o MIME Gmail por conversão implícita. Resposta usa o endpoint próprio do Zoho com a mensagem e conta originais. Upload pode deixar temporário no provedor se o envio não for concluído.
- Confirmação, snapshot criptografado e idempotência são mantidos. Se o Zoho aceitar e devolver ID, a consulta à pasta Enviados pode confirmar o registro. Timeout sem ID continua **incerto**, sem busca heurística ou reenvio automático; o proprietário deve conferir o webmail antes de criar outra mensagem.
- Arquivar, excluir, marcar lida/não lida e modificar labels remotamente continuam desabilitados nesta primeira conexão. Esses recursos requerem ampliar permissões e validar a capacidade separadamente durante a Etapa 5. A Central não solicita alteração remota de leitura.

Referências: [listagem](https://www.zoho.com/mail/help/api/get-emails-list.html), [busca](https://www.zoho.com/mail/help/api/get-search-emails.html), [leitura](https://www.zoho.com/mail/help/api/get-email-content.html), [envio](https://www.zoho.com/mail/help/api/post-send-an-email.html), [resposta](https://www.zoho.com/mail/help/api/post-reply-to-an-email.html), [anexos](https://www.zoho.com/mail/help/api/post-upload-attachments.html).

## Aceite e limites atuais

Executar testes reais com o proprietário: conectar a caixa correta, atualizar e ler mensagem conhecida, pesquisar, baixar anexo controlado, preparar/revisar um único envio para destinatário acordado, confirmar recebimento/resposta e verificar conta de origem, anexos e conversa. Testar desconexão/reconexão em momento acordado. Não enviar mensagem de teste sem revisão e autorização.

Até concluir esse aceite, o Zoho está implementado e coberto por simulações; não declarar a conta conectada nem o provedor funcionando em produção. Não há novas tabelas, bibliotecas, workers ou serviços. A caixa unificada reutiliza as tabelas da Central e não migra e-mails.

Reversão: retirar a configuração Zoho privada após desconectar/revogar o consentimento, preservando Google, chaves, tabelas e rascunhos. Não remover chaves nem executar DROP. Outlook, tarefas e produção continuam dependentes de suas aprovações e validações próprias.

## Validação local realizada

Em 18/10/2026: 125 verificações do piloto (40 específicas do Zoho), 122 da fundação, PHP lint de 56 arquivos e 72 combinações de tela/tema/largura aprovadas. As simulações incluem URL OAuth real gerada pela biblioteca, PKCE/state/provedor, escopos separados por vírgulas, validação de identidade, renovação/revogação, polling, HTML, Reply-To, anexos, composição/revisão, envio/resposta, duplicidade, timeout incerto, contexto por conta, caixa unificada e preservação de Google/chaves no provisionamento.

Rotas reais de visão geral, contas, caixa Gmail, caixa unificada e dashboard responderam 200 em HTTPS com sessão temporária. Acesso anônimo à caixa unificada/callback Zoho foi recusado e o callback foi registrado sem query string. O envelope do token Gmail permaneceu igual durante a conferência, e a sessão temporária foi removida. Não houve chamada ao Gmail/Zoho por esse teste local.

O cadastro organizacional Zoho e a categoria MeuProjetoWeb foram preparados no banco local; nenhuma tabela ou dependência foi acrescentada. O cliente Zoho privado foi fornecido pelo proprietário e provisionado em 18/10/2026. O botão Autorizar no Zoho está disponível; o consentimento e o aceite real continuam pendentes. A Etapa 5 segue em andamento, incluindo as capacidades adicionais previstas nessa fase.
## Atualização de provisionamento privado

Arquivo zoho-client.json validado sem exibir valores. Simulação e aplicação executadas em container temporário sem rede; dados Google, chaves, chave ativa e diretório de anexos preservados, com backup privado externo. O app foi reconstruído com a proteção de logs dos dois callbacks e o arquivo foi remontado somente para leitura.

Após a gravação atômica, foi necessário ajustar o proprietário Unix do arquivo privado para www-data (UID/GID 33), mantendo modo 0600 e ACL Windows restrita ao usuário/SYSTEM. O utilitário foi corrigido para preservar proprietário/grupo do arquivo anterior nas próximas atualizações, sem liberar leitura pública. Ao provisionar em outro ambiente, validar explicitamente a leitura pelo usuário do PHP antes de ativar a conexão.

Verificação após o ajuste: www-data carrega configurações Gmail/Zoho e diretório de anexos; página HTTPS de contas oferece Autorizar no Zoho sem credenciais no HTML; rota de conexão retorna 303 com região, callback, escopos e PKCE corretos, sem seguir o redirecionamento. Caixa unificada respondeu 200; callback anônimo foi recusado e registrado sem código/state no log. Estado local ao final: Gmail conectado, Zoho ainda não conectado. Nenhuma requisição ao Zoho, leitura de mensagem ou envio real foi executado nesse provisionamento.
### Ajuste do reconhecimento da identidade Zoho

Após o proprietário relatar rejeição da caixa autorizada, a validação foi ampliada para reconhecer primaryEmailAddress, campo canônico documentado pelo Zoho, além da lista emailAddress. Aceita indicadores booleanos explícitos serializados como true/false/1/0; valores desconhecidos permanecem recusados. Alias explícito, endereço não confirmado, caixa desativada, divergência de endereço principal e múltiplas caixas compatíveis continuam bloqueados. Não foi obtida nem registrada resposta bruta da conta real; a causa concreta da rejeição anterior ainda depende de uma nova autorização com esse reconhecimento atualizado.

135 verificações sintéticas aprovadas, incluindo 50 específicas do Zoho. Estado local após o ajuste: Gmail connected, Zoho not_connected. Nenhum novo token real foi salvo, nenhuma mensagem foi consultada/enviada e a configuração privada não mudou. O proprietário informou não saber se o endereço é alias; a nova tentativa deve confirmar a identidade pela própria API, sem dispensar a conferência de conta.

### Endereço central escolhido pelo proprietário

O proprietário esclareceu que moises.cavalcante@meuprojetoweb.com deve continuar como endereço da conexão no Core e apresentou a lista de e-mails de login do Zoho Accounts (no-reply, sistema e moises.cavalcante no mesmo domínio). A lista de login sozinha não comprova aliases ou encaminhamento no Zoho Mail; nenhuma mudança foi feita no login principal, DNS, encaminhamentos, SMTP transacional ou endereço cadastrado no Core.

A regra anterior de aceitar somente principal foi substituída, para o endereço explicitamente configurado, pela comprovação de identidade na mesma caixa: principal canônico válido ou associação confirmada em emailAddress mais remetente exato e ativo em sendMailDetails, sem aceitar SMTP externo. Endereço apenas de recuperação/login, membro não confirmado, remetente diferente/desativado e múltiplos accountIds compatíveis são recusados. Os demais endereços da caixa não viram remetentes automaticamente. O OAuth continua comparando o endereço escolhido com o cadastro e com a configuração privada, mantendo o remetente fixo.

Aliases confirmados da mesma caixa podem compartilhar a leitura por uma única conexão; caixas independentes não são fundidas por pertencer ao mesmo domínio. Responder para um endereço confirmado da própria caixa é bloqueado, inclusive quando é outro alias, para evitar retorno acidental a si mesmo.

146 verificações sintéticas do piloto aprovadas, incluindo 61 Zoho. O fluxo OAuth/envio/resposta foi exercitado com alias configurado diferente do endereço canônico do login, preservando o remetente escolhido. Arquivos alterados neste ajuste: ZohoMailProvider.php, MailSendService.php, mail_center_zoho_test.php e documentação. Sem alteração de credenciais, escopos, tabelas, produção ou envio real. Aceite da conta real ainda depende de nova autorização e confirmação da API.

Referências: https://www.zoho.com/mail/help/api/get-all-users-accounts.html e https://www.zoho.com/mail/help/adminconsole/user-settings.html#email-alias
### Correção da atualização real — 18/10/2026

Após conectar o Zoho, o proprietário confirmou envio ao Outlook com cópia para no-reply e resposta recebida no webmail. O Core concluía a sincronização com zero mensagens. Diagnóstico somente de metadados mostrou que a API real retornava zero com includearchive=true; sem esse filtro retornava mensagens recentes, com datas válidas em milissegundos. A consulta normal passou a usar includearchive=false, preservando includesent=true. Esta atualização cobre recebidos/enviados; mensagens arquivadas não são incluídas nesse ciclo. O comportamento real do filtro foi observado apesar da descrição genérica de inclusão na documentação do provedor.

Regressão reproduz a caixa de arquivados vazia com mensagens normais presentes. Resultado: 147 verificações aprovadas, sendo 62 Zoho. Um lote real autorizado trouxe 3 registros e completou o ciclo. Consulta local confirmou uma mensagem do endereço Outlook informado pelo proprietário entre os registros. Não foram exibidos assunto, conteúdo, tokens ou resposta bruta; não houve novo envio nem alteração em produção. Falta somente a confirmação visual do proprietário na Central após recarregar a página.
