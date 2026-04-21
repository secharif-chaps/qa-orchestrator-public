export default {
  // Frontend: TypeScript & JavaScript
  'apps/front/**/*.{ts,tsx,js}': [
    'cd apps/front && npx eslint --no-error-on-unmatched-pattern --fix',
    'cd apps/front && npx prettier --write',
  ],
  // Frontend: Vue components (eslint + stylelint + prettier)
  'apps/front/**/*.vue': [
    'cd apps/front && npx eslint --no-error-on-unmatched-pattern --fix',
    'cd apps/front && npx stylelint --fix',
    'cd apps/front && npx prettier --write',
  ],
  // Frontend: CSS files
  'apps/front/**/*.css': [
    'cd apps/front && npx stylelint --fix',
    'cd apps/front && npx prettier --write',
  ],
  // Frontend: i18n locale files (via Docker — consistent Node version)
  'apps/front/src/i18n/locales/*.json': [
    'docker compose exec -T frontend node scripts/i18n/format.mjs',
    'docker compose exec -T frontend node scripts/i18n/duplicates.mjs',
    'docker compose exec -T frontend node scripts/i18n/check.mjs',
  ],
  // Python backends: ruff (via Docker — not installed on host)
  'apps/screen/**/*.py': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/screen\//, ''))
    return [
      `docker compose exec -T screen ruff check --fix ${relative.join(' ')}`,
      `docker compose exec -T screen ruff format ${relative.join(' ')}`,
    ]
  },
  'apps/stream/**/*.py': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/stream\//, ''))
    return [
      `docker compose exec -T stream ruff check --fix ${relative.join(' ')}`,
      `docker compose exec -T stream ruff format ${relative.join(' ')}`,
      // Stream is mypy-clean; run on the whole project since mypy needs full context.
      'docker compose exec -T stream mypy app/ --ignore-missing-imports',
    ]
  },
  'apps/global-service/**/*.py': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/global-service\//, ''))
    return [
      `docker compose exec -T global-service ruff check --fix ${relative.join(' ')}`,
      `docker compose exec -T global-service ruff format ${relative.join(' ')}`,
    ]
  },
  // LogRecord-collision guard — `extra={"module": ...}` etc. crash at runtime.
  // Stdlib-only AST scan, runs on the host (no Docker round-trip).
  'apps/{screen,stream,global-service}/**/*.py': (filenames) => [
    `python3 .gitlab/scripts/check-logger-extra.py ${filenames.join(' ')}`,
  ],
  // Alembic migration chain sanity checks — once per touched app.
  // Fails on duplicate revision IDs and multi-head chains before they reach CI.
  'apps/screen/alembic/versions/**/*.py': () => ['.husky/scripts/check-alembic.sh screen'],
  'apps/stream/alembic/versions/**/*.py': () => ['.husky/scripts/check-alembic.sh stream'],
  'apps/global-service/alembic/versions/**/*.py': () => ['.husky/scripts/check-alembic.sh global-service'],
  // Target backend: PHP (via Docker — ECS + PHPStan not installed on host)
  // ECS runs on staged files only, PHPStan must analyse the whole project
  'apps/target/**/*.php': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/target\//, ''))
    return [
      `docker compose exec -T target php vendor/bin/ecs check --config=/app/ecs.php --fix ${relative.join(' ')}`,
      `docker compose exec -T target php vendor/bin/phpstan --memory-limit=1G analyse`,
    ]
  },
  // Taskfile validation + formatting
  'Taskfile.yml': ['cd apps/front && npx prettier --write ../../Taskfile.yml'],
  // Documentation
  'docs/**/*.md': (files) => [`cd apps/front && npx prettier --write ${files.map((f) => `../../${f}`).join(' ')}`],
  // Catch-all: format everything else not already handled
  '!(apps/**|node_modules/**|.husky/**|infra/**|docs/**)*.{json,yaml,yml,md}': (files) => [
    `cd apps/front && npx prettier --write --ignore-unknown ${files.map((f) => `../../${f}`).join(' ')}`,
  ],
}
