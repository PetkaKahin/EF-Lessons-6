COMPOSE_ENV=$(if $(wildcard .env),--env-file .env,)
COMPOSE=docker compose $(COMPOSE_ENV)
ARGS=$(filter-out $@,$(MAKECMDGOALS))

.PHONY: init up down artisan test composer queue queue-restart

init:
	$(COMPOSE) up -d --build
	$(COMPOSE) exec php sh -lc "if [ -f .env.example ] && [ ! -f .env ]; then cp .env.example .env; fi"
	$(COMPOSE) exec php sh -lc "if [ -f composer.json ]; then composer install; fi"
	$(COMPOSE) exec php sh -lc "if [ -f artisan ]; then php artisan key:generate --ansi; fi"
	$(COMPOSE) exec php sh -lc "if [ -f artisan ]; then php artisan migrate --force; fi"

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

composer:
	$(COMPOSE) exec php composer install

artisan:
	$(COMPOSE) exec php php artisan $(ARGS)

seed:
	$(COMPOSE) exec php php artisan db:seed

test:
	$(COMPOSE) exec php ./vendor/bin/pest

queue:
	$(COMPOSE) exec php php artisan queue:work

queue-restart:
	$(COMPOSE) exec php php artisan queue:restart

ifeq ($(firstword $(MAKECMDGOALS)),artisan)
%:
	@:
endif
