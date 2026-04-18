#!/bin/bash
# Unified Keycloak setup for all environments (local dev, staging, preprod).
# Configures roles, clients, organization mappers, and service account permissions.
#
# Usage:
#   # Local dev (after realm import — clients/roles already exist)
#   bash infra/scripts/setup-keycloak.sh \
#     --wait --theme \
#     --create-org "ChapsMind Dev" --org-domain chapsmind.local \
#     --add-users admin,company_manager,company_viewer,no_access
#
#   # Staging (empty realm — create everything)
#   bash infra/scripts/setup-keycloak.sh \
#     --url https://sso.deveryware.team/auth \
#     --user admin --pass secret \
#     --realm chapsmind-internal \
#     --create-roles --create-clients \
#     --domain staging.chapsmind.com

set -euo pipefail

# Single source of truth: every role known to ChapsMind is declared in the
# realm export JSON. The setup and doctor scripts derive the role set from it
# so we never have three conflicting lists to keep in sync.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REALM_JSON="$SCRIPT_DIR/../files/realm-chapsmind.json"
# Repo-relative path used in user-facing messages — the absolute path is too
# noisy and shifts between environments.
REALM_JSON_DISPLAY="infra/files/$(basename "$REALM_JSON")"

if [ ! -f "$REALM_JSON" ]; then
    echo "❌ Realm JSON not found at $REALM_JSON_DISPLAY" >&2
    echo "   This file is the source of truth for roles. Aborting." >&2
    exit 1
fi
if ! jq empty "$REALM_JSON" 2>/dev/null; then
    echo "❌ Realm JSON at $REALM_JSON_DISPLAY is not valid JSON. Aborting." >&2
    exit 1
fi

# ─── Defaults ─────────────────────────────────────────
KEYCLOAK_URL="${KEYCLOAK_URL:-http://localhost:8080}"
ADMIN_USER="${KEYCLOAK_ADMIN:-admin}"
ADMIN_PASS="${KEYCLOAK_ADMIN_PASSWORD:-admin}"
REALM="${KEYCLOAK_REALM:-chapsmind}"
AUTH_REALM="master"
DOMAIN="localhost"

OPT_WAIT=false
OPT_THEME=false
OPT_CREATE_ROLES=false
OPT_CREATE_CLIENTS=false
OPT_CREATE_ORG=""
OPT_ORG_DOMAIN=""
OPT_ORG_ALIAS=""
OPT_ADD_USERS=""

# ─── Parse options ────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --url)            KEYCLOAK_URL="$2";      shift 2 ;;
        --user)           ADMIN_USER="$2";         shift 2 ;;
        --pass)           ADMIN_PASS="$2";         shift 2 ;;
        --realm)          REALM="$2";              shift 2 ;;
        --auth-realm)     AUTH_REALM="$2";         shift 2 ;;
        --domain)         DOMAIN="$2";             shift 2 ;;
        --wait)           OPT_WAIT=true;           shift ;;
        --theme)          OPT_THEME=true;          shift ;;
        --create-roles)   OPT_CREATE_ROLES=true;   shift ;;
        --create-clients) OPT_CREATE_CLIENTS=true; shift ;;
        --create-org)     OPT_CREATE_ORG="$2";     shift 2 ;;
        --org-domain)     OPT_ORG_DOMAIN="$2";     shift 2 ;;
        --org-alias)      OPT_ORG_ALIAS="$2";      shift 2 ;;
        --add-users)      OPT_ADD_USERS="$2";      shift 2 ;;
        -h|--help)
            sed -n '2,/^$/{ s/^# //; s/^#$//; p }' "$0"
            exit 0 ;;
        *)
            echo "❌ Unknown option: $1" >&2
            exit 1 ;;
    esac
done

echo "🔐 Setting up Keycloak"
echo "   URL:   $KEYCLOAK_URL"
echo "   Realm: $REALM"
echo ""

# ─── Helpers ──────────────────────────────────────────
kc() {
    local method=$1 path=$2
    shift 2
    curl -sf -X "$method" "$KEYCLOAK_URL/admin/realms/$REALM$path" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        "$@"
}

kc_get()    { kc GET    "$@"; }
kc_post()   { local path=$1; shift; kc POST  "$path" -d "$@"; }
kc_put()    { local path=$1; shift; kc PUT   "$path" -d "$@"; }
kc_delete() { kc DELETE "$@"; }

# ─── Wait (optional) ─────────────────────────────────
if [ "$OPT_WAIT" = true ]; then
    echo "⏳ Waiting for Keycloak..."
    until curl -sf "$KEYCLOAK_URL/realms/master" > /dev/null 2>&1; do
        echo "   Not ready, retrying..."
        sleep 5
    done
    echo "✅ Keycloak is ready"
fi

# ─── Auth ─────────────────────────────────────────────
echo "🔑 Authenticating..."
ADMIN_TOKEN=$(curl -sf -X POST "$KEYCLOAK_URL/realms/$AUTH_REALM/protocol/openid-connect/token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password&client_id=admin-cli&username=$ADMIN_USER&password=$ADMIN_PASS" \
    | jq -r '.access_token')

if [ -z "$ADMIN_TOKEN" ] || [ "$ADMIN_TOKEN" == "null" ]; then
    echo "❌ Failed to authenticate — check credentials"
    exit 1
fi
echo "✅ Authenticated"

# ─── Verify realm ────────────────────────────────────
REALM_STATUS=$(curl -sf -o /dev/null -w "%{http_code}" "$KEYCLOAK_URL/admin/realms/$REALM" \
    -H "Authorization: Bearer $ADMIN_TOKEN")
if [ "$REALM_STATUS" != "200" ]; then
    echo "❌ Realm '$REALM' not found (HTTP $REALM_STATUS)"
    exit 1
fi
echo "✅ Realm '$REALM' exists"

# ─── Realm settings ───────────────────────────────────
echo ""
echo "🔧 Configuring realm settings..."
if [[ "$DOMAIN" == "localhost" ]]; then
    SSL_REQUIRED="none"
else
    SSL_REQUIRED="external"
fi
kc_put "" "{
    \"organizationsEnabled\": true,
    \"accessTokenLifespan\": 3600,
    \"ssoSessionIdleTimeout\": 86400,
    \"ssoSessionMaxLifespan\": 604800,
    \"sslRequired\": \"$SSL_REQUIRED\",
    \"registrationAllowed\": false,
    \"resetPasswordAllowed\": true
}"
echo "✅ Realm settings configured (organizations, token lifespans, SSL)"

# ─── Theme (optional) ────────────────────────────────
if [ "$OPT_THEME" = true ]; then
    echo ""
    echo "🎨 Configuring ChapsMind theme..."
    kc_put "" '{"loginTheme": "chapsmind", "emailTheme": "chapsmind"}'
    echo "✅ Theme configured (login + email)"
fi

# ─── Shared data ──────────────────────────────────────

PROTOCOL_MAPPERS='[
    {
        "name": "organization-mapper",
        "protocol": "openid-connect",
        "protocolMapper": "oidc-organization-membership-mapper",
        "consentRequired": false,
        "config": {
            "id.token.claim": "true",
            "access.token.claim": "true",
            "claim.name": "organization",
            "userinfo.token.claim": "true",
            "addOrganizationId": "true",
            "jsonType.label": "JSON"
        }
    },
    {
        "name": "subject-mapper",
        "protocol": "openid-connect",
        "protocolMapper": "oidc-sub-mapper",
        "consentRequired": false,
        "config": {
            "id.token.claim": "true",
            "access.token.claim": "true"
        }
    }
]'

ORG_MAPPER_PAYLOAD='{
    "name": "organization-mapper",
    "protocol": "openid-connect",
    "protocolMapper": "oidc-organization-membership-mapper",
    "consentRequired": false,
    "config": {
        "id.token.claim": "true",
        "access.token.claim": "true",
        "claim.name": "organization",
        "userinfo.token.claim": "true",
        "addOrganizationId": "true",
        "jsonType.label": "JSON"
    }
}'

DEFAULT_SCOPE_NAMES="web-origins acr profile roles email organization"

# ─── Roles (optional) ────────────────────────────────
if [ "$OPT_CREATE_ROLES" = true ]; then
    echo ""
    echo "🔧 Syncing realm roles from $REALM_JSON_DISPLAY..."

    ALL_ROLES=$(kc_get "/roles")
    EXISTING_ROLE_NAMES=$(echo "$ALL_ROLES" | jq -r '.[].name')

    # 1. Create every role declared in the realm JSON (composite ones included,
    #    they are upgraded to composite later). Update the description on roles
    #    that already exist so the JSON stays the canonical reference.
    while IFS=$'\t' read -r name description; do
        [ -z "$name" ] && continue
        if echo "$EXISTING_ROLE_NAMES" | grep -qx "$name"; then
            current_desc=$(echo "$ALL_ROLES" | jq -r --arg n "$name" '.[] | select(.name==$n) | .description // ""')
            if [ "$current_desc" != "$description" ]; then
                kc_put "/roles/$name" "$(jq -n \
                    --arg name "$name" \
                    --arg desc "$description" \
                    '{name: $name, description: $desc}')"
                echo "   ✏️  $name (description updated)"
            else
                echo "   ✅ $name (exists)"
            fi
        else
            kc_post "/roles" "$(jq -n \
                --arg name "$name" \
                --arg desc "$description" \
                '{name: $name, description: $desc, composite: false, clientRole: false}')"
            echo "   ✅ $name (created)"
        fi
    done < <(jq -r '.roles.realm[] | [.name, (.description // "")] | @tsv' "$REALM_JSON")

    # 2. Drop legacy roles that the realm JSON no longer declares. Without this
    #    step a role removed from the source of truth (e.g. admin.workflows)
    #    survives forever on long-lived Keycloak instances.
    DECLARED_ROLES=$(jq -r '.roles.realm[].name' "$REALM_JSON")
    PROTECTED_ROLES="offline_access uma_authorization default-roles-$REALM"
    while IFS= read -r role; do
        [ -z "$role" ] && continue
        # Skip Keycloak built-ins.
        if echo "$PROTECTED_ROLES" | tr ' ' '\n' | grep -qx "$role"; then
            continue
        fi
        # Keep only roles still in the source of truth.
        if echo "$DECLARED_ROLES" | grep -qx "$role"; then
            continue
        fi
        kc_delete "/roles/$role" >/dev/null 2>&1 || true
        echo "   🗑  $role (removed — no longer declared in $REALM_JSON_DISPLAY)"
    done <<<"$EXISTING_ROLE_NAMES"

    # 3. Configure composite roles from the JSON declaration. Re-fetch role IDs
    #    after creations/deletions so we work on a fresh snapshot.
    ALL_ROLES=$(kc_get "/roles")
    while IFS= read -r composite_name; do
        [ -z "$composite_name" ] && continue
        composite_id=$(echo "$ALL_ROLES" | jq -r --arg n "$composite_name" '.[] | select(.name==$n) | .id')
        if [ -z "$composite_id" ]; then
            echo "   ⚠️  $composite_name not found, skipping composite wiring" >&2
            continue
        fi
        # Pull child names from the JSON, then map them to live role objects.
        members_json=$(jq -c --arg n "$composite_name" \
            '[.roles.realm[] | select(.name==$n) | .composites.realm[]?]' "$REALM_JSON")
        members_payload=$(echo "$ALL_ROLES" | jq -c --argjson members "$members_json" \
            '[.[] | select(.name as $n | $members | index($n))]')
        kc_post "/roles-by-id/$composite_id/composites" "$members_payload"
        echo "   ✅ $composite_name composite synced ($(echo "$members_json" | jq 'length') children)"
    done < <(jq -r '.roles.realm[] | select(.composite==true) | .name' "$REALM_JSON")
fi

# ─── Cache: clients and scopes ────────────────────────
ALL_CLIENTS=$(kc_get "/clients")
ALL_SCOPES=$(kc_get "/client-scopes")

client_uuid() {
    echo "$ALL_CLIENTS" | jq -r ".[] | select(.clientId==\"$1\") | .id"
}

declare -A CLIENT_UUIDS

# ─── Clients (optional) ──────────────────────────────
if [ "$OPT_CREATE_CLIENTS" = true ]; then
    echo ""
    echo "🔧 Creating clients..."

    # Determine webOrigins based on domain
    if [[ "$DOMAIN" == "localhost" ]]; then
        WEB_ORIGINS="\"http://$DOMAIN\""
    elif [[ "$DOMAIN" == *"*"* ]]; then
        # Keycloak webOrigins does not support wildcards — use "*" to allow all
        WEB_ORIGINS="\"*\""
    else
        WEB_ORIGINS="\"https://$DOMAIN\""
    fi

    create_or_update_client() {
        local client_id=$1
        local config=$2

        local uuid
        uuid=$(client_uuid "$client_id")

        if [ -n "$uuid" ] && [ "$uuid" != "null" ]; then
            echo "   ✅ $client_id (exists — UUID: $uuid)"
        else
            kc_post "/clients" "$config"
            ALL_CLIENTS=$(kc_get "/clients")
            uuid=$(client_uuid "$client_id")
            echo "   ✅ $client_id (created — UUID: $uuid)"
        fi

        CLIENT_UUIDS[$client_id]=$uuid

        # Assign default scopes (remove from optional first, then add as default)
        for scope_name in $DEFAULT_SCOPE_NAMES; do
            local scope_id
            scope_id=$(echo "$ALL_SCOPES" | jq -r ".[] | select(.name==\"$scope_name\") | .id")
            if [ -n "$scope_id" ] && [ "$scope_id" != "null" ]; then
                # Remove from optional scopes (Keycloak ignores PUT to default if already optional)
                curl -sf -X DELETE "$KEYCLOAK_URL/admin/realms/$REALM/clients/$uuid/optional-client-scopes/$scope_id" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" 2>/dev/null || true
                # Add as default scope
                curl -sf -X PUT "$KEYCLOAK_URL/admin/realms/$REALM/clients/$uuid/default-client-scopes/$scope_id" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" \
                    -H "Content-Type: application/json" 2>/dev/null || true
            fi
        done
    }

    create_or_update_client "chapsmind-front" "$(cat <<JSONEOF
{
    "clientId": "chapsmind-front",
    "name": "ChapsMind Frontend",
    "enabled": true,
    "publicClient": true,
    "standardFlowEnabled": true,
    "directAccessGrantsEnabled": true,
    "serviceAccountsEnabled": false,
    "redirectUris": ["*"],
    "webOrigins": [$WEB_ORIGINS],
    "attributes": {
        "pkce.code.challenge.method": "S256",
        "post.logout.redirect.uris": "/login"
    },
    "protocolMappers": $PROTOCOL_MAPPERS
}
JSONEOF
)"

    create_or_update_client "chapsmind-global-service-back" "$(cat <<JSONEOF
{
    "clientId": "chapsmind-global-service-back",
    "name": "ChapsMind Backend",
    "enabled": true,
    "publicClient": false,
    "standardFlowEnabled": true,
    "directAccessGrantsEnabled": true,
    "serviceAccountsEnabled": false,
    "redirectUris": ["*"],
    "webOrigins": [$WEB_ORIGINS],
    "protocolMappers": $PROTOCOL_MAPPERS
}
JSONEOF
)"

    BACKEND_SECRET=$(kc_get "/clients/${CLIENT_UUIDS[chapsmind-global-service-back]}/client-secret" | jq -r '.value')
    echo "   🔑 chapsmind-global-service-back secret: $BACKEND_SECRET"

    create_or_update_client "chapsmind-admin" "$(cat <<JSONEOF
{
    "clientId": "chapsmind-admin",
    "name": "ChapsMind Admin Service Account",
    "enabled": true,
    "publicClient": false,
    "standardFlowEnabled": false,
    "directAccessGrantsEnabled": false,
    "serviceAccountsEnabled": true,
    "fullScopeAllowed": true,
    "protocolMappers": $PROTOCOL_MAPPERS
}
JSONEOF
)"

    ADMIN_CLIENT_SECRET=$(kc_get "/clients/${CLIENT_UUIDS[chapsmind-admin]}/client-secret" | jq -r '.value')
    echo "   🔑 chapsmind-admin secret: $ADMIN_CLIENT_SECRET"
fi

# ─── Populate CLIENT_UUIDS for existing clients ──────
for cname in "chapsmind-front" "chapsmind-global-service-back" "chapsmind-admin"; do
    if [ -z "${CLIENT_UUIDS[$cname]+x}" ]; then
        CLIENT_UUIDS[$cname]=$(client_uuid "$cname")
    fi
done

# ─── Service account roles ────────────────────────────
echo ""
echo "🔧 Assigning realm-management roles to chapsmind-admin service account..."

ADMIN_CLIENT_UUID="${CLIENT_UUIDS[chapsmind-admin]}"
if [ -z "$ADMIN_CLIENT_UUID" ] || [ "$ADMIN_CLIENT_UUID" == "null" ]; then
    echo "❌ Client 'chapsmind-admin' not found"
    exit 1
fi

SERVICE_ACCOUNT_ID=$(kc_get "/clients/$ADMIN_CLIENT_UUID/service-account-user" | jq -r '.id')
REALM_MGMT_UUID=$(client_uuid "realm-management")

MGMT_ROLES=$(kc_get "/clients/$REALM_MGMT_UUID/roles")
ROLES_TO_ASSIGN=$(echo "$MGMT_ROLES" | jq '[.[] | select(.name | test("^(realm-admin|manage-users|view-users|query-users|manage-clients|view-clients)$"))]')

kc_post "/users/$SERVICE_ACCOUNT_ID/role-mappings/clients/$REALM_MGMT_UUID" "$ROLES_TO_ASSIGN"
echo "✅ Service account roles assigned"

# ─── Organization mapper in dedicated scopes ──────────
echo ""
echo "🔧 Configuring organization mappers in client dedicated scopes..."

for client_name in "chapsmind-front" "chapsmind-global-service-back" "chapsmind-admin"; do
    CLIENT_UUID="${CLIENT_UUIDS[$client_name]}"
    [ -z "$CLIENT_UUID" ] || [ "$CLIENT_UUID" == "null" ] && continue

    DEDICATED_SCOPE_NAME="${client_name}-dedicated"
    DEDICATED_SCOPE_ID=""

    for endpoint in "/clients/$CLIENT_UUID/default-client-scopes" "/clients/$CLIENT_UUID/optional-client-scopes" "/client-scopes"; do
        DEDICATED_SCOPE_ID=$(kc_get "$endpoint" | jq -r ".[] | select(.name==\"$DEDICATED_SCOPE_NAME\") | .id")
        [ -n "$DEDICATED_SCOPE_ID" ] && [ "$DEDICATED_SCOPE_ID" != "null" ] && break
        DEDICATED_SCOPE_ID=""
    done

    if [ -n "$DEDICATED_SCOPE_ID" ]; then
        MAPPERS=$(kc_get "/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models")
        EXISTING_MAPPER=$(echo "$MAPPERS" | jq '.[] | select(.name=="organization-mapper")')
        EXISTING_MAPPER_ID=$(echo "$EXISTING_MAPPER" | jq -r '.id // empty')

        if [ -n "$EXISTING_MAPPER_ID" ]; then
            UPDATED_MAPPER=$(echo "$EXISTING_MAPPER" | jq '
                .config."addOrganizationId" = "true" |
                .config."jsonType.label" = "JSON"')
            kc_put "/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models/$EXISTING_MAPPER_ID" "$UPDATED_MAPPER"
            echo "   ✅ $client_name — organization mapper updated (dedicated scope)"
        else
            kc_post "/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models" "$ORG_MAPPER_PAYLOAD"
            echo "   ✅ $client_name — organization mapper created (dedicated scope)"
        fi
    else
        # Fallback: add/update mapper directly on client
        echo "   ℹ️  Dedicated scope not found for $client_name, using client mappers"
        MAPPERS=$(kc_get "/clients/$CLIENT_UUID/protocol-mappers/models")
        EXISTING_MAPPER=$(echo "$MAPPERS" | jq '.[] | select(.name=="organization-mapper")')
        EXISTING_MAPPER_ID=$(echo "$EXISTING_MAPPER" | jq -r '.id // empty')

        if [ -n "$EXISTING_MAPPER_ID" ]; then
            UPDATED_MAPPER=$(echo "$EXISTING_MAPPER" | jq '
                .config."addOrganizationId" = "true" |
                .config."jsonType.label" = "JSON"')
            kc_put "/clients/$CLIENT_UUID/protocol-mappers/models/$EXISTING_MAPPER_ID" "$UPDATED_MAPPER"
            echo "   ✅ $client_name — organization mapper updated (client)"
        else
            kc_post "/clients/$CLIENT_UUID/protocol-mappers/models" "$ORG_MAPPER_PAYLOAD"
            echo "   ✅ $client_name — organization mapper created (client)"
        fi
    fi
done

# ─── Create organization (optional) ──────────────────
if [ -n "$OPT_CREATE_ORG" ]; then
    echo ""
    echo "🏢 Creating organization '$OPT_CREATE_ORG'..."

    ORG_DOMAIN="${OPT_ORG_DOMAIN:-$DOMAIN}"
    ORG_ALIAS="${OPT_ORG_ALIAS:-$(echo "$OPT_CREATE_ORG" | tr '[:upper:] ' '[:lower:]-')}"

    ORGS=$(kc_get "/organizations" 2>/dev/null || echo "[]")
    ORG_ID=$(echo "$ORGS" | jq -r ".[] | select(.name==\"$OPT_CREATE_ORG\") | .id")

    if [ -n "$ORG_ID" ] && [ "$ORG_ID" != "null" ]; then
        echo "✅ Organization already exists (ID: $ORG_ID)"
    else
        kc_post "/organizations" "$(cat <<JSONEOF
{
    "name": "$OPT_CREATE_ORG",
    "alias": "$ORG_ALIAS",
    "enabled": true,
    "domains": [
        {"name": "$ORG_DOMAIN", "verified": true}
    ]
}
JSONEOF
)"
        ORG_ID=$(kc_get "/organizations" | jq -r ".[] | select(.name==\"$OPT_CREATE_ORG\") | .id")
        echo "✅ Organization created (ID: $ORG_ID)"
    fi
fi

# ─── Add users to organization (optional) ────────────
if [ -n "$OPT_ADD_USERS" ] && [ -n "${ORG_ID:-}" ]; then
    echo ""
    echo "👥 Adding users to organization..."

    IFS=',' read -ra USERS <<< "$OPT_ADD_USERS"
    for username in "${USERS[@]}"; do
        USER_ID=$(kc_get "/users?username=$username" | jq -r '.[0].id')

        if [ -n "$USER_ID" ] && [ "$USER_ID" != "null" ]; then
            RESULT=$(curl -s -w "\n%{http_code}" -X POST "$KEYCLOAK_URL/admin/realms/$REALM/organizations/$ORG_ID/members" \
                -H "Authorization: Bearer $ADMIN_TOKEN" \
                -H "Content-Type: application/json" \
                -d "\"$USER_ID\"")
            HTTP_CODE=$(echo "$RESULT" | tail -n1)

            case "$HTTP_CODE" in
                200|201|204) echo "   ✅ Added $username" ;;
                409)         echo "   ℹ️  $username already in organization" ;;
                *)           echo "   ⚠️  Failed to add $username (HTTP: $HTTP_CODE)" ;;
            esac
        else
            echo "   ⚠️  User $username not found, skipping"
        fi
    done
elif [ -n "$OPT_ADD_USERS" ]; then
    echo "⚠️  Cannot add users: no organization created or found"
fi

# ─── Summary ─────────────────────────────────────────
echo ""
echo "════════════════════════════════════════════════════"
echo "🎉 Keycloak setup complete!"

if [ "$OPT_CREATE_CLIENTS" = true ]; then
    echo ""
    echo "📋 Environment variables for your deployment:"
    echo ""
    echo "   # Backend"
    echo "   KEYCLOAK_SERVER_URL=$KEYCLOAK_URL"
    echo "   KEYCLOAK_REALM=$REALM"
    echo "   KEYCLOAK_CLIENT_ID=chapsmind-global-service-back"
    echo "   KEYCLOAK_CLIENT_SECRET=${BACKEND_SECRET:-<see Keycloak console>}"
    echo "   KEYCLOAK_ADMIN_CLIENT_ID=chapsmind-admin"
    echo "   KEYCLOAK_ADMIN_CLIENT_SECRET=${ADMIN_CLIENT_SECRET:-<see Keycloak console>}"
    echo ""
    echo "   # Frontend"
    echo "   VITE_KEYCLOAK_URL=$KEYCLOAK_URL"
    echo "   VITE_KEYCLOAK_REALM=$REALM"
    echo "   VITE_KEYCLOAK_CLIENT_ID=chapsmind-front"
fi

if [ -n "$OPT_ADD_USERS" ]; then
    echo ""
    echo "👤 Test users: $OPT_ADD_USERS"
fi

echo "════════════════════════════════════════════════════"