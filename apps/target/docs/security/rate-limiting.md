# Rate Limiting

<p align="center">
  <img src="../assets/rate-limiting-logo.svg" alt="Rate Limiting" width="120">
</p>

## Overview

The API implements rate limiting to protect against abuse and ensure fair usage. Rate limits are applied per user for authenticated requests and per IP address for anonymous requests.

The authenticated limit (2,000 requests/hour) is 4x higher than the anonymous limit (500 requests/hour) because authenticated users:

- Can be individually tracked and contacted if abuse is detected
- Have agreed to terms of service
- Typically have legitimate use cases requiring more API calls
- Can be rate-limited per user, preventing one user from affecting others

## Configuration

Rate limiting is configured in `api/config/packages/rate_limiter.yaml`:

```yaml
framework:
    rate_limiter:
        api_anonymous:
            policy: 'sliding_window'
            limit: 500
            interval: '1 hour'

        api_authenticated:
            policy: 'sliding_window'
            limit: 2000
            interval: '1 hour'
```

## Limits

| User Type      | Limit         | Window |
| -------------- | ------------- | ------ |
| Anonymous (IP) | 500 requests  | 1 hour |
| Authenticated  | 2000 requests | 1 hour |

## Response Headers

All API responses include rate limit information in the headers:

| Header                  | Description                                        |
| ----------------------- | -------------------------------------------------- |
| `X-RateLimit-Limit`     | Maximum number of requests allowed in the window   |
| `X-RateLimit-Remaining` | Number of requests remaining in the current window |
| `X-RateLimit-Reset`     | Unix timestamp when the rate limit window resets   |

Example response headers:

```
X-RateLimit-Limit: 2000
X-RateLimit-Remaining: 1998
X-RateLimit-Reset: 1701432000
```

## Rate Limit Exceeded

When the rate limit is exceeded, the API returns a `429 Too Many Requests` response:

```json
{
    "@context": "/api/contexts/Error",
    "@type": "hydra:Error",
    "hydra:title": "An error occurred",
    "hydra:description": "Rate limit exceeded. Please try again in 42 seconds."
}
```

The response includes a `Retry-After` header indicating the number of seconds to wait before making another request.

## Excluded Paths

The following paths are excluded from rate limiting:

- `/api/docs` - API documentation
- `/api/docs.jsonld` - API documentation (JSON-LD format)
- `/api/contexts/*` - JSON-LD contexts

## Implementation Details

### Identifier Hashing

For privacy and security, user identifiers and IP addresses are hashed using SHA-256 before being used as rate limiter keys. This ensures that:

- User email addresses are not stored in plain text in Redis
- IP addresses are not directly exposed in the rate limiter storage

### Sliding Window Policy

The rate limiter uses a sliding window policy, which provides smoother rate limiting compared to fixed windows:

- Requests are tracked over a rolling time window
- The limit gradually replenishes as old requests expire
- This prevents burst traffic at window boundaries

### Storage

Rate limit data is stored in Redis with automatic expiration. The keys follow the pattern:

- Authenticated: `user_<sha256_hash>`
- Anonymous: `ip_<sha256_hash>`

## Best Practices for API Consumers

1. **Monitor headers**: Always check the `X-RateLimit-Remaining` header to anticipate rate limit exhaustion
2. **Implement backoff**: When receiving a 429 response, wait for the duration specified in the `Retry-After` header
3. **Cache responses**: Cache API responses where appropriate to reduce the number of requests
4. **Use authentication**: Authenticated users have a higher rate limit (2000 vs 500 requests/hour)

## Webhook Rate Limiting

The webhook endpoint (`/api/webhook`) is also rate limited. Since webhooks authenticate using a virtual user, they share a rate limit pool identified by the `webhook` user identifier.

## Modifying Limits

To change the rate limits:

1. Update `api/config/packages/rate_limiter.yaml`
2. Update the constants in `RateLimitSubscriber.php` to match (for documentation purposes)
3. Clear the cache: `bin/console cache:clear`

Note: Changing limits does not affect existing rate limit windows. Users will see the new limits after their current window expires.
