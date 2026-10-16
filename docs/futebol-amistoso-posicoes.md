# Amistoso: visualizacao e posicoes

Partidas 0.2.1 restaura os auxiliares de siglas, iniciais e avatares utilizados por Ver Partida e Escalacao. A ausencia dessas funcoes provocava erro fatal ao renderizar o campo.

Na criacao em Seguir Meu Time, a rotina existente copia as vagas resolvidas da formacao. Ela ja reconhece a posicao escolhida explicitamente em Meu Time 0.3.0, mesmo diferente da principal.

Os campos de Ver Partida e Escalacao agora exibem a sigla da vaga salva na partida, usando a configuracao de campo registrada para ela. A posicao principal permanece no cadastro e nao e alterada pela criacao ou consulta da partida. Nao ha nova coluna nem remapeamento dos jogos existentes.

Mudancas posteriores em Meu Time ou no cadastro nao reescrevem a escala original. Jogos finalizados conservam seu campo salvo.

Validacao: `football_tracking_test.php` cobre criacao e consulta de amistoso, posicao alternativa, avatares, permissao, partida inexistente e preservacao apos mudancas. `football_tracking_ui.cjs` confere Ver Partida e Escalacao em desktop e celular.

Depois do deploy, sincronizar o modulo Partidas na base Futebol e no projeto. Financeiro e regras dos planos nao foram alterados.
