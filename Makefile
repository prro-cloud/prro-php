.PHONY: all test cover lint fmt audit sync-spec

# Контракт API — копія server/api/openapi.yaml репозиторію сервісу.
SPEC ?= ../../uakey/server/api/openapi.yaml

all: lint test

vendor: composer.json
	composer install
	@touch vendor

test: vendor
	vendor/bin/phpunit

cover: vendor
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text

lint: vendor
	vendor/bin/phpstan analyse --no-progress
	vendor/bin/php-cs-fixer check --diff

fmt: vendor
	vendor/bin/php-cs-fixer fix

audit: vendor
	composer audit

# Оновлює копію контракту; tests/Contract покаже, що змінилося.
sync-spec:
	cp $(SPEC) tests/testdata/openapi.yaml
