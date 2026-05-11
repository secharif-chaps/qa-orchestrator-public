# ADR-2025-001: Scalable Mercure Topics and Token Management

| Status   | Date       | Author                    |
| -------- | ---------- | ------------------------- |
| Accepted | 2025-12-29 | Frédéric Fayard-Le Barzic |

## Context

Target uses [Mercure](https://mercure.rocks/) for real-time updates (Server-Sent Events) to push notifications to
connected clients when resources change (WatchFiles, Conversations, Messages). The current implementation has
scalability issues that need to be addressed.

### Current Implementation Problems

#### 1. Token Bloat

The current `GenerateTokenHandler` generates JWT tokens containing explicit URLs for every resource the user has access
to:

```php
// Current implementation - api/src/Application/Mercure/GenerateTokenHandler.php
$payload = [
    'mercure' => [
        'subscribe' => [
            '/api/watch_files/uuid-1',
            '/api/watch_files/uuid-2',
            '/api/watch_files/uuid-3',
            // ... potentially 100+ entries
            '/api/conversations/conv-1/messages',
            '/api/conversations/conv-2/messages',
            // ... more entries
        ],
    ],
];
```

**Impact:**

- Token size grows linearly with user's resources (30 WatchFiles + 90 conversations = ~120 URLs)
- Token generation requires database queries to fetch all user resources
- Performance degrades as users accumulate more resources (~500ms for 30 WatchFiles)
- JWT tokens can become too large for HTTP headers

#### 2. Token Invalidation on Every Mutation

After every POST/PUT/PATCH/DELETE operation, the frontend forces a token refresh:

```typescript
// Current frontend behavior - pwa/api/api.ts
if (method !== 'GET') {
  await mercure.getMercureToken(true) // Force refresh
}
```

**Impact:**

- Additional HTTP request after every mutation
- Cache invalidation reduces efficiency
- Poor user experience with high-frequency operations

#### 3. Security Concerns with Resource-Based Topics

Current topics are resource-centric (`/api/watch_files/{id}`), which means:

- Anyone with a valid token could potentially subscribe to any resource topic if they know the UUID
- Security relies on the token containing only authorized resource URLs
- No namespace isolation between users

### Requirements

1. **Scalability**: Token size must remain constant regardless of resource count
2. **Security**: Users must only receive updates for resources they own or have access to
3. **Performance**: Minimize token regeneration and database queries
4. **Simplicity**: Solution must be maintainable and easy to understand

## Decision Drivers

- Token size must not grow with user resources
- Strong security isolation between users
- Minimal changes to existing frontend subscription logic
- Support for shared resources (WatchFiles accessible by multiple users)
- Leverage Mercure's built-in
  features ([URI Templates](https://mercure.rocks/spec#topic-selectors), [Subscription API](https://mercure.rocks/docs/hub/config))

## Considered Options

### Option A: URI Templates with Resource-Based Topics

**Description:**
Use Mercure's [URI Template feature (RFC 6570)](https://tools.ietf.org/html/rfc6570) in JWT tokens to authorize
pattern-based subscriptions while keeping resource-centric topics.

```php
// Token with URI Templates
$payload = [
    'mercure' => [
        'subscribe' => [
            '/watch-files/{id}',           // Template pattern
            '/conversations/{id}/messages', // Template pattern
        ],
    ],
];

// Publication (unchanged - exact topic)
$update = new Update('/watch-files/' . $watchFile->getId(), $data, true);
```

**How URI Templates work in Mercure:**

- The JWT token contains URI Templates (patterns with `{variables}`)
- When subscribing, the client uses exact topic URLs
- Mercure matches exact topics against the patterns in the JWT
- Example: Topic `/watch-files/abc-123` matches pattern `/watch-files/{id}`

**Pros:**

- Constant token size (2 entries instead of 100+)
- Single publication per resource update
- Minimal backend changes
- No frontend changes required for subscription logic

**Cons:**

- **Security gap**: Any authenticated user could subscribe to any resource topic if they know/guess the UUID
- Relies on UUID obscurity for security (not a strong security model)
- No user isolation at the topic level

### Option B: Single User Topic with Client-Side Routing

**Description:**
Use a single topic per user for all their events, with the message payload containing resource type and ID.

```php
// Token - single entry per user
$payload = [
    'mercure' => [
        'subscribe' => ["/users/{$userId}"],
    ],
];

// Publication - all events go to user's topic
$update = new Update(
    "/users/{$userId}",
    json_encode([
        'type' => 'watch_file.updated',
        'resourceId' => $watchFile->getId(),
        'data' => $serializedData,
    ]),
    true
);
```

**Pros:**

- Minimal token (single entry)
- Strong user isolation
- Single SSE connection per user

**Cons:**

- Client receives ALL events, even irrelevant ones (bandwidth waste)
- Complex client-side routing/filtering logic required
- Must publish to each authorized user (N publications for shared resources)
- Loses Mercure's native topic-based filtering capability
- Frontend must maintain routing logic for each resource type

### Option C: User-Prefixed Topics with URI Templates (Selected)

**Description:**
Prefix all topics with the user's UUID, combining security isolation with URI Template efficiency.

```php
// Token with user-scoped URI Templates
$payload = [
    'mercure' => [
        'subscribe' => [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
        ],
    ],
    'sub' => $userId,
];

// Publication - to each authorized user's namespace
foreach ($authorizedUserIds as $userId) {
    $topic = "/users/{$userId}/watch-files/{$watchFile->getId()}";
    $this->hub->publish(new Update($topic, $data, true));
}
```

**How it works:**

1. Each user's JWT only authorizes subscriptions to their own namespace (`/users/{their-uuid}/*`)
2. Frontend subscribes to topics in the user's namespace
3. Backend publishes to each authorized user's topic when a resource changes
4. Mercure's Subscription API can be used to skip publishing to disconnected users

**Pros:**

- Constant token size (2 entries regardless of resource count)
- Strong security: users can only subscribe to their own namespace
- Clear ownership model at the topic level
- Can leverage [Subscription API](https://mercure.rocks/docs/hub/config) to skip disconnected users
- No token refresh needed when creating new resources

**Cons:**

- Multiple publications for shared resources (one per authorized user)
- Slightly more complex publication logic
- Requires frontend update to include userId in topic URLs
- **Cannot use API Platform's automatic Mercure integration**

## Decision

**We choose Option C: User-Prefixed Topics with URI Templates.**

### Rationale

#### 1. Security First

By prefixing topics with user UUIDs, we create strong namespace isolation:

```
User A's JWT authorizes: /users/user-a-uuid/watch-files/{id}
User B's JWT authorizes: /users/user-b-uuid/watch-files/{id}

User A tries to subscribe to: /users/user-b-uuid/watch-files/some-watchfile
Result: ❌ REJECTED - pattern doesn't match
```

Even if User A knows the UUID of User B's WatchFile, they cannot subscribe to User B's topic because their JWT only
authorizes their own namespace.

#### 2. Scalable Token Size

The token contains exactly 3 URI Template entries regardless of whether the user has 1 or 1000 resources:

```php
'subscribe' => [
    "/users/{$userId}/watch-files/{id}",             // Covers ALL user's watchfiles
    "/users/{$userId}/conversations/{id}",           // Covers ALL user's conversations
    "/users/{$userId}/conversations/{id}/messages",  // Covers ALL user's conversation messages
]
```

#### 3. No Token Refresh on Mutations

Since the token uses templates, creating new resources doesn't require token refresh. The existing token already
authorizes subscriptions to any resource in the user's namespace.

```typescript
// Before: Token refresh after every mutation
if (method !== 'GET') {
  await mercure.getMercureToken(true) // ❌ No longer needed
}

// After: Token remains valid for new resources
// No action needed - template already covers new resources
```

#### 4. Leverages Mercure Features

- Uses standard [Mercure URI Templates (RFC 6570)](https://mercure.rocks/spec#topic-selectors)
- Uses the [Subscription API](https://mercure.rocks/docs/hub/config) (already enabled: `subscriptions` directive in
  Caddyfile) for optimization
- Compatible with Mercure's private updates (`private: true`)

#### 5. Acceptable Trade-off for Multiple Publications

The cost of multiple publications for shared resources is acceptable because:

- **Most WatchFiles are personal** (single owner) - no multiplication
- **Subscription API optimization** - skip publishing to disconnected users
- **Publication is async** - non-blocking, handled by message queue
- **Mercure is efficient** - designed for high-throughput publishing

### Why Not the Other Options?

| Option                             | Primary Rejection Reason                                                                                                                                                            |
| ---------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **A: Resource Topics + Templates** | **Security gap** - no user isolation. Any user with a valid JWT could subscribe to any topic if they guess the UUID. This violates the principle of least privilege.                |
| **B: Single User Topic**           | **Inefficient** - all events sent to client regardless of relevance. Wastes bandwidth and requires complex client-side routing. Also loses Mercure's native filtering capabilities. |

## Impact on Existing Implementation

### 1. API Platform Mercure Integration - BREAKING CHANGE

**Current state:**
The `WatchFile` entity uses API Platform's automatic Mercure integration:

```php
// api/src/Domain/WatchFile/WatchFile.php
#[ApiResource(
    mercure: [
        'private' => true,
    ],
)]
class WatchFile { ... }
```

This automatically:

- Adds `Link` header with Mercure hub URL in responses
- Publishes updates to topic `/api/watch_files/{id}` when resources change

**Impact:**

- **We must disable API Platform's automatic Mercure publication** (`mercure: false`)
- The automatic topic format (`/api/watch_files/{id}`) doesn't include the user namespace
- We cannot customize the topic format in API Platform's Mercure integration
- **Manual publication is required** using our new `MercurePublisher` service

**Migration:**

```php
// BEFORE - api/src/Domain/WatchFile/WatchFile.php
#[ApiResource(
    mercure: ['private' => true],  // ❌ Remove this
)]
class WatchFile { ... }

// AFTER
#[ApiResource(
    mercure: false,  // ✅ Disable automatic publication
)]
class WatchFile { ... }
```

Similarly for other entities:

- `Conversation.php` - already has `mercure: false`
- `Message.php` - uses custom `MercureSSEMessageUpdateNotifier`

### 2. Subscription API Security - CRITICAL

**Current state:**
The Caddyfile enables the Subscription API publicly:

```caddyfile
# api/frankenphp/Caddyfile

mercure {
    subscriptions  # ⚠️ Exposes /.well-known/mercure/subscriptions publicly
}
```

**Security risk:**
The endpoint `/.well-known/mercure/subscriptions` is accessible to anyone and reveals:

- Which topics have active subscribers
- Subscriber IDs (can be user identifiers)
- Active/inactive subscription status

This is an **information disclosure vulnerability**:

- Attackers can enumerate active users
- Attackers can discover which resources are being monitored
- Privacy violation - reveals user activity patterns

**Mitigation options:**

#### Option 2a: Restrict Subscription API to Internal Network Only (Recommended)

```caddyfile
# api/frankenphp/Caddyfile
{$SERVER_NAME:localhost}{$CADDY_HTTP_PORT} {
   # ... existing config ...

   mercure {
        subscriptions
        # ... other directives ...
   }

   # Block external access to Subscription API
   @subscription_api path /.well-known/mercure/subscriptions*
   handle @subscription_api {
        # Only allow internal Docker network
        @internal remote_ip 172.16.0.0/12 192.168.0.0/16 10.0.0.0/8 127.0.0.1
        handle @internal {
            # Allow internal requests to pass through to Mercure
            reverse_proxy localhost:80
        }
        handle {
            respond "Forbidden" 403
        }
    }
}
```

#### Option 2b: Use Separate Internal Mercure Endpoint

Create a separate internal-only endpoint for the Subscription API:

```caddyfile
# Internal endpoint (only accessible within Docker network)

:3001 {
    mercure {
        subscriptions
        publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} {env.MERCURE_PUBLISHER_JWT_ALG}
    }
}

# Public endpoint (no Subscription API)
{$SERVER_NAME:localhost}{$CADDY_HTTP_PORT} {
    mercure {
       # subscriptions  # ❌ Disabled for public access
       publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} {env.MERCURE_PUBLISHER_JWT_ALG}
       subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} {env.MERCURE_SUBSCRIBER_JWT_ALG}
    }
}
```

#### Option 2c: Disable Subscription API Entirely

If the optimization isn't critical, simply disable it:

```caddyfile
mercure {
# subscriptions  # ❌ Commented out - API disabled
    publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} {env.MERCURE_PUBLISHER_JWT_ALG}
    subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} {env.MERCURE_SUBSCRIBER_JWT_ALG}
}
```

**Recommendation:** Option 2a (restrict to internal network) provides the best balance of security and functionality.

### 3. Frontend Discovery Mechanism

**Current state:**
The frontend discovers Mercure topics from API responses via `Link` headers:

```typescript
// pwa/api/api.ts
function discoverMercure(response: Response): MercureResponse | undefined {
  const linkHeader = response.headers.get('Link')
  const mercureMatch = linkHeader.match(/<([^>]+)>;\s*rel="mercure"/)
  // Returns topic from response URL
}
```

**New Approach:**

The `MercureDiscoverySubscriber` now adds two separate `Link` headers following RFC 8288:

- `Link: <hub-url>; rel="mercure"` - The Mercure hub URL for SSE connections
- `Link: <topic>; rel="topic"` - The user-scoped topic to subscribe to

**Supported routes (GET only):**

| Route                                  | Topic Format                                              |
| -------------------------------------- | --------------------------------------------------------- |
| `GET /api/watch_files/{id}`            | `/users/{userId}/watch-files/{watchFileId}`               |
| `GET /api/conversations/{id}`          | `/users/{userId}/conversations/{conversationId}`          |
| `GET /api/conversations/{id}/messages` | `/users/{userId}/conversations/{conversationId}/messages` |

**Migration:**

```typescript
// BEFORE - topic from response URL
const topic = response.url // /api/watch_files/{id}

// AFTER - topic from Link header with rel="topic"
function discoverMercure(response: Response): { hubUrl: string; topic: string } | undefined {
  const linkHeader = response.headers.get('Link')
  if (!linkHeader) return undefined

  const hubMatch = linkHeader.match(/<([^>]+)>;\s*rel="mercure"/)
  const topicMatch = linkHeader.match(/<([^>]+)>;\s*rel="topic"/)

  if (!hubMatch || !topicMatch) return undefined

  return {
    hubUrl: hubMatch[1],
    topic: topicMatch[1], // Already user-scoped: /users/{userId}/...
  }
}
```

## Technical Implementation

### 1. Topic Format

```
/users/{userId}/watch-files/{watchFileId}
/users/{userId}/conversations/{conversationId}
/users/{userId}/conversations/{conversationId}/messages
```

Example topics:

```
/users/550e8400-e29b-41d4-a716-446655440000/watch-files/7c9e6679-7425-40de-944b-e07fc1f90ae7
/users/550e8400-e29b-41d4-a716-446655440000/conversations/a1b2c3d4-e5f6-7890-abcd-ef1234567890
/users/550e8400-e29b-41d4-a716-446655440000/conversations/a1b2c3d4-e5f6-7890-abcd-ef1234567890/messages
```

### 2. New Service: MercureTopicGenerator

```php
<?php
// src/Infrastructure/Mercure/MercureTopicGenerator.php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Chat\Conversation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;

final readonly class MercureTopicGenerator
{
    /**
     * Generate topic for a WatchFile update scoped to a specific user.
     */
    public function forWatchFile(User $user, WatchFile $watchFile): string
    {
        return sprintf(
            '/users/%s/watch-files/%s',
            $user->getId()->toRfc4122(),
            $watchFile->getId()->toRfc4122()
        );
    }

    /**
     * Generate topic for Conversation messages scoped to a specific user.
     */
    public function forConversation(User $user, Conversation $conversation): string
    {
        return sprintf(
            '/users/%s/conversations/%s',
            $user->getId()->toRfc4122(),
            $conversation->getId()->toRfc4122()
        );
    }

    /**
     * Generate topic for Conversation messages scoped to a specific user.
     */
    public function forConversationMessages(User $user, Conversation $conversation): string
    {
        return sprintf(
            '/users/%s/conversations/%s/messages',
            $user->getId()->toRfc4122(),
            $conversation->getId()->toRfc4122()
        );
    }

    /**
     * Get URI Templates for JWT token subscription claims.
     * These templates authorize the user to subscribe to any resource in their namespace.
     *
     * @return string[] URI Templates for JWT token
     */
    public function getSubscriptionTemplates(User $user): array
    {
        $userId = $user->getId()->toRfc4122();

        return [
            "/users/{$userId}/watch-files/{id}",
            "/users/{$userId}/conversations/{id}",
            "/users/{$userId}/conversations/{id}/messages",
        ];
    }
}
```

### 3. Modified Token Generation

```php
<?php
// src/Application/Mercure/GenerateTokenHandler.php

declare(strict_types=1);

namespace App\Application\Mercure;

use App\Infrastructure\Mercure\MercureTopicGenerator;
use Firebase\JWT\JWT;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class GenerateTokenHandler
{
    public function __construct(
        private TagAwareCacheInterface $cache,
        private MercureTopicGenerator $topicGenerator,
        private string $mercureSecret,
    ) {}

    public function __invoke(GenerateTokenAction $action): TokenDto
    {
        $user = $action->user;
        $cacheKey = 'mercure_token_v2_' . $user->getId()->toRfc4122();

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user) {
            // Cache for almost the full token lifetime (minus 60s buffer)
            $item->expiresAfter(3540); // 59 minutes
            $item->tag(['mercure_token', 'user_' . $user->getId()->toRfc4122()]);

            $now = time();
            $payload = [
                'mercure' => [
                    // URI Templates - covers ALL resources in user's namespace
                    'subscribe' => $this->topicGenerator->getSubscriptionTemplates($user),
                    'publish' => [], // Clients cannot publish
                ],
                'sub' => $user->getUserIdentifier(),
                'iat' => $now,
                'exp' => $now + 3600, // 1 hour
            ];

            return new TokenDto(
                token: JWT::encode($payload, $this->mercureSecret, 'HS256'),
                expiresAt: $payload['exp']
            );
        });
    }
}
```

### 4. Modified Publication Logic

```php
<?php
// src/Infrastructure/Mercure/MercurePublisher.php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Domain\Chat\Message;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class MercurePublisher
{
    public function __construct(
        private HubInterface $hub,
        private MercureTopicGenerator $topicGenerator,
        private SerializerInterface $serializer,
        private ?ActiveSubscribersChecker $subscribersChecker = null,
    ) {}

    /**
     * Publish WatchFile update to all authorized users.
     *
     * @param User[] $authorizedUsers Users who should receive the update
     */
    public function publishWatchFileUpdate(WatchFile $watchFile, array $authorizedUsers): void
    {
        $data = $this->serializer->serialize($watchFile, 'json', [
            'groups' => ['watch_file:read'],
        ]);

        foreach ($authorizedUsers as $user) {
            $topic = $this->topicGenerator->forWatchFile($user, $watchFile);

            // Optional optimization: skip if no active subscribers
            if ($this->subscribersChecker !== null
                && !$this->subscribersChecker->hasActiveSubscribers($topic)) {
                continue;
            }

            $this->hub->publish(new Update($topic, $data, private: true));
        }
    }

    /**
     * Publish Message update to conversation owner.
     */
    public function publishMessageUpdate(Message $message, User $conversationOwner): void
    {
        $conversation = $message->getConversation();
        if ($conversation === null) {
            return;
        }

        $topic = $this->topicGenerator->forConversation($conversationOwner, $conversation);
        $data = $this->serializer->serialize($message, 'json', [
            'groups' => ['message:read'],
        ]);

        $this->hub->publish(new Update($topic, $data, private: true));
    }
}
```

### 5. Active Subscribers Checker (Optional Optimization)

This service uses Mercure's [Subscription API](https://mercure.rocks/docs/hub/config) to check if anyone is currently
subscribed to a topic before publishing.

**Important:** This API must be restricted to internal access only (see Security section).

```php
<?php
// src/Infrastructure/Mercure/ActiveSubscribersChecker.php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ActiveSubscribersChecker
{
    /** @var array<array{topic: string, active: bool}> */
    private array $cache = [];
    private float $cacheExpiry = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $mercureInternalUrl, // Internal URL only!
        private readonly string $publisherJwt,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Check if there are active subscribers for a given topic.
     * Uses Mercure's Subscription API: /.well-known/mercure/subscriptions
     */
    public function hasActiveSubscribers(string $topic): bool
    {
        $subscriptions = $this->getActiveSubscriptions();

        foreach ($subscriptions as $subscription) {
            if ($subscription['topic'] === $topic && $subscription['active']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array{topic: string, active: bool, subscriber: string}>
     */
    private function getActiveSubscriptions(): array
    {
        // Cache for 5 seconds to avoid hammering the Subscription API
        if (microtime(true) < $this->cacheExpiry) {
            return $this->cache;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                $this->mercureInternalUrl . '/.well-known/mercure/subscriptions',
                [
                    'headers' => ['Authorization' => 'Bearer ' . $this->publisherJwt],
                    'timeout' => 1, // Fast timeout - this is an optimization
                ]
            );

            $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->cache = $data['subscriptions'] ?? [];
            $this->cacheExpiry = microtime(true) + 5;

            return $this->cache;
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to fetch Mercure subscriptions: ' . $e->getMessage());

            // On error, assume subscribers exist (fail-open for availability)
            return [];
        }
    }
}
```

### 6. Frontend Changes

```typescript
// pwa/composables/useMercureTopics.ts

export function useMercureTopics() {
  const { user } = useAuth()

  const assertAuthenticated = (): string => {
    if (!user.value?.id) {
      throw new Error('User must be authenticated to generate Mercure topics')
    }
    return user.value.id
  }

  const watchFileTopic = (watchFileId: string): string => {
    return `/users/${assertAuthenticated()}/watch-files/${watchFileId}`
  }

  const conversationTopic = (conversationId: string): string => {
    return `/users/${assertAuthenticated()}/conversations/${conversationId}`
  }

  const conversationMessagesTopic = (conversationId: string): string => {
    return `/users/${assertAuthenticated()}/conversations/${conversationId}/messages`
  }

  return {
    watchFileTopic,
    conversationTopic,
    conversationMessagesTopic,
  }
}
```

```typescript
// pwa/api/watchFile.ts - Updated subscription keys

export const createWatchFileSubscribeKeys = (userId: string) => ({
  byId: (watchFileId: string) => `/users/${userId}/watch-files/${watchFileId}`,
})

export const createConversationSubscribeKeys = (userId: string) => ({
  byId: (conversationId: string) => `/users/${userId}/conversations/${conversationId}`,
  messages: (conversationId: string) => `/users/${userId}/conversations/${conversationId}/messages`,
})
```

```typescript
// pwa/composables/useMercure.ts - Remove forced token refresh

// REMOVE this logic:
// if (method !== 'GET') {
//     await mercure.getMercureToken(true);
// }

// Token with URI Templates remains valid for new resources
```

## Infrastructure Constraints

### HTTP/2 Requirement

**Constraint:** The application MUST be served over HTTP/2 (or HTTP/3) for optimal SSE connection handling.

#### Browser Connection Limits

| Protocol | Connection Limit     | SSE Impact                               |
| -------- | -------------------- | ---------------------------------------- |
| HTTP/1.1 | 6 per domain         | Problematic - SSE holds connections open |
| HTTP/2   | Multiplexed on 1 TCP | No practical limit                       |

With HTTP/1.1, browsers enforce a maximum of **6 concurrent connections per domain**. Since each SSE subscription (
`EventSource`) holds a connection open indefinitely, opening 3-5 SSE connections would consume most of the available
connection pool, blocking other HTTP requests.

**HTTP/2 solves this** by multiplexing all streams over a single TCP connection, allowing hundreds of concurrent SSE
subscriptions without connection pool exhaustion.

#### Current Configuration

Target uses **Caddy** as the web server with HTTPS, which **automatically enables HTTP/2**:

```caddyfile
# api/frankenphp/Caddyfile
{$SERVER_NAME:localhost}{$CADDY_HTTP_PORT} {
                                           # HTTPS + HTTP/2 enabled by default with Caddy
                                           # ...
                                           }
```

✅ **Current setup is compatible** - HTTP/2 is active by default on `https://basil.local`.

#### Scenarios Where This Could Be Problematic

1. **Local development without HTTPS** → Falls back to HTTP/1.1
2. **Reverse proxy forcing HTTP/1.1** → Loses HTTP/2 multiplexing
3. **Very old browsers** → No HTTP/2 support (Edge < 14, Safari < 9)
4. **Corporate proxies** → May downgrade to HTTP/1.1

#### Future Optimization: SSE Consolidation

If HTTP/2 becomes unavailable or connection limits become an issue, the topic format already supports **consolidating
multiple topics into a single SSE connection**:

```typescript
// Current: Multiple EventSource connections (works with HTTP/2)
subscribe(hubUrl, ['/users/{userId}/watch-files/{wf1}'], options, 'wf1')
subscribe(hubUrl, ['/users/{userId}/watch-files/{wf2}'], options, 'wf2')
subscribe(hubUrl, ['/users/{userId}/conversations/{c1}'], options, 'c1')

// Future optimization: Single EventSource with multiple topics
subscribe(
  hubUrl,
  [
    '/users/{userId}/watch-files/{wf1}',
    '/users/{userId}/watch-files/{wf2}',
    '/users/{userId}/conversations/{c1}',
  ],
  options,
  'consolidated',
)
```

Mercure natively supports subscribing to multiple topics in a single connection. The frontend would then route incoming
messages based on the topic in the event:

```typescript
eventSource.onmessage = (event) => {
  const data = JSON.parse(event.data)
  const topic = new URL(event.lastEventId).pathname // Or use custom header

  // Client-side routing based on topic
  if (topic.includes('/watch-files/')) {
    handleWatchFileUpdate(data)
  } else if (topic.includes('/conversations/')) {
    handleConversationUpdate(data)
  }
}
```

**Current recommendation:** Keep the simpler multi-connection approach while HTTP/2 is available. The consolidation
pattern is a fallback if HTTP/2 constraints emerge.

## Consequences

### Positive

1. **Constant Token Size**: Token contains exactly 2 URI Template entries regardless of resource count
2. **Strong Security**: User namespace isolation prevents unauthorized subscriptions
3. **Improved Cache Efficiency**: Token cache duration extended from 30s to ~59 minutes
4. **No Mutation Refresh**: Creating new resources doesn't require token refresh
5. **Optimized Publishing**: Subscription API allows skipping disconnected users
6. **Clear Ownership Model**: Topics clearly indicate which user they belong to

### Negative

1. **Multiple Publications**: Shared resources require publishing to each authorized user's topic
2. **API Platform Integration Lost**: Cannot use automatic Mercure publication from API Platform
3. **Migration Complexity**: Existing subscriptions must be updated to new topic format
4. **Frontend Update Required**: Topic generation logic must include user ID
5. **Coordinated Deployment**: Frontend and backend must be deployed together

### Neutral

1. **Subscription API Security**: Must be restricted to internal access (additional config required)
2. **Topic Format Change**: Breaking change but improves long-term maintainability

## Performance Comparison

| Metric                           | Before           | After                       |
| -------------------------------- | ---------------- | --------------------------- |
| Token size (30 WatchFiles)       | ~4KB             | ~200B                       |
| Token generation time            | ~500ms           | <10ms                       |
| Token cache duration             | 30s              | 3540s (59 min)              |
| DB queries for token             | N (per resource) | 0                           |
| Publications per shared resource | 1                | N (per authorized user)     |
| Subscription API calls           | 0                | 0-N (optional optimization) |

## Migration Plan

### Phase 1: Security First

1. Restrict Subscription API to internal network only
2. Audit current Mercure usage

### Phase 2: Backend Preparation

1. Add `MercureTopicGenerator` service
2. Add `MercurePublisher` service
3. Add `ActiveSubscribersChecker` service (optional)
4. Update `GenerateTokenHandler` to use URI Templates
5. Set `mercure: false` on entities using automatic publication

### Phase 3: Dual Publication (Transition)

1. Publish to both old and new topic formats temporarily
2. Deploy backend changes
3. Monitor for issues

### Phase 4: Frontend Update

1. Update subscription logic to use new topic format with userId
2. Remove forced token refresh after mutations
3. Deploy frontend changes

### Phase 5: Cleanup

1. Remove dual publication logic
2. Remove old topic format support
3. Update tests

## Security Considerations

### Topic Isolation

- Each user's JWT only authorizes their namespace
- Pattern: `/users/{their-uuid}/*`
- Cross-user subscription attempts are rejected by Mercure

### UUID Security

- UUIDs are cryptographically random (UUIDv4)
- 122 bits of entropy makes guessing infeasible
- Combined with namespace isolation provides defense in depth

### Private Updates

- All updates use `private: true`
- Mercure verifies JWT claims before delivery
- No anonymous access to real-time updates

### Subscription API Protection

- Must be restricted to internal network
- Prevents information disclosure about active users
- Backend-only access for optimization purposes

## References

- [Mercure Specification](https://mercure.rocks/spec)
- [Mercure Topic Selectors (URI Templates)](https://mercure.rocks/spec#topic-selectors)
- [RFC 6570 - URI Template](https://tools.ietf.org/html/rfc6570)
- [Mercure Hub Configuration](https://mercure.rocks/docs/hub/config)
- [Mercure Subscription API](https://mercure.rocks/docs/hub/config) - `subscriptions` directive
- [Symfony Mercure Component](https://symfony.com/doc/current/mercure.html)
- [API Platform Mercure Integration](https://api-platform.com/docs/core/mercure/)

## Appendix: Topic Matching Examples

### Successful Matches

```
JWT Subscribe Claim: /users/abc-123/watch-files/{id}

Topic: /users/abc-123/watch-files/wf-456  → ✅ MATCH
Topic: /users/abc-123/watch-files/wf-789  → ✅ MATCH
Topic: /users/abc-123/watch-files/any-uuid → ✅ MATCH
```

### Rejected Matches

```
JWT Subscribe Claim: /users/abc-123/watch-files/{id}

Topic: /users/xyz-999/watch-files/wf-456  → ❌ NO MATCH (different user)
Topic: /watch-files/wf-456                → ❌ NO MATCH (no user prefix)
Topic: /users/abc-123/conversations/c-1   → ❌ NO MATCH (different resource type)
Topic: /api/watch_files/wf-456            → ❌ NO MATCH (old format)
```

### Multiple Users Scenario

```
WatchFile "project-x" is shared between User A and User B

Publication targets:
  - /users/user-a-uuid/watch-files/project-x  → User A receives update
  - /users/user-b-uuid/watch-files/project-x  → User B receives update

User C (not authorized) tries to subscribe to:
  - /users/user-a-uuid/watch-files/project-x  → ❌ REJECTED (not their namespace)
  - /users/user-c-uuid/watch-files/project-x  → No publication sent to this topic
```
