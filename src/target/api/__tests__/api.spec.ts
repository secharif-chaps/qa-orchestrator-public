import { describe, expect, it } from 'vitest';
import { discoverMercure } from '../api';

/**
 * Creates a mock Response object with the specified Link header
 */
function createMockResponse(linkHeader: string | null): Response {
  const headers = new Headers();
  if (linkHeader !== null) {
    headers.set('Link', linkHeader);
  }
  return {
    headers,
    url: 'https://basil.local/api/watch_files/123',
  } as Response;
}

describe('discoverMercure', () => {
  it('parses rel="mercure" header to extract hub URL', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toBeDefined();
    expect(result?.url).toBe('https://basil.local/.well-known/mercure');
  });

  it('parses rel="topic" header to extract user-scoped topic', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toBeDefined();
    expect(result?.topics).toEqual(['/users/user-123/watch-files/wf-456']);
  });

  it('returns undefined when Link header is missing', () => {
    const response = createMockResponse(null);

    const result = discoverMercure(response);

    expect(result).toBeUndefined();
  });

  it('returns undefined when rel="mercure" is missing', () => {
    const linkHeader = '</users/user-123/watch-files/wf-456>; rel="topic"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toBeUndefined();
  });

  it('returns undefined when rel="topic" is missing', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toBeUndefined();
  });

  it('returns correct MercureResponse structure with url and topics array', () => {
    const linkHeader =
      '<https://hub.example.com/mercure>; rel="mercure", </users/abc-123/watch-files/def-456>; rel="topic"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toEqual({
      url: 'https://hub.example.com/mercure',
      topics: ['/users/abc-123/watch-files/def-456'],
    });
  });

  it('extracts multiple topics from Link header', () => {
    const linkHeader =
      '<https://basil.local/.well-known/mercure>; rel="mercure", </users/user-123/watch-files/wf-456>; rel="topic", </users/user-123/conversations/conv-789/messages>; rel="topic"';
    const response = createMockResponse(linkHeader);

    const result = discoverMercure(response);

    expect(result).toBeDefined();
    expect(result?.url).toBe('https://basil.local/.well-known/mercure');
    expect(result?.topics).toEqual([
      '/users/user-123/watch-files/wf-456',
      '/users/user-123/conversations/conv-789/messages',
    ]);
  });
});
