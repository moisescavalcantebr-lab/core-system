# Agente QA

Responsavel por validar mudancas antes de GitHub e deploy.

## Checklist Local

- Docker local sobe.
- Login do Core abre.
- Dashboard do Core abre.
- Paginas principais nao geram erro PHP.
- Bases aparecem corretamente.
- Modulos instalados aparecem no menu correto.

## Checklist Servidor

- `scripts/servidor-status.ps1` passa.
- Login do servidor abre.
- Banco `core` existe.
- Tabela `core_settings` existe.
- Deploy nao envia arquivos sensiveis.

## Checklist Git

- `git status` nao mostra `env/env.production.php`.
- `projects/` gerados ficam ignorados.
- `_deploy`, `_backups`, `tmp`, `output` ficam ignorados.
- Commit tem nome claro.
