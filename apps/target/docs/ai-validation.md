# AI Validation Usage Guide

## Overview

The AI Validation system allows documents to be enriched with AI-generated validation metadata, enabling analysts to filter and search documents by their AI validation status.

## Architecture

- **Domain Layer**:
    - `AiValidationStatus` enum (PENDING|VALIDATED|REJECTED|UNCERTAIN|FAILED) - AI validation specific status
    - `AIValidation` value object (status, confidence score, validation reason, processed date, reference subject)
    - `ValidationReason` value object (bilingual text support)
    - `Document` entity (with AI validation methods)
- **Application Layer**:
    - `EnrichDocumentWithAiValidationAction` (command)
    - `EnrichDocumentWithAiValidationHandler` (handler)
- **Infrastructure Layer**:
    - `DocumentOpenSearchGateway` (OpenSearch persistence)
    - OpenSearch migration for AI validation fields
- **UserInterface Layer**: Symfony Messenger integration

## AI Validation Statuses

The `AiValidationStatus` enum provides 5 specific statuses for AI validation:

- **VALIDATED**: Document is validated by AI (high quality, relevant content)
- **REJECTED**: Document is rejected by AI (low quality, irrelevant, or inappropriate content)
- **UNCERTAIN**: AI is uncertain about the document (requires human review)
- **PENDING**: AI validation is pending (not yet processed)
- **FAILED**: AI validation failed due to technical issues

## Confidence Score Ranges

The confidence score is an integer between 0 and 100:

- **High Confidence**: 80-100 (strong AI certainty)
- **Medium Confidence**: 41-79 (moderate AI certainty)
- **Low Confidence**: 0-40 (low AI certainty)

## OpenSearch Mapping

The AI validation fields are stored in OpenSearch with the following structure:

```json
{
    "aiValidation": {
        "status": "validated",
        "confidenceScore": 95,
        "validationReason": {
            "en": "Document content is highly relevant and meets quality standards",
            "fr": "Le contenu du document est très pertinent et respecte les standards de qualité"
        },
        "processedAt": "2024-01-15T14:30:00+00:00",
        "referenceSubject": "Technology and AI"
    }
}
```

## Error Handling

The system provides comprehensive error handling for different scenarios:

### Exceptions

- `DocumentNotFoundException`: When document doesn't exist
- `DocumentSaveFailedException`: When OpenSearch update fails
- `InvalidAiValidationException`: When AI validation rules are violated (e.g., confidence score out of range)

### N8N Workflow Error Processing

The system now handles errors from the N8N workflow automatically:

#### Error Message Format

When N8N encounters an error during validation, it sends:

```json
{
    "documentId": "e2cc1724-65d5-3a71-8965-c48f0e79b59c",
    "validationError": "Authorization failed - please check your credentials"
}
```

#### Automatic Error Handling

The `EnrichDocumentWithAiValidationHandler` automatically:

1. **Detects Errors**: Checks for `validationError` presence using `$action->isError()`
2. **Sets Status**: Creates `AIValidation` with `FAILED` status and `0` confidence score
3. **Clears Data**: Sets `validationReason` and `referenceSubject` to `null`
4. **Logs Context**: Records error details for debugging

### Unified Action Structure

The system uses a single action (`EnrichDocumentWithAiValidationAction`) for both success and error cases:

```php
// Success case
$action = new EnrichDocumentWithAiValidationAction(
    documentId: 'doc-123',
    aiValidation: $aiValidation
);

// Error case
$action = new EnrichDocumentWithAiValidationAction(
    documentId: 'doc-123',
    validationError: 'Authorization failed - please check your credentials'
);
```

All exceptions are properly logged with context information for debugging.

## Testing

The system includes comprehensive tests following the project's testing conventions:

### Unit Tests

- **Location**: `tests/Units/Domain/Document/DocumentAiValidationTest.php`
- **Coverage**: Value objects, Document methods, Handler logic
- **Tools**: `NullDocumentGateway`, `NullLogger`
- **Error Testing**: Special characters, empty errors, non-existent documents

### Integration Tests

- **Location**: `tests/Integration/DocumentAiValidationIntegrationTest.php`
- **Coverage**: End-to-end workflows, complex data handling
- **Tools**: Real infrastructure components
- **Error Scenarios**: Validation error processing, existing validation replacement

### Test Coverage

The test suite comprehensively covers all scenarios:

#### Success Cases

- ✅ All AIValidation statuses (VALIDATED, UNCERTAIN, REJECTED, PENDING, FAILED)
- ✅ Reference subject handling
- ✅ Validation reason processing
- ✅ Document status method validation

#### Error Cases

- ✅ Validation error processing
- ✅ Error with existing AI validation
- ✅ Special characters in error messages
- ✅ Empty error strings
- ✅ Non-existent document errors

#### Edge Cases

- ✅ Null validation reasons
- ✅ Null reference subjects
- ✅ Document status transitions
- ✅ Confidence score validation rules

## N8N Workflow Configuration

### Workflow Structure

The N8N workflow (`docker/n8n/workflows/validate-document.json`) handles AI validation:

1. **Document Validation**: Uses AI models to validate document content
2. **Success Response**: Returns validation results with confidence scores
3. **Error Response**: Returns error information when validation fails

### Message Formats

#### Success Message

```json
{
    "documentId": "32653957-6121-32b6-94a6-83040ef720f5",
    "aiValidation": {
        "status": "validated",
        "confidenceScore": 85,
        "validationReason": {
            "fr": "Le document présente une forte pertinence stratégique...",
            "en": "The document demonstrates strong strategic relevance..."
        },
        "processedAt": "2025-10-05T12:55:29.143Z",
        "referenceSubject": "Default reference subject for testing"
    }
}
```

#### Error Message

```json
{
    "documentId": "e2cc1724-65d5-3a71-8965-c48f0e79b59c",
    "validationError": "Authorization failed - please check your credentials"
}
```

## Migration

To apply the OpenSearch mapping changes:

```bash
# Run the OpenSearch migration
docker compose exec api php bin/console opensearch:migrations:migrate

# Verify the mapping was applied
docker compose exec api php bin/console opensearch:index:show document_version20250929180835
```

## Data Fixtures

The system includes realistic data generation using Zenstruck\Foundry:

```php
// DocumentFactory generates realistic AI validation data
$document = DocumentFactory::new()
    ->with([
        'aiValidation' => DocumentFactory::new()->generateAiValidation(),
    ])
    ->create();
```

The `DocumentFactory` includes methods to generate realistic AI validation data with proper status distribution and bilingual validation reasons.
