# Apify — Operations Guide

This guide is for the operations team. It covers cost monitoring, troubleshooting common issues, and configuration changes without deployment.

---

## Environment Variables

| Variable                     | Default Value           | Description                                                                  |
| ---------------------------- | ----------------------- | ---------------------------------------------------------------------------- |
| `APIFY_API_URL`              | `https://api.apify.com` | Base URL of the Apify API                                                    |
| `APIFY_API_TOKEN`            | _(required)_            | Apify authentication token (Account Settings > Integrations)                 |
| `APIFY_WEBHOOK_URL`          | _(required)_            | Public URL of the application to receive Apify webhooks                      |
| `APIFY_MAX_TOTAL_CHARGE_USD` | `0.10`                  | Maximum cost per run in USD — run is cancelled if this threshold is exceeded |

`APIFY_WEBHOOK_URL` must be accessible from the internet. In production, it's the public URL of the API (e.g., `https://api.target.chapsmind.io/webhooks/apify`).

---

## Cost Monitoring

### Data Structure

Each completed Apify run generates an entry in the `source_activities` table with:

- `action_type = 'source_collect_cost'`
- Metrics encoded in the `metadata` JSON column

### Useful SQL Queries

**Total Apify cost over the last 30 days:**

```postgresql
SELECT
    SUM((metadata->>'cost_usd')::float) AS total_cost_usd,
    SUM((metadata->>'compute_units')::float) AS total_compute_units,
    COUNT(*) AS nb_runs
FROM source_activities
WHERE action_type = 'source_collect_cost'
  AND created_at >= NOW() - INTERVAL '30 days';
```

**Cost per Apify actor:**

```postgresql
SELECT
    metadata->>'apify_actor_id' AS actor_id,
    COUNT(*) AS nb_runs,
    SUM((metadata->>'cost_usd')::float) AS total_cost_usd,
    AVG((metadata->>'cost_usd')::float) AS avg_cost_usd,
    AVG((metadata->>'compute_units')::float) AS avg_compute_units
FROM source_activities
WHERE action_type = 'source_collect_cost'
  AND created_at >= NOW() - INTERVAL '30 days'
GROUP BY metadata->>'apify_actor_id'
ORDER BY total_cost_usd DESC;
```

**Most expensive runs:**

```postgresql
SELECT
    metadata->>'collect_task_id' AS collect_task_id,
    metadata->>'apify_actor_id' AS actor_id,
    metadata->>'run_id' AS run_id,
    (metadata->>'cost_usd')::float AS cost_usd,
    (metadata->>'compute_units')::float AS compute_units,
    created_at
FROM source_activities
WHERE action_type = 'source_collect_cost'
ORDER BY (metadata->>'cost_usd')::float DESC
LIMIT 20;
```

**Cost for a specific source:**

```postgresql
SELECT
    sa.source_id,
    SUM((sa.metadata->>'cost_usd')::float) AS total_cost_usd,
    COUNT(*) AS nb_runs
FROM source_activities sa
WHERE sa.action_type = 'source_collect_cost'
  AND sa.source_id = 'YOUR_SOURCE_ID'
GROUP BY sa.source_id;
```

### Available Keys in `metadata`

| Key               | Type   | Description                                      |
| ----------------- | ------ | ------------------------------------------------ |
| `collect_task_id` | string | ULID of the application's CollectTask            |
| `apify_actor_id`  | string | Actor ID (e.g., `apify/website-content-crawler`) |
| `run_id`          | string | Apify run ID                                     |
| `compute_units`   | float  | Compute units consumed                           |
| `cost_usd`        | float  | Cost in USD                                      |

---

## Troubleshooting

### Webhook Not Received

**Symptoms**: The Apify run appears complete in the Apify UI, but no documents are created and no `source_collect_cost` activity is recorded.

**Possible Causes**:

1. `APIFY_WEBHOOK_URL` is misconfigured or points to an inaccessible URL from the internet.
2. The container was unreachable at the time of the run.
3. The webhook was sent but returned an HTTP error.

**Diagnosis**:

```bash
# Verify the configured value
docker exec chapsmind-target-1 php bin/console debug:container --parameter=app.apify.webhook_url

# Search logs for received webhooks
grep "apify webhook received" var/log/prod.log

# Check for reception errors
grep "apify" var/log/prod.log | grep -i "error\|exception\|failed"
```

Also check the Apify UI (section "Webhooks" of the run) to see if the webhook was attempted and what HTTP response was received.

---

### Empty Dataset

**Symptoms**: The webhook is received, but no documents are created. Logs indicate "0 items" in the dataset.

**Possible Causes**:

1. The input template is misconfigured (wrong field, wrong variable).
2. The actor failed to scrape the URLs (anti-bot protection, invalid URL).
3. The source has no URL or query configured.

**Diagnosis**:

```bash
# Verify configured templates
docker exec chapsmind-target-1 php bin/console debug:container --parameter=app.apify.input_templates

# See details of a run in logs
grep "apify_actor_id" var/log/prod.log | grep "RUN_ID"
```

Manually test the actor in the Apify UI with the same input to isolate whether the problem is in the template or the actor itself.

---

### Actor Failed (FAILED Status on Apify)

**Symptoms**: The CollectTask transitions to `FAILED` status. Logs indicate `Dispatched UpdateTaskStatusAction` with status `failed`.

**Possible Causes**:

1. Invalid input (required field missing, incorrect format).
2. Apify run timeout (actor too slow or dataset too large).
3. `APIFY_MAX_TOTAL_CHARGE_USD` quota reached.

**Diagnosis**:

```bash
# See the mapping of Apify statuses → CollectTaskStatus
# ApifyStatusMapper translates: SUCCEEDED, FAILED, TIMED-OUT, ABORTED, etc.
grep "UpdateTaskStatusAction" var/log/prod.log | tail -20
```

In the Apify UI, open the relevant run (via the `run_id` in logs or `source_activities`) and read the actor's execution logs.

**If the `APIFY_MAX_TOTAL_CHARGE_USD` quota is the cause**:

- Increase the value in environment variables and redeploy.
- Or configure a lower `maxResults` in the actor's template in `config/services/collect_provider.yaml`.

---

### High Costs

**Symptoms**: The Apify invoice is higher than expected.

**Actions**:

1. Reduce `APIFY_MAX_TOTAL_CHARGE_USD` to cap future runs.
2. Identify the most expensive actors via the "Cost per actor" SQL query above.
3. Reduce `maxCrawlPages`, `maxResults`, or `maxArticles` in the templates of the expensive actors in `config/services/collect_provider.yaml`.
4. If an actor is consistently too expensive, route its SourceType to Bakus by removing its entry from `app.collect.provider_routing.source_types`.

---

## Modify Routing for a SourceType

To switch a SourceType from Apify to Bakus (or vice versa) without code deployment:

Modify `config/services/collect_provider.yaml`:

```yaml
parameters:
  app.collect.provider_routing.source_types:
    # To route to Apify:
    my:source:type: 'apify'

    # To route to Bakus (or remove the line to use COLLECT_DEFAULT_PROVIDER):
    # my:source:type: 'bakus'
```

The change takes effect after containers are restarted. `CollectTask` entries already created retain their original provider and continue executing on the initial provider.

To test the current routing without modifying config:

```bash
docker exec chapsmind-target-1 php bin/console debug:container \
  --parameter=app.collect.provider_routing.source_types
```

---

## Monitor Runs via Structured Logs

Apify logs use consistent structured keys. Examples of searching in Kibana or via grep:

```bash
# All events for a CollectTask
grep '"collect_task_id":"ULID"' var/log/prod.log

# All runs of an actor
grep '"apify_actor_id":"apify/website-content-crawler"' var/log/prod.log

# Cost recordings (compute_units and cost_usd)
grep '"compute_units"' var/log/prod.log

# Apify errors only
grep 'apify' var/log/prod.log | grep '"level":"error"'
```

### Structured Keys Available in Apify Logs

| Key               | Present in                                    |
| ----------------- | --------------------------------------------- |
| `collect_task_id` | All logs of the Apify flow                    |
| `apify_actor_id`  | Run creation, webhook receipt, normalization  |
| `run_id`          | Webhook receipt, dataset fetch, cost logging  |
| `compute_units`   | Cost logging (`source_collect_cost`)          |
| `cost_usd`        | Cost logging (`source_collect_cost`)          |
| `provider_name`   | All logs of the collect flow (value: `apify`) |
