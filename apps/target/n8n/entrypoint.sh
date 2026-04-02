#!/bin/sh
set -e

start_n8n() {
  echo "[init] Starting n8n core..."
  exec /docker-entrypoint.sh "$@" &
  N8N_PID=$!
  echo "[init] n8n started with PID: $N8N_PID"
}

wait_for_api() {
  echo "[init] Waiting for n8n HTTP API..."
  local max_attempts=60
  local attempt=0

  while [ $attempt -lt $max_attempts ]; do
    content=$(wget -qO- "http://127.0.0.1:${N8N_PORT}/healthz/readiness" 2>/dev/null || true)
    if echo "$content" | grep -q 'ok'; then
      echo "[init] n8n API is ready!"
      return 0
    fi

    attempt=$((attempt + 1))
    echo "[init] Still waiting for n8n API... (attempt $attempt/$max_attempts)"
    sleep 2
  done

  echo "[init] ERROR: n8n API failed to become ready after $max_attempts attempts"
  return 1
}

create_default_user() {
  local max_attempts=5
  local attempt=0

  echo "[init] Creating default user..."

  while [ $attempt -lt $max_attempts ]; do
    contentForm=$(wget -qSO- "http://127.0.0.1:${N8N_PORT}/setup" 2>&1 || true)

    if echo "$contentForm" | grep -vq '404 Not Found'; then
      echo "[init] n8n API is ready for user creation."
      break
    fi

    attempt=$((attempt + 1))
    echo "[init] Still waiting for n8n user creation API... (attempt $attempt/$max_attempts)"
    sleep 2
  done

  userAlreadyExists=$(n8n mfa:disable --email="$N8N_DEFAULT_EMAIL" 2>&1 || true)
  if echo "$userAlreadyExists" | grep -q 'Successfully disabled MFA'; then
    echo "[init] Default user already exists. Skipping creation."
    return 0
  fi

  contentSubmit=$(wget -qSO- \
    --header="Content-Type: application/json" \
    --header="Origin: http://127.0.0.1:${N8N_PORT}/setup" \
    --post-data='{"email":"'"$N8N_DEFAULT_EMAIL"'","firstName":"'"$N8N_DEFAULT_FIRSTNAME"'","lastName":"'"$N8N_DEFAULT_LASTNAME"'","password":"'"$N8N_DEFAULT_PASSWORD"'"}' \
    "http://127.0.0.1:${N8N_PORT}/rest/owner/setup" 2>/dev/null || true)

  # When user already exists, n8n returns 400 error with no HTML content
  if echo "$contentSubmit" | grep -q '400 Bad Request'; then
    echo "[init] Default user already exists. Skipping creation."
    return 0
  fi

  # After 2 restarts, n8n may return an empty response, treat that as user already exists
  if [ -z "$contentSubmit" ]; then
    echo "[init] POST to /setup returned no content, so assuming user already exists."
    echo "[init] Default user already exists. Skipping creation."
    return 0
  fi

  if echo "$contentSubmit" | grep -q 'error'; then
    echo "[init] ERROR: Failed to create default user - $contentSubmit"
    kill $N8N_PID 2>/dev/null || true
    exit 1
  fi

  if echo "$contentSubmit" | grep -qv "createdAt\":\"$(date +%Y-%m-%d)"; then
    echo "[init] ERROR: Failed to create default user - $contentSubmit"
    kill $N8N_PID 2>/dev/null || true
    exit 1
  fi

  echo "[init] Default user created successfully."
}

start_n8n "$@"

if wait_for_api; then
  create_default_user
else
  echo "[init] ERROR: Failed to initialize n8n - API not ready"
  kill $N8N_PID 2>/dev/null || true
  exit 1
fi

echo "[init] Initialization complete, enjoy n8n!"
wait $N8N_PID
