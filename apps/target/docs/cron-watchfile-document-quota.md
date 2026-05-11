# WatchFile Document Quota Cron

## Objective

Automatically enforce the maximum number of documents allowed per WatchFile.The cron job executes the Symfony command that:

- Identifies WatchFiles exceeding the configured `document.max_per_watchfile` quota
- Moves exceeding WatchFiles to `DRAFT` status
- Records a WatchFile activity (`DOCUMENT_QUOTA_EXCEEDED`) for traceability

## Command

```
php bin/console app:usage-limit:document:check
```

### Docker / Compose execution

```
docker compose exec api php bin/console app:usage-limit:document:check
```

### Optional parameters

`--quota=<int>` – overrides the configured quota (useful for QA / staging tests).

Example:

```
docker compose exec api php bin/console app:usage-limit:document:check --quota=5000
```

## Scheduling guideline (infrastructure team)

| Environment | Schedule                      | Notes                                                             |
| ----------- | ----------------------------- | ----------------------------------------------------------------- |
| Production  | `0 * * * *` (every hour)      | Recommended. Ensures fast remediation when document counts spike. |
| Staging     | `0 */3 * * *` (every 3 hours) | Align with staging load expectations.                             |

### Infrastructure notes

- Command relies on OpenSearch aggregations; ensure the cluster is reachable.
- Log output is written to STDOUT/STDERR. Redirect to a persistent log file or centralized logging system.
- The command exits with:
  - `0` when successful (even if no WatchFiles exceeded quota)
  - `1` when an error occurs

## Monitoring & alerting

- Track the log message `WatchFile moved to DRAFT due to document quota exceeded`.
- Alert if the cron command fails consecutively (non-zero exit code).
- Optionally emit metrics:
  - Number of WatchFiles processed
  - Highest document count observed

## Manual execution checklist

1. Run the command via Docker (`docker compose exec api ...`) or the dedicated task runner.
2. Review console output/logs for:
   - `Starting document quota check`
   - `WatchFile moved to DRAFT...` entries
3. Re-run with `--quota` only in non-production environments.

## Failure recovery

| Failure                | Mitigation                                                                     |
| ---------------------- | ------------------------------------------------------------------------------ |
| OpenSearch unreachable | Check cluster health, rerun command once the cluster is up.                    |
| System user missing    | Re-seed fixture or manually create the Basil user before rerunning.            |
| Repeated failures      | Temporarily disable the cron entry, investigate logs, re-enable when resolved. |
