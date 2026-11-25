# Goomer Menu API (PHP)

Trilha 2 do plano em `docs/visao-geral.md` (o contrato comum: regras, rotas, esquema e testes). PHP 8.4 + Slim 4 + PDO,
arquitetura limpa organizada por módulo. Convenções detalhadas na skill `.claude/skills/goomer-php/SKILL.md`.

## Ambiente

- Sem PHP no host: tudo roda em container. `make` pode não estar instalado; use o `docker compose` equivalente do `Makefile`.
- Subir: `docker compose up -d --build` (API em `localhost:8090`, Mailpit em `localhost:8026`, Postgres sem porta no host).
- Testes: `docker compose --profile test run --rm test vendor/bin/phpunit [--testsuite unit|usecase|integration|e2e]`.
- Qualidade: `... run --rm test sh -c "vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/deptrac analyse"`.

## Regras que não se negociam

- `Domain` não importa nada de fora (nem framework, nem PDO, nem Application). O Deptrac barra.
- SQL escrito à mão com prepared statements nas consultas; Doctrine Migrations só para o esquema.
- O restaurante das rotas protegidas vem sempre do token (`JwtMiddleware::context`), nunca da URL. Recurso de outro restaurante é 404.
- Erros em Problem Details; preços em centavos (`Money`); hora "agora" sempre via `Clock`.
- PHPStan nível max limpo, Pint limpo, cobertura de Domain e Application >= 90% (`bin/coverage-check.php`).

## Git

- Uma branch por funcionalidade (`feature/<nome>`), fast-forward na `main`; nunca commit direto na `main`.
- Commits `tipo(escopo): descrição`, até 200 caracteres, sem Co-Authored-By. No máximo 6 arquivos e 600 linhas por commit.
- Hooks em `.githooks` (`git config core.hooksPath .githooks`); o `pre-push` roda lint, PHPStan, Deptrac e testes unitários.
