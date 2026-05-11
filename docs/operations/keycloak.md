# Keycloak Developer Guide

## Overview

Basil uses Keycloak as its identity and access management solution. Keycloak provides:

- **Single Sign-On (SSO)** - Users can authenticate once and access all Basil services
- **Multi-tenant architecture** - Each client organization has its own realm
- **Role-based access control** - Different permission levels for users
- **Identity federation** - Integration with enterprise SSO systems

### Architecture

```text
Keycloak
├── Master Realm (Admin access)
├── Basil Development Realm (Local development)
└── Client Realms (Production tenants)
```

### Key Components

- **Realm**: Isolated space for managing users, clients, and roles
- **Client**: Application that can request authentication (Basil frontend/backend)
- **User**: Individual with access to the system
- **Role**: Permission level assigned to users

## Accessing Keycloak

### Development Environment

When running the development stack with Docker Compose, Keycloak is available at:

```text
https://auth.basil.local/admin/
```

### Admin Console Access

To access the Keycloak admin console:

1. Navigate to `https://auth.basil.local/admin/`
2. Use the admin credentials (see below)

### Switching Between Realms

- **Master Realm**: Administrative access to all realms
- **Chapsmind-dev Realm**: Development environment for testing
- Use the realm dropdown in the top-left corner to switch between realms

## Default Login for Developers

### Admin Account (Master Realm)

For full administrative access:

```text
Username: basil
Password: Basil300425!
```

> ⚠️ **Security Note**: Change the default admin password in production environments

### Development Test Users (chapsmind-dev Realm)

For testing the application, multiple users are available across different organizations:

#### ChapsVision Organization Users

| Username        | Email                             | Role           |
| --------------- | --------------------------------- | -------------- |
| basil           | <basil@chapsvision.com>           | Admin, Support |
| alice.martin    | <alice.martin@chapsvision.com>    | Support        |
| bob.smith       | <bob.smith@chapsvision.com>       | User           |
| charlie.johnson | <charlie.johnson@chapsvision.com> | User           |
| diana.williams  | <diana.williams@chapsvision.com>  | User           |
| ethan.brown     | <ethan.brown@chapsvision.com>     | User           |
| fiona.jones     | <fiona.jones@chapsvision.com>     | User           |
| george.garcia   | <george.garcia@chapsvision.com>   | User           |
| hannah.martinez | <hannah.martinez@chapsvision.com> | User           |
| ian.lopez       | <ian.lopez@chapsvision.com>       | User           |
| julia.gonzalez  | <julia.gonzalez@chapsvision.com>  | User           |

#### Acme Corp Organization Users

| Username    | Email                       |
| ----------- | --------------------------- |
| john.doe    | <john.doe@acme-corp.com>    |
| jane.smith  | <jane.smith@acme-corp.com>  |
| mike.wilson | <mike.wilson@acme-corp.com> |

#### TechStart Organization Users

| Username    | Email                      |
| ----------- | -------------------------- |
| emma.davis  | <emma.davis@techstart.io>  |
| liam.taylor | <liam.taylor@techstart.io> |

#### Special Test Users

| Username     | Email                         | Purpose                                              |
| ------------ | ----------------------------- | ---------------------------------------------------- |
| sarah.connor | <sarah.connor@consultant.com> | Multi-org user (member of all 3 orgs), Support group |
| orphan.user  | <orphan@external.com>         | User without organization (edge case testing)        |

**All users share the same password:** `Basil300425!`

## Organizations

The development realm has organizations enabled for multi-tenant testing. Each organization can have its own identity providers, domains, and members.

### Available Organizations

| Organization | Domain          | Identity Provider(s)      | Description                 |
| ------------ | --------------- | ------------------------- | --------------------------- |
| chapsvision  | chapsvision.com | OIDC SSO, SAML Enterprise | Main organization           |
| acme-corp    | acme-corp.com   | Microsoft                 | Corporate test organization |
| techstart    | techstart.io    | Google                    | Startup test organization   |

### Test Identity Providers

These are fictional IdPs for testing the SSO flows:

| Alias                | Type            | Linked Organization |
| -------------------- | --------------- | ------------------- |
| google-test          | Google OAuth    | TechStart           |
| microsoft-test       | Microsoft OAuth | Acme Corp           |
| saml-enterprise-test | SAML 2.0        | ChapsVision         |
| oidc-chapsvision-sso | OpenID Connect  | ChapsVision         |

> **Note**: These IdPs have placeholder credentials and won't work for actual authentication. They are useful for testing UI flows and organization selection.

## Groups and Permissions

### Support Group

The `support` group is designed for internal support staff and consultants who need administrative access without full realm admin privileges.

**Members**: basil, alice.martin, sarah.connor

**Permissions**:

- Manage users, groups, and identity providers
- View events, realm settings, and clients (read-only)
- Impersonate users for troubleshooting

### Environment Variables

Ensure these variables are set in your `.env` file:

```bash
KEYCLOAK_SERVER_NAME=auth.basil.local
```

## Production Update Policy

We use the community version of Keycloak provided by the ChapsVision forge, which requires regular updates to maintain security.

The Keycloak version installed in production must be at minimum **"LatestMinor -1"**.

In case of exploitable vulnerabilities affecting the production version:

- **Critical severity**: update within 15 days
- **High severity**: update within 1 month
- **Medium severity**: update within 2 months

> 📖 **Complete documentation**: [Keycloak Update Policy](https://forge.deverywa.re/deverydoc/projets/keycloak/doc/politique_de_maj.html)

### Troubleshooting

**Common Issues:**

- **Keycloak not accessible**: Check if the container is running with `docker compose ps`

**Reset Development Data:**

```bash
docker compose down -v
docker compose up -d
```

This will reset Keycloak to its initial state with default users.

## Theme Customization

Basil includes a custom Keycloak theme that provides branded login pages and UI components.

### Development

For local development, the theme is mounted directly from the `docker/keycloak/chapsmind-theme/` directory into the Keycloak container. Changes to theme files are reflected immediately without needing to restart Keycloak.

### Production Deployment

For production environments, themes are packaged as JAR archives through the CI/CD pipeline:

- **Automatic Building**: The `build-keycloak-theme` CI job creates versioned JAR packages
- **Package Registry**: Theme packages are stored in GitLab's package registry
- **Production Deployment**: Download and deploy JAR files to Keycloak's providers directory

For detailed information about theme development and deployment, see the [Keycloak Theme Documentation](./keycloak-theme.md).
