---
name: n8n
description: N8N workflow automation for AI orchestration in the Basil project. Use when working with N8N workflow JSON files in docker/n8n/workflows/, integrating RabbitMQ message queues with agent_commands/agent_responses, implementing AI agents with LLM nodes, handling chat system workflows, or testing workflows with datasets. Activates when editing workflow JSON files, configuring RabbitMQ message handlers, or running `task n8n:*` commands. CRITICAL - Always follow the node naming convention (Domain_Role_Action) and test workflows before activation.
allowed-tools: Read, Write, Edit, Glob, Grep, Bash
metadata:
  author: chaps-e
  version: "1.0"
---

## When to use this skill

- When working with N8N workflow JSON files in `docker/n8n/workflows/`
- When configuring RabbitMQ integration (agent_commands, agent_responses queues)
- When implementing AI agent workflows with LLM nodes
- When setting up Primary/Fallback error handling patterns
- When naming N8N nodes (use Domain_Role_Action convention)
- When testing workflows with `task n8n:test -- <dataset>`
- When exporting workflows with `task n8n:export-workflows`
- When validating RabbitMQ messages with `task n8n:validate:rabbitmq`
- When integrating chat system functionality
- When working with sub-workflows (orchestrator-router, tool workflows)

# N8N Workflow Automation

**CRITICAL**: N8N is the AI orchestration engine for Basil. All AI interactions (chat, document analysis, classification) flow through N8N workflows.

## Basil N8N Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Symfony API    │────▶│    RabbitMQ     │────▶│      N8N        │
│  (Backend)      │     │  agent_commands │     │   Workflows     │
└─────────────────┘     └─────────────────┘     └────────┬────────┘
                                                         │
                        ┌─────────────────┐              │
                        │    RabbitMQ     │◀─────────────┘
                        │ agent_responses │
                        └────────┬────────┘
                                 │
                        ┌────────▼────────┐
                        │  Symfony API    │────▶ Mercure SSE ────▶ Frontend
                        │   (Handlers)    │
                        └─────────────────┘
```

## Directory Structure

```
docker/n8n/
├── workflows/                    # Exported workflow JSON files
│   ├── 1-orchestrator-router.json
│   ├── 2-chat-session-message.json
│   ├── 3-tool-watchfile-builder.json
│   ├── 4-tool-rename-watchfile.json
│   ├── 5-tool-classify-watchfile.json
│   └── 6-tool-deepsearch-agent.json
├── credentials/                  # Encrypted credentials
├── tests/
│   └── datasets/                 # Test datasets for workflows
└── Taskfile.yaml                 # N8N task definitions
```

## Key Workflows

| Workflow                   | Purpose                             | Trigger      |
| -------------------------- | ----------------------------------- | ------------ |
| `1-orchestrator-router`    | Main entry point, routes messages   | RabbitMQ     |
| `2-chat-session-message`   | Chat AI processing                  | RabbitMQ     |
| `3-tool-watchfile-builder` | WatchFile creation/update           | Sub-workflow |
| `6-tool-deepsearch-agent`  | DeepSearch with strategic questions | Sub-workflow |

## Node Naming Convention

**Pattern**: `[Domain]_[Role]_[Action]`

```
✅ Good:
- LLM_Model_ValidationPrimary
- Condition_Check_IsRelevant
- RabbitMQ_Publish_ChatMessage
- Feedback_Success

❌ Bad:
- Validator node
- check if relevant
- publish_event
```

### Common Prefixes

| Prefix                | Use Case              | Example                        |
| --------------------- | --------------------- | ------------------------------ |
| `Workflow_Trigger_`   | Subworkflow entry     | `Workflow_Trigger_AddActor`    |
| `Condition_Check_`    | State/existence check | `Condition_Check_Duplicate`    |
| `Condition_Validate_` | Validation logic      | `Condition_Validate_Relevance` |
| `LLM_Model_`          | LLM invocation        | `LLM_Model_Primary`            |
| `LLM_Agent_`          | AI agent with tools   | `LLM_Agent_Classify`           |
| `LLM_Parser_`         | Parse LLM output      | `LLM_Parser_JSON`              |
| `RabbitMQ_Publish_`   | Queue message         | `RabbitMQ_Publish_Response`    |
| `Feedback_`           | User-facing message   | `Feedback_Error`               |
| `Response_Return_`    | Final output          | `Response_Return_Final`        |

## RabbitMQ Integration

### Message Flow

```php
// Symfony dispatches to N8N
$this->messageBus->dispatch(new ChatSessionMessageAgent(
    payload: [...],
    watchFileId: $watchFile->getId(),
    userId: $user->getId(),
));
// → Queue: agent_commands

// N8N responds back
// → Queue: agent_responses

// Symfony handlers process response
#[AsMessageHandler]
class ModelMessageHandler {
    public function __invoke(ModelMessageAction $action): void {
        // Save message, notify via Mercure
    }
}
```

### Required Message Headers

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

## Error Handling Pattern

Always use Primary/Fallback chain:

```
LLM_Model_Primary (gemini-1.5-flash)
  → [Success] → Next_Node
  → [Error] → LLM_Model_Fallback (gemini-1.5-pro)
            → [Error] → Feedback_Error
```

## Commands

```bash
# Export workflows
task n8n:export-workflows

# Validate RabbitMQ messages match PHP classes
task n8n:validate:rabbitmq

# Run workflow tests
task n8n:test -- <dataset-filename>

# Setup test environment
task n8n:test:setup
```

## Documentation

- [workflows.md](references/workflows.md) - Workflow patterns and structure
- [testing.md](references/testing.md) - Testing workflows with datasets
- [rabbitmq.md](references/rabbitmq.md) - RabbitMQ message integration
- [examples.md](references/examples.md) - Complete code examples
