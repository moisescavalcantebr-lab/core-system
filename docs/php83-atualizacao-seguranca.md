# Atualizacao de seguranca PHP 8.3 — Core

Data: 22/10/2026. Execucao autorizada e realizada somente no Docker local.

## Motivacao e fontes

Os avisos oficiais CVE-2026-109618 e CVE-2026-109619 afetam PHP 8.3 anterior a 8.3.36. O primeiro inclui demonstracao de execucao remota sem autenticacao em Apache/mod_php. Reiniciar a imagem antiga ou ocultar erros nao corrige o runtime.

- [PHP 8.3.36, release de seguranca](https://www.php.net/releases/8_3_36.php)
- [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
- [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)
- [Imagem oficial PHP](https://github.com/docker-library/official-images/blob/master/library/php)
- [Imagem oficial phpMyAdmin](https://github.com/docker-library/official-images/blob/master/library/phpmyadmin)

## Diagnostico e plano executado

1. Inspecionar os containers e recuperar os arquivos Compose efetivamente usados.
2. Guardar imagens anteriores, dump logico de TODOS os bancos, arquivos da aplicacao e pasta privada de e-mail/TLS fora do workspace, com ACL restrita.
3. Restaurar o dump em MySQL local isolado, sem rede/portas e com limite de memoria.
4. Construir e verificar PHP 8.3.36 antes de recriar somente `app_php`.
5. Validar runtime CLI e Apache/HTTPS, extensoes, Core e projetos.
6. Atualizar `app_pma` separadamente: ele possui outro PHP.
7. Limpar fixtures e comparar os dados com o backup original restaurado.
8. Registrar os arquivos no Git. Preparar a producao, sem executar deploy remoto.

## Ambiente encontrado e resultado local

| Componente | Antes | Depois |
| --- | --- | --- |
| Core `app_php` | PHP 8.3.35 | PHP 8.3.36 |
| Apache do Core | 2.4.68, mod_php/prefork | 2.4.69, mesma serie 2.4 |
| Sistema da imagem Core | Debian 13/Trixie | Debian 13/Trixie |
| phpMyAdmin `app_pma` | PHP 8.3.26 | PHP 8.3.36, phpMyAdmin 5.2.3 |
| MySQL `app_db` | 8.0.45, healthy | Mesmo container, imagem e volume |
| Volume de dados | `docker_db_data` | Preservado |
| Rede | `docker_default` | Preservada |
| Portas Core | loopback 8000 e 8443 | Preservadas |
| Porta phpMyAdmin | loopback 8080 | Preservada |

O contexto local e `desktop-linux`, projeto Compose `docker`. O Core usa, nesta ordem:

1. `docker/docker-compose.yml`
2. `docker/docker-compose.mail-center.local.yml`
3. `docker/docker-compose.mail-center.example.yml`

O terceiro arquivo, apesar do nome/comentario de exemplo, esta carregado no container real e fornece o mount de segredos. A variavel de interpolacao `MAIL_CENTER_PRIVATE_DIR` nao estava definida no shell. Os scripts a recuperam do estado salvo, validam o Compose e restauram o valor anterior do processo.

Mounts preservados: workspace em `/var/www/html`; certificados e segredos externos somente leitura; anexos privados com escrita; vhost TLS local somente leitura. Nao foram sobrescritos arquivos `.env`, certificados, credenciais, configuracoes de dominio ou Cloudflare.

`docker inspect app_php --format '{{.Config.Image}}'` retornou `docker-app`. Esse e o nome da imagem personalizada; a versao deve ser consultada no PHP. A ausencia de saida relatada anteriormente nao foi reproduzida, portanto nao foi atribuida uma causa sem evidencia.

## Arquivos e protecoes para novas instalacoes

- `docker/php/Dockerfile`: base exata `php:8.3.36-apache-trixie`, fixada pelo digest do indice oficial `sha256:756c2884677bedea30afaa95811b42cd3ff251203291c5820c1ad209e86c9fb4`.
- `docker/php/verify-runtime.php`: build falha se PHP sair da serie 8.3, ficar abaixo de 8.3.36, perder extensoes necessarias ou suporte GD a JPEG/PNG/WebP.
- `docker/phpmyadmin/Dockerfile`: base oficial `phpmyadmin:5.2.3-apache`, digest `sha256:62a50b1419fe55b9b4c3cd6f5aed03a18f8f5b4a236f24735d76400bea75a5ff`, com verificacao do PHP corrigido. Arquiteturas com rebuild atrasado falham na verificacao.
- `docker/docker-compose.yml`: phpMyAdmin passa a ser construido a partir desse Dockerfile, imagem local `core-phpmyadmin:5.2.3-php8.3.36`.
- Scripts `php83-backup-local.ps1`, `php83-aplicar-local.ps1`, `php83-phpmyadmin-local.ps1` e `php83-validar-dados-local.ps1`: backup, aplicacao restrita a um servico, rollback e verificacao de dados.
- Testes `php83_runtime_ui.cjs`, `php83_database_snapshot.php` e ajuste da sessao sintetica em `crypto_binance_test.php` para incluir autenticacao recente exigida pela Central.

PDO, MySQLi, GD, curl, dom, fileinfo, mbstring, sodium, OpenSSL, sessao e OPcache foram preservados. Nao houve atualizacao de Composer, schemas, instaladores, regras de negocio ou versao principal de PHP/Apache/MySQL. Rewrite, SSL, `.htaccess` e protecao de logs dos callbacks OAuth foram mantidos.

Fixar um digest evita mudancas silenciosas; futuras correcoes exigem revisar o tag/digest e reconstruir deliberadamente. Nao usar imagens antigas em cache como prova de atualizacao.

## Validacao

Passaram:

- Build limpo com `--pull --no-cache`; verificacoes de versao, extensoes, GD e Apache antes/depois da troca.
- Comparacao da lista completa de extensoes com a imagem anterior: mesma lista.
- Composer autoload/platform check.
- PHP 8.3.36 pelo SAPI web `apache2handler`, via HTTPS local.
- GET autenticado de dashboard, projetos, modulos, paginas e contas da Central, sem avisos PHP.
- Bloqueio HTTP de arquivo privado; endpoint temporario de verificacao protegido por nonce e removido ao terminar.
- Cripto: provisao real de projeto de teste, schemas/copias, assinatura somente leitura, idempotencia, isolamento de demonstracao e rollback.
- Primeiro acesso, login por senha, token invalido/expirado/usado, CSRF, consumo atomico de token e rollback entre bancos.
- Interfaces Cripto e Financeiro do Time em 1280 e 390 pixels.
- Financeiro do Time: recebimentos parciais, caixa, concorrencia, grupos/rateios, trinta jogadores, centavos e rollback.
- Central de E-mail: 122 verificacoes em banco sintetico, incluindo dono, CSRF, CRUD, concorrencia, isolamento, XSS e rotas.
- phpMyAdmin: HTTP 200 com PHP/8.3.36, sintaxe Apache e conexao ao MySQL 8.0.45.
- Comparacao final com o backup original restaurado: checksums iguais nas 83 tabelas verificadas, incluindo Core, os dois projetos reais e schemas de teste preexistentes. Nenhum banco de fixture permaneceu ativo.

Os testes usaram dados sinteticos. Nao enviaram e-mails, nao sincronizaram contas reais, nao operaram Binance e nao alteraram senhas dos projetos reais. As fixtures foram removidas. A verificacao de checksums deve ser feita com acesso a todos os bancos: o usuario de provisionamento dos projetos enxerga menos tabelas que o usuario do Core.

A primeira troca acionou rollback por uma comparacao literal entre caminhos Windows com barras diferentes. A equivalencia dos mounts foi conferida, a comparacao foi normalizada e a troca seguinte passou. A protecao da Central tambem recusou inicialmente a sessao sintetica sem data de autenticacao recente; foi corrigida apenas a fixture de teste.

Cobertura: testes de regressao representativos, nao auditoria completa de todos os fluxos e addons. O servidor nao foi inspecionado novamente nem atualizado nesta execucao.

## Backup e rollback local

O backup desta execucao esta fora do site e do Git, em uma subpasta `php83-20261022-180456` de `../private-core-backups`. Contem arquivos sensiveis; nao publicar nem anexar seu conteudo ao chat. A pasta tem heranca de ACL removida e acesso concedido ao usuario atual.

Inclui dump de todos os bancos (Core, projetos e schemas de teste preexistentes), imagem PHP anterior, imagem phpMyAdmin anterior, arquivos do workspace, pasta privada de e-mail/TLS, inspect privado dos containers, arquivos Compose e checksums. O arquivo `state.json` registra o rollback e os IDs originais. As imagens antigas nao foram removidas.

Para novas execucoes locais, no PowerShell a partir da raiz do workspace:

```powershell
./scripts/php83-backup-local.ps1
# Use exatamente a pasta BACKUP_VERIFIED retornada; nao uma pasta de outro ambiente.
$backup = 'CAMINHO_ABSOLUTO_DO_BACKUP_VERIFICADO'
$docker = 'C:\Program Files\Docker\Docker\resources\bin\docker.exe'
& $docker build --pull --no-cache -t core-php:8.3.36-security-local docker/php
if ($LASTEXITCODE -ne 0) { throw 'Build PHP falhou' }
./scripts/php83-aplicar-local.ps1 -Backup $backup
& $docker build --pull --no-cache -t core-phpmyadmin:5.2.3-php8.3.36 docker/phpmyadmin
if ($LASTEXITCODE -ne 0) { throw 'Build phpMyAdmin falhou' }
./scripts/php83-phpmyadmin-local.ps1 -Backup $backup
./scripts/php83-validar-dados-local.ps1 -Backup $backup
```

Nao executar novamente apenas para repetir verificacoes ja concluidas. O MySQL temporario existe somente no Docker local, sem portas/rede, com limite de 768 MiB e buffer de 64 MiB. Sua limpeza remove exclusivamente o container de nome unico e seu volume anonimo; nunca o volume da aplicacao.

Rollback do runtime, caso necessario:

```powershell
./scripts/php83-aplicar-local.ps1 -Backup $backup -Rollback
./scripts/php83-phpmyadmin-local.ps1 -Backup $backup -Rollback
```

Cada comando afeta somente seu servico. Se a imagem antiga saiu do cache, carrega-la primeiro do respectivo `.tar` do backup. Os scripts verificam IDs/estado local e falham diante de uma stack diferente.

Nao restaurar banco automaticamente para resolver falha de runtime: isso descartaria dados gravados depois do backup. A imagem antiga e vulneravel; rollback e recuperacao temporaria de disponibilidade, nao solucao permanente.

## DigitalOcean — diagnostico de 22/10, atualizacao nao executada

Diagnostico de leitura concluido em 22/10/2026, 22:48 UTC, com autenticacao por senha em terminal. A chave local foi recusada; a conexao interativa funcionou. Nenhuma imagem foi enviada/carregada e nenhum container remoto foi reiniciado.

| Item remoto confirmado | Estado |
| --- | --- |
| Arquitetura | x86_64 / linux amd64 |
| Memoria | 961 MiB totais, aproximadamente 80 MiB disponiveis |
| Swap | Ausente |
| Disco raiz | 24 GiB; aproximadamente 13 GiB livres |
| Core | PHP 8.3.35, Apache 2.4.68 |
| phpMyAdmin | PHP 8.3.26, porta restrita a 127.0.0.1:8080 |
| MySQL | 8.0.46, healthy; container em execucao ha aproximadamente tres horas |
| Volume MySQL | docker_db_data em /var/lib/mysql |
| Compose | Projeto docker, rede docker_default |

O MySQL remoto e 8.0.46, diferente do local 8.0.45. Ele nao deve ser atualizado nem recriado nesta correcao. O tempo de execucao de tres horas, isoladamente, nao comprova a causa de eventual reinicio.

Os quatro arquivos Compose reais de app_php sao, em ordem:

1. `/opt/workspace/docker/docker-compose.yml`
2. `/opt/workspace/docker/docker-compose.prod.yml`
3. `/opt/workspace/docker/docker-compose.mail-center.prod.yml`
4. `/opt/private-mail-center/tls/docker-compose.origin.yml`

O servidor tem mounts privados adicionais em relacao ao local: `core-config.php`, `core-env-base.php`, certificado/chave e configuracao de logs/TLS da origem. Esses mounts sao somente leitura e precisam permanecer identicos. Portas atuais do app: 80 e 443 publicas, mais 127.0.0.1:8000. Nao copiar o override TLS local para producao.

**Pendencia de capacidade antes da publicacao:** o servidor ainda tem 1 GB, pouca memoria disponivel e nenhum swap. Considerando o historico de OOM, a recomendacao e ampliar a RAM para 2 GB antes de carregar imagens ou recriar servicos. Nao criar um segundo MySQL nesse servidor. Upgrade, parada e alteracao de custo precisam da escolha do usuario; nao foram executados.

Pacote local das duas imagens ja verificadas, arquitetura amd64, preparado fora do site/Git:

- Arquivo: `../private-core-backups/php83-20261022-180456/php83-corrected-images.tar`
- Tamanho: 199308288 bytes.
- SHA256: `4c96c24d6c610efe6b899d4f056bbc8c58f979a3ddb449d000f1ead4269b9add`.
- Manifesto: `php83-corrected-images.sha256`, na mesma pasta privada.

Antes da aplicacao, ainda e necessario backup remoto recente de bancos/arquivos/Compose/imagens e teste de restauracao local com MySQL 8.0.46, preservando as diferencas em relacao ao ambiente local. Tambem e necessario conferir o conteudo efetivo do Compose em arquivo privado antes de definir o instalador final e garantir que deploys futuros mantenham a imagem corrigida.

### Historico e procedimento preparado

O destino confirmado e `157.230.214.72`, workspace `/opt/workspace`. A tentativa anterior expirou durante o banner SSH; o diagnostico atual por senha funcionou e confirmou os dados acima. O atalho SSH `meuprojetoweb` historicamente apontava para outro IP; nao o usar como substituto sem conferir.

Comandos de leitura usados para conferir o servidor; repetir se o estado mudar antes da aplicacao:

```bash
docker inspect app_php --format '{{.Config.Image}}'
docker inspect app_php --format '{{index .Config.Labels "com.docker.compose.project"}}'
docker inspect app_php --format '{{index .Config.Labels "com.docker.compose.project.working_dir"}}'
docker inspect app_php --format '{{index .Config.Labels "com.docker.compose.project.config_files"}}'
docker inspect app_db --format '{{range .Mounts}}{{.Type}} {{.Name}} {{.Source}} {{.Destination}}{{println}}{{end}}'
docker exec app_php php -v
docker exec app_php php -m
docker exec app_php apache2ctl -v
docker exec app_pma php -v
uname -m
free -h
df -h
```

Guardar inspect e Compose resolvido completos somente em pasta privada com `umask 077`: podem conter credenciais. Nao exibir todas as variaveis no chat.

Depois do diagnostico remoto e da autorizacao especifica:

1. Fazer snapshot recente na DigitalOcean e backup logico de TODOS os bancos e arquivos privados; verificar checksums e restauracao no cliente local. O backup antigo de tres dias nao substitui isso.
2. Salvar imagens PHP/phpMyAdmin atuais e Compose resolvido, com a lista ordenada de overrides reais. Preservar `.env`, TLS, e-mail, dominio, portas, redes e mounts.
3. Confirmar arquitetura. Preferir construir/verificar as imagens localmente e transferir os arquivos com SHA256 via SSH/SCP, para evitar compilacao e segundo MySQL no servidor com pouca RAM.
4. Carregar as imagens no servidor e usar override contendo SOMENTE `image` e `pull_policy: never` dos servicos a atualizar, mantendo todos os arquivos Compose existentes. A lista `COMPOSE` deve vir dos labels remotos, nao ser copiada do local.
5. Atualizar primeiro `app`, validar CLI/web/Core/MySQL e depois atualizar `phpmyadmin` separadamente. Conferir o ID e o volume de `app_db` antes/depois.

Modelo de comandos para transporte (a arquitetura deve corresponder ao servidor):

```powershell
# Pasta privada fora do site/Git; criar somente depois de confirmar o destino.
& $docker image save --output "$backup/php83-corrected-images.tar" core-php:8.3.36-security-local core-phpmyadmin:5.2.3-php8.3.36
if ($LASTEXITCODE -ne 0) { throw 'Exportacao falhou' }
Get-FileHash "$backup/php83-corrected-images.tar" -Algorithm SHA256
# Transferir via SCP para pasta privada e conferir o mesmo hash no servidor.
```

Modelo remoto, ainda dependente dos caminhos/Compose confirmados:

```bash
# Depois de conferir SHA256 com o hash registrado na origem:
docker image load --input "$PRIVATE_RELEASE/php83-corrected-images.tar"
# COMPOSE e um array Bash com projeto e TODOS os arquivos reais, em ordem.
# PHP_IMAGE_OVERRIDE e arquivo privado que aponta app para a imagem corrigida.
"${COMPOSE[@]}" -f "$PHP_IMAGE_OVERRIDE" config --quiet
"${COMPOSE[@]}" -f "$PHP_IMAGE_OVERRIDE" up -d --no-build --no-deps --force-recreate app
docker exec app_php php /usr/local/lib/core-verify-runtime.php
docker exec app_php apache2ctl -t
# Validar HTTPS, autenticacao e dados antes de continuar com phpMyAdmin.
```

Rollback remoto: mesmo Compose original, override apontando para a imagem anterior, `up -d --no-build --no-deps --force-recreate app`; para PMA, procedimento separado. Nao restaurar bancos por padrao.

Os exemplos remotos nao sao um comando pronto para colar: `COMPOSE`, caminhos privados, overrides e arquitetura precisam ser determinados no servidor real. Nao executar `deploy-atualizar-servidor.ps1` para esta correcao isolada: ele publica codigo e pode executar migracoes nao relacionadas. O deploy Docker local generico tambem nao substitui rebuild do runtime.

Proibido nesta atualizacao: `down -v`, limpeza indiscriminada, recriar MySQL, mudar versoes principais, executar instaladores/migracoes ou sobrescrever `.env` de producao. Nenhum desses passos foi executado localmente.

### Upgrade de RAM proposto

Conforme a documentacao oficial da DigitalOcean, pode-se redimensionar o Droplet existente, preferindo CPU/RAM sem aumentar o disco quando disponivel. A operacao exige parada; fazer backup/snapshot recente antes e desligamento ordenado pelo sistema. O preco e a configuracao disponivel devem ser conferidos no painel antes de confirmar. Nenhum upgrade foi executado.

Fonte: https://docs.digitalocean.com/products/droplets/how-to/resize/
