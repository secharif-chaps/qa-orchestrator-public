# N8N Testing Framework - Environment Update Guide

This guide describes how to update your development environment to support the new N8N workflow testing and validation framework.

## What's New

The testing framework adds:

- **Automated workflow testing** with JSON datasets
- **Static validation** of workflow configuration (naming, RabbitMQ messages, agents)
- **Pre-commit hooks** for automatic validation
- **CI/CD integration** for continuous testing

## Update Steps

Follow these steps in order to update your environment:

### 1. Build the DevTools Container

The validation framework requires the `devtools` container with PHP and Python dependencies:

```bash
docker compose --profile devtools build devtools
```

**Why:** The validation scripts (`api/tests/N8N/validate-n8n-workflows.php`) run in the devtools container.

### 2. Pull Latest N8N Image

Update to the latest N8N version:

```bash
docker compose pull n8n
```

**Why:** to take advantage of the new Agent and LLM node update, and because the tester requires access to the N8N API and specific npm packages.

### 3. Restart N8N Service

Apply the new configuration:

```bash
docker compose up -d --force-recreate n8n
```

### 4. Install Test Dependencies

Install Node.js packages for the test runner:

```bash
task n8n:test:setup
```

This command runs:

```bash
docker compose exec n8n npm --prefix /tests install
```

**What it installs:**

- `axios` - HTTP client for N8N API calls
- `diff` - Snapshot comparison
- `json-diff` - JSON structure comparison
- `ajv` - JSON Schema validation

see: `docker/tests/package.json` for full list

### 5. Create N8N API Key

The test framework needs an API key to trigger workflows:

1. **Access N8N UI:**

    ```
    https://n8n.basil.local
    ```

2. **Navigate to Settings:**
    - Click your profile (bottom left)
    - Go to **Settings** → **API**

3. **Create API Key:**
    - Click **"Create an API key"**
    - Name it: `Test Runner`
    - Copy the generated key

4. **Add to Environment:**

    Edit your `.env` file:

    ```bash
    # N8N Testing
    N8N_API_KEY=n8napi_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    ```

### 6. Restart N8N Again

Apply the API key configuration:

```bash
docker compose up -d --force-recreate n8n
```

**Why:** The `N8N_API_KEY` environment variable must be loaded by the n8n container.

### 7. Verify Installation

Run all validation checks:

```bash
task n8n:validate
```

Expected output:

```
🔍 Validating workflows in docker/n8n/workflows

✅ N8N Naming Convention: All checks passed!
✅ N8N Agent Configuration: All checks passed!
✅ N8N Feedback Node Configuration: All checks passed!
✅ N8N RabbitMQ Message Format: All checks passed!
✅ N8N Call Workflow Tool Configuration: All checks passed!
✅ N8N Execute Sub-workflow Configuration: All checks passed!

🎉 All validations passed!
```

### 8. Test a Workflow

Run a test to verify the complete setup:

```bash
task n8n:test -- reference-subject-workflow-evaluation.json --verbose
```

Expected output:

```
✅ All tests passed (5/5)
```

## Troubleshooting

### API Key Not Working

**Symptoms:**

- `401 Unauthorized` errors
- Tests fail with authentication errors

**Solutions:**

1. Verify the API key
2. Check the key is in `.env`: `grep N8N_API_KEY .env`
3. Restart n8n: `docker compose up -d --force-receate n8n`
4. Regenerate the key in N8N UI if needed

### DevTools Container Not Found

**Symptoms:**

- `task n8n:validate` fails with "service devtools not found"

**Solutions:**

```bash
# Build with the devtools profile
docker compose --profile devtools build devtools

# Verify it exists
docker compose --profile devtools ps devtools
```

### Test Dependencies Missing

**Symptoms:**

- `Cannot find module 'axios'` errors
- Test runner fails to start

**Solutions:**

```bash
# Reinstall test dependencies
task n8n:test:setup

# Verify installation
docker compose exec n8n npm --prefix /tests list
```

### Validation Errors on Existing Workflows

**Symptoms:**

- Validation reports errors in existing workflows

**Solutions:**

1. **Review the error** - Most are legitimate configuration issues
2. **Use skip tags** if needed (see [Validation Documentation](./workflow-validation.md#skipping-validation)):

    ```
    @n8n-validate-ignore <error_type>
    ```

    Add this to the node's **Notes** field.

3. **Fix the issue** - Follow validation error messages

## Configuration Files

### Environment Variables

Add to your `.env`:

```bash
# N8N Testing Framework
N8N_API_KEY=your_api_key_here
```

### Pre-commit Hook

The testing framework is automatically integrated into pre-commit:

- **Workflow validation** runs on `*.json` changes in `docker/n8n/workflows/`
- **Documentation validation** runs on `*.md` changes in `docs/`

Install/update hooks:

```bash
task hook:install
```

## Next Steps

- **[Workflow Testing Guide](./workflow-testing.md)** - Write and run workflow tests
- **[Workflow Validation Guide](./workflow-validation.md)** - Understand validation rules
- **[Best Practices](./best-practices.md)** - Error handling and naming conventions

## Additional Notes

### CI/CD Integration

The testing framework runs automatically in GitLab CI:

- **Validation** runs on all MRs
- **Tests** run on workflow changes
- **Coverage reports** are generated

### Validation Skip Tags

You can skip validation on specific nodes using the `@n8n-validate-ignore` tag:

```
@n8n-validate-ignore RabbitMQMessage
```

See [Validation Documentation](./workflow-validation.md#skipping-validation) for details.

### Docker Compose Profiles

The `devtools` service uses Docker Compose profiles:

```yaml
devtools:
    profiles:
        - devtools
```

Always use `--profile devtools` when working with validation:

```bash
docker compose --profile devtools run --rm devtools php api/tests/N8N/validate-n8n-workflows.php
```

Or use the Taskfile wrapper:

```bash
task n8n:validate  # Handles profile automatically
```
