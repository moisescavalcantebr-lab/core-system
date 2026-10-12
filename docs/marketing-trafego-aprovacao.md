# Trafego: aprovacao do planejamento

Terceira entrega, sem financeiro, carteira, proposta comercial ou APIs. Aprovacao do planejamento nao publica anuncios, nao confirma pagamento nem garante aprovacao pela plataforma.

## Fluxo

1. Studio prepara a campanha e salva o planejamento.
2. Com a conversa encerrada, solicita aprovacao. O Core guarda uma versao imutavel do destino, idioma, plataforma, objetivo, regiao, publico, posicionamentos, titulo, texto, material e periodo.
3. Administrador ativo do projeto consulta essa versao e confirma a aprovacao ou solicita ajustes com motivo obrigatorio.
4. Ajustes ficam no historico. O Studio altera o planejamento e envia uma nova versao; nao reabre o briefing nem perde a campanha vinculada.
5. O Core so permite registrar o envio a plataforma e a primeira veiculacao com aprovacao da versao atual. Conversa aberta tambem impede avancar.

Alterar conteudo, publico, datas ou outro campo do planejamento apresentado invalida a aprovacao pendente ou concedida. Alterar apenas identificadores e referencias operacionais da plataforma nao invalida o conteudo aprovado. Os snapshots anteriores e as decisoes permanecem no banco e os eventos no historico compartilhado.

Links de materiais sao referencias: substituir o arquivo hospedado no mesmo URL nao e detectado. Utilize links de arquivos versionados para preservar o material aprovado. Nao enviar credenciais.

## Compatibilidade e seguranca

Tabela nova idempotente `content_studio_traffic_approvals`, no banco do Core. Planos existentes nao recebem aprovacao automatica. Campanhas ja em veiculacao, pausadas ou concluidas mantem seus registros, resultados e a possibilidade de pausa/retomada/conclusao, sem exigir aprovacao retroativa. Pedidos antigos que ainda estejam em revisao na plataforma sem aprovacao nao podem iniciar a veiculacao neste fluxo; uma recusa real da plataforma permite corrigir e submeter o planejamento ao cliente. Nao registrar uma recusa ficticia para contornar etapas.

Todas as decisoes exigem POST, CSRF, administrador ativo, identidade do projeto, revisao atual e versao pendente. Aprovar exige confirmacao explicita; ajustes exigem mensagem. Apenas o cliente aprova. Nao ha email automatico; a consulta e feita no painel.

## Publicacao

Fazer deploy do Core e sincronizar o modulo Trafego da base Marketing no projeto existente. Versao do modulo: 0.5.0. Testes: `traffic_requests_test.php`, `traffic_operations_test.php` e `traffic_requests_ui.cjs` em `scripts/tests/`.
