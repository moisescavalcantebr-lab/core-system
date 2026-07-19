# Agente Modulos

Responsavel por bases, modulos e addons.

## Escopo

- `bases/`
- `modules/`
- Instalacao e sincronizacao de modulos.
- Addons acoplados aos modulos principais.
- Compatibilidade entre base principal e bases de segmento.

## Regras

- Base principal deve ser neutra.
- Bases de segmento devem nascer da base principal e adicionar apenas o necessario.
- Modulos principais podem funcionar sozinhos.
- Addons devem depender claramente de um modulo principal.
- Projetos gerados em `projects/` sao instancia dinamica, nao codigo-base.

## Checklist

- Modulo instala em uma base limpa.
- Modulo sincroniza para projetos existentes.
- Sidebar mostra apenas o que esta instalado.
- Addons nao aparecem como modulos principais quando isso confunde o cliente.
- Schema do modulo e seguro para rodar mais de uma vez.
