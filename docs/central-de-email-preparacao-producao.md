# Central de E-mail — preparação de produção

Registro de 19/10/2026. Esta preparação não publica a Central nem conecta caixas no servidor.

## Ambiente confirmado

- Servidor confirmado pelo proprietário: `157.230.214.72`, workspace `/opt/workspace`.
- URL configurada: `https://meuprojetoweb.com`.
- PHP 8.3.35, MySQL 8.0.46, conexão utf8mb4 e extensões necessárias disponíveis.
- O usuário `moises.cavalcante@meuprojetoweb.com` existe como administrador ativo. O login Gmail usado no Docker local não existe nesse banco. Resolver o ID no servidor; não reutilizar o ID 5 local.
- A Central, suas dependências, tabelas e proteção de logs dos callbacks ainda não estavam instaladas em produção na inspeção inicial.
- Inspeção por SSH executada sem bootstrap, uploads, DDL ou alterações de configuração. A senha foi digitada diretamente no terminal, sem armazenamento.

## Preparado localmente

- `scripts/deploy-atualizar-servidor.ps1 -PackageOnly` gera `_deploy/workspace-update.zip` com Composer, vendor sem dependências de desenvolvimento e migrações 001–004.
- A preparação usa `composer.lock`, desabilita scripts/plugins e valida a plataforma. Não reaproveita cegamente o vendor do ambiente de uso. O instalador do Composer foi conferido com a assinatura SHA-384 oficial antes de executá-lo.
- A auditoria das 17 dependências fixadas não retornou avisos de vulnerabilidade nesta execução. Isso não substitui revisões futuras.
- `check_mail_release.php` verifica hashes dos arquivos Composer, versões/referências instaladas, ausência de dependências de desenvolvimento, extensões, migrações e callbacks. A validação do pacote recusa ENV e arquivos privados.
- O fluxo de publicação copia vendor/manifests, valida o runtime antes de substituir arquivos e aplica explicitamente as migrações da Central. O uso real exige `-ApplyMailCenterMigrations`; gerar o pacote não publica nem migra nada.
- O override privado de produção é carregado somente se já tiver sido provisionado no servidor. O exemplo `docker/docker-compose.mail-center.prod.example.yml` não é aplicado automaticamente.
- O fluxo geral de deploy também contém os passos preexistentes de Marketing/Meu Trabalho e execução do bootstrap do Core. A publicação é uma atualização do Core, não um envio isolado da Central; esses efeitos devem entrar na revisão final.

## Configuração privada

Foi preparado `private-mail-center/production/secrets.json`, fora do workspace, com ACL apenas do usuário Windows e SYSTEM. Ele contém os clientes Google/Zoho existentes, callbacks do domínio final e uma chave aleatória nova de produção. Nenhum token local foi copiado. O arquivo foi provisionado por SSH em `/opt/private-mail-center/secrets.json`, fora da raiz pública, com hash conferido e permissões 0600 para o usuário PHP.

O provisionamento também criou o diretório privado de anexos e resolveu o proprietário no banco de produção, gerando `/opt/private-mail-center/core-config.php` com módulo desabilitado. Esse trecho ainda não foi integrado ao ENV do Core; mounts não foram aplicados e containers não foram reiniciados. O script recusa sobrescrever segredos diferentes. Testes isolados verificaram integridade, permissões, idempotência e recusa de sobrescrita.

O modelo público, contendo somente placeholders, está em `docs/examples/mail-center-production-secrets.example.json`. O trecho de ENV está em `env/mail-center.production.example.php`; integrar ao ENV existente sem substituir os dados do Core.

Provisionamento pendente no servidor:

1. **Concluído:** criar `/opt/private-mail-center` e provisionar segredos/proprietário. Atualizar o backup protegido para incluir essa pasta antes da publicação; o backup inicial foi criado antes dela.
2. Disponibilizar `secrets.json` somente leitura em `/run/mail-center/secrets.json` e anexos privados em `/var/lib/mail-center/attachments`, pelo override de produção. Não usar certificados ou override de localhost.
3. Resolver o proprietário pelo login confirmado e manter a Central desabilitada até terminar HTTPS, logs e consentimentos.
4. Usar cookies seguros. Se HTTPS terminar em proxy, identificar a origem real e configurar somente proxies confiáveis; não liberar cabeçalhos de qualquer cliente.
5. Conferir redação dos callbacks no Apache e também no proxy/CDN/WAF, antes de marcar `oauth_log_redaction_verified=true`.

## HTTPS da origem

A inspeção confirmou HTTPS público funcionando pela Cloudflare, mas a origem responde apenas em HTTP e não aceita conexão na porta 443. O proprietário confirmou o modo **Flexível**. Não alterar o modo antes de instalar e testar HTTPS na origem.

Foram preparados exemplos inativos `docker/php/mail-center-origin-ssl.example.conf` e `docker/docker-compose.mail-center.tls.example.yml`. A sintaxe Apache foi validada em container isolado, usando certificado local apenas para esse teste. Nenhum desses exemplos foi aplicado ao servidor.

A chave RSA de produção permanece na pasta privada externa `production/tls/privkey.pem`. O pedido público de certificado está em `_deploy/mail-origin.csr`; a assinatura do CSR foi verificada. O proprietário emitiu o certificado [Cloudflare Origin CA](https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/) e salvou `private-mail-center/origin-cert.pem`, fora do workspace. Ainda não foi instalado no servidor.

Validação em 19/10/2026: assinatura conferida com a CA RSA oficial obtida por HTTPS, uso sslserver e hostname aprovados, chave pública correspondente à chave privada preparada, SANs `meuprojetoweb.com` e `*.meuprojetoweb.com`, validade de 19/10/2026 a 15/10/2041. Apache temporário sem rede externa aprovou a sintaxe e serviu um arquivo sintético por HTTPS, com verificação da CA e do nome habilitada. Nenhuma porta foi publicada pelo teste. Evidência em `_deploy/mail-origin-tls-validation.txt`; teste em `scripts/tests/mail_center_origin_tls_test.sh`.

Certificado/chave/CA e exemplos foram posteriormente preparados na pasta privada versionada do servidor, via SSH autenticado, com hash do transporte conferido e validação criptográfica repetida no destino. Pasta 0700 e arquivos 0600; nenhum mount/configuração ativa, reinício, porta ou firewall foi alterado. O teste isolado do preparador também conferiu repetição idempotente e permissões. Evidência em `_deploy/mail-server-tls-staging.txt`. A inspeção somente leitura confirmou SSL disponível/desativado, 443 livre e os Compose atuais; o plano de ativação e reversão está em [Ativação HTTPS](central-de-email-ativacao-https.md).

Depois da preparação, o proprietário autorizou explicitamente a ativação. HTTPS da origem foi ativado e conferido tanto no servidor quanto externamente, com CA/nome verificados e resposta 200; HTTP e acesso público preservados. Somente o container web foi recriado, sem build ou mudança da imagem/banco/ENV. Backup e resultado estão no plano de ativação. O deploy foi adaptado para preservar o override TLS externo. Ainda falta configurar [Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/) na Cloudflare, preferencialmente por regra restrita ao hostname canônico. Certificados Origin CA são destinados à conexão Cloudflare–origem; a validação direta deve usar a CA correspondente. Não confiar genericamente em cabeçalhos encaminhados para contornar a ausência de TLS.

## Retornos a cadastrar nos provedores

Manter os retornos locais que continuam sendo usados e acrescentar os retornos exatos de produção aos respectivos clientes OAuth:

- Google: `https://meuprojetoweb.com/web/admin/central_contas/oauth_callback.php`
- Zoho: `https://meuprojetoweb.com/web/admin/central_contas/oauth_zoho_callback.php`

Em 19/10/2026, o proprietário confirmou que salvou os retornos de produção nos clientes Google e Zoho, mantendo os retornos locais. Isso não equivale a conectar ou testar as caixas em produção.

Não acrescentar permissões de exclusão ou gerenciamento de pastas. Revisar a situação de consentimento/publicação do cliente Google antes do uso contínuo. Após instalar e habilitar, conectar novamente Gmail e Zoho no ambiente de produção; banco, proprietário e chave de produção são próprios. Tokens, rascunhos e marcas locais não são transportados por esta release. Outlook continua adiado.

O proprietário confirmou que o aplicativo Google está em Testing. Com escopos Gmail, refresh tokens emitidos nesse estado têm validade de sete dias. Orientada a mudança do status OAuth para In production antes de obter a autorização definitiva no servidor. Publicação do status OAuth não equivale ao deploy/ativação da Central. Uso pessoal está entre as exceções documentadas à revisão de escopos; pode permanecer a tela de aplicativo não verificado. Se o console apresentar requisitos adicionais, conferir a mensagem exata antes de alterar branding/escopos. Ainda não há confirmação de mudança de status.

Atualização posterior de 19/10/2026: proprietário confirmou status OAuth **Em produção** e apresentou captura da Central de verificação com **marca verificada e aparecendo para os usuários**. Branding concluído; acesso aos dados continua não verificado para os escopos gmail.readonly e gmail.send. A Central permanece de uso exclusivo do proprietário, cenário coberto pela exceção documentada de uso pessoal à revisão formal dos escopos. Não declarar que as permissões foram aprovadas pelo Google ou que a exceção remove todos os limites/avisos. Não é necessário preparar vídeo ou submeter revisão de escopos para esse uso pessoal; a autorização real no servidor ainda precisa ser testada, com possibilidade de aviso de aplicativo não verificado. Se o público do aplicativo mudar, reavaliar os requisitos. Não houve conexão OAuth em produção nem deploy da Central nesta confirmação.

Para preencher Branding, foram preparados apresentação, privacidade, termos e CSS estáticos em `web/central-email/`. Após autorização explícita, somente esses quatro arquivos foram publicados, com verificação de integridade na origem e HTTP 200 também pela Cloudflare; login do Core preservado, sem reinício, mudança de banco ou ativação da Central. URLs: `https://meuprojetoweb.com/web/central-email/index.html`, `privacidade.html` e `termos.html` no mesmo diretório. Textos descrevem o uso pessoal, dados acessados, armazenamento, criptografia aplicável, infraestrutura, revogação e retenção, sem prometer apagar automaticamente backups/metadados ao desconectar. Backup da publicação: `/opt/private-mail-center/branding-20261019T183828Z-NTxX6hdw`; relatório local `_deploy/mail-branding-publication.txt`. O pacote completo deve ser reconstruído antes de eventual deploy para incorporar também esses arquivos.

## Backup e reversão

Backup preparatório criado em `/opt/backups/central-email/before-mail-20261019T172016Z-nVZUrK`, com diretório 0700 e arquivos 0600. Inclui dump lógico do banco Core e arquivos existentes do workspace; o script também inclui a pasta privada se ela já existir. Gzip, arquivos tar e checksums foram conferidos. Não é um backup de todos os bancos independentes dos projetos.

`scripts/central-contas-validar-restauracao.ps1` baixa o dump para pasta privada restrita, restaura em MySQL temporário sem rede externa e ensaia duas aplicações idempotentes das migrações. O PHP de ensaio compartilha apenas o namespace de rede isolado desse banco e não tem configuração de provedores. O dump temporário e o container são removidos ao final. O resultado fica no relatório local ignorado `_deploy/mail-server-restoration.txt`.

Ensaio concluído em 19/10/2026: backup restaurado, contagens de usuários/projetos preservadas e migrações 001–004 aplicadas duas vezes com sucesso. Container e cópia temporária removidos. Antes da publicação, conferir esse relatório e atualizar o backup se o banco tiver recebido mudanças relevantes desde sua criação. A reversão mantém as tabelas aditivas, desabilita o módulo e restaura a versão anterior do código, preservando dados/chaves; não executar DROP automático. Restaurar o banco completo pode apagar alterações posteriores e exige janela e decisão explícitas.

Após a ativação TLS e confirmação da regra Cloudflare, novo backup protegido criado em `/opt/backups/central-email/before-mail-20261019T182056Z-wooKZn`, agora incluindo a pasta privada, chave de produção, certificado e configuração de reversão TLS. Integridade gzip/tar/checksums aprovada. O ensaio de restauração citado acima refere-se ao backup inicial; não declarar que o novo arquivo foi restaurado.

## Conferência posterior à regra Cloudflare

Login público 200, inicial 200 após um redirecionamento e origem direta 200, com TLS validado. Testes anônimos com marcadores fictícios confirmaram presença do controle no log e registro dos caminhos Google/Zoho sem seus parâmetros de consulta. Nenhum token real foi usado. A regra Full (strict) foi confirmada pelo proprietário; não houve leitura por API. A política dos logs da CDN/WAF ainda não foi verificada e `oauth_log_redaction_verified` continua falso.

O cookie emitido pelo Core atualmente instalado não apresentou Secure, HttpOnly ou SameSite=Lax nessa conferência. O código local já inclui a política compartilhada de sessão; conferir as três propriedades na resposta real após publicar essa política, antes de ativar OAuth. Não corrigir isso confiando em cabeçalhos de proxy arbitrários nem tratar a existência de HTTPS como prova de cookies seguros. Relatório local: `_deploy/mail-server-post-https.txt`.

## Sequência restante

**Atualização de 19/10/2026 após autorização de publicação:** instalação limitada concluída no servidor, com Central desabilitada. Migrações 001–004 aplicadas/conferidas, segredos/configuração/base do ENV montados privadamente e política de sessão validada na rota administrativa real. Conferência externa aprovou login 200 e Secure/HttpOnly/SameSite=Lax, Central bloqueada 404 e arquivos ENV/vendor protegidos 403. Backup: `/opt/private-mail-center/releases/install-20261019T190924Z-fiA0zmtu`. A imagem foi preservada e só app foi recriado, sem executar migrações de outros módulos. O status abaixo descreve a preparação anterior; a próxima etapa efetiva é concluir a conferência dos logs CDN/WAF, habilitar somente para o proprietário e realizar os consentimentos/testes reais, mediante autorização dessa etapa.

A revisão do deploy geral identificou efeitos em outros módulos. Foi preparado um [plano de publicação limitada](central-de-email-publicacao-limitada.md), com pacote exclusivo da Central e comparação dos seis arquivos compartilhados. Prévia no servidor aprovada: 956 arquivos íntegros, nenhum conflito e runtime compatível, sem alterar aplicação/banco. Git registrado e branch enviada ao repositório que o proprietário confirmou como privado e autorizou. Usar esse plano para a instalação inicial desabilitada; não executar o deploy geral supondo equivalência de escopo.

1. **Concluído:** ensaio de restauração e migrações sobre a cópia isolada do banco.
2. **Callbacks concluídos por confirmação do proprietário:** Google e Zoho. Revisar ainda a situação do consentimento Google para uso contínuo.
3. **HTTPS da origem concluído:** proprietário confirmou a regra Full (strict) na Cloudflare; acesso público e origem direta passaram depois da mudança. Concluir mounts/integração privada e conferência de cookies/logs, com módulo bloqueado até os testes. Segredos e trecho desabilitado do proprietário já foram provisionados.
4. Revisar/registrar a release no Git e conferir alterações abrangidas pelo deploy completo.
5. Em janela autorizada, publicar, migrar, validar com o proprietário e conectar as duas caixas novamente.
6. Testar leitura, busca, anexos, marcas e sincronização. Envio controlado somente com confirmação do proprietário; manter retorno à versão anterior disponível.

Sem deploy da aplicação, mudança do ENV ativo, ativação de OAuth em produção ou envio real nesta preparação. O container web foi recriado apenas para a ativação HTTPS autorizada. O pacote anterior deve ser reconstruído para incorporar os novos scripts e exemplos antes da revisão final.
