# Central de E-Mail — publicação limitada para revisão

Preparado em 19/10/2026. Este plano instala o código com a Central desabilitada; não conecta caixas e não envia mensagens.

**Executado após autorização explícita do proprietário em 19/10/2026.** Código instalado, migrações 001–004 conferidas, mounts privados e wrapper de ENV aplicados, imagem preservada. Recriado somente app; Central permanece desabilitada e sem conexões OAuth reais no servidor. Backup da instalação: `/opt/private-mail-center/releases/install-20261019T190924Z-fiA0zmtu`. Reversão não precisou ser executada.

Conferência externa posterior: `/web/admin/login.php` 200 com cookie Secure/HttpOnly/SameSite=Lax, Central desabilitada 404, ENV privado 403, manifest do vendor 403 e página de Branding 200. Nenhuma migração de Marketing/Meu Trabalho foi executada pelo procedimento. Relatório local `_deploy/mail-core-installation.txt`.

## Pacote conferido

- Repositório confirmado pelo proprietário como privado: `moisescavalcantebr-lab/core-system`.
- Branch: `codex/central-email-production`. Alterações de bases e página Financeiro ficaram fora dos commits.
- Pacote: `_deploy/mail-core-overlay.tar`, SHA-256 `0f245b2260c2c7bb09ed6bfe8625fff732ab5b608990e29538657ad2da10cf24`.
- Prévia privada no servidor: `/opt/private-mail-center/releases/plan-20261019T190147Z-Lh01QElx`.
- Conferência no servidor: 956 arquivos íntegros, zero divergências de produção; runtime/dependências aprovados na imagem atual em container sem rede externa. Nenhum arquivo ativo ou tabela foi alterado nessa prévia.
- Verificações locais: fundação 122; piloto 298, incluindo provedores simulados; release 8; manifesto limitado 7. Interfaces de provedores reais não foram usadas nesses testes.

## Escopo exato

Adicionar os arquivos da Central, suas dependências Composer, migrações 001–004, assets e páginas públicas já aprovadas. Substituir somente os seis arquivos compartilhados revisados: `.htaccess`, bootstrap, AuthService, layout administrativo, header e sidebar. A verificação compara o estado existente com a baseline anterior à Central ou com o novo conteúdo esperado e interrompe em divergência.

O pacote não inclui bases, projetos de clientes, dados da página Financeiro, módulos Marketing/Meu Trabalho ou alterações em seus bancos. Não usar o deploy geral para esta instalação.

## Aplicação proposta

1. Repetir a comparação/integridade e validar que a imagem ainda é a da prévia.
2. Preservar dump atual do Core, arquivos afetados, ENV e Compose efetivo em pasta privada 0700, com arquivos privados 0600.
3. Preservar o ENV original em arquivo privado montado somente leitura; instalar wrapper de configuração que mantém o Core e inclui o fragmento da Central, ainda `enabled=false` e `oauth_log_redaction_verified=false`. O procedimento para antes de escrever se o ENV original depender de caminhos relativos por `__DIR__`, `__FILE__` ou `dirname`.
4. Montar segredos/configuração privada somente leitura e anexos em diretório privado; não copiar tokens locais. Aplicar código/dependências antes dos arquivos compartilhados.
5. Recriar somente `app`, mantendo imagem atual, HTTP e override HTTPS. Sem build e sem reiniciar banco ou phpMyAdmin. Pode haver breve indisponibilidade do Core.
6. Executar prévia/aplicação das migrações aditivas 001–004 e verificar a instalação, sem executar migrações de outros módulos.
7. Conferir a rota efetiva `/web/admin/login.php`, TLS, resposta 200 e cookie Secure/HttpOnly/SameSite=Lax. As ferramentas foram ajustadas para essa rota; a conferência independente antes da instalação confirmou que os atributos ainda não estavam presentes nela.

Em falha, o procedimento restaura arquivos/ENV/Compose e tenta recriar o container anterior. Tabelas aditivas eventualmente criadas são preservadas, sem DROP nem restauração automática do banco completo. Não apagar alterações posteriores de outros módulos.

Ferramentas: `scripts/central-contas-aplicar-release.ps1` e `.sh`. O ensaio de fluxo usa container descartável sem rede, com comandos Docker/provedores simulados e dados sintéticos; ele testa sucesso e reversão após falha de migração. Esse ensaio não substitui a conferência real após a publicação.

## Depois da instalação desabilitada

Conferir logs Apache e política CDN/WAF, proprietário e restrições de acesso. Resolver as pendências antes de habilitar a Central e marcar a revisão de logs como concluída. Conectar Gmail/Zoho novamente em produção por consentimento do proprietário; testar leitura/busca/sincronização. Envio real somente com autorização. Outlook continua adiado.

Google: proprietário confirmou Em produção; captura mostrou Branding verificado. Escopos de dados continuam não verificados, com uso exclusivo pessoal previsto pela exceção documentada do Google. Nenhuma aprovação formal de escopos foi declarada.
