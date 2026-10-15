# Futebol: primeira etapa do nucleo

Participantes cadastra a pessoa. Jogadores continua como perfil esportivo e Meu Time como vinculo ao elenco; a reorganizacao desses dois componentes sera a etapa seguinte.

## Acesso opcional

A politica e declarada no manifesto instalado de Participantes: `participant_access: optional` apenas na base Futebol. Catalogo e bases sem essa opcao permanecem em `required`. A politica e lida no servidor; formularios nao podem altera-la.

Na administracao de Futebol, nome e os dados da pessoa podem ser salvos sem conta. `participants.user_id` fica NULL, como o schema ja permite. A conta pode ser criada ao editar a mesma pessoa, sem trocar o ID ou criar outro participante. Ao optar por acesso, e-mail, usuario unico e senha continuam obrigatorios. Nao existem senha padrao ou elevacao de papel: novas contas recebem CLIENT; papeis existentes sao preservados.

Contas ja vinculadas nao sao removidas ao desmarcar ou omitir campos. Senha vazia ao editar conta existente preserva o hash. Formulario com erro preserva os dados, mas nunca a senha. Criacao e atualizacao usam transacao e bloqueio do participante; na politica opcional, um formulario que ainda aponta para outro vinculo de acesso e recusado, evitando criar conta duplicada ou sobrescrever um acesso criado por outra edicao.

Na politica opcional, status da pessoa e status da conta sao separados. Editar ou ativar/desativar o participante nao desbloqueia nem bloqueia a conta automaticamente. Contas ja vinculadas possuem um campo separado de status do acesso, alterado somente por escolha explicita de administrador ativo. Tela antiga nao pode sobrescrever uma mudanca concorrente de acesso; deixar o campo como estava preserva o estado atual da conta. Nas demais bases, o comportamento anterior de vincular esses estados foi mantido.

Cadastro publico continua com suas regras anteriores: cria acesso e nao recebe esta opcao administrativa. Esta etapa nao transforma automaticamente participantes em jogadores, nao redefine permissao esportiva e nao altera registros de Jogadores, Meu Time ou Financeiro.

## Publicacao

Sem alteracao de schema, sem exclusao ou migracao de dados. Modulo Participantes 0.2.0. A copia da base Futebol guarda a politica opcional; preserve esse ajuste ao reinstalar o modulo a partir do catalogo.

Depois do deploy, sincronizar Participantes da base Futebol para projetos existentes. A politica vale a partir da sincronizacao, sem desvincular contas existentes. Ainda nao ha validacao de producao nesta entrega; testar cadastro sem acesso, edicao, criacao posterior de conta e preservacao de papeis e status.

Testes locais: `scripts/tests/participant_access_test.php` (banco temporario, rotas reais, administrador ativo, CSRF, conflito, rollback e politicas) e `scripts/tests/participant_access_ui.cjs` (desktop/celular). Schemas e manifestos das outras bases nao sao alterados.
