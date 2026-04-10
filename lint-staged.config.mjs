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
  // Screen backend: Python (via Docker — ruff not installed on host)
  'apps/screen/**/*.py': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/screen\//, ''))
    return [
      `docker compose exec -T screen ruff check --fix ${relative.join(' ')}`,
      `docker compose exec -T screen ruff format ${relative.join(' ')}`,
    ]
  },
  // Target backend: PHP (via Docker — ECS + PHPStan not installed on host)
  // ECS runs on staged files only, PHPStan must analyse the whole project
  'apps/target/**/*.php': (filenames) => {
    const relative = filenames.map((f) => f.replace(/.*apps\/target\//, ''))
    return [
      `docker compose exec -T target php vendor/bin/ecs check --config=/app/ecs.php --fix ${relative.join(' ')}`,
      `docker compose exec -T target php vendor/bin/phpstan --memory-limit=1G analyse`,
    ]
  },
}
