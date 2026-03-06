# N8N RabbitMQ Integration

## Message Flow Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Symfony Backend                           │
├─────────────────────────────────────────────────────────────────┤
│  AddMessageHandler                                               │
│    │                                                             │
│    ├─ Save user message to database                             │
│    │                                                             │
│    └─ Dispatch ChatSessionMessageAgent ──────────────────────┐  │
│                                                               │  │
└───────────────────────────────────────────────────────────────│──┘
                                                                │
                                                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       RabbitMQ                                   │
│  Queue: agent_commands                                           │
└───────────────────────────────────────────┬─────────────────────┘
                                            │
                                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                         N8N Workflow                             │
├─────────────────────────────────────────────────────────────────┤
│  Trigger_RabbitMQ (agent_commands)                               │
│    │                                                             │
│    ├─ Process with AI (LLM_Agent_*)                             │
│    │                                                             │
│    └─ RabbitMQ_Publish_Response ─────────────────────────────┐  │
│                                                               │  │
└───────────────────────────────────────────────────────────────│──┘
                                                                │
                                                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       RabbitMQ                                   │
│  Queue: agent_responses                                          │
└───────────────────────────────────────────┬─────────────────────┘
                                            │
                                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Symfony Backend                           │
├─────────────────────────────────────────────────────────────────┤
│  ModelMessageHandler / SystemMessageHandler / ErrorMessageHandler│
│    │                                                             │
│    ├─ Save AI message to database                               │
│    │                                                             │
│    └─ Publish to Mercure ──────────────────────────▶ Frontend   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Queues Configuration

### Symfony Messenger Configuration

```yaml
# api/config/packages/messenger.yaml
framework:
    messenger:
        transports:
            agent_commands:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queues:
                        agent_commands: ~

            agent_responses:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queues:
                        agent_responses: ~

        routing:
            'App\Application\Agent\TriggerAgent': agent_commands
            'App\Application\Chat\ModelMessageAction': agent_responses
            'App\Application\Chat\SystemMessageAction': agent_responses
            'App\Application\Chat\ErrorModelMessageAction': agent_responses
```

## Message Types

### Commands (Backend → N8N)

#### ChatSessionMessageAgent

```php
<?php

namespace App\Application\Agent;

readonly class ChatSessionMessageAgent
{
    public function __construct(
        public array $payload,
        public string $watchFileId,
        public string $userId,
    ) {}
}
```

**Payload Structure:**

```php
[
    'watchFile' => [...],                    // Normalized WatchFile
    'userMessageContentText' => 'Hello',     // User message
    'userMessageContentId' => 'uuid',        // Message content ID
    'conversationId' => 'uuid',              // Conversation ID
    'conversationLanguage' => 'en',          // 'en' or 'fr'
    'conversationHistory' => 'User: ...',    // Last 20 messages
    'metadata' => [
        'collectors_list' => [...],
        'source_types' => [...],
    ],
]
```

### Responses (N8N → Backend)

#### ModelMessageAction

```php
<?php

namespace App\Application\Chat;

readonly class ModelMessageAction
{
    public function __construct(
        public string $conversationId,
        public string $message,
        public string $messageId,
        public ?array $context = null,
    ) {}
}
```

#### SystemMessageAction

```php
<?php

namespace App\Application\Chat;

readonly class SystemMessageAction
{
    public function __construct(
        public string $conversationId,
        public string $message,
        public string $messageId,
        public string $systemMessageType,
    ) {}
}
```

#### ErrorModelMessageAction

```php
<?php

namespace App\Application\Chat;

readonly class ErrorModelMessageAction
{
    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $error,
        public ?array $context = null,
    ) {}
}
```

## N8N Node Configuration

### Trigger Node

```json
{
    "name": "Trigger_RabbitMQ",
    "type": "n8n-nodes-base.amqpTrigger",
    "parameters": {
        "queue": "agent_commands",
        "options": {
            "acknowledge": "immediately"
        }
    },
    "credentials": {
        "rabbitmq": {
            "id": "1",
            "name": "RabbitMQ"
        }
    }
}
```

### Publish Node

```json
{
    "name": "RabbitMQ_Publish_ChatMessage",
    "type": "n8n-nodes-base.amqp",
    "parameters": {
        "queue": "agent_responses",
        "sendTo": "queue",
        "options": {
            "headers": {
                "header": [
                    {
                        "key": "type",
                        "value": "App\\Application\\Chat\\ModelMessageAction"
                    }
                ]
            }
        },
        "message": "={{ JSON.stringify({ conversationId: $json.conversationId, message: $json.generatedMessage, messageId: $json.messageId, context: { execution: { id: $execution.id } } }) }}"
    }
}
```

## Type Header Requirement

**CRITICAL**: Every RabbitMQ publish node MUST include a `type` header with the PHP class FQCN.

### Correct Configuration

```json
{
    "headers": {
        "header": [
            {
                "key": "type",
                "value": "App\\Application\\Chat\\ModelMessageAction"
            }
        ]
    }
}
```

### Why Type Header?

Symfony Messenger uses the `type` header to:

1. Deserialize the JSON to the correct PHP class
2. Route to the appropriate message handler
3. Validate the message structure

### Validation

```bash
# Validate all RabbitMQ messages match PHP classes
task n8n:validate:rabbitmq
```

## Message Structure Examples

### Success Response

```json
{
    "conversationId": "550e8400-e29b-41d4-a716-446655440000",
    "message": "Based on my analysis, here are the key findings...",
    "messageId": "550e8400-e29b-41d4-a716-446655440001",
    "context": {
        "execution": {
            "id": "abc123"
        },
        "model": "gemini-1.5-flash",
        "confidence": 0.92
    }
}
```

### System Message

```json
{
    "conversationId": "550e8400-e29b-41d4-a716-446655440000",
    "message": "Actor 'Apple Inc.' has been added to your WatchFile.",
    "messageId": "550e8400-e29b-41d4-a716-446655440001",
    "systemMessageType": "actor_added"
}
```

### Error Response

```json
{
    "conversationId": "550e8400-e29b-41d4-a716-446655440000",
    "messageId": "550e8400-e29b-41d4-a716-446655440001",
    "error": "Failed to process request: AI model timeout",
    "context": {
        "execution": {
            "id": "abc123"
        },
        "stage": "llm_processing",
        "retryable": true
    }
}
```

## Troubleshooting

### Message Not Received by N8N

1. Check RabbitMQ is running: `docker compose ps rabbitmq`
2. Verify queue exists: http://localhost:15672 (guest/guest)
3. Check N8N workflow is active
4. Verify trigger node queue name matches

### Message Not Received by Symfony

1. Check `type` header is present and correct
2. Verify PHP class exists with exact FQCN
3. Check Symfony messenger consumer is running
4. Run validation: `task n8n:validate:rabbitmq`

### Deserialization Errors

```bash
# Check handler exists
grep -r "ModelMessageAction" api/src/Application/

# Verify class structure
php -r "var_dump(new App\Application\Chat\ModelMessageAction('', '', ''));"
```
