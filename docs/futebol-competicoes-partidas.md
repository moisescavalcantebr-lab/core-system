# Acompanhamento do meu time

## Campeonato

Competicoes acompanha os jogos do proprio time, nao organiza jogos entre terceiros nem a tabela de todos os clubes. A equipe do projeto ocupa o lado A; o lado B e o adversario. O nome do lado A fica salvo na partida e e preservado na edicao.

Resultados finalizados acumulam vitorias, empates, derrotas, gols, saldo e pontos (3/1/0). A posicao oficial e informada manualmente no cadastro ou edicao do campeonato, inclusive depois de cadastrar partidas. Nao e calculada pelos resultados parciais dos outros clubes.

O campo opcional `competitions.official_position` e criado de forma repetivel quando necessario. A edicao com partidas continua bloqueando mudancas estruturais, mas permite nome, temporada e posicao oficial.

## Treino em Partidas

Novos treinos dependem das duas formacoes ativas em Meu Time. A restricao e validada no servidor, nao somente na interface. A regra comercial de plano existente foi mantida.

Ao criar uma partida, sao copiados nomes, configuracao de campo, jogadores e lados de Meu Time para `matches` e `match_lineup`. Jogadores disponiveis nao sao automaticamente inscritos no treino. `matches.lineup_snapshot_at` impede recopia ate mesmo quando a escala original era vazia. Alterar nomes, posicoes ou elenco em Meu Time nao remonta uma partida existente.

Partidas antigas que ja possuem escala permanecem intactas. Nao e feito preenchimento com o elenco atual em jogos finalizados. A criacao de partida e escala usa transacao, com rollback se a copia falhar.

## Classificacao e participacao

Classificacao foi instalada na base Futebol. No treino, sua tabela individual continua vinculada a competicao/periodo e contabiliza o resultado do lado em que cada jogador estava na partida: vitoria 3, empate 1, derrota 0. O recalculo e repetivel.

Essa classificacao NAO e o ranking geral de participacao. O ranking simples de um ponto por partida permanece para uma etapa posterior; a pontuacao antiga de presenca nao foi substituida nesta entrega.

## Retirada do Treino antigo

O modulo `treino` esta aposentado: manifesto sem menu, sem schema de instalacao ativo e endpoints redirecionados para Meu Time. Nao existe mais uma segunda tela funcional para montar os mesmos times. A sincronizacao atualiza os endpoints antigos e remove seu item da navegacao sem precisar atualizar o layout do projeto.

Arquivos minimos de compatibilidade e tabelas legadas foram preservados deliberadamente. Nenhum dado de `training_roster` ou `training_settings` e apagado. Partidas nao le essas tabelas nem as cria no fluxo novo. A limpeza fisica do legado pode ocorrer em uma migracao futura; nao e necessaria para usar o fluxo novo.

## Atualizacao e testes

Depois do deploy, sincronizar TODOS os modulos do projeto para atualizar Meu Time, Competicoes, Partidas e o redirecionamento de Treino, alem de instalar Classificacao. Atualizar somente Partidas nao instala os arquivos novos de Classificacao.

Validacoes locais:

- `docker exec app_php php /var/www/html/scripts/tests/football_tracking_test.php`
- `docker exec app_php php /var/www/html/scripts/tests/football_modules_test.php`
- `docker exec app_php php /var/www/html/scripts/tests/team_formations_test.php`

O teste integrado usa um banco temporario e cobre campeonato do proprio time, posicao oficial, espelhamento inicial, independencia do historico, bloqueio com uma formacao, classificacao individual, rollback, CSRF, acesso e preservacao do legado.
