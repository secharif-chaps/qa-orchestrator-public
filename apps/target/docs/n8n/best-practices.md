# N8N Best Practices

Production-ready guidelines for building robust, maintainable, and performant N8N workflows in Basil.

## Naming and Organization

### Node Naming Convention

**✅ ALWAYS follow**: `Domain_Role_Action` format

```
✅ Good:
- LLM_Validator_Primary
- Condition_IsRelevant
- RabbitMQ_PublishEvent
- Agent_Generator_ReferenceSubject

❌ Bad:
- Validator node
- check if relevant
- publish_event
- ref subject gen
```

See [Naming Convention](./naming-convention.md) for complete guide.

### Workflow Naming

**Format**: `[Domain] - [Purpose]`

```
✅ Good:
- Document - Summary Generation
- Folder - Reference Subject Update
- Watchfile - Classification Workflow

❌ Bad:
- workflow1
- test_new_feature
- document workflow v2
```

### Node Documentation

Add notes to complex nodes:

```
Right-click node → Add Note

Example note:
"""
Validates reference subject relevance using:
1. Context from last 5 messages
2. Folder metadata (type, domain)
3. AI confidence threshold (>0.8)

Fallback to LLM_Validator_Fallback on timeout.
"""
```

## Error Handling

### Always Use Error Outputs

**❌ Without Error Handling:**

```
LLM_Primary → Next_Node
```

**Problem**: Workflow fails completely on LLM error

**✅ With Error Handling:**

```
LLM_Primary
  → [Success] → Next_Node
  → [Error] → LLM_Fallback → Next_Node
```

**Enable Error Output:**

1. Node Settings → "Continue on Fail"
2. Connect red error output to fallback

### Primary/Fallback Pattern

For critical operations, always provide fallback:

```
LLM_Validator_Primary (gemini-1.5-flash)
  → [Error] → LLM_Validator_Fallback (gemini-1.5-pro)
  → [Error] → Feedback_ValidatorError
```

**Fallback Strategy:**

- Primary: Fast, cheaper model
- Fallback: Slower, more capable model
- Final: Error notification with details

### Structured Error Feedback

Use Set nodes to create consistent error responses:

```javascript
// Feedback_Error node
{
  "status": "error",
  "stage": "validation",
  "node": "{{ $json.node }}",
  "error": "{{ $json.error.message }}",
  "timestamp": "{{ $now.toISO() }}",
  "retryable": true
}
```

### Global Error Workflow

Create a dedicated error handling workflow:

**`Error Handler - Global`**:

```
Trigger_ErrorWorkflow
  → Log_Error (to database/file)
  → Condition_IsCritical
    → [True] → Notify_Team (Slack/Email)
    → [False] → Store_For_Review
```

**Assign to workflows:**
Workflow Settings → Error Workflow → `Error Handler - Global`

## AI and LLM Integration

### Temperature Settings

Choose appropriate temperature based on task:

```
Deterministic tasks (validation, extraction):
Temperature: 0.0 - 0.2

Creative tasks (generation, summarization):
Temperature: 0.5 - 0.7

Highly creative (brainstorming, ideation):
Temperature: 0.8 - 1.0
```

### Prompt Engineering

**Use Twig templates** for complex prompts:

```twig
{# templates/prompts/validate_reference_subject.twig #}
You are validating reference subjects for folder analysis.

Context:
- Folder Type: {{ folderType }}
- Domain: {{ domain }}
- Current Subject: {{ currentSubject }}

Recent Messages:
{% for message in messages|slice(0, 5) %}
- {{ message.author }}: {{ message.content }}
{% endfor %}

Task:
Is "{{ proposedSubject }}" relevant given this context?

Respond in JSON:
{
  "isRelevant": true/false,
  "confidence": 0.0-1.0,
  "reasoning": "explanation"
}
```

**Load in N8N:**

```javascript
// HTTP Request to API
GET /api/prompts/validate_reference_subject
?folderType={{ $json.folderType }}
&domain={{ $json.domain }}
```

### JSON Schema Validation

Always validate LLM JSON responses:

```javascript
// Parser_Validator_Structured node
const schema = {
  type: 'object',
  properties: {
    isRelevant: { type: 'boolean' },
    confidence: { type: 'number', minimum: 0, maximum: 1 },
    reasoning: { type: 'string' },
  },
  required: ['isRelevant', 'confidence', 'reasoning'],
}

// Validate
const jsonschema = require('jsonschema')
const result = jsonschema.validate($json, schema)

if (!result.valid) {
  throw new Error(`Invalid JSON: ${result.errors}`)
}

return $json
```

### Response Parsing

Extract JSON from LLM responses that may contain extra text:

```javascript
// Parser_JSON_Extractor node
const text = $json.response

// Extract JSON between first { and last }
const start = text.indexOf('{')
const end = text.lastIndexOf('}') + 1

if (start === -1 || end === 0) {
  throw new Error('No JSON found in response')
}

const jsonStr = text.substring(start, end)
return JSON.parse(jsonStr)
```

## Performance Optimization

### Minimize Data Transfer

Filter early, transfer only needed fields:

```javascript
// ❌ Bad: Transfer everything
HTTP_API_GetFolders
  → Process_All_Fields (heavy payload)

// ✅ Good: Filter immediately
HTTP_API_GetFolders
  → Code_FilterFields (only id, name, state)
  → Process_Minimal_Data (light payload)
```

**Code_FilterFields:**

```javascript
return $input.all().map((item) => ({
  id: item.json.id,
  name: item.json.name,
  state: item.json.state,
}))
```

### Use Pagination

For large datasets, paginate API requests:

```javascript
// HTTP_API_GetFolders (paginated)
GET /api/folders?page={{ $json.page }}&limit=50

// Loop_NextPage condition
{{ $json.hasMore === true }}
```

### Batch Operations

Group multiple operations:

```
❌ Bad: Process one by one
For Each Document:
  → HTTP_API_UpdateDocument

✅ Good: Batch update
Code_PrepareUpdates
  → HTTP_API_BatchUpdateDocuments
```

### Cache Expensive Operations

Store AI results to avoid duplicate processing:

```
Trigger_RabbitMQ
  → Condition_CheckCache (Redis)
    → [Hit] → Return_Cached_Result
    → [Miss] → LLM_Generate
              → Store_In_Cache (TTL: 1 hour)
              → Return_Result
```

## Security

### Never Hardcode Credentials

**❌ Bad:**

```javascript
const apiKey = 'sk-abc123def456'
```

**✅ Good:**

```javascript
// Use credentials manager
// Configure in node settings → Credential → Select saved credential
```

### Sanitize User Input

Always validate and sanitize external input:

```javascript
// Webhook input validation
const allowedFolderIds = $json.folderId.match(/^\d+$/)

if (!allowedFolderIds) {
  throw new Error('Invalid folderId format')
}

// Prevent injection
const safeName = $json.folderName.replace(/[<>]/g, '').substring(0, 255)
```

### Use HTTPS Only

**✅ Good:**

```
https://api/api/folders
https://opensearch:9200
```

**❌ Bad:**

```
http://api/api/folders (unencrypted)
```

### Audit Workflow Access

Restrict workflow edit permissions:

1. Workflow Settings → Sharing
2. Set owner and editors
3. Enable "Only owner can execute"

## Testing

### Test with Realistic Data

Create test datasets that match production:

```json
// Test data for Document Summary workflow
{
  "documentId": 123,
  "content": "Lorem ipsum dolor sit amet... (500 words)",
  "metadata": {
    "type": "pdf",
    "language": "en",
    "pages": 10
  }
}
```

### Manual Execution First

Before activating:

1. **Save workflow**
2. **Execute manually** with test data
3. **Review all node outputs**
4. **Check error paths**
5. **Verify final output**
6. **Activate** only when confident

### Monitor Executions

Regularly review execution history:

1. Executions tab
2. Filter by status (error, waiting, success)
3. Investigate failures
4. Optimize slow executions

### Enable Execution Logging

**Workflow Settings:**

- ✅ Save Execution Progress
- ✅ Save Data on Error
- ✅ Save Data on Success (for debugging)

**⚠️ Warning**: Logging increases storage usage. Disable "Save Data on Success" in production if not needed.

### Automated Testing with Test Runner

Basil includes a dedicated test runner for N8N workflows that validates AI agent responses via webhooks.

**Location**: `docker/n8n/scripts/test-workflow.js`

**Features**:

- ✅ Webhook-based workflow execution
- ✅ JSON Schema validation (AJV)
- ✅ AI quality metrics (response time, structure compliance, quality scores)
- ✅ Sequential or parallel test execution
- ✅ Detailed JSON reports

**Quick Start**:

```bash
# Install dependencies (first time only)
task n8n:test:setup

# Run custom dataset
task n8n:test -- my-dataset.json
```

**Advanced Usage**:

```bash
# Run custom dataset
task n8n:test -- my-dataset.json --verbose

# Run with parallel execution
task n8n:test -- my-dataset.json --parallel

# Or with manual webhook URL
task n8n:test -- my-dataset.json --webhook-url http://127.0.0.1:5678/webhook/my-path
```

**Create Test Datasets**:

```json
{
  "version": "1.0",
  "metadata": {
    "workflowId": "7uS3PqE9HezIqOd1",
    "workflowName": "WatchFile Builder - Update reference subject",
    "description": "Dataset description"
  },
  "testCases": [
    {
      "testCaseId": "TC-001",
      "name": "First creation with empty WatchFile",
      "expectedOutput": "success",
      "input": {
        "watchFile": {
          "id": "uuid",
          "name": "Market Intelligence",
          "referenceSubject": null
        },
        "language": "en",
        "referenceSubject": "Monitor competitors..."
      }
    }
  ]
}
```

**Environment Configuration**:

Set `N8N_BASE_URL` in `.env` file:

```bash
N8N_BASE_URL=http://127.0.0.1:5678
```

**Metrics Calculated**:

- Response time (ms)
- Structure compliance (JSON schema validation)
- Language match (response matches requested language)
- Quality score (AI-generated 0-100)
- Confidence score (AI confidence 0-100)

**Test Execution Tracking**:

The test runner automatically tracks executions in N8N with two systems:

1. **Automatic N8N Tagging** - Executions are tagged via API:
   - `test:<runId>` - Links all tests from same run
   - `dataset:<dataset-name>` - Dataset identifier
   - `case:<testCaseId>` - Specific test case

2. **Metadata in Payload** - Sent to workflow:

   ```json
   {
     "_testMetadata": {
       "runId": "test-1731628345678-abc123",
       "testCaseId": "TC-001",
       "source": "n8n-test-runner"
     }
   }
   ```

3. **Execution IDs in Reports** - Each test result includes the N8N execution ID

**Filter in N8N UI:**

- By tag: `test:test-1731628345678-abc123` (all tests from a run)
- By tag: `dataset:reference-subject-workflow-evaluation`
- By tag: `case:TC-001` (specific test)

**See Full Documentation**: `docker/n8n/tests/README.md`

### Snapshot Testing

Jest-like snapshot testing eliminates manual response format declaration:

**First Execution - Create Snapshot:**

```bash
task n8n:test:reference-subject
# Creates snapshots automatically for tests without them
```

**Subsequent Executions - Validate Against Snapshot:**

```bash
task n8n:test:reference-subject
# Fails if response doesn't match snapshot
```

**Update Snapshots After Intentional Changes:**

```bash
task n8n:test -- reference-subject-workflow-evaluation.json --update-snapshots
# or
task n8n:test -- reference-subject-workflow-evaluation.json -u
```

**How Snapshots Work:**

1. Normalizes response (removes execution ID, timestamps, messageId, conversationId)
2. Saves normalized response to test case JSON
3. Future runs compare against saved snapshot
4. Displays detailed diff on mismatch

**Snapshot Diff Example:**

```
  ✗ FAILED (15234ms)
    📸 Snapshot: 3 differences
       referenceSubject.fr.context: "old" → "new"
       metadata.qualityScore: 85 → 92
       metadata.keyActors: added in response
```

**Best Practices:**

- ✅ Review snapshot diffs before updating
- ✅ Commit snapshots to version control
- ✅ Update snapshots only for intentional changes
- ❌ Don't ignore snapshot failures
- ❌ Don't update snapshots to "fix" broken tests

**See Full Documentation**: `docker/n8n/tests/README.md`

### RabbitMQ Message Validation

All RabbitMQ nodes must send messages that match the expected PHP class structure. The validator ensures message integrity between N8N workflows and PHP message handlers.

**Validator Script**: `api/bin/validate-n8n-rabbitmq-messages.php`

**Quick Validation:**

```bash
# Validate all workflows
task n8n:validate:rabbitmq

# Validate specific workflow
php api/bin/validate-n8n-rabbitmq-messages.php docker/n8n/workflows/my-workflow.json
```

**How It Works:**

1. Extracts `type` header from RabbitMQ nodes (contains PHP class name)
2. Uses PHP Reflection to parse class constructor parameters
3. Compares required fields with message JSON template
4. Reports missing or unexpected fields

**Error Types:**

**🔴 CRITICAL** (cause test failure):

- `CLASS_NOT_FOUND`: PHP class doesn't exist in codebase
- `MISSING_TYPE_HEADER`: No 'type' header defined in RabbitMQ node
- `MISSING_REQUIRED_FIELDS`: Required class properties missing from message

**🟡 WARNINGS** (informational only):

- `UNEXPECTED_FIELDS`: Extra fields in message (may be intentional metadata)
- `DYNAMIC_TYPE_HEADER`: Type header uses N8N expression (can't validate statically)

**Example Error:**

```
📄 extract-document-events.json
  Workflow: Extract Document Events
  Node: Send Response to Backend (ID: send-response)
  Error: [MISSING_REQUIRED_FIELDS] Missing required fields: documentId, watchFileId
         (class: App\Application\Document\UpdateDocumentEventsAction)
```

**Best Practices:**

✅ **Always define `type` header:**

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

✅ **Include all required fields:**

```json
{
  "message": "={\"conversationId\": $json.id, \"message\": $json.content, \"messageId\": $json.messageId}}"
}
```

❌ **Avoid dynamic type headers when possible:**

```json
{
  "key": "type",
  "value": "={{ $('Edit Fields').item.json.messageType }}" // Can't validate!
}
```

**Automatic CI Validation:**

The validator runs automatically in GitLab CI pipeline during the `CodingStandards` stage. Any critical validation errors will fail the pipeline and block merge requests.

**CI Job:** `n8n-rabbitmq-validation`

- Runs on: Merge requests and main branch
- Blocks merge: Yes (if critical errors found)
- Stage: CodingStandards (alongside PHPStan, ECS, ESLint)

**Local Testing:**

Before pushing, validate locally to catch errors early:

```bash
# Quick validation
task n8n:validate:rabbitmq

# Or run directly
php api/bin/validate-n8n-rabbitmq-messages.php
```

**PHPUnit Integration:**

The validator also has PHPUnit tests (run on host, not in Docker):

```bash
# Run PHP tests including RabbitMQ validation
php vendor/bin/phpunit api/tests/Integration/N8N/
```

**Exit Codes:**

- `0`: All validations passed ✅
- `1`: Critical validation errors found ❌ (blocks CI)
- `2`: Script error (missing files, parse errors)

## Data Management

### Clean Up Old Executions

N8N stores all executions by default:

**Automatic Cleanup:**

```
Trigger_Schedule (daily)
  → Code_CleanupExecutions
  → DB_Delete_Old_Executions (>30 days)
```

**Manual Cleanup:**

```bash
# Via CLI
docker compose exec n8n n8n execute --id=cleanup-workflow
```

### Limit Output Size

Prevent memory issues with large responses:

```javascript
// Code_LimitOutput node
const maxItems = 100
return $input.all().slice(0, maxItems)
```

### Stream Large Files

Don't load entire files in memory:

```
❌ Bad:
HTTP_Download_File (10GB)
  → Process_Entire_File (OOM error)

✅ Good:
HTTP_Download_File (streaming)
  → Process_Chunks (1MB at a time)
```

## Workflow Composition

### Modularize with Sub-workflows

Break complex workflows into modules:

**Main Workflow:**

```
Trigger_RabbitMQ
  → SubWorkflow_ValidateDocument
  → SubWorkflow_ExtractMetadata
  → SubWorkflow_GenerateSummary
  → Output_Final
```

**Benefits:**

- ✅ Reusability (use in multiple workflows)
- ✅ Testability (test each module independently)
- ✅ Maintainability (easier to understand)
- ✅ Performance (parallel sub-workflow execution)

### Execute Workflow Node Configuration

**⚠️ CRITICAL: Known n8n Bug with `waitForSubWorkflow: false`**

There is a known issue in n8n (GitHub #13830) where setting `waitForSubWorkflow: false` in Execute Workflow nodes can cause sub-workflows to not execute properly, especially when combined with error handling settings like `onError: "continueErrorOutput"`.

**Symptoms:**

- Sub-workflow appears to start but never executes
- Sub-workflow is immediately aborted
- Parent workflow reports success but sub-workflow does nothing

**Workaround:**
**ALWAYS set `waitForSubWorkflow: true`** for Execute Workflow nodes that call sub-workflows.

```json
{
  "type": "n8n-nodes-base.executeWorkflow",
  "parameters": {
    "options": {
      "waitForSubWorkflow": true // ✅ REQUIRED - false causes execution issues
    }
  },
  "onError": "continueErrorOutput"
}
```

**Why this happens:**

- When `waitForSubWorkflow: false`, n8n attempts to queue the sub-workflow asynchronously
- Combined with error handling settings, this triggers a bug where the sub-workflow execution is aborted
- Setting `waitForSubWorkflow: true` forces synchronous execution, which works correctly

**References:**

- [GitHub Issue #13830](https://github.com/n8n-io/n8n/issues/13830)
- [n8n Community Discussion](https://community.n8n.io/t/execute-workflow-wait-for-sub-workflow-false-sub-workflow-aborts/53771)

### Keep Workflows Focused

**One workflow = One responsibility**

```
✅ Good:
- Document - Summary Generation
- Document - Classification
- Document - OCR Processing

❌ Bad:
- Document - All Processing (100+ nodes)
```

### Use Merge Nodes

Combine parallel branch results:

```
Trigger_RabbitMQ
  ├→ HTTP_API_GetFolder
  └→ HTTP_API_GetDocuments
    → Merge_Results (merge mode: combine)
    → Process_Combined_Data
```

## Monitoring and Observability

### Add Metadata to Outputs

Include tracing information:

```javascript
// Output_Final node
{
  "result": "{{ $json.result }}",
  "metadata": {
    "workflowId": "{{ $workflow.id }}",
    "executionId": "{{ $execution.id }}",
    "timestamp": "{{ $now.toISO() }}",
    "duration": "{{ $execution.duration }}",
    "nodeCount": "{{ $execution.nodes.length }}"
  }
}
```

### Log Critical Operations

Publish events for monitoring:

```
LLM_Validator_Primary
  → [Success] → RabbitMQ_LogValidationSuccess
  → [Error] → RabbitMQ_LogValidationFailure
```

### Health Check Workflows

Create monitoring workflows:

**`Health Check - N8N Services`**:

```
Trigger_Schedule (every 5 min)
  → HTTP_CheckAPI (api/health)
  → HTTP_CheckOpenSearch (_cluster/health)
  → HTTP_CheckRabbitMQ (management/api/healthchecks)
  → Condition_AllHealthy
    → [False] → Notify_Team (Slack/Email)
```

## Version Control

### Export Workflows Regularly

```bash
# Export all workflows
docker compose exec n8n n8n export:workflow --all --output=/data/workflows/backup-$(date +%Y%m%d).json

# Export specific workflow
docker compose exec n8n n8n export:workflow --id=<workflow-id> --output=/data/workflows/my-workflow.json
```

### Commit to Git

Store workflows in version control:

```bash
# Export to project directory
docker compose exec n8n n8n export:workflow --all --output=/data/workflows/production.json

# Copy to git repo
cp docker/n8n/data/workflows/production.json docker/n8n/workflows/

# Commit
git add docker/n8n/workflows/production.json
git commit -m "feat(n8n): update workflow configurations"
```

### Document Changes

Update workflow notes with changelog:

```
Workflow Note:
"""
CHANGELOG:
- 2025-11-14: Added fallback for LLM_Validator
- 2025-11-10: Optimized reference subject validation
- 2025-11-05: Initial version
"""
```

## Common Patterns

### Idempotent Operations

Ensure operations can be safely retried:

```
Trigger_RabbitMQ
  → Condition_CheckIfProcessed (check cache/DB)
    → [Already Processed] → Output_Skip
    → [Not Processed] → Process_Document
                       → Mark_As_Processed
                       → Output_Result
```

### Circuit Breaker

Prevent cascading failures:

```javascript
// Code_CircuitBreaker node
const errorCount = await getErrorCount('llm_service')

if (errorCount > 10) {
  // Circuit open - fail fast
  throw new Error('Circuit breaker open for LLM service')
}

// Proceed with call
```

### Dead Letter Queue

Handle persistent failures:

```
Trigger_RabbitMQ (main queue)
  → [Error] → Retry_Logic (max 3 times)
            → [Still Failing] → RabbitMQ_DeadLetterQueue
```

Process dead letters manually:

```
Trigger_Schedule (daily)
  → RabbitMQ_ReadDeadLetterQueue
  → Manual_Review_Required
```

## Deployment Checklist

Before activating a new workflow:

- [ ] All nodes follow [naming convention](./naming-convention.md)
- [ ] Error outputs configured for critical nodes
- [ ] Primary/Fallback chains for AI operations
- [ ] Test execution completed successfully
- [ ] Realistic test data used
- [ ] Credentials properly configured (no hardcoded secrets)
- [ ] Execution logging enabled
- [ ] Workflow documented (notes, README)
- [ ] Exported to version control
- [ ] Team notified of new workflow

## Resources

- **[N8N Best Practices (Official)](https://docs.n8n.io/workflows/best-practices/)** - Official recommendations
- **[Error Handling Guide](https://docs.n8n.io/flow-logic/error-handling/)** - Advanced error handling
- **[Performance Tips](https://docs.n8n.io/hosting/scaling/)** - Scaling and optimization

## Next Steps

- **[Workflow Testing](./workflow-testing.md)** - Test your workflows
- **[Workflow Validation](./workflow-validation.md)** - Validate workflow configuration
- **[Setup Guide](./setup.md)** - Environment setup and configuration
