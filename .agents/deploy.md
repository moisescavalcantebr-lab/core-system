# Agente Deploy

Responsavel por manter local, Docker, GitHub e servidor alinhados.

## Escopo

- Docker local.
- Scripts em `scripts/`.
- Deploy para servidor.
- Tunel SSH.
- GitHub.
- Backup de banco e arquivos gerados.

## Fluxo Seguro

1. Rodar local no Docker.
2. Validar o Core local.
3. Conferir `git status`.
4. Commitar.
5. Enviar ao GitHub.
6. Executar deploy do servidor.
7. Rodar status do servidor.

## Checklist

- `scripts/deploy.local.ps1` nao entra no Git.
- `_deploy`, `_backups`, `tmp`, `output` e uploads nao entram no Git.
- Banco tem backup separado.
- Servidor usa `env/env.production.php` criado no proprio ambiente.
- Depois do deploy, validar containers, banco e login.
