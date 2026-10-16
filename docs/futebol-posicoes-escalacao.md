# Posicoes e escalacao

Jogadores e Meu Time 0.3.0 separam a posicao principal do cadastro da posicao usada na formacao.

- O cadastro oferece todas as posicoes ativas, mesmo utilizadas por outro jogador. O limite de jogadores e a exclusividade do numero da camisa continuam iguais.
- Em Meu Time, selecionar o avatar abre time, posicao na formacao e a opcao desmarcada de atualizar tambem a posicao principal.
- Somente vagas livres da configuracao atual aparecem. A principal e sugerida quando compativel e livre; outras posicoes podem ser selecionadas.
- O servidor revalida time, jogador ativo e vaga sob bloqueio transacional. Uma vaga ocupada ou removida desde a abertura da pagina exige nova escolha, sem alterar o cadastro.
- A opcao de atualizar a principal usa a posicao ativa correspondente no catalogo. Ela e salva na mesma transacao da escalacao.
- `team_roster.field_position_code` identifica escolhas explicitas, independentes da principal. Registros anteriores nulos continuam usando a resolucao legada, sem migracao destrutiva.
- Posicoes removidas da formacao devolvem os jogadores afetados aos disponiveis. Retirar e recolocar permite uma nova escolha.
- A sigla sob o jogador no campo representa sua posicao na formacao; o card de Jogadores continua representando a principal.

Nao ha mudancas nas telas ou regras de Partidas nesta entrega. A revisao especifica da integracao sera feita na proxima etapa.

Depois do deploy, sincronizar Jogadores e Meu Time na base e no projeto. A coluna nova e incluida de forma idempotente ao abrir Meu Time ou ao escalar; novas instalacoes ja a recebem no schema.

Validacao: `team_formations_test.php`, `team_formations_ui.cjs`, `player_cards_test.php`, `player_cards_ui.cjs`, `football_modules_test.php` e regressao `football_tracking_test.php`.
