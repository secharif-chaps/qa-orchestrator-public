# N8N Workflow Patterns

## Workflow Structure

### Main Workflow Pattern

```
Trigger_RabbitMQ
  │
  ├─ Input Validation
  │   └─ Condition_Validate_Input
  │
  ├─ Data Enrichment
  │   └─ HTTP_API_GetContext
  │
  ├─ AI Processing
  │   ├─ LLM_Model_Primary
  │   │   → [Error] → LLM_Model_Fallback
  │   └─ LLM_Parser_Structured
  │
  ├─ Result Validation
  │   └─ Condition_Validate_Result
  │
  ├─ Output
  │   ├─ [Success] → RabbitMQ_Publish_Response
  │   └─ [Error] → RabbitMQ_Publish_Error
  │
  └─ Response_Return_Final
```

### Sub-workflow Pattern

```
Workflow_Trigger_ProcessDocument
  │
  ├─ Test Entry (for testing)
  │   ├─ Webhook_Trigger_TestInput
  │   ├─ Set_Format_TestInput
  │   └─ ExecutionData_Store_TestMetadata
  │
  ├─ Main Logic
  │   └─ ... processing nodes ...
  │
  └─ Response_Return_Final
```

## AI Processing Patterns

### LLM Chain with Fallback

```
LLM_Model_ValidationPrimary
  │
  ├─ [Success] → LLM_Parser_StructuredOutput
  │               → Condition_Validate_Result
  │
  └─ [Error] → LLM_Model_ValidationFallback
               │
               ├─ [Success] → LLM_Parser_StructuredOutput
               │
               └─ [Error] → Feedback_AIError
```

### AI Agent with Tools

```javascript
// LLM_Agent_WatchFileBuilder configuration
{
  "model": "gemini-1.5-flash",
  "temperature": 0.2,
  "systemMessage": `You are an AI assistant for building WatchFiles.

Available tools:
- Tool_WatchFile_AddActor: Add actor to watchfile
- Tool_WatchFile_AddSource: Add source to watchfile
- Tool_Search_Web: Search the web for information

Always validate relevance before adding.`,
  "tools": ["Tool_WatchFile_AddActor", "Tool_WatchFile_AddSource", "Tool_Search_Web"]
}
```

### Structured Output Parsing

```javascript
// LLM_Parser_StructuredOutput code
const text = $json.response

// Extract JSON from LLM response
const start = text.indexOf('{')
const end = text.lastIndexOf('}') + 1

if (start === -1 || end === 0) {
    throw new Error('No JSON found in response')
}

const jsonStr = text.substring(start, end)
const parsed = JSON.parse(jsonStr)

// Validate required fields
if (typeof parsed.isRelevant !== 'boolean') {
    throw new Error('Missing isRelevant field')
}

return parsed
```

## Condition Patterns

### State Check

```javascript
// Condition_Check_IsFirstUpdate
{
    {
        $json.watchFile.referenceSubject === null || $json.watchFile.referenceSubject === ''
    }
}
```

### Relevance Validation

```javascript
// Condition_Validate_Relevance
{
    {
        $json.isRelevant === true && $json.confidence >= 0.8
    }
}
```

### Origin Check

```javascript
// Condition_Check_FromConversation
{
    {
        $json.source === 'conversation' || $json.metadata?.fromChat === true
    }
}
```

## RabbitMQ Response Patterns

### Success Response

```javascript
// RabbitMQ_Publish_ChatMessage
{
  "conversationId": "{{ $json.conversationId }}",
  "message": "{{ $json.generatedMessage }}",
  "messageId": "{{ $json.userMessageId }}",
  "context": {
    "execution": {
      "id": "{{ $execution.id }}"
    },
    "model": "gemini-1.5-flash",
    "confidence": {{ $json.confidence }}
  }
}
```

### Error Response

```javascript
// RabbitMQ_Publish_Error
{
  "conversationId": "{{ $json.conversationId }}",
  "messageId": "{{ $json.userMessageId }}",
  "error": {
    "message": "{{ $json.error.message }}",
    "stage": "{{ $json.stage }}",
    "retryable": true
  },
  "context": {
    "execution": {
      "id": "{{ $execution.id }}"
    }
  }
}
```

## Feedback Messages

### Success Feedback

```javascript
// Feedback_Success (Set node)
{
  "success": true,
  "message": "{{ $json.generatedContent }}",
  "context": {
    "execution": {
      "id": "{{ $execution.id }}"
    },
    "metadata": {
      "model": "{{ $json.model }}",
      "confidence": {{ $json.confidence }}
    }
  }
}
```

### Error Feedback

```javascript
// Feedback_Error (Set node)
{
  "success": false,
  "message": "An error occurred while processing your request.",
  "error": {
    "type": "{{ $json.errorType }}",
    "details": "{{ $json.errorMessage }}"
  },
  "context": {
    "execution": {
      "id": "{{ $execution.id }}"
    }
  }
}
```

## Data Transformation

### Filter Minimal Fields

```javascript
// Code_FilterFields
return $input.all().map((item) => ({
    id: item.json.id,
    name: item.json.name,
    status: item.json.status,
}))
```

### Merge Results

```javascript
// Code_MergeResults
const primary = $('LLM_Model_Primary').json
const enrichment = $('HTTP_API_GetContext').json

return {
    ...primary,
    context: enrichment,
    processedAt: new Date().toISOString(),
}
```

## Expression Reference

### Data Access

```javascript
$json // Current node input
$json.field // Access field
$json.nested?.field // Optional chaining
$node['NodeName'].json // Specific node output
$('NodeName').json // Alternative syntax
$input.all() // All input items
$execution.id // Execution ID
$workflow.id // Workflow ID
$now.toISO() // Current timestamp
```

### Conditionals

```javascript
{
    {
        $json.score > 0.8 ? 'valid' : 'invalid'
    }
}
{
    {
        $json.items?.length ?? 0
    }
}
{
    {
        $json.status === 'active' && $json.enabled
    }
}
```

### Array Operations

```javascript
{
    {
        $json.items.map((i) => i.name).join(', ')
    }
}
{
    {
        $json.items.filter((i) => i.active).length
    }
}
{
    {
        $json.items.find((i) => i.id === '123')
    }
}
```
