# OpenSearch Security Setup with OpenSearch Dashboards

This document explains how to set up secure OpenSearch with OpenSearch Dashboards in the Docker Compose environment.

## Overview

The setup enables security features in OpenSearch and configures OpenSearch Dashboards to connect securely using HTTPS.

## Environment Variables

Add the following environment variables to your `.env` file:

```bash
# OpenSearch Security Configuration
OPENSEARCH_VERSION=2.19.0
OPENSEARCH_HOST=opensearch
OPENSEARCH_PORT=9200
OPENSEARCH_PASSWORD=!ChangeMeOpenSearchPassword!

# OpenSearch Dashboards Configuration
OPENSEARCH_DASHBOARDS_PORT=5601
```

## Initial Setup Process

### 1. Start OpenSearch with Security Enabled

When you first start OpenSearch with security enabled, it will:

- Generate a random password for the `admin` user
- Create security configurations for OpenSearch Dashboards and other services
- Display these credentials in the container logs
- Generate SSL certificates in `/usr/share/opensearch/config/certs/`

**Important**: The health check initially uses HTTP to allow OpenSearch to start up and generate certificates. Once certificates are available, the service will be marked as healthy.

### 2. Get the Admin User Password

```bash
# View the generated password in the OpenSearch logs
docker compose logs opensearch

# Or update the password via the security configuration
docker compose exec opensearch /usr/share/opensearch/plugins/opensearch-security/tools/securityadmin.sh
```

### 3. Startup Sequence

The services start in this order:

1. **OpenSearch** starts and begins generating certificates
2. **Health check** passes once OpenSearch responds on HTTP
3. **OpenSearch Dashboards** starts and waits for OpenSearch to be healthy
4. **Certificates** are generated and available for secure connections
5. **OpenSearch Dashboards** connects securely using the generated certificates

## Connecting to OpenSearch Dashboards

### 1. Access OpenSearch Dashboards

Navigate to `http://localhost:5601` (or your configured `OPENSEARCH_DASHBOARDS_PORT`)

### 2. Login

Use the `admin` user with the password configured for OpenSearch.

## Security Features Enabled

- **Security Plugin**: Authentication and authorization
- **TLS/SSL**: Encrypted communication between services
- **User Management**: Built-in user accounts and roles
- **Role-Based Access Control**: Fine-grained permissions

## Troubleshooting

### OpenSearch Dashboards Connection Issues

If OpenSearch Dashboards cannot connect to OpenSearch:

1. Verify OpenSearch is running and healthy:

   ```bash
   docker compose ps opensearch
   ```

2. Check OpenSearch logs:

   ```bash
   docker compose logs opensearch
   ```

3. Verify the security configuration is correct:
   ```bash
   docker compose exec opensearch curl -k -u admin:password https://localhost:9200
   ```

### Certificate Issues

The setup uses OpenSearch's auto-generated certificates. If you encounter certificate issues:

1. Ensure the volume mount is correct in `compose.override.yaml`
2. Check that OpenSearch has generated the certificates
3. Verify the SSL verification settings in OpenSearch Dashboards

## Production Considerations

For production environments:

1. **Change Default Passwords**: Update `OPENSEARCH_PASSWORD` to a strong, unique password
2. **Custom Certificates**: Consider using your own CA and certificates
3. **Network Security**: Restrict access to OpenSearch and OpenSearch Dashboards ports
4. **Backup**: Regularly backup the `opensearch_data` volume
5. **Monitoring**: Set up proper logging and monitoring for security events

## References

- [OpenSearch Docker Installation](https://opensearch.org/docs/latest/install-and-configure/install-opensearch/docker/)
- [OpenSearch Dashboards Docker Installation](https://opensearch.org/docs/latest/install-and-configure/install-dashboards/docker/)
- [OpenSearch Security](https://opensearch.org/docs/latest/security/)
