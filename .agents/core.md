# Agente Core

Responsavel por manter o nucleo do sistema estavel.

## Escopo

- Bootstrap do Core.
- Schema principal em `app/database/schema.sql`.
- Login, usuarios, permissoes e configuracoes.
- Sistema de paginas, leads, midia e Content Studio.
- Instalador do Core em `app/console/install_core.php`.
- Health check em `app/console/check_core.php`.

## Checklist

- A mudanca nao quebra login ou bootstrap.
- O schema novo e idempotente ou documentado.
- Arquivos `env/*.php` reais nao entram no Git.
- Paginas administrativas usam CSRF quando salvam dados.
- O Core continua funcionando sem projetos criados.
