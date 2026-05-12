# Documentation - Document Summary System

## Overview

The document summary system automatically generates bilingual summaries (French and English) of documents using N8N as an external orchestrator and OpenSearch as the storage database.

## Architecture

The system follows **Clean Architecture** and **Domain-Driven Design (DDD)** principles:

### Architectural Layers

- **Domain**: Business entities (`Document`, `Summary`, `SummaryStatus`) and interfaces (`DocumentGatewayInterface`)
- **Application**: Use cases (`UpdateDocumentSummaryAction`, `UpdateDocumentSummaryHandler`) and agents (`DocumentSummaryTriggerAgent`)
- **Infrastructure**: Concrete implementations (`DocumentOpenSearchGateway`) and external integrations
- **UserInterface**: API entry points and DTOs

### Main Components

1. **Trigger Agent**: `DocumentSummaryTriggerAgent` - Agent that encapsulates data and triggers the workflow
2. **N8N Workflow**: External orchestrator that generates summaries
3. **Update Action**: `UpdateDocumentSummaryAction` - Action to process the N8N response
4. **Processing Handler**: `UpdateDocumentSummaryHandler` - Processes and saves summaries in OpenSearch
5. **Persistence Gateway**: `DocumentOpenSearchGateway` - Document persistence interface in OpenSearch

### Data Flow

```
DocumentSummaryTriggerAgent → N8N Workflow → UpdateDocumentSummaryAction → UpdateDocumentSummaryHandler → DocumentOpenSearchGateway → OpenSearch
```

## Workflow Triggering

The document summary workflow is triggered programmatically via the Symfony messenger system. The `DocumentSummaryTriggerAgent` agent encapsulates the necessary data and is sent to N8N via RabbitMQ.

### Agent Structure

```php
$agent = new DocumentSummaryTriggerAgent([
    'id' => $documentId,
    'content' => $documentContent,
]);
```

### Messenger Configuration

The system uses Symfony Messenger for asynchronous communication:

```yaml
# config/packages/messenger.yaml
routing:
  'App\Application\Agent\TriggerAgent': agent_commands
  'App\Application\Document\UpdateDocumentSummaryAction': agent_responses
```

## N8N Workflow

### Workflow Structure

The N8N workflow (`docker/n8n/workflows/generate-document-summary.json`):

1. **Data Reception**: Receives the document ID and content
2. **Summary Generation**: Uses AI models to generate FR/EN summaries
3. **Response Return**: Sends the response to the backend via webhook

### Data Format Sent by N8N

#### Success

```json
{
  "documentId": "doc-123",
  "summary": {
    "fr": "Résumé français généré",
    "en": "Generated English summary"
  }
}
```

#### Failure

```json
{
  "documentId": "doc-123",
  "summaryError": "Erreur lors de la génération du résumé"
}
```

## Status Management

### Automatic Determination Logic

The system automatically determines the status based on received data:

- **PENDING**: Default status when launching the workflow
- **COMPLETED**: When a valid summary is present
- **FAILED**: When an error is present

## Error Handling

The system uses dedicated exceptions for robust error handling:

- **`DocumentNotFoundException`**: Document not found in OpenSearch
- **`DocumentSaveFailedException`**: Save failure in OpenSearch
- **`InvalidSummaryStateException`**: Inconsistent state (summary + error simultaneously)
- **`UpdateDocumentSummaryException`**: General summary update error

## Testing and Validation

The system includes comprehensive integration tests (`DocumentSummaryIntegrationTest`) that validate:

- **End-to-end flow**: From agent dispatch to persistence
- **Error handling**: Validation of dedicated exceptions
- **Persistence**: Verification of OpenSearch save operations
- **Consistent states**: Validation of statuses and data

### Diagnostic Commands

```bash
# Check service status
docker compose ps

# Check logs
docker compose logs api
docker compose logs n8n
docker compose logs opensearch

# Test OpenSearch connection
docker compose exec api php bin/console debug:container opensearch
```
