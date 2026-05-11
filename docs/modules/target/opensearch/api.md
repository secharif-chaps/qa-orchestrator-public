# OpenSearch API Integration

## Overview

This document explains how the API backend integrates with OpenSearch for search and analytics functionality. The integration follows the layered architecture principles with proper dependency injection and configuration management.

## Architecture

The API integrates with OpenSearch through several layers:

1. **Environment Configuration** - Connection details via environment variables
2. **Service Configuration** - Service definitions in `services.yaml`
3. **Client Factory** - Infrastructure layer factory for creating OpenSearch clients
4. **Application Usage** - Services and commands that use the OpenSearch client

## Configuration

### Environment Variables

The API uses the `OPENSEARCH_URL` environment variable for connection:

```bash
OPENSEARCH_URL=http://opensearch:9200
```

This URL includes:

- **Host**: `opensearch` (Docker service name)
- **Port**: `9200` (default OpenSearch port)

### Service Configuration (`opensearch.yaml`)

The OpenSearch client is configured as a service in `api/config/services/opensearch.yaml`:

```yaml
# Use client autoconfigured by API Platform
OpenSearch\Client: '@api_platform.elasticsearch.client'

App\Infrastructure\OpenSearch\State\CollectionProviderWithAggregations:
  arguments:
    $collectionExtensions: !tagged_iterator api_platform.elasticsearch.request_body_search_extension.collection
```

### API Platform Integration

API Platform is configured to use the same OpenSearch instance in `api/config/packages/api_platform.yaml`:

```yaml
api_platform:
  elasticsearch:
    hosts: ['%env(string:OPENSEARCH_URL)%']
    enabled: true
    client: opensearch
```

## Implementation

### Client Configuration

The API uses the `OpenSearch\Client` autoconfigured by API Platform, aliased in the service container. This setup:

- Leverages API Platform's built-in OpenSearch support
- Uses environment variables instead of hardcoded values
- Follows clean architecture principles

## Usage

### Dependency Injection

The OpenSearch client can be injected into any service or command that needs to interact with OpenSearch. This follows Symfony's dependency injection pattern and makes services easy to test and configure.

### Common Operations

The API can perform various OpenSearch operations including:

- **Search**: Query documents across indices
- **Cluster Management**: Get cluster health and information
- **Index Operations**: Create, update, and manage indices
- **Document Operations**: Index, update, and delete documents

All operations are handled through the injected client and follow consistent error handling patterns.

## Testing and Debugging

### Connection Testing Command

Use the provided command to test OpenSearch connectivity:

```bash
docker compose exec api php bin/console app:elasticsearch:check-connection
```

This command verifies:

- Connection to OpenSearch
- Cluster health and status
- Available indices
- Basic cluster information

### Common Issues and Solutions

#### 1. Connection Refused

- Check if OpenSearch service is running: `docker compose ps opensearch`
- Verify network connectivity between services
- Check firewall settings

#### 2. Authentication Errors

- Verify `OPENSEARCH_URL` contains correct credentials
- Check if OpenSearch security is properly configured
- Ensure the user has necessary permissions

#### 3. Type Assertion Errors

When using the OpenSearch client, handle the response types properly by separating the method calls and using type assertions for safety.

## Best Practices

### 1. Error Handling

Always wrap OpenSearch operations in try-catch blocks and log errors appropriately. This helps with debugging and provides better user experience when operations fail.

### 2. Connection Management

- The client is automatically managed by the service container
- No need to manually close connections
- The factory handles retry logic and connection pooling

### 3. Type Safety

- Handle response types properly with type assertions
- Use PHPStan for static analysis to catch type-related issues early

### 4. Configuration

- Keep connection details in environment variables
- Use the same configuration across all services
- Avoid hardcoding connection strings

## Related Documentation

- [OpenSearch and OpenSearch Dashboards Overview](./dashboards.md)
- [OpenSearch Security Setup](./security-setup.md)
- [API Platform Documentation](https://api-platform.com/docs/core/elasticsearch/)
- [OpenSearch Documentation](https://opensearch.org/docs/latest/)
