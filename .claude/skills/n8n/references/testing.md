# N8N Workflow Testing

## Test Framework Overview

Basil includes a dedicated test runner for validating N8N workflows via webhooks.

```bash
# Install dependencies (first time)
task n8n:test:setup

# Run tests with dataset
task n8n:test -- <dataset-filename>
```

## Test Dataset Structure

```json
{
    "version": "1.0",
    "metadata": {
        "workflowId": "7uS3PqE9HezIqOd1",
        "workflowName": "WatchFile Builder - Update reference subject",
        "description": "Tests for reference subject generation"
    },
    "testCases": [
        {
            "testCaseId": "TC-001",
            "name": "First creation with empty WatchFile",
            "expectedOutput": "success",
            "input": {
                "watchFile": {
                    "id": "550e8400-e29b-41d4-a716-446655440000",
                    "name": "Market Intelligence",
                    "referenceSubject": null
                },
                "language": "en",
                "referenceSubject": "Monitor competitors in the tech sector"
            }
        },
        {
            "testCaseId": "TC-002",
            "name": "Update existing reference subject",
            "expectedOutput": "success",
            "input": {
                "watchFile": {
                    "id": "550e8400-e29b-41d4-a716-446655440001",
                    "name": "Competitor Analysis",
                    "referenceSubject": "Track competitor pricing"
                },
                "language": "fr",
                "referenceSubject": "Suivre les prix des concurrents et leurs promotions"
            }
        }
    ]
}
```

## Creating Test Datasets

### Location

```
docker/n8n/tests/datasets/
├── reference-subject-workflow-evaluation.json
├── actor-validation-tests.json
└── source-classification-tests.json
```

### Required Fields

| Field            | Type   | Description                            |
| ---------------- | ------ | -------------------------------------- |
| `testCaseId`     | string | Unique identifier (TC-001, TC-002...)  |
| `name`           | string | Human-readable test name               |
| `expectedOutput` | string | Expected outcome: "success" or "error" |
| `input`          | object | Input data for the workflow            |

### Optional Fields

| Field         | Type   | Description                          |
| ------------- | ------ | ------------------------------------ |
| `description` | string | Detailed test description            |
| `tags`        | array  | Test categorization tags             |
| `snapshot`    | object | Expected output for snapshot testing |

## Running Tests

### Basic Execution

```bash
# Run all tests in a dataset
task n8n:test -- reference-subject-workflow-evaluation.json

# Verbose output
task n8n:test -- reference-subject-workflow-evaluation.json --verbose

# Parallel execution
task n8n:test -- reference-subject-workflow-evaluation.json --parallel
```

### Custom Webhook URL

```bash
task n8n:test -- my-dataset.json --webhook-url http://127.0.0.1:5678/webhook/my-path
```

## Snapshot Testing

Similar to Jest snapshots - automatically validates response structure.

### First Run (Create Snapshot)

```bash
task n8n:test -- reference-subject-workflow-evaluation.json
# Creates snapshots for tests without them
```

### Subsequent Runs (Validate)

```bash
task n8n:test -- reference-subject-workflow-evaluation.json
# Fails if response doesn't match snapshot
```

### Update Snapshots

```bash
task n8n:test -- reference-subject-workflow-evaluation.json --update-snapshots
# or
task n8n:test -- reference-subject-workflow-evaluation.json -u
```

### Snapshot Normalization

The test runner normalizes responses before comparison:

- Removes execution IDs
- Removes timestamps
- Removes messageId, conversationId

### Snapshot Diff Example

```
  ✗ FAILED (15234ms)
    📸 Snapshot: 3 differences
       referenceSubject.fr.context: "old" → "new"
       metadata.qualityScore: 85 → 92
       metadata.keyActors: added in response
```

## RabbitMQ Message Validation

Ensures N8N messages match PHP class structure.

### Running Validation

```bash
# Validate all workflows
task n8n:validate:rabbitmq

# Validate specific workflow
php api/bin/validate-n8n-rabbitmq-messages.php docker/n8n/workflows/my-workflow.json
```

### Error Types

**Critical (Blocks CI):**

- `CLASS_NOT_FOUND` - PHP class doesn't exist
- `MISSING_TYPE_HEADER` - No 'type' header in RabbitMQ node
- `MISSING_REQUIRED_FIELDS` - Required class properties missing

**Warnings:**

- `UNEXPECTED_FIELDS` - Extra fields (may be intentional)
- `DYNAMIC_TYPE_HEADER` - Expression-based type header

### Correct RabbitMQ Node Configuration

```json
{
    "headers": {
        "header": [
            {
                "key": "type",
                "value": "App\\Application\\Chat\\ModelMessageAction"
            }
        ]
    },
    "message": "={\"conversationId\": $json.id, \"message\": $json.content, \"messageId\": $json.messageId}}"
}
```

## Test Execution Tracking

### Automatic N8N Tagging

Executions are tagged via API:

- `test:<runId>` - Links all tests from same run
- `dataset:<dataset-name>` - Dataset identifier
- `case:<testCaseId>` - Specific test case

### Metadata in Payload

```json
{
    "_testMetadata": {
        "runId": "test-1731628345678-abc123",
        "testCaseId": "TC-001",
        "source": "n8n-test-runner"
    }
}
```

### Filter in N8N UI

- By tag: `test:test-1731628345678-abc123`
- By tag: `dataset:reference-subject-workflow-evaluation`
- By tag: `case:TC-001`

## Metrics Calculated

| Metric               | Description                         |
| -------------------- | ----------------------------------- |
| Response time        | Execution duration in ms            |
| Structure compliance | JSON schema validation              |
| Language match       | Response matches requested language |
| Quality score        | AI-generated 0-100                  |
| Confidence score     | AI confidence 0-100                 |

## Best Practices

### Dataset Design

- Cover edge cases (empty inputs, invalid data)
- Test both success and error paths
- Use realistic data matching production
- Include bilingual tests (EN/FR)

### Snapshot Testing

- Review diffs before updating
- Commit snapshots to version control
- Update only for intentional changes
- Never update to "fix" broken tests

### CI Integration

The validator runs in GitLab CI during `CodingStandards` stage:

- Blocks merge on critical errors
- Runs alongside PHPStan, ECS, ESLint

```bash
# Local validation before push
task n8n:validate:rabbitmq
```
