# Meu Time: duas formacoes opcionais

- Um unico elenco de jogadores, com Time 1 ativo por padrao.
- Configurar Time permite renomear as duas formacoes (ate 80 caracteres). Campos vazios recuperam Time 1/Time 2. Os nomes aparecem nos campos e nas identificacoes dos botoes de destino e permanecem salvos ao desativar o segundo campo.
- A opcao Segundo time habilita o campo Time 2. Ambos usam a modalidade e a configuracao de posicoes de Meu Time.
- Jogadores disponiveis reune os jogadores fora dos campos, incluindo os antigos reservas. Os botoes 1 + e 2 + escolhem o destino.
- O mesmo jogador nao pode ocupar os dois campos. Retirar do campo libera o jogador sem excluir cadastro.
- Vaga ocupada nao substitui outro atleta: o jogador continua disponivel.
- Desativar o segundo time pede confirmacao e devolve seus jogadores aos disponiveis; reativar nao remonta a formacao automaticamente.
- Treino, Partidas, Competicoes, Participantes e Jogadores nao foram alterados nesta etapa.

## Banco e atualizacao

`team_profile.formations_count` guarda um ou dois campos. `team_roster.team_key` identifica a formacao; a unicidade por jogador foi preservada.

O schema atende instalacoes novas. Para bancos existentes, Meu Time verifica as colunas e as adiciona na primeira abertura, com um campo e os registros antigos associados ao Time 1. A migracao e repetivel e nao apaga dados.

Depois do deploy, sincronizar o modulo Meu Time no projeto e abrir a pagina para aplicar a verificacao do banco. O usuario do banco precisa de permissao para ALTER TABLE.

## Verificacao local

- `docker exec app_php php /var/www/html/scripts/tests/team_formations_test.php`
- `docker exec app_php php /var/www/html/scripts/tests/football_modules_test.php`
- `node scripts/tests/team_formations_ui.cjs` com Playwright disponivel via NODE_PATH.

O teste de rotas usa banco temporario e valida migracao legada, separacao das vagas, duplicidade, retorno aos disponiveis, desativacao, reativacao, CSRF, perfil de administrador e preservacao dos jogadores. O teste visual usa HTML produzido pelas rotas reais em desktop, tablet e celular.
