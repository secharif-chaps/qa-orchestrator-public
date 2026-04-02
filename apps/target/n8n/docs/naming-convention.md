# N8N Workflow Node Naming Convention

## Overview

This document defines the official naming convention for N8N workflow nodes in the Basil project. Consistent naming improves workflow readability, maintainability, and collaboration.

## Convention Format

**Pattern**: `[Domain]_[Role]_[Action/Modifier]`

- **Domain**: Functional category or context (e.g., `LLM`, `Agent`, `Trigger`, `Condition`)
- **Role**: Specific purpose or component name (e.g., `Validator`, `Generator`, `ReferenceSubject`)
- **Action/Modifier**: Operation or variant (e.g., `Primary`, `Fallback`, `Structured`, `IsRelevant`)

**Style**: PascalCase with underscore separators

## Quick Reference Table

| Node Type              | Prefix Pattern                       | Use Case                     | Example                            |
| ---------------------- | ------------------------------------ | ---------------------------- | ---------------------------------- |
| **Workflow Entry**     | `Workflow_Trigger_<Purpose>`         | Subworkflow triggers         | `Workflow_Trigger_AddActor`        |
| **Webhook**            | `Webhook_Trigger_<Purpose>`          | HTTP webhook entry point     | `Webhook_Trigger_TestInput`        |
| **Conditions**         | `Condition_<Check\|Validate>_<What>` | Logical branching            | `Condition_Check_SourceDuplicate`  |
| **LLM Models**         | `LLM_Model_<Role>`                   | Language model calls         | `LLM_Model_ValidationPrimary`      |
| **LLM Parsers**        | `LLM_Parser_<OutputType>`            | Structured output parsers    | `LLM_Parser_StructuredOutput`      |
| **AI Agents**          | `LLM_Agent_<Purpose>`                | Complex AI agents            | `LLM_Agent_ValidateActorRelevance` |
| **AI Tools**           | `Tool_<Service>_<Action>`            | AI agent tools               | `Tool_Wikipedia_Search`            |
| **RabbitMQ**           | `RabbitMQ_Publish_<Message>`         | Message queue operations     | `RabbitMQ_Publish_ChatMessage`     |
| **Feedback**           | `Feedback_<State>`                   | User-facing messages         | `Feedback_Success`                 |
| **Data Format**        | `Set_Format_<Purpose>`               | Internal data transformation | `Set_Format_TestInput`             |
| **Response**           | `Response_Return_<Type>`             | Final workflow output        | `Response_Return_Final`            |
| **Execution Metadata** | `ExecutionData_Store_<What>`         | Test metadata storage        | `ExecutionData_Store_TestMetadata` |
| **External Services**  | `<Service>_<Action>_<What>`          | External API calls           | `JinaAI_Fetch_WebpageContent`      |

## Domain Categories

### Workflow Triggers

**Prefix**: `Workflow_Trigger_<Purpose>`

Nodes that initiate subworkflow execution.

**Examples**:

- `Workflow_Trigger_AddActor` - Actor addition subworkflow trigger
- `Workflow_Trigger_AddSource` - Source addition subworkflow trigger
- `Workflow_Trigger_UpdateReferenceSubject` - Reference subject update trigger

### Webhook Triggers

**Prefix**: `Webhook_Trigger_<Purpose>`

HTTP webhook entry points (typically for testing).

**Examples**:

- `Webhook_Trigger_TestInput` - Test webhook entry point
- `Webhook_Trigger_ExternalAPI` - External API webhook
- `Webhook_Trigger_Callback` - Callback webhook

### Conditions

**Prefix**: `Condition_<Check|Validate>_<What>`

Nodes that perform logical branching.

**Sub-patterns**:

- `Condition_Check_<What>` - Existence or state checks
- `Condition_Validate_<What>` - Validation and verification

**Examples**:

- `Condition_Check_ActorDuplicate` - Check if actor is duplicate
- `Condition_Check_SourceDuplicate` - Check if source is duplicate
- `Condition_Validate_ActorRelevance` - Validate actor relevance
- `Condition_Validate_BakusFormat` - Validate BAKUS format
- `Condition_Check_ConversationContext` - Check conversation origin

### LLM Models

**Prefix**: `LLM_Model_<Role>`

Direct language model invocation nodes.

**Common roles**:

- `<Domain>Primary` - Main LLM call (e.g., `ValidationPrimary`)
- `<Domain>Fallback` - Backup LLM call (e.g., `ValidationFallback`)
- `<Domain>Parser` - Parser-specific LLM (e.g., `ValidationParser`)

**Examples**:

- `LLM_Model_ValidationPrimary` - Main validation LLM
- `LLM_Model_ValidationFallback` - Backup validation LLM
- `LLM_Model_BakusPrimary` - Main BAKUS mapping LLM
- `LLM_Model_BakusParser` - BAKUS parser LLM

### LLM Parsers

**Prefix**: `LLM_Parser_<OutputType>`

Structured output parsers for LLM responses.

**Examples**:

- `LLM_Parser_ValidationOutput` - Validation output parser
- `LLM_Parser_BakusOutput` - BAKUS output parser
- `LLM_Parser_StructuredOutput` - Generic structured parser
- `LLM_Parser_JSONExtractor` - JSON extraction parser

### AI Agents

**Prefix**: `LLM_Agent_<Purpose>`

Complex AI agents with specialized prompts and tools.

**Examples**:

- `LLM_Agent_ValidateActorRelevance` - Actor relevance validation agent
- `LLM_Agent_ValidateSourceRelevance` - Source relevance validation agent
- `LLM_Agent_MapSourceBakus` - Source BAKUS mapping agent
- `LLM_Agent_GenerateReferenceSubject` - Reference subject generation agent

### AI Tools

**Prefix**: `Tool_<Service>_<Action>`

Tools available to AI agents for external data retrieval.

> **⚠️ IMPORTANT**: AI tool node names are often referenced directly in LLM agent system prompts. When renaming an AI tool node (e.g., `watchFileBuilder` → `Tool_WatchFile_Builder`), you **must** also update all references to that tool name in the corresponding LLM Agent node's system message. Failing to do so will cause the agent to reference non-existent tools.

**Examples**:

- `Tool_Wikipedia_Search` - Wikipedia search tool
- `Tool_JinaAI_ReadWebpage` - JinaAI webpage reading tool
- `Tool_Calculator_Compute` - Calculator tool
- `Tool_WebSearch_Query` - Web search tool

### Message Queue

**Prefix**: `RabbitMQ_Publish_<Message>`

RabbitMQ message publishing operations.

**Examples**:

- `RabbitMQ_Publish_ChatMessage` - Publish chat message to queue
- `RabbitMQ_Publish_ActorCreation` - Publish actor creation event
- `RabbitMQ_Publish_SourceUpdate` - Publish source update event
- `RabbitMQ_Publish_ReferenceSubject` - Publish reference subject update

### Feedback Nodes

**Prefix**: `Feedback_<State>`

**Purpose**: User-facing messages and system notifications that are displayed or sent to users/chat interfaces.

**Key distinction**: Use `Feedback_` for messages intended for end-users or chat systems. For internal data transformations, use `Set_Format_` instead.

**Examples**:

- `Feedback_Success` - Success message to user
- `Feedback_SourceNotRelevant` - Source not relevant notification
- `Feedback_SourceDuplicated` - Duplicate source error message
- `Feedback_BakusInvalid` - BAKUS validation error message
- `Feedback_AIAgentError` - AI agent processing error notification
- `Feedback_InvalidDomain` - Invalid domain error message

### Data Formatting Nodes

**Prefix**: `Set_Format_<Purpose>`

**Purpose**: Internal data transformations and formatting operations. These nodes prepare data for processing but are not user-facing messages.

**Key distinction**: Use `Set_Format_` for internal data manipulation. For user-facing messages, use `Feedback_` instead.

**Examples**:

- `Set_Format_TestInput` - Format webhook test input data
- `Set_Format_RequestBody` - Format HTTP request body
- `Set_Format_QueryParameters` - Format query parameters
- `Set_Format_TransformData` - Transform data structure

### Response Nodes

**Prefix**: `Response_Return_<Type>`

Final workflow outputs and return values.

**Examples**:

- `Response_Return_Final` - Final workflow response
- `Response_Return_Success` - Success response
- `Response_Return_Error` - Error response
- `Response_Return_JSON` - Structured JSON response

### Execution Metadata Nodes

**Prefix**: `ExecutionData_Store_<What>`

Nodes that store execution metadata for testing and monitoring.

**Examples**:

- `ExecutionData_Store_TestMetadata` - Store test execution metadata
- `ExecutionData_Store_PerformanceMetrics` - Store performance data
- `ExecutionData_Store_AuditLog` - Store audit trail information

### External Services

**Prefix**: `<Service>_<Action>_<What>`

Nodes that call external services or APIs (excluding standard HTTP requests).

**Examples**:

- `JinaAI_Fetch_WebpageContent` - Fetch webpage via JinaAI Reader API
- `Wikipedia_Search_Article` - Search Wikipedia articles
- `OpenSearch_Query_Documents` - Query OpenSearch index
- `OpenAI_Generate_Completion` - Generate OpenAI completion

### HTTP Requests

**Prefix**: `HTTP_[Service]_[Action]`

External HTTP API calls.

**Examples**:

- `HTTP_API_GetFolder` - Fetch folder from API
- `HTTP_OpenSearch_Search` - Search in OpenSearch
- `HTTP_AI_GenerateResponse` - Call AI service

### Database Operations

**Prefix**: `DB_[Entity]_[Operation]`

Database CRUD operations.

**Examples**:

- `DB_User_Create` - Create user record
- `DB_Folder_Update` - Update folder record
- `DB_Document_Query` - Query document records

## Naming Best Practices

### ✅ DO

- Use PascalCase for each segment
- Keep names concise but descriptive
- Group related nodes with common prefixes
- Use consistent terminology across workflows
- Follow the `Domain_Role_Action` hierarchy

### ❌ DON'T

- Mix naming styles (snake_case, camelCase, kebab-case)
- Use arbitrary numbers (`Parser2`, `success2`)
- Include questions in names (`So it's relevant?`)
- Use spaces or special characters beyond underscores
- Use French or mixed languages (English only)
- Create overly long names (>40 characters recommended)

## Migration Example

### Before Uniformization

```
trigger_subworkflow
is the first update?
Validator_Primary
Structured Output Parser2
ReferenceSubject - So it's relevant ?
From conversation ?2
Add message system2
Feedback - success2
RefSubjectFeedback
```

### After Uniformization

```
Workflow_Trigger_UpdateReferenceSubject
Condition_Check_IsFirstUpdate
LLM_Model_ValidationPrimary
LLM_Parser_StructuredOutput
Condition_Validate_Relevance
Condition_Check_ConversationContext
RabbitMQ_Publish_ChatMessage
Feedback_Success
Response_Return_Final
```

## Workflow Pipeline Example

### Add Actor to WatchFile Pipeline

```
Workflow_Trigger_AddActor
│
├─ Test Entry Points
│  ├─ Webhook_Trigger_TestInput
│  ├─ Set_Format_TestInput
│  └─ ExecutionData_Store_TestMetadata
│
├─ Validation Checks
│  ├─ Condition_Validate_PrimaryDomain
│  ├─ Condition_Check_PrimaryDomainExists
│  ├─ Condition_Check_ConversationContext
│  └─ Condition_Check_ActorDuplicate
│
├─ External Data Retrieval
│  ├─ JinaAI_Fetch_WebpageContent
│  └─ Tool_Wikipedia_Search
│
├─ AI Processing
│  ├─ LLM_Agent_ValidateActorRelevance
│  ├─ LLM_Model_Primary
│  ├─ LLM_Model_Secondary
│  ├─ LLM_Model_Parser
│  └─ LLM_Parser_StructuredOutput
│
├─ Relevance Check
│  └─ Condition_Validate_ActorRelevance
│
├─ Message Publishing
│  ├─ RabbitMQ_Publish_ChatMessage
│  └─ RabbitMQ_Publish_ActorCreation
│
├─ User Feedback
│  ├─ Feedback_Success
│  ├─ Feedback_SourceNotRelevant
│  ├─ Feedback_DuplicateActorError
│  ├─ Feedback_InvalidDomain
│  └─ Feedback_AIAgentError
│
└─ Response_Return_Final
```

## Benefits of This Convention

### Visual Grouping

Nodes are automatically grouped by domain in the workflow editor, making it easy to identify:

- All LLM nodes (`LLM_Model_*`, `LLM_Parser_*`, `LLM_Agent_*`)
- All conditions (`Condition_Check_*`, `Condition_Validate_*`)
- All feedback messages (`Feedback_*`)
- All message queue operations (`RabbitMQ_Publish_*`)
- All AI tools (`Tool_*`)

### Scalability

New nodes can be added without creating naming conflicts:

- `LLM_Model_ClassifierPrimary`
- `LLM_Agent_SummarizeDocument`
- `Condition_Check_HasAttachments`
- `Tool_GoogleSearch_Query`
- `Feedback_QuotaExceeded`

### Consistency

All team members can predict node names based on functionality:

- Need a fallback LLM for translation? → `LLM_Model_TranslationFallback`
- Adding a relevance check? → `Condition_Validate_Relevance`
- Publishing to RabbitMQ? → `RabbitMQ_Publish_<MessageType>`
- Adding a Wikipedia tool? → `Tool_Wikipedia_Search`
- Showing success feedback? → `Feedback_Success`

### Maintainability

Finding and updating nodes becomes straightforward:

```bash
# Find all LLM models
grep "LLM_Model_" workflow.json

# Find all AI agents
grep "LLM_Agent_" workflow.json

# Find all RabbitMQ operations
grep "RabbitMQ_Publish_" workflow.json

# Find all feedback nodes
grep "Feedback_" workflow.json

# Find all validation conditions
grep "Condition_Validate_" workflow.json

# Find all AI tools
grep "Tool_" workflow.json
```

### Automation Friendly

Scripts can easily parse and manipulate workflows based on naming patterns:

Example:

```python
import json

with open('workflow.json') as f:
    workflow = json.load(f)

    # Find all LLM-related nodes
    llm_models = [node for node in workflow['nodes']
                  if node['name'].startswith('LLM_Model_')]
    llm_agents = [node for node in workflow['nodes']
                  if node['name'].startswith('LLM_Agent_')]

    # Find all feedback nodes
    feedback_nodes = [node for node in workflow['nodes']
                      if node['name'].startswith('Feedback_')]

    print(f"LLM Models: {len(llm_models)}")
    print(f"AI Agents: {len(llm_agents)}")
    print(f"Feedback Nodes: {len(feedback_nodes)}")
```
