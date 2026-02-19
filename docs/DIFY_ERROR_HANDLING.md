# Dify Error Handling

## 📋 Overview

This document describes the error handling system for Dify callbacks, implemented according to ticket **TAR-1171**.

## 🎯 Features

### ✅ Implemented

1. **Structured Error Parsing** - Handles callbacks with format:
   ```json
   {
     "status": "failed",
     "message": [
       {
         "error_type": "<error_type>",
         "error_message": "<error_message>",
         "retry_after": 120,
         "...": "other_optional_attributes"
       }
     ]
   }
   ```

2. **Error Categorization** - Maps to internal types:
   - Rate limiting (LLM, API)
   - Authentication (invalid key, expired)
   - Workflow errors (timeout, node, validation)
   - Data errors (invalid input, missing field)
   - External services (unavailable, API error)
   - Network (timeout, connection)

3. **Error Whitelist** - Special handling for specific types:
   - Whitelisted errors receive custom messages
   - Specific retry logic
   - Metadata extraction (retry_after, node_id, etc.)

4. **Contextualized and Internationalized User Messages**:
   - **Default: English** - "The service is temporarily overloaded. Please retry in 2 minutes."
   - **French** - "Le service est temporairement saturé. Merci de réessayer dans 2 minutes."
   - Language auto-detection via query param (`?lang=fr`) or Accept-Language header
   - Falls back to English for unsupported languages

5. **Comprehensive Logging** - All errors are logged with:
   - Original and categorized error types
   - Technical metadata
   - Priority information
   - Whitelist/recoverable status

6. **Enriched API Response** - The webhook returns:
   ```json
   {
     "message": "Dify callback processed successfully",
     "task_id": 123,
     "new_status": "error",
     "error_details": {
       "user_message": "User-friendly message",
       "error_type": "rate_limit_llm",
       "is_recoverable": true,
       "retry_after_seconds": 120,
       "recommended_action": "retry_after_120s",
       "error_count": 1,
       "has_whitelisted_error": true
     }
   }
   ```

## 🏗️ Architecture

### Files Created

1. **`app/schemas/dify_errors.py`** - Pydantic schemas:
   - `DifyErrorType` - Enum of error types
   - `DifyErrorDetail` - Individual error detail
   - `DifyErrorResponse` - Complete callback format
   - `ParsedError` - Processed error
   - `ErrorHandlingResult` - Processing result

2. **`app/core/dify_error_config.py`** - Configuration:
   - `WHITELISTED_ERROR_TYPES` - Set of errors to handle specially
   - `ERROR_TYPE_MAPPING` - Dify types → internal types mapping
   - `ERROR_PRIORITY` - Priorities for primary error selection
   - `RECOVERABLE_ERROR_TYPES` - Errors that can be retried

3. **`app/core/dify_error_i18n.py`** - Internationalization (NEW):
   - `USER_MESSAGE_TEMPLATES_EN` - English message templates (default)
   - `USER_MESSAGE_TEMPLATES_FR` - French message templates
   - `get_message_template()` - Gets template by language
   - `SUPPORTED_LANGUAGES` - Supported languages: en, fr
   - Helper functions for formatting (retry_time, node_info, etc.)

4. **`app/services/dify_error_handler.py`** - Main service:
   - `DifyErrorHandler(language="en")` - Handler class with i18n support
   - `parse_error_callback()` - Parses and categorizes
   - `is_error_callback()` - Detects if callback is error
   - `get_user_error_message()` - Generates localized user message
   - `should_retry_task()` - Determines if automatic retry
   - `get_retry_delay_seconds()` - Calculates retry delay

### Files Modified

1. **`app/api/endpoints/webhooks.py`**:
   - Import `DifyErrorHandler`
   - Integration in `dify_task_callback()`
   - Structured error parsing
   - Enriched response with `error_details`
   - Language detection from query param or Accept-Language header

## 🔧 Usage

### Backend Side

Processing is automatic in the webhook `/webhooks/dify/tasks/{task_id}/callback`:

```python
# In webhook

# Language detection (query param, Accept-Language header, or default to "en")
language = "en"  # Extracted from request
if "lang" in request.query_params:
    language = request.query_params["lang"]
elif "accept-language" in request.headers:
    # Parse first language code
    language = request.headers["accept-language"].split(",")[0].split("-")[0]

error_handler = DifyErrorHandler(language=language)

if error_handler.is_error_callback(body):
    # Parse and categorize
    error_result = error_handler.parse_error_callback(
        callback_body=body,
        task_id=task_id,
        task_type=task.type.value
    )

    # Get user message
    error_msg = error_handler.get_user_error_message(error_result)

    # Store in task
    task.status = TaskStatus.ERROR
    task.error = error_msg

    # Return structured details
    return {
        "message": "Dify callback processed successfully",
        "task_id": task_id,
        "new_status": "error",
        "error_details": {
            "user_message": error_result.primary_error.user_message,
            "error_type": error_result.primary_error.categorized_type.value,
            "is_recoverable": error_result.primary_error.is_recoverable,
            "retry_after_seconds": error_result.primary_error.retry_after_seconds,
            "recommended_action": error_result.recommended_action,
            "error_count": len(error_result.errors),
            "has_whitelisted_error": error_result.has_whitelisted_error,
        }
    }
```

### Frontend Side

The frontend can use `error_details` to:

1. **Display user message**:
   ```typescript
   if (response.error_details) {
     showToast(response.error_details.user_message, 'error')
   }
   ```

2. **Handle automatic retry**:
   ```typescript
   if (response.error_details.is_recoverable) {
     const delay = response.error_details.retry_after_seconds || 60
     setTimeout(() => retryTask(taskId), delay * 1000)
   }
   ```

3. **Recommended action**:
   ```typescript
   switch (response.error_details.recommended_action) {
     case 'retry':
       showRetryButton()
       break
     case 'contact_admin':
       showContactAdminMessage()
       break
     case 'fix_input':
       highlightInvalidFields()
       break
   }
   ```

## 📝 Configuration

### Adding a New Whitelisted Error Type

In `app/core/dify_error_config.py`:

```python
# 1. Add to whitelist
WHITELISTED_ERROR_TYPES.add("new_error_type")

# 2. Add mapping if needed
ERROR_TYPE_MAPPING["new_error_type"] = DifyErrorType.CUSTOM_TYPE

# 3. Define priority
ERROR_PRIORITY[DifyErrorType.CUSTOM_TYPE] = 85

# 4. If recoverable, add to set
RECOVERABLE_ERROR_TYPES.add(DifyErrorType.CUSTOM_TYPE)
```

### Adding/Modifying User Messages (i18n)

In `app/core/dify_error_i18n.py`, add template for each language:

```python
# English
USER_MESSAGE_TEMPLATES_EN[DifyErrorType.CUSTOM_TYPE] = lambda e: (
    f"Custom error message in English. "
    f"Please retry in {_format_retry_time(e.retry_after_seconds, 'en')}."
    if e.retry_after_seconds
    else "Custom error message in English."
)

# French
USER_MESSAGE_TEMPLATES_FR[DifyErrorType.CUSTOM_TYPE] = lambda e: (
    f"Message d'erreur personnalisé en français. "
    f"Merci de réessayer dans {_format_retry_time(e.retry_after_seconds, 'fr')}."
    if e.retry_after_seconds
    else "Message d'erreur personnalisé en français."
)
```

Templates support the following variables:
- `e.retry_after_seconds` - Retry delay (int)
- `e.technical_details.get('node_id')` - Node ID
- `e.technical_details.get('field_name')` - Field name
- `e.technical_details.get('service_name')` - Service name

Helper functions available:
- `_format_retry_time(seconds, lang)` - Formats delay (e.g., "2 minutes")
- `_format_node_info(node_id, lang)` - Formats node info (e.g., " (node: X)")
- `_format_field_info(field_name, lang)` - Formats field info
- `_format_service_info(service_name, lang)` - Formats service info

### Adding a New Language

In `app/core/dify_error_i18n.py`:

```python
# 1. Add supported language
SUPPORTED_LANGUAGES.append("de")  # German

# 2. Create templates for all error types
USER_MESSAGE_TEMPLATES_DE: Dict[DifyErrorType, Callable[[ParsedError], str]] = {
    DifyErrorType.RATE_LIMIT_LLM: lambda e: (
        f"Der Dienst ist vorübergehend überlastet. "
        f"Bitte versuchen Sie es in {_format_retry_time(e.retry_after_seconds, 'de')} erneut."
        if e.retry_after_seconds
        else "Der Dienst ist vorübergehend überlastet."
    ),
    # ... other types
}

# 3. Register in registry
MESSAGE_TEMPLATES_REGISTRY["de"] = USER_MESSAGE_TEMPLATES_DE

# 4. Update _format_retry_time() to support new language
def _format_retry_time(seconds: int | None, lang: str = "en") -> str:
    """Format retry time in a user-friendly way."""
    if not seconds:
        translations = {"en": "a few moments", "fr": "quelques instants", "de": "einige Augenblicke"}
        return translations.get(lang, translations["en"])
    # ... rest of logic
```

## 🧪 Tests

### Automated Tests

See `tests/test_dify_error_handler.py`:

```bash
# From backend container
cd ./infra
docker compose exec backend pytest tests/test_dify_error_handler.py -v
```

### Manual Tests with cURL

**Rate Limit with retry_after (English - default):**
```bash
curl -X POST http://localhost:8000/webhooks/dify/tasks/123/callback \
  -H "Content-Type: application/json" \
  -d '{
    "status": "failed",
    "message": [
      {
        "error_type": "rate_limit_llm",
        "error_message": "Too many requests to LLM service",
        "retry_after": 120,
        "service_name": "OpenAI GPT-4"
      }
    ]
  }'
```

**Rate Limit (French via query param):**
```bash
curl -X POST "http://localhost:8000/webhooks/dify/tasks/123/callback?lang=fr" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "failed",
    "message": [
      {
        "error_type": "rate_limit_llm",
        "error_message": "Trop de requêtes",
        "retry_after": 120
      }
    ]
  }'
```

**Rate Limit (French via Accept-Language header):**
```bash
curl -X POST http://localhost:8000/webhooks/dify/tasks/123/callback \
  -H "Content-Type: application/json" \
  -H "Accept-Language: fr-FR,fr;q=0.9,en;q=0.8" \
  -d '{
    "status": "failed",
    "message": [
      {
        "error_type": "rate_limit_llm",
        "error_message": "Trop de requêtes",
        "retry_after": 120
      }
    ]
  }'
```

**Authentication Error:**
```bash
curl -X POST http://localhost:8000/webhooks/dify/tasks/456/callback \
  -H "Content-Type: application/json" \
  -d '{
    "status": "failed",
    "message": [
      {
        "error_type": "auth_invalid_key",
        "error_message": "Invalid API key provided",
        "http_status": 401
      }
    ]
  }'
```

**Multiple Errors (priority test):**
```bash
curl -X POST http://localhost:8000/webhooks/dify/tasks/789/callback \
  -H "Content-Type: application/json" \
  -d '{
    "status": "failed",
    "message": [
      {
        "error_type": "network_timeout",
        "error_message": "Network timeout occurred"
      },
      {
        "error_type": "rate_limit_api",
        "error_message": "API rate limit exceeded",
        "retry_after": 60
      },
      {
        "error_type": "auth_invalid_key",
        "error_message": "Authentication failed"
      }
    ]
  }'
```

## 📊 Logs

Errors are logged with different levels of detail:

### Individual Logs (per error)
```
❌ Dify Error [1/3]: rate_limit_llm - Too many requests to LLM service
  {
    "task_id": 123,
    "task_type": "profile",
    "error_index": 1,
    "total_errors": 3,
    "original_type": "rate_limit_llm",
    "categorized_type": "rate_limit_llm",
    "is_whitelisted": true,
    "is_recoverable": true,
    "retry_after_seconds": 120,
    "service_name": "OpenAI GPT-4",
    "priority": 80
  }
```

### Summary Log
```
❌ Error Summary for task 123: 3 error(s) detected
  {
    "task_id": 123,
    "task_type": "profile",
    "error_count": 3,
    "whitelisted_count": 2,
    "recoverable_count": 2,
    "error_types": ["rate_limit_llm", "rate_limit_api", "auth_invalid_key"]
  }
```

### Callback Processing Log
```
✅ Error parsing complete for task 123
  {
    "task_id": 123,
    "error_count": 3,
    "primary_error_type": "auth_invalid_key",
    "has_whitelisted": true,
    "is_recoverable": false
  }
```

## 🔍 Debugging

### Enable Detailed Logs

In `.env` or `docker-compose.yml`:
```bash
LOG_LEVEL=DEBUG
```

This will enable technical details in API response:
```json
{
  "error_details": {
    "user_message": "...",
    "technical": {
      "errors": [
        {
          "type": "rate_limit_llm",
          "original_type": "rate_limit_llm",
          "message": "Too many requests..."
        }
      ]
    }
  }
}
```

### Check Whitelist

To check if error is in whitelist:
```python
from app.services.dify_error_handler import DifyErrorHandler

handler = DifyErrorHandler()
print("rate_limit_llm" in handler.whitelisted_types)  # True
print("custom_error" in handler.whitelisted_types)    # False
```

### Test Categorization

```python
from app.core.dify_error_config import ERROR_TYPE_MAPPING
from app.schemas.dify_errors import DifyErrorType

# Check mapping
print(ERROR_TYPE_MAPPING.get("rate_limit_llm"))  # DifyErrorType.RATE_LIMIT_LLM
print(ERROR_TYPE_MAPPING.get("unknown_type", DifyErrorType.UNKNOWN))  # DifyErrorType.UNKNOWN
```

## 📈 Metrics and Monitoring

Logs can be used for monitoring:

1. **Number of errors by type** - Count `categorized_type` in logs
2. **Whitelisted error rate** - Ratio `has_whitelisted=true`
3. **Recoverable vs non-recoverable errors** - Ratio `is_recoverable`
4. **Average retry time** - Average of `retry_after_seconds`

Example queries (if JSON logs):
```bash
# Count errors by type
cat backend.log | jq -r 'select(.categorized_type) | .categorized_type' | sort | uniq -c

# Whitelisted errors
cat backend.log | jq -r 'select(.has_whitelisted==true) | .task_id'
```

## 🌍 Internationalization (i18n)

### Supported Languages

- **English (en)** - Default language
- **French (fr)** - Full support

### Language Detection

The system automatically detects preferred language via:

1. **Query parameter** (highest priority): `?lang=fr` or `?lang=en`
2. **Accept-Language header**: Parses first language code (e.g., `fr-FR,fr;q=0.9` → `fr`)
3. **Default**: English (`en`) if no language detected or language unsupported

### Usage Examples

**Frontend (Fetch API):**
```javascript
// Via query param
fetch('/webhooks/dify/tasks/123/callback?lang=fr', { ... })

// Via Accept-Language header
fetch('/webhooks/dify/tasks/123/callback', {
  headers: {
    'Accept-Language': 'fr-FR',
    'Content-Type': 'application/json'
  },
  ...
})
```

**Backend (manual initialization):**
```python
from app.services.dify_error_handler import DifyErrorHandler

# English handler
handler_en = DifyErrorHandler(language="en")

# French handler
handler_fr = DifyErrorHandler(language="fr")

# Unsupported language falls back to English
handler_de = DifyErrorHandler(language="de")  # Will use "en"
```

## 🚀 Future Improvements

1. **Automatic Retry** - Implement automatic retry for recoverable errors
2. **Alerting** - Alert admin for authentication errors
3. **Global Rate Limit** - Track rate limits globally to prevent
4. **Error Cache** - Memorize recent errors for patterns
5. **More Languages** - Add support for Spanish, German, etc.
6. **Metrics API** - Endpoint exposing error stats

