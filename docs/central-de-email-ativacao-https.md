# Ativação HTTPS da origem — plano para revisão

Estado em 19/10/2026: HTTPS da origem ativado após autorização explícita do proprietário. Certificado e chave protegidos no servidor, pasta root 0700, arquivos 0600. Cloudflare ainda não alterada nesta etapa; Central continua desabilitada.

Servidor: `157.230.214.72`. Compose atual: `/opt/workspace/docker/docker-compose.yml` e `/opt/workspace/docker/docker-compose.prod.yml`, projeto `docker`. Container web `app_php`, rede `docker_default`. Porta 443 livre; UFW ativo. Apache tem mod_ssl instalado, desabilitado; formato seguro dos callbacks ainda ausente. HTTP local respondeu 200.

## Arquivos preparados

Release privada:

```text
/opt/private-mail-center/tls/release-ff1eb07b4c61e61a22331078097b44fed9e85236fad65606065bf15fdf2bf22e
```

Contém certificado, chave correspondente, CA pública oficial e exemplos de VirtualHost/redenção dos logs. Validação local em Apache isolado aprovou assinatura, nome, chave e handshake HTTPS. O servidor repetiu a validação criptográfica depois da transferência. A configuração Apache ainda precisa ser validada contra a imagem exata de produção antes da ativação.

## Ação autorizada e executada

Ativar HTTPS somente no serviço web, preservando o HTTP atual e sem publicar a Central. Usar a imagem atual, sem build, sem migrações, sem alterações de banco ou ENV e sem reiniciar banco/phpMyAdmin. A recriação do container web pode causar uma breve indisponibilidade do Core. Não mudar a Cloudflare nesta ação.

Antes de executar, capturar os hashes dos dois arquivos Compose, identificação da imagem e estado das portas; interromper se divergirem da prévia. Fazer backup da configuração necessária à reversão. Gerar override privado externo ao workspace, com porta `443:443`, mounts somente leitura para certificado/chave/VirtualHost/formato seguro dos logs e comando que habilita SSL e esse formato antes de iniciar Apache. Não montar ou habilitar a configuração de provedores nesta etapa.

Validar o Compose combinado sem imprimir variáveis ou credenciais. Validar Apache em container temporário com a imagem atual, sem rede externa e sem montar ENV/dados reais. Somente depois recriar `app` com os dois Compose atuais mais o override, usando `--no-build --no-deps`. Examinar o comportamento do UFW e do encaminhamento Docker antes de qualquer mudança de firewall; não desabilitar o firewall nem abrir outras portas.

Conferir HTTP e HTTPS diretamente na origem, usando CA oficial e hostname correto, sem desabilitar a verificação TLS. A validação HTTPS deve usar `--resolve meuprojetoweb.com:443:157.230.214.72 --cacert <CA oficial>`. Conferir também login e acesso público pela Cloudflare, que permanece em Flexível. Não registrar cookies ou parâmetros de OAuth.

## Reversão

Se houver falha na sintaxe, prontidão ou saúde do Core, remover o override da chamada e recriar somente `app` com os dois Compose originais e a imagem preservada, sem build e sem alterar banco/arquivos da aplicação. Conferir HTTP local e acesso público. Certificado/chave ficam guardados na pasta privada; não é necessário apagá-los para reverter. Cloudflare ainda em Flexível continua usando o caminho HTTP anterior.

## Depois do HTTPS da origem

Antes de Full (strict), verificar os demais hosts com proxy da zona e seus destinos. Se não estiverem preparados, aplicar modo estrito somente ao hostname do Core por regra compatível. Conferir reconhecimento de HTTPS/cookies seguros no Core antes da integração OAuth. Integrar o override TLS ao fluxo de deploy para que uma publicação futura não remova a porta 443.

Continuam separados: backup atualizado incluindo arquivos privados, integração ENV/mounts da Central, revisão de logs em todas as camadas, rebuild/revisão da release, migrações e ativação autorizada da Central. Não transportar tokens locais.

## Resultado da execução

Em 19/10/2026, `central-contas-ativar-tls.sh` validou Apache na imagem exata em container temporário sem rede, preservou snapshots privados e recriou somente `app` sem build. Override ativo externo: `/opt/private-mail-center/tls/docker-compose.origin.yml`. Imagem da aplicação preservada; sem alterações de banco/ENV/firewall/Cloudflare ou ativação da Central.

Backup de configuração e logs privados: `/opt/private-mail-center/tls/activation-20261019T181226Z-qqw9ac5D`. HTTP local, HTTPS local e acesso público retornaram 200. Uma segunda conferência externa diretamente em `157.230.214.72`, com nome meuprojetoweb.com e CA oficial, retornou HTTPS 200 e verificação TLS 0. Reversão não precisou ser executada em produção. O teste simulado isolado cobriu sucesso, bloqueio de repetição e restauração por falha de saúde.

O deploy local foi adaptado para incluir o override externo quando existir, preservando o TLS em uma publicação futura; sintaxe PowerShell conferida. O pacote anterior ainda deve ser reconstruído para incorporar essa adaptação. Próximo: modo Full (strict) somente para o hostname canônico, por regra de configuração, sem afetar outros destinos da zona.

O proprietário posteriormente confirmou a implantação da regra Full (strict) para o hostname canônico na Cloudflare. A conferência externa posterior retornou login público 200 sem redirecionamentos, página inicial 200 após um redirecionamento e origem direta 200, todos com verificação TLS aprovada. O conteúdo da regra não foi lido por API; a confirmação do modo vem do proprietário. Não houve envio de e-mails ou ativação da Central.
