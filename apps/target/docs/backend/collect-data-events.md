# Collect Data Event Handling

This document explains how the `CollectDataReceivedEvent` system works in the Basil API. This event is triggered when receiving data from external sources (documents, pings, API responses, etc.) and uses a **chain of responsibility pattern** to process the incoming data.

## Overview

The `CollectDataReceivedEvent` is a **domain event** that gets dispatched when external data is received by the system. The `CollectDataReceivedEventListener` automatically processes these events using a **chain of responsibility pattern** to find and execute the appropriate handler(s) for the specific type of data received.

## How the Collect Data Event System Works

### 1. **Event Triggering**

The `CollectDataReceivedEvent` is dispatched when:

- External documents are received
- API responses come back from external services
- Ping responses are received
- Any other external data source provides information

### 2. **Event Listener Registration**

```php
#[AsEventListener(event: CollectDataReceivedEvent::class)]
```

- Automatically listens for `CollectDataReceivedEvent` events
- No manual registration needed - Symfony's autoconfiguration handles it

### 3. **Handler Discovery**

```php
#[AutowireIterator('collect_data_handler')]
private iterable $collectDataHandlers
```

- Uses Symfony's `AutowireIterator` to automatically discover all services tagged with `collect_data_handler`
- All handlers are injected as an iterable collection

### 4. **Handler Selection Process**

For each incoming event, the listener:

1. **Iterates** through all registered handlers
2. **Calls `supports()`** method to check if handler can process the specific data type
3. **Executes** the handler if it supports the event
4. **Logs** the process and any errors

### 5. **Error Handling**

- Each handler execution is wrapped in try-catch
- Errors are logged but don't stop other handlers from running
- Detailed error information is captured (message, code, file, line, trace)

## Handler Interface

All collect data handlers must implement the `CollectDataHandler` interface:

```php
interface CollectDataHandler
{
    /**
     * Determines if this handler supports the given collect data event.
     */
    public function supports(CollectDataReceivedEvent $event): bool;

    /**
     * Handles the collect data event.
     */
    public function __invoke(CollectDataReceivedEvent $event): void;
}
```

## How to Add a New Handler

### Step 1: Create the Handler Class

Create a new class that implements the `CollectDataHandler` interface to handle specific types of external data:

```php
<?php

namespace App\Infrastructure\Collect;

use App\Domain\Collect\CollectDataHandler;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;

class DocumentCollectDataHandler implements CollectDataHandler
{
    public function supports(CollectDataReceivedEvent $event): bool
    {
        // Define your condition for when this handler should run
        // Example: handle when external document data is received
        return 'document' === $event->getType();
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        // Your processing logic here
        // Access event data via: $event->data
        // Access metadata via: $event->collectTaskId, $event->providerTaskId, etc.

        // Example: Process document data from external source
        $documentData = $event->data['content'] ?? '';
        $documentType = $event->data['mime_type'] ?? 'unknown';

        // Process the document...
    }
}
```

### Step 2: Register the Handler as a Service

No action needed if using Symfony's autoconfiguration. Just ensure your handler class is in a namespace that Symfony scans for services and implements the `CollectDataHandler` interface.

### Step 3: That's It!

- The listener will automatically discover your new handler
- It will be called for any events that match your `supports()` condition
- No changes needed to the listener itself

## Example: Existing Handler

Here's how the `MergedResultCollectDataHandler` works as an example of handling external data:

```php
class MergedResultCollectDataHandler implements CollectDataHandler
{
    public function supports(CollectDataReceivedEvent $event): bool
    {
        // This handler processes "merged_result" type data from external sources
        return 'merged_result' === $event->getType();
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        // Process merged result data received from external source
        $resultData = $event->data['result'] ?? [];
        $hashDocumentSha1 = $resultData['hash_document_sha1'] ?? null;

        // Extract document content from external provider
        $content = $this->gateway->getDocumentContent($hashDocumentSha1, $documentOrigin);

        // Create and save the document
        $document = $this->buildDocument($content, $event);
        $this->messageBus->dispatch(new IngestDocumentAction($event->collectTaskId, $document));
    }
}
```

## Key Benefits

- **Flexible data processing** - different handlers can process different types of external data (documents, pings, API responses, etc.)
- **Multiple handlers** can process the same event if they all return `true` from `supports()`
- **Order matters** - handlers are called in the order they're registered
- **Failures are isolated** - if one handler fails, others still run
- **Completely decoupled** - handlers don't know about each other
- **Easy to test** - each handler can be unit tested independently
- **Easy to extend** - just create a new handler and register it for new external data types

## Best Practices

### Handler Design

- Keep handlers focused on a single responsibility (e.g., handle only documents, only pings, only API responses)
- Use descriptive names that indicate what external data type they handle
- Place handlers in the Infrastructure layer (they often interact with external services)
- Make the `supports()` method efficient - it's called for every event
- Consider the data structure you expect from external sources when writing the `supports()` method

### Error Handling

- Always wrap external service calls in try-catch
- Log errors with sufficient context for debugging
- Don't let handler failures break the entire event processing

### Testing

- Write unit tests for each handler's `supports()` and `__invoke()` methods
- Mock external dependencies
- Test error scenarios

### Performance

- Keep handlers lightweight
- Consider async processing for heavy operations
- Use appropriate logging levels (debug for normal flow, error for failures)

## Architecture Integration

This pattern fits well with the Clean Architecture approach:

- **Domain Layer**: Defines the `CollectDataHandler` interface and `CollectDataReceivedEvent`
- **Application Layer**: Contains the `CollectDataReceivedEventListener` that orchestrates handlers
- **Infrastructure Layer**: Contains concrete handler implementations that interact with external services

The pattern makes it very easy to add new external data processing logic without modifying existing code - just create a new handler and register it!

## Common Use Cases

This event system is perfect for handling various types of external data:

- **Document Processing**: When external documents are received and need to be processed
- **Webhook Data**: When external services send webhook notifications

Each use case can have its own dedicated handler, making the system highly modular and maintainable.
