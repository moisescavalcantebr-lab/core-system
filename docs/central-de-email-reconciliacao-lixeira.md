# Reconciliação local da Lixeira do Zoho

Implementada localmente em 19/10/2026 e publicada posteriormente no mesmo dia pelo pacote limitado de organização. Mantida a decisão de usar somente Lixeira e restauração na Central. A retenção e a exclusão definitiva continuam sob responsabilidade do provedor; nenhuma configuração de retenção foi alterada.

## Funcionamento

Ao atualizar uma caixa Zoho, inclusive por Sincronizar todos, o mesmo bloqueio de sincronização permite até três consultas adicionais de **metadados** de uma mensagem local da Lixeira. Não lê corpos/anexos nem escreve no provedor. Não depende das permissões adicionais de organização.

A Lixeira precisa aparecer uma única vez no inventário atual, identificada pelo tipo Trash do Zoho. Primeiro confere a mensagem nessa pasta. Se a API responder explicitamente que não existe, procura seu identificador nas demais pastas, inclusive pastas personalizadas. A consulta direta também cobre mensagens arquivadas e anteriores à janela habitual de 30 dias.

Se encontrar a mensagem, atualiza pasta e estado de leitura, mantendo favoritos, sinalização e fixação locais. Se todas as pastas informarem ausência, marca somente o registro local como indisponível; não apaga o registro nem chama exclusão remota. Novas importações podem reativar esse registro.

A busca continua no próximo lote usando um checkpoint criptografado. Avança entre as mensagens e reinicia ao chegar ao fim, evitando conferir sempre o mesmo item. Mudanças no inventário de pastas, na geração da conexão ou checkpoints com mais de uma hora reiniciam a evidência parcial. Reconexão concorrente impede a gravação de resultado antigo. Restauração local entre lotes prevalece sobre o checkpoint.

Falha de rede, quota, autenticação ou resposta malformada interrompe o lote sem interpretar a falha como exclusão. A página de polling não pode reutilizar metadados em memória invalidados pela consulta mais recente.

## Limites e publicação

É atualização manual e progressiva, sem agendamento novo. Uma caixa com muitas mensagens na Lixeira ou muitas pastas precisará de vários lotes. O inventário é limitado a mil pastas; inventário maior ou Lixeira ambígua não autoriza marcar ausências. Não é uma fotografia atômica da caixa: movimentos simultâneos no webmail podem exigir nova atualização. A reconciliação se restringe às mensagens já conhecidas localmente na Lixeira, sem prometer espelhamento integral de todas as alterações externas.

Gmail mantém sua sincronização por histórico. Outlook permanece adiado. A publicação controlada em produção incluiu backup, conferência dos arquivos e lotes de metadados das duas conexões; ver [relatório de publicação](central-de-email-publicacao-organizacao.md). As ações de organização dependem de novo consentimento no servidor, solicitado ao proprietário. Os tokens locais não foram copiados.

Validação: 514 verificações aprovadas na suíte piloto, incluindo 32 específicas desta reconciliação, com banco descartável e APIs simuladas; cobre retomada, limite de consultas, ausência confirmada, restauração, arquivo antigo, leitura, inventário alterado/ambíguo, prazo, erros de provedor, geração da conexão, cursor criptografado e descarte de página obsoleta. Nenhuma mensagem real foi modificada pelos testes.

Referência: [API oficial de metadados Zoho](https://www.zoho.com/mail/help/api/get-email-meta-data.html).
