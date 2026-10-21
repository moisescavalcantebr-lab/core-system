# Financeiro do Time — identificacao e recebimentos parciais

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
- Aprovacao de solicitacao de credito exige um unico jogador vinculado ao solicitante e registra identificacao/historico. A conciliacao e o cancelamento de creditos antecipados ficam para etapa propria; o addon bloqueia cancelamento generico desses movimentos para evitar alterar saldo ja utilizado.
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

## Proximas etapas, nao implementadas

1. Definir saldo inicial e transferencias entre caixa/conta sem inflar arrecadacao; nao registrar saldo inicial como receita manual ficticia.
2. Cobrancas em grupo, competencia mensal, rateio e vinculo especifico com partida/treino. A geracao por categorias existente nao substitui esse fluxo esportivo.
3. Conciliar creditos antecipados e utilizacao por jogador, estornos vinculados, chave Pix do time e regras herdadas de pagamentos de partidas. Nao atribuir uma segunda entrada ao consumo de um credito.
4. Cadastro proprio de patrocinadores/instituicoes, consulta restrita do jogador e definicao comercial por plano.

## Publicacao

Etapa preparada localmente. Nao foi aplicado deploy nem migracao em projetos reais. Para publicar, instalar o addon com seus pais, aplicar apenas seu schema aditivo e copiar as rotas novas junto dos hooks do Financeiro, com backup e verificacao. Nao usar o publicador antigo de sete/28 arquivos, cujo escopo e visual e nao inclui este addon.
