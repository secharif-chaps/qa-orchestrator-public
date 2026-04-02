# Renovate Dependency Management

## Overview

Renovate automatically monitors and updates project dependencies for both the API (Symfony/PHP) and PWA (Nuxt.js/TypeScript) components.

## How It Works

### Automated Updates

Renovate runs automatically via GitLab CI scheduled pipeline:

- **Schedule**: Daily at 2am (Europe/Paris timezone)
- **Configuration**: `renovate.json` at project root
- **Trigger**: GitLab CI schedule with `SCHEDULE_TYPE=Renovate`

### Update Types

#### 🟢 Automerge Enabled (Automatic)

These updates merge automatically when CI pipeline passes:

- **Patch updates** (e.g., 1.2.3 → 1.2.4) for non-critical packages
- **PHPStan packages** - Static analysis tools
- **Pinia ecosystem** - State management updates
- **VueUse packages** - Vue composition utilities
- **Testing libraries** - Vitest, PHPUnit, Testing Library
- **Linting and formatting** - ESLint, Prettier, Stylelint, ECS
- **Lockfile maintenance** - Monthly automatic lockfile updates

**Safety measures**:

- Minimum 3 days release age (except security updates)
- Full CI pipeline must pass
- Excludes critical packages (Symfony, Nuxt, Vue, Doctrine, API Platform)

#### 🟡 Grouped Updates (Manual Review)

Multiple related packages updated together:

- **Symfony packages** - All `symfony/*` packages (Monday mornings)
- **Doctrine packages** - All `doctrine/*` packages
- **API Platform packages** - All `api-platform/*` packages
- **Nuxt ecosystem** - Nuxt + `@nuxt/*` + `@nuxtjs/*` packages
- **Vue ecosystem** - Vue + `@vue/*` packages (excluding VueUse)
- **Tailwind CSS** - Tailwind + plugins
- **Docker images** - Container image updates (weekends only)
- **OpenSearch packages** - Search engine updates
- **Keycloak packages** - Authentication system updates

#### 🔴 Security Updates (High Priority)

- Created immediately (no release age delay)
- Labeled with `security` and `priority`
- Highest priority for review
- Automatic notifications enabled

## Dependency Dashboard

Access the dashboard at: **Issues → Filter by `renovate-dashboard` label**

The dashboard shows:

- ✅ All pending updates
- ⏸️ Rate-limited or pending updates
- ❌ Failed update attempts
- 📝 Configuration errors

**Useful actions**:

- **Close the dashboard issue** to pause all Renovate updates
- **Reopen** to resume updates
- **Comment** `@renovatebot recreate` to recreate a specific closed MR

## Merge Request Labels

| Label          | Meaning                    |
| -------------- | -------------------------- |
| `dependencies` | All Renovate MRs           |
| `renovate`     | Renovate bot identifier    |
| `security`     | Security vulnerability fix |
| `priority`     | High priority update       |

## Managing Updates

### Temporarily Pause Updates

**Option 1: Pause all updates**

```bash
# Close the Dependency Dashboard issue
# All updates will be paused until you reopen it
```

**Option 2: Skip specific updates**

```bash
# Edit renovate.json and add to "ignoreDeps":
"ignoreDeps": ["package-name", "another-package"]
```

### Force Update Recreation

If a Renovate MR was closed but you want to recreate it:

1. Go to the Dependency Dashboard issue
2. Find the update in the "Closed/Ignored Updates" section
3. Comment: `@renovatebot recreate`

### Emergency Security Update

Security updates bypass all schedules and are created immediately with:

- Label: `security` + `priority`
- Minimum release age: 0 days
- Priority: 10 (highest)

## Configuration

### Main Configuration

Located at `renovate.json` in project root.

Key settings:

```json
{
    "prConcurrentLimit": 5, // Max 5 MRs at once
    "prHourlyLimit": 2, // Max 2 MRs per hour
    "stabilityDays": 3, // Wait 3 days before updating
    "schedule": [
        // Run outside work hours
        "after 10pm every weekday",
        "before 5am every weekday",
        "every weekend"
    ]
}
```

### GitLab CI Job

Located in `.gitlab-ci.yml` under `renovate:` job.

**Manual trigger** (for testing):

```bash
# Via GitLab UI: CI/CD → Pipelines → Run pipeline
# Variables:
SCHEDULE_TYPE=Renovate
```

### Enable Automerge in GitLab

For automerge to work, configure in **Settings → Merge requests**:

1. ✅ Enable "Merge when pipeline succeeds"
2. ✅ Enable "Automatically resolve merge request conflicts" (optional)
3. Set required approvals if needed

## Scheduling Strategy

| Day            | Updates                                              |
| -------------- | ---------------------------------------------------- |
| Monday         | Symfony, Doctrine, Nuxt, Vue, Tailwind (grouped)     |
| Tuesday-Friday | Security updates, patch updates, individual packages |
| Weekend        | Docker images, general maintenance                   |
| 1st of month   | Lockfile maintenance (Composer, Yarn)                |

## Troubleshooting

### No MRs Created

**Check**:

1. Is the Dependency Dashboard issue closed? → Reopen it
2. Are all dependencies up to date? → Check the dashboard
3. CI schedule active? → Check CI/CD → Schedules
4. Check Renovate logs: Pipeline artifacts → `renovate-log.json`

### Automerge Not Working

**Check**:

1. GitLab "Merge when pipeline succeeds" enabled?
2. CI pipeline passing?
3. Package excluded from automerge? (Check `renovate.json`)
4. Minimum release age passed? (3 days default)

### Too Many MRs

**Solutions**:

- Increase `stabilityDays` in `renovate.json`
- Adjust `prConcurrentLimit` (default: 5)
- Create more package groups
- Adjust schedules to spread updates

### Update Keeps Getting Recreated

**Cause**: Renovate recreates closed MRs if:

- Issue is still valid
- No "Stop asking" comment

**Solution**:

1. Add package to `ignoreDeps` in `renovate.json`, or
2. Comment on closed MR: `@renovatebot ignore this dependency`

## Best Practices

### For Developers

1. **Review grouped updates carefully** - Multiple packages changed at once
2. **Test locally** - Run tests before merging major updates
3. **Check CHANGELOG** - Review breaking changes for major versions
4. **Monitor CI** - Ensure all pipelines pass
5. **Don't ignore security updates** - Merge them ASAP

### For Maintainers

1. **Review dashboard weekly** - Stay on top of pending updates
2. **Adjust grouping** - Add more groups if too many individual MRs
3. **Fine-tune automerge** - Balance safety vs automation
4. **Monitor failure patterns** - Update config if certain packages always fail
5. **Keep config documented** - Update this doc when changing `renovate.json`

## Common Scenarios

### Major Framework Update (e.g., Symfony 7.3 → 7.4)

**NOT automated** - Requires manual planning:

1. Create feature branch
2. Update manually
3. Test thoroughly
4. Update migration guide
5. Coordinate team deployment

### Security Vulnerability

**Automated with priority**:

1. Renovate creates MR immediately
2. Labeled `security` + `priority`
3. Review ASAP
4. Test if possible
5. Merge quickly

### New Package Added

**Automatic**:

- Renovate detects new package in next run
- Updates according to rules
- No configuration needed

## Resources

- [Renovate Documentation](https://docs.renovatebot.com/)
- [Configuration Options](https://docs.renovatebot.com/configuration-options/)
- [Package Rules](https://docs.renovatebot.com/configuration-options/#packagerules)
- [Automerge](https://docs.renovatebot.com/key-concepts/automerge/)
- [Dependency Dashboard](https://docs.renovatebot.com/key-concepts/dashboard/)

## Contact

Questions or issues with Renovate?

- Check this documentation first
- Review the Dependency Dashboard
- Check Renovate logs in CI artifacts
- Ask in team chat
- Open an issue with `renovate` label
