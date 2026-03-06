# Symfony Services Configuration

## Service Definition

### Autowiring (Default)

Services are autowired by default in `config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
```

### Interface Binding

Bind interfaces to implementations:

```yaml
services:
    # Gateway bindings (Domain interface -> Infrastructure impl)
    App\Domain\WatchFile\WatchFileGatewayInterface:
        alias: App\Infrastructure\WatchFile\WatchFileDoctrineGateway

    App\Domain\User\UserGatewayInterface:
        alias: App\Infrastructure\User\UserDoctrineGateway

    App\Domain\Document\DocumentGatewayInterface:
        alias: App\Infrastructure\Document\DocumentDoctrineGateway
```

### Tagged Services

```yaml
services:
    # API Platform providers
    App\Infrastructure\WatchFile\WatchFileCollectionProvider:
        tags:
            - { name: 'api_platform.state_provider' }

    # API Platform processors
    App\Infrastructure\WatchFile\WatchFileProcessor:
        tags:
            - { name: 'api_platform.state_processor' }

    # Messenger handlers (usually auto-configured via #[AsMessageHandler])
    App\Application\WatchFile\CreateWatchFileHandler:
        tags:
            - { name: 'messenger.message_handler' }
```

## Service Patterns

### Readonly Service

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\WatchFileGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

readonly class WatchFileDoctrineGateway implements WatchFileGatewayInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}
}
```

### Service with Configuration

```yaml
services:
    App\Infrastructure\Elasticsearch\ElasticsearchClient:
        arguments:
            $hosts: ['%env(ELASTICSEARCH_URL)%']
            $indexPrefix: '%env(ELASTICSEARCH_INDEX_PREFIX)%'
```

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch;

readonly class ElasticsearchClient
{
    public function __construct(
        private array $hosts,
        private string $indexPrefix,
    ) {}
}
```

### Service Decoration

```yaml
services:
    App\Infrastructure\WatchFile\CachedWatchFileGateway:
        decorates: App\Domain\WatchFile\WatchFileGatewayInterface
        arguments:
            $inner: '@.inner'
            $cache: '@cache.app'
```

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile;

use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\Cache\CacheItemPoolInterface;

readonly class CachedWatchFileGateway implements WatchFileGatewayInterface
{
    public function __construct(
        private WatchFileGatewayInterface $inner,
        private CacheItemPoolInterface $cache,
    ) {}
}
```

## Messenger Configuration

### Transport Setup

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async_priority_high:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queues:
                        async_priority_high: ~
            async_priority_low:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queues:
                        async_priority_low: ~

        routing:
            App\Application\WatchFile\AnalyzeWatchFileAction: async_priority_high
            App\Application\Document\IndexDocumentAction: async_priority_low
```

### Handler Registration

```php
<?php

declare(strict_types=1);

namespace App\Application\WatchFile;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class CreateWatchFileHandler
{
    public function __invoke(CreateWatchFileAction $action): WatchFile
    {
        // Handler logic
    }
}
```

## Environment Variables

```yaml
# config/services.yaml
parameters:
    app.elasticsearch.index_prefix: '%env(ELASTICSEARCH_INDEX_PREFIX)%'
    app.mercure.hub_url: '%env(MERCURE_PUBLIC_URL)%'
```

Access in services:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

readonly class MercurePublisher
{
    public function __construct(
        #[Autowire('%app.mercure.hub_url%')]
        private string $hubUrl,
    ) {}
}
```

## Key Rules

1. **Autowiring**: Let Symfony autowire dependencies, don't register manually unless needed
2. **Interface Aliases**: Always bind domain interfaces to infrastructure implementations
3. **Readonly Services**: All services should be `readonly` classes
4. **Constructor Injection**: Never use setter injection or service locator
5. **Tagged Services**: Use attributes (`#[AsMessageHandler]`) instead of manual tagging when possible
6. **Environment Variables**: Use `%env(VAR)%` syntax, never `$_ENV` directly
