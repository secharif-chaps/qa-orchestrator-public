export default {
  'src/**/*.{ts,tsx}': (files) => [
    `npx eslint --no-error-on-unmatched-pattern --fix ${files.join(' ')}`,
    `npx prettier --write ${files.join(' ')}`,
  ],
  'src/**/*.vue': (files) => [
    `npx eslint --no-error-on-unmatched-pattern --fix ${files.join(' ')}`,
    `npx stylelint --fix ${files.join(' ')}`,
    `npx prettier --write ${files.join(' ')}`,
  ],
  'src/**/*.css': (files) => [
    `npx stylelint --fix ${files.join(' ')}`,
    `npx prettier --write ${files.join(' ')}`,
  ],
  '!(src/**/*.{ts,tsx,vue,css})': 'npx prettier --write --ignore-unknown',
}
