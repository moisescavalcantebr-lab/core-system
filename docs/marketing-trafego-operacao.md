# Trafego: operacao manual sem financeiro

Esta etapa adiciona planejamento e acompanhamento ao pedido preparado. Nao cria propostas, aprovacao do cliente, cobranca, pagamentos, reserva de saldo, APIs ou anuncios reais. Os botoes registram atividades realizadas fora do Core pelo operador; nao comprovam automaticamente os dados da plataforma.

## Planejamento

O Studio define Meta ou Google, objetivo, regiao, publico, canais/posicionamentos, titulo, texto, material por link e periodo. A preferencia original do cliente e preservada. O planejamento e compartilhado com o projeto e fica bloqueado para edicao depois do registro de envio a plataforma; se houver recusa, pode ser corrigido e reenviado.

ID da campanha e links de referencia sao registrados pelo operador, sem credenciais. O destino permanece o do pedido preparado. A aprovacao versionada do cliente sera uma etapa posterior; este fluxo e para testes manuais, nao uma autorizacao comercial automatica.

## Estados

Preparada -> em revisao na plataforma -> em veiculacao -> pausada/retomada -> concluida.
Uma recusa na plataforma permite corrigir o planejamento e registrar nova revisao. Cada transicao requer confirmacao manual, planejamento, identificador externo e referencia da operacao. Pausa, recusa e conclusao exigem motivo/resumo.

Pedidos, campanhas do Studio e historico sao atualizados juntos em transacao. A conversa precisa estar fechada para avancar a operacao. O cliente pode continuar conversando quando o Studio reabre a troca, mas nao altera estados operacionais.

## Resultados

Registros por dia, com impressoes, cliques no link e visitas medidas, fonte e link de referencia. Nao informar valores acumulados de varios dias em um unico dia. Visitas nao medidas ficam nulas, e nao sao inferidas dos cliques. Atualizar um dia substitui aquele registro sem somar resultados duplicados. Totais e grafico de cliques sao compartilhados com o cliente.

A data precisa estar no intervalo entre o inicio e a conclusao registrados no Core, sem dias futuros. Inicio/conclusao registram o momento da confirmacao do operador; nao sao sincronizados pela plataforma. Nenhum lead e importado.

## Publicacao e testes

Deploy do Core e sincronizacao dos modulos da base Marketing no projeto. As duas tabelas novas sao criadas idempotentemente no Core, preservando as anteriores. Sem mudancas na carteira.

Testes: `scripts/tests/traffic_requests_test.php`, `scripts/tests/traffic_operations_test.php` e `scripts/tests/traffic_requests_ui.cjs`.
