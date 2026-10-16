# Cards de Jogadores

O modelo Elenco prioriza avatar/foto, nome, posicao, camisa e formacao atual. Os nomes das formacoes respeitam Meu Time e a validacao compartilhada de vagas; jogadores fora dos campos aparecem como Disponivel. Sem Meu Time instalado, o cadastro funciona normalmente e informa Sem formacao definida.

Ver perfil continua levando para a ficha existente. Editar e Desativar ficam no menu de opcoes. A desativacao exige confirmacao visual e mantem o endpoint original com POST e CSRF. Cancelar a confirmacao nao envia o formulario. O aviso informa que posicao e camisa serao liberadas, conforme o comportamento ja existente do endpoint.

## Ranking de participacao

Cada card tem uma area reservada, com traco e identificacao de ranking ainda nao calculado. Nao sao mostrados pontos ficticios, posicoes por ordem de cadastro ou a classificacao antiga baseada em presencas. A regra geral de um ponto por partida finalizada sera implementada separadamente.

Nenhuma tabela ou historico foi alterado nesta entrega. As tabelas de partidas/presencas nao sao consultadas para preencher esse espaco.

## Atualizacao

Depois do deploy, sincronizar o modulo Jogadores no projeto. O layout utiliza as variaveis de tema existentes, com cards responsivos e duas colunas no celular. A foto possui iniciais como alternativa quando nao ha arquivo cadastrado.

## Testes

- `docker exec app_php php /var/www/html/scripts/tests/player_cards_test.php`
- `node scripts/tests/player_cards_ui.cjs` com Playwright disponivel via NODE_PATH.
- `docker exec app_php php /var/www/html/scripts/tests/football_modules_test.php`

Os testes usam banco temporario, verificam cadastros e formacoes preservados, funcionamento sem Meu Time, identificacao de nomes personalizados, escape, controle de acesso e CSRF. A verificacao visual cobre temas claro, escuro e sunset, largura de 320 a 1440px, carregamento de foto, menu, confirmacao e cancelamento de desativacao.
