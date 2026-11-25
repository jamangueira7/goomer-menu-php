# Atalhos para quem não conhece a stack: tudo roda em containers (não precisa de PHP no host).
COMPOSE = docker compose
TEST = $(COMPOSE) --profile test run --rm test

.PHONY: up down logs test test-unit test-integration coverage lint fix stan deptrac check create-admin

up: ## Sobe db, migrate (migrations + seed), api (localhost:8090) e mailpit (localhost:8026)
	$(COMPOSE) up -d --build

down: ## Derruba os containers (o volume do banco fica)
	$(COMPOSE) down

logs: ## Acompanha os logs da API
	$(COMPOSE) logs -f api

test: ## Todos os níveis: unitário, caso de uso, integração e ponta a ponta
	$(TEST) vendor/bin/phpunit

test-unit: ## Só unitário e caso de uso (sem banco)
	$(TEST) vendor/bin/phpunit --testsuite unit,usecase

test-integration: ## Integração e ponta a ponta contra o banco goomer_test
	$(TEST) vendor/bin/phpunit --testsuite integration,e2e

coverage: ## Cobertura de Domain e Application (meta: 90%)
	$(TEST) sh -c "vendor/bin/phpunit --coverage-clover coverage/clover.xml && php bin/coverage-check.php coverage/clover.xml 90"

lint: ## Pint (estilo), PHPStan nível max e Deptrac (camadas)
	$(TEST) sh -c "vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G --no-progress && vendor/bin/deptrac analyse --no-progress"

fix: ## Formata o código com o Pint
	$(TEST) vendor/bin/pint

create-admin: ## Ex.: make create-admin EMAIL=ana@bar.com PASSWORD=segredo123 NAME="Ana" RESTAURANT=1
	$(COMPOSE) run --rm migrate php bin/create-admin.php --email="$(EMAIL)" --password="$(PASSWORD)" --name="$(or $(NAME),Administrador)" --restaurant="$(or $(RESTAURANT),1)"
