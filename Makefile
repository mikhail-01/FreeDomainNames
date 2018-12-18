# REQUIRED SECTION
ROOT_DIR:=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
include $(ROOT_DIR)/.mk-lib/common.mk
include .env
export $(shell sed 's/=.*//' .env)
THIS_FILE := $(lastword $(MAKEFILE_LIST))
# END OF REQUIRED SECTION

.PHONY: help dependencies build tests start stop restart bash composer status ps clean down install mysql
dependencies: check-dependencies ## Check dependencies

start: ## Start all or c=<name> containers in background
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) up --build -d $(c)

stop: ## Stop all or c=<name> containers
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) stop $(c)

pull: ## Pull images
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) pull

restart: ## Restart all or c=<name> containers
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) stop $(c)
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) up --build $(c) -d

bash: ## Execute bash into fpm container
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec php-fpm /bin/bash

bash-nginx: ## Execute bash into nginx container
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec nginx /bin/bash

composer: ## Run composer command in workpace container
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) run --rm php-fpm composer $(filter-out $@,$(MAKECMDGOALS))

composer-install: ## Run composer install command in workspace container
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) run --rm php-fpm composer install

status: ## Show status of containers
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) ps

ps: status ## Alias of status

clean: ## Clean all data
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) down

down: clean ## Alias of clean

install: build composer-install  # Install project

tests:  ## Run phpunit tests
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) run --rm php-fpm ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests

mysql: ## Run mysql console
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec db mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE}

