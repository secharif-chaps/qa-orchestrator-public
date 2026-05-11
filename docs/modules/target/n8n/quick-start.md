# N8N Quick Start Guide

Get your first N8N workflow running in Basil in under 5 minutes.

## Prerequisites

- Docker and Docker Compose installed
- Basil development environment set up
- Basic understanding of workflows and automation

## Step 1: Configure N8N (1 min)

Ensure N8N configuration is in your `.env` file:

```bash
# Copy from .env.dist if not present
N8N_SERVER_NAME=n8n.basil.local
N8N_DEFAULT_EMAIL=basil@chapsvision.com
N8N_DEFAULT_PASSWORD=Basil300425!
```

Add to `/etc/hosts`:

```text
127.0.0.1 n8n.basil.local
```

## Step 2: Start N8N (30 sec)

```bash
# Start N8N container
docker compose up -d n8n

# Verify it's running
docker compose ps n8n
```

Expected output:

```text
NAME      IMAGE     STATUS       PORTS
n8n       n8nio/n8n Up 10 seconds 0.0.0.0:5678->5678/tcp
```

## Step 3: Access N8N UI (10 sec)

Open your browser:

```bash
# Linux/Mac
open https://n8n.basil.local

# Windows
start https://n8n.basil.local
```

Login with credentials from `.env`:

- **Email**: `basil@chapsvision.com`
- **Password**: `Basil300425!`

## Step 4: Create Your First Workflow (3 min)

### Example: Simple RabbitMQ to API Workflow

1. **Click "New Workflow"** in N8N UI

2. **Add Trigger Node**
   - Search for "RabbitMQ Trigger"
   - Name it: `Trigger_RabbitMQ`
   - Configure:
     - Host: `rabbitmq`
     - Queue: `test_queue`
     - Exchange: `default`

3. **Add HTTP Request Node**
   - Search for "HTTP Request"
   - Name it: `HTTP_API_GetFolder`
   - Configure:
     - Method: `GET`
     - URL: `https://api/api/folders/{{ $json.folderId }}`

4. **Add Set Node**
   - Search for "Set"
   - Name it: `Output_Final`
   - Configure output fields

5. **Connect Nodes**
   - Drag from `Trigger_RabbitMQ` to `HTTP_API_GetFolder`
   - Drag from `HTTP_API_GetFolder` to `Output_Final`

6. **Save Workflow**
   - Click "Save" (top right)
   - Name it: `Test Workflow - RabbitMQ to API`

7. **Activate Workflow**
   - Toggle "Active" switch (top right)

### Test Your Workflow

```bash
# Publish test message to RabbitMQ
docker compose exec api php bin/console messenger:send-message test_queue '{"folderId": 1}'

# Check N8N executions (UI > Executions tab)
```

## Next Steps

### Learn Core Concepts

Understand N8N building blocks:

- **[Workflow Principles](./concepts.md)** - Triggers, nodes, connections
- **[Node Types](./concepts.md#node-types-in-basil)** - Available node categories
- **[Naming Convention](./naming-convention.md)** - Standard naming format

### Explore Existing Workflows

Study production workflows:

- **[Document Summary](./document-summary-workflow.md)** - AI-powered summaries
- **[Watchfile Classification](./watchfile-classification-workflow.md)** - Auto-classification
- **[AI Validation](../ai/ai-validation.md)** - Multi-stage validation

### Build Advanced Workflows

- **[Best Practices](./best-practices.md)** - Error handling, performance
- **[Workflow Testing](./workflow-testing.md)** - Test your workflows
- **[Workflow Validation](./workflow-validation.md)** - Validate workflow configuration

## Common First Workflow Patterns

### Pattern 1: Event → Process → Store

```text
Trigger_RabbitMQ
  → HTTP_API_GetData
  → LLM_Processor_Primary
  → Condition_IsValid
  → RabbitMQ_PublishResult
```

**Use cases**: Document processing, AI analysis, data enrichment

### Pattern 2: Schedule → Query → Notify

```text
Trigger_Schedule
  → DB_Documents_Query
  → Condition_HasNewItems
  → HTTP_API_SendNotification
```

**Use cases**: Daily reports, monitoring, scheduled tasks

### Pattern 3: Webhook → Validate → Response

```text
Trigger_Webhook
  → Condition_IsAuthorized
  → Agent_Processor_Main
  → Output_JSON
```

**Use cases**: External integrations, API endpoints, chat interfaces

## Troubleshooting

### N8N Won't Start

```bash
# Check logs
docker compose logs n8n

# Common issues:
# - Port 5678 already in use
# - Database connection failed
# - Missing environment variables
```

### Can't Access UI

```bash
# Verify hosts file
cat /etc/hosts | grep n8n.basil.local

# Check Traefik routing
docker compose logs traefik | grep n8n

# Regenerate certificates if needed
rm certs/n8n.basil.local*.pem
docker compose restart traefik
```

### Workflow Not Triggering

```bash
# Check workflow is active (UI toggle)
# Verify RabbitMQ connection
docker compose exec n8n n8n credentials:list

# Test with manual execution first
# (Click "Execute Workflow" in UI)
```

## Useful Commands

```bash
# View N8N logs
docker compose logs -f n8n

# Restart N8N
docker compose restart n8n

# Export all workflows
docker compose exec n8n n8n export:workflow --all --output=/data/backup.json

# Import workflows
docker compose exec n8n n8n import:workflow --input=/data/workflows.json

# List credentials
docker compose exec n8n n8n credentials:list

# Execute workflow via CLI
docker compose exec n8n n8n execute --id=<workflow-id>
```

## Best Practices from Day 1

✅ **Always use naming convention**: `Domain_Role_Action`
✅ **Add node notes**: Document business logic
✅ **Test with real data**: Use production-like test cases
✅ **Enable error workflows**: Catch and log failures
✅ **Export regularly**: Commit workflows to Git

## Resources

- **[N8N Official Docs](https://docs.n8n.io)** - Comprehensive N8N documentation
- **[Node Reference](https://docs.n8n.io/integrations/)** - All available nodes
- **[Expressions](https://docs.n8n.io/code/expressions/)** - Dynamic data manipulation
- **[Community Forum](https://community.n8n.io)** - Get help from community

## What's Next?

Now that you have N8N running:

1. **Study existing workflows** in `docker/n8n/workflows/`
2. **Read [Concepts](./concepts.md)** to understand workflow architecture
3. **Review [Best Practices](./best-practices.md)** before building complex workflows
4. **Check [Naming Convention](./naming-convention.md)** for standards

Happy automating! 🚀
