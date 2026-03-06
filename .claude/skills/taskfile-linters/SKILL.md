---
name: taskfile-linters
description: Taskfile commands and linter configuration for the Basil project. Use when running tests with `task api:test`, fixing code style with `task api:cs:fix` or `task pwa:eslint:fix`, running PHPStan with `task api:phpstan:check`, loading fixtures with `task api:fixture:load`, or executing any development task. Activates when needing to run linters, formatters, tests, or build commands. Run `task -l` to list all available tasks.
allowed-tools: Bash, Read, Glob, Grep
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When running PHPUnit tests with `task api:test`
- When fixing PHP code style with `task api:cs:fix`
- When fixing TypeScript/Vue linting with `task pwa:eslint:fix`
- When running PHPStan static analysis with `task api:phpstan:check`
- When loading database fixtures with `task api:fixture:load`
- When setting up the integration test environment with `task api:test:integration:setup`
- When running all linters with `task lint`
- When exporting N8N workflows with `task n8n:export-workflows`
- When needing to discover available commands with `task -l`
- When formatting code with `task prettier:fix`

# Taskfile & Linters

Basil uses [Task](https://taskfile.dev/) as a task runner with multiple linters for code quality.

## Taskfile Structure

```
basil/
├── Taskfile.yaml            # Root tasks (lint, prettier, stylelint, hooks)
├── api/Taskfile.yaml        # API tasks (api:*)
├── pwa/Taskfile.yaml        # PWA tasks (pwa:*)
└── docker/n8n/Taskfile.yaml # N8N tasks (n8n:*)
```

## Quick Reference

```bash
# List all available tasks
task -l

# Run all linters before committing
task lint
```

## Root Tasks

| Task                 | Description                                        |
| -------------------- | -------------------------------------------------- |
| `task lint`          | Run all linters (prettier, stylelint, eslint, ecs) |
| `task prettier:fix`  | Fix code formatting across project                 |
| `task stylelint:fix` | Fix CSS/SCSS style issues in PWA                   |
| `task hook:install`  | Install git hooks (commit-msg, pre-commit)         |

## API Tasks (PHP Backend)

| Task                                  | Description                                  |
| ------------------------------------- | -------------------------------------------- |
| `task api:composer:install`           | Install PHP dependencies                     |
| `task api:cs:fix`                     | Fix PHP code style with ECS                  |
| `task api:cs:check`                   | Check PHP code style (no fix)                |
| `task api:phpstan:check`              | Run PHPStan analysis (level 9)               |
| `task api:phpstan:generate-baseline`  | Generate PHPStan baseline                    |
| `task api:phpstan:clear-result-cache` | Clear PHPStan cache                          |
| `task api:test`                       | Run all PHPUnit tests                        |
| `task api:test:unit`                  | Run unit tests only                          |
| `task api:test:integration`           | Run integration tests only                   |
| `task api:test:integration:setup`     | Setup test environment (required first time) |
| `task api:test:integration:reset`     | Reset test environment                       |
| `task api:fixture:load`               | Load database fixtures                       |

## PWA Tasks (Frontend)

| Task                     | Description                       |
| ------------------------ | --------------------------------- |
| `task pwa:eslint:fix`    | Fix TypeScript/Vue linting issues |
| `task pwa:eslint:check`  | Check linting (no fix)            |
| `task pwa:test`          | Run Vitest tests                  |
| `task pwa:test:ui`       | Run tests in UI mode              |
| `task pwa:test:coverage` | Run tests with coverage           |

## N8N Tasks

| Task                          | Description                                 |
| ----------------------------- | ------------------------------------------- |
| `task n8n:export-workflows`   | Export workflows to `docker/n8n/workflows/` |
| `task n8n:export-credentials` | Export credentials                          |
| `task n8n:test -- <dataset>`  | Run workflow tests                          |
| `task n8n:test:setup`         | Setup test environment                      |
| `task n8n:validate`           | Validate workflow configurations            |
| `task n8n:validate:datasets`  | Validate test datasets                      |
| `task n8n:reset`              | Reset N8N instance (destructive)            |

## Linter Tools

### ECS (Easy Coding Standard) - PHP

Config: `api/ecs.php`

```bash
# Fix all PHP files
task api:cs:fix

# Fix specific file
task api:cs:fix -- src/Domain/WatchFile/WatchFile.php

# Check only (CI mode)
task api:cs:check
```

### PHPStan - PHP Static Analysis

Config: `api/phpstan.neon` (Level 9)

```bash
# Analyze all code
task api:phpstan:check

# Analyze specific file
task api:phpstan:check -- src/Domain/WatchFile/WatchFile.php

# Generate baseline for existing errors
task api:phpstan:generate-baseline
```

### ESLint - TypeScript/Vue

Config: `pwa/eslint.config.mjs`

```bash
# Fix all files
task pwa:eslint:fix

# Check only
task pwa:eslint:check
```

### Prettier - Code Formatting

Config: `.prettierrc`

```bash
# Format all files
task prettier:fix
```

### Stylelint - CSS/SCSS

Config: `.stylelintrc.json`

```bash
# Fix CSS/SCSS in PWA
task stylelint:fix
```

## Before Committing Workflow

```bash
# 1. Run all linters
task lint

# 2. Run PHPStan
task api:phpstan:check

# 3. Run tests
task api:test
task pwa:test
```

## Running Specific Tests

```bash
# PHP - specific file
docker compose exec api php vendor/bin/phpunit tests/Units/Domain/WatchFile/WatchFileTest.php

# PHP - specific method
docker compose exec api php vendor/bin/phpunit --filter=testMethodName

# Vitest - specific file
docker compose exec pwa npm run test -- tests/components/MyComponent.test.ts

# Vitest - run once (no watch)
docker compose exec pwa npm run test -- --run
```

## Task Arguments

Pass arguments to tasks with `--`:

```bash
# PHPStan with specific file
task api:phpstan:check -- src/Domain/WatchFile/WatchFile.php

# ECS with specific directory
task api:cs:fix -- src/Application/

# N8N test with dataset
task n8n:test -- my-dataset.json --verbose
```
