# N8N Testing Quick Reference

One-page reference for setting up testable N8N workflows.

> **Full Documentation**: See [Workflow Testing Guide](./workflow-testing.md) for complete details.

## Response Format Template

```json
{
  "success": true,
  "message": "Descriptive success/error message",
  "context": {
    "execution": {
      "id": "{{ $execution.id }}"
    },
    "timestamp": "{{ $now }}"
  },
  "messageId": null,
  "conversationId": "...",
  "watchFileId": "..."
}
```

## Workflow Settings Checklist

- [x] **Timeout**: 300000ms (5 minutes)
- [x] **Save Execution Progress**: Enabled (`save`)
- [x] **Error Workflow**: Optional global handler

**Location**: Workflow Settings → Executions tab

## Required Nodes

### 1. Test Webhook Trigger

```
Node: Webhook_Trigger_TestInput
- HTTP Method: POST
- Path: webhook/{name}
- Response Mode: lastNode
- Response Data: allEntries
```

### 2. Execution Metadata Storage

```
Node: ExecutionData_Store_TestMetadata
- Type: Edit Fields (Set)
- Values:
  - runId: {{ $json._testMetadata.runId }}
  - testCaseId: {{ $json._testMetadata.testCaseId }}
  - testCaseName: {{ $json._testMetadata.testCaseName }}
  - datasetFile: {{ $json._testMetadata.datasetFile }}
```

### 3. Response Formatter

```
Node: Set_Format_Response
- Ensure includes:
  - success: boolean
  - message: string
  - context.execution.id: {{ $execution.id }}
```

## Workflow Flow Template

```
Webhook_Trigger_TestInput
  ↓
Set_Format_TestInput (optional - normalize to production format)
  ↓
ExecutionData_Store_TestMetadata
  ↓
[Main Workflow Logic]
  ↓
Set_Format_Response
  ↓
Response_Return_Success / Response_Return_Error
```

## Dataset Template

```json
{
  "version": "1.0",
  "metadata": {
    "workflowId": "WORKFLOW_ID",
    "workflowName": "Workflow Name",
    "description": "Dataset description",
    "snapshotNormalization": {
      "removeFields": ["*.context.execution", "*.context.timestamp"]
    }
  },
  "testCases": [
    {
      "testCaseId": "TC-001",
      "name": "Test name",
      "description": "What this tests",
      "expectedOutput": "Success",
      "input": {
        /* webhook payload */
      },
      "snapshot": {
        /* expected response */
      }
    }
  ]
}
```

## Common Commands

```bash
# Setup
task n8n:test:setup

# Run single test
task n8n:test -- dataset.json --case TC-001

# Update snapshots
task n8n:test -- dataset.json -u

# Verbose output
task n8n:test -- dataset.json --verbose
```

## Node Naming Convention

```
Domain_Role_Action

Examples:
✅ Webhook_Trigger_TestInput
✅ ExecutionData_Store_TestMetadata
✅ Set_Format_Response
✅ Response_Return_Success
✅ LLM_Validator_Primary
```

## Testing Workflow

1. Add webhook trigger (`POST`, `lastNode`, `allEntries`)
2. Add execution metadata node
3. Connect to main workflow
4. Ensure response includes `success`, `message`, `context.execution.id`
5. Configure timeout (5 min) and save execution progress
6. Create dataset in `docker/n8n/tests/datasets/`
7. Run: `task n8n:test -- your-dataset.json`
8. Debug failures: Click execution link in output

## Debugging

```bash
# View execution in N8N UI
https://n8n.basil.local/workflow/{workflowId}/executions/{executionId}

# Filter by test run
N8N UI → Executions → Filter → runId: test-xxx

# Check logs
docker compose logs -f n8n
```

## Common Issues

| Issue                            | Solution                                           |
| -------------------------------- | -------------------------------------------------- |
| `Snapshot: 13 differences`       | Add fields to `snapshotNormalization.removeFields` |
| `Expected success but got error` | Check `success: false` in response                 |
| `Missing execution ID`           | Add `context.execution.id: {{ $execution.id }}`    |
| `Timeout after 300000ms`         | Optimize workflow or increase timeout              |

## Response Error Detection

Test runner automatically detects errors when response has:

- `success: false`
- `error` or `errorMessage` field present
- `status === 'error'`
- Nested `referenceSubject.success === false`

## Snapshot Normalization Patterns

```javascript
// Remove field from all top-level objects
'*.context.execution'

// Remove nested field
'result.generatedAt'

// Remove top-level field
'_testMetadata'

// Remove deeply nested with wildcard
'*.metadata.processingTime'
```

## Test Metadata Payload

Automatically included in webhook requests:

```json
{
  "_testMetadata": {
    "runId": "test-1763243143106-r9t4m1y",
    "testCaseId": "TC-001",
    "testCaseName": "First creation",
    "timestamp": "2025-11-15T00:12:34.567Z",
    "source": "n8n-test-runner",
    "datasetFile": "reference-subject.json"
  },
  "watchFile": {
    /* actual test data */
  }
}
```

## Files & Locations

```
docker/n8n/
├── workflows/              # Exported workflow JSON
│   └── reference-subject.json
└── tests/
    ├── test-workflow.js    # Test runner
    ├── datasets/           # Test datasets
    │   └── reference-subject-workflow-evaluation.json
    └── reports/            # Test reports
        └── test-report.json

docs/n8n/
├── workflow-testing.md     # Complete guide
└── testing-quick-reference.md  # This file
```

## Next Steps

- **Full Guide**: [Workflow Testing](./workflow-testing.md)
- **Best Practices**: [Development Guide](./best-practices.md)
- **Naming Convention**: [Node Naming](./naming-convention.md)
- **Examples**: `docker/n8n/tests/datasets/`
