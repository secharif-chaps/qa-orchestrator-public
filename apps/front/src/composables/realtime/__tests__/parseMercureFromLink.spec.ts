import { describe, expect, it } from 'vitest'
import { parseMercureFromLink } from '../useRealtime'

describe('parseMercureFromLink', () => {
  it('parses rel="mercure" header to extract hub URL', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toBeDefined()
    expect(result?.hubUrl).toBe('https://basil.local/.well-known/mercure')
  })

  it('parses rel="topic" header to extract user-scoped topic', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toBeDefined()
    expect(result?.topics).toEqual(['/users/user-123/watch-files/wf-456'])
  })

  it('returns undefined when Link header is missing', () => {
    const result = parseMercureFromLink(null)

    expect(result).toBeUndefined()
  })

  it('returns undefined when rel="mercure" is missing', () => {
    const linkHeader = '</users/user-123/watch-files/wf-456>; rel="topic"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toBeUndefined()
  })

  it('returns undefined when rel="topic" is missing', () => {
    const linkHeader = '<https://basil.local/.well-known/mercure>; rel="mercure"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toBeUndefined()
  })

  it('returns a Mercure push descriptor with type, hubUrl and topics', () => {
    const linkHeader =
      '<https://hub.example.com/mercure>; rel="mercure", </users/abc-123/watch-files/def-456>; rel="topic"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toEqual({
      type: 'mercure',
      hubUrl: 'https://hub.example.com/mercure',
      topics: ['/users/abc-123/watch-files/def-456'],
    })
  })

  it('extracts multiple topics from Link header', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic", </users/user-123/conversations/conv-789/messages>; rel="topic"'

    const result = parseMercureFromLink(linkHeader)

    expect(result).toBeDefined()
    expect(result?.hubUrl).toBe('https://basil.local/.well-known/mercure')
    expect(result?.topics).toEqual([
      '/users/user-123/watch-files/wf-456',
      '/users/user-123/conversations/conv-789/messages',
    ])
  })
})
