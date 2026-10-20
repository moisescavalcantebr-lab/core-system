# Financeiro do Time — primeira etapa

Addon vinculado ao Financeiro e Jogadores, instalado inicialmente na base Futebol. Mantem um unico conjunto de lancamentos em `finance_entries`; `finance_team_history` registra snapshots, responsavel, data e motivo de cancelamento, sem copiar o caixa.

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

## Proximas etapas, nao implementadas

1. Definir saldo inicial e transferencias entre caixa/conta sem inflar arrecadacao; nao registrar saldo inicial como receita manual ficticia.
2. Pagamentos parciais e separacao entre pagador e beneficiario da quitacao. Nesta versao, cada lancamento identificado tem uma unica parte relacionada.
3. Cobranças em grupo, competencia mensal, rateio e vinculo especifico com partida/treino. A geracao por categorias existente nao substitui esse fluxo esportivo.
4. Conciliar creditos antecipados e utilizacao por jogador, estornos vinculados, chave Pix do time e regras herdadas de pagamentos de partidas. Nao atribuir uma segunda entrada ao consumo de um credito.
5. Cadastro proprio de patrocinadores/instituicoes, consulta restrita do jogador e definicao comercial por plano.

## Publicacao

Etapa preparada localmente. Nao foi aplicado deploy nem migracao em projetos reais. Para publicar, instalar o addon com seus pais, aplicar apenas seu schema aditivo e copiar as rotas novas junto dos hooks do Financeiro, com backup e verificacao. Nao usar o publicador antigo de sete/28 arquivos, cujo escopo e visual e nao inclui este addon.
