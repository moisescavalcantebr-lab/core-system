# Publicação dos ajustes de organização

Escopo autorizado em 19/10/2026: publicar os ajustes locais da Central, mantendo somente Lixeira e restauração. Removido da interface o botão de marcar mensagens lidas como não lidas; mensagens ainda não lidas oferecem Marcar como lida. A diferenciação visual permanece.

## Pacote e execução

`scripts/central-contas-publicar-organizacao.ps1 -PackageOnly` prepara um pacote de vinte arquivos da Central, sem ENV, senhas, tokens, dependências ou outros módulos. Usa como baseline o pacote original da instalação e as duas correções pontuais já publicadas: filtro da Lixeira e prioridade de mensagens recentes do Gmail. Divergência inesperada interrompe a publicação.

Sem PackageOnly, conecta por senha SSH ao servidor confirmado 157.230.214.72. A senha é digitada pelo proprietário somente no terminal. O pacote vai para uma pasta privada em `/opt/private-mail-center/releases`, com checksum e validação de sintaxe. Salva o código/configuração anteriores, instala os arquivos, habilita `remote_organization_enabled` na configuração privada preservando o inode montado no container e faz reload do Apache. Não troca a imagem Docker, não recria o banco, não aplica migrações e não importa tokens locais.

A conferência após a publicação verifica hashes, schema existente, login público HTTP 200 e restrição anônima da Central HTTP 404. Falha de publicação restaura os arquivos e a configuração anteriores. Falha posterior de sincronização é reportada separadamente, sem repetir escrita nem reverter código saudável. Relatório técnico local: `_deploy/mail-organization-publication.txt`.

Conexões com permissões antigas continuam lendo/enviando normalmente. As ações remotas só aparecem depois de o proprietário conceder as permissões adicionais **em produção**, na opção Autorizar organização de mensagens em Configurações. O fato de o consentimento existir no Docker local não o transfere ao servidor.

## Quantidade de mensagens

O sincronizador processa até dez mensagens por caixa em cada lote, sujeito ao prazo da requisição. A importação inicial usa uma janela de trinta dias; o cache anterior continua existindo, por isso um ambiente usado por mais tempo pode ter mensagens mais antigas. Listas locais/unificadas paginam de vinte e cinco em vinte e cinco; cinquenta não é um limite total.

O diagnóstico mostra apenas contagens, filas, paginação, erros técnicos e permissões, sem assuntos, remetentes, conteúdo ou tokens. A validação de produção executa no máximo cinco lotes por caixa, aguardando entre lotes e parando ao concluir o ciclo ou encontrar erro. Não envia, move ou exclui e-mails no provedor. Pode ser necessário continuar manualmente se ainda houver páginas pendentes. Não foi criado agendamento.

Comparação local antes da publicação: Gmail com 53 registros disponíveis, 49 recebidos nos últimos trinta dias, histórico com mais páginas e sem erro/cooldown; Zoho com seis registros e ciclo de polling completo. Não se deve exigir igualdade absoluta entre caches independentes com idades e contextos diferentes.

Validação local: 516 verificações da suíte piloto e oito cenários de interface em desktop/celular e temas claro/escuro, com banco descartável e APIs/páginas simuladas. O pacote foi conferido contra a baseline original, a sintaxe Bash foi validada e o diagnóstico local não acessou provedores.
