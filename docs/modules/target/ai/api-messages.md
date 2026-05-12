# Messages API Documentation

This document provides comprehensive information about the Messages API, including cursor-based pagination, filtering capabilities, and message structure.

## Overview

The Messages API allows you to retrieve messages from conversations using efficient cursor-based pagination. Messages are automatically ordered by creation date (newest first) and support real-time updates via Mercure.

## Endpoint

### Get Conversation Messages

```text
GET /api/conversations/{id}/messages
```

**Parameters:**

- `{id}` (path, required): The UUID of the conversation

**Description:** Retrieve all messages associated with a specific conversation using cursor-based pagination.

## Cursor-Based Pagination

The Messages API uses cursor-based pagination for efficient navigation through large message collections. This approach is particularly effective for chat applications where messages are frequently added and real-time updates are important.

### How Cursor Pagination Works

The cursor pagination implementation uses a **filter-based approach** rather than traditional cursor tokens:

1. **Ordering:** Messages are always ordered by `createdAt` in descending order (newest first)
2. **Cursor Mechanism:** The cursor is implemented using the `createdAt[before]` filter
3. **Pagination Strategy:** Each page request uses the `createdAt` timestamp of the last message from the previous page as the cursor
4. **Default Items:** 30 items per page (configurable)

### Pagination Parameters

| Parameter           | Type    | Description                                        | Default |
| ------------------- | ------- | -------------------------------------------------- | ------- |
| `itemsPerPage`      | integer | Number of items per page                           | 30      |
| `order[createdAt]`  | string  | Sort order (`desc` or `asc`)                       | `desc`  |
| `createdAt[before]` | string  | Cursor filter - get messages before this timestamp | -       |

### How to Implement Cursor Pagination

1. **First Page:** Make a request without any cursor filter
2. **Subsequent Pages:** Use the `createdAt` timestamp of the last message from the previous page as the `createdAt[before]` filter
3. **Continue:** Repeat until no more messages are returned

### Example Usage

```bash
# First page - no cursor filter
GET /api/conversations/550e8400-e29b-41d4-a716-446655440000/messages?order[createdAt]=desc&itemsPerPage=30

# Second page - using timestamp from last message of first page
GET /api/conversations/550e8400-e29b-41d4-a716-446655440000/messages?order[createdAt]=desc&itemsPerPage=30&createdAt[before]=2024-01-15T10:30:00+00:00

# Third page - using timestamp from last message of second page
GET /api/conversations/550e8400-e29b-41d4-a716-446655440000/messages?order[createdAt]=desc&itemsPerPage=30&createdAt[before]=2024-01-15T09:15:00+00:00
```

### Response Structure

```json
{
  "@context": "/api/contexts/Message",
  "@id": "/api/conversations/{id}/messages",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/messages/{messageId}",
      "@type": "Message",
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "role": "user",
      "contents": [
        {
          "@id": "/api/message-contents/{contentId}",
          "@type": "TextContent",
          "id": "660e8400-e29b-41d4-a716-446655440001",
          "type": "text",
          "content": "Hello, how are you?",
          "createdAt": "2024-01-15T10:30:00+00:00"
        }
      ],
      "metadata": null,
      "createdAt": "2024-01-15T10:30:00+00:00",
      "createdBy": {
        "@id": "/api/users/{userId}",
        "@type": "User",
        "id": "770e8400-e29b-41d4-a716-446655440002",
        "email": "user@example.com"
      }
    }
  ]
}
```

**Note:** Unlike traditional cursor pagination, this implementation doesn't return `hydra:view` with `next`/`previous` links. Instead, you need to implement the pagination logic in your client code using the `createdAt[before]` filter.

## Filtering

The Messages API supports various filtering options to help you retrieve specific messages.

### Date Filters

Filter messages by creation date using the `createdAt` parameter:

| Filter                       | Description                                            | Example                                 |
| ---------------------------- | ------------------------------------------------------ | --------------------------------------- |
| `createdAt[after]`           | Messages created after the specified date (inclusive)  | `createdAt[after]=2024-01-01`           |
| `createdAt[before]`          | Messages created before the specified date (inclusive) | `createdAt[before]=2024-01-31`          |
| `createdAt[strictly_after]`  | Messages created after the specified date (exclusive)  | `createdAt[strictly_after]=2024-01-01`  |
| `createdAt[strictly_before]` | Messages created before the specified date (exclusive) | `createdAt[strictly_before]=2024-01-31` |

### Date Format

Dates should be provided in ISO 8601 format:

- `YYYY-MM-DD` for date only
- `YYYY-MM-DDTHH:MM:SS+00:00` for date and time

### Example Filtering

```bash
# Get messages from the last 7 days
GET /api/conversations/{id}/messages?createdAt[after]=2024-01-08

# Get messages from a specific date range
GET /api/conversations/{id}/messages?createdAt[after]=2024-01-01&createdAt[before]=2024-01-31

# Get messages from a specific time period
GET /api/conversations/{id}/messages?createdAt[after]=2024-01-15T10:00:00+00:00&createdAt[before]=2024-01-15T18:00:00+00:00
```

## Message Structure

### Message Object

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "role": "user",
  "contents": [...],
  "metadata": null,
  "createdAt": "2024-01-15T10:30:00+00:00",
  "createdBy": {...}
}
```

### Message Properties

| Property    | Type              | Description                      |
| ----------- | ----------------- | -------------------------------- |
| `id`        | string (UUID)     | Unique message identifier        |
| `role`      | string            | Message role (`user` or `model`) |
| `contents`  | array             | Array of message content objects |
| `metadata`  | object/null       | Additional message metadata      |
| `createdAt` | string (ISO 8601) | Message creation timestamp       |
| `createdBy` | object            | User who created the message     |

### Message Roles

- **`user`**: Messages sent by the user
- **`model`**: Messages generated by the AI model

### Content Types

Messages can contain multiple content objects of different types:

#### Text Content

```json
{
  "id": "660e8400-e29b-41d4-a716-446655440001",
  "type": "text",
  "content": "Hello, how are you?",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

#### File Content

```json
{
  "id": "660e8400-e29b-41d4-a716-446655440002",
  "type": "file",
  "filename": "document.pdf",
  "mimeType": "application/pdf",
  "size": 1024000,
  "path": "/uploads/documents/document.pdf",
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

#### Function Call Content

```json
{
  "id": "660e8400-e29b-41d4-a716-446655440003",
  "type": "function_call",
  "functionName": "search_web",
  "parameters": {
    "query": "latest news",
    "count": 5
  },
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

#### Function Response Content

```json
{
  "id": "660e8400-e29b-41d4-a716-446655440004",
  "type": "function_response",
  "result": {
    "results": [
      {
        "title": "Latest News Article",
        "url": "https://example.com/news",
        "snippet": "Breaking news..."
      }
    ]
  },
  "createdAt": "2024-01-15T10:30:00+00:00"
}
```

## Real-Time Updates

The Messages API supports real-time updates via Mercure. When you make a request, check for the `Link` header containing `rel="mercure"` to subscribe to real-time updates.

## Error Handling

### Common HTTP Status Codes

| Status Code | Description                            |
| ----------- | -------------------------------------- |
| 200         | Success                                |
| 400         | Bad Request (invalid parameters)       |
| 401         | Unauthorized (authentication required) |
| 403         | Forbidden (insufficient permissions)   |
| 404         | Not Found (conversation not found)     |
| 500         | Internal Server Error                  |

### Error Response Format

```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10",
  "title": "An error occurred",
  "detail": "Invalid conversation ID format",
  "trace": [...]
}
```

## Best Practices

### Pagination

1. **Use the `createdAt[before]` filter approach** for efficient cursor-based pagination
2. **Always order by `createdAt` descending** to get newest messages first
3. **Track the last message timestamp** from each page to use as the cursor for the next page
4. **Set appropriate page sizes** based on your use case (10-50 items typically work well)
5. **Handle empty responses** to determine when you've reached the end of the message history
6. **Cache timestamps** when possible to improve performance and enable backward navigation

### Filtering

1. **Use date filters** to limit the scope of messages when you don't need the entire history
2. **Combine filters** with pagination for optimal performance
3. **Validate date formats** before sending requests

### Real-Time Updates

1. **Subscribe to Mercure updates** for live message notifications
2. **Handle connection errors** and implement reconnection logic
3. **Clean up EventSource connections** when components unmount

### Performance

1. **Limit the number of concurrent requests** to avoid overwhelming the server
2. **Use appropriate page sizes** to balance between performance and user experience
3. **Implement proper caching** for frequently accessed conversations
4. **Monitor API usage** and implement rate limiting if needed

This documentation provides a comprehensive guide to using the Messages API with cursor-based pagination, filtering, and real-time updates. The API is designed to be efficient and scalable for chat applications with large message histories.
