# 📡 Data Collection System

This document describes the complete architecture of Basil's data collection system, including integration with the external provider Bakus.

## 📚 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Collection Process](#collection-process)
4. [Data Schema](#data-schema)
5. [Connection Methods](#connection-methods)
6. [Source ↔ Collector Mapping](#source-collector-mapping)
7. [Collector Parameters](#collector-parameters)
8. [Authentication & Security](#authentication-security)
9. [Events & Handlers](#events-handlers)

---

## 🎯 Overview

The collection system retrieves data from external sources (RSS feeds, social media, websites, etc.) via the **Bakus** provider, which acts as a collector aggregator.

### Simplified collection flow

```
┌──────────┐      ┌───────────┐      ┌────────┐      ┌──────────┐
│  Source  │─────▶│ Basil     │─────▶│ Bakus  │─────▶│Document  │
│  (Type)  │      │CollectTask│      │Collector      │  Stored  │
└──────────┘      └───────────┘      └────────┘      └──────────┘
```

---

## 🏗️ Architecture

### Main components

```
┌─────────────────────────────────────────────────────┐
│                   Application                       │
│  ┌──────────────────────────────────────────────┐   │
│  │        CollectDataReceivedEventListener      │   │
│  │     (Chain of Responsibility Pattern)        │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────┐
│                      Domain                         │
│  ┌──────────────┐  ┌────────────────────────────┐   │
│  │ CollectTask  │  │  CollectDataHandler        │   │
│  │   (Entity)   │  │     (Interface)            │   │
│  └──────────────┘  └────────────────────────────┘   │
│  ┌──────────────┐  ┌────────────────────────────┐   │
│  │   Source     │  │  CollectDataReceivedEvent  │   │
│  │  (Entity)    │  │       (Event)              │   │
│  └──────────────┘  └────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────┐
│                 Infrastructure                      │
│  ┌──────────────────────────────────────────────┐   │
│  │     BakusCollectTaskMapper (Mapping)         │   │
│  └──────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────┐   │
│  │  BakusWebSocketClient (Pull Stream)          │   │
│  │  BakusWebSocketPush   (Push Server)          │   │
│  └──────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────┐   │
│  │  MergedResultCollectDataHandler              │   │
│  │  (Concrete Handler Implementation)           │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

### Architectural layers

- **Domain**: Business entities (`CollectTask`, `Source`), events, interfaces
- **Application**: Orchestration, listeners, use cases
- **Infrastructure**: Concrete implementations, external integrations (Bakus)

---

## 🔄 Collection Process

### 1. Creating a collect task

```php
// A Source is associated with a WatchFile
$source = new Source(
    name: 'TechCrunch RSS',
    type: SourceType::RSS_FEED,
    url: 'https://techcrunch.com/feed/',
    // ...
);

// Creating the collect task
$collectTask = new CollectTask(
    source: $source,
    watchFile: $watchFile,
    providerName: 'bakus'
);
```

### 2. Mapping to Bakus

The `BakusCollectTaskMapper` transforms the Basil task into a Bakus request:

```php
$bakusQuery = [
    'collector_modules' => [
        [
            'name' => 'rss.bakus',           // Bakus collector name
            'version' => '1.0.0',
            'key' => 'rss_feed',             // Collection type
            'value' => 'https://techcrunch.com/feed/',
            'parameters' => [
                'recursive_request_content' => true
            ],
            'max_cache_age' => null
        ]
    ],
    'postprocess_modules' => [],
    'mode' => 'stream',                       // Streaming mode
    'result_limit' => 100000000,              // Very high limit
    'callback' => [
        'type' => 'websocket_pull',           // or 'websocket_push'
        'include_status_update' => true,
        'url' => 'https://basil.local/webhook/collect',
        'verify_ssl' => false,
        'headers' => [
            'Authorization' => 'Bearer eyJ0eXAi...'  // JWT Token
        ]
    ]
];
```

### 3. Executing the collection

Depending on the configured mode (`BAKUS_CALLBACK_TYPE`):

#### **Pull** mode (WebSocket Client)

```bash
# Basil connects to Bakus
php bin/console collect:pull-stream <collect-task-id>
```

1. Basil creates the request via Bakus HTTP API
2. Basil connects to Bakus WebSocket
3. Bakus sends collected data via WebSocket
4. Basil processes data in real-time

#### **Push** mode (WebSocket Server)

```bash
# Basil starts a WebSocket server
php bin/console collect:push-stream 0.0.0.0:5000
```

1. Basil starts a WebSocket server
2. Basil creates the request via Bakus HTTP API with callback URL
3. Bakus connects to Basil's WebSocket server
4. Bakus sends collected data
5. Basil processes data in real-time

### 4. Receiving data

Data arrives via `CollectDataReceivedEvent` events:

```php
{
    "collectTaskId": "01JBXX...",
    "providerTaskId": "bakus-task-123",
    "data": {
        "type": "merged_result",           // Data type
        "result": {
            "hash_document_sha1": "abc123...",
            "document_origin": "techcrunch.com",
            "title": "New AI breakthrough",
            "url": "https://techcrunch.com/...",
            "date": "2025-01-30T10:00:00Z"
        }
    }
}
```

### 5. Processing via Handlers

The `CollectDataReceivedEventListener` dispatches to appropriate handlers:

```php
foreach ($this->collectDataHandlers as $handler) {
    if ($handler->supports($event)) {
        $handler($event);  // Process data
    }
}
```

---

## 📊 Data Schema

### CollectTask structure

```php
class CollectTask
{
    private ?string $id;                        // ULID
    private Source $source;                     // Data source
    private WatchFile $watchFile;               // Watch folder
    private string $providerName;               // 'bakus'
    private ?string $providerTaskId;            // Task ID on Bakus side
    private CollectTaskStatus $status;          // PENDING, QUEUED, RUNNING, etc.
    private ?\DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $completedAt;
    private array $metadata;                    // Additional metadata
}
```

### CollectTask states

```php
enum CollectTaskStatus: string
{
    case PENDING = 'pending';           // Waiting for creation
    case QUEUED = 'queued';             // Queued at Bakus
    case RUNNING = 'running';           // Currently executing
    case COMPLETED = 'completed';       // Successfully completed
    case FAILED = 'failed';             // Failed
    case CANCELLED = 'cancelled';       // Cancelled
}
```

### Received data types

The `data.type` field indicates the data type:

| Type                      | Description                  | Handler                          |
| ------------------------- | ---------------------------- | -------------------------------- |
| `merged_result`           | Merged and enriched document | `MergedResultCollectDataHandler` |
| `raw_result`              | Unprocessed raw document     | _(To be implemented)_            |
| `document_refined_result` | Refined document             | _(To be implemented)_            |
| `status`                  | Status update                | _(To be implemented)_            |

### Event structure

```php
readonly class CollectDataReceivedEvent extends AbstractCollectEvent
{
    public const array DOCUMENT_TYPES = [
        'merged_result',
        'raw_result',
        'document_refined_result'
    ];

    public function __construct(
        public string $collectTaskId,
        public string $providerTaskId,
        public array $data                   // array<string, mixed>
    ) {}

    public function getType(): ?string      // Returns $data['type']
    public function isDocument(): bool      // Checks if it's a document
}
```

---

## 🔌 Connection Methods

### Pull vs Push comparison

| Aspect           | Pull (Client)           | Push (Server)           |
| ---------------- | ----------------------- | ----------------------- |
| **Basil Role**   | WebSocket Client        | WebSocket Server        |
| **Bakus Role**   | WebSocket Server        | WebSocket Client        |
| **Initiation**   | Basil connects          | Bakus connects          |
| **Firewall**     | Basil must access Bakus | Bakus must access Basil |
| **SSL/TLS**      | Managed by Bakus        | Managed by Basil        |
| **Reconnection** | Handled by Basil        | Handled by Bakus        |
| **Use Case**     | Dev environment         | Production environment  |

### Configuration

```yaml
# .env
BAKUS_CALLBACK_TYPE=websocket_pull      # or 'websocket_push'
BAKUS_CALLBACK_URL=                      # URL for push mode
BAKUS_WEBSOCKET_URL=wss://bakus.io/ws   # URL for pull mode
BAKUS_WEBSOCKET_SERVER_PORT=5000        # Server port for push mode
```

### Pull architecture (Client)

```
┌────────────┐                    ┌────────────┐
│   Basil    │                    │   Bakus    │
│  (Client)  │◀──────────────────▶│  (Server)  │
└────────────┘   WebSocket        └────────────┘
     │                                    │
     │  1. POST /queries                  │
     │───────────────────────────────────▶│
     │                                    │
     │  2. Connect WebSocket              │
     │───────────────────────────────────▶│
     │                                    │
     │  3. Receive data stream            │
     │◀───────────────────────────────────│
     │                                    │
```

**Command**:

```bash
php bin/console collect:pull-stream <collect-task-id>
```

**Features**:

- Automatic reconnection with exponential backoff
- Circuit breaker after 5 attempts
- Timeout and error handling
- Real-time statistics

### Push architecture (Server)

```
┌────────────┐                    ┌────────────┐
│   Basil    │                    │   Bakus    │
│  (Server)  │◀──────────────────▶│  (Client)  │
└────────────┘   WebSocket        └────────────┘
     │                                    │
     │  1. Start WebSocket server         │
     │                                    │
     │  2. POST /queries with callback    │
     │───────────────────────────────────▶│
     │                                    │
     │  3. Bakus connects                 │
     │◀───────────────────────────────────│
     │                                    │
     │  4. Receive data stream            │
     │◀───────────────────────────────────│
     │                                    │
```

**Command**:

```bash
php bin/console collect:push-stream 0.0.0.0:5000 [--source-id=X] [--watch-file-id=Y]
```

**Features**:

- Multi-connection support (up to 100 by default)
- JWT authorization middleware
- Configurable SSL/TLS
- Signal handling (SIGTERM, SIGINT)
- Optional filtering by source/watch_file

---

## 🗺️ Source ↔ Collector Mapping

### Mapping configuration

The `config/services/collect_provider.yaml` file defines the correspondence between Basil source types and Bakus collectors:

```yaml
app.bakus.collector_mapping:
    # Bakus Collector -> Supported Basil source types

    # Web pages
    get_pages.site.sbx:
        - 'rss_feed'
        - 'website'
        - 'blog'

    # Documents
    search_documents.questel.sbx:
        - 'document:questel:search'

    # Geolocated events
    get_zone_events.geoconfirmed.sbx:
        - 'event:geoconfirmed:zone'

    # YouTube
    get_video_comments.youtube.sbx:
        - 'video:youtube:comments'
    get_videos_from_channel.youtube.sbx:
        - 'video:youtube:channel'
    get_videos_from_playlist.youtube.sbx:
        - 'video:youtube:playlist'
    search_videos.youtube.sbx:
        - 'video:youtube:search'

    # Odysee
    get_videos.odysee.sbx:
        - 'video:odysee'

    # Reddit
    get_posts_from_sr.reddit.sbx:
        - 'post:reddit:subreddit'
    get_posts_from_user.reddit.sbx:
        - 'post:reddit:user'

    # Domains
    company_name.nrd_search.psql.blackmorf:
        - 'domain:company_name'
    content_str.new_domain_names_nrd.psql.blackmorf:
        - 'domain:search'
    content_str.domain_name_details.psql.blackmorf:
        - 'domain:search'
    content_str.certstream_fqdns_and_domains_recent.psql.blackmorf:
        - 'domain:search'
```

### Mapping process

```php
// 1. Retrieve available collectors
$collectors = $collectorGateway->getCollectors();

// 2. Filter by source type
$supportedCollectors = array_filter(
    $collectors,
    fn($c) => $c->isSupportedSourceType(SourceType::RSS_FEED)
);

// 3. Build Bakus modules
foreach ($supportedCollectors as $collector) {
    $bakusModule = [
        'name' => $collector->name,          // 'rss.bakus'
        'version' => $collector->version,    // '1.0.0'
        'key' => $collector->type,           // 'rss_feed'
        'value' => $source->getUrl(),        // Source URL
        'parameters' => [...],               // Parameters (see below)
        'max_cache_age' => null
    ];
}
```

### Complete correspondence table

| Basil Source Type               | Bakus Collector                          | Category  |
| ------------------------------- | ---------------------------------------- | --------- |
| `rss_feed`                      | `get_pages.site.sbx`                     | Web       |
| `blog`                          | `get_pages.site.sbx`                     | Web       |
| `website`                       | `get_pages.site.sbx`                     | Web       |
| `newsletter`                    | _(To be mapped)_                         | Email     |
| `social_media:x:*`              | _(To be implemented)_                    | Social    |
| `social_media:facebook:*`       | _(To be implemented)_                    | Social    |
| `social_media:linkedin:*`       | _(To be implemented)_                    | Social    |
| `social_media:instagram:*`      | _(To be implemented)_                    | Social    |
| `social_media:reddit:subreddit` | `get_posts_from_sr.reddit.sbx`           | Social    |
| `social_media:reddit:user`      | `get_posts_from_user.reddit.sbx`         | Social    |
| `video:youtube:comments`        | `get_video_comments.youtube.sbx`         | Video     |
| `video:youtube:channel`         | `get_videos_from_channel.youtube.sbx`    | Video     |
| `video:youtube:playlist`        | `get_videos_from_playlist.youtube.sbx`   | Video     |
| `video:youtube:search`          | `search_videos.youtube.sbx`              | Video     |
| `video:odysee`                  | `get_videos.odysee.sbx`                  | Video     |
| `document:questel:search`       | `search_documents.questel.sbx`           | Documents |
| `event:geoconfirmed:events`     | `get_zone_events.geoconfirmed.sbx`       | Events    |
| `domain:company_name`           | `company_name.nrd_search.psql.blackmorf` | Domains   |
| `domain:search`                 | Multiple NRD collectors                  | Domains   |

---

## ⚙️ Collector Parameters

### Parameter hierarchy

When creating a Bakus request, parameters are resolved according to this priority order:

```
1. Source parameters                (highest priority)
    ↓
2. Configured default parameters
    ↓
3. Collector default value
    ↓
4. Error if required parameter is missing
```

### Default parameter configuration

```yaml
# config/services/collect_provider.yaml
app.bakus.collector_default_parameters:
    # RSS Bakus
    rss.bakus:
        recursive_request_content: true # Fetch full content

    # Deep web scraping
    get_pages.site.sbx:
        depth: 1 # Scraping depth
        attachments: separated # Attachment handling
```

### Resolution logic

```php
foreach ($collector->parameters as $parameter) {
    // 1. Priority: source parameter
    if ($source->hasParameter($parameter->name)) {
        $value = $source->getParameter($parameter->name);
    }
    // 2. Configured default parameter
    elseif (isset($defaultParams[$collector->name][$parameter->name])) {
        $value = $defaultParams[$collector->name][$parameter->name];
    }
    // 3. Collector default value
    elseif ($parameter->default !== null) {
        $value = $parameter->default;
    }
    // 4. Error if required
    elseif ($parameter->required) {
        throw new NotSupportedCollectorException(
            "Required parameter '{$parameter->name}' is missing"
        );
    }
    // 5. Otherwise, skip
    else {
        continue;
    }

    $bakusParameters[$parameter->name] = $value;
}
```

### Example parameters by collector

#### RSS Feed (`rss.bakus`)

| Parameter                   | Type    | Required | Default | Description                |
| --------------------------- | ------- | -------- | ------- | -------------------------- |
| `recursive_request_content` | boolean | No       | `true`  | Fetch full article content |

#### Web Scraping (`get_pages.site.sbx`)

| Parameter     | Type    | Required | Default     | Description                                        |
| ------------- | ------- | -------- | ----------- | -------------------------------------------------- |
| `depth`       | integer | No       | `1`         | Navigation depth (1-5)                             |
| `attachments` | string  | No       | `separated` | Attachment handling: `separated`, `inline`, `none` |

#### YouTube Channel (`get_videos_from_channel.youtube.sbx`)

| Parameter     | Type    | Required | Default | Description                                  |
| ------------- | ------- | -------- | ------- | -------------------------------------------- |
| `max_results` | integer | No       | `50`    | Maximum number of videos to fetch            |
| `order`       | string  | No       | `date`  | Sort order: `date`, `relevance`, `viewCount` |

---

## 🔐 Authentication & Security

### JWT for callback authentication

Each collection request generates a unique JWT Token to secure callbacks:

```php
// Token generation
$token = GenerateCollectTaskTokenHandler::handle($collectTaskId);

// Token structure
{
    "source_id": "01JBXX...",
    "watch_file_id": "01JBXY...",
    "collect_task_id": "01JBXZ...",
    "iat": 1706616000,              // Issued at
    "nbf": 1706616000,              // Not before
    "exp": 1706619600               // Expiration (1h by default)
}
```

### Token validation

```php
// Push mode: authorization middleware
class CollectTaskAuthorizationMiddleware
{
    public function __invoke(Connection $connection, Message $message)
    {
        $authHeader = $connection->getHandshakeRequest()
            ->getHeader('Authorization');

        if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            throw new UnauthorizedException('Missing token');
        }

        $collectTask = $this->validateToken($matches[1]);
        // Token validated, connection authorized
    }
}
```

### Security configuration

```yaml
# .env
COLLECT_TASK_JWT_SECRET=your-secret-key-here    # JWT signing key

# SSL/TLS for Push server
BAKUS_WEBSOCKET_SERVER_SSL_ENABLED=true
BAKUS_WEBSOCKET_SERVER_SSL_CERT=/certs/basil.local-cert.pem
BAKUS_WEBSOCKET_SERVER_SSL_KEY=/certs/basil.local-key.pem
BAKUS_WEBSOCKET_SERVER_SSL_ALLOW_SELF_SIGNED=true
```

---

## 🎯 Events & Handlers

### Chain of Responsibility architecture

See detailed documentation: [Collect Data Event Handling](./collect-data-events.md)

```
CollectDataReceivedEvent
         │
         ▼
CollectDataReceivedEventListener
         │
         ├─▶ MergedResultCollectDataHandler
         ├─▶ RawResultCollectDataHandler
         ├─▶ RefinedResultCollectDataHandler
         └─▶ ... (other handlers)
```

### Event types

#### `CollectDataReceivedEvent`

Data received from provider:

```php
new CollectDataReceivedEvent(
    collectTaskId: '01JBXZ...',
    providerTaskId: 'bakus-task-123',
    data: [
        'type' => 'merged_result',
        'result' => [...]
    ]
);
```

#### `CollectDataStreamConnectedEvent`

WebSocket connection established:

```php
new CollectDataStreamConnectedEvent(
    collectTaskId: '01JBXZ...',
    providerTaskId: 'bakus-task-123'
);
```

#### `CollectDataStreamDisconnectedEvent`

WebSocket disconnection:

```php
new CollectDataStreamDisconnectedEvent(
    collectTaskId: '01JBXZ...',
    providerTaskId: 'bakus-task-123',
    reason: 'Normal Closure',
    code: 1000
);
```

### Available handlers

#### `MergedResultCollectDataHandler`

Processes merged and enriched documents:

```php
public function supports(CollectDataReceivedEvent $event): bool
{
    return isset($event->data['type'])
        && 'merged_result' === $event->data['type'];
}

public function __invoke(CollectDataReceivedEvent $event): void
{
    // 1. Retrieve content from Bakus
    $hashSha1 = $event->data['result']['hash_document_sha1'];
    $content = $this->gateway->getDocumentContent($hashSha1);

    // 2. Build document
    $document = $this->buildDocument($content, $event);

    // 3. Dispatch ingest action
    $this->messageBus->dispatch(
        new IngestDocumentAction($event->collectTaskId, $document)
    );
}
```

---

## 📈 Statistics & Monitoring

### WebSocketStreamStatistics

Real-time statistics tracking class:

```php
$statistics = new WebSocketStreamStatistics();

// Counters
$statistics->incrementPing();
$statistics->incrementPong();
$statistics->incrementMessagesReceived();
$statistics->incrementMessagesProcessed($event);
$statistics->incrementMessagesFailed();
$statistics->incrementDocumentsReceived();

// Connections
$statistics->incrementActiveConnections();
$statistics->decrementActiveConnections();

// Pending messages
$statistics->incrementPendingMessages();
$statistics->decrementPendingMessages();

// Display
echo $statistics;  // "Statistics: Uptime=00:05:23 | Connections=2/10 | ..."
$array = $statistics->toArray();
```

### Structured logs

```php
$this->logger->info('WebSocket message received', [
    'collect_task_id' => $event->collectTaskId,
    'provider_task_id' => $event->providerTaskId,
    'type' => $event->getType(),
    'is_document' => $event->isDocument(),
    'data_size' => strlen(json_encode($event->data))
]);
```

---

## 🚀 CLI Commands

### Pull Stream

```bash
php bin/console collect:pull-stream <collect-task-id> [options]
```

**Options**:

- `--max-reconnect-attempts=N`: Number of reconnection attempts (default: 5)
- `--max-backoff-seconds=N`: Maximum delay between reconnections (default: 60)

**Example**:

```bash
php bin/console collect:pull-stream 01JBXZ1234567890ABCDEFGH \
  --max-reconnect-attempts=10 \
  --max-backoff-seconds=120
```

### Push Stream

```bash
php bin/console collect:push-stream <host:port> [options]
```

**Options**:

- `--source-id=ID`: Filter by specific source
- `--watch-file-id=ID`: Filter by watch folder
- `--stats-interval=N`: Statistics display interval (default: 60s)

**Example**:

```bash
php bin/console collect:push-stream 0.0.0.0:5000 \
  --source-id=01JBXY... \
  --stats-interval=30
```

---

## 🐛 Debugging & Troubleshooting

### Important logs

```bash
# Error logs
tail -f var/log/prod.log
```

Or the container logs if using Docker:

```bash
docker compose logs -f api runner collect_push # adapt service name as needed
```

### Checkpoints

**Verify Bakus configuration**:

```bash
php bin/console debug:container --parameter=app.bakus
```

### Common errors

| Error                           | Cause                         | Solution                                 |
| ------------------------------- | ----------------------------- | ---------------------------------------- |
| `No supported collectors found` | Unmapped source type          | Add mapping in `collect_provider.yaml`   |
| `Required parameter missing`    | Missing collector parameter   | Add parameter to source or config        |
| `Connection refused`            | Inaccessible WebSocket server | Check firewall and network configuration |
| `Invalid token`                 | Expired or invalid JWT        | Regenerate collection token              |
| `Circuit breaker active`        | Too many failed reconnections | Wait for circuit breaker reset           |

---

## 📚 Additional Resources

- [Collect Data Event Handling](./collect-data-events.md) - Detailed event system
- [API Bakus Documentation](https://bakus-tst-a.chapsvision.com/docs) - Official Bakus documentation
- [WebSocket RFC 6455](https://tools.ietf.org/html/rfc6455) - WebSocket specification
- [JWT RFC 7519](https://tools.ietf.org/html/rfc7519) - JSON Web Token specification

---

**Last updated**: 2025-01-30
**Version**: 1.0.0
