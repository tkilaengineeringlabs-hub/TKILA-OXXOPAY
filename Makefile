SHELL := /bin/bash

.PHONY: version-update version-print version-verify check-new-version

check-new-version:
	@if [ -z "$(NEW_VERSION)" ]; then \
		echo "Error: NEW_VERSION is required. Usage: make version-update NEW_VERSION=1.0.8"; \
		exit 1; \
	fi

version-update: check-new-version
	@echo "Updating version to $(NEW_VERSION)..."
	@sed -i '' -E 's/^(\s*Version:\s*).*/\1$(NEW_VERSION)/' spin_checkout.php
	@sed -i '' -E 's/^(\s*Stable tag:\s*).*/\1$(NEW_VERSION)/' readme.txt
	@sed -i '' -E 's/^(\s*Requires at least:\s*).*/\1$(NEW_VERSION)/' readme.txt
	@sed -i '' -E 's/(public[[:space:]]+\$$version[[:space:]]*=[[:space:]]*")[^"]*(";)/\1$(NEW_VERSION)\2/' spin_plugin.php
	@sed -i '' -E "s/('integration_version'[[:space:]]*=>[[:space:]]*')[^']*(')/\1$(NEW_VERSION)\2/" spin_plugin.php
	@if command -v npm >/dev/null 2>&1; then \
		npm version $(NEW_VERSION) --no-git-tag-version >/dev/null; \
	else \
		echo "npm not found, skipping npm version bump"; \
	fi
	@echo "Version updated successfully to $(NEW_VERSION)"
	@echo "Run 'make version-verify NEW_VERSION=$(NEW_VERSION)' to verify all changes"

version-print:
	@echo "spin_checkout.php header: $$(grep -E '^[[:space:]]*Version:' spin_checkout.php | sed 's/.*Version:[[:space:]]*//')"
	@echo "readme.txt Stable tag:   $$(grep -E '^[[:space:]]*Stable tag:' readme.txt | sed 's/.*Stable tag:[[:space:]]*//')"
	@echo "readme.txt Requires at least: $$(grep -E '^[[:space:]]*Requires at least:' readme.txt | sed 's/.*Requires at least:[[:space:]]*//')"
	@echo "spin_plugin.php property: $$(sed -nE 's/.*public[[:space:]]+\$$version[[:space:]]*=[[:space:]]*"([^"]*)";.*/\1/p' spin_plugin.php)"
	@echo "spin_plugin.php agent:   $$(grep -E "'integration_version'" spin_plugin.php | sed -E "s/.*'integration_version'[[:space:]]*=>[[:space:]]*'([^']*)'.*/\1/")"
	@if [ -f package.json ]; then \
		echo "package.json:            $$(grep -E '\"version\"' package.json | head -n1 | sed -E 's/.*\"version\"[[:space:]]*:[[:space:]]*\"([^\"]*)\".*/\1/')"; \
	fi
	@if [ -f package-lock.json ]; then \
		echo "package-lock.json:       $$(jq -r '.version' package-lock.json 2>/dev/null || grep -E '\"version\"' package-lock.json | head -n1 | sed -E 's/.*\"version\"[[:space:]]*:[[:space:]]*\"([^\"]*)\".*/\1/')"; \
	fi

version-verify: check-new-version
	@echo "Verifying version is $(NEW_VERSION)..."
	@V1=$$(grep -E '^[[:space:]]*Version:' spin_checkout.php | sed 's/.*Version:[[:space:]]*//'); \
	V2=$$(grep -E '^[[:space:]]*Stable tag:' readme.txt | sed 's/.*Stable tag:[[:space:]]*//'); \
	VReq=$$(grep -E '^[[:space:]]*Requires at least:' readme.txt | sed 's/.*Requires at least:[[:space:]]*//'); \
	V3=$$(sed -nE 's/.*public[[:space:]]+\$$version[[:space:]]*=[[:space:]]*"([^"]*)";.*/\1/p' spin_plugin.php); \
	V4=$$(grep -E "'integration_version'" spin_plugin.php | sed -E "s/.*'integration_version'[[:space:]]*=>[[:space:]]*'([^']*)'.*/\1/"); \
	ERR=0; \
	for V in "$$V1" "$$V2" "$$VReq" "$$V3" "$$V4"; do \
	  if [ "$$V" != "$(NEW_VERSION)" ]; then \
	    echo "Mismatch: expected $(NEW_VERSION) but found $$V"; ERR=1; \
	  fi; \
	done; \
	if command -v jq >/dev/null 2>&1 && [ -f package.json ]; then \
	  V5=$$(jq -r .version package.json); \
	  [ "$$V5" = "$(NEW_VERSION)" ] || { echo "Mismatch in package.json: $$V5"; ERR=1; }; \
	fi; \
	if command -v jq >/dev/null 2>&1 && [ -f package-lock.json ]; then \
	  V6=$$(jq -r .version package-lock.json); \
	  [ "$$V6" = "$(NEW_VERSION)" ] || { echo "Mismatch in package-lock.json: $$V6"; ERR=1; }; \
	fi; \
	exit $$ERR
