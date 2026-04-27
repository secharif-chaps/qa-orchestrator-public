# Testing the Dify Error UI

This guide explains how to test the contextualized error display introduced in TAR-1172.
No real Dify failures are needed — a helper script injects mock errors directly into the database.

---

## Prerequisites

- Docker services are running
- The app is accessible at `http://localhost`
- At least one company exists

---

## Database migration

This feature requires migration `028` which adds the `error_details` column to the `tasks` table.

### Check if migration is already applied

```bash
task migrate:status
```

Look for `028_add_task_error_details` in the output. If it shows `(head)` or is listed as applied, you're good.

Alternatively:

```bash
docker exec infra-screen-1 alembic current
```

### Run the migration if not applied

```bash
task migrate
```

Or directly:

```bash
docker exec infra-screen-1 alembic upgrade head
```

---

## How it works

When a Dify workflow fails, the backend saves structured error details in the `error_details` column of the `tasks` table. The frontend reads this and displays one of three error states:

| Error category  | Condition                                            | What the user sees                                         |
| --------------- | ---------------------------------------------------- | ---------------------------------------------------------- |
| **Rate limit**  | `error_type` is `rate_limit_llm` or `rate_limit_api` | Countdown timer + disabled retry button until timer hits 0 |
| **Recoverable** | `is_recoverable: true` (no countdown)                | Warning alert + active retry button                        |
| **Permanent**   | `is_recoverable: false`                              | Red error alert + retry button                             |

The countdown is **persistent** — it resumes correctly after page refresh or modal close/reopen, based on `updated_at`.

---

## Step-by-step test procedure

### 1. Start the services

Open `http://localhost` and navigate to any company page.

### 2. Run the mock error script

From the **monorepo root**:

```bash
./infra/scripts/mock-task-error.sh
```

You will be prompted through 3 steps:

**Step 1 — Choose a company**

```
── Step 1 / 3 : Choose a company ──

  [1]  inwi  (id: 1)

Enter number: 1
```

**Step 2 — Choose a task**

```
── Step 2 / 3 : Choose a task ──

  [1]  csr       (id: 4, current status: succeeded)
  [2]  digital   (id: 3, current status: succeeded)
  [3]  jobs      (id: 9, current status: succeeded)
  [4]  press     (id: 5, current status: succeeded)
  [5]  profile   (id: 2, current status: succeeded)
  [6]  timeline  (id: 6, current status: succeeded)
  [7]  team      (id: 8, current status: succeeded)

Enter number: 6
```

**Step 3 — Choose an error type**

```
── Step 3 / 3 : Choose an error type ──

  [1]  Rate limit LLM      (rate_limit_llm)         — countdown timer, recoverable
  [2]  Rate limit API      (rate_limit_api)          — countdown timer, recoverable
  [3]  Context too long    (context_length_exceeded) — recoverable, no countdown
  [4]  Timeout             (timeout)                 — recoverable, no countdown
  [5]  Invalid output      (invalid_output)          — recoverable, no countdown
  [6]  Unknown error       (unknown_error)           — permanent, not recoverable
  [7]  Provider error      (provider_error)          — permanent, not recoverable

Enter number: 1
Retry after how many seconds? [default: 30] 20
```

### 3. Refresh the browser

Hard-refresh the company page (`Ctrl+Shift+R`). The affected card now shows the error overlay.

---

## What to verify for each scenario

### Scenario A — Rate limit with countdown

Use error type **[1] or [2]** with a short duration (e.g. 15 seconds).

- [ ] The card shows a **yellow warning** overlay with the countdown: _"Please retry in 15 seconds"_
- [ ] The retry button is **disabled** and shows _"Retry in 15s"_ with an hourglass icon
- [ ] The countdown ticks down every second
- [ ] When it reaches 0, the description changes to the recoverable message (no "in 0 seconds")
- [ ] The button becomes **active** and shows _"Retry"_ with a refresh icon
- [ ] **Refresh the page mid-countdown** → the timer resumes from the correct remaining time, not from the beginning
- [ ] **Open the card (click it) mid-countdown** → the section view shows the same countdown, resuming correctly
- [ ] **Close and reopen the section** → the timer keeps its position, does not restart
- [ ] **Toggle the language** (FR ↔ EN) → all text updates instantly, countdown continues uninterrupted

### Scenario B — Recoverable error (no countdown)

Use error type **[3], [4], or [5]**.

- [ ] The card shows a **yellow warning** overlay with a recoverable title and description
- [ ] The retry button is **immediately active**
- [ ] Clicking the section shows the same recoverable message
- [ ] **Toggle the language** → text changes correctly

### Scenario C — Permanent / generic error

Use error type **[6] or [7]**.

- [ ] The card shows a **red error** overlay with a generic title and description
- [ ] The retry button is present and active
- [ ] Clicking the section shows the same error message
- [ ] **Toggle the language** → text changes correctly

### Scenario D — Language consistency

For any error type:

- [ ] Switch language to **French** → all error messages are in French
- [ ] Switch language to **English** → all error messages are in English
- [ ] The **raw backend error string** (e.g. `"Rate limit exceeded"`) is **never shown** to the user

---

## Reset a task back to normal

To clear the mock error and restore a task to `succeeded`:

```bash
docker exec infra-db-1 psql -U postgres -d chapsmind_db -c \
  "UPDATE tasks SET status='succeeded', error=NULL, error_details=NULL WHERE id = <task_id>;"
```

Or run the script again and pick a new error type — each run overwrites the previous one.

---

## Error types reference

| `error_type`              | `is_recoverable` | `retry_after_seconds` | UI shown                |
| ------------------------- | ---------------- | --------------------- | ----------------------- |
| `rate_limit_llm`          | true             | set by QA             | Rate limit countdown    |
| `rate_limit_api`          | true             | set by QA             | Rate limit countdown    |
| `context_length_exceeded` | true             | null                  | Recoverable warning     |
| `timeout`                 | true             | null                  | Recoverable warning     |
| `invalid_output`          | true             | null                  | Recoverable warning     |
| `unknown_error`           | false            | null                  | Generic permanent error |
| `provider_error`          | false            | null                  | Generic permanent error |
