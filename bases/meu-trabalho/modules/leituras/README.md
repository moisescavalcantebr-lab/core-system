# Leituras

Modulo principal e independente para acompanhamento pessoal da producao de leituras. Nao integra nem envia dados a sistemas da empresa.

## Dados

- `leituras_reasons`: cadastro reutilizavel de numero da razao e nome do local, isolado por administrador. Numero unico por usuario; zeros iniciais sao normalizados.
- `leituras_readings`: um registro por usuario e dia, vinculado a uma razao. Guarda numero e local historicos; renomear o cadastro nao reescreve lancamentos anteriores.
- Dados recebidos: data, razao, total, LIS-S e LIS-N. LIS-S (com impressao) e LIS-N (sem impressao) sao referencias independentes: nao compoem nem validam total, executadas ou enviadas. As chaves e colunas internas `lis` e `leit` foram mantidas para preservar os dados existentes; nao ha migracao de banco nesta renomeacao.
- Resultados: executadas e enviadas podem ficar nulos ate serem informados. Zero e um resultado explicito. Pendentes = total - executadas; nao fica conhecida antes da execucao ser informada.
- Inteiros entre zero e um milhao. Executadas <= total; enviadas <= executadas.
- Razao usada nao pode ser excluida: pode ser desativada e reativada. Registros anteriores continuam editaveis com a razao inativa. Exclusao de lancamentos exige confirmacao.
- Escritas usam CSRF, transacao, bloqueio de linha e revisao para impedir que uma tela antiga sobrescreva uma edicao recente. Banco e consultas preservam isolamento por usuario.

## Dashboard

Resumo do mes de trabalho aberto, producao mensal dos ultimos 12 meses, referencias LIS-S/LIS-N e producao diaria. A tela do modulo permite escolher mes e razao. Comparacao e com o mes calendario imediatamente anterior, nunca com o ultimo mes que tiver dados.

Na tabela diaria, `Total anterior` soma os lancamentos da mesma razao no mes calendario anterior. Zero registrado aparece como zero; ausencia de historico aparece como `-`. A variacao em quantidade aparece junto ao total atual. A comparacao usa o identificador estavel da razao, inclusive apos renomear seu numero ou local.

## Fechamento Manual

- `leituras_workspaces` mantem o mes em andamento de cada usuario. No primeiro uso inicia no mes mais recente com leituras, ou no mes corrente quando nao ha registros. Depois disso, a virada do calendario nao altera esse mes.
- `leituras_periods` mantem revisao e data do arquivamento. `Arquivar mes e iniciar proximo` fecha o mes inteiro (independentemente do filtro de razao) e abre o proximo mes calendario nao arquivado. Nao copia leituras nem apaga registros existentes. Razoes permanecem reutilizaveis.
- Antes de arquivar, a tela e a confirmacao exibem quantidade de lancamentos e resultados ainda nao informados. O usuario decide o fechamento; o sistema nao considera ausencias como zero nem exige que enviadas esteja informada.
- Meses arquivados ficam disponiveis em `Arquivos` e continuam nos graficos e comparacoes. Nao permitem inserir, editar, excluir ou mover leituras ate uma reabertura manual.
- `Reabrir mes` torna esse mes o periodo de trabalho para correcoes. Ao arquiva-lo novamente, dados existentes no proximo mes sao preservados.
- Edicoes e fechamentos usam bloqueio comum por usuario e revisao mensal, evitando que uma conferencia desatualizada arquive alteracoes nao vistas. A atualizacao do esquema apenas adiciona tabelas idempotentes, sem migrar ou remover leituras existentes.

Totais de resultados ausentes mostram `-`; agregados parciais mostram cobertura em dias. Diferencas LIS-S/LIS-N so aparecem quando os dois meses possuem referencia preenchida em todos os dias cadastrados. Percentual nao e calculado quando o anterior e zero. Comparacao considera apenas dias lancados, nao presume que o mes esta encerrado nem estima dias nao cadastrados.

## Instalacao e Testes

Manifesto segue o contrato do Core. Schema idempotente e copia administrativa seguem a instalacao normal em bases e sincronizacao em projetos. Sem dependencia de Holerite, Contas, Participantes ou addons. Instalacao nao cria razoes nem leituras reais.

`php modules/leituras/tests/check.php`

No Docker local: `docker exec -e LEITURAS_MYSQL_TEST=1 app_php php /var/www/html/modules/leituras/tests/check.php`. Testes de banco criam uma base temporaria exclusiva e a removem ao terminar.
