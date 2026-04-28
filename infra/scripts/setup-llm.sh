#!/bin/bash
# Configure LLM environment variables in .env, then provision the matching
# n8n credential file so workflows referencing the OpenAI-compatible LLM
# Gateway pick up the dev's key on first boot.
#
# Source order: env vars → existing .env values → interactive prompt.
# Usage: bash infra/scripts/setup-llm.sh

set -euo pipefail

ENV_FILE=".env"
N8N_CREDENTIALS_DIR="apps/target/n8n/credentials"
# Stable id and name referenced by every workflow that talks to the LLM Gateway.
# Changing them would break workflow imports — keep in sync with the JSON
# `credentials` blocks in apps/target/n8n/workflows/*.json.
N8N_LLM_CREDENTIAL_ID="opXO2Ykv65TKscag"
N8N_LLM_CREDENTIAL_NAME="LLM Gateway - chapsmind-target-env-dev"
N8N_LLM_CREDENTIAL_FILE="${N8N_CREDENTIALS_DIR}/llm-gateway-chapsmind-target-env-dev.json"

if ! [ -f "$ENV_FILE" ]; then
  echo "❌ $ENV_FILE not found. Run from monorepo root."
  exit 1
fi

read_env_value() {
  local var_name="$1"
  grep "^${var_name}=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2- || true
}

write_env_value() {
  local var_name="$1"
  local var_value="$2"
  if grep -q "^${var_name}=" "$ENV_FILE"; then
    sed -i.bak "s|^${var_name}=.*|${var_name}=${var_value}|" "$ENV_FILE"
  else
    echo "${var_name}=${var_value}" >> "$ENV_FILE"
  fi
  rm -f "${ENV_FILE}.bak"
}

# Crypto-js compatible AES-CBC encryption used by n8n credential storage.
# Equivalent to `CryptoJS.AES.encrypt(plaintext, passphrase)` — OpenSSL salted
# format with MD5 KDF. n8n decrypts on import using the container's
# N8N_ENCRYPTION_KEY. Output is base64 of `Salted__` + 8-byte salt + ciphertext.
encrypt_for_n8n() {
  local plaintext="$1"
  local passphrase="$2"
  printf '%s' "$plaintext" \
    | openssl enc -e -aes-256-cbc -md md5 -pass "pass:${passphrase}" -base64 -A 2>/dev/null
}

provision_n8n_llm_credential() {
  local api_key="$1"
  local base_url="$2"
  local n8n_key

  n8n_key=$(read_env_value "N8N_ENCRYPTION_KEY")
  if [ -z "$n8n_key" ]; then
    n8n_key="${N8N_ENCRYPTION_KEY:-!ChangeThisN8nEncryptionKey!}"
  fi

  if [ ! -d "$N8N_CREDENTIALS_DIR" ]; then
    echo "ℹ️  $N8N_CREDENTIALS_DIR not found — skipping n8n credential provisioning."
    return 0
  fi

  # The data field of an `openAiApi` credential is { apiKey, url } in n8n.
  # We escape both values for safe JSON embedding (manual escaping covers
  # quotes and backslashes; URL/API key never contain newlines in practice).
  local escaped_key escaped_url data_json encrypted_data timestamp
  escaped_key=$(printf '%s' "$api_key" | sed 's/\\/\\\\/g; s/"/\\"/g')
  escaped_url=$(printf '%s' "$base_url" | sed 's/\\/\\\\/g; s/"/\\"/g')
  data_json="{\"apiKey\":\"${escaped_key}\",\"url\":\"${escaped_url}\"}"

  encrypted_data=$(encrypt_for_n8n "$data_json" "$n8n_key")
  if [ -z "$encrypted_data" ]; then
    echo "❌ Failed to encrypt n8n credential payload."
    return 1
  fi

  timestamp=$(date -u +"%Y-%m-%dT%H:%M:%S.000Z")

  cat > "$N8N_LLM_CREDENTIAL_FILE" <<EOF
{
  "updatedAt": "${timestamp}",
  "createdAt": "${timestamp}",
  "id": "${N8N_LLM_CREDENTIAL_ID}",
  "name": "${N8N_LLM_CREDENTIAL_NAME}",
  "data": "${encrypted_data}",
  "type": "openAiApi",
  "isManaged": false
}
EOF

  echo "✅ n8n credential generated: ${N8N_LLM_CREDENTIAL_FILE}"
}

# ─── 1. Resolve LLM_API_KEY / LLM_BASE_URL ─────────────────

EXISTING_KEY=$(read_env_value "LLM_API_KEY")
EXISTING_URL=$(read_env_value "LLM_BASE_URL")

API_KEY=""
BASE_URL=""

if [ -n "$EXISTING_KEY" ] && [ "$EXISTING_KEY" != "changeme" ] && \
   [ -n "$EXISTING_URL" ] && [ "$EXISTING_URL" != "changeme" ]; then
  echo "ℹ️  LLM already configured in $ENV_FILE."
  API_KEY="$EXISTING_KEY"
  BASE_URL="$EXISTING_URL"
elif [ -n "${LLM_API_KEY:-}" ] && [ "${LLM_API_KEY:-}" != "changeme" ] && \
     [ -n "${LLM_BASE_URL:-}" ] && [ "${LLM_BASE_URL:-}" != "changeme" ]; then
  # Picked up from shell exports (CI or pre-configured shell)
  API_KEY="$LLM_API_KEY"
  BASE_URL="$LLM_BASE_URL"
else
  echo ""
  echo "🤖 LLM configuration required (OpenAI-compatible endpoint)"
  echo "   Set LLM_BASE_URL and LLM_API_KEY for your provider."
  echo "   (Credentials available in Passbolt — search for 'LLM Gateway - chapsmind-env-dev')"
  echo ""
  if [ -t 0 ]; then
    read -rp "LLM_BASE_URL: " BASE_URL
    read -srp "LLM_API_KEY (input hidden): " API_KEY
    echo ""
  else
    echo "⚠️  LLM_API_KEY / LLM_BASE_URL not set."
    echo "   Export them as environment variables or run interactively."
    echo "   AI features (chat, agents) will not work until configured."
    exit 0
  fi
fi

if [ -z "$API_KEY" ] || [ -z "$BASE_URL" ]; then
  echo "⚠️  LLM_API_KEY or LLM_BASE_URL is empty — skipping configuration."
  exit 0
fi

# ─── 2. Persist to .env ────────────────────────────────────

write_env_value "LLM_API_KEY" "$API_KEY"
write_env_value "LLM_BASE_URL" "$BASE_URL"
echo "✅ LLM configured in $ENV_FILE (LLM_BASE_URL=${BASE_URL})"

# ─── 3. Provision matching n8n credential ──────────────────
# The n8n workflows reference an `openAiApi` credential by stable id
# (opXO2Ykv65TKscag). We re-generate it on every run so a new dev can
# import workflows out of the box without touching the n8n UI.

provision_n8n_llm_credential "$API_KEY" "$BASE_URL"
