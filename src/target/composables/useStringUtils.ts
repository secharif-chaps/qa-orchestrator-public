/**
 * Composable for string utility functions
 */

function unescapeString(str: string): string {
  return str.replace(/\\(.)/g, (match, char) => {
    switch (char) {
      case 'n':
        return '\n'
      case 't':
        return '\t'
      case 'r':
        return '\r'
      case 'v':
        return '\v'
      case 'f':
        return '\f'
      case '\\':
        return '\\'
      default:
        return char
    }
  })
}

export function useStringUtils() {
  return {
    unescapeString,
  }
}
