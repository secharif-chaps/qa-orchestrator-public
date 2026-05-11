# N8N Workflow Validation

This validation framework ensures N8N workflows follow best practices and maintain consistency across the project.

## Purpose

The workflow validator performs seven types of checks:

1. **Naming Convention**: Ensures all nodes follow the `Domain_Role_Action` naming pattern
2. **Agent Configuration**: Validates AI agent nodes have proper error handling and retry logic
3. **Feedback Nodes**: Ensures response nodes return consistent JSON structures
4. **RabbitMQ Messages**: Validates RabbitMQ messages match PHP class structures
5. **Call Workflow Tool Configuration**: Validates Call Workflow Tool nodes have proper configuration
6. **Execute Sub-workflow Configuration**: Validates Execute Sub-workflow nodes have proper configuration
7. **Node Version Requirements**: Ensures Agent and LLM nodes meet minimum version requirements

## Quick Start

```bash
# Validate all workflows (console output)
task n8n:validate

# Generate JUnit report for CI
task n8n:validate -- --format=junit --output=api/var/reports/n8n-validation.xml

# Validate custom directory
task n8n:validate -- --workflows-dir=/custom/path
```

## Validators

### 1. Naming Convention Validator

Enforces the **Domain_Role_Action** naming pattern for all nodes (except exempt types).

#### Pattern

```text
Domain_Role_Action
```

- **Domain**: PascalCase (e.g., `Folder`, `Actor`, `RabbitMQ`, `Document`)
- **Role**: PascalCase (e.g., `Agent`, `LLM`, `Send`, `Request`, `Validator`)
- **Action**: PascalCase (e.g., `UpdateReferenceSubject`, `Detect`, `FolderUpdated`, `Format`)

#### Examples

✅ **Valid names:**

- `Folder_Agent_UpdateReferenceSubject`
- `Actor_LLM_DetectEntities`
- `RabbitMQ_Send_FolderUpdated`
- `Document_Validator_CheckFormat`

❌ **Invalid names:**

- `AI Agent Reference Subject` (spaces, not underscore-separated)
- `generate-summary` (kebab-case, lowercase)
- `UpdateFolder` (missing Domain and Role)
- `folder_update` (lowercase)

#### Exempt Node Types

The following node types are exempt from naming validation:

- `n8n-nodes-base.start` - Workflow triggers
- `n8n-nodes-base.set` - Data transformation nodes
- `n8n-nodes-base.if` - Conditional logic
- `n8n-nodes-base.switch` - Multi-way branching
- `n8n-nodes-base.merge` - Data merging
- `n8n-nodes-base.stopAndError` - Error handling
- `n8n-nodes-base.noOp` - No-operation nodes
- `n8n-nodes-base.stickyNote` - Workflow annotations and comments

### 2. Agent Configuration Validator

Validates AI agent nodes (`@n8n/n8n-nodes-langchain.agent`) have robust error handling and retry logic.

#### Required Configuration

All agent nodes must have:

1. **Fallback Model** (`needsFallback=true`)
   - Ensures a backup LLM is used if primary fails
   - Prevents workflow failures due to single model issues

2. **Retry on Fail** (`retryOnFail=true`)
   - Enables automatic retry for transient failures
   - Improves workflow reliability

3. **Max Iterations** (`maxIterations < 30`)
   - Must be defined and less than 30
   - Prevents infinite loops in agent reasoning
   - Recommended: 5-15 iterations for most use cases

4. **Error Output** (`onError="continueErrorOutput"`)
   - Must be set to continue on error with error output
   - Allows graceful error handling downstream

5. **Error Output Connected**
   - Error output (index 1) must be connected to another node
   - Enables proper error handling and logging

#### Example Configuration

```json
{
  "name": "Folder_Agent_GenerateSummary",
  "type": "@n8n/n8n-nodes-langchain.agent",
  "parameters": {
    "needsFallback": true,
    "options": {
      "maxIterations": 10
    }
  },
  "retryOnFail": true,
  "onError": "continueErrorOutput"
}
```

#### Common Errors

| Error Code                         | Description                  | Fix                                        |
| ---------------------------------- | ---------------------------- | ------------------------------------------ |
| `AGENT_MISSING_FALLBACK`           | No fallback model configured | Set `needsFallback=true` in parameters     |
| `AGENT_RETRY_DISABLED`             | Retry is not enabled         | Set `retryOnFail=true` on node             |
| `AGENT_MISSING_MAX_ITERATIONS`     | No max iterations defined    | Add `maxIterations` in `options`           |
| `AGENT_MAX_ITERATIONS_TOO_HIGH`    | Max iterations >= 30         | Reduce to < 30 (recommended: 5-15)         |
| `AGENT_ERROR_OUTPUT_DISABLED`      | Error output not enabled     | Set `onError="continueErrorOutput"`        |
| `AGENT_ERROR_OUTPUT_NOT_CONNECTED` | Error output not connected   | Connect error output to error handler node |

### 3. Feedback Node Validator

Ensures response/feedback nodes return consistent, well-structured JSON with debugging information.

#### Identification

Feedback nodes are identified by naming convention:

- Node name **must start with** `Feedback_`
- Examples: `Feedback_Success`, `Feedback_Error`, `Feedback_Complete`

#### Required JSON Structure

All feedback nodes must return JSON with these fields:

```json
{
  "success": true, // boolean - operation result
  "message": "Operation completed", // string - translated message
  "context": {
    "execution": {
      // execution context for debugging
      "id": "$execution.id"
    }
  }
}
```

#### Field Requirements

1. **`success`** (boolean, CRITICAL)
   - Indicates operation success or failure
   - Must be present in all responses

2. **`message`** (string, CRITICAL)
   - Human-readable message
   - Should be translated based on user language
   - Pattern check: looks for `language === 'en'` or similar

3. **`context.execution`** (object, CRITICAL)
   - Contains N8N execution ID for debugging
   - Pattern: `$execution` variable
   - Helps trace issues in production

4. **Translation** (WARNING)
   - Messages should be translated based on language
   - Checks for language conditionals in code

#### Example Configurations

**✅ Complete feedback node:**

```json
{
  "name": "Feedback_Success",
  "type": "n8n-nodes-base.set",
  "parameters": {
    "mode": "raw",
    "jsonOutput": "={{ {\n  success: true,\n  message: $('language') === 'en' ? 'Folder created successfully' : 'Dossier créé avec succès',\n  context: {\n    execution: $execution\n  }\n} }}"
  }
}
```

**❌ Incomplete feedback node:**

```json
{
  "name": "Feedback_Error",
  "type": "n8n-nodes-base.set",
  "parameters": {
    "mode": "raw",
    "jsonOutput": "={{ { data: $json } }}" // Missing success, message, context
  }
}
```

#### Common Errors

| Error Code                           | Description            | Severity | Fix                                      |
| ------------------------------------ | ---------------------- | -------- | ---------------------------------------- |
| `FEEDBACK_WRONG_MODE`                | Mode is not "raw"      | WARNING  | Set `mode: "raw"`                        |
| `FEEDBACK_MISSING_JSON_OUTPUT`       | No JSON output defined | CRITICAL | Add `jsonOutput` with JSON expression    |
| `FEEDBACK_MISSING_SUCCESS_FIELD`     | No `success` field     | CRITICAL | Add `success: boolean` to JSON           |
| `FEEDBACK_MISSING_MESSAGE_FIELD`     | No `message` field     | CRITICAL | Add `message: string` to JSON            |
| `FEEDBACK_MISSING_EXECUTION_CONTEXT` | No `context.execution` | CRITICAL | Add `context: { execution: $execution }` |
| `FEEDBACK_NO_TRANSLATION`            | Message not translated | WARNING  | Add language conditional for message     |

### 4. RabbitMQ Message Validator

Ensures RabbitMQ nodes send messages that match PHP class structures, preventing runtime errors by validating message format statically.

#### Validation Rules

All RabbitMQ nodes must:

1. **Use correct queue name**: Publishers use `agent_responses`, triggers use `agent_commands`
2. **Have a `type` header**: Specifies the PHP message class (publishers only)
3. **Reference existing PHP class**: Class must exist in the codebase (publishers only)
4. **Include required fields**: All non-nullable constructor parameters without defaults (publishers only)
5. **Match class structure**: Message structure matches PHP class constructor signature (publishers only)

#### Queue Name Requirements

**RabbitMQ Publishers** (type: `n8n-nodes-base.rabbitmq`):

- Must use queue: `agent_responses`
- Used for sending responses back to the PHP backend

**RabbitMQ Triggers** (type: `n8n-nodes-base.rabbitmqTrigger`):

- Must use queue: `agent_commands`
- Used for receiving commands from the PHP backend

**Example (RabbitMQ Publisher):**

```json
{
  "type": "n8n-nodes-base.rabbitmq",
  "parameters": {
    "queue": "agent_responses",
    "message": "={{ ... }}"
  }
}
```

**Example (RabbitMQ Trigger):**

```json
{
  "type": "n8n-nodes-base.rabbitmqTrigger",
  "parameters": {
    "queue": "agent_commands"
  }
}
```

#### Type Header Configuration

Every RabbitMQ node **must** include a `type` header:

```json
{
  "parameters": {
    "options": {
      "headers": {
        "header": [
          {
            "key": "type",
            "value": "App\\\\Message\\\\Folder\\\\FolderUpdatedMessage"
          }
        ]
      }
    }
  }
}
```

!!! tip "Double Backslashes"
Use double backslashes (`\\`) in the JSON value to properly escape the namespace separator.

#### Message Body Structure

The message body must include all required PHP class constructor parameters:

**PHP Class Example:**

```php
final readonly class FolderUpdatedMessage
{
    public function __construct(
        public string $folderId,        // Required (no default, not nullable)
        public string $userId,          // Required
        public ?string $comment = null, // Optional (nullable with default)
    ) {
    }
}
```

**N8N Message Body:**

```json
{
  "folderId": "={{ $json.folderId }}",
  "userId": "={{ $json.userId }}",
  "comment": "={{ $json.comment }}"
}
```

#### Field Requirements

- **Required fields**: Non-nullable parameters without defaults
- **Optional fields**: Nullable parameters or parameters with default values
- **Extra fields**: Allowed but generate warnings (useful for metadata)

#### Chat Message Translation

For specific chat action classes, the `message` field **must be translated** based on user language:

**Required for:**

- `App\Application\Chat\SystemMessageAction`
- `App\Application\Chat\ModelMessageAction`

**Example (correct):**

```json
{
  "message": "={{ $('language') === 'en' ? 'Processing your request' : 'Traitement de votre demande' }}"
}
```

**Example (incorrect):**

```json
{
  "message": "Processing your request" // Not translated!
}
```

#### Common Errors

| Error Code                             | Description                         | Severity | Fix                                                                 |
| -------------------------------------- | ----------------------------------- | -------- | ------------------------------------------------------------------- |
| `RABBITMQ_MISSING_QUEUE`               | No queue parameter                  | CRITICAL | Add `queue` parameter                                               |
| `RABBITMQ_WRONG_QUEUE`                 | Wrong queue name                    | CRITICAL | Use `agent_responses` for publishers, `agent_commands` for triggers |
| `RABBITMQ_DYNAMIC_QUEUE`               | Queue uses N8N expression           | WARNING  | Cannot validate statically - ensure correct queue at runtime        |
| `RABBITMQ_MISSING_TYPE_HEADER`         | No `type` header                    | CRITICAL | Add `type` header with PHP class name                               |
| `RABBITMQ_CLASS_NOT_FOUND`             | PHP class doesn't exist             | CRITICAL | Fix class name or create the class                                  |
| `RABBITMQ_MISSING_MESSAGE`             | No message body                     | CRITICAL | Add message JSON structure                                          |
| `RABBITMQ_MISSING_REQUIRED_FIELDS`     | Required parameters missing         | CRITICAL | Add missing fields to message body                                  |
| `RABBITMQ_CHAT_MISSING_MESSAGE`        | Chat action missing `message` field | CRITICAL | Add `message` field to JSON                                         |
| `RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED` | Chat message not translated         | CRITICAL | Add language-based translation (en/fr)                              |
| `RABBITMQ_DYNAMIC_TYPE_HEADER`         | Type uses N8N expression            | WARNING  | Cannot validate statically - check at runtime                       |
| `RABBITMQ_UNEXPECTED_FIELDS`           | Fields not in PHP class             | WARNING  | May be intentional for metadata/future use                          |

#### Supported PHP Features

The validator uses PHP Reflection to analyze message classes:

- ✅ Constructor property promotion (PHP 8.0+)
- ✅ Readonly properties
- ✅ Nullable types (`?string`)
- ✅ Union types (`string|int`)
- ✅ Default parameter values
- ✅ Class inheritance

### 5. Node Version Validator

Ensures N8N nodes meet minimum version requirements for compatibility and feature availability.

#### Validation Rules

All Agent, LLM, and Call Workflow Tool nodes must meet minimum version requirements:

1. **Agent Nodes** (`@n8n/n8n-nodes-langchain.agent`)
   - Must be version >= 3.0
   - Version 3 includes critical bug fixes and improved error handling

2. **LLM Nodes** (`@n8n/n8n-nodes-langchain.lmChatOpenAi`)
   - Must be version >= 1.3
   - Must have `responseApiEnabled=false` to ensure proper response formatting

3. **Call Workflow Tool Nodes** (`@n8n/n8n-nodes-langchain.toolWorkflow`)
   - Must be version >= 2.2
   - Version 2.2 includes improved workflow input handling and better error messages

4. **Connected LLM Validation**
   - LLM nodes connected to Agents are automatically validated
   - Connection via `ai_languageModel` connection type

#### Required Configuration

**Agent Node Example:**

```json
{
  "name": "Folder_Agent_GenerateSummary",
  "type": "@n8n/n8n-nodes-langchain.agent",
  "typeVersion": 3,
  "parameters": {
    "needsFallback": true
  }
}
```

**LLM Node Example:**

```json
{
  "name": "LLM_Model_Primary",
  "type": "@n8n/n8n-nodes-langchain.lmChatOpenAi",
  "typeVersion": 1.3,
  "parameters": {
    "options": {
      "responseApiEnabled": false
    }
  }
}
```

**Call Workflow Tool Node Example:**

```json
{
  "name": "Tool_WatchFile_Classify",
  "type": "@n8n/n8n-nodes-langchain.toolWorkflow",
  "typeVersion": 2.2,
  "parameters": {
    "description": "Classify a watchfile into monitoring categories",
    "workflowId": "5-tool-classify-watchfile"
  }
}
```

#### Common Errors

| Error Code                           | Description                                            | Fix                                        |
| ------------------------------------ | ------------------------------------------------------ | ------------------------------------------ |
| `AGENT_MISSING_VERSION`              | Agent node has no typeVersion defined                  | Add `typeVersion` field to node            |
| `AGENT_VERSION_TOO_LOW`              | Agent node version is < 3.0                            | Update node to version 3.0 or higher       |
| `LLM_MISSING_VERSION`                | LLM node has no typeVersion defined                    | Add `typeVersion` field to node            |
| `LLM_VERSION_TOO_LOW`                | LLM node version is < 1.3                              | Update node to version 1.3 or higher       |
| `LLM_RESPONSE_API_ENABLED`           | LLM node has responseApiEnabled=true (should be false) | Set `responseApiEnabled: false` in options |
| `CALL_WORKFLOW_TOOL_MISSING_VERSION` | Call Workflow Tool node has no typeVersion defined     | Add `typeVersion` field to node            |
| `CALL_WORKFLOW_TOOL_VERSION_TOO_LOW` | Call Workflow Tool node version is < 2.2               | Update node to version 2.2 or higher       |

#### Upgrading Nodes

To upgrade nodes in N8N:

1. **Open the workflow** in N8N UI
2. **Select the node** to upgrade
3. **Click on node** and look for version indicator
4. **Check for upgrade option** - N8N shows upgrade button if newer version available
5. **Review breaking changes** in N8N release notes
6. **Test workflow** after upgrading

!!! warning "Breaking Changes"
Version upgrades may introduce breaking changes. Always test workflows after upgrading node versions.

#### Why These Versions?

**Agent v3:**

- Improved error output handling
- Better fallback model integration
- Enhanced retry mechanism
- Fixed critical bugs in tool execution

**LLM v1.3:**

- `responseApiEnabled` parameter introduced
- Proper streaming response support
- Better error messages
- Consistent output formatting

**responseApiEnabled=false:**

- Ensures responses use standard format
- Prevents API-specific formatting issues
- Required for compatibility with our Agent implementation

**Call Workflow Tool v2.2:**

- Improved workflow input parameter handling
- Better validation of workflow references
- Enhanced error messages for missing workflows
- Fixed issues with dynamic input definitions

## Usage

### Command-Line Options

```bash
php api/tests/N8N/validate-n8n-workflows.php [OPTIONS]
```

| Option                   | Description                                        | Default                |
| ------------------------ | -------------------------------------------------- | ---------------------- |
| `--format=<format>`      | Output format: `console` or `junit`                | `console`              |
| `--output=<file>`        | JUnit output file path (required for junit format) | -                      |
| `--coverage=<file>`      | Cobertura coverage report file path (for GitLab)   | -                      |
| `--workflows-dir=<path>` | Custom workflows directory                         | `docker/n8n/workflows` |

### Output Formats

#### Console Output (Always Active)

Colored terminal output with emoji indicators:

```text
🔍 Validating workflows in docker/n8n/workflows

❌ N8N Naming Convention: 60 critical, 0 warnings
  📄 generate-reference-subject.json
    Node: AI Agent Reference Subject (ID: 65b25dde...)
    [INVALID_NAMING_CONVENTION] Node name "AI Agent Reference Subject"
    does not follow Domain_Role_Action convention

✅ N8N Agent Configuration: All checks passed!

═══════════════════════════════════════════════════════════════
📊 VALIDATION SUMMARY
═══════════════════════════════════════════════════════════════

N8N Naming Convention: 60 critical, 0 warnings
N8N Agent Configuration: 0 critical, 0 warnings

🔴 CRITICAL ERRORS: 60 (will fail CI)

📄 WORKFLOWS VALIDATED: 12
📊 NODES CHECKED: 181
```

#### JUnit Output (Optional)

XML format for GitLab CI integration:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<testsuites tests="180" failures="60" errors="0">
  <testsuite name="N8N Naming Convention" tests="60" failures="60">
    <testcase name="generate-reference-subject.json :: AI Agent Reference Subject"
              classname="N8N.generate-reference-subject.json">
      <failure type="INVALID_NAMING_CONVENTION">
        Node name "AI Agent Reference Subject" does not follow Domain_Role_Action convention
      </failure>
    </testcase>
  </testsuite>
</testsuites>
```

#### Coverage Report (Optional)

Generate a Cobertura XML coverage report for GitLab visualization:

```bash
php api/tests/N8N/validate-n8n-workflows.php --coverage=coverage.xml
```

The coverage report shows which workflow nodes have been examined by validators:

- **Line rate**: Percentage of nodes validated (0.0 to 1.0)
- **Lines covered**: Number of nodes examined by at least one validator
- **Lines valid**: Total number of nodes across all workflows

**Coverage Calculation:**

Each validator tracks which nodes it examines:

- **Naming Convention**: Validates all nodes except exempt types (~67% coverage)
- **Agent Configuration**: Validates only AI agent nodes (~7% coverage)
- **Feedback Nodes**: Validates only nodes starting with `Feedback_` (~9% coverage)
- **RabbitMQ Messages**: Validates RabbitMQ publisher/trigger nodes (~12% coverage)

**Console Output Example:**

```text
📈 COVERAGE BY VALIDATOR

N8N Naming Convention: 108/161 nodes (67.1%)
N8N Agent Configuration: 11/161 nodes (6.8%)
N8N Feedback Node Configuration: 15/161 nodes (9.3%)
N8N RabbitMQ Message Format: 19/161 nodes (11.8%)
```

**Cobertura XML Format:**

GitLab natively supports Cobertura format for coverage visualization:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<coverage line-rate="1" branch-rate="0" lines-covered="161" lines-valid="161">
  <sources>
    <source>/path/to/workflows</source>
  </sources>
  <packages>
    <package name="n8n.workflows" line-rate="1">
      <classes>
        <class name="orchestrator-router"
               filename="/path/to/orchestrator-router.json"
               line-rate="1">
          <lines>
            <line number="1" hits="1" branch="false"/>
            <line number="2" hits="1" branch="false"/>
          </lines>
        </class>
      </classes>
    </package>
  </packages>
</coverage>
```

### Exit Codes

| Code | Meaning                                         |
| ---- | ----------------------------------------------- |
| `0`  | All validations passed                          |
| `1`  | Validation errors found (fails CI)              |
| `2`  | Script error (invalid arguments, missing files) |

## GitLab CI Integration

The validator runs automatically in CI via the `n8n-workflow-validation` job:

```yaml
n8n-workflow-validation:
  stage: TestsAndSecurity
  script:
    - cd api
    - php tests/N8N/validate-n8n-workflows.php \
      --workflows-dir=../docker/n8n/workflows \
      --format=junit \
      --output=var/reports/n8n-workflow-validation.xml \
      --coverage=var/reports/n8n-workflow-coverage.xml
  artifacts:
    reports:
      junit: api/var/reports/n8n-workflow-validation.xml
      coverage_report:
        coverage_format: cobertura
        path: api/var/reports/n8n-workflow-coverage.xml
    when: always
    expire_in: 1 week
  allow_failure: false # CI fails if validation errors found
```

### CI Output

GitLab displays validation results in:

- **Pipeline view**: Test report with pass/fail status
- **Merge Request**: Test summary in MR widget
- **Coverage badge**: Green coverage visualization for validated nodes
- **Job artifacts**: Downloadable JUnit XML and coverage reports

The coverage report appears in:

- **Project badges**: Show coverage percentage
- **Merge Request widget**: Coverage change compared to base branch
- **Pipeline graphs**: Coverage trends over time

## Fixing Common Issues

### Naming Convention Errors

**Problem:** `Node name "AI Agent Reference Subject" does not follow Domain_Role_Action convention`

**Solution:**

1. Identify the domain (e.g., `Folder`, `Actor`, `Document`)
2. Identify the role (e.g., `Agent`, `LLM`, `Validator`)
3. Identify the action (e.g., `UpdateReferenceSubject`, `DetectEntities`)
4. Rename node: `Folder_Agent_UpdateReferenceSubject`

### Agent Configuration Errors

**Problem:** `Agent node "Generate Summary" must have needsFallback=true`

**Solution:** In N8N node settings:

1. Open agent node configuration
2. Enable "Fallback Model" option
3. Configure fallback LLM (e.g., different model/provider)

**Problem:** `Agent node "AI Validation" has error output enabled but not connected`

**Solution:**

1. Add error handler node (e.g., `Set_Format_ErrorResponse`)
2. Connect agent's error output (red dot, index 1) to error handler
3. Implement proper error response formatting

### Feedback Node Errors

**Problem:** `Feedback node "Feedback_Complete" must include "success: boolean"`

**Solution:** Update JSON output to include all required fields:

```javascript
={{
  {
    success: true,  // Add this
    message: $('language') === 'en'
      ? 'Operation completed successfully'
      : 'Opération terminée avec succès',  // Add this
    context: {
      execution: $execution  // Add this
    },
    data: $json  // Your existing data
  }
}}
```

## Architecture

### Validation Flow

```text
validate-n8n-workflows.php
    ├── WorkflowLoader.loadAllWorkflows()
    │   └── Load all *.json files from workflows directory
    ├── For each validator:
    │   ├── NamingConventionValidator.validateDirectory()
    │   ├── AgentNodeValidator.validateDirectory()
    │   ├── FeedbackNodeValidator.validateDirectory()
    │   ├── RabbitMQMessageValidator.validateDirectory()
    │   ├── CallWorkflowToolNodeValidator.validateDirectory()
    │   ├── ExecuteSubWorkflowNodeValidator.validateDirectory()
    │   └── NodeVersionValidator.validateDirectory()
    └── Report results:
        ├── ConsoleReporter.report() (always)
        └── JUnitReporter.generate() (if --format=junit)
```

### Class Structure

```text
App\Tests\N8N\Validation\
├── AbstractValidator            # Base class for all validators
├── ValidationError             # Value object for single error
├── ValidationSeverity          # CRITICAL | WARNING enum
├── ValidationResult            # Aggregates errors from one validator
├── WorkflowLoader             # Loads and parses workflow JSON files
├── ClassPropertyDefinition    # PHP class property introspection
├── ConsoleReporter            # Pretty console output
├── JUnitReporter              # JUnit XML generation
├── NamingConventionValidator
├── AgentNodeValidator
├── FeedbackNodeValidator
├── RabbitMQMessageValidator
├── CallWorkflowToolNodeValidator
├── ExecuteSubWorkflowNodeValidator
└── NodeVersionValidator
```

### Extending with New Validators

1. Create new validator class extending `AbstractValidator`
2. Implement `getName()` and `validateWorkflow()` methods
3. Use `addError()` to report validation issues
4. Add validator instance to `validate-n8n-workflows.php`

Example:

```php
final class CustomValidator extends AbstractValidator
{
    public function getName(): string
    {
        return 'My Custom Validator';
    }

    protected function validateWorkflow(string $filename, array $workflow): void
    {
        foreach ($workflow['nodes'] as $node) {
            ++$this->totalNodes;

            // Your validation logic here
            if ($someCondition) {
                $this->addError(
                    workflowFile: $filename,
                    workflowName: $workflow['name'],
                    nodeName: $node['name'],
                    nodeId: $node['id'],
                    errorType: 'MY_ERROR_TYPE',
                    message: 'Description of the issue',
                    severity: ValidationSeverity::CRITICAL
                );
            }
        }
    }
}
```

## Skipping Validation

Sometimes you need to skip validation for specific nodes that cannot comply with validation rules due to runtime behavior or dynamic configuration. You can use special tags in the node's **Notes** field to skip validation.

### Tag Format

Add tags to the node's Notes field in N8N:

```text
@n8n-validate-ignore <target>
```

Where `<target>` can be:

- **Validator name** (without "Validator" suffix) - Skips all checks from that validator
- **Specific error type** - Skips only that specific error

### Example: Skip Specific Error

```text
@n8n-validate-ignore RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED

This message comes from the AI agent which generates translated responses.
```

### Example: Skip All Validator Checks

```text
@n8n-validate-ignore RabbitMQMessage

Dynamic configuration - validated at runtime.
```

### Available Skip Targets

**Validator Names:**

- `RabbitMQMessage` - All RabbitMQ message format checks
- `Agent` - All AI Agent configuration checks
- `CallWorkflowTool` - All Call Workflow Tool checks
- `ExecuteSubWorkflow` - All Execute Sub-workflow checks
- `FeedbackNode` - All Feedback node checks
- `NamingConvention` - All naming convention checks
- `NodeVersion` - All node version requirement checks

**Common Error Types:**

- `RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED` - Chat message translation check
- `RABBITMQ_DYNAMIC_TYPE_HEADER` - Dynamic type header warning
- `AGENT_MISSING_FALLBACK` - Agent fallback requirement
- `AGENT_ERROR_OUTPUT_NOT_CONNECTED` - Agent error output check
- `AGENT_VERSION_TOO_LOW` - Agent node version requirement
- `LLM_VERSION_TOO_LOW` - LLM node version requirement
- `LLM_RESPONSE_API_ENABLED` - LLM responseApiEnabled check
- `CALL_WORKFLOW_TOOL_VERSION_TOO_LOW` - Call Workflow Tool node version requirement

See validation output for complete list of error types.

### Best Practices

**✅ DO:**

- Add clear justification after the tag explaining WHY validation should be skipped
- Use specific error types when possible instead of skipping entire validator
- Document runtime behavior that makes static validation impossible
- Keep tags up to date when changing node configuration

**❌ DON'T:**

- Abuse skip tags to hide real validation errors
- Skip without documentation - always explain why
- Use skip tags as permanent solution when the node can be fixed
- Skip entire validators when only one check is problematic

### Multiple Skip Tags

You can add multiple skip tags to skip different checks:

```text
@n8n-validate-ignore RABBITMQ_CHAT_MESSAGE_NOT_TRANSLATED
@n8n-validate-ignore AGENT_ERROR_OUTPUT_NOT_CONNECTED

Complex workflow with runtime error handling and AI-generated messages.
```

## Related Documentation

- [N8N Overview](index.md) - Main N8N documentation
- [Workflow Development](index.md#workflow-development) - Best practices for N8N workflows

## Troubleshooting

### Validator doesn't detect my node

**Check:**

1. Node type matches expected pattern (e.g., `@n8n/n8n-nodes-langchain.agent`)
2. Node naming matches feedback patterns
3. Node is not in exempt types list

### False positive errors

**Common causes:**

1. Typos in node configuration
2. Case sensitivity issues (PascalCase required)
3. Missing underscores in naming

### CI job fails unexpectedly

**Debug steps:**

1. Run validator locally: `task n8n:validate`
2. Check workflows directory path is correct
3. Verify all workflows are valid JSON
4. Check PHPStan/ECS passed before validation

## Best Practices

1. **Run validator locally** before committing workflow changes
2. **Fix CRITICAL errors immediately** - they will fail CI
3. **Address warnings** for better maintainability
4. **Use consistent naming** across all workflows
5. **Enable all agent safeguards** (fallback, retry, error handling)
6. **Always include execution context** in responses for debugging
7. **Translate user-facing messages** based on language preference

## Performance

- **Validation time**: ~1-2 seconds for 12 workflows (180 nodes)
- **Memory usage**: < 50MB
- **No network calls**: All validation is local
- **PHPStan level 9**: Strict type safety guaranteed
