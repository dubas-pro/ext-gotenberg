.DEFAULT_GOAL := help

MODULE_NAME := Gotenberg

.PHONY: npm-ci
npm-ci: ## Install npm packages
	@if [ ! -d "node_modules" ]; then npm ci --silent --no-update-notifier; fi

.PHONY: dev-composer-install
dev-composer-install: ## Install composer packages for development
	@if [ ! -d "vendor" ]; then composer install --quiet; fi

.PHONY: composer-install
composer-install: npm-ci ## Install composer packages for the module
	@node build --composer-install

.PHONY: up
up: ## Start environment
	@docker compose up --detach --remove-orphans

.PHONY: all full
all full: npm-ci up ## Build full environment
	@echo "Building full environment..."
	@if [ ! -f config.php ]; then cp config.dist.php config.php; fi
	@docker compose exec --user devilbox php node build --all
	@$(MAKE) import --no-print-directory
	@echo "Done."
	@echo "Open http://localhost:$(shell docker compose port httpd 80 | awk -F: '{print $$2}') in your browser."

.PHONY: extension package
extension package: npm-ci ## Build extension package
	@node build --extension

.PHONY: copy
copy: npm-ci ## Copy extension
	@echo "Copying extension..."
	@node build --copy

.PHONY: rebuild rb
rebuild rb: ## Rebuild EspoCRM
	@echo "Rebuilding..."
	@docker compose exec --user devilbox php sh -c ' \
		cd site; php rebuild.php; \
	'
	@echo "Done."

.PHONY: clear-cache
clear-cache: ## Clear EspoCRM cache
	@echo "Clearing cache..."
	@docker compose exec --user devilbox php sh -c ' \
		cd site; php clear_cache.php \
	'
	@echo "Done."

.PHONY: cc
cc: copy clear-cache ## Copy extension and clear cache

.PHONY: cr
cr: copy rebuild ## Copy extension and rebuild

.PHONY: config
config: ## Merge config files
	@echo "Merging config files..."
	@if [ ! -f config.php ]; then cp config.dist.php config.php; fi
	@docker compose exec --user devilbox php sh -c ' \
		cd php_scripts; php merge_configs.php; \
	'
	@echo "Done."

.PHONY: import
import: ## Import test data
	@docker compose exec --user devilbox php sh -c ' \
		cd php_scripts; php import.php; \
	'

.PHONY: before-install
before-install: ## Run before install
	@docker compose exec --user devilbox php sh -c ' \
		cd php_scripts; php before_install.php; \
	'

.PHONY: after-install
after-install: ## Run after install
	@docker compose exec --user devilbox php sh -c ' \
		cd php_scripts; php after_install.php; \
	'

.PHONY: before-uninstall
before-uninstall: ## Run before uninstall
	@docker compose exec --user devilbox php sh -c ' \
		cd php_scripts; php before_uninstall.php; \
	'

.PHONY: ecs
ecs: dev-composer-install ## Fix PHP code style
	@vendor/bin/ecs check --clear-cache --fix

.PHONY: phpstan
phpstan: dev-composer-install ## Run PHPStan
	@vendor/bin/phpstan

.PHONY: prepare-tests
prepare-tests: ## Prepare test environment
	@docker compose exec --user devilbox php sh -c ' \
		cd site && npm ci && npx grunt test; \
	'

.PHONY: test
test: ## Run unit tests
	@docker compose exec --user devilbox php sh -c ' \
		$(MAKE) copy --no-print-directory; \
		cd site; vendor/bin/phpunit tests/unit/Espo/Modules/$(MODULE_NAME) ; \
	'

.PHONY: test-integration
test-integration: ## Run integration tests
	@docker compose exec --user devilbox php sh -c ' \
		$(MAKE) copy --no-print-directory; \
		cd site && vendor/bin/phpunit tests/integration/Espo/Modules/$(MODULE_NAME); \
	'

.PHONY: clean
clean: ## Clean up
	@echo "Stop, remove containers and volumes, and clean up..."
	@docker compose down --volumes --remove-orphans
	@rm --recursive --force site
	@if [ -d .git ]; then git clean -fdX; fi
	@echo "Done."

help: ## Display this help screen
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' Makefile | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
