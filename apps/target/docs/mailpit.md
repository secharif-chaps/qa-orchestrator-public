# Mailpit Configuration and Access

### Reminder

Always check that your global `.env.dist` file is the same than `.env`.

## Accessing Mailpit

Mailpit provides a web interface to view and test emails. You can access it at:

```
http://localhost:8025
```

The port can be customized through the `MAILPIT_PORT` environment variable (default: 8025).

## Default Configuration

Mailpit is configured with the following default settings:

- SMTP Port: 1025
- Web Interface Port: 8025
- Maximum Messages: 5000
- SMTP Authentication: Accepts any credentials
- Insecure SMTP Auth: Allowed

## Services Configured with Mailpit

The following services are configured to use Mailpit by default:

1. **Symfony Mailer**
   - Configuration: `MAILER_DSN=smtp://mailpit:1025`
   - Used by the Symfony application for sending emails

2. **Keycloak**
   - Host: `mailpit`
   - Port: `1025`
   - From: `noreply@basil.local`
   - From Display Name: `Basil Keycloak`

3. **n8n**
   - Host: `mailpit`
   - Port: `1025`
   - SSL: `false`
   - Sender: `${N8N_DEFAULT_EMAIL:-basil@chapsvision.com}` (configurable)

## Environment Variables

The following environment variables can be used to customize Mailpit:

- `MAILPIT_PORT`: Web interface port (default: 8025)
- `MP_MAX_MESSAGES`: Maximum number of messages to store (default: 5000)
- `MP_SMTP_AUTH_ACCEPT_ANY`: Accept any SMTP credentials (default: 1)
- `MP_SMTP_AUTH_ALLOW_INSECURE`: Allow insecure SMTP authentication (default: 1)

## Testing Emails

1. Any email sent through the configured services will be captured by Mailpit
2. Access the web interface at `http://localhost:8025` to view captured emails
3. Emails can be viewed, forwarded, or downloaded for testing purposes
