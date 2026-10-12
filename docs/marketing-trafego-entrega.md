# Terceira entrega: passos 9 e 10

Escopo correto: relatorios, encerramento e preparacao para uso comercial. A aprovacao versionada e um ajuste complementar preservado. Nao ha financeiro, cobranca, carteira, APIs bancarias, APIs de anuncios ou publicacao automatica.

## Passo 9: relatorios e encerramento

- Studio e administrador ativo do projeto consultam o mesmo relatorio consolidado autenticado, parcial ou final, com destino, campanha, plano, periodo, dados diarios, fontes, evidencias, totais e grafico de cliques.
- Imprimir pelo navegador permite imprimir ou salvar em PDF. CSV exporta os dados diarios; valores ausentes ficam vazios. Formulas em campos textuais sao neutralizadas para abertura em planilhas.
- Cobertura explicita informa dias registrados e dias com cada metrica. Totais parciais nao significam cobertura de todo o periodo. Cliques nao sao convertidos em visitas; nao ha garantia de volume de acessos, resultado comercial ou validacao automatica de evidencias.
- Concluir veiculacao registra o termino na plataforma, sem fechar imediatamente a conferencia dos dados. O Studio pode completar/corrigir os resultados antes de encerrar a entrega.
- Encerrar entrega exige veiculacao concluida, conversa encerrada, pelo menos um registro diario, resumo com limitacoes, referencia final e confirmacao explicita do operador.
- Encerramento guarda snapshot imutavel com resumo, referencia, responsavel, data e hash de integridade. O cliente recebe o registro no historico e consulta o relatorio final no painel. Nao ha email automatico, aceite automatico do cliente ou comprovacao independente da plataforma.
- Apos encerrar, resultados ficam somente para consulta e exportacao. Nao existe reabertura ou sobrescrita do relatorio final; eventual correcao formal versionada sera uma etapa posterior.
- Nenhum lead, contato da conta ou dado bancario aparece no relatorio consolidado. O relatorio nao e publico: requer autenticacao e escopo do projeto; o Core exige administrador.

Tabela idempotente no Core: `content_studio_traffic_deliveries`. Pedidos anteriores permanecem consultaveis e nao sao encerrados automaticamente. Modulo Trafego 0.6.0.

## Passo 10: preparacao comercial

O Core mostra um checklist de registros operacionais, sem marcar o servico como liberado para venda. A preparacao comercial nao e uma certificacao nem uma implementacao financeira. Pendencias obrigatorias antes de venda:

1. Validar o ciclo completo manualmente em projeto de teste: briefing, conversa, plano, aprovacao, atividade real na plataforma, resultados, conclusao e entrega final.
2. Conferir o que cada plataforma realmente disponibiliza e como cobra nos testes. Definir precos, custo de midia, remuneracao do servico e tratamento de ajustes. Nenhum calculo e aplicado nesta entrega.
3. Definir oferta e limites mensuraveis: o que sera acompanhado, periodicidade, prazo, fontes, diferenca entre cliques e visitas e limites de atribuicao. Nao prometer leads ou vendas.
4. Validar cadastro do negocio, responsabilidade pelo conteudo, consentimentos na landing page e canais de atendimento. A declaracao atual do cliente nao substitui verificacao documental.
5. Formalizar aprovacao, atendimento, cancelamento, devolucao e documentacao fiscal com apoio apropriado. Nao assumir que o simples registro de veiculacao decide todos os casos de devolucao.
6. Revisar anuncios e destinos nas plataformas e testar manualmente, sem usar dados ou credenciais dos clientes nos relatorios.
7. Testar permissao de acesso, isolamento entre projetos, CSRF, URLs, exportacao, erros e conflitos de revisao.
8. Validar ambiente de producao: acessos administrativos, HTTPS, backups e restauracao dos bancos e arquivos, disponibilidade e capacidade do servidor, logs e procedimento de recuperacao. Estrutura no Git nao substitui backup de dados do servidor.
9. Fazer deploy, sincronizar o modulo da base Marketing e validar o relatorio no Core e no projeto. Registrar evidencias dos testes e autorizar manualmente o piloto comercial somente depois de resolver as pendencias.

## Testes

`scripts/tests/traffic_operations_test.php`, `scripts/tests/traffic_requests_test.php` e `scripts/tests/traffic_requests_ui.cjs`. Bancos temporarios nos testes; nao executar a simulacao contra dados de clientes ou lancar anuncios automaticamente.
