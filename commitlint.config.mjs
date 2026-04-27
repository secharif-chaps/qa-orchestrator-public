// Commitlint configuration with gitmoji support and Jira ticket requirement
// Format: <gitmoji> <type>(scope): TAR-xxx description
// Exception: chore and docs commits can omit the ticket number

export default {
  extends: ['@commitlint/config-conventional'],
  plugins: [
    {
      rules: {
        'header-gitmoji-format': (parsed, _when, _value) => {
          const { header } = parsed
          if (!header) return [false, 'Header must not be empty']

          // Allow gitmoji prefix (emoji followed by space)
          // Then conventional commit format with optional TAR-xxx
          const pattern =
            /^(?:[\p{Emoji_Presentation}\p{Emoji}\u{FE0F}]+ )?[a-z]+(?:\(.+\))?!?: (?:TAR-\d+ )?.+/u
          const valid = pattern.test(header)

          return [valid, 'Header must match: <gitmoji> <type>(scope): TAR-xxx description']
        },
        'jira-ticket-required': (parsed, _when, _value) => {
          const { header, type } = parsed
          if (!header) return [false, 'Header must not be empty']

          // chore and docs commits don't require a ticket
          const exemptTypes = ['chore', 'docs']
          if (exemptTypes.includes(type)) return [true]

          // Check for TAR-xxx pattern after the colon
          const colonIndex = header.indexOf(': ')
          if (colonIndex === -1) return [false, 'Missing colon separator in header']

          const description = header.slice(colonIndex + 2)
          const hasTicket = /^TAR-\d+/.test(description)

          return [
            hasTicket,
            'Non-chore/docs commits must include a Jira ticket (TAR-xxx) in the description',
          ]
        },
      },
    },
  ],
  rules: {
    'header-gitmoji-format': [2, 'always'],
    'jira-ticket-required': [1, 'always'],
    'type-enum': [
      2,
      'always',
      [
        'feat',
        'fix',
        'docs',
        'style',
        'refactor',
        'perf',
        'test',
        'build',
        'ci',
        'chore',
        'revert',
      ],
    ],
    // Disable default header rules that conflict with gitmoji
    'header-max-length': [2, 'always', 120],
    'subject-empty': [0],
    'type-empty': [0],
  },
}
