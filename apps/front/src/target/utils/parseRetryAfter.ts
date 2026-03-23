export function parseRetryAfter(response: Response): number {
  const header = response.headers.get('Retry-After')
  if (!header) return 60

  const seconds = Number(header)
  if (!Number.isNaN(seconds) && seconds > 0) return seconds

  const date = Date.parse(header)
  if (!Number.isNaN(date)) {
    const diff = Math.ceil((date - Date.now()) / 1000)
    return diff > 0 ? diff : 60
  }

  return 60
}
