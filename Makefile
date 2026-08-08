.PHONY: help setup test analyse lint openapi-lint check infra-up infra-down up down

help:
	@echo make setup          Install PHP and OpenAPI tooling
	@echo make test           Run core, DIAN and API tests
	@echo make analyse        Run PHPStan on framework-independent packages
	@echo make lint           Check PHP style and syntax
	@echo make openapi-lint   Validate the OpenAPI 3.1 contract
	@echo make check          Run the complete local quality gate
	@echo make up             Build and start the complete Tribux stack
	@echo make infra-up       Start PostgreSQL and Redis only

setup:
	composer install --no-interaction
	composer --working-dir=apps/api install --no-interaction
	npm ci

test:
	composer test
	composer --working-dir=apps/api test

analyse:
	composer analyse

lint:
	composer validate --strict
	composer --working-dir=apps/api validate --strict
	composer lint
	php apps/api/vendor/bin/pint --test apps/api/app apps/api/bootstrap/app.php apps/api/bootstrap/providers.php apps/api/config/tribux.php apps/api/database apps/api/routes apps/api/tests

openapi-lint:
	npm run openapi:lint

check: lint analyse test openapi-lint
	@echo Tribux quality checks passed.

infra-up:
	docker compose up -d postgres redis

infra-down:
	docker compose down

up:
	docker compose up --build -d

down:
	docker compose down
