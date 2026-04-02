# OpenSearch and OpenSearch Dashboards Documentation

## Reminder

Always check that your global `.env.dist` file is the same than `.env`.

## Environment Configuration

Ensure your root `.env` file (used by Docker Compose) includes the following OpenSearch Dashboards-related environment variables:

```env
OPENSEARCH_HOST=opensearch
OPENSEARCH_PORT=9200
OPENSEARCH_PASSWORD=Basil300425!
OPENSEARCH_DASHBOARDS_SERVER_NAME=opensearch-dashboards.basil.local
```

These variables are required for the new OpenSearch Dashboards setup to work properly.

## Overview

OpenSearch and OpenSearch Dashboards are powerful tools that work together to provide robust search, analytics, and visualization capabilities for our application.

## Accessing OpenSearch Dashboards

OpenSearch Dashboards is now accessed via HTTPS at: https://opensearch-dashboards.basil.local

**Important**: Access to OpenSearch Dashboards requires an auto-generated token service. The system automatically generates and manages authentication tokens for secure access to the OpenSearch Dashboards interface.

OpenSearch is not exposed externally and is only accessible within the internal Docker network.

## Why OpenSearch?

OpenSearch is a distributed, RESTful search and analytics engine that provides:

1. **Full-Text Search**: Advanced search capabilities across all types of data
2. **Real-time Analytics**: Instant insights from your data
3. **Scalability**: Horizontal scaling to handle growing data volumes
4. **High Availability**: Built-in replication and failover mechanisms
5. **Schema Flexibility**: Dynamic mapping for different data types
6. **RESTful API**: Easy integration with various applications

## Why OpenSearch Dashboards?

OpenSearch Dashboards is the visualization and management interface for OpenSearch that offers:

1. **Data Visualization**: Create interactive charts, graphs, and dashboards
2. **Data Exploration**: Intuitive interface to explore and analyze data
3. **Monitoring**: Real-time monitoring of OpenSearch clusters
4. **Management Tools**: User-friendly tools for managing indices and data
5. **Custom Dashboards**: Create and share custom dashboards for different use cases

## Troubleshooting

### Hosts File Configuration

Make sure to add `opensearch-dashboards.basil.local` to your machine's hosts file to resolve the domain name locally:

**Linux/macOS**: Edit `/etc/hosts`
**Windows**: Edit `C:\Windows\System32\drivers\etc\hosts`

Add this line:

```
127.0.0.1 opensearch-dashboards.basil.local
```

### Certificate Generation

When generating SSL certificates for the application, ensure that `opensearch-dashboards.basil.local` is included in the certificate's Subject Alternative Names (SAN) or Common Name (CN). The certificate generation process must account for this domain to avoid SSL/TLS connection errors.

If you encounter SSL certificate errors when accessing OpenSearch Dashboards, verify that:

1. The certificate includes `opensearch-dashboards.basil.local` in its allowed domains
2. The certificate is properly installed and trusted by your system
3. The certificate generation process has been run with the correct domain configuration
