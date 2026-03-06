# N8N Examples

## Workflow Nodes

### Sub-workflow Entry Point

```json
{
    "parameters": {
        "workflowInputs": {
            "values": [
                { "name": "document", "type": "object" },
                { "name": "BusNameStamp" },
                { "name": "RouterContextStamp" }
            ]
        }
    },
    "type": "n8n-nodes-base.executeWorkflowTrigger",
    "typeVersion": 1.1,
    "name": "Workflow_Trigger_ValidateDocument"
}
```

---

### Webhook Test Entry Point

```json
{
    "parameters": {
        "httpMethod": "POST",
        "path": "test-validate-document",
        "responseMode": "responseNode"
    },
    "type": "n8n-nodes-base.webhook",
    "typeVersion": 2,
    "name": "Webhook_Trigger_TestInput"
}
```

---

### LLM Agent Node

```json
{
    "parameters": {
        "promptType": "define",
        "text": "={{ $json.prompt }}",
        "hasOutputParser": true,
        "needsFallback": true,
        "options": {
            "systemMessage": "You are a document relevance analyzer.",
            "maxIterations": 1
        }
    },
    "type": "@n8n/n8n-nodes-langchain.agent",
    "typeVersion": 3,
    "name": "LLM_Agent_ValidateDocument",
    "retryOnFail": true,
    "maxTries": 3,
    "waitBetweenTries": 5000,
    "onError": "continueErrorOutput"
}
```

---

### LLM Model (Primary)

```json
{
    "parameters": {
        "model": {
            "__rl": true,
            "value": "gemini-1.5-flash",
            "mode": "list"
        },
        "options": {}
    },
    "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
    "typeVersion": 1.3,
    "name": "LLM_Model_Primary",
    "credentials": {
        "openAiApi": {
            "id": "xxx",
            "name": "Gemini Flash"
        }
    }
}
```

---

### LLM Model (Fallback)

```json
{
    "parameters": {
        "model": {
            "__rl": true,
            "value": "Mistral-Small",
            "mode": "list"
        },
        "options": {}
    },
    "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
    "typeVersion": 1.3,
    "name": "LLM_Model_Fallback",
    "credentials": {
        "openAiApi": {
            "id": "yyy",
            "name": "Mistral Small"
        }
    }
}
```

---

### Structured Output Parser

```json
{
    "parameters": {
        "schemaType": "manual",
        "inputSchema": "{\n  \"type\": \"object\",\n  \"required\": [\"documentId\", \"aiValidation\"],\n  \"properties\": {\n    \"documentId\": { \"type\": \"string\" },\n    \"aiValidation\": {\n      \"type\": \"object\",\n      \"required\": [\"status\", \"confidenceScore\"],\n      \"properties\": {\n        \"status\": { \"type\": \"string\", \"enum\": [\"validated\", \"rejected\", \"uncertain\"] },\n        \"confidenceScore\": { \"type\": \"integer\", \"minimum\": 0, \"maximum\": 100 }\n      }\n    }\n  }\n}"
    },
    "type": "@n8n/n8n-nodes-langchain.outputParserStructured",
    "typeVersion": 1.2,
    "name": "LLM_Parser_ValidationSchema"
}
```

---

## RabbitMQ Nodes

### Publish Response to Backend

```json
{
    "parameters": {
        "queue": "agent_responses",
        "sendInputData": false,
        "message": "={{ JSON.stringify($json.output) }}",
        "options": {
            "headers": {
                "header": [
                    {
                        "key": "type",
                        "value": "App\\Application\\Chat\\ModelMessageAction"
                    },
                    {
                        "key": "X-Message-Stamp-Symfony\\Component\\Messenger\\Stamp\\BusNameStamp",
                        "value": "={{ $('Trigger_RabbitMQ').item.json.BusNameStamp }}"
                    },
                    {
                        "key": "X-Message-Stamp-Symfony\\Component\\Messenger\\Stamp\\RouterContextStamp",
                        "value": "={{ $('Trigger_RabbitMQ').item.json.RouterContextStamp }}"
                    }
                ]
            }
        }
    },
    "type": "n8n-nodes-base.rabbitmq",
    "typeVersion": 1.1,
    "name": "RabbitMQ_Publish_ChatMessage"
}
```

---

### Publish Error Response

```json
{
    "parameters": {
        "queue": "agent_responses",
        "sendInputData": false,
        "message": "={\n  \"conversationId\": \"{{ $json.conversationId }}\",\n  \"error\": {{ JSON.stringify($json.error || 'Unknown error') }}\n}",
        "options": {
            "headers": {
                "header": [
                    {
                        "key": "type",
                        "value": "App\\Application\\Chat\\ErrorModelMessageAction"
                    }
                ]
            }
        }
    },
    "type": "n8n-nodes-base.rabbitmq",
    "typeVersion": 1.1,
    "name": "RabbitMQ_Publish_Error"
}
```

---

## N8N Expressions

### Basic JSON Access

```javascript
// Access input field
{
    {
        $json.watchFile.id
    }
}

// Access nested object
{
    {
        $json.document.referenceSubject
    }
}

// Access from specific node
{
    {
        $('LLM_Model_Primary').item.json.output
    }
}
```

---

### Conditional Values

```javascript
// Null coalescing
{
    {
        $json.language ?? 'en'
    }
}

// Ternary operator
{
    {
        $json.isValid ? 'success' : 'failure'
    }
}

// Optional chaining
{
    {
        $json.watchFile?.owner?.email
    }
}
```

---

### String Operations

```javascript
// Template string
'User {{ $json.user.firstName }} requested: {{ $json.message }}'

// JSON stringify
{
    {
        JSON.stringify($json.payload)
    }
}

// Current timestamp
{
    {
        new Date().toISOString()
    }
}
```

---

### Array Operations

```javascript
// Get array length
{
    {
        $json.items.length
    }
}

// Map to specific field
{
    {
        $json.actors.map((a) => a.name).join(', ')
    }
}

// Filter array
{
    {
        $json.documents.filter((d) => d.status === 'validated')
    }
}
```

---

### Execution Context

```javascript
// Current execution ID
{
    {
        $execution.id
    }
}

// Workflow ID
{
    {
        $workflow.id
    }
}

// Workflow name
{
    {
        $workflow.name
    }
}
```

---

## Condition Nodes

### Check Duplicate Actor

```json
{
    "parameters": {
        "conditions": {
            "options": { "caseSensitive": false },
            "combinator": "and",
            "conditions": [
                {
                    "leftValue": "={{ $json.actorExists }}",
                    "rightValue": true,
                    "operator": { "type": "boolean", "operation": "equals" }
                }
            ]
        }
    },
    "type": "n8n-nodes-base.if",
    "typeVersion": 2.2,
    "name": "Condition_Check_IsDuplicate"
}
```

---

### Validate Confidence Score

```json
{
    "parameters": {
        "conditions": {
            "combinator": "and",
            "conditions": [
                {
                    "leftValue": "={{ $json.confidenceScore }}",
                    "rightValue": 75,
                    "operator": { "type": "number", "operation": "gte" }
                }
            ]
        }
    },
    "type": "n8n-nodes-base.if",
    "typeVersion": 2.2,
    "name": "Condition_Validate_HighConfidence"
}
```

---

## HTTP Request Nodes

### API Call to Backend

```json
{
    "parameters": {
        "method": "GET",
        "url": "={{ $env.API_URL }}/api/watch_files/{{ $json.watchFileId }}",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpHeaderAuth",
        "options": {
            "timeout": 30000
        }
    },
    "type": "n8n-nodes-base.httpRequest",
    "typeVersion": 4.2,
    "name": "HTTP_API_GetWatchFile",
    "credentials": {
        "httpHeaderAuth": {
            "id": "zzz",
            "name": "API Auth"
        }
    }
}
```

---

## Test Dataset Example

### Test Case Structure

```json
{
    "version": "1.0",
    "metadata": {
        "workflowId": "abc123",
        "workflowName": "Validate Document",
        "description": "Evaluation dataset for document validation workflow",
        "snapshotNormalization": {
            "removeFields": ["*.context", "*.processedAt"]
        }
    },
    "testCases": [
        {
            "testCaseId": "TC-001",
            "name": "Valid document - High relevance",
            "description": "Document matching all criteria of the reference subject",
            "expectedBehavior": "Workflow returns validated status with high confidence",
            "expectedOutput": "success",
            "successCheck": {
                "enabled": true,
                "expectedPathValue": [
                    ["aiValidation.status", "validated"],
                    ["aiValidation.confidenceScore", 85]
                ]
            },
            "input": {
                "document": {
                    "id": "doc-001",
                    "content": "Market analysis report on competitive intelligence...",
                    "referenceSubject": "Competitive analysis of Market Intelligence software"
                },
                "BusNameStamp": "[\"messenger.bus.default\"]",
                "RouterContextStamp": "{}"
            }
        },
        {
            "testCaseId": "TC-002",
            "name": "Invalid document - Off topic",
            "description": "Document completely unrelated to reference subject",
            "expectedBehavior": "Workflow returns rejected status",
            "expectedOutput": "success",
            "successCheck": {
                "enabled": true,
                "expectedPathValue": [["aiValidation.status", "rejected"]]
            },
            "input": {
                "document": {
                    "id": "doc-002",
                    "content": "Recipe for chocolate cake...",
                    "referenceSubject": "Competitive analysis of Market Intelligence software"
                },
                "BusNameStamp": "[\"messenger.bus.default\"]",
                "RouterContextStamp": "{}"
            }
        }
    ]
}
```

---

## Complete LLM Chain Pattern

### Validate Document Flow

```
Workflow_Trigger_ValidateDocument
  │
  ├─ Webhook_Trigger_TestInput (for testing)
  │   └─ Set_Format_TestInput
  │
  ├─ LLM_Agent_ValidateDocument
  │   ├─ LLM_Model_Primary (gemini-1.5-flash)
  │   ├─ LLM_Model_Fallback (mistral-small)
  │   ├─ LLM_Parser_ValidationSchema
  │   └─ LLM_Parser_Autofixing
  │
  ├─ [Success] → RabbitMQ_Publish_ValidationResult
  │
  └─ [Error] → RabbitMQ_Publish_FailureStatus
```

### Node Connections JSON

```json
{
    "connections": {
        "Workflow_Trigger_ValidateDocument": {
            "main": [
                [
                    {
                        "node": "LLM_Agent_ValidateDocument",
                        "type": "main",
                        "index": 0
                    }
                ]
            ]
        },
        "LLM_Agent_ValidateDocument": {
            "main": [
                [
                    {
                        "node": "RabbitMQ_Publish_ValidationResult",
                        "type": "main",
                        "index": 0
                    }
                ],
                [
                    {
                        "node": "RabbitMQ_Publish_FailureStatus",
                        "type": "main",
                        "index": 0
                    }
                ]
            ]
        },
        "LLM_Model_Primary": {
            "ai_languageModel": [
                [
                    {
                        "node": "LLM_Agent_ValidateDocument",
                        "type": "ai_languageModel",
                        "index": 0
                    }
                ]
            ]
        },
        "LLM_Model_Fallback": {
            "ai_languageModel": [
                [
                    {
                        "node": "LLM_Parser_Autofixing",
                        "type": "ai_languageModel",
                        "index": 0
                    }
                ]
            ]
        },
        "LLM_Parser_ValidationSchema": {
            "ai_outputParser": [
                [
                    {
                        "node": "LLM_Agent_ValidateDocument",
                        "type": "ai_outputParser",
                        "index": 0
                    }
                ]
            ]
        },
        "LLM_Parser_Autofixing": {
            "ai_outputParser": [
                [
                    {
                        "node": "LLM_Parser_ValidationSchema",
                        "type": "ai_outputParser",
                        "index": 0
                    }
                ]
            ]
        }
    }
}
```

---

## Response Format Examples

### Success Response

```json
{
    "success": true,
    "message": "Document validation completed",
    "context": {
        "execution": {
            "id": "{{ $execution.id }}"
        },
        "metadata": {
            "model": "gemini-1.5-flash",
            "confidence": 92
        }
    }
}
```

---

### Error Response

```json
{
    "success": false,
    "error": "LLM processing failed",
    "context": {
        "execution": {
            "id": "{{ $execution.id }}"
        },
        "errorDetails": "{{ $json.error?.message || 'Unknown error' }}"
    }
}
```

---

## PHP Message Classes

### Command (Backend → N8N)

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

### Response (N8N → Backend)

```php
<?php

namespace App\Application\Chat;

readonly class ModelMessageAction
{
    public function __construct(
        public string $conversationId,
        public string $message,
        public ?string $messageId = null,
        public ?array $context = null,
    ) {}
}
```

### Handler

```php
<?php

namespace App\Application\Chat;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ModelMessageHandler
{
    public function __construct(
        private MessageGatewayInterface $messageGateway,
        private SSEMessageUpdateNotifierInterface $notifier,
    ) {}

    public function __invoke(ModelMessageAction $action): void
    {
        $message = new Message($action->conversationId)
            ->setTextContent($action->message)
            ->setRole(MessageRole::MODEL);

        $this->messageGateway->save($message);
        $this->notifier->updateMessageOnConversation($message);
    }
}
```
