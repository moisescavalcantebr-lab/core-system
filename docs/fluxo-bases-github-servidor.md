# Fluxo oficial: laboratorio local, GitHub e servidor

Este documento define o fluxo seguro para criar, testar, promover e publicar bases, modulos e addons do Core.

## Objetivo

Separar claramente o que e produto do que e runtime.

- Produto: Core, bases oficiais, modulos, addons, schemas, layouts, paginas-modelo e scripts.
- Runtime: projetos criados por clientes, bancos dos projetos, leads, uploads, comprovantes, configuracoes e dados reais.

O objetivo e evitar que uma atualizacao do VS Code apague ou misture algo criado no servidor, e tambem evitar que o servidor vire laboratorio sem controle.

## Fonte de verdade

O GitHub deve ser a fonte de verdade para:

- Core.
- Bases oficiais.
- Modulos principais.
- Addons.
- Scripts de instalacao, deploy e reparo.
- Documentacao operacional.

O VS Code local e o Docker local sao o ambiente de criacao e teste.

O servidor e o ambiente de producao. Ele executa o produto e guarda os dados reais.

## Regra principal

Tudo que muda estrutura de produto deve nascer ou ser consolidado no laboratorio local antes de ir para o servidor.

No servidor, a rotina normal deve ser:

1. Receber deploy do Git/VS Code.
2. Criar projetos a partir de bases oficiais.
3. Operar projetos, leads, carteira, paginas publicas e dados reais.
4. Gerar backups.

No servidor, a rotina normal nao deve ser:

- Clonar base para criar novo produto.
- Adaptar modulo ou addon.
- Instalar/desinstalar modulos em bases oficiais como experimento.
- Promover uma base sem antes passar pelo laboratorio.

## Ambientes

### VS Code

Local onde o codigo e editado.

Aqui entram:

- Ajustes do Core.
- Criacao de bases novas.
- Criacao e organizacao de modulos.
- Criacao e organizacao de addons.
- Documentacao.
- Commit e push para o GitHub.

### Docker local

Local onde o que foi criado no VS Code e testado antes de virar produto.

Aqui entram:

- Projetos de teste.
- Bases de laboratorio.
- Validacao de modulo instalado.
- Validacao de dashboard, planos, carteira, email e paginas.
- Simulacao do fluxo do cliente sem mexer no servidor.

### GitHub

Fonte oficial do produto.

O GitHub deve receber somente o que foi validado:

- Core funcional.
- Base oficial pronta.
- Modulos e addons revisados.
- Scripts de instalacao/deploy.
- Documentos de operacao.

### Servidor

Ambiente de producao.

Aqui entram:

- Core instalado.
- Bases oficiais protegidas.
- Projetos reais.
- Dados reais.
- Backups.

O servidor nao deve ser a origem de uma nova base de produto. Se isso acontecer por emergencia, deve ser tratado como excecao e sincronizado manualmente antes do proximo deploy.

## Estados de uma base

### Laboratorio

Base criada localmente para teste.

Pode ser clonada, modificada, quebrada, ajustada e apagada.

Nao deve ir para o servidor como produto final.

### Candidata

Base testada localmente e quase pronta.

Ainda pode receber ajustes, mas ja deve seguir o padrao do Core.

### Oficial protegida

Base pronta para virar produto.

Regras:

- Deve estar no GitHub.
- Deve ter sido testada no Docker local.
- Deve estar bloqueada/protegida.
- Deve ter schemas e modulos coerentes.
- Pode ir para o servidor.

### Legacy

Base antiga preservada apenas como referencia ou backup.

Exemplo: `futebol-amador` antigo.

Regra recomendada: nao enviar legacy para producao, a menos que exista motivo claro.

## Fluxo recomendado para criar uma base oficial

1. Clonar a base principal no laboratorio local.
2. Ajustar nome, slug e identidade da nova base.
3. Instalar apenas os modulos necessarios.
4. Instalar addons somente quando fizerem parte do produto daquela base.
5. Criar um projeto de teste local.
6. Testar login, dashboard, menu, carteira, planos e configuracoes.
7. Testar cada modulo instalado.
8. Testar criacao de projeto a partir dessa base.
9. Validar banco/schema.
10. Bloquear/proteger a base.
11. Commitar e enviar ao GitHub.
12. Rodar deploy para o servidor.
13. No servidor, criar apenas projetos reais a partir da base oficial.

## O que deve mudar no Core

### Etapa 1 - Documentar o fluxo

Status: concluida.

Resultado esperado:

- Fluxo oficial registrado.
- Riscos mapeados.
- Proximas etapas definidas.

### Etapa 2 - Criar modo laboratorio

Status: concluida.

Adicionar uma forma clara de o Core saber se esta em:

- `local`.
- `laboratorio`.
- `producao`.

Esse modo pode vir do `env.production.php` ou de uma chave equivalente.

Uso esperado:

- Local: libera clonagem, montagem de bases, instalacao de modulos e testes.
- Producao: bloqueia laboratorio e mostra apenas operacao segura.

Implementacao atual:

- `app/helpers/environment.php` centraliza a leitura do ambiente.
- `app/bootstrap/bootstrap.php` carrega esse helper antes dos servicos.
- `env/env.production.php.example` declara `app.environment`.
- `app/console/install_core.php` cria instalacoes novas como `production` por padrao.

Funcoes disponiveis para as proximas etapas:

- `coreEnvironment()`.
- `coreEnvironmentLabel()`.
- `coreIsProduction()`.
- `coreIsLocal()`.
- `coreIsLaboratory()`.
- `coreLaboratoryEnabled()`.

### Etapa 3 - Bloquear acoes de laboratorio no servidor

Status: concluida.

Mesmo que um botao fique escondido, o backend deve bloquear a acao.

Acoes que devem ser protegidas em producao:

- Clonar base.
- Instalar modulo em base oficial como experimento.
- Desinstalar modulo de base oficial.
- Alterar estrutura de base oficial sem fluxo de promocao.
- Registrar base nova como produto sem validacao.

Implementacao atual:

- `coreRequireLaboratory()` bloqueia acoes quando o ambiente e producao.
- Clonagem de bases foi protegida na tela e na action.
- Registro manual de base foi protegido.
- Instalacao, remocao, configuracao comercial e aplicacao de modulos em bases foram protegidas.
- Em producao, essas acoes so passam se `app.allow_laboratory_in_production` estiver explicitamente `true`.

### Etapa 4 - Ajustar a tela de bases por ambiente

Status: concluida.

No local:

- Mostrar laboratorio.
- Permitir clonar.
- Permitir montar base.
- Permitir instalar/remover modulos.
- Permitir testar.

No servidor:

- Mostrar bases oficiais.
- Permitir criar projetos.
- Permitir sincronizar projeto.
- Permitir atualizar schema de projeto quando necessario.
- Ocultar ou bloquear fluxo de laboratorio.

Implementacao atual:

- `web/admin/bases/index.php` identifica o ambiente e mostra acoes de laboratorio somente quando `coreLaboratoryEnabled()` permite.
- Em producao, bases registradas aparecem como produto publicado/protegido, sem botoes de clonagem, registro, exclusao, bloqueio, sync em lote ou atualizacao estrutural.
- Em producao, pastas de bases ainda nao registradas aparecem apenas como alerta de leitura. O registro deve ser feito no laboratorio e publicado por deploy.
- `web/admin/bases/modules.php` tambem respeita o ambiente.
- No laboratorio, a tela de modulos permite ver disponiveis, abrir cada modulo, configurar, instalar, desinstalar e aplicar nos projetos.
- Em producao, a tela de modulos mostra somente os modulos publicados da base em modo leitura. Instalacao, remocao e configuracao ficam ocultas.
- As actions continuam protegidas no backend pela Etapa 3, entao esconder botoes nao e a unica seguranca.

### Etapa 5 - Separar catalogo de modulos e addons

Status: concluida.

No laboratorio:

- Modulos principais aparecem para montagem.
- Addons aparecem vinculados aos modulos.
- A base recebe somente o que foi escolhido.
- A tela de modulos separa o catalogo em quatro blocos: modulos principais instalados, addons instalados, modulos principais disponiveis e addons disponiveis.
- Cada bloco tem contagem propria e destaque visual diferente para reduzir erro de instalacao.

No servidor:

- O usuario nao precisa ver o catalogo completo.
- A base oficial ja sobe com os modulos/addons definidos.
- O projeto recebe o pacote da base.
- A tela permanece em leitura/consulta, mostrando apenas o pacote publicado da base.

### Etapa 6 - Criar validacao de promocao de base

Status: concluida.

Antes de uma base virar oficial protegida, validar:

- `project.json`.
- `app/database/schema.sql`.
- Modulos instalados.
- Addons instalados.
- Menus.
- Dashboard.
- Arquivos obrigatorios.
- Ausencia de projetos de teste dentro da base.
- Ausencia de arquivos temporarios.

Implementacao atual:

- O deploy normal executa `scripts/servidor-validar-bases-protegidas.ps1` antes de preparar e enviar o pacote.
- A validacao consulta o servidor e lista as bases marcadas como protegidas no banco.
- Se existir uma base protegida no servidor que nao existe na pasta local `bases/`, o deploy e bloqueado.
- Esse bloqueio evita que uma base consolidada no servidor desapareca porque o VS Code/Git ficou atrasado.
- Em servidor novo, sem banco Core instalado ou sem bases protegidas, a validacao nao bloqueia a primeira instalacao.
- Para primeira instalacao ou emergencia existe o parametro `-SkipProtectedBasesGuard`.
- O uso normal deve ser sempre sem `-SkipProtectedBasesGuard`.

Comandos principais:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\servidor-validar-bases-protegidas.ps1
```

```powershell
powershell -ExecutionPolicy Bypass -File scripts\servidor-sincronizar-bases-git.ps1 -Overwrite
```

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-atualizar-servidor.ps1
```

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-atualizar-servidor.ps1 -SkipProtectedBasesGuard
```

No VS Code, as mesmas rotinas estao disponiveis em:

- `Servidor: validar bases protegidas`
- `Servidor: sincronizar bases protegidas com Git`
- `Deploy: atualizar servidor`
- `Deploy: atualizar servidor sem validar bases`

### Etapa 7 - Ajustar o deploy

Status: implantado no deploy do servidor.

O pacote de produto deve enviar somente a estrutura consolidada:

- `app`.
- `bases` oficiais, exceto bases legacy como `bases/futebol-amador`.
- `cron`.
- `docs`.
- `docker`.
- `modules`.
- `scripts` operacionais, exceto `scripts/deploy.local.ps1`.
- `storage/paginas`.
- `web`.
- Arquivos raiz publicos: `.htaccess`, `index.php` e `README.md`.

O pacote de produto nao deve enviar:

- `projects`, porque projetos sao runtime/clientes e entram no fluxo de backup/restauracao.
- `env`, porque contem configuracoes e segredos do ambiente.
- `_backups`, `_deploy`, `_notes`, `output` e `tmp`.
- `.git`, `.codex`, `.agents` e `.vscode`.
- `storage/uploads`, `storage/logs` e `storage/cache`.
- Arquivos soltos de banco local, como `backup.sql` e `Docker MySQL.session.sql`.

Regra operacional:

- VS Code cria e altera.
- Docker local testa.
- GitHub guarda a versao consolidada.
- Servidor recebe apenas o pacote consolidado.

### Etapa 8 - Backups e restauracao

Runtime nao vai para o GitHub.

Precisa de fluxo proprio para:

- Backup diario.
- Retencao visivel no Core, por exemplo ultimos 30 dias.
- Armazenamento externo, como DigitalOcean Spaces ou Google Drive.
- Notificacao por email apenas avisando sucesso/falha.
- Restauracao manual controlada.

### Etapa 9 - Manual operacional

Depois das etapas tecnicas, criar um manual final:

- Como criar uma base no laboratorio.
- Como testar no Docker.
- Como proteger uma base.
- Como publicar no GitHub.
- Como subir para o servidor.
- Como criar projeto no servidor.
- Como restaurar backup.

## Scripts atuais e papel recomendado

### `scripts/deploy-atualizar-docker.ps1`

Usar para alinhar VS Code com o Docker local.

Papel: teste local.

### `scripts/deploy-atualizar-servidor.ps1`

Usar somente depois de validar localmente.

Papel: publicar produto no servidor.

### `scripts/servidor-reparar-banco-core.ps1`

Usar para instalacao/reparo do banco do Core.

Papel: manutencao e primeira instalacao.

### `scripts/servidor-status.ps1`

Usar para diagnostico do servidor.

Papel: verificar containers, env, banco e conexao.

### `scripts/servidor-sincronizar-bases-git.ps1`

Este script deve virar excecao, nao rotina.

Uso recomendado:

- Emergencia.
- Recuperar uma base oficial que foi criada no servidor por necessidade real.
- Antes de qualquer deploy que poderia apagar uma base protegida que nao existe localmente.

No fluxo novo, a base oficial deve nascer localmente e subir pelo GitHub/deploy.

### `scripts/servidor-validar-bases-protegidas.ps1`

Pode continuar como protecao temporaria.

No fluxo novo, ele ajuda a impedir que uma base oficial existente no servidor seja apagada por um deploy desalinhado.

## Regras praticas

- Projeto de cliente nao volta para GitHub.
- Base oficial volta para GitHub.
- Modulo e addon sempre voltam para GitHub.
- Servidor nao e laboratorio.
- Local pode quebrar; servidor nao.
- Base protegida deve ser tratada como produto.
- Base legacy deve ficar fora do deploy normal.
- Backup resolve runtime; Git resolve produto.

## Checklist antes de publicar no servidor

- Docker local atualizado.
- Projeto de teste criado e validado localmente.
- Base bloqueada/protegida.
- Modulos e addons instalados na base correta.
- `git status` revisado.
- Commit feito.
- GitHub atualizado.
- Deploy executado.
- Servidor validado com `servidor-status.ps1`.

## Decisao atual

Vamos implantar por etapas.

Primeiro, documentamos e congelamos o fluxo.

Depois, ajustamos o Core para respeitar ambiente local/laboratorio/producao.

So depois alteramos telas, permissoes, scripts e deploy.
