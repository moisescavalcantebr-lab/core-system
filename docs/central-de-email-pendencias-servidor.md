# Central de E-mail — ajustes locais e preparação do servidor

Atualizado em 18/10/2026. Nenhum deploy realizado nesta etapa.

## Pronto no ambiente local

- Pastas principais visíveis; demais pastas individuais recolhidas em **Outras pastas**.
- Favoritar, sinalizar e fixar mensagens, com filtros na conta individual e em Todos os e-mails.
- Fixados aparecem primeiro dentro da busca/pasta selecionada. A busca continua respeitando a conta escolhida.
- Modo de leitura das caixas individuais configurável em **Configurações → Leitura e destaque**: painel lateral (padrão) ou página completa. Todos os e-mails abre sempre lado a lado, sem opção de página completa. Paginação no topo, ao lado de Voltar à lista durante a leitura.
- Cor de sinalização configurável em **Configurações → Destaque de mensagens**. A identificação por cor da conta permanece independente.
- Marcações e preferências armazenadas somente na Central, sem novas permissões OAuth nem alterações no Gmail/Zoho.
- Limpeza de metadados antigos preserva favoritos, sinalizados e fixados. Isso não impede exclusões realizadas no provedor.
- Migração local `003_organization` aplicada, com duas tabelas próprias e verificações de proprietário e CSRF.

As atualizações das caixas continuam manuais: **Atualizar** na conta individual e **Sincronizar todos** na visão unificada. Cada clique processa um lote limitado; havendo pendências, outro clique continua. Não há sincronização automática em segundo plano. A visão unificada de Rascunhos mostra rascunhos remotos já sincronizados; os rascunhos de composição da Central continuam na conta individual.

## Ordem para organizar e publicar

1. **Validar o uso local.** Conferir as três marcações, filtros, destaque e leitura nas duas caixas conectadas. Atualizar o navegador com Ctrl+F5 caso os novos controles não apareçam.
2. **Conferir o servidor e preparar o pacote.** Verificar PHP 8.3, extensões exigidas no `composer.json`, banco, HTTPS e permissões do processo PHP. Preparar dependências a partir do `composer.lock`, assets e migrações 001, 002, 003 e 004. O fluxo de deploy atual ainda precisa ser adaptado para dependências e migrações da Central; revisar também as alterações automáticas de banco existentes no bootstrap do Core. Não executar o deploy atual supondo que essa preparação esteja pronta.
3. **Preparar backup e configuração privada.** Fazer backup recuperável do banco, chaves, segredos e anexos criptografados. Validar a restauração e proteger as chaves. Identificar o usuário proprietário no banco de produção, sem reutilizar automaticamente o ID local. Manter segredos e anexos fora da pasta pública, com acesso restrito ao processo PHP. Manter o módulo bloqueado até a configuração estar completa.
4. **Ajustar HTTPS e autorizações para o endereço final.** Configurar URL oficial, certificado válido e callbacks exatos de Gmail/Zoho. O certificado confiado no Windows serve apenas ao localhost. Revisar configuração de consentimento e validade das autorizações do Google; conectar novamente as caixas quando necessário. Garantir que logs não registrem parâmetros de callback, tokens ou corpos de mensagens.
5. **Validar em homologação.** Conferir isolamento do proprietário, CSRF, leitura, anexos, busca, marcas, reconexão e sincronização coletiva com falha parcial. Fazer envio controlado somente com confirmação do proprietário. Testar backup, restauração e retorno à versão anterior antes da publicação.
6. **Publicar em janela autorizada.** Aplicar migrações com prévia de execução e verificação de checksums, atualizar dependências/assets e validar primeiro com o proprietário. Conferir logs sem dados sensíveis e manter plano de retorno. A publicação continua pendente de autorização e da preparação acima.

## Depois, em etapas separadas

- **Outlook:** resolver o acesso ao Microsoft Entra, configurar o aplicativo e validar a caixa piloto. Não é condição para publicar Gmail e Zoho.
- **Criar, renomear e excluir pastas:** exige verificar suporte e permissões de cada provedor. Gmail usa marcadores, com diferenças em relação às pastas do Zoho. Não foi implementado nesta etapa.
- **Excluir mensagens:** planejar inicialmente mover para a lixeira, com confirmação, e verificar as permissões necessárias. Nenhuma exclusão remota foi habilitada.
- **Favoritos/sinalizadores no provedor:** as marcações atuais são locais. Sincronizá-las com os aplicativos oficiais seria outro recurso, sujeito às permissões e diferenças entre provedores.
- **Sincronização automática:** opcional, com execução agendada, limites, monitoramento e controle de concorrência; ainda não configurada.

## Validação desta etapa

258 verificações do piloto e 122 da fundação aprovadas. Interface validada em 66 combinações do piloto, 24 de configurações, quatro de leitura e quatro de marcações, incluindo celular e temas claro/escuro. Capturas de marcações e preferências inspecionadas.

No HTTPS local, páginas e filtros responderam normalmente; ações recusaram GET, CSRF inválido, usuário anônimo e mensagem inexistente. Tokens e quantidade de marcações reais permaneceram iguais nesses testes. As ações de organização foram exercitadas com dados sintéticos, sem chamadas aos provedores, novos envios ou mudanças em produção.



## Situação em 19/10/2026

Servidor confirmado e compatível; proprietário de produção identificado pelo endereço do projeto. Pacote passou a incluir dependências de produção e migrações 001–004. Configuração privada de produção preparada separadamente, com chave nova e sem tokens locais. Backup protegido do Core criado no servidor, restaurado em banco temporário isolado e migrações ensaiadas duas vezes com sucesso; temporários removidos. Nada foi publicado ou habilitado em produção.

Segredos e trecho de configuração desabilitado do proprietário já foram provisionados fora da raiz pública, sem integrar ao ENV ativo nem reiniciar containers. HTTPS público termina na Cloudflare em modo Flexível; a origem ainda não tem TLS. Exemplos inativos de TLS e CSR público foram preparados localmente, sem emissão ou instalação de certificado.

Próximo: cadastrar callbacks de produção no Google/Zoho; emitir e validar certificado da origem; revisar a ativação de TLS e a mudança de modo da Cloudflare; concluir mounts/ENV e conferir logs; atualizar backup incluindo a pasta privada; reconstruir/revisar/registrar a release; publicar em janela autorizada e reconectar as caixas. Detalhes e evidências em [Preparação de produção](central-de-email-preparacao-producao.md).

Atualização posterior: callbacks confirmados nos dois provedores; certificado validado e HTTPS da origem ativado com autorização, imagem preservada e somente container web recriado. Proprietário confirmou regra Full (strict) no hostname canônico; acessos público/direto passaram. Redação dos parâmetros dos callbacks aprovada no Apache com marcadores sintéticos. Backup atualizado incluindo a pasta privada, com integridade aprovada. Ainda faltam política de logs CDN/WAF, consentimento Google para uso contínuo, integração ENV/mounts, revisão/rebuild da release e publicação autorizada. A sessão do Core atualmente instalado ainda não emitiu os atributos Secure/HttpOnly/SameSite no teste: a política já preparada localmente deve ser publicada e validada antes de habilitar a Central.

**Publicação limitada concluída em 19/10/2026:** o proprietário autorizou a aplicação do pacote, e a Central agora está instalada no Core do servidor, ainda desabilitada. Migrações 001–004 conferidas, configuração privada e política de sessão integradas. Login administrativo externo 200 com Secure/HttpOnly/SameSite=Lax; Central bloqueada 404, ENV/vendor 403 e Branding 200. Backup e evidências no plano de publicação limitada. Google já confirmado Em produção com Branding aprovado. Próximo: revisar logs CDN/WAF, habilitar acesso exclusivo do proprietário e conectar/testar Gmail/Zoho em produção. Não houve consentimento real ou envio de e-mail no servidor nessa publicação.

**Liberação posterior concluída com autorização:** conferência Cloudflare documentada pelos registros fornecidos (Logpush em contratação, Workers sem projetos, evento HTTP sintético com caminho e sem consulta no objeto). Limites dessa evidência registrados na preparação de produção. Central habilitada apenas para o proprietário; funções de acesso aprovadas para dono/ator diferente/anônimo. Dois cadastros Gmail/Zoho criados sem tokens; segredos preservados e backup do fragmento anterior guardado. Próximo: proprietário entrar novamente no Core, autorizar Gmail e Zoho pelos botões da interface e validar sincronização/leitura. Zero conexões OAuth de produção e zero envios até esta etapa. Outlook continua adiado.
