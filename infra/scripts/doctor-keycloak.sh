#!/bin/bash
# Diagnose Keycloak realm configuration for ChapsMind.
# Inspects clients, scopes, mappers, roles, and organizations via the Admin API.
#
# Usage:
#   bash infra/scripts/doctor-keycloak.sh \
#     --url https://sso.deveryware.team/auth \
#     --realm chapsmind-internal \
#     --user admin --pass secret
#
#   # Auth on the target realm (not master)
#   bash infra/scripts/doctor-keycloak.sh \
#     --url https://sso.deveryware.team/auth \
#     --realm chapsmind-internal \
#     --auth-realm chapsmind-internal \
#     --user admin --pass secret

# No set -e: diagnostic script must continue on errors to report all issues
set -uo pipefail

KEYCLOAK_URL=""
REALM=""
AUTH_REALM=""
ADMIN_USER=""
ADMIN_PASS=""

ERRORS=0
WARNINGS=0

err()  { echo "   ❌ $*"; ERRORS=$((ERRORS + 1)); }
warn() { echo "   ⚠️  $*"; WARNINGS=$((WARNINGS + 1)); }
ok()   { echo "   ✅ $*"; }
info() { echo "   ℹ️  $*"; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --url)        KEYCLOAK_URL="$2"; shift 2 ;;
        --realm)      REALM="$2";        shift 2 ;;
        --auth-realm) AUTH_REALM="$2";   shift 2 ;;
        --user)       ADMIN_USER="$2";   shift 2 ;;
        --pass)       ADMIN_PASS="$2";   shift 2 ;;
        -h|--help)
            sed -n '2,/^$/{ s/^# //; s/^#$//; p }' "$0"
            exit 0 ;;
        *) echo "❌ Unknown option: $1" >&2; exit 1 ;;
    esac
done

for var in KEYCLOAK_URL REALM ADMIN_USER ADMIN_PASS; do
    if [ -z "${!var}" ]; then
        echo "❌ Missing --$(echo "$var" | tr '[:upper:]' '[:lower:]' | tr '_' '-')" >&2
        exit 1
    fi
done

KEYCLOAK_URL="${KEYCLOAK_URL%/}"

kc_get() {
    curl -sf "$KEYCLOAK_URL/admin/realms/$REALM$1" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json"
}

echo "🔍 ChapsMind Keycloak Doctor"
echo "   URL:   $KEYCLOAK_URL"
echo "   Realm: $REALM"
echo ""

# ─── Auth ─────────────────────────────────────────────
# Default auth realm = target realm if not specified
AUTH_REALM="${AUTH_REALM:-$REALM}"
echo "🔑 Authenticating on realm '$AUTH_REALM'..."
ADMIN_TOKEN=$(curl -sf -X POST "$KEYCLOAK_URL/realms/$AUTH_REALM/protocol/openid-connect/token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password&client_id=admin-cli&username=$ADMIN_USER&password=$ADMIN_PASS" \
    | jq -r '.access_token')

if [ -z "$ADMIN_TOKEN" ] || [ "$ADMIN_TOKEN" == "null" ]; then
    echo "❌ Authentication failed — check credentials and auth-realm"
    exit 1
fi
ok "Authenticated"
echo ""

# ─── 1. Realm settings ───────────────────────────────
echo "1️⃣  Realm settings"
REALM_CONFIG=$(kc_get "")

ORGS_ENABLED=$(echo "$REALM_CONFIG" | jq -r '.organizationsEnabled')
if [ "$ORGS_ENABLED" = "true" ]; then ok "Organizations enabled"; else err "Organizations DISABLED"; fi

REG_ALLOWED=$(echo "$REALM_CONFIG" | jq -r '.registrationAllowed')
if [ "$REG_ALLOWED" = "false" ]; then ok "Public registration disabled"; else warn "Public registration enabled"; fi

SSL_REQUIRED=$(echo "$REALM_CONFIG" | jq -r '.sslRequired')
case "$SSL_REQUIRED" in
    external|all) ok "SSL required: $SSL_REQUIRED" ;;
    none)         warn "SSL required: none (acceptable for localhost only)" ;;
esac

TOKEN_LIFESPAN=$(echo "$REALM_CONFIG" | jq -r '.accessTokenLifespan')
SSO_IDLE=$(echo "$REALM_CONFIG" | jq -r '.ssoSessionIdleTimeout')
SSO_MAX=$(echo "$REALM_CONFIG" | jq -r '.ssoSessionMaxLifespan')
info "Access token lifespan: ${TOKEN_LIFESPAN}s ($(( TOKEN_LIFESPAN / 60 ))min)"
info "SSO idle timeout: ${SSO_IDLE}s ($(( SSO_IDLE / 3600 ))h)"
info "SSO max lifespan: ${SSO_MAX}s ($(( SSO_MAX / 86400 ))d)"

echo ""

# ─── 2. Clients ───────────────────────────────────────
echo "2️⃣  Clients"
ALL_CLIENTS=$(kc_get "/clients")
ALL_SCOPES=$(kc_get "/client-scopes")

EXPECTED_CLIENTS="chapsmind-front chapsmind-global-service-back chapsmind-admin"

for client_name in $EXPECTED_CLIENTS; do
    CLIENT_JSON=$(echo "$ALL_CLIENTS" | jq ".[] | select(.clientId==\"$client_name\")")
    if [ -z "$CLIENT_JSON" ]; then
        err "$client_name — NOT FOUND"
        continue
    fi

    CLIENT_UUID=$(echo "$CLIENT_JSON" | jq -r '.id')
    IS_PUBLIC=$(echo "$CLIENT_JSON" | jq -r '.publicClient')
    SA_ENABLED=$(echo "$CLIENT_JSON" | jq -r '.serviceAccountsEnabled')
    REDIRECT_URIS=$(echo "$CLIENT_JSON" | jq -c '.redirectUris')
    WEB_ORIGINS=$(echo "$CLIENT_JSON" | jq -c '.webOrigins')

    echo ""
    echo "   📦 $client_name"

    # Client type checks
    case "$client_name" in
        chapsmind-front)
            if [ "$IS_PUBLIC" = "true" ]; then ok "Public client"; else err "Should be public client"; fi
            PKCE=$(echo "$CLIENT_JSON" | jq -r '.attributes["pkce.code.challenge.method"] // empty')
            if [ "$PKCE" = "S256" ]; then ok "PKCE: S256"; else warn "PKCE not configured (recommended: S256)"; fi
            ;;
        chapsmind-global-service-back)
            if [ "$IS_PUBLIC" = "false" ]; then ok "Confidential client"; else err "Should be confidential"; fi
            ;;
        chapsmind-admin)
            if [ "$SA_ENABLED" = "true" ]; then ok "Service account enabled"; else err "Service account NOT enabled"; fi
            ;;
    esac

    info "Redirect URIs: $REDIRECT_URIS"
    info "Web Origins: $WEB_ORIGINS"

    # ─── Check default scopes ─────────────────────────
    DEFAULT_SCOPES=$(kc_get "/clients/$CLIENT_UUID/default-client-scopes" | jq -r '.[].name' | sort)
    OPTIONAL_SCOPES=$(kc_get "/clients/$CLIENT_UUID/optional-client-scopes" | jq -r '.[].name' | sort)

    REQUIRED_SCOPES="email organization profile roles web-origins"
    for scope in $REQUIRED_SCOPES; do
        if echo "$DEFAULT_SCOPES" | grep -qx "$scope"; then
            ok "Scope '$scope' → default"
        elif echo "$OPTIONAL_SCOPES" | grep -qx "$scope"; then
            err "Scope '$scope' → optional (MUST be default)"
        else
            err "Scope '$scope' → not assigned"
        fi
    done

    # ─── Check organization mapper ────────────────────
    DEDICATED_SCOPE_NAME="${client_name}-dedicated"
    DEDICATED_SCOPE_ID=$(echo "$ALL_SCOPES" | jq -r ".[] | select(.name==\"$DEDICATED_SCOPE_NAME\") | .id")
    MAPPER=""

    if [ -n "$DEDICATED_SCOPE_ID" ] && [ "$DEDICATED_SCOPE_ID" != "null" ]; then
        MAPPER=$(kc_get "/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models" | jq '.[] | select(.name=="organization-mapper")')
        [ -n "$MAPPER" ] && info "Organization mapper: in dedicated scope"
    fi

    if [ -z "$MAPPER" ]; then
        MAPPER=$(kc_get "/clients/$CLIENT_UUID/protocol-mappers/models" | jq '.[] | select(.name=="organization-mapper")')
        [ -n "$MAPPER" ] && info "Organization mapper: on client directly"
    fi

    if [ -z "$MAPPER" ]; then
        err "Organization mapper NOT FOUND"
    else
        ADD_TO_ACCESS=$(echo "$MAPPER" | jq -r '.config["access.token.claim"]')
        ADD_TO_ID=$(echo "$MAPPER" | jq -r '.config["id.token.claim"]')
        ADD_ORG_ID=$(echo "$MAPPER" | jq -r '.config["addOrganizationId"]')
        JSON_TYPE=$(echo "$MAPPER" | jq -r '.config["jsonType.label"] // empty')

        if [ "$ADD_TO_ACCESS" = "true" ]; then ok "Mapper → access token"; else err "Mapper NOT in access token"; fi
        if [ "$ADD_TO_ID" = "true" ]; then ok "Mapper → ID token"; else warn "Mapper not in ID token"; fi
        if [ "$ADD_ORG_ID" = "true" ]; then ok "Mapper → includes org ID"; else err "Mapper: org ID NOT included"; fi
        if [ "$JSON_TYPE" = "JSON" ]; then ok "Mapper → claim type: JSON"; else err "Mapper: claim type is '${JSON_TYPE:-not set}' (MUST be 'JSON')"; fi
    fi
done

echo ""

# ─── 3. Roles ─────────────────────────────────────────
echo "3️⃣  Realm roles"
ALL_ROLES=$(kc_get "/roles")

EXPECTED_ROLES="company.create organization.read organization.write organization.manage admin.organizations admin.tasks admin.workflows admin"
for role in $EXPECTED_ROLES; do
    ROLE_JSON=$(echo "$ALL_ROLES" | jq ".[] | select(.name==\"$role\")")
    if [ -n "$ROLE_JSON" ]; then
        IS_COMPOSITE=$(echo "$ROLE_JSON" | jq -r '.composite')
        if [ "$role" = "admin" ]; then
            if [ "$IS_COMPOSITE" = "true" ]; then ok "$role (composite)"; else warn "$role exists but is NOT composite"; fi
        else
            ok "$role"
        fi
    else
        err "$role — NOT FOUND"
    fi
done

echo ""

# ─── 4. Organizations ────────────────────────────────
echo "4️⃣  Organizations"
ORGS=$(kc_get "/organizations" 2>/dev/null || echo "[]")
ORG_COUNT=$(echo "$ORGS" | jq 'length')

if [ "$ORG_COUNT" -eq 0 ]; then
    warn "No organizations — users won't have an organization claim"
else
    ok "$ORG_COUNT organization(s)"
    echo "$ORGS" | jq -r '.[] | "   → \(.name) (ID: \(.id[:8])...)"'
fi

echo ""

# ─── 5. Service account ──────────────────────────────
echo "5️⃣  Service account (chapsmind-admin)"
ADMIN_CLIENT_UUID=$(echo "$ALL_CLIENTS" | jq -r '.[] | select(.clientId=="chapsmind-admin") | .id')

if [ -n "$ADMIN_CLIENT_UUID" ] && [ "$ADMIN_CLIENT_UUID" != "null" ]; then
    SA_USER=$(kc_get "/clients/$ADMIN_CLIENT_UUID/service-account-user" 2>/dev/null)
    if [ -n "$SA_USER" ]; then
        SA_ID=$(echo "$SA_USER" | jq -r '.id')

        REALM_MGMT_UUID=$(echo "$ALL_CLIENTS" | jq -r '.[] | select(.clientId=="realm-management") | .id')
        if [ -n "$REALM_MGMT_UUID" ] && [ "$REALM_MGMT_UUID" != "null" ]; then
            SA_ROLES=$(kc_get "/users/$SA_ID/role-mappings/clients/$REALM_MGMT_UUID" | jq -r '.[].name' 2>/dev/null)
            EXPECTED_SA_ROLES="realm-admin manage-users view-users query-users manage-clients view-clients"
            for role in $EXPECTED_SA_ROLES; do
                if echo "$SA_ROLES" | grep -qx "$role"; then
                    ok "realm-management/$role"
                else
                    warn "realm-management/$role (missing)"
                fi
            done
        fi
    else
        err "Service account user not found"
    fi
else
    err "chapsmind-admin client not found — cannot check service account"
fi

echo ""

# ─── Summary ──────────────────────────────────────────
echo "════════════════════════════════════════════════════"
if [ "$ERRORS" -gt 0 ]; then
    echo "❌ $ERRORS error(s), $WARNINGS warning(s)"
    echo ""
    echo "Fix with: bash infra/scripts/setup-keycloak.sh --create-roles --create-clients ..."
    echo "Or correct manually in the Keycloak console."
    exit 1
elif [ "$WARNINGS" -gt 0 ]; then
    echo "⚠️  $WARNINGS warning(s), no errors — review above"
else
    echo "✅ All checks passed"
fi
