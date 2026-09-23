@AGENTS.md

## Ambiente deste projeto

PHP e Composer **não** estão instalados no host (Windows): tudo roda nos containers Docker. Rode os comandos a partir da raiz do repositório, por exemplo:

```sh
docker compose exec app-server php artisan test
docker compose exec app-server composer require <pacote>
```

Veja o `ROADMAP.md` (raiz) para o estado atual, próximos passos e armadilhas conhecidas.
