# Manter a mensagem aberta ao alterar leitura

Ajuste solicitado em 19/10/2026: marcar como lida deve preservar a mensagem aberta e a posição da leitura, em vez de retornar à lista. A mesma resposta é suportada para a operação de não lida, sem reintroduzir o botão que o proprietário pediu para ocultar.

O formulário de leitura envia POST com CSRF e solicita JSON. Depois da confirmação do provedor, a interface atualiza a indicação de leitura na linha correspondente e mostra o resultado, sem substituir o corpo da mensagem, recarregar o painel ou navegar. O botão Marcar como lida desaparece após sucesso. Ações Mover e Lixeira mantêm o fluxo anterior.

Em falha, a mensagem continua aberta com orientação para atualizar antes de tentar novamente. Não há repetição automática. Sem JavaScript, o POST redireciona para a mesma mensagem, preservando os filtros validados, em vez da lista.

Validação local: 516 verificações da suíte piloto e seis cenários de navegador para leitura, mover e lixeira, em desktop e celular, com banco/API/páginas simulados. O teste de leitura exige que a URL e o painel permaneçam abertos após o POST.

Publicação: `scripts/central-contas-publicar-organizacao.ps1 -ReaderFix`, restrita a `web/assets/js/central_contas.js` e `app/actions/central_contas/message_update.php`, com comparação da baseline da produção, backup, validação de hashes/sintaxe e saúde, e reversão em falha. Não modifica configuração, não sincroniza caixas, não copia credenciais nem altera mensagens no provedor. O layout já usa a data de alteração do JavaScript na URL para atualizar o cache do navegador.

Relatório: `_deploy/mail-reader-fix-publication.txt`.

Publicação concluída em 19/10/2026; backup `/opt/private-mail-center/releases/organization-20261019T222349Z-hI0wIZpi`. Hashes e saúde do Core aprovados; Gmail/Zoho continuam autorizados e sem erros registrados. Sem chamadas aos provedores, sincronização, migrações ou recriação de containers nesta publicação.
