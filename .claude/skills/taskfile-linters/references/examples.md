# Taskfile & Linters Examples

## Common Development Workflows

### Starting a New Feature

```bash
# 1. Create feature branch
git checkout -b feature/TAR-123-new-feature

# 2. Make changes...

# 3. Run linters before committing
task lint

# 4. Run static analysis
task api:phpstan:check

# 5. Run tests
task api:test
task pwa:test
```

### Fixing PHPStan Errors

```bash
# Check all files
task api:phpstan:check

# Check specific file to isolate errors
task api:phpstan:check -- src/Domain/WatchFile/WatchFile.php

# Clear cache if results seem stale
task api:phpstan:clear-result-cache
task api:phpstan:check

# Generate baseline for legacy code
task api:phpstan:generate-baseline
```

### Running Integration Tests

```bash
# First time setup (required!)
task api:test:integration:setup

# Run all integration tests
task api:test:integration

# Run specific integration test
docker compose exec api php vendor/bin/phpunit tests/Integration/WatchFile/WatchFileApiTest.php

# Reset environment if database schema changes
task api:test:integration:reset
```

---

## ECS Configuration

### `api/ecs.php` Structure

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    ->withPreparedSets(
        psr12: true,
        common: true,
    )
    ->withSkip([
        // Skip specific rules if needed
    ]);
```

### Common ECS Fixes

```bash
# Fix trailing commas
task api:cs:fix

# Fix specific directory after refactoring
task api:cs:fix -- src/Domain/

# Check without fixing (for CI)
task api:cs:check
```

---

## PHPStan Configuration

### `api/phpstan.neon` Structure

```neon
parameters:
    level: 9
    paths:
        - src
    excludePaths:
        - src/Kernel.php
    tmpDir: var/phpstan
```

### PHPStan Common Commands

```bash
# Full analysis
task api:phpstan:check

# Analyze specific path
task api:phpstan:check -- src/Application/WatchFile/

# Memory limit for large codebases
docker compose exec api php vendor/bin/phpstan --memory-limit=2G analyse

# Generate baseline when inheriting legacy code
task api:phpstan:generate-baseline
```

---

## ESLint Configuration

### `pwa/eslint.config.mjs` Structure

```javascript
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
    rules: {
        'vue/multi-word-component-names': 'off',
        '@typescript-eslint/no-explicit-any': 'warn',
    },
})
```

### ESLint Commands

```bash
# Fix all Vue/TypeScript files
task pwa:eslint:fix

# Check without fixing
task pwa:eslint:check

# Fix specific file directly
docker compose exec pwa npx eslint --fix components/MyComponent.vue
```

---

## Prettier Configuration

### `.prettierrc` Structure

```json
{
    "semi": false,
    "singleQuote": true,
    "tabWidth": 2,
    "trailingComma": "es5",
    "printWidth": 120
}
```

### Prettier Commands

```bash
# Format entire project
task prettier:fix

# Check formatting (CI)
docker compose run --rm devtools npx prettier --check .

# Format specific file
docker compose run --rm devtools npx prettier --write pwa/components/MyComponent.vue
```

---

## Stylelint Configuration

### `.stylelintrc.json` Structure

```json
{
    "extends": ["stylelint-config-standard-scss", "stylelint-config-recommended-vue/scss"],
    "rules": {
        "at-rule-no-unknown": null,
        "scss/at-rule-no-unknown": true
    }
}
```

### Stylelint Commands

```bash
# Fix all CSS/SCSS
task stylelint:fix

# Check specific files
docker compose run --rm devtools npx stylelint "pwa/components/**/*.vue" --fix
```

---

## PHPUnit Test Commands

### Unit Tests

```bash
# Run all unit tests
task api:test:unit

# Run specific test file
docker compose exec api php vendor/bin/phpunit tests/Units/Domain/WatchFile/WatchFileTest.php

# Run specific test method
docker compose exec api php vendor/bin/phpunit --filter=testCreateWatchFile

# Run with coverage
task api:test:unit:coverage
```

### Integration Tests

```bash
# Setup test environment (first time)
task api:test:integration:setup

# Run all integration tests
task api:test:integration

# Run specific integration test class
docker compose exec api php vendor/bin/phpunit tests/Integration/WatchFile/WatchFileApiTest.php

# Run specific method
docker compose exec api php vendor/bin/phpunit --filter=testGetWatchFile tests/Integration/

# Reset environment after schema changes
task api:test:integration:reset
```

---

## Vitest Commands

### Running Tests

```bash
# Run all tests (watch mode)
task pwa:test

# Run once without watch
docker compose exec pwa npm run test -- --run

# Run specific test file
docker compose exec pwa npm run test -- tests/components/watchFiles/WatchFileList.test.ts

# Run with UI
task pwa:test:ui

# Run with coverage
task pwa:test:coverage
```

---

## N8N Workflow Commands

### Exporting Workflows

```bash
# Export all workflows to docker/n8n/workflows/
task n8n:export-workflows

# Preview what would be exported
task n8n:export-workflows -- --dry-run

# Export credentials
task n8n:export-credentials
```

### Testing Workflows

```bash
# Setup test environment
task n8n:test:setup

# Run test with dataset
task n8n:test -- my-dataset.json

# Run with verbose output
task n8n:test -- my-dataset.json --verbose

# Validate workflow configurations
task n8n:validate

# Validate test datasets
task n8n:validate:datasets
```

---

## Git Hooks

### Installing Hooks

```bash
# Install commit-msg and pre-commit hooks
task hook:install
```

### Pre-commit Hook Actions

The pre-commit hook runs:

1. `lint-staged` - Format staged files with Prettier
2. Runs relevant linters on staged files

### Commit Message Format

Commits must follow conventional format:

```
type(scope): description

feat(watchfile): add export functionality
fix(chat): resolve message ordering issue
chore(deps): update API Platform to 4.1
```

---

## CI Pipeline Tasks

### For GitHub Actions / GitLab CI

```bash
# Check code style (fails on errors)
task api:cs:check
task pwa:eslint:check

# Static analysis
task api:phpstan:check

# Tests
task api:test
docker compose exec pwa npm run test -- --run
```

---

## Troubleshooting

### PHPStan Cache Issues

```bash
# Clear PHPStan cache
task api:phpstan:clear-result-cache

# Then re-run analysis
task api:phpstan:check
```

### Integration Test Database Issues

```bash
# Reset entire test environment
task api:test:integration:reset

# Or manually recreate
docker compose --profile test down database-test elasticsearch-test
task api:test:integration:setup
```

### Docker Compose Issues

```bash
# Restart services
docker compose restart api pwa

# Rebuild containers
docker compose build api pwa

# View logs
docker compose logs -f api
```
