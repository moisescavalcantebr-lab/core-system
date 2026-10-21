# Financeiro do Time — identificacao, recebimentos e rateios

Addon vinculado ao Financeiro e Jogadores, instalado inicialmente na base Futebol. Mantem um unico conjunto de movimentos de caixa em `finance_entries`; `finance_team_history` registra snapshots, responsavel, data e motivo de cancelamento. Cobrancas do elenco sao obrigacoes separadas do dinheiro recebido.

## Regras implementadas

- Novos lancamentos manuais exigem entrada/saida, finalidade, valor positivo e identificacao. Nao ha selecao implicita de jogador ou identidade externa.
- O administrador/financeiro seleciona jogador do proprio banco do projeto, mesmo sem login ou inativo, ou informa o nome de pessoa/empresa. O addon nao cria um catalogo padrao de patrocinadores, instituicoes ou doadores.
- Jogador e vinculado por `party_module=jogadores`, `party_id` e `party_type=member`, mantendo o enum geral compativel. `party_name` guarda o nome do momento do registro. Nome digitado e vinculo sao sempre escapados na apresentacao; a selecao e revalidada no servidor.
- Pendente exige data prevista. Confirmado exige data de recebimento/pagamento. Comprovante continua opcional. Registro e historico sao gravados na mesma transacao.
- Edicao de novo registro fica disponivel enquanto pendente. Confirmado/cancelado nao pode ser reescrito ou excluido. Cancelamento manual exige motivo e confirmacao; usa bloqueio de linha, preserva dados e registra snapshot. Corrigir um confirmado requer cancelamento e novo registro. Cancelamento nao executa devolucao bancaria.
- Confirmacao rapida de registro acompanhado abre a edicao para informar a data efetiva. O usuario CLIENT nao recebe acesso as telas administrativas ou actions.
- O historico por jogador inclui inativos e distingue registros acompanhados de lancamentos anteriores, que continuam nas telas gerais sem preenchimento retroativo ou migracao de valores.
- Parcelas e recorrencias ja existentes no Financeiro recebem a mesma identificacao e trilha, de forma atomica; series sao criadas pendentes, e parcelas de valor zero sao rejeitadas.
- Aprovacao de solicitacao de credito exige um unico jogador vinculado ao solicitante e registra identificacao/historico. Novos creditos seguem a conciliacao descrita abaixo. Creditos antigos nao sao convertidos automaticamente.
- O Financeiro continua com o comportamento geral em projetos sem este addon. Nenhum novo projeto, patrocinador ou jogador real e criado por esta etapa.

## Cobrancas e recebimentos parciais (0.2.0)

- A tela Cobrancas do elenco cria obrigacao individual com jogador beneficiario, finalidade, valor total e data prevista. Criar uma cobranca nao cria entrada no caixa.
- Cada recebimento exige pagador identificado (jogador ou pessoa/empresa), valor efetivamente recebido e data. O pagador pode ser diferente do beneficiario. Um unico movimento `source=team_payment` e gravado no Financeiro para cada recebimento, ligado a cobranca.
- O restante e calculado em centavos, somando somente recebimentos confirmados. A cobranca aparece Em aberto, Parcial, Quitada ou Cancelada. Valor excedente e rejeitado, inclusive com pedidos simultaneos, usando bloqueio de linha na cobranca.
- Chaves unicas e fingerprints tornam criacao e recebimento idempotentes. Reenvio identico nao duplica registro; o mesmo envio com valores alterados e rejeitado. Um recebimento cancelado nao e reativado por repeticao do formulario antigo.
- Cancelar um recebimento com motivo ajusta o caixa e reabre o restante da cobranca na mesma transacao, com historico em ambos. Nao realiza devolucao bancaria. A cobranca so pode ser cancelada quando nao possui recebimentos ativos; total e beneficiario nao sao reescritos depois da criacao.
- O resumo financeiro e o dashboard somam apenas o restante das cobrancas a receber. O total cobrado nao entra como dinheiro recebido. A visao por jogador mostra movimentos em que ele e pagador ou beneficiario, indicando de quem e a cobranca.
- `finance_team_charges` guarda a obrigacao e snapshots; `finance_team_charge_payments` liga os movimentos reais e suas chaves de reenvio; `finance_team_charge_history` guarda a trilha. Nao ha caixa paralelo. As FKs dos recebimentos impedem exclusao fisica dos movimentos vinculados; excluir uma categoria limpa o vinculo da cobranca sem apagar a obrigacao.
- Lancamentos manuais pendentes anteriores continuam com confirmacao integral. Nao sao convertidos em cobrancas automaticamente: para o novo fluxo parcial, usar Cobrancas do elenco. Nao criar uma segunda cobranca para representar uma pendencia ja existente sem regularizar o registro anterior.

## Grupos e rateios (0.3.0)

- Selecionar explicitamente de 1 a 30 jogadores, inclusive inativos identificados, para cobrar o mesmo valor por pessoa ou dividir um total. Nenhum jogador ou modo e preselecionado. Selecionar ativos e uma acao opcional do administrador.
- Previa obrigatoria no servidor mostra nomes, valores, vencimento e total antes da criacao. No rateio, o resto dos centavos e distribuido pela ordem crescente dos IDs, a mesma ordem mostrada na previa. Nao sao criadas cobrancas de valor zero.
- A confirmacao e assinada com chave da sessao, responsavel, chave de reenvio e distribuicao canonica. O servidor revalida e bloqueia os cadastros selecionados durante a gravacao. Mudar valor, jogadores ou dados cadastrais exige refazer a previa.
- O grupo inteiro, as cobrancas e os historicos sao gravados numa unica transacao. Falha em qualquer jogador desfaz tudo; confirmacoes repetidas ou simultaneas nao criam um segundo grupo.
- `finance_team_charge_batches` guarda os parametros e snapshots da previa; `finance_team_charge_batch_items` vincula cada jogador a uma cobranca individual. O mesmo gravador interno e usado na criacao individual e em grupo.
- Criar o grupo nao registra recebimentos e nao envia mensagens aos jogadores. Cada cobranca usa o fluxo de recebimentos parciais ja existente. A visao do grupo acompanha recebido, em aberto e valores cancelados, mantendo o total originalmente gerado.
- Categorias apenas classificam estas cobrancas; nao geram meses ou parcelas adicionais no grupo. Esta etapa nao implementa recorrencia automatica, disparos de cobranca, rateio ponderado ou cancelamento coletivo.

## Caixas e creditos (0.4.0)

- Caixa geral preserva o historico sem conta; novas contas sao cadastradas pelo responsavel. Saldo inicial tem data, motivo e historico proprio, sem criar receita. Movimentos anteriores a sua data sao rejeitados.
- Transferencias exigem origem/destino diferentes, saldo disponivel, data e motivo. Reenvio nao duplica a transferencia. Cancelamento exige motivo e disponibilidade no destino. Transferencias nao entram na arrecadacao nem na despesa.
- Novos recebimentos e pagamentos permitem selecionar a conta. O saldo total inclui saldos iniciais; os graficos de entradas e saidas continuam mostrando apenas movimentos reais.
- Credito pertence a um jogador cadastrado; o pagador pode ser outra pessoa identificada. Deposito cria uma entrada real, devolucao realizada cria uma saida. Uso quita cobranca sem outro lancamento no caixa. Recebimentos em dinheiro e em credito compartilham o limite restante da cobranca.
- Cancelar uso devolve credito e reabre cobranca, com motivo e trilha. Cancelar deposito so e permitido se o credito estiver disponivel. Nao executa operacao bancaria. Creditos legados nao sao reinterpretados nem migrados automaticamente.
- Mutacoes novas usam bloqueio por projeto, transacao, centavos inteiros e chaves de reenvio. Origem, responsavel, datas e snapshots ficam registrados.
- Solicitacao aprovada registra credito do jogador com data efetiva e conta. Pix vem das configuracoes do proprio projeto. Consulta do jogador exige vinculo unico ao login e nunca aceita outro jogador pela URL.
- Categorias e finalidade permitem identificar mensalidade, jogo ou outra atividade manualmente. Recorrencia esportiva automatica, vinculo automatico com partida/treino, consumo de credito no pagamento automatico de partidas, cadastro de patrocinadores/instituicoes e mudancas comerciais por plano seguem para evolucao separada. Nenhuma dessas automacoes e ativada por esta entrega.

## Publicacao

Usar `scripts/futebol-financeiro-publicar.ps1`; `-PackageOnly` prepara sem conectar ao servidor. O pacote inclui o addon, hooks e copias das bases. O instalador encontra apenas projetos Futebol com Financeiro e Jogadores, confere hashes da versao anterior e interrompe em divergencias.

Antes de instalar: bloqueio temporario das rotas financeiras, backup privado de codigo e banco, restauracao integral em MySQL isolado sem rede e conferencia de contagens. Aplicar apenas o SQL aditivo do addon; nunca importar o schema completo da base em projeto existente. Comparar o digest dos dados financeiros anteriores antes/depois. Ativar o manifest somente depois de instalar dependencias. Verificar hashes, autenticacao e saude do Core.

Em falha, reverter apenas os arquivos exatos e restaurar o acesso. Tabelas aditivas permanecem, sem apagar dados ou restaurar automaticamente um banco em uso. Backups e relatorios privados ficam em `/opt/private-futebol/finance-*`; o relatorio local fica em `_deploy/futebol-financeiro-publication.txt`. Este documento nao confirma uma publicacao: exigir `finance_published=yes` no relatorio final.
