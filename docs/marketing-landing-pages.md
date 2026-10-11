# Marketing e Landing Pages

- Base: Marketing (`marketing`), anteriormente Divulgacao.
- Modulo principal: Landing Pages (`landing_page`).
- O Content Studio continua no Core; esta mudanca nao adiciona campanhas, creditos ou integracoes com plataformas de anuncios.

O modulo cria paginas e captura leads no banco do proprio projeto. As rotas passam a ser `/admin/landing_page/`, `/landing_page.php?slug=...` e `/landing_page-lead.php`. As tabelas sao `landing_page_pages` e `landing_page_leads`.

## Atualizacao

O deploy executa `app/console/migrate_marketing_identity.php --apply` antes do bootstrap. A migracao preserva o ID da base, os vinculos com planos e os precos do modulo. Pode ser simulada sem `--apply` e repetida sem duplicar registros.

Esta renomeacao foi preparada para uma base sem projetos: se houver projetos existentes na identidade antiga, a migracao interrompe sem apaga-los. Conflitos entre os cadastros antigos e novos tambem interrompem a migracao. Nao ha migracao de bancos de projetos nem exclusao de dados.

O manifesto declara `previous_slug: divulgacao`, permitindo ao guard validar a renomeacao de uma base publicada. Uma pasta antiga remanescente no servidor e ignorada pelo registro de bases enquanto esse alias estiver presente.
