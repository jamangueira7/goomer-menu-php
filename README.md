# Goomer Menu API — PHP

API de produtos, promoções e cardápio de restaurante (trilha 2 do plano em [`docs/visao-geral.md`](docs/visao-geral.md)):
PHP 8.4, Slim 4, PSR-7, PHP-DI, PDO com SQL escrito à mão, Doctrine Migrations só para o esquema, JWT HS256,
Argon2id, symfony/mailer e OpenAPI gerado com swagger-php.

## Como rodar

Só precisa de Docker. Nada de PHP ou Composer no host.

```bash
cp .env.example .env        # opcional: o compose tem padrões de desenvolvimento
docker compose up -d --build   # ou: make up
```

| Serviço | O que faz | Onde |
| --- | --- | --- |
| `db` | Postgres 16 com healthcheck (sem porta no host) | `docker compose exec db psql -U goomer` |
| `migrate` | Roda as migrations e o seed, e encerra | `docker compose logs migrate` |
| `api` | Sobe só depois do `migrate` terminar com sucesso | http://localhost:8090 |
| `mailpit` | Recebe os e-mails por SMTP (1025) | http://localhost:8026 |

Documentação OpenAPI (só com `APP_ENV=dev`, o padrão do compose): http://localhost:8090/docs
(JSON em `/docs/openapi.json`).

**Admin de exemplo criado pelo seed:** `admin@bardojoao.com` / `goomer123`, no restaurante 1, "Bar do João"
(`America/Sao_Paulo`). O seed também cria um cardápio pequeno, com um happy hour do chopp de segunda a sexta,
das 18:00 às 20:00, e uma feijoada que só aparece no sábado, das 11:00 às 16:00.

Outro admin: `make create-admin EMAIL=ana@bar.com PASSWORD=segredo123 NAME="Ana"` ou, sem `make`:

```bash
docker compose run --rm migrate php bin/create-admin.php --email=ana@bar.com --password=segredo123 --name="Ana" --restaurant=1
```

### Comandos (Makefile)

| `make` | Equivalente | O que faz |
| --- | --- | --- |
| `up` / `down` / `logs` | `docker compose up -d --build` / `down` / `logs -f api` | Sobe, derruba e acompanha |
| `test` | `docker compose --profile test run --rm test vendor/bin/phpunit` | Todos os níveis de teste |
| `test-integration` | `... run --rm test vendor/bin/phpunit --testsuite integration,e2e` | Integração e ponta a ponta |
| `lint` | `... run --rm test sh -c "vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/deptrac analyse"` | Estilo, análise estática e camadas |
| `coverage` | `... vendor/bin/phpunit --coverage-clover coverage/clover.xml && php bin/coverage-check.php` | Meta de 90% em Domain e Application |

## Exemplos com curl

```bash
API=http://localhost:8090/api/v1

# Login (devolve accessToken, válido por 1 hora)
TOKEN=$(curl -s -X POST $API/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"admin@bardojoao.com","password":"goomer123"}' | sed -E 's/.*"accessToken":"([^"]+)".*/\1/')

# Criar produto (preço em centavos; disponibilidade opcional)
curl -s -X POST $API/products -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Caipirinha","price":2200,"category":"bebidas","availability":[{"weekday":5,"start":"17:00","end":"23:45"}]}'

# Criar promoção (409 se sobrepõe outra do mesmo produto; 422 se o preço não for menor que o normal)
curl -s -X POST $API/promotions -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"productId":8,"description":"Sexta da caipirinha","price":1500,"windows":[{"weekday":5,"start":"18:00","end":"20:00"}]}'

# Cardápio público (sem token), calculado no fuso do restaurante
curl -s $API/restaurants/1/menu

# Listagem com filtros e paginação; ordem do cardápio
curl -s "$API/products?category=bebidas&visible=true&page=1&perPage=20" -H "Authorization: Bearer $TOKEN"
curl -s -X PUT $API/products/order -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"ids":[2,1]}'
```

Categorias: `entradas`, `pratos_principais`, `sobremesas`, `bebidas`. Dias da semana: 0 (domingo) a 6 (sábado).

### Recuperação de senha pelo Mailpit

Como o projeto não tem front, o link do e-mail (`APP_RESET_URL?token=...`) não abre tela nenhuma; o token é copiado
dele e enviado à API:

```bash
curl -s -X POST $API/auth/forgot-password -H 'Content-Type: application/json' -d '{"email":"admin@bardojoao.com"}'
# 202 sempre. Abra http://localhost:8026, veja o e-mail "Redefinição de senha" e copie o valor de token=...
# Ou pela API do Mailpit:
TOKEN_RESET=$(curl -s "http://localhost:8026/api/v1/search?query=to:admin@bardojoao.com" \
  | sed -E 's/.*"ID":"([^"]+)".*/\1/' | xargs -I{} curl -s http://localhost:8026/api/v1/message/{} \
  | grep -oE 'token=[a-f0-9]{64}' | head -1 | cut -d= -f2)

curl -s -o /dev/null -w '%{http_code}\n' -X POST $API/auth/reset-password -H 'Content-Type: application/json' \
  -d "{\"token\":\"$TOKEN_RESET\",\"password\":\"nova-senha-123\"}"   # 204; 422 se usado, vencido ou inválido
```

Acima de 5 pedidos por e-mail ou por IP em 15 minutos, a resposta é 429 com `Retry-After`.

## Arquitetura

```
src/<Módulo>/{Domain, Application, Infrastructure, Http}   módulos: Product, Promotion, Menu, Auth, User
src/Shared                                                 Money, Weekday, TimeOfDay, TimeWindow, Timezone, Clock, Problem Details
src/Bootstrap/Container.php                                único ponto de composição (PHP-DI)
migrations/                                                Doctrine Migrations com SQL à mão
```

- **Domain** é PHP puro (dá para testar só com `new`); o Deptrac falha se ele importar Application, Infrastructure,
  Http ou qualquer biblioteca (Slim, PDO, Doctrine, Firebase, Symfony...).
- Cada ação é um caso de uso (`CreatePromotionUseCase`, `GetMenuUseCase`...) com DTO de entrada e de saída.
- O cardápio sai de uma única consulta com `LEFT JOIN LATERAL` (`PdoMenuReader`); dia e hora locais vêm do domínio
  (`Timezone::localize(Clock::now())`).
- O banco é a segunda linha de defesa: ENUM de categoria, CHECKs de horário (minutos de 15 em 15, fim >= início + 15 min),
  `EXCLUDE USING gist` com `btree_gist` contra janelas sobrepostas do mesmo produto, `ON DELETE CASCADE`, e-mail único.

## Testes

| Nível | Pasta | Testes | Como roda |
| --- | --- | --- | --- |
| Unitário de domínio | `tests/Unit` | 31 | Sem banco, em milissegundos |
| Caso de uso | `tests/UseCase` | 29 | Repositórios em memória e `FixedClock` (fusos diferentes) |
| Integração | `tests/Integration` | 14 | Repositórios PDO contra o Postgres real (banco `goomer_test`), incluindo o EXCLUDE e os CHECKs |
| Ponta a ponta | `tests/E2E` | 14 | App Slim inteira, banco de teste e Mailpit de verdade (pega o token do e-mail pela API do Mailpit) |
| Arquitetura | `deptrac.yaml` | — | Domain sem dependências externas |

Cobertura de linhas: Domain 100%, Application 99,6%. PHPStan no nível max e Pint sem pendências.

## Desafios encontrados

Decisões do enunciado (valem nas três trilhas; detalhes em `docs/visao-geral.md`):

- **Intervalo mínimo de 15 minutos**: minutos só em 00, 15, 30 ou 45 e fim pelo menos 15 minutos depois do início.
  Como o fim precisa ser maior que o início, a maior janela possível vai até 23:45 (não existe 24:00).
- **Promoção que vira a meia-noite** (22:00–02:00) é recusada com 422; cadastre uma janela em cada dia.
- **Janelas sobrepostas** do mesmo produto: 409. A aplicação checa antes e o Postgres garante com `EXCLUDE USING gist`
  (o repositório traduz o SQLSTATE `23P01` para o mesmo 409). Janelas que só se encostam (18:00–20:00 e 20:00–21:00)
  não se sobrepõem. As janelas de uma mesma promoção também não podem se sobrepor entre si (409).
- **Preço promocional >= preço normal**: 422. Se o preço normal do produto cair depois, a promoção não é revalidada
  (decisão simples; o próximo `PUT` da promoção valida de novo).
- **Disponibilidade do produto**: janelas opcionais; sem janela, sempre disponível.
- **Excluir produto** apaga as promoções dele (`ON DELETE CASCADE`).
- **JWT depois da troca de senha**: tokens antigos continuam válidos até vencer (no máximo 1 hora).

Decisões e desvios desta trilha:

- **Testcontainers → Postgres do compose**: em PHP não há um Testcontainers maduro. Os testes de integração e ponta a
  ponta usam o mesmo Postgres do compose num banco separado, `goomer_test`, que o bootstrap cria e migra
  (`tests/Support/TestDatabase.php`) e recusa rodar em banco cujo nome não termine em `_test`.
- **Ponta a ponta sem servidor HTTP**: os testes mandam requisições PSR-7 direto para a app Slim montada com o container
  real (mesmas rotas, middlewares e banco), trocando só o `Clock` por um relógio fixo. Isso deixa o cardápio
  determinístico (quarta-feira 18:30 em São Paulo) sem depender da hora em que a suíte roda.
- **Ids gerados antes de salvar**: os repositórios pedem `nextval()` da sequência (`nextId()`), então a entidade
  nasce com id e não precisa de setter.
- **Parâmetros repetidos no SQL**: com prepare nativo do PDO (`ATTR_EMULATE_PREPARES=false`), o mesmo `:nome` não pode
  aparecer duas vezes. A consulta do cardápio usa uma CTE (`now_local`) com dia e hora uma vez só.
- **Minutos para o EXCLUDE**: a função `minute_of_day(time)` é `IMMUTABLE`, o que permite usá-la no índice GiST com
  `int4range(início, fim)`.
- **Limite de 429**: cada pedido de recuperação fica na tabela `password_reset_requests` (e-mail, IP e horário), contada
  nos últimos 15 minutos. O limite vale exista ou não o e-mail, para não vazar quem está cadastrado. Um novo pedido
  marca os tokens abertos anteriores como usados.
- **Servidor da imagem final**: o servidor embutido do PHP com `PHP_CLI_SERVER_WORKERS=4`, em vez de nginx + php-fpm.
  Mantém a imagem enxuta e o compose simples, o que basta para um projeto de estudo; em produção o caminho seria
  FrankenPHP ou php-fpm.
- **Portas**: a API fica em `8090` e a UI do Mailpit em `8026`, porque `8000`, `8025` e `5432` já estavam ocupadas na
  máquina de desenvolvimento. O Postgres não publica porta no host.
- **firebase/php-jwt 7**: a linha 6.x foi barrada pelo Composer por aviso de segurança; a API usada é a mesma.
- **Erros de formato**: JSON malformado é 400; campo com tipo errado ou regra de negócio violada é 422, com o detalhe
  por campo em `errors` no Problem Details. Id de rota não numérico é 404.
