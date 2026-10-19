# Organização de e-mails — análise de 19/10/2026

Gmail e Zoho estão conectados em produção; sincronização limitada de metadados validada. O filtro de lixeira foi publicado. As ações de mensagem foram implementadas localmente, atrás de uma opção desabilitada por padrão; ativação e teste em caixas reais ainda pendentes. Nenhuma permissão OAuth real foi ampliada.

## Ajuste preparado localmente

Listas individuais e unificadas, busca, favoritos, sinalizados, fixados e navegação para a próxima mensagem ocultam mensagens vinculadas à Lixeira. Mesmo uma mensagem com outro marcador permanece oculta nessas listas. Abrir explicitamente Lixeira continua mostrando essas mensagens. O filtro depende dos vínculos já sincronizados: alterações feitas no webmail aparecem após atualizar o lote correspondente. Não apaga metadados nem altera mensagens no provedor.

Publicado em produção em 19/10/2026, com hash do código, saúde do Core e restrição de acesso anônimo aprovados no relatório `_deploy/mail-trash-filter-publication.txt`. Backup privado: `/opt/private-mail-center/releases/trash-filter-20261019T200110Z-RzI2wu5z`. Sem alterações no banco, permissões OAuth ou chamadas aos provedores. O terminal exibiu uma falha depois do sucesso: o pipeline PowerShell acrescentou uma linha CRLF interpretada como comando inválido pelo Bash. Reproduzido localmente (saída 127); corrigido com saída explícita ao final do script, sem repetir a publicação já concluída.

Validação local: suíte piloto aprovada com 325 verificações, incluindo 27 novas verificações do filtro de lixeira, em banco descartável e provedores simulados. Nenhuma caixa real foi acessada pelos testes.

## Ordem recomendada

1. **Marcar lida/não lida, mover e enviar para a lixeira/restaurar.** Ação explícita na mensagem, aplicada à conta de origem. Destinos pertencem à mesma conta; mover entre Gmail e Zoho não faz parte deste recurso. Na visão unificada, cada ação resolve o provedor da mensagem. Não marcar automaticamente como lida ao abrir sem uma preferência definida.
2. **Criar e renomear pastas próprias.** Controles discretos no menu da pasta. Bloquear mudanças nas pastas de sistema. Gmail usa marcadores e permite múltiplos por mensagem: definir mover como adicionar destino e remover apenas a origem escolhida, preservando outros marcadores.
3. **Excluir pastas próprias.** Regra confirmada pelo proprietário em 19/10/2026: permitir somente pastas vazias, sem subpastas, com confirmação explícita. Conferir o estado remoto completo, incluindo mensagens fora do cache/lote local, imediatamente antes da operação; se a consulta falhar ou não provar que está vazia, bloquear. No Gmail excluir marcador preserva as mensagens; no Zoho excluir pasta pode excluir seu conteúdo e subpastas. Considerar alterações concorrentes no webmail; não prometer atomicidade sem garantia da API. Renomear pastas próprias é a prioridade de organização, mantendo os controles no Core.

## Permissões necessárias

| Provedor | Situação atual | Ampliação proposta |
| --- | --- | --- |
| Gmail | gmail.readonly e gmail.send | gmail.modify para leitura, escrita, movimentação, lida/não lida e lixeira; inclui gerenciamento de marcadores. Não solicitar o escopo completo mail.google.com para exclusão permanente. |
| Zoho | accounts.READ, folders.READ, messages.READ e messages.CREATE | messages.UPDATE para mover e marcar; messages.DELETE para enviar à lixeira com expunge=false; folders.CREATE e folders.UPDATE para criar/renomear; folders.DELETE somente na etapa de excluir pastas. |

Será necessário reconectar e aceitar as permissões adicionais quando a implementação estiver pronta. Conferir os escopos efetivamente concedidos antes de habilitar cada botão. O aviso/estado de verificação de dados do Google deve ser reavaliado conforme o uso e os novos escopos; branding verificado não significa escopos aprovados.

## Sincronização e implementação

Confirmar a operação no servidor antes de atualizar a lista local. Em timeout com resultado incerto, consultar o estado remoto antes de repetir, especialmente na criação de pastas. Respeitar limites, concorrência com sincronização e reconexões, isolamento de conta/proprietário, CSRF e auditoria sem conteúdo/tokens. Restaurar da lixeira deve escolher destino válido da mesma conta. Sem exclusão definitiva de mensagens nesta proposta.

O cliente HTTP agora aceita explicitamente PUT/DELETE mantendo HTTPS e a lista de hosts permitidos. A lixeira Zoho usa DELETE de mensagem com expunge=false explícito, conforme documentação; bloqueia nova ação em mensagem já na lixeira. O adaptador de pastas ainda precisará aceitar a resposta 201 de criação. A documentação de excluir pasta apresenta inconsistência entre a linha do método e o exemplo DELETE: confirmar o contrato antes de implementar essa etapa.

Testar primeiro com provedores simulados, depois com pasta e mensagens de teste autorizadas em cada conta. Só então publicar e renovar consentimentos. Alterações feitas pelo webmail continuam chegando pelos lotes de sincronização; atualização automática em segundo plano permanece uma etapa separada. Favoritos, sinalizadores e fixação atuais continuam locais.

## Referências oficiais

- [Escopos Gmail](https://developers.google.com/workspace/gmail/api/auth/scopes)
- [Criar marcadores Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.labels/create)
- [Enviar para lixeira Gmail](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/trash)
- [Mover mensagens Zoho](https://www.zoho.com/mail/help/api/move-email.html)
- [Marcar lida Zoho](https://www.zoho.com/mail/help/api/put-mark-email-as-read.html)
- [Criar pasta Zoho](https://www.zoho.com/mail/help/api/post-create-new-folder.html)
- [Renomear pasta Zoho](https://www.zoho.com/mail/help/api/put-rename-folder.html)
- [Operações de pastas Zoho e efeito da exclusão](https://www.zoho.com/mail/help/api/folders-api.html)
