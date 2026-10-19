# Gmail piloto — fluxo principal validado localmente

Esta etapa implementa apenas o conector Gmail. Outlook e Zoho permanecem cadastráveis, sem conexão. A caixa piloto foi escolhida pelo proprietário; nenhuma senha de Gmail é necessária.

O código foi testado com provedores simulados e o fluxo principal foi validado com contas reais pelo proprietário em 18/10/2026: conexão Gmail, sincronização/leitura, envio controlado ao Outlook e leitura da resposta de retorno no Gmail pela Central. O teste real não comprova todas as funcionalidades; anexos, resposta enviada pelo próprio Core e ciclos de desconexão/reautorização ainda não foram confirmados em conta real. Os demais provedores dependem de aprovação da próxima fase.

## 1. HTTPS local preparado

O Docker local atende em `https://localhost:8443/web/login.php`. A porta HTTPS é vinculada apenas a `127.0.0.1`; HTTP em localhost:8000 redireciona para HTTPS. O proprietário aprovou preparar esse ambiente e confiar no certificado somente no seu usuário do Windows. O certificado foi instalado em CurrentUser/Root, não no repositório do computador. A URL canônica do ENV local foi atualizada e o cookie de sessão foi conferido com Secure.

Não foi criado proxy, túnel, DNS ou container adicional e não foi publicado código em produção. O domínio de produção não deve ser usado como callback até o ambiente que atende o endpoint estar preparado e sua implantação ter aprovação específica.

O callback implementado é:

`https://localhost:8443/web/admin/central_contas/oauth_callback.php`

Cadastre esse valor exato no Google, incluindo porta 8443. Abra o Core pelo nome localhost para manter a mesma origem do callback. Não usar curingas, fragmentos ou URLs montadas a partir de cabeçalhos recebidos.

Certificado local: validade de 18/10/2026 a 18/10/2027; SAN localhost, 127.0.0.1 e ::1; SHA-256 `81:76:1B:D6:6C:A8:3F:97:9B:3A:28:D8:68:0D:8A:81:53:C9:87:58:8B:20:93:C4:09:98:11:3A:4D:FB:F4:1C`. A chave fica fora do workspace, em `C:\Users\Moises Cavalcante\Meus Sites\private-mail-center\tls`, com ACL restrita ao usuário e SYSTEM. Não distribuir a chave nem reutilizar esse certificado em produção. A renovação exige novo certificado e nova conferência de confiança.

Para iniciar novamente o app com a configuração HTTPS, use `powershell -File scripts/central-contas-local-https.ps1`; acrescente `-Build` se o Dockerfile mudar. O banco deve estar iniciado. O script usa os dois arquivos Compose e preserva a configuração de TLS; recriar o app apenas pelo Compose base remove os mounts e a porta HTTPS. O script não instala certificados nem gera chaves.

## 2. Criar o aplicativo no Google Cloud

1. Entre no [Google Cloud Console](https://console.cloud.google.com/) com a conta que administrará o aplicativo. Crie um projeto dedicado à Central de Contas de teste; mantenha desenvolvimento e produção separados.
2. Na [biblioteca de APIs](https://console.cloud.google.com/apis/library/gmail.googleapis.com), selecione esse projeto e habilite **Gmail API**.
3. Abra **Google Auth Platform**. Configure o nome do aplicativo, o contato de suporte e o contato do desenvolvedor. Para Gmail pessoal, use público **External**; não escolha Internal, que depende de organização Workspace.
4. Mantenha inicialmente o aplicativo em **Testing** e adicione `moises.cavalcante.br@gmail.com` como usuário de teste. Esse usuário deve ser o mesmo endereço cadastrado na Central e no arquivo privado.
5. Em acesso a dados, declare apenas estes escopos: `https://www.googleapis.com/auth/gmail.readonly` e `https://www.googleapis.com/auth/gmail.send`. O piloto não arquiva, exclui nem marca mensagens como lidas remotamente.
6. Crie um cliente OAuth de tipo **Web application / Aplicativo da Web**. Em **Authorized redirect URIs**, cadastre o callback HTTPS exato definido no item 1. Esta é uma integração no servidor; não usa client secret no navegador nem um cliente Desktop.
7. Baixe o JSON do cliente Web e guarde-o em pasta privada fora do repositório/workspace e da raiz web. Não cole o conteúdo no chat, em issues, commits ou logs.

Os nomes de telas podem variar. As regras e os parâmetros são descritos na [documentação oficial do fluxo Web](https://developers.google.com/identity/protocols/oauth2/web-server).

Leitura usa escopo restrito e envio usa escopo sensível. A aplicação de uso pessoal pode se enquadrar em exceção de verificação, mas o enquadramento e os avisos precisam ser conferidos no Console; este documento não garante dispensa de avaliação. Em modo externo Testing, refresh tokens normalmente expiram em sete dias para esses escopos. A Central terá de pedir reconexão quando isso ocorrer. Fontes: [escopos Gmail](https://developers.google.com/workspace/gmail/api/auth/scopes), [verificação e exceções](https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification), [validade dos tokens](https://developers.google.com/identity/protocols/oauth2).

## 3. Provisionar segredos e anexos privados

O arquivo `env/mail-center.secrets.example.json` é apenas exemplo público bloqueado pelo Apache; não substituir seus marcadores por segredos no repositório. O arquivo real precisa existir fora do DocumentRoot e fora do código versionado.

Há um utilitário CLI em `app/console/mail_center_setup.php` para validar o JSON Web, gerar chave aleatória e gravar `secrets.json` sem exibir nenhum segredo. Ele não sobrescreve arquivo/chave existente. Os caminhos abaixo são marcadores de um ambiente Linux/container preparado:

```sh
php app/console/mail_center_setup.php \
  --google-json=/CAMINHO_PRIVADO/client-web.json \
  --private-dir=/PASTA_PRIVADA_EXISTENTE \
  --callback=https://localhost:8443/web/admin/central_contas/oauth_callback.php \
  --pilot-email=moises.cavalcante.br@gmail.com
```

Primeiro roda em simulação. Acrescentar `--apply` somente depois de conferir os caminhos. A pasta de anexos é criada dentro da pasta privada. Ela deve ser persistente e acessível apenas ao usuário da aplicação. O arquivo de segredos deve ter leitura para esse usuário e ser montado somente para leitura na aplicação; não tornar o arquivo legível por todos para contornar permissões.

`docker/docker-compose.mail-center.local.yml` já monta TLS e anexos. Quando o JSON Google estiver disponível, será necessário gerar o arquivo privado e conferir permissões. Depois disso, `powershell -File scripts/central-contas-local-https.ps1 -WithGoogleSecrets` acrescenta o mount somente leitura de `secrets.json`, usando `docker/docker-compose.mail-center.example.yml`. O arquivo privado local já foi provisionado e montado somente para leitura. Se os caminhos vistos dentro do container diferirem daqueles usados na geração, ajustar `attachments_dir` no arquivo privado para `/var/lib/mail-center/attachments`. Não editar o exemplo versionado com valores reais. Ao reiniciar o piloto já conectado, manter `-WithGoogleSecrets`.

No ENV privado do ambiente, configurar `mail_center.secrets_file` com `/run/mail-center/secrets.json`, manter o ID do proprietário correto para aquele banco e usar `app_url` HTTPS canônico. `app.session_secure` não pode ser desabilitado ao operar caixas reais. Usar `trusted_proxy_ips` apenas quando o HTTPS terminar em proxy conhecido, com IPs exatos e proxy configurado para sobrescrever o cabeçalho recebido do cliente.

Em 18/10/2026, o cliente Web fornecido pelo proprietário foi validado e a configuração privada local foi provisionada. O arquivo secrets.json e a chave de dados ficam fora do workspace, com ACL do usuário/SYSTEM, e o arquivo é montado somente para leitura. O caminho interno de anexos foi conferido para www-data. O callback foi novamente testado sem parâmetros nos logs e a flag de revisão foi habilitada somente no ENV local. Nenhum consentimento, token ou acesso à caixa real foi realizado nesse provisionamento. Perder a chave impede recuperar tokens, rascunhos e anexos criptografados; mantenha backup protegido separado do banco. Não copiar os tokens/keys de produção para o ambiente local.

## 4. Revisar logs e limites antes de autorizar

O Dockerfile inclui configuração para o callback aparecer nos logs do Apache sem a query string, que contém o código OAuth e state. Essa regra também foi preparada para o container local. É obrigatório conferir separadamente logs de proxy/CDN/WAF, erros e ferramentas de observabilidade: a regra do Apache não protege esses outros pontos.

Somente depois da conferência marcar `mail_center.oauth_log_redaction_verified=true` no ENV privado. O padrão é false e bloqueia a conexão. O callback redireciona imediatamente para uma URL sem o código, sem renderizar layout ou carregar recursos externos, e usa no-store/referrer-policy.

Uploads: até 2 MB por arquivo, 6 MB totais no rascunho e até três arquivos por submissão. Tipos de saída: PDF, JPG/JPEG, PNG, WebP, TXT e CSV, com extensão/conteúdo compatíveis. Arquivos ficam criptografados fora da raiz web. Downloads recebidos usam attachment/nosniff, com limite de 2 MB no piloto; arquivos maiores são abertos no provedor. MIME não equivale a antivírus.

Operações reais exigem HTTPS mesmo quando os dados já estão no cache local. A auditoria guarda ações e estados, sem corpo, destinatários ou tokens. Busca é enviada por POST para não incluir os termos nos logs de URL.

## 5. Instalação e testes de aceite

Na implantação futura, instalar as dependências a partir de `composer.lock`, sem plugins/scripts, e aplicar explicitamente:

```sh
php app/console/migrate_mail_center.php --pilot
php app/console/migrate_mail_center.php --pilot --apply
```

A migração não conecta contas nem dispara sincronização/envios. O instalador/deploy antigo ainda não empacota automaticamente o vendor e estes arquivos Composer; não publicar este código pelo pipeline antigo sem adequar e validar o pacote na fase de produção.

Com infraestrutura e consentimento preparados:

1. Entrar novamente no Core como proprietário e cadastrar/conferir a caixa Gmail piloto.
2. Escolher **Autorizar no Google**, conferir o endereço e conceder apenas as duas permissões do piloto. Contas diferentes são recusadas.
3. Atualizar lotes até completar a carga inicial de 30 dias. Depois, as atualizações usam o histórico incremental. Não há cron instalado nem recebimento instantâneo.
4. Ler uma mensagem conhecida, pesquisar no cache e no Gmail, abrir HTML protegido e baixar um anexo de teste. A leitura não marca a mensagem como lida no Gmail.
5. Definir com o proprietário um destinatário controlado, salvar rascunho, revisar conta/remetente/destinatários/anexos e confirmar um único envio. Conferir recebimento no destinatário e cópia nos enviados. A aceitação pelo Gmail não comprova entrega ao destinatário.
6. Responder a uma mensagem de teste e conferir conversa e conta de origem. Mensagem própria não é respondida automaticamente a si mesma.
7. Conferir duplo clique, token expirado, desconexão e reconexão. Se houver timeout com resultado incerto, usar **Conferir no Gmail**; não criar um segundo envio antes da conferência. A Central não faz retries automáticos de envio.

Não há envio real automático ou teste para destinatário externo escolhido pelo agente. A confirmação de envio usa o conteúdo salvo exibido na revisão; editar o formulário exige salvar novamente.

## 6. Manutenção, limites e reversão

Não há worker, cron ou agendamento instalado. Atualização pelo painel é manual. A CLI de sincronização requer `--allow-provider-access` e ID explícito da conta. Não executá-la enquanto o piloto real não estiver aprovado/configurado.

A manutenção CLI `app/console/mail_center_maintenance.php` simula por padrão e aplica com `--apply`. Ela marca envios interrompidos como incertos, expurga auditoria/metadados antigos e snapshots concluídos, e remove temporários vencidos. Não envia e-mails nem exclui mensagens no Gmail. Anexos de envios incertos/pendentes são preservados para não descartar dados silenciosamente. Sem cron, o expurgo depende de execução manual.

Rascunhos não são apagados automaticamente. Nesta entrega não há exclusão de conta ou migração de caixa. A desconexão bloqueia uso local e tenta revogar no Google; eventual falha exige retirar o consentimento na Conta Google. Desconectar não desfaz uma mensagem já aceita pelo provedor nem garante cancelar uma requisição de envio que já estava em andamento.

Reversão: desabilitar a Central/recursos do piloto e preservar as tabelas; retirar o consentimento no Google se necessário. Não remover chaves antes de decidir sobre rascunhos/anexos. DDL MySQL pode ter commit implícito: não usar DROP automático como rollback. Migrações aplicadas têm checksum e não devem ser editadas.

Aceite principal da Etapa 4 confirmado pelo proprietário: sincronização/leitura, envio Gmail para Outlook e recebimento/leitura da resposta na Central. O registro local de envio apontou status confirmed e uma tentativa, sem falha. Anexos reais, resposta enviada pelo próprio Core, busca/paginação real e desconexão/reautorização real não foram confirmados pelo proprietário; a cobertura atual desses caminhos é sintética. Esses limites permanecem registrados para a validação ampliada. A próxima fase e a publicação em produção exigem autorização específica; Outlook e Zoho ainda não estão conectados.
