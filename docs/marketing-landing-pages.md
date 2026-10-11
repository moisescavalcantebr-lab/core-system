# Marketing e Landing Pages

- Base: Marketing (`marketing`), anteriormente Divulgacao.
- Modulo principal: Landing Pages (`landing_page`).
- O Content Studio continua no Core; esta mudanca nao adiciona campanhas, creditos ou integracoes com plataformas de anuncios.

O modulo cria paginas e captura leads no banco do proprio projeto. As rotas passam a ser `/admin/landing_page/`, `/landing_page.php?slug=...` e `/landing_page-lead.php`. As tabelas sao `landing_page_pages` e `landing_page_leads`.

## Imagens e banners

A imagem principal acompanha o titulo. `banners_json` guarda ate quatro destinos opcionais, com titulo e imagem. Um link HTTP/HTTPS valido e obrigatorio para exibir cada banner; sem imagem, ele aparece como link textual. Campos vazios ou imagens sem destino nao aparecem. A segunda imagem antiga e preservada no primeiro espaco de banner ate o cliente configurar seu link.

Os banners sao links diretos: nao passam pelo formulario nem criam leads na origem. Uma pagina de destino do sistema continua usando suas proprias regras de captura. Esta alteracao nao adiciona contadores de acessos ou cliques.

Na lista, paginas publicadas possuem um botao para copiar o endereco completo. O schema de projetos existentes recebe a coluna nova sem apagar registros quando o modulo atualizado e carregado. Para projetos ja criados, sincronize o modulo/base pelo Core depois do deploy.

Teste de regressao: `docker exec app_php php /var/www/html/scripts/tests/landing_page_banners_test.php`.

## Atualizacao

O deploy executa `app/console/migrate_marketing_identity.php --apply` antes do bootstrap. A migracao preserva o ID da base, os vinculos com planos e os precos do modulo. Pode ser simulada sem `--apply` e repetida sem duplicar registros.

Esta renomeacao foi preparada para uma base sem projetos: se houver projetos existentes na identidade antiga, a migracao interrompe sem apaga-los. Conflitos entre os cadastros antigos e novos tambem interrompem a migracao. Nao ha migracao de bancos de projetos nem exclusao de dados.

O manifesto declara `previous_slug: divulgacao`, permitindo ao guard validar a renomeacao de uma base publicada. Uma pasta antiga remanescente no servidor e ignorada pelo registro de bases enquanto esse alias estiver presente.
