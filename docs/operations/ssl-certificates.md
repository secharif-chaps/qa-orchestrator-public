# Self-Signed SSL Certificates for Basil

This documentation explains how the self-signed SSL certificate generation system works in the Basil project, how to use them, and how to customize them.

## Overview

The Basil project uses a self-signed SSL certificate system to enable local HTTPS development. The `certs/self-signed-generator.sh` script automates the creation of a local Certificate Authority (CA) and the generation of certificates for project domains.

### Certificate Architecture

The system generates two types of certificates:

1. **Certificate Authority (CA) Certificate**:
   - `ca-key.pem`: CA private key
   - `ca-cert.pem`: CA certificate (valid for 5 years)
   - `ca-cert.crt`: Copy of CA certificate in CRT format for Windows

2. **Domain Certificate**:
   - `{domain}-key.pem`: Domain private key
   - `{domain}-cert.pem`: Domain certificate (valid for 1 year)

## Default Configuration

### Configured Domains

By default, certificates are generated for the following domains:

- **Primary domain**: `basil.local`
- **Aliases (SAN)**:
  - `www.basil.local` - WWW redirect
  - `auth.basil.local` - Keycloak server
  - `n8n.basil.local` - N8N automation

### Organization Information

Certificates use the following organization information:

- **Country**: FR (France)
- **State/Province**: Occitanie
- **City**: Montpellier
- **Organization**: ChapsVision
- **Organizational Unit**: Basil

## Certificate Generation

### Prerequisites

- **OpenSSL** must be installed and accessible in PATH
- **Bash** (available on Unix/Linux/macOS and WSL on Windows)

### Automatic Generation

```bash
# Make the script executable
chmod +x ./certs/self-signed-generator.sh

# Generate certificates
./certs/self-signed-generator.sh
```

### Generation Process

The script follows these steps:

1. **Prerequisites Check**:
   - Verify OpenSSL presence
   - Validate environment variables

2. **CA Generation** (if it doesn't exist):

   ```bash
   # Generate RSA private key
   openssl genpkey -algorithm RSA -out ca-key.pem

   # Create self-signed CA certificate (5 years)
   openssl req -x509 -new -nodes -key ca-key.pem -sha256 -days 1825 -out ca-cert.pem
   ```

3. **Domain Certificate Generation**:

   ```bash
   # Generate domain private key
   openssl genpkey -algorithm RSA -out basil.local-key.pem

   # Create CSR with SAN extensions
   openssl req -new -key basil.local-key.pem -out basil.local.csr -config san.conf

   # Sign certificate with CA (1 year)
   openssl x509 -req -in basil.local.csr -CA ca-cert.pem -CAkey ca-key.pem -out basil.local-cert.pem -days 365
   ```

4. **SAN Configuration (Subject Alternative Names)**:
   - The script automatically generates a temporary OpenSSL configuration
   - Includes the primary domain and all aliases
   - Allows multiple domains to be used with a single certificate

5. **Cleanup**:
   - Remove temporary files (CSR, SAN configuration)
   - Keep only final certificates and keys

## Certificate Installation and Configuration

### 1. Local DNS Configuration

Add domains to your hosts file:

**Windows** (`C:\Windows\System32\drivers\etc\hosts`):

```txt
127.0.0.1 basil.local www.basil.local auth.basil.local n8n.basil.local
```

**macOS/Linux** (`/etc/hosts`):

```txt
127.0.0.1 basil.local www.basil.local auth.basil.local n8n.basil.local
```

### 2. Installing CA Certificate in Browser

To avoid security warnings, add the CA certificate (`certs/ca-cert.pem`) to your browser's trusted certificate authorities:

#### Chrome / Edge

1. Settings → Privacy and Security → Security
2. Manage certificates → Authorities
3. Import → Select `certs/ca-cert.pem`
4. Check "Trust this certificate for identifying websites"

#### Firefox

1. Settings → Privacy & Security
2. View Certificates → Authorities
3. Import → Select `certs/ca-cert.pem`
4. Check "Trust this CA to identify websites"

#### System Installation (recommended)

**Windows**:

```cmd
# Run as administrator
certmgr.msc
```

1. Navigate to "Trusted Root Certification Authorities" → "Certificates"
2. Right-click → "All Tasks" → "Import"
3. Select `certs/ca-cert.pem`

**macOS**:

```bash
# Add to system keychain
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain certs/ca-cert.pem
```

**Linux (Ubuntu/Debian)**:

```bash
# Copy certificate
sudo cp certs/ca-cert.pem /usr/local/share/ca-certificates/basil-ca.crt

# Update certificates
sudo update-ca-certificates
```

### 3. Installation Verification

Test that certificates work:

```bash
# Check certificate
openssl x509 -in certs/basil.local-cert.pem -text -noout

# Test HTTPS connection
curl -I https://basil.local

# Verify in browser
# → No certificate errors should appear
```

## Custom Configuration

### Modifying Domains

To use custom domains, create a configuration file:

```bash
# Copy configuration template
cp certs/.env.dist certs/.env
```

Edit `certs/.env`:

```env
# Primary domain (required)
PRIMARY_DOMAIN=myproject.local

# Additional aliases (optional)
DOMAIN_ALIASES=www.myproject.local,api.myproject.local,admin.myproject.local
```

### Configuration Variables

| Variable         | Description                     | Default Value                                      |
| ---------------- | ------------------------------- | -------------------------------------------------- |
| `PRIMARY_DOMAIN` | Primary domain for certificate  | `basil.local`                                      |
| `DOMAIN_ALIASES` | Comma-separated list of aliases | `www.basil.local,auth.basil.local,n8n.basil.local` |

### Domain Validation

The script validates that:

- `PRIMARY_DOMAIN` contains only alphanumeric characters, dots, and hyphens
- `DOMAIN_ALIASES` follows the same format and uses commas as separators
- No domain contains dangerous special characters

## Adding a New Alias

### Method 1: Configuration Modification

1. **Edit the configuration**:

   ```bash
   nano certs/.env
   ```

2. **Add the new domain**:

   ```env
   DOMAIN_ALIASES=www.basil.local,auth.basil.local,n8n.basil.local,new.basil.local
   ```

3. **Remove old certificates**:

   ```bash
   rm certs/basil.local-cert.pem certs/basil.local-key.pem
   ```

4. **Regenerate certificates**:

   ```bash
   ./certs/self-signed-generator.sh
   ```

5. **Update your hosts file**:

   ```txt
   127.0.0.1 basil.local www.basil.local auth.basil.local n8n.basil.local new.basil.local
   ```

### Method 2: Temporary Environment Variables

```bash
# Temporarily define new domains
export PRIMARY_DOMAIN="basil.local"
export DOMAIN_ALIASES="www.basil.local,auth.basil.local,n8n.basil.local,new.basil.local"

# Remove old certificates
rm certs/basil.local-cert.pem certs/basil.local-key.pem

# Regenerate
./certs/self-signed-generator.sh
```

### Verifying the New Alias

```bash
# Check that the new domain is in the certificate
openssl x509 -in certs/basil.local-cert.pem -text -noout | grep -A 10 "Subject Alternative Name"

# Test connection
curl -I https://new.basil.local
```

## Usage in Docker

### Automatic Configuration

Certificates are automatically mounted in Docker containers via `docker-compose.yml`:

```yaml
volumes:
  - ./certs/ca-cert.pem:/usr/local/share/ca-certificates/basil-ca.crt:ro
  - ./certs/basil.local-cert.pem:/etc/ssl/certs/basil.pem:ro
  - ./certs/basil.local-key.pem:/etc/ssl/private/basil.key:ro
```

## Troubleshooting

### Common Errors

#### 1. "openssl command not found"

**Solution**:

```bash
# Ubuntu/Debian
sudo apt-get install openssl

# macOS
brew install openssl

# Windows
# Install OpenSSL or use WSL
```

#### 2. "Permission denied" during execution

**Solution**:

```bash
chmod +x certs/self-signed-generator.sh
```

#### 3. Certificate not recognized by browser

**Solutions**:

1. Check that the CA certificate is properly installed
2. Restart browser after installation
3. Verify that the domain matches exactly (with/without www)
4. Check developer tools for specific errors

#### 4. "NET::ERR_CERT_COMMON_NAME_INVALID"

This error indicates a domain matching problem:

**Solutions**:

1. Check that the domain is included in `PRIMARY_DOMAIN` or `DOMAIN_ALIASES`
2. Regenerate the certificate with the correct domain
3. Check your hosts file

### Debugging

#### Check Certificate Content

```bash
# Display certificate details
openssl x509 -in certs/basil.local-cert.pem -text -noout

# Check validity
openssl x509 -in certs/basil.local-cert.pem -noout -dates

# Check SAN
openssl x509 -in certs/basil.local-cert.pem -text -noout | grep -A 10 "Subject Alternative Name"
```

#### Test Certificate Chain

```bash
# Verify that certificate is signed by CA
openssl verify -CAfile certs/ca-cert.pem certs/basil.local-cert.pem

# Test SSL connection
openssl s_client -connect basil.local:443 -CAfile certs/ca-cert.pem
```

#### Docker Logs

```bash
# Check web server logs
docker-compose logs nginx

# Check application logs
docker-compose logs api
```

## Security and Best Practices

### Private Key Protection

Private keys are automatically protected with appropriate permissions:

```bash
# Permissions automatically applied by the script
chmod 600 certs/ca-key.pem
chmod 600 certs/basil.local-key.pem
chmod 644 certs/ca-cert.pem
chmod 644 certs/basil.local-cert.pem
```

### Version Control Exclusion

The `.gitignore` file automatically excludes private keys:

```gitignore
# SSL Certificates
certs/*.pem
certs/*.key
certs/*.csr
certs/*.crt
!certs/.env.dist
```

### Regular Renewal

To maintain optimal security:

1. **Renew certificates** before they expire (1 year)
2. **Regenerate CA** every 2-3 years
3. **Remove old certificates** from trust store during renewal

### Development Environment Only

⚠️ **Important**: These self-signed certificates should **never** be used in production. They are intended for local development only.

For production, use:

- **Let's Encrypt** for free certificates
- **Commercial SSL certificates** for advanced needs
- **Enterprise certificates** if your organization provides them

## Integration with Development Environment

### Project Environment Variables

Domains configured in `certs/.env` must match variables in the main project `.env`:

```env
# .env (project root)
SERVER_NAME=basil.local,www.basil.local
TRUSTED_HOSTS=^(basil\.local|www\.basil\.local)$
KEYCLOAK_SERVER_NAME=auth.basil.local
```

### Automatic Synchronization

To maintain consistency between certificates and application configuration, create a synchronization script:

```bash
#!/bin/bash
# sync-domains.sh

# Read certificate configuration
source certs/.env

# Update main .env
sed -i "s/SERVER_NAME=.*/SERVER_NAME=${PRIMARY_DOMAIN},${DOMAIN_ALIASES}/" .env

# Restart services
docker-compose restart
```

This documentation covers all aspects of managing self-signed SSL certificates in Basil. For any specific questions or issues, check system logs or create a ticket on the project.
