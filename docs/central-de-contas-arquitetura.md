# Central de Contas — arquitetura e acompanhamento

Data: 18/10/2026. Desenho da Etapa 2 aprovado. Fundação da Etapa 3 implementada apenas no Docker local. As Etapas 4 a 7 continuam propostas e dependem de aprovação própria.

## 1. Escopo autorizado e decisão de produto

O proprietário aprovou o desenho e a Etapa 3. Conectar caixas, instalar dependências e publicar continuam sujeitos às respectivas fases. Atualização posterior do proprietário: somente Gmail, Outlook e Zoho; HostGator/Lojas Marim fica fora do escopo, pois a caixa será descontinuada. O Gmail será usado para cadastros em plataformas e sites.

Escopo atualizado: centralizar contas de e-mail, visualizar mensagens, ler, pesquisar, enviar, responder, organizar e acompanhar pendências. As caixas permanecem nos provedores. O gerenciador de senhas externo continua independente. Retirados do plano: catálogo de logins de plataformas, campos de senha, recuperação de plataformas e integração com cofre de senhas.

“Receber” significa consultar/sincronizar as mensagens recebidas no provedor. Não haverá servidor próprio, alteração de MX, encaminhamento ou migração de caixas. Sem sincronização em segundo plano, a atualização ocorrerá ao solicitar sincronização na interface; não se prometerá recebimento instantâneo.

## 2. Evidências e fronteiras

Confirmado no repositório:

- `app/views/layout_admin.php`, `app/views/partials/sidebar.php` e classes `c-page`, `c-card`, `c-btn`: estrutura visual reutilizável.
- `app/helpers/auth.php`, `app/services/auth/AuthService.php`, `app/helpers/csrf.php`: autenticação existente e CSRF; autorização atual é administrativa, não exclusiva de uma pessoa.
- `app/database/schema.sql`: `core_users.id` é `INT UNSIGNED`; o desenho usará o mesmo tipo nas referências ao usuário.
- `app/console/migrate_marketing_identity.php`: precedente para migração CLI explícita com simulação e `--apply`.
- `app/bootstrap/bootstrap.php`: faz alterações no banco ao atender requisições. O módulo não acrescentará migrações automáticas a esse fluxo.
- `modules/contas/README.md`: `contas` já identifica contas a pagar e receber.
- `app/services/projects/MailService.php`: envio transacional global; permanecerá separado das identidades da Central.
- `app/services/Media/MediaService.php`: biblioteca de imagens; não será usada para anexos confidenciais.
- `web/sw.js`: atualmente encaminha requisições para a rede e não mantém cache offline. A Central exigirá `Cache-Control: no-store`.

Decisão proposta: recurso exclusivo do Core, slug `central_contas`, tabelas `core_mail_*`. Não será pacote distribuído em `modules/`, `bases/` ou projetos de clientes. A Central poderá referenciar `projects.id` para organização, sem conectar bancos dos projetos nem copiar suas credenciais.

Fluxo: página/ação PHP → autorização do proprietário → serviço da Central → repositório PDO e adaptador do provedor. Renderização continua no layout administrativo. Não há segundo login nem aplicação frontend separada.

## 3. Acesso e configurações

O módulo inicia desabilitado e exige `mail_center.enabled` e `mail_center.owner_user_id` na configuração privada. O ID será identificado no ambiente na implementação, sem deduzir proprietário pelo primeiro administrador ou pelo papel SUPER_ADMIN. Sem configuração válida, negar acesso.

Toda requisição verifica sessão existente, usuário ativo no banco e correspondência ao proprietário configurado. Toda busca de conta, mensagem, rascunho, envio, arquivo e tarefa inclui `owner_user_id`. Recursos de outro dono retornam 404. Esconder o menu não é controle de acesso.

Configuração proposta, sem valores secretos neste documento: proprietário, habilitação, URL canônica por ambiente, provedores habilitados, IDs de clientes OAuth, referências aos segredos, limites de anexos, retenção e limites de chamadas. Segredos e chaves ficam em arquivo montado somente para leitura fora do DocumentRoot ou mecanismo equivalente do host. Não entram no Git, em `core_settings`, no navegador ou nos logs. Nenhum endpoint aceitará URL arbitrária de API ou SMTP fornecida por formulário.

Local e produção usam a mesma estrutura de configuração e credenciais diferentes. HTTPS será obrigatório para operar caixas reais; o uso atual em HTTP localhost serve para fundação/testes sem mensagens reais. OAuth local dependerá das regras do provedor e de callback de desenvolvimento registrado, sem criar túneis nesta fase.

## 4. Modelo de dados proposto

As tabelas abaixo são propostas, não SQL executado. MySQL 8, InnoDB, utf8mb4, horários em UTC e apresentação em America/Sao_Paulo. IDs internos `BIGINT UNSIGNED`, salvo referências aos IDs existentes. IDs remotos são strings opacas com comparação sensível a maiúsculas e tamanho validado por adaptador; não serão convertidos em inteiros.

| Fase | Tabela | Conteúdo e restrições principais |
| --- | --- | --- |
| 3 | `core_mail_schema_versions` | Versão, checksum e data de migração; ledger exclusivo da Central. |
| 3 | `core_mail_providers` | Código único, nome, tipo de adaptador, habilitação. Capacidades implementadas pelo adaptador; endpoint não editável pelo usuário. |
| 3 | `core_mail_categories` | Dono, nome, cor e ordem; único `(owner_user_id, name)`. |
| 3 | `core_mail_accounts` | Dono, provedor, endereço informado, nome, finalidade, categoria, projeto opcional, ordem, ativo e estado cadastral; endereço confirmado somente pelo provedor posteriormente. |
| 3 | `core_mail_audit_events` | Dono, conta opcional, ação, resultado, ID de correlação, código de erro seguro e data; sem assunto, corpo, destinatário, tokens ou query de busca. |
| 4 | `core_mail_connections` | Uma conexão por conta; ID remoto confirmado, escopos concedidos, tokens criptografados, nonce, versão da chave, expiração e estado. |
| 4 | `core_mail_identities` | Conta, endereço e nome confirmados, identidade padrão e permissões de envio; alias apenas quando validado. |
| 4 | `core_mail_folders` | Conta, ID remoto, pai, nome, tipo `folder`/`label`; único `(account_id, remote_id)`. |
| 4 | `core_mail_messages` | Dono, conta, ID remoto, conversa remota, assunto, remetente, destinatários, datas, flags, existência de anexos e data da sincronização; único `(account_id, remote_id)`. Sem corpo completo no cache inicial. |
| 4 | `core_mail_message_folders` | Associação N:N de mensagem com pasta/label; nunca reduzir múltiplas labels a uma única pasta. |
| 4 | `core_mail_sync_states` | Conta, escopo/pasta, cursor opaco protegido, última execução, progresso, falha, lease e expiração; único `(account_id, scope_key)`. |
| 4 | `core_mail_drafts` | Dono, conta, identidade, destinatários e conteúdo criptografado, referência à mensagem respondida e versão para concorrência. |
| 4 | `core_mail_attachments` | Descritores remotos ou arquivos temporários de rascunho, tamanho, MIME, nome seguro, expiração e referência à mensagem/rascunho. Bytes não serão BLOBs no MySQL. |
| 4 | `core_mail_outbox` | Dono, conta, identidade imutável, snapshot criptografado, chave de idempotência única por dono, tentativas, lease, datas, estado e referência remota quando disponível. |
| 6 | `core_mail_tags` | Dono, nome e cor; único `(owner_user_id, name)`. |
| 6 | `core_mail_message_tags` | Mensagem e tag do mesmo dono; marcações locais independentes de labels remotas. |
| 6 | `core_mail_tasks` | Dono/responsável, conta, mensagem opcional, título não sensível, prioridade, prazo, estado e lembrete; no uso pessoal responsável é o proprietário. |

Assuntos e participantes também são dados pessoais: armazenamento limitado à janela selecionada, sem snippets por padrão. Rascunhos e snapshots precisam de conteúdo persistente para funcionamento; serão criptografados, sem justificar uma cópia integral das caixas.

Regras relacionais: conta pertence ao dono; filhos não podem referenciar contas, mensagens ou identidades de outro dono. Usar FKs compostas onde aplicável e validação transacional no serviço, inclusive tarefas/tags. IDs de conversa são isolados por conta; assunto igual não une conversas de caixas diferentes. Índices principais: dono/conta/data/ID, dono/estado/prazo e estado/próxima tentativa. JSON apenas para estruturas limitadas, sem guardar respostas brutas dos provedores.

Exclusão de conta exige escolha explícita: desativar, desconectar ou eliminar dados locais. Nenhuma dessas operações exclui mensagens no provedor. Categorias em uso não serão excluídas silenciosamente. Remover projeto desvincula a classificação da conta, preservando e-mail.

## 5. Adaptadores e diferenças entre provedores

Contrato proposto: capacidades, URL de autorização, troca/renovação/revogação, identidade autenticada, listar pastas/labels, listar/buscar/ler mensagens, anexos, sincronização incremental, alterações suportadas, envio e resposta. Retornos normalizados e erros classificados em autenticação, permissão, quota, rede, validação e resultado incerto.

| Capacidade | Gmail API | Microsoft Graph | Zoho/domínios |
| --- | --- | --- | --- |
| OAuth e leitura/envio | Documentados; consentimento depende dos escopos | Documentados inclusive para contas pessoais, com permissões delegadas apropriadas | OAuth/API documentados; plano, região e caixa precisam de confirmação |
| Organização | Labels e labels de sistema | Pastas, flags e categorias conforme endpoints/escopos | Pastas/tags conforme API e plano a validar |
| Incremental | Histórico; cursor inválido exige recuperação | Delta por pasta; acompanhar mudanças de pasta | Método exato pendente; aceitar polling paginado como alternativa |
| Busca | Busca nativa com restrições de escopo | Busca nativa com limitações próprias | Operadores e cobertura a validar |
| Aliases | Somente identidades de envio permitidas pelo provedor | Não presumir envio como alias nem permissões de caixa compartilhada | Somente identidades confirmadas |
| Exclusão/arquivamento | Operações distintas conforme labels | Arquivo depende de pasta/operação suportada | Não habilitar até validar |

Gmail: propor inicialmente leitura e envio com `gmail.readonly` e `gmail.send`; solicitar `gmail.modify` somente quando organizar remotamente for aprovado. `gmail.metadata` não permite a busca `q` e não atende à leitura de corpo. Evitar o escopo integral `mail.google.com`. Mesmo metadados podem usar escopos restritos. Verificação, exceção de uso pessoal e eventual avaliação de segurança precisam ser resolvidas antes do piloto.

Microsoft: propor permissões delegadas de leitura e envio e acesso offline; `Mail.ReadWrite` somente para alterações necessárias. Sem permissões de aplicação para todas as caixas do tenant. Confirmar consentimento e políticas da conta. `202 Accepted` não significa entrega confirmada.

Zoho: MX do MeuProjetoWeb identificado como Zoho na Etapa 1. Selecionar região e escopos a partir da conta real e da documentação. HostGator/Lojas Marim não será integrado, por decisão do proprietário. Cadastro de novas caixas e categorias é por tela; os provedores permitidos nesta entrega são Gmail, Outlook e Zoho.

IMAP/SMTP: alternativa condicionada à falta de API adequada e autenticação suportada. Não faz parte da primeira implementação: IMAP não está instalado no PHP e SMTP tem bloqueios documentados na DigitalOcean. Não oferecer campo de senha de caixa; app password também é segredo e não será adotada silenciosamente como alternativa.

## 6. OAuth, desconexão e tokens

1. Dono autenticado inicia conexão por POST com CSRF; conta e provedor são selecionados no servidor.
2. Gerar `state` aleatório, de uso único, validade de 10 minutos, vinculado à sessão, dono, conta, provedor e redirect URI. PKCE S256 quando suportado pelo provedor/fluxo escolhido. Sem fallback silencioso para fluxo inseguro.
3. Redirecionar ao endpoint oficial com escopos mínimos e callback exato pré-cadastrado. Callback proposto: `/web/admin/central_contas/oauth_callback.php`. É endereço futuro, não endpoint existente ou registrado.
4. Callback exige sessão e `state` válido, trata cancelamento e erro sem logar parâmetros completos. Troca o código no servidor e confirma identidade com o provedor. Mudança de conta requer confirmação; não sobrescrever outra conexão automaticamente.
5. Guardar tokens com Sodium XChaCha20-Poly1305, nonce aleatório e dados autenticados incluindo dono, conta e provedor. Guardar identificador da chave, não a chave, no banco. Tokens nunca vão ao HTML, JS ou URL.
6. Renovação com margem de expiração e lock por conexão; preservar refresh token anterior se não houver substituto e persistir rotação atomicamente. `invalid_grant` suspende acesso e solicita reconexão.
7. Desconectar bloqueia imediatamente o uso local e remove tokens após tentativa de revogação suportada. Se a revogação externa falhar/não existir, informar como retirar o consentimento no provedor. Nenhuma garantia falsa de revogação remota.

Chaves: chave nova para novas gravações, leitura com conjunto antigo durante rotação, recriptografia controlada e backup separado. Perder a chave exige reconectar e pode tornar rascunhos irrecuperáveis. Rotação não ocorre por requisição web comum.

## 7. Sincronização e pesquisa

Proposta inicial: sincronizar os últimos 30 dias, páginas de até 50 registros (limitadas pelo adaptador), com histórico ampliável sob demanda. Mensagens antigas continuam no provedor; os filtros locais deixam explícita a janela disponível.

Upsert por conta/ID remoto, lease por conta/escopo, checkpoint somente após a página persistir e cursor protegido. Respeitar `Retry-After`, backoff exponencial com jitter e orçamento por conta. Não repetir envio por um erro de sincronização. Cursor inválido exige ressincronização limitada, sem apagar dados locais indiscriminadamente. Alteração remota de ID precisa de tratamento específico; no Graph usar IDs imutáveis quando a operação permitir.

Começar por sincronização manual em lotes curtos e com progresso visível. CLI futura permite reaproveitar a lógica. Cron/worker só será instalado após aprovação e se necessário para atualização automática/lembretes. A tela deve mostrar última sincronização e falhas; nunca apresentar dados antigos como atuais.

Busca local cobre metadados já sincronizados. Busca no provedor ocorre separadamente e será identificada na UI. Busca unificada pagina por conta e tolera falha parcial; não promete equivalência entre sintaxes ou busca completa no corpo sem consultar o provedor.

## 8. Envio, resposta e prevenção de duplicidade

Compositor mostra remetente destacado; responder fixa a conta da mensagem de origem. Trocar conta exige ação explícita, nova validação da identidade e confirmação. Não confiar em `from` vindo do navegador. Validar destinatários, limites, cabeçalhos e anexos no servidor.

Estados: `draft` → `queued` → `sending` → `accepted` → `confirmed`; alternativas `failed`, `unknown` e `cancelled`. `confirmed` significa evidência de aceitação/registro no provedor, não leitura ou entrega na caixa do destinatário. Aceitação e evidência disponível serão exibidas separadamente.

Cada envio tem chave de idempotência e snapshot imutável. Claim atômico e lease impedem dois executores de enviar simultaneamente. Duplo clique retorna a operação existente. Timeout após início do envio gera `unknown`: bloquear retry automático e reconciliar com o provedor; se não for possível, solicitar decisão explícita. Chave local ou Message-ID não garantem exatamente uma entrega em todos os provedores. Falha anterior à submissão pode ter retry controlado.

Resposta usa referência/thread do provedor e cabeçalhos adequados quando disponíveis. MIME e encoding serão tratados por biblioteca apropriada. Não encaminhar anexos ou conteúdo de outra conta automaticamente. “Enviado” só aparece com evidência do provedor e sem mascarar incerteza.

## 9. Interface no layout do Core

Menu: “Central de Contas”, visível só ao dono. Mesmos cabeçalho, sidebar, tema e componentes do Core; CSS específico limitado à Central.

- Visão geral: caixas cadastradas, conectadas, com erro, última atualização e pendências.
- Contas: nome, endereço, provedor, finalidade, categoria, projeto opcional e estado. Na Etapa 3, mostrar “cadastrada; conexão ainda indisponível”.
- Caixa: filtro por conta, pasta/label, categoria, período e estado; origem com nome/endereço/texto, além da cor.
- Mensagem: conta e remetente no cabeçalho, texto como fallback, anexos identificados e ações compatíveis com o provedor.
- Composição: remetente obrigatório, Para/Cc/Cco, assunto, conteúdo, anexos, rascunho, confirmação e estado do envio.
- Pendências: responder, aguardar, resolver, importante e arquivar; “arquivar” local é uma marcação até que a ação remota seja explicitamente acionada.
- Celular: lista e mensagem em telas sucessivas, sem três colunas obrigatórias; manter navegação do Core.

O tema escuro não deve tornar o e-mail ilegível; conteúdo externo ficará em área isolada. Sem rastreadores, pixels ou imagens remotas carregados automaticamente. Não prometer notificações instantâneas ou uso offline.

## 10. Segurança, retenção e operação

Antes de caixas reais: confirmar proteção do DocumentRoot e dos diretórios internos, sessões, credenciais versionadas apontadas no diagnóstico, HTTPS até a origem, configuração de erros e autorização por dono. Nenhum resultado de TLS público comprova segurança da origem.

Na Etapa 3, propor `Options -Indexes`, bloqueios dos diretórios internos e arquivos sensíveis no `.htaccess`, preservando `app/actions/` necessário ao Core. Não mover o DocumentRoot nesta entrega, pois isso mudaria URLs existentes. Revisão de segredo literal e remoção da configuração legada só após conferir uso; rotação externa/histórico Git exigem autorização específica. Não corrigir isso por substituição cega.

Sessão: configurar Secure por ambiente HTTPS canônico confiável, HttpOnly, SameSite=Lax e strict mode antes de iniciar sessão; regenerar no login e impor inatividade no módulo. Não confiar em `X-Forwarded-Proto` sem lista de proxies confiáveis. Revalidar status do dono no banco. Testar o login existente após cada alteração.

HTML: biblioteca de sanitização mantida, allowlist e iframe sandbox sem scripts/formulários; sanitizar também SVG/CSS/URLs ou removê-los. Mostrar texto se o sanitizador falhar. Não inserir HTML bruto no layout. CSP restritiva no documento do e-mail; CSP global do Core depende de auditoria dos scripts inline existentes.

Anexos: download autenticado em streaming, checagem de dono, nome e MIME seguros, `Content-Disposition: attachment`, `nosniff`, limites de bytes e tempo; nenhum caminho fornecido pelo navegador. Arquivos locais temporários ficam fora do DocumentRoot, criptografados quando persistidos, com permissões restritas. Não usar a biblioteca pública de mídia. Piloto: uploads até 2 MB por arquivo e limite total conservador de 6 MB, subordinados aos limites reais de PHP, proxy e provedor. Sem execução ou extração de arquivos; validar MIME não equivale a antivírus.

SSRF: hosts oficiais allowlisted, HTTPS, verificação TLS, sem redirects livres, validação de links de paginação antes de reutilizar Authorization. Corpos, imagens e links do e-mail nunca viram instruções para o servidor buscar URLs arbitrárias.

Rate limiting persistente: usar eventos de auditoria indexados para ações do proprietário e estado de sincronização para budgets por conta; não criar Redis. Valores iniciais a testar: 5 inícios OAuth por 10 minutos e 5 envios por minuto, teto diário conservador configurável. Restrições do provedor continuam superiores.

Retenção proposta: metadados por 90 dias; corpos lidos apenas durante a requisição; rascunhos até exclusão pelo dono, com alerta aos 90 dias; temporários de upload por 24 horas; snapshots de envios concluídos por 7 dias; registros mínimos de envio/idempotência por 90 dias; auditoria por 90 dias, sem conteúdo. Filas `unknown` não serão reenviadas ou eliminadas silenciosamente. Expurgos dependem de execução CLI aprovada e deixam evento seguro. A retenção não apaga e-mails no provedor.

Backups: banco e arquivos privados criptografados diariamente, retenção inicial de 7 diários e 4 semanais, chave guardada separadamente; validar custo/espaço antes de ativar. A cópia dos tokens exige mesma proteção do original. Testar restauração isolada com envio e conexões externos desabilitados. RPO alvo de 24 h e RTO alvo de 4 h são metas a comprovar, não SLA existente. Backups também precisam de expiração documentada.

Observabilidade: estado de cada caixa, horário da sincronização, contagem de falhas, filas e correlação. Redigir erros externos; não registrar corpos HTTP brutos, parâmetros OAuth, destinatários, conteúdo ou chaves. Falhas de uma conta não tornam as demais indisponíveis.

## 11. Plano exato de arquivos por fase

Os caminhos são relativos à raiz do repositório. Esta lista é a proposta de mudança, não arquivos já criados. Qualquer ampliação funcional terá nova revisão ao final da fase.

### Etapa 3 — fundação, cadastro das caixas e segurança necessária

Criar:

- `app/helpers/mail_center.php`: configuração, autorização exclusiva e headers privados.
- `app/services/mail/MailAccountService.php`: CRUD, categorias, provedores e vínculos.
- `app/services/mail/MailAuditService.php`: eventos redigidos e limites locais.
- `app/services/mail/MailSchemaMigration.php`: migrações explícitas e ledger.
- `app/database/mail_center_001_foundation.sql`: as cinco tabelas da fase 3.
- `app/console/migrate_mail_center.php`: simular/aplicar, sem bootstrap web.
- `app/console/check_mail_center.php`: checagens sem mutação ou segredos.
- `web/admin/central_contas/index.php`: visão inicial e estados vazios reais.
- `web/admin/central_contas/accounts.php`: cadastro e edição.
- `web/admin/central_contas/_nav.php`: navegação local.
- `app/actions/central_contas/account_save.php`.
- `app/actions/central_contas/account_status.php`.
- `app/actions/central_contas/category_save.php`.
- `web/assets/css/admin/central_contas.css`.
- `scripts/tests/mail_center_foundation_test.php`.
- `scripts/tests/mail_center_ui.cjs`.

Alterar:

- `app/views/partials/sidebar.php`: item condicionado à autorização.
- `app/bootstrap/bootstrap.php`: parâmetros de sessão antes do início, sem instalar tabelas da Central.
- `app/services/auth/AuthService.php`: regeneração de sessão.
- `.htaccess`: desabilitar listagem/bloquear internos preservando rotas; revisar diretivas legadas sem expor valores.
- `env/env.production.php.example`: campos não secretos e instruções de configuração desabilitada por padrão.
- `docs/central-de-contas-arquitetura.md`: registrar resultado e limitações.

Configuração real `env/env.production.php`: somente habilitação e ID do proprietário mediante implementação aprovada; arquivo não versionado. Produção não será habilitada nesta fase. Não alterar `modules/contas`, bases, projetos, `core_settings`, schema global ou MailService transacional.

### Etapa 4 — piloto, somente após escolha explícita de caixa/provedor

Criar:

- `app/database/mail_center_002_pilot.sql`: nove tabelas da fase 4.
- `app/services/mail/MailProviderInterface.php`.
- `app/services/mail/MailProviderRegistry.php`.
- `app/services/mail/MailHttpClient.php`.
- `app/services/mail/MailOAuthService.php`.
- `app/services/mail/MailTokenCipher.php`.
- `app/services/mail/MailSyncService.php`.
- `app/services/mail/MailMessageService.php`.
- `app/services/mail/MailSendService.php`.
- `app/services/mail/MailAttachmentService.php`.
- `app/services/mail/MailHtmlRenderer.php`.
- `app/services/mail/providers/GmailMailProvider.php` se Gmail for o piloto, OU `ZohoMailProvider.php` se Zoho for o piloto, OU `GraphMailProvider.php` se Graph for escolhido expressamente. Criar apenas o adaptador escolhido.
- `web/admin/central_contas/oauth_callback.php`.
- `web/admin/central_contas/messages.php`.
- `web/admin/central_contas/message.php`.
- `web/admin/central_contas/compose.php`.
- `web/admin/central_contas/message_body.php`.
- `web/admin/central_contas/attachment.php`.
- `app/actions/central_contas/connect.php`.
- `app/actions/central_contas/disconnect.php`.
- `app/actions/central_contas/sync.php`.
- `app/actions/central_contas/draft_save.php`.
- `app/actions/central_contas/send.php`.
- `app/actions/central_contas/message_update.php`.
- `web/assets/js/central_contas.js`.
- `app/console/mail_center_sync.php`.
- `app/console/mail_center_maintenance.php`.
- `scripts/tests/mail_center_pilot_test.php`.
- `scripts/tests/mail_center_pilot_ui.cjs`.
- `composer.json`, `composer.lock`: dependências aprovadas para OAuth, sanitização e MIME, com versões resolvidas/testadas na ocasião. Candidatas: `league/oauth2-client`, `ezyang/htmlpurifier`, `symfony/mime`; compatibilidade e dependências transitivas ainda precisam de auditoria.

Alterar configuração de exemplo, helper de migração, helper de autorização, páginas iniciais, CSS, testes e documentação da fase 3. Carregar Composer apenas na infraestrutura da Central inicialmente. Alterar `docker/php/Dockerfile` somente se requisitos reais das bibliotecas exigirem extensões adicionais, sob aprovação dessa fase. Arquivo/chave montados fora da raiz web e eventual configuração de mount no Compose terão caminhos concretos definidos após conferir o host.

### Etapa 5 — demais provedores e caixa unificada

Criar os adaptadores ainda ausentes dentre os três caminhos definidos acima e `scripts/tests/mail_center_multibox_test.php`. Alterar registry, busca/sincronização, páginas de mensagens e compositor, configuração de exemplo, testes UI e documento. Nenhuma nova tabela prevista; diferenças que exijam schema terão migração e aprovação próprias.

### Etapa 6 — pendências

Criar `app/database/mail_center_003_tasks.sql` (três tabelas), `app/services/mail/MailTaskService.php`, `web/admin/central_contas/tasks.php`, `app/actions/central_contas/task_save.php`, `app/actions/central_contas/tag_save.php` e `scripts/tests/mail_center_tasks_test.php`. Alterar índice, mensagem, navegação, serviço de manutenção, CSS e testes UI. Lembretes no painel; agendamento externo somente se aprovado. Não adicionar destinatários ou notificações externas implicitamente.

### Etapa 7 — hardening e produção

Criar `scripts/tests/mail_center_security_test.php`, `docs/central-de-contas-operacao.md` e `docs/central-de-contas-deploy.md`. Revisar apenas os arquivos anteriores e a configuração de produção necessária. Antes de publicar, apresentar diff final, backup validado, plano de execução e rollback. Remover todas as migrações legadas do bootstrap é trabalho transversal separado: avaliar impacto e pedir escopo específico, sem embutir refatoração ampla nesta entrega.

## 12. Migração, reversão e implantação

Cada migração tem preflight, simulação, checksum e execução CLI explícita. Não assumir rollback transacional para DDL MySQL: pode haver commit implícito. Aplicar em backup restaurado de teste antes do banco principal; documentar retomada de falha parcial.

Rollback primário: desabilitar a feature, bloquear novas operações, pausar fila/sincronização e voltar o código compatível, preservando tabelas e registros. Desabilitar o módulo não é revogar consentimento. Reduções de schema e descarte de tokens/dados precisam de aprovação específica. Na fase 3, se houver dados, não usar DROP como rollback automático.

Não executar envios em ambiente restaurado nem promover token de produção ao local. Configurações e chaves distintas por ambiente. Produção exige teste de headers/HTTPS/origem, limites HTTP, permissões de arquivos, egress, backup e callback registrado. Ponto de aprovação explícito antes de deploy permanece obrigatório.

## 13. Complexidade e testes de aceite

| Etapa | Complexidade | Principais testes exigidos |
| --- | --- | --- |
| 3 | Média; sessão/Apache elevam risco de regressão | Dono acessa; outro ADMIN/SUPER_ADMIN/usuário inativo não acessa; CSRF; isolamento; CRUD e vínculos; migração repetida; páginas antigas/login; layouts desktop/celular/temas; bloqueios HTTP preservam ações do Core. |
| 4 | Alta | State expirado/reutilizado, conta trocada, refresh concorrente, ciphertext alterado, expiração/revogação, HTML hostil, anexos/SSRF, cursor inválido, rate limit, duplo clique, timeout ambíguo, remetente correto e envio/recebimento controlado pelo proprietário. |
| 5 | Alta por provedor | Falha parcial, IDs iguais em caixas distintas, labels múltiplas, mudanças de pasta, paginação/busca, ausência de duplicação e resposta pela origem. |
| 6 | Média | Dono, prazo/fuso, mensagem removida, marcas locais, persistência e ausência de notificações externas involuntárias. |
| 7 | Alta | Restauração, rotação, rollback, origem HTTPS, backups, segregação local/produção, expurgo, retries e auditoria sem conteúdo. |

Testes automatizados usarão fixtures sintéticas e bancos temporários isolados, nunca caixas reais por padrão. Registros e payloads de teste não carregarão credenciais. Teste real de envio exige caixa piloto e destinatário de teste acordados na Etapa 4. Não atribuir calendário fechado antes de definir piloto, consentimento e produção.

## 14. Custos, lacunas e decisão solicitada

Não há custo novo autorizado. Não se propõe novo container, Redis, servidor de e-mail ou serviço de marketing. Custos potenciais: plano do provedor, avaliação Google se aplicável, disco/backup e eventual hospedagem de callback HTTPS. APIs têm quotas e limites de envio; limites concretos dependem da conta e serão rechecados no piloto.

Pendentes para as próximas fases: ID do proprietário em produção; plano/região Zoho; caixa piloto de menor sensibilidade; cadastro OAuth/consentimento; permissões reais dos aliases; proteção da origem e arquivos em produção; cron no host; limites Apache/proxy e restauração comprovada. O proprietário local foi identificado pelo e-mail informado, sem inferência de papel ou ordem no cadastro. Lojas Marim/HostGator está excluído.

Decisão executada: o proprietário aprovou somente a Etapa 3, ajustada para cadastro/organização de caixas e sem catálogo de senhas ou plataformas. Não foram conectadas contas nem enviados e-mails; sem dependências novas ou deploy. Antes de habilitar qualquer caixa real, todos os bloqueadores de segurança e consentimento deverão estar resolvidos. Outlook pessoal de recuperação fica para a última conexão, salvo decisão posterior explícita.

## 15. Verificações desta etapa e fontes

Na Etapa 2: releitura dos pontos de integração, comparação com contrato dos módulos, conferência do service worker, revisão do plano por dependência de fase e consulta às fontes oficiais abaixo. A Etapa 2 adicionou apenas este documento. Na Etapa 3 houve implementação e testes, detalhados na seção seguinte.

- Gmail, escopos: https://developers.google.com/workspace/gmail/api/auth/scopes
- Gmail, pesquisa/listagem: https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/list
- Gmail, sincronização: https://developers.google.com/workspace/gmail/api/guides/sync
- Google, verificação/exceções: https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification
- Google, validade dos tokens: https://developers.google.com/identity/protocols/oauth2
- Graph, permissões: https://learn.microsoft.com/graph/permissions-reference
- Graph, delta: https://learn.microsoft.com/en-us/graph/delta-query-messages
- Graph, envio: https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0
- Zoho, OAuth: https://www.zoho.com/mail/help/api/using-oauth-2.html
- Zoho, IMAP: https://www.zoho.com/mail/help/imap-access.html
- DigitalOcean, SMTP: https://docs.digitalocean.com/support/why-is-smtp-blocked/

As capacidades documentadas dos provedores não comprovam habilitação nas contas do proprietário. A aprovação deste desenho não autoriza etapas posteriores automaticamente.

## 16. Resultado da Etapa 3

Implementado somente no ambiente local, com a aprovação do proprietário:

- Visão geral e cadastro/edição de caixas, finalidade, categorias, cor, ordem e vínculo opcional a projetos. Ativação/desativação preserva os registros; sem exclusão destrutiva.
- Provedores cadastrados: Gmail, Outlook e Zoho. Nenhuma caixa real foi cadastrada ou conectada automaticamente.
- Acesso limitado ao usuário local validado pelo e-mail informado (ID 5). A configuração privada local habilita a feature; não foi alterado ENV de produção. Usuário inativo, sem papel administrativo, outro administrador ou proprietário não configurado é recusado.
- CSRF e POST obrigatórios nas três ações; bloqueio de campos extras, validação de vínculos e revisão otimista; mutação e auditoria na mesma transação; limite de 30 alterações por minuto por proprietário.
- Migração CLI simulável, checksum, lock e retomada com DDL idempotente. Cinco tabelas criadas: `core_mail_schema_versions`, `core_mail_providers`, `core_mail_categories`, `core_mail_accounts`, `core_mail_audit_events`. Os esquemas das 31 tabelas anteriores permaneceram iguais na comparação imediatamente após a migração.
- Sessões com strict mode, HttpOnly, SameSite=Lax e Secure conforme URL canônica/configuração confiável. Login regenera o ID, descarta CSRF antigo e registra horário de autenticação; Central exige nova autenticação após inatividade. Sessões anteriores à atualização podem exigir novo login.
- Apache sem listagem de diretórios e com bloqueio de fontes/configurações/backups/logs. Ações PHP do Core e avatares raster com nomes simples continuam permitidos. Diretivas legadas de credenciais literais foram retiradas do `.htaccess` após conferir que o Core usa o ENV; nenhum segredo foi rotacionado nem o histórico Git foi reescrito.
- Adicionado `app/helpers/session.php` além da lista original para centralizar e testar a política da sessão, sem misturá-la aos serviços de e-mail. Os demais arquivos seguem o plano da Etapa 3.

Validação realizada:

- Suíte PHP em banco temporário com fixtures sintéticas: 122 verificações aprovadas (migração repetida e simulação, checksum, autorização, CRUD, isolamento, dados inválidos, campos de segredo recusados, CSRF, concorrência, limite de alterações, escape de HTML, rotas e regeneração de sessão). O banco temporário é eliminado ao final.
- Teste visual com Playwright/Edge: 24 combinações (quatro telas, temas claro/escuro, larguras 1280/390/360), sem overflow ou sobreposição da sidebar. Capturas revisadas; usam contas sintéticas, não caixas reais.
- PHP lint dos arquivos da fundação aprovado e Apache `Syntax OK`.
- HTTP local anônimo: login 200; Central e ações privadas 404; ENV, bootstrap, SQL, logs, Git, backups e scripts bloqueados com 403; CSS da Central 200. Cookie observado com HttpOnly/SameSite=Lax.
- HTTP local autenticado por sessão temporária de teste: Central (visão geral/cadastro), dashboard, bases, módulos, projetos e configurações renderizados sem avisos. Respostas da Central com no-store, ação recusando GET com 405 e as três ações rejeitando CSRF inválido com 403. O Apache local não aceita 419 como esperado; a Central usa o código padrão 403, confirmado por HTTP. Login e sessão também foram testados com usuário/senha sintéticos no banco isolado. Sessão temporária removida ao final do teste correto; nenhum cookie de teste foi registrado no relatório.

Não realizado: conexão OAuth, armazenamento de tokens, leitura/recepção/envio de mensagens, testes reais de provedores, alterações de DNS, instalação de dependências/containers/cron, commit, deploy, rotação externa de credenciais ou validação da origem em produção. Não houve catálogo de plataformas ou integração com gerenciador de senhas.

Riscos remanescentes: bootstrap legado continua executando DDL/DML em requisições; removê-los exige escopo transversal separado. A remoção das diretivas locais não elimina possíveis segredos antigos no histórico Git nem comprova sua validade. Antes de caixas reais, validar origem HTTPS, segredos fora do DocumentRoot, consentimento e sanitização/anexos. A retenção automática da auditoria entrará com a rotina de manutenção na fase piloto; nesta fundação os eventos não têm conteúdo e não são expurgados automaticamente.

Acesso local: entrar novamente no Core com o usuário proprietário e usar o menu “Central de Contas”, ou abrir `/web/admin/central_contas/index.php`. Nenhuma credencial precisa ser informada à Central nesta etapa.

Reversão: desabilitar `mail_center.enabled` no ENV local; voltar o código se necessário preservando as cinco tabelas. Sem DROP automático. Não copiar o ID 5 para produção: identificar o proprietário no banco daquele ambiente. Próxima decisão: aprovar a Etapa 4 e escolher a caixa piloto; recomendação de Gmail de cadastros, mantendo o Outlook de recuperação para o final.

## 17. Piloto Gmail e HTTPS local — implementação da Etapa 4

A Etapa 4 foi autorizada pelo proprietário com `moises.cavalcante.br@gmail.com` como piloto. Foi implementado o código local; o aceite real permanece pendente do aplicativo Google e da validação controlada de leitura, envio e recebimento. Outlook e Zoho continuam sem conectores ativos. HostGator/Lojas Marim permanece excluído.

Implementação:

- OAuth Web com PKCE S256, state de uso único vinculado a sessão/proprietário/conta e conferência do endereço piloto. Escopos somente gmail.readonly e gmail.send. Renovação serializada e desconexão com remoção de tokens locais e tentativa de revogação.
- Tokens, cursores, rascunhos, snapshots e arquivos privados com XChaCha20-Poly1305 e contexto por proprietário/conta/finalidade. Chaves e credenciais exigidas em arquivo externo ao DocumentRoot. Nenhum segredo Google real ou chave de dados foi provisionado.
- Sincronização manual em lotes, janela inicial de 30 dias, histórico incremental, pausa por quota e recuperação de cursor vencido. Pesquisa local e no Gmail, leitura protegida, HTML sanitizado em iframe com sandbox/CSP e bloqueio de recursos remotos.
- Compositor em texto simples com conta de origem fixa, rascunho salvo e revisão antes da confirmação. MIME por biblioteca, anexos criptografados e limitados, idempotência e consulta dos enviados. Resultado incerto bloqueia reenvio automático; não há garantia de entrega apenas pela aceitação da API.
- Migração 002 aplicada exclusivamente no MySQL do Docker local: nove tabelas adicionais (`core_mail_connections`, `core_mail_identities`, `core_mail_folders`, `core_mail_messages`, `core_mail_message_folders`, `core_mail_sync_states`, `core_mail_drafts`, `core_mail_attachments`, `core_mail_outbox`). As definições das 36 tabelas anteriores foram comparadas e preservadas. Cadastro organizacional do Gmail piloto e categoria Cadastros criados para o proprietário local ID 5; zero conexões reais.
- Dependências diretas com Composer/lock: league/oauth2-client 2.9.1, ezyang/htmlpurifier 4.19.1, symfony/mime 7.4.19 e egulias/email-validator 4.0.4. Auditoria de dependências sem avisos conhecidos na execução desta etapa. O deploy legado ainda precisa adaptação antes de produção.
- Sortable 1.15.0, já usado pelo Core, passou a arquivo local; páginas privadas da Central não o carregam. Serviços, rotas, exemplos privados e testes ficam nos diretórios previstos. Guia operacional em `docs/central-de-contas-gmail-piloto.md`.
- HTTPS no app Apache existente, sem serviço adicional: localhost:8443 vinculado a 127.0.0.1; HTTP localhost:8000 redireciona. Certificado/chave fora do workspace com ACL restrita; certificado confiado apenas no usuário Windows após aprovação explícita e conferência SHA-256. Script `scripts/central-contas-local-https.ps1` preserva os overrides Docker. Certificado vence em 18/10/2027; requer renovação posterior.

Verificações realizadas em 18/10/2026:

- 81 verificações do piloto com OAuth e Gmail simulados, sem acesso a caixas reais; 122 verificações da fundação em bancos sintéticos isolados.
- 36 combinações visuais do piloto e 24 da fundação, claro/escuro e 1280/390/360, sem overflow. Capturas revisadas; revisão de envio bloqueada quando há alterações não salvas.
- PHP lint de 56 arquivos e Apache Syntax OK. Edge abriu HTTPS com validação normal do certificado, sem ignorar erros TLS. Cookie Secure observado.
- Rotas reais de visão geral, contas, caixa e dashboard/bases/módulos/projetos/configurações responderam 200 por HTTPS com sessão temporária descartável. Sem acesso anônimo à Central; ações rejeitaram GET e CSRF inválido; fontes/configurações privadas bloqueadas.
- Callback registrado no Apache sem query string; código sintético de teste ausente nos logs capturados. Ambiente local não tem proxy/túnel/CDN adicional; produção exigirá conferência própria. Sessão temporária removida.

Pendências e limites: cliente OAuth Web e consentimento do proprietário; geração/mount privado de secrets.json; aceite real de leitura, anexo, envio, resposta e entrega ao destinatário controlado. O Core legado continua com DDL/DML no bootstrap. Não há cron instalado, sincronização instantânea ou antivírus de anexos. A manutenção é manual; rascunhos não são apagados automaticamente. Os testes locais não comprovam funcionamento integral com a conta real nem segurança de produção.

Não houve alteração de produção, DNS, migração de e-mail, commit, push ou envio de mensagens reais. A Etapa 4 permanece aberta até o aceite real; não avançar para Outlook/Zoho ou produção por inferência desta entrega.

### Atualização: configuração privada do piloto

Em 18/10/2026, após o proprietário fornecer o nome do JSON Google salvo na pasta privada, foram validados o tipo Web, o callback local exato e a presença das credenciais, sem exibir seus valores. O utilitário de setup foi executado em simulação e aplicado em container temporário sem rede. secrets.json e chave aleatória foram gravados fora do workspace, com ACL restrita ao usuário Windows/SYSTEM. O app local foi recriado com mount de segredos somente leitura; o diretório de anexos foi validado pelo usuário www-data. Os demais valores do ENV local foram preservados ao configurar o piloto.

Logs do callback novamente conferidos sem código/state, TLS validado e página real de contas respondendo 200 com o botão Autorizar no Google disponível. Conferida ausência do segredo e das chaves no HTML; sessão descartável removida. Zero conexões reais no banco ao final desta validação. Aguarda consentimento do proprietário no navegador e aceite real. Não houve chamada ao Gmail, leitura, envio, deploy ou alteração de produção.

### Correção do consentimento OAuth Google

Corrigido o erro real `Conflict params: approval_prompt and prompt` relatado pelo proprietário. GenericProvider adicionava approval_prompt=auto por padrão; a chamada agora fornece approval_prompt=null, que é omitido da query, preservando prompt=consent. Não houve alteração em vendor ou nos escopos. Acrescentadas verificações da URL serializada para conflito de parâmetros, acesso offline, callback e escopos: 83 verificações do piloto aprovadas. A rota POST real também retornou 303 com somente prompt, PKCE S256, state e callback corretos; o redirecionamento não foi seguido e a sessão de teste foi removida. Consentimento e aceite real continuam pendentes do proprietário.

### Conexão Gmail autorizada pelo proprietário

Em 18/10/2026, o proprietário informou que concluiu o consentimento Google. Conferência somente no banco local confirmou account.connection_status=connected, connection.status=connected e presença do envelope de token criptografado, sem expor token ou consultar mensagens. O aceite real da Etapa 4 continua pendente de sincronização/leitura e teste controlado de envio, resposta e recebimento. Produção permanece inalterada.

### Leitura validada e rascunho de teste preparado

O proprietário confirmou sucesso na sincronização e leitura pela Central. Após informar um endereço Outlook sob seu controle, foi criado o rascunho local #1 com assunto Teste da Central de Contas — Core, sem Cc/Cco/anexos. O conteúdo foi relido pelo serviço e a revisão HTTPS respondeu 200. A contagem da outbox permaneceu igual; nenhum envio foi solicitado ou executado. Aguarda revisão/confirmação do proprietário no Core, recebimento no Outlook e resposta para validar a etapa funcional restante.

## 18. Aceite do fluxo principal do piloto Gmail

O proprietário confirmou em 18/10/2026 que a resposta chegou e o fluxo funcionou. Evidências: confirmação do proprietário para sincronização/leitura; outbox local #1 com status confirmed, attempts=1, failure_code nulo e identificador remoto presente; confirmação do proprietário para recebimento no Outlook e retorno ao Gmail pela Central. Atendido o critério principal de envio/recepção controlados da Etapa 4 para esse fluxo. Nenhum conteúdo da resposta foi consultado ou reproduzido pelo agente.

Escopo validado: Gmail piloto no Core e no MySQL principal local, HTTPS, OAuth e tokens protegidos, atualização manual, leitura, composição/revisão, envio e recebimento da resposta. Outlook foi apenas o destinatário controlado; seu conector ainda não existe.

Cobertura: 83 verificações sintéticas do piloto, 122 da fundação, 60 combinações de tela/tema/largura e validações HTTP/TLS descritas acima. Não confirmados em caixa real: anexos, resposta enviada pelo próprio Core, busca/paginação e ciclos de desconexão/reautorização; falhas de rede/token e isolamento permanecem com cobertura sintética. Os testes não são comprovação de segurança integral ou prontidão de produção.

Nesta atualização foram modificados somente este relatório e docs/central-de-contas-gmail-piloto.md. Implementação, nove tabelas do piloto e dependências da Etapa 4 constam na seção 17; nenhuma nova tabela ou alteração de código foi necessária para o resultado confirmado. Não houve deploy, commit, DNS, migração de caixas ou conexão Outlook/Zoho.

Riscos e dependências: Google em Testing pode exigir reautorização após a validade do refresh token; sincronização e manutenção são manuais; certificado local exige renovação; deploy atual ainda requer adequação, segredos próprios e validação no servidor. Não foi contratado serviço ou custo adicional pelo agente. A fase de hardening/produção e seus testes ampliados continuam pendentes.

Próxima etapa recomendada: Etapa 5, um provedor por vez; avaliar Zoho primeiro, preservando a preferência de deixar o Outlook pessoal de recuperação para a última conexão. Aguardar aprovação explícita antes de iniciar a Etapa 5, conforme o prompt mestre do proprietário.

## 19. Etapa 5 autorizada — Zoho e caixa unificada local

O proprietário aprovou iniciar pelo Zoho e informou a caixa moises.cavalcante@meuprojetoweb.com e o webmail mail.zoho.com. A região US foi escolhida com base no webmail, conforme a documentação oficial do provedor. O Gmail permanece conectado; Outlook pessoal de recuperação continua reservado para depois.

Implementado neste incremento:

- Adaptador Zoho com OAuth Server-based/PKCE S256, state vinculado a proprietário/conta/provedor, callback próprio e servidores regionais fixos. Escopos mínimos accounts.READ, folders.READ, messages.READ e messages.CREATE. Nenhum segredo ou token em query de chamadas ao provedor; hosts, IDs e identidade principal são validados.
- Contrato existente de metadados preservado por adaptação. Zoho usa polling paginado da janela recente; não se inventa um histórico incremental equivalente ao Gmail. Anexos recebidos binários passam pelo limite/download seguro; envio usa JSON e upload privado, com origem fixa e Reply-To no preparo da resposta. Aliases não são habilitados automaticamente.
- Runtime vinculado à conta do recurso em leitura, anexos, rascunhos, envio, resposta, sincronização e autorização. Trocar um ID não permite usar o adaptador ou o token da outra caixa. SMTP transacional do Core não foi modificado.
- Caixa unificada dos metadados de Gmail/Zoho ativos e conectados, origem explícita em cada linha, pesquisa local via POST/CSRF e paginação. Composição nova continua exigindo escolher uma caixa. Mensagens com identificadores remotos iguais em contas diferentes não são fundidas.
- Snapshot criptografado, confirmação, limite de envios e idempotência preservados. No Zoho, resultado incerto sem ID não é associado por heurística nem reenviado automaticamente. Estado confirmado depende de consulta à pasta Enviados quando há ID conhecido.
- Provisionamento Zoho com simulação, backup privado, preservação de Google/chaves e recusa de sobrescrita. Configuração ausente mantém a conexão Zoho bloqueada. Callback Apache protegido contra registro de code/state; regra aplicada e conferida no app Docker local.

Arquivos novos: app/services/mail/MailZohoConfig.php; app/services/mail/providers/ZohoMailProvider.php; app/console/mail_center_zoho_setup.php; web/admin/central_contas/oauth_zoho_callback.php; web/admin/central_contas/unified.php; env/mail-center.zoho-client.example.json; scripts/tests/mail_center_zoho_test.php; docs/central-de-contas-zoho-local.md.

Arquivos adaptados: MailHttpClient, MailMessageService, MailOAuthService, MailPilotConfig, MailProviderRegistry, MailSyncService, MailSendService e texto de MailAttachmentService; helpers mail_center.php/mail_pilot.php; ações connect/disconnect/sync/draft_save/send/draft_attachment_remove; telas index/accounts/compose/messages/message/message_body/attachment/oauth_callback e parciais _connection/_nav; CLI mail_center_sync.php; docker/php/mail-center.conf; testes mail_center_pilot_test.php, mail_center_pilot_views.php e mail_center_pilot_ui.cjs; este relatório. Não houve nova migração: zero tabelas adicionais. Somente cadastro organizacional da caixa Zoho e categoria MeuProjetoWeb foram adicionados aos registros locais existentes.

Resultados: 125 verificações isoladas do piloto, incluindo 40 Zoho, e 122 da fundação aprovadas. PHP lint de 56 arquivos e Apache Syntax OK. Playwright/Edge: 48 combinações do piloto (incluindo Zoho/unificada) e 24 da fundação; claro/escuro, 1280/390/360; capturas revisadas sem overflow. Produção não foi usada para testar.

Validações HTTPS no ambiente real local: overview, contas, caixa Gmail, unificada e dashboard 200; sessão temporária removida; acesso anônimo à unificada e callback 404; callback registrado sem parâmetros e marcador sintético ausente no log. O envelope do token Gmail não foi alterado durante essas verificações. Não houve acesso a corpo de mensagem real, chamada ao Zoho ou novo envio.

Limites e pendências: cliente OAuth Zoho, consentimento e teste real de conta/plano/API, leitura, busca, anexos, envio/resposta e recebimento. Polling não detecta imediatamente todas as exclusões/movimentos; cache não é espelho integral do provedor. Operações remotas de arquivamento/leitura/labels e outros provedores ainda não foram habilitados. Sem novo serviço, biblioteca, cron, worker, custo contratado, DNS, migração de caixa, commit, push ou deploy. O pipeline de produção permanece pendente de preparação em fase própria.

A Etapa 5 permanece em andamento; não iniciar tarefas ou produção. Próxima ação do proprietário: criar cliente Server-based no Zoho API Console e salvar o JSON privado conforme docs/central-de-contas-zoho-local.md. Após fornecer apenas o nome do arquivo, provisionar sem exibir valores e conduzir o aceite real, mantendo o Gmail e suas chaves intactos.

### Atualização: cliente Zoho privado provisionado

O proprietário confirmou o arquivo zoho-client.json na pasta privada externa. JSON, Client ID e presença do segredo foram validados sem exibir valores. Provisionamento em simulação e aplicação sem rede, preservando configuração Google/chaves/diretório de anexos e criando backup privado. O app local foi reconstruído para persistir a regra de logs e montar o segredo somente leitura.

Detectada falta de leitura pelo usuário PHP após a substituição atômica do arquivo. Ajustado o arquivo privado para proprietário/grupo www-data (33:33), modo 0600 e ACL Windows preservada. Corrigido app/console/mail_center_zoho_setup.php para preservar UID/GID anteriores e acrescentado teste de regressão em scripts/tests/mail_center_zoho_test.php. Sem chmod público, nova chave ou alteração de token Gmail.

Validações reais locais: runtime como www-data disponível; página de contas HTTPS 200 com botão Autorizar no Zoho e sem segredos no HTML; ação OAuth 303 com callback/servidor/scopes/PKCE corretos, sem seguir ao provedor; caixa unificada 200; callback protegido nos logs após rebuild. Sessão temporária removida. Gmail permanece connected; Zoho not_connected, aguardando consentimento do proprietário. Sem acesso à caixa real, envio, produção ou nova tabela.

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

## 20. Outlook pessoal preparado e Central com navegação própria — 18/10/2026

O proprietário aprovou concluir o terceiro provedor e simplificar a navegação: Central em aba própria, menu à direita com Todos os e-mails e cada caixa individual, links para abrir caixas em novas abas e menu recolhível no celular. O menu principal do Core agora abre a Central em outra aba; o restante do Core preserva seu layout. As telas privadas continuam usando o proprietário autorizado, sessão Core, CSRF e cabeçalhos no-store.

Outlook implementado para conta pessoal via Microsoft Graph /me e OAuth consumers, código de autorização com PKCE/state, callback próprio e correspondência exata de endereço. Escopos delegados offline_access, User.Read, Mail.Read e Mail.Send. Não usa permissões de aplicativo, Mail.ReadWrite, credenciais de SMTP nem senha da caixa. Tokens criptografados reutilizam o cofre local e refresh com bloqueio por conta.

Adaptador fornece perfil, pastas principais, polling paginado de 30 dias, busca remota, leitura sob demanda, Reply-To/referências RFC e anexos de arquivo dentro do limite local. IDs base64 do Graph preservam maiúsculas, barras, sinal de soma e preenchimento; requisições pedem ImmutableId. Links opacos de paginação só são aceitos com HTTPS no host oficial, sem credenciais/fragmento/porta e no mesmo caminho /v1.0/me da coleção solicitada. Mensagens com IDs iguais em caixas diferentes permanecem isoladas.

Envio MIME conserva destinatários/Cc/Bcc/anexos, remetente fixo e snapshot criptografado. Graph 202 sem ID produz estado accepted, sem confundir com entrega; confirmação exige uma única cópia em Sent Items com internetMessageId exato. Resultados incertos e aceitos não são reenviados automaticamente. Microsoft pode reescrever o Message-ID ou indexar com atraso, mantendo accepted mesmo se houve entrega. Desconexão apaga tokens locais; o proprietário deve remover consentimento nas configurações Microsoft para revogar no provedor, sem revogação global de sessões pelo Core.

Provisionador Outlook simula antes da aplicação, recusa sobrescrever configuração Outlook existente, preserva integralmente Gmail/Zoho/chaves/anexos, cria backup privado e conserva proprietário/grupo Unix e modo 0600. Exemplo JSON não contém valores reais. O proprietário informou não ter aplicativo registrado; nenhum segredo Microsoft foi provisionado e nenhum consentimento/contato real com Graph foi feito. Cadastro organizacional local moisescavalcante@outlook.com.br criado, com conexão pendente. Guia detalhado em docs/central-de-contas-outlook-local.md.

Novos arquivos: MailOutlookConfig.php, providers/OutlookMailProvider.php, app/console/mail_center_outlook_setup.php, web/admin/central_contas/oauth_outlook_callback.php, app/views/layout_mail.php, scripts/tests/mail_center_outlook_test.php, env/mail-center.outlook-client.example.json e guia Outlook. Adaptados OAuth/config/runtime/registro/HTTP/metadados/envio, telas/partial de conexão/unificada/messages/nav, layout_admin/sidebar, CSS, proteção Apache dos callbacks e fixtures/testes de piloto. Nenhuma tabela ou dependência nova.

Validação: 187 verificações sintéticas do piloto (40 específicas Outlook), 122 de fundação e 54 combinações visuais do piloto no Edge (9 telas x 2 temas x 3 larguras), com inspeção das capturas de desktop escuro e celular claro. Cobertura inclui escopos excessivos/identidade divergente recusados, refresh, isolamento entre provedores, paginação host/caminho protegidos, MIME/anexos/Reply-To, estado accepted, confirmação de cópia, timeout sem reenvio, preservação de segredos e permissões de arquivo. Foi corrigido, antes da validação final, um erro na rotina local de edição em lote e o comportamento do componente details em desktop; os arquivos foram recompostos e a suíte/regressões e páginas reais passaram.

Docker app reconstruído para proteger callback Outlook contra registro de query. Verificação HTTPS real com sessão temporária descartada: contas, unificada, Gmail e Zoho 200/no-store e layout próprio; callback Outlook anônimo 404 com caminho registrado e marcador de code/state ausente dos logs. Impressões dos envelopes de token existentes comparadas apenas em memória, sem mudança. Nenhum e-mail novo enviado, senha guardada, produção acessada, deploy, commit, DNS ou cron alterado.

Etapa 5 continua em andamento, aguardando cadastro Microsoft, arquivo outlook-client.json privado, provisionamento e consentimento/teste real pelo proprietário. Sem iniciar tarefas ou produção. Limites documentados: polling manual, pastas principais no seletor, ausência de modificações remotas, tipos/tamanhos de anexos limitados e possível confirmação de envio pendente no Graph.

## 21. Preferência corrigida: Core preservado e caixas em sanfona — 18/10/2026

O proprietário adiou o Outlook após dificuldades de acesso ao diretório Microsoft e corrigiu a preferência de interface: Central de Contas na aba e nos menus normais do Core; organização em sanfona apenas ao acessar as caixas. A imagem fornecida foi usada como referência da navegação de contas/pastas e leitura rápida, sem copiar dados privados para fixtures.

Removida a seleção global de layout independente e o target blank do item Central no Core. A navegação anterior Visão geral, Contas e categorias, Caixas de e-mail e Todos os e-mails foi restaurada. layout_mail.php e estilos da área independente foram removidos. Nas telas de caixa/unificada/leitura/composição, uma árvore lateral própria contém Todos os e-mails e uma sanfona por conta ativa/conectada, com Todos desta conta e pastas/marcadores locais. Contadores indicam registros sincronizados disponíveis, não total remoto nem quantidade de não lidos. Selecionar uma caixa/pasta pela árvore limpa a busca anterior para evitar filtros ocultos. Outlook pendente não aparece como caixa conectada; o cadastro organizacional e o código já preparado foram preservados para retomada posterior.

Caixa individual e unificada ganharam painel de leitura por clique, sem navegar para fora da listagem. A prévia usa o mesmo controle de proprietário/conta, montagem escapada de metadados/texto, download seguro de anexos e iframe HTML protegido já existentes. Nenhum corpo é persistido por essa função. Responder e abrir mensagem completa continuam disponíveis; links normais funcionam sem JavaScript. Requisições de prévia anteriores são canceladas ao trocar de mensagem; falha mostra acesso à leitura completa, sem envio/reenvio ou exibição de erros sensíveis. O Core preserva cabeçalho e menu principal; no celular os painéis se empilham e a seleção conduz à leitura.

Arquivos principais: layout_admin.php, partials/sidebar.php e novo partials/mail_folders.php; _nav.php, messages.php, unified.php, message.php e novo _message_content.php; CSS/JS central_contas; fixtures/testes de visualização. Nenhuma alteração de banco, credenciais, permissões OAuth ou sincronização remota. Nenhuma ação no Azure, compra, cartão, envio, deploy ou migração de caixa.

Validação: 190 verificações do piloto e 122 da fundação aprovadas; 54 combinações visuais do piloto, 24 de organização e 4 de leitura interativa. As verificações interativas cobrem abrir/fechar sanfona, preservação dos menus Core, clique que mantém a URL da caixa, conteúdo sintético da prévia, resposta, destaque da seleção e falha sem tentativas automáticas. Capturas de leitura desktop claro e celular escuro revisadas. Prévia isolada retorna somente o artigo, sem duplicar layout. HTTPS real com sessão temporária descartada: cinco páginas 200/no-store, menus Core e sanfona presentes, prévia anônima 404; envelopes de tokens Gmail/Zoho comparados em memória e preservados. Nenhuma mensagem real foi lida para gerar as capturas e nenhuma chamada aos provedores foi feita na conferência HTTP.

Etapa 5 permanece aberta, com Outlook adiado por decisão do proprietário. Não iniciar configuração Microsoft adicional, tarefas ou produção sem nova orientação.

## 22. Central de E-mail compacta — 18/10/2026

O proprietário solicitou reduzir a poluição visual com base nas capturas do Core e Outlook: renomear a entrada para Central de E-mail, abrir Todos os e-mails por padrão, recolher o menu Core apenas nesta área, usar seletor de conta, busca única e mensagens em linhas compactas; configurações no rodapé do menu de e-mail. Também perguntou sobre favoritos e renomear pastas; essa parte foi tratada como proposta, sem habilitar operações remotas ou novas permissões.

Implementado: entrada do Core aponta para unified.php; barra lateral Core fica em ícones no desktop e pode expandir pelo botão do cabeçalho. Estilos e estado são restritos às páginas privadas de e-mail e não persistem para outras áreas. CSS de e-mail carrega no head para estabilizar o layout. O menu de e-mail tem marca, seletor Todos/caixa, composição com escolha explícita de remetente quando necessário, sanfonas de pastas e rodapé com atualização, rascunhos, envios locais e Configurações. No celular as sanfonas iniciam recolhidas. Detalhes continuam nas telas de configuração existentes.

Removidos da listagem o título/subtítulo, navegação repetida, cartão da conta, avisos explicativos, seleção de modalidade de busca e cartões de rascunhos/envios. Caixa individual e unificada usam o mesmo parcial _inbox.php: um campo de busca, lista De/Assunto/Recebido com elipse e indicação visual de não lida, paginação e leitor inicialmente oculto. Selecionar uma mensagem abre a leitura na mesma página; Voltar à lista restaura a largura. No celular a leitura substitui visualmente a lista até voltar. Recursos de responder, composição, anexos e estados de envio foram preservados. JavaScript agora carrega uma única vez pelo layout, inclusive para o seletor funcionar na leitura completa e na composição.

Busca normal é local, por assunto/remetente (unificada inclui conta), dentro dos metadados sincronizados; o cadastro de conta e o cache não foram alterados. Uma conta selecionada restringe a consulta por account_id/proprietário; Todos consulta apenas caixas ativas e conectadas do mesmo proprietário. Não houve mudança para busca em corpos, sincronização integral ou pesquisa remota automática. Teste adicional comprova isolamento da busca Gmail/Zoho e abrangência da unificada.

Proposta de organização para discussão futura: favoritos de mensagens/pastas e apelidos de pastas guardados somente na Central, sem mudar os nomes originais. Favoritar, renomear ou mover no próprio Gmail/Zoho exige avaliar as APIs e escopos de escrita de cada provedor. Nenhum desses recursos foi apresentado como já habilitado; zero tabelas ou escopos novos neste incremento.

Arquivos principais: layout_admin.php, partials/header.php/sidebar.php/mail_folders.php, novo web/admin/central_contas/_inbox.php, messages.php, unified.php, compose.php, _nav.php e nome em index.php; CSS/JS central_contas; testes visuais/de leitura e regressão Zoho de busca. Rotas, migrações e contratos de provedor preservados. Outlook continua adiado.

Resultados: 191 verificações do piloto, 122 da fundação, 54 combinações visuais do piloto, 24 de configuração e 4 de leitura interativa. Testados recolher/expandir Core, sanfonas, busca única, configurações, leitura/retorno sem sair da URL da caixa, falha da prévia, seletor e confirmação de envio com alterações não salvas. Captura desktop escura da lista compacta inspecionada. Uma expressão inválida de classe no layout foi corrigida antes das verificações finais. HTTPS real: cinco páginas 200/no-store, seletor/busca/rodapé e JS único; prévia anônima 404; tokens Gmail/Zoho preservados, sem consultar corpos reais ou chamar provedores na verificação. Sessão de teste descartada. Sem novos envios, alteração de pastas remotas, produção, DNS, compra ou deploy.

## 23. Pastas principais unificadas em Todos os e-mails — 18/10/2026

O proprietário pediu remover os grupos por conta do menu de Todos os e-mails e usar somente pastas principais comuns. Implementado menu Todos os e-mails, Caixa de entrada, Enviados, Rascunhos, Spam e Lixeira. Gmail/Zoho deixam de aparecer como sanfonas nessa visão; continuam acessíveis no seletor, com as pastas individuais preservadas.

O filtro unificado agrupa metadados já sincronizados por pasta: IDs de sistema explícitos do Gmail e nomes padrão conhecidos em inglês/português das pastas Zoho/Outlook. Pastas personalizadas desconhecidas continuam em Todos ou na conta individual. Isso não renomeia, move ou mescla pastas no provedor. Busca e paginação preservam a pasta escolhida. Pastas sem mensagens sincronizadas mostram estado vazio; não recorrem a Todos. Rascunhos desta visão correspondem a mensagens da pasta remota sincronizada; rascunhos locais de composição continuam acessíveis na conta individual, como antes.

Filtragem SQL mantém proprietário/contas ativas e conectadas, usa parâmetros e EXISTS para impedir duplicação quando uma mensagem tem múltiplas associações. IDs remotos iguais em contas distintas permanecem isolados. Parâmetros de pasta desconhecidos são recusados sem expor detalhes. Arquivos: helper mail_center.php, MailMessageService.php, partial mail_folders.php, unified.php e _inbox.php, regressões Zoho e verificações visuais do piloto. Sem tabelas, permissões ou consultas remotas novas.

Validação: 203 verificações do piloto aprovadas e 66 combinações visuais; inclui grupos Entrada de Gmail/Zoho, busca dentro do grupo, Enviados/Rascunhos/Spam/Lixeira, grupo inexistente recusado, isolamento por proprietário, múltiplas associações sem duplicar mensagem e marcador Gmail personalizado chamado Inbox sem ser confundido com INBOX. Captura de Entrada unificada em desktop escuro inspecionada. Consulta ao cache local real confirmou Entrada paginada e dois Enviados; demais grupos vazios no cache atual. Nenhum conteúdo, token ou resposta bruta exibido, nenhum acesso a provedor, envio, renomeação ou deploy. Outlook permanece adiado.

## 24. Função do e-mail e identificação discreta por cor — 18/10/2026

O proprietário definiu Categoria como a função de uso do endereço e pediu aproveitar a cor escolhida para separar visualmente as contas na lista. Nomenclatura da interface alterada para Contas e funções, Função do e-mail e Funções do e-mail; o campo descritivo anterior Finalidade agora aparece como Observações de uso. Mensagens de confirmação/validação acompanham a nomenclatura. Dados, IDs, tabelas e rotas category existentes foram preservados, sem migração ou reclassificação automática.

Consultas individual/unificada agora trazem account_color/account_function a partir da função vinculada à conta, por LEFT JOIN com proprietário validado. Isso reflete alterações de cor imediatamente, sem sincronizar novamente mensagens ou mudar tokens. A lista mostra faixa lateral de 3 px e tonalidade de fundo de 5%; seleção mantém a faixa de identificação e adiciona contorno. Texto e endereço de origem permanecem legíveis. Cor também aparece discretamente nos cartões de configuração e na sanfona da conta individual. Uma função compartilhada implica uma cor compartilhada; uma conta sem função usa tom neutro. Tooltip do endereço identifica a função sem acrescentar texto à lista.

Helper de cor aceita somente hexadecimal de seis dígitos e aplica fallback neutro a valor ausente/inválido; não aceita conteúdo CSS arbitrário. Não foi editada nenhuma função, cor ou associação do banco real; valores existentes são reutilizados. A escolha/edição permanece nas configurações do proprietário.

Arquivos principais: helpers/mail_center.php, MailMessageService.php, MailAccountService.php (somente mensagens de UI), category_save.php (confirmação), accounts.php/index.php/_nav.php/_inbox.php, partial mail_folders.php, CSS e testes de piloto/Zoho/configuração. Sem alteração de e-mails, pastas, permissões OAuth, credenciais, produção ou comportamento de envio.

Resultados: 207 verificações do piloto e 122 da fundação aprovadas; 66 combinações visuais do piloto e 24 de configuração aprovadas. Novas verificações cobrem cores/funções distintas Gmail/Zoho, conta individual, mudança de cor sem ressincronizar mensagens e rejeição de conteúdo CSS. Capturas com azul/verde na lista unificada escura e formulário de funções no celular claro inspecionadas. Fixtures contêm somente dados sintéticos. Nenhuma caixa real acessada ou novo envio efetuado.

## 25. Sincronizar todas as caixas com um clique — 18/10/2026

O proprietário pediu um botão em Todos os e-mails para atualizar as caixas juntas. Adicionado Sincronizar todos no rodapé do menu unificado, inclusive ao filtrar uma pasta principal. A atualização individual foi preservada. O botão envia POST com CSRF e mantém a pasta selecionada após retornar.

Novo MailSyncAllService seleciona somente contas do proprietário, ativas e conectadas, com provedor habilitado e token presente; executa um lote limitado do sincronizador existente em cada conta. Cada runtime permanece vinculado à respectiva conta, com seus limites, cursor, lease e renovação de token. Falha em uma não interrompe as demais. Lock MySQL por proprietário impede operações coletivas concorrentes. Não há envio, repetição automática, cron ou sincronização contínua. A interface desabilita o botão e mostra Sincronizando enquanto aguarda; ao voltar pelo navegador, restaura o controle.

O resumo informa caixas atualizadas, falhas e existência de mensagens pendentes. Um clique atualiza um lote de todas as caixas elegíveis, sem prometer esgotar o histórico numa única requisição. Se houver pendências, o proprietário pode clicar novamente para continuar os lotes. Outlook permanece desconectado/adiado e não entra na execução.

Arquivos: novo app/services/mail/MailSyncAllService.php, nova ação app/actions/central_contas/sync_all.php, carregador mail_pilot.php, partial mail_folders.php, JS, novo scripts/tests/mail_center_sync_all_test.php e ajustes dos testes de Zoho/UI. Sem migração, novas bibliotecas ou alteração de permissões.

Validação: 216 verificações sintéticas do piloto aprovadas, incluindo 9 novas de execução coletiva, falha parcial, conta desativada, reautorização, isolamento de proprietário, zero contas elegíveis, bloqueio concorrente e proprietário inválido. 66 combinações visuais passaram. HTTPS real com sessão temporária descartada confirmou botão/pasta mantidos e bloqueios GET 405, CSRF inválido 403 e anônimo 404, sem disparar sincronização nesses testes HTTP.

Um lote real coletivo autorizado foi executado como www-data: 2 caixas elegíveis, 2 atualizadas, 0 falhas, 8 registros processados e 1 caixa com continuação pendente. Nenhum conteúdo/token foi exibido ou e-mail enviado. Scripts temporários removidos. Sem produção, deploy, tarefas automáticas ou acesso Microsoft adicional.

## 26. Organização local de mensagens e preparação do servidor — 18/10/2026

Implementados Favoritos, Sinalizados e Fixados, com filtros por conta ou em Todos os e-mails; fixados têm prioridade dentro do escopo de busca/pasta. Pastas individuais secundárias ficam recolhidas em Outras pastas. A sinalização usa cor escolhida em Configurações, preservando a faixa de identificação da conta e o visual existente.

A migração 003_organization cria core_mail_message_marks e core_mail_preferences e foi aplicada somente no Docker local. O comando de migração aceita --organization, com prévia, checksum e aplicação idempotente; o diagnóstico também verifica essa etapa. Marcações são locais e não são sobrescritas pela sincronização. A manutenção preserva metadados marcados, sem impedir exclusões realizadas no provedor.

Arquivos principais: MailLocalOrganizationService.php, MailMessageService.php, MailSchemaMigration.php, MailAuditService.php, helpers mail_center/mail_pilot, ações message_mark.php e preferences_save.php, controladores/listagem/sidebar/configurações, CSS/JS e comandos de manutenção/diagnóstico/migração. As ações usam POST, CSRF, campos permitidos e verificação de proprietário/conta ativa; definir um estado é idempotente. Nenhuma nova permissão OAuth ou operação remota foi adicionada.

Validação: 258 verificações do piloto e 122 da fundação aprovadas; 66 combinações de interface do piloto, 24 de configurações, quatro de leitura e quatro de marcações passaram. Novos testes cobrem isolamento, estados independentes, ordenação, preservação após sincronização, retenção, validação de cor, falha de requisição, filtros e rotas protegidas. Capturas de desktop escuro e configurações no celular claro inspecionadas.

HTTPS local confirmou filtros/configurações e pastas extras sem avisos PHP; ações recusaram GET (405), CSRF inválido (403), anônimo e mensagem inexistente (404). Sessão temporária descartada. Tokens e quantidade de marcas reais preservados; nenhuma preferência ou marca real foi modificada pelos testes. Sem chamadas aos provedores, envio, deploy ou acesso adicional ao Microsoft Entra.

A ordem de preparação, limitações atuais e recursos remotos adiados estão em [Central de E-mail — pendências para o servidor](central-de-email-pendencias-servidor.md). O deploy existente ainda requer adaptação para dependências e migrações da Central antes de ser utilizado para publicá-la.

## 27. Preferência de leitura e navegação no topo — 18/10/2026

Adicionada escolha em Configurações → Leitura e destaque: abrir mensagens no painel ao lado da lista (padrão) ou na página completa. Preferência por proprietário, persistida em core_mail_preferences.reading_mode pela nova migração 004_reading. Migração aplicada somente no Docker local; --organization passa a incluir e conferir 004, sem alterar checksums de migrações anteriores. A configuração privada, tokens e permissões OAuth permanecem os mesmos.

No modo completo, o clique segue o link normal da mensagem, sem buscar prévia. No painel, Abrir mensagem completa continua disponível. Proteções do corpo HTML e bloqueio de imagens externas permanecem. A preferência controla o modo de leitura da mensagem, sem liberar conteúdo externo.

Anterior/Próxima foram movidos do rodapé da lista para uma barra no topo, junto de Voltar à lista quando o painel está aberto. Continuam sendo navegação entre páginas da lista. Fechar o leitor restaura a lista e o foco; os controles funcionam em celular.

Validação: testes sintéticos cobrem persistência, isolamento por proprietário, modo inválido e preservação do modo ao salvar somente a cor. O navegador passou oito combinações (painel/completo, claro/escuro, desktop/celular), com ausência de requisição de prévia no modo completo; quatro combinações de marcações/configurações também passaram. Captura de desktop com Voltar à lista e Próxima adjacentes inspecionada. Diagnóstico local confirmou checksums 001–004 e configuração disponível. Nenhum acesso aos provedores ou deploy nesta etapa.

## 28. Menu direto da caixa individual — 18/10/2026

O seletor de caixa passa a ser a identificação única da conta na navegação individual. Removidos o título repetido do provedor/endereço, a sanfona da conta e o link duplicado Todos desta conta. Todos os e-mails aponta para a conta selecionada; favoritos, sinalizados e fixados continuam no mesmo escopo. A opção Todos os e-mails do seletor ainda abre a visão unificada, preservada.

Pastas principais aparecem diretamente abaixo das marcações, nesta ordem: Caixa de entrada, Enviados, Rascunhos, Spam e Lixeira, conforme as pastas sincronizadas disponíveis. Nomes de sistema recebem rótulos em português somente na interface; IDs, contagens e pastas no provedor permanecem iguais. Outras pastas continua recolhível. Sem migração ou chamadas remotas.

Validação: 267 verificações sintéticas do piloto e 66 combinações visuais aprovadas, incluindo link Todos restrito à conta, ausência das duplicações, tradução/posição de Entrada e preservação da visão unificada. Captura da conta individual escura inspecionada.

## 29. Ícones compactos e correção de cabeçalhos UTF-8 — 18/10/2026

A lista reserva uma coluna à direita para os três ícones na mesma linha e a data logo abaixo. Remetente e assunto usam reticências dentro da área disponível, inclusive no painel estreito de leitura; controles não são cobertos por assuntos longos. Validado nos temas claro/escuro e no celular.

Reproduzida uma falha real de decodificação: mb_decode_mimeheader aplicado ao cabeçalho inteiro substituía bytes de textos já decodificados em UTF-8 por interrogações. MailMessageService.decodeHeader agora decodifica somente palavras MIME RFC 2047, preserva trechos Unicode e restaura a configuração global de encoding. Aplicado à sincronização e ao assunto preparado para resposta. Não há substituição heurística de interrogações nem reescrita de mensagens remotas.

Novo refreshHeaders valida proprietário, conta e ID remoto e atualiza somente assunto/remetente/destinatários em cache, preservando pastas, marcas, cursores e estado de envio. A leitura aproveita o cabeçalho já obtido para reparar o cache da mensagem aberta. Novo comando CLI repair_mail_headers.php oferece prévia sem acesso remoto e aplicação em lotes limitados, com prazo e cursor; saídas contêm apenas contagens e não registram conteúdo, endereços, credenciais ou respostas brutas.

Validação: 279 verificações sintéticas do piloto aprovadas, incluindo UTF-8 direto, emoji, japonês, MIME base64/quoted-printable latino, cabeçalhos mistos/adjacentes, encoding global, isolamento e preservação de marcações. Oito combinações do leitor e quatro de marcações passaram. Teste visual mede ícones na mesma linha, data abaixo e assunto longo limitado; captura inspecionada.

Executada recuperação real autorizada somente de metadados: 35 registros relidos nos provedores conectados, zero indisponíveis. A prévia posterior identificou quatro registros que ainda contêm interrogações nos originais recuperados; essa presença também pode ser pontuação legítima. Não foram inventados caracteres, alterados corpos ou enviados e-mails. Conteúdo já corrompido no original remoto não pode ser reconstruído com segurança por esse procedimento. Sem deploy ou novas permissões.

## 30. Painel unificado fixo, próxima mensagem e navegação simplificada — 18/10/2026

Todos os e-mails agora usa sempre o painel lado a lado, independentemente da preferência salva. Links carregam o contexto unificado, a prévia omite Abrir mensagem completa e a falha da leitura não oferece saída para página completa. A configuração do modo de leitura foi rotulada explicitamente para caixas individuais, preservando a escolha já salva.

A leitura completa individual perdeu o menu Resumo/Contas e funções/Abrir uma caixa/Todos os e-mails e o botão redundante de abrir a própria mensagem completa. No topo ficam Voltar à caixa e Próxima mensagem. A próxima é consultada somente no cache da mesma conta e proprietário, na ordem fixados/data/id, com os filtros de pasta, marcação e busca local vigente. Não atravessa contas ou reinicia no fim; o botão fica desativado quando não há sucessora. Os links preservam pasta/marcação e a busca continua em sessão. O seletor e o menu lateral permanecem disponíveis.

Nas configurações e demais telas que usam a navegação compartilhada, Resumo e Contas e funções permanecem; Abrir uma caixa e Todos os e-mails foram substituídos por Voltar para Central de E-mail.

Validação: 298 verificações sintéticas do piloto e 122 da fundação aprovadas. Cobertura inclui ordem da próxima, filtros, isolamento, referência correta do link, preferência individual completa e unificada forçada a painel, ausência de menus/botões repetidos. Doze combinações do leitor, 66 da interface do piloto e 24 de configurações passaram; captura da leitura completa individual inspecionada. Nenhuma migração, mudança de credenciais, consulta a provedores reais, envio ou deploy nesta etapa.

## 31. Preparação de produção, backup e ensaio — 19/10/2026

Proprietário confirmou o servidor 157.230.214.72; o alias SSH meuprojetoweb aponta para outro host e não foi usado. Autenticação por senha feita diretamente em terminal visível; senha não armazenada. Probe independente enviado em memória por SSH, sem bootstrap/uploads, confirmou PHP 8.3.35/extensões, MySQL 8.0.46, utf8mb4, URL canônica meuprojetoweb.com e administrador ativo moises.cavalcante@meuprojetoweb.com. Código/vendor/schema da Central e redação de callback ainda ausentes na inspeção inicial. Não reutilizar owner ID local.

Adaptado o empacotamento para instalar 17 dependências congeladas sem dev/plugins/scripts, validar Composer/lock/plataforma, gerar manifesto de hashes e incluir migrações 001–004. Composer preparado em diretório ignorado após validar assinatura SHA-384 do instalador oficial; auditoria não retornou avisos nesta execução. O pipeline prepara preflight antes da substituição, cópia de vendor/manifests, migrações explícitas e override privado de produção se provisionado. Publicação real requer flag específica, backup e revisão; só PackageOnly foi executado nesta etapa. Passos preexistentes do deploy geral/DDL do bootstrap permanecem e entram na revisão final.

Novos inspect_mail_server.php e check_mail_release.php independem do bootstrap. Verificação de release aprovada no runtime construído do Dockerfile; Apache com sintaxe válida. Oito regressões locais recusam lock alterado, dev, versão divergente, migração ausente e ENV dentro do pacote. Chave nova de produção e clientes existentes Google/Zoho preparados em pasta externa com ACL usuário/SYSTEM, sem copiar tokens ou enviar segredos ao servidor; configuração validada em container sem rede, montada somente leitura.

Backup protegido do Core criado em /opt/backups/central-email/before-mail-20261019T172016Z-nVZUrK; gzip/tar/checksums conferidos. Dump restaurado em MySQL temporário isolado (sem rede externa), e migrações 001–004 aplicadas e repetidas com preservação de usuários/projetos. Processo PHP de ensaio verifica hostname exclusivo e não usa configuração de provedores. Container e cópia local protegida removidos após sucesso. Falhas iniciais de transporte/default PowerShell e argumento do teste de prontidão foram corrigidas sem alterações no banco de produção.

O relatório operacional e a sequência restante estão em docs/central-de-email-preparacao-producao.md. Ainda pendentes: callbacks nos provedores, provisionamento privado/mounts, HTTPS/proxies e redação em todas as camadas, revisão/registro da release e publicação autorizada. Nenhum deploy, migração em produção, consentimento de produção ou envio de e-mail nesta etapa; única escrita remota foi o backup protegido.

## 32. Provisionamento privado e inspeção HTTPS — 19/10/2026

Provisionados por SSH os segredos preparados de produção em /opt/private-mail-center, com hash conferido, arquivo 0600 pertencente ao usuário PHP e anexos 0700. O proprietário foi resolvido no banco de produção e um fragmento de configuração foi gerado com módulo desabilitado. ENV ativo e containers permaneceram inalterados. Teste isolado do provisionador aprovou hash, permissões, repetição idempotente e recusa de sobrescrita divergente; nenhum token local foi transferido.

Inspeção de portas e serviços identificou origem somente HTTP; proprietário confirmou Cloudflare Flexível. Preparados exemplos inativos de VirtualHost SSL/Compose e chave privada externa com CSR público para o domínio canônico. Assinatura do CSR e sintaxe Apache dos exemplos verificadas isoladamente. Ainda não houve emissão/instalação de certificado, abertura da porta 443, reinício, alteração de modo Cloudflare ou deploy. Callbacks Google/Zoho continuam aguardando confirmação do cadastro pelo proprietário. Backup deve ser atualizado para incluir a pasta privada e o pacote reconstruído antes da publicação.

## 33. Certificado, ativação TLS e regra Cloudflare — 19/10/2026

Proprietário confirmou os callbacks Google/Zoho e emitiu Origin CA usando CSR próprio. Certificado público salvo fora do workspace; chave não exposta. Assinatura conferida com CA oficial, hostname e correspondência da chave aprovados; validade até 15/10/2041. Handshake Apache isolado sem rede aprovado. Transferência SSH privada versionada conferiu hash, assinatura e chave novamente no servidor, sem ativar serviços nessa preparação.

Após autorização explícita, procedimento validou Apache na imagem exata de produção em container isolado, preservou snapshots privados para reversão e recriou somente app sem build. Porta 443 ativada por override externo; imagem, banco e ENV preservados. HTTP, HTTPS direto e acesso público responderam 200. Teste simulado prévio cobriu sucesso, recusa de repetição e reversão por falha de saúde. Deploy local passou a incluir o override externo se existir, para preservar TLS em publicação futura. Relatórios locais não contêm chaves, cookies ou conteúdo de mensagens.

Proprietário confirmou a regra Full (strict) restrita a meuprojetoweb.com na Cloudflare. Conferência externa posterior aprovou login público 200, inicial 200 após um redirecionamento e origem direta 200, com TLS verificado. A regra não foi inspecionada por API; sua configuração é informação confirmada pelo proprietário. Central permanece desabilitada; não houve deploy da aplicação, migrações ou envios.

Teste posterior com marcadores fictícios confirmou registro do controle e dos caminhos OAuth Google/Zoho sem suas consultas no Apache. Política de logs CDN/WAF segue não verificada. Cookie atual do Core não apresentou Secure/HttpOnly/SameSite; a política local incluída na release deve ser publicada e testada antes de ativar a Central. Backup atualizado em /opt/backups/central-email/before-mail-20261019T182056Z-wooKZn inclui a pasta privada, com integridade gzip/tar/checksums aprovada; restauração ensaiada refere-se ao backup anterior.

PackageOnly reconstruiu pacote local com 2378 entradas e verificação Composer/runtime aprovada; conferência do ZIP confirmou ausência dos nomes privados reais, presença da política de sessão e preservação do override TLS no deploy. Evidências e hash no relatório ignorado _deploy/mail-release-https-checks.json. Nenhum arquivo da release foi enviado ao servidor. Documentação operacional posterior pode exigir nova geração do pacote na revisão final.
