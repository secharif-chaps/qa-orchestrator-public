# Renovate Setup Guide

This guide will help you set up and configure Renovate for automated dependency management.

## Prerequisites

- GitLab access with Maintainer role or higher
- Personal Access Token with `api` scope
- Basic understanding of GitLab CI/CD

## Step-by-Step Setup

### 1. Create GitLab Labels

Labels help organize and filter Renovate merge requests.

Go to **Project → Settings → Labels** and create:

| Label Name     | Color     | Description                                        |
| -------------- | --------- | -------------------------------------------------- |
| `dependencies` | `#0366d6` | Dependency updates managed by Renovate             |
| `renovate`     | `#1e90ff` | Automated dependency update by Renovate bot        |
| `security`     | `#ee0701` | Security vulnerability fix                         |
| `priority`     | `#d93f0b` | High priority update requiring immediate attention |

### 2. Configure GitLab Schedule

#### Via GitLab UI

1. Go to **CI/CD → Schedules**
2. Click **New schedule**
3. Fill in the form:

    ```
    Description: Renovate dependency updates
    Interval Pattern Type: Custom (Cron syntax)
    Cron timezone: Europe/Paris
    Cron syntax: 0 6 * * 1-5
    (Runs on working days at 6:00 AM)

    Target branch: main
    Activated: ✓
    ```

4. Add variable:
    - Key: `SCHEDULE_TYPE`
    - Value: `Renovate`

5. Click **Save pipeline schedule**

#### Via GitLab API

```bash
curl -X POST "https://git.mediaspeech.com/api/v4/projects/483/pipeline_schedules" \
  --header "PRIVATE-TOKEN: $GITLAB_PERSONAL_ACCESS_TOKEN" \
  --form "description=Renovate dependency updates" \
  --form "ref=main" \
  --form "cron=0 2 * * *" \
  --form "cron_timezone=Europe/Paris" \
  --form "active=true"

# Get the schedule ID from the response, then add the variable:
curl -X POST "https://git.mediaspeech.com/api/v4/projects/483/pipeline_schedules/SCHEDULE_ID/variables" \
  --header "PRIVATE-TOKEN: $GITLAB_PERSONAL_ACCESS_TOKEN" \
  --form "key=SCHEDULE_TYPE" \
  --form "value=Renovate"
```

### 3. Enable Automerge Features

For Renovate automerge to work, configure in **Settings → Merge requests**:

#### Required Settings

1. Navigate to **Settings → Merge requests**
2. Scroll to **Merge checks**
3. Enable:
    - ✅ **Pipelines must succeed** (highly recommended)
    - ✅ **All threads must be resolved** (optional but recommended)

4. Scroll to **Merge options**
5. Configure:
    - ✅ **Enable "Merge when pipeline succeeds"** (REQUIRED for automerge)
    - ✅ **Automatically resolve merge request conflicts** (optional)
    - Select **Merge commit** as default merge method (recommended)

#### Optional: Configure Approval Rules

If you want human approval for certain updates:

1. Go to **Settings → Merge requests → Approval rules**
2. Create rule: "Critical dependencies"
3. Set required approvals: 1
4. Add target branches: `main`

Then update `renovate.json` for packages requiring approval:

```json
{
    "packageRules": [
        {
            "matchPackageNames": ["symfony/*", "nuxt", "vue"],
            "requiredStatusChecks": null,
            "platformAutomerge": false
        }
    ]
}
```

### 4. Test Renovate Configuration

Before scheduling, test that Renovate can run:

#### Dry Run Test

```bash
# Set environment variables
export RENOVATE_TOKEN=$GITLAB_PERSONAL_ACCESS_TOKEN
export RENOVATE_ENDPOINT=https://git.mediaspeech.com/api/v4
export RENOVATE_PLATFORM=gitlab

# Run Renovate in dry-run mode (no MRs created)
docker run --rm \
  -e RENOVATE_TOKEN \
  -e RENOVATE_ENDPOINT \
  -e RENOVATE_PLATFORM \
  -e LOG_LEVEL=debug \
  -v $(pwd)/renovate.json:/usr/src/app/renovate.json \
  ghcr.io/renovatebot/renovate:latest \
  --dry-run=full \
  basil/basil
```

#### Manual Pipeline Trigger

1. Go to **CI/CD → Pipelines**
2. Click **Run pipeline**
3. Select branch: `main`
4. Add variable:
    - Key: `SCHEDULE_TYPE`
    - Value: `Renovate`
5. Click **Run pipeline**
6. Monitor the `renovate` job logs

### 5. Verify Setup

After the first run, check:

1. **Dependency Dashboard Created**
    - Go to **Issues**
    - Look for issue titled "Dependency Dashboard"
    - Should list all pending updates

2. **Merge Requests Created**
    - Go to **Merge requests**
    - Filter by label: `renovate`
    - Should see grouped or individual MRs

3. **Logs Available**
    - Go to the Renovate pipeline job
    - Download artifact: `renovate-log.json`
    - Check for errors or warnings

## Troubleshooting

### No Dependency Dashboard Created

**Possible causes**:

- Renovate hasn't run yet → Wait for scheduled run or trigger manually
- Configuration error → Check `renovate.json` syntax with JSON validator
- Authentication issue → Verify CI variables

**Solution**:

```bash
# Check Renovate logs in pipeline artifacts
# Look for errors related to authentication or configuration
```

### Automerge Not Working

**Check these settings**:

1. GitLab Settings → Merge requests:
    - "Merge when pipeline succeeds" must be enabled
    - No required approvals blocking automerge

2. `renovate.json` configuration:
    - Package matches automerge rules
    - `platformAutomerge: true` is set
    - Package not in `excludePackageNames`

3. Release age:
    - Default `stabilityDays: 3` must pass
    - Check `minimumReleaseAge` for the package

**Debug**:

```bash
# Check specific package rule
cat renovate.json | jq '.packageRules[] | select(.groupName == "Pinia ecosystem")'
```

### Rate Limiting Issues

If you see rate limit errors:

**GitLab Rate Limits**:

- Update `renovate.json`:
    ```json
    {
        "prConcurrentLimit": 3,
        "prHourlyLimit": 1
    }
    ```

**Package Registry Rate Limits**:

- Add to `renovate.json`:
    ```json
    {
        "hostRules": [
            {
                "hostType": "npm",
                "maxRetries": 3,
                "retryAfter": 60
            }
        ]
    }
    ```

### Schedule Not Running

**Verify**:

1. Schedule is active:
    - Go to **CI/CD → Schedules**
    - Check "Active" column shows ✓

2. Variable is set:
    - Click on schedule
    - Verify `SCHEDULE_TYPE=Renovate` exists

3. Last run status:
    - Check "Last pipeline" column
    - Click to view pipeline details

**Re-trigger manually**:

```bash
# Via API
curl -X POST "https://git.mediaspeech.com/api/v4/projects/483/pipeline_schedules/SCHEDULE_ID/play" \
  --header "PRIVATE-TOKEN: $GITLAB_PERSONAL_ACCESS_TOKEN"
```

## Maintenance

### Weekly Tasks

- Review Dependency Dashboard
- Merge pending security updates
- Check for failed MRs and resolve

### Monthly Tasks

- Review automerge performance
- Adjust package grouping if needed
- Update ignored dependencies list
- Check Renovate version updates

### Configuration Changes

When modifying `renovate.json`:

1. Test locally with dry-run first
2. Commit to feature branch
3. Trigger manual Renovate run
4. Verify changes in logs
5. Merge to main if successful

## Advanced Configuration

### Custom Package Groups

Add custom grouping to `renovate.json`:

```json
{
    "packageRules": [
        {
            "description": "Group my custom packages",
            "groupName": "Custom packages",
            "matchPackagePatterns": ["^@mycompany/"],
            "schedule": ["before 5am on monday"]
        }
    ]
}
```

### Selective Automerge

Enable automerge only for specific packages:

```json
{
    "packageRules": [
        {
            "matchPackageNames": ["lodash", "axios"],
            "matchUpdateTypes": ["patch", "minor"],
            "automerge": true
        }
    ]
}
```

### Custom Schedules

Different schedules for different package types:

```json
{
    "packageRules": [
        {
            "matchPackagePatterns": ["*"],
            "matchUpdateTypes": ["major"],
            "schedule": ["before 5am on the first monday of the month"]
        }
    ]
}
```

## Resources

- Main configuration file: `renovate.json` in project root
- [Renovate Workflow Documentation](./renovate-workflow.md)
- GitLab CI Job: See `.gitlab-ci.yml` (search for `renovate:`)
- [Renovate Documentation](https://docs.renovatebot.com/)

## Support

If you encounter issues:

1. Check this documentation
2. Review Renovate logs in pipeline artifacts
3. Search [Renovate discussions](https://github.com/renovatebot/renovate/discussions)
4. Ask in team chat
5. Create issue with `renovate` label
