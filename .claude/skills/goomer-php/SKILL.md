---
name: goomer-php
description: Convenções da Goomer Menu API em PHP (Slim 4, PDO, arquitetura limpa por módulo). Use ao criar ou mudar caso de uso, entidade, repositório, rota, migration ou teste neste repositório.
---

# Convenções da Goomer Menu API (PHP)

## Onde cada coisa mora

```
src/<Módulo>/Domain          entidades, objetos de valor, exceções e interfaces de repositório (PHP puro)
src/<Módulo>/Application     um caso de uso por ação (`XxxUseCase::execute`), DTOs de entrada e saída, portas
src/<Módulo>/Infrastructure  PDO, JWT, Argon2id, symfony/mailer
src/<Módulo>/Http            controllers finos com atributos OpenAPI
src/Shared/...               Money, Weekday, TimeOfDay, TimeWindow, Timezone, Clock, Problem Details, Database
src/Bootstrap                Container (único ponto de composição) e Env
```

Módulos: `Product`, `Promotion`, `Menu`, `Auth`, `User`, mais `Shared`.

## Receitas

- **Nova regra de negócio**: entra na entidade ou no objeto de valor, lançando `ValidationError` (422), `NotFound` (404)
  ou `Conflict` (409). Teste unitário com `new`, sem banco.
- **Novo caso de uso**: classe `final readonly` em `Application`, recebe primitivos ou um DTO, devolve DTO (`toArray()`).
  Teste em `tests/UseCase` com os repositórios em memória de `tests/Support` e `FixedClock`.
- **Nova consulta**: SQL à mão no repositório PDO (`Database::execute/fetchAll/fetchOne`), parâmetros nomeados e
  nunca o mesmo nome duas vezes (prepare nativo). Teste em `tests/Integration` com `TestDatabase::reset()`.
- **Nova rota**: método no controller (`Request, Response, array $args`), registrado em `Shared/Http/Routes.php` dentro
  ou fora do grupo protegido; ler corpo com `Input` e chamar `validate()`. Teste em `tests/E2E` (estende `ApiTestCase`).
- **Mudança de esquema**: nova classe em `migrations/VersionAAAAMMDDhhmmss.php` com `addSql` escrito à mão; regras
  que dá para garantir no banco (CHECK, EXCLUDE, FK) entram também lá.
- **Nova porta**: interface em `Application` (ou `Domain`, se for repositório), implementação em `Infrastructure`,
  ligação em `Bootstrap/Container.php`.

## Checklist antes do commit

1. `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (max) e `vendor/bin/deptrac analyse` limpos.
2. Testes do nível certo passando; cobertura de Domain/Application >= 90% (`php bin/coverage-check.php`).
3. Commit `tipo(escopo): descrição` (<= 200 caracteres), <= 6 arquivos e 600 linhas, na branch `feature/<nome>`.
