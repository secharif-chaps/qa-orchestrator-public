export default {
  'apps/front/**/*.{ts,vue,js}': [
    'cd apps/front && yarn eslint --fix',
    'cd apps/front && yarn prettier --write',
  ],
  'apps/front/**/*.{css,vue}': [
    'cd apps/front && yarn stylelint --fix',
  ],
  'apps/screen/**/*.py': [
    'cd apps/screen && ruff check --fix',
    'cd apps/screen && ruff format',
  ],
}
