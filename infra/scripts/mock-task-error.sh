#!/bin/bash
# mock-task-error.sh
# QA helper: inject a mock Dify error on any task to test the frontend error UI.
#
# Usage: ./infra/scripts/mock-task-error.sh
# Run from the monorepo root (where compose files live).

set -e

DC="${DC:-docker compose -f infra/compose.yaml -f infra/compose.local.yaml}"
DB_CONTAINER="infra-db-1"
DB_NAME="chapsmind_db"
DB_USER="postgres"

psql() {
  docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -A -c "$1"
}

# ─── Colors ───────────────────────────────────────────────────────────────────
BOLD="\033[1m"
CYAN="\033[1;36m"
GREEN="\033[1;32m"
YELLOW="\033[1;33m"
RED="\033[1;31m"
RESET="\033[0m"

header() { echo -e "\n${CYAN}${BOLD}$1${RESET}"; }
success() { echo -e "${GREEN}✔  $1${RESET}"; }
warn()    { echo -e "${YELLOW}⚠  $1${RESET}"; }
error()   { echo -e "${RED}✘  $1${RESET}"; exit 1; }

# ─── Step 1: choose company ───────────────────────────────────────────────────
header "── Step 1 / 3 : Choose a company ──"

companies=$(psql "SELECT id, name FROM screen_schema.companies ORDER BY name;")

if [[ -z "$companies" ]]; then
  error "No companies found in the database. Run the seed script first."
fi

echo ""
i=1
declare -a company_ids
declare -a company_names
while IFS="|" read -r id name; do
  echo -e "  ${BOLD}[$i]${RESET}  $name  ${YELLOW}(id: $id)${RESET}"
  company_ids+=("$id")
  company_names+=("$name")
  ((i++))
done <<< "$companies"

echo ""
read -rp "Enter number: " company_choice

if ! [[ "$company_choice" =~ ^[0-9]+$ ]] || \
   (( company_choice < 1 || company_choice > ${#company_ids[@]} )); then
  error "Invalid choice."
fi

selected_company_id="${company_ids[$((company_choice - 1))]}"
selected_company_name="${company_names[$((company_choice - 1))]}"
success "Selected company: $selected_company_name (id: $selected_company_id)"

# ─── Step 2: choose task ──────────────────────────────────────────────────────
header "── Step 2 / 3 : Choose a task ──"

tasks=$(psql "SELECT id, type, status FROM screen_schema.tasks WHERE company_id = $selected_company_id ORDER BY type;")

if [[ -z "$tasks" ]]; then
  error "No tasks found for company '$selected_company_name'."
fi

echo ""
i=1
declare -a task_ids
declare -a task_types
while IFS="|" read -r id type status; do
  echo -e "  ${BOLD}[$i]${RESET}  $type  ${YELLOW}(id: $id, current status: $status)${RESET}"
  task_ids+=("$id")
  task_types+=("$type")
  ((i++))
done <<< "$tasks"

echo ""
read -rp "Enter number: " task_choice

if ! [[ "$task_choice" =~ ^[0-9]+$ ]] || \
   (( task_choice < 1 || task_choice > ${#task_ids[@]} )); then
  error "Invalid choice."
fi

selected_task_id="${task_ids[$((task_choice - 1))]}"
selected_task_type="${task_types[$((task_choice - 1))]}"
success "Selected task: $selected_task_type (id: $selected_task_id)"

# ─── Step 3: choose error type ────────────────────────────────────────────────
header "── Step 3 / 3 : Choose an error type ──"

echo ""
echo -e "  ${BOLD}[1]${RESET}  Rate limit LLM      ${YELLOW}(rate_limit_llm)${RESET}       — countdown timer, recoverable"
echo -e "  ${BOLD}[2]${RESET}  Rate limit API      ${YELLOW}(rate_limit_api)${RESET}       — countdown timer, recoverable"
echo -e "  ${BOLD}[3]${RESET}  Context too long    ${YELLOW}(context_length_exceeded)${RESET} — recoverable, no countdown"
echo -e "  ${BOLD}[4]${RESET}  Timeout             ${YELLOW}(timeout)${RESET}              — recoverable, no countdown"
echo -e "  ${BOLD}[5]${RESET}  Invalid output      ${YELLOW}(invalid_output)${RESET}       — recoverable, no countdown"
echo -e "  ${BOLD}[6]${RESET}  Unknown error       ${YELLOW}(unknown_error)${RESET}        — permanent, not recoverable"
echo -e "  ${BOLD}[7]${RESET}  Provider error      ${YELLOW}(provider_error)${RESET}       — permanent, not recoverable"
echo ""
read -rp "Enter number: " error_choice

case "$error_choice" in
  1)
    error_type="rate_limit_llm"
    is_recoverable="true"
    read -rp "Retry after how many seconds? [default: 30] " retry_secs
    retry_secs="${retry_secs:-30}"
    recommended_action="retry_after_${retry_secs}s"
    ;;
  2)
    error_type="rate_limit_api"
    is_recoverable="true"
    read -rp "Retry after how many seconds? [default: 60] " retry_secs
    retry_secs="${retry_secs:-60}"
    recommended_action="retry_after_${retry_secs}s"
    ;;
  3)
    error_type="context_length_exceeded"
    is_recoverable="true"
    retry_secs="null"
    recommended_action="split_content"
    ;;
  4)
    error_type="timeout"
    is_recoverable="true"
    retry_secs="null"
    recommended_action="retry"
    ;;
  5)
    error_type="invalid_output"
    is_recoverable="true"
    retry_secs="null"
    recommended_action="retry"
    ;;
  6)
    error_type="unknown_error"
    is_recoverable="false"
    retry_secs="null"
    recommended_action="null"
    ;;
  7)
    error_type="provider_error"
    is_recoverable="false"
    retry_secs="null"
    recommended_action="contact_support"
    ;;
  *)
    error "Invalid choice."
    ;;
esac

# Build the JSON — handle null values properly
if [[ "$retry_secs" == "null" ]]; then
  retry_after_json="null"
else
  retry_after_json="$retry_secs"
fi

if [[ "$recommended_action" == "null" ]]; then
  recommended_action_json="null"
else
  recommended_action_json="\"$recommended_action\""
fi

error_details_json="{\"error_type\": \"$error_type\", \"is_recoverable\": $is_recoverable, \"retry_after_seconds\": $retry_after_json, \"recommended_action\": $recommended_action_json}"

# ─── Apply to database ────────────────────────────────────────────────────────
header "── Applying mock error ──"

psql "UPDATE screen_schema.tasks
      SET status = 'error',
          error = 'Mock error injected by QA: $error_type',
          error_details = '$error_details_json',
          updated_at = NOW()
      WHERE id = $selected_task_id;"

echo ""
success "Done! Task '$selected_task_type' (id: $selected_task_id) on '$selected_company_name' is now in error state."
echo -e "  Error type : ${BOLD}$error_type${RESET}"
echo -e "  Recoverable: ${BOLD}$is_recoverable${RESET}"
if [[ "$retry_secs" != "null" ]]; then
  echo -e "  Countdown  : ${BOLD}${retry_secs}s${RESET}"
fi
echo ""
warn "Refresh the company page in the browser to see the error UI."
