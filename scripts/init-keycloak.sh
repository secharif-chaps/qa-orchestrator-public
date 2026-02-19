#!/bin/bash
# Initialize Keycloak with proper service account roles
# Run this after Keycloak starts: ./scripts/init-keycloak.sh

set -e

KEYCLOAK_URL="${KEYCLOAK_URL:-http://localhost:8080}"
ADMIN_USER="${KEYCLOAK_ADMIN:-admin}"
ADMIN_PASS="${KEYCLOAK_ADMIN_PASSWORD:-admin}"
REALM="chapsmind"

echo "🔐 Configuring Keycloak..."
echo "   URL: $KEYCLOAK_URL"
echo "   Realm: $REALM"

# Wait for Keycloak to be ready
echo "⏳ Waiting for Keycloak..."
until curl -sf "$KEYCLOAK_URL/realms/master" > /dev/null 2>&1; do
    echo "   Keycloak not ready, waiting..."
    sleep 5
done
echo "✅ Keycloak is ready"

# Get admin token
echo "🔑 Getting admin token..."
ADMIN_TOKEN=$(curl -sf -X POST "$KEYCLOAK_URL/realms/master/protocol/openid-connect/token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password" \
    -d "client_id=admin-cli" \
    -d "username=$ADMIN_USER" \
    -d "password=$ADMIN_PASS" | jq -r '.access_token')

if [ -z "$ADMIN_TOKEN" ] || [ "$ADMIN_TOKEN" == "null" ]; then
    echo "❌ Failed to get admin token"
    exit 1
fi
echo "✅ Got admin token"

# Check if realm exists
echo "🔍 Checking if realm '$REALM' exists..."
REALM_EXISTS=$(curl -sf -o /dev/null -w "%{http_code}" "$KEYCLOAK_URL/admin/realms/$REALM" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

if [ "$REALM_EXISTS" != "200" ]; then
    echo "❌ Realm '$REALM' not found. Make sure the realm was imported."
    exit 1
fi
echo "✅ Realm exists"

# Set ChapsMind theme for login and email
echo "🎨 Configuring ChapsMind theme..."
curl -sf -X PUT "$KEYCLOAK_URL/admin/realms/$REALM" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{"loginTheme": "chapsmind", "emailTheme": "chapsmind"}'
echo "✅ Theme configured (login + email)"

# Get the chapsmind-admin client ID
echo "🔍 Getting chapsmind-admin client..."
CLIENT_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[] | select(.clientId=="chapsmind-admin") | .id')

if [ -z "$CLIENT_ID" ] || [ "$CLIENT_ID" == "null" ]; then
    echo "❌ Client 'chapsmind-admin' not found"
    exit 1
fi
echo "✅ Found chapsmind-admin client: $CLIENT_ID"

# Get service account user ID
echo "🔍 Getting service account user..."
SERVICE_ACCOUNT_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_ID/service-account-user" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.id')

if [ -z "$SERVICE_ACCOUNT_ID" ] || [ "$SERVICE_ACCOUNT_ID" == "null" ]; then
    echo "❌ Service account user not found"
    exit 1
fi
echo "✅ Found service account user: $SERVICE_ACCOUNT_ID"

# Get realm-management client ID
echo "🔍 Getting realm-management client..."
REALM_MGMT_CLIENT_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[] | select(.clientId=="realm-management") | .id')

if [ -z "$REALM_MGMT_CLIENT_ID" ] || [ "$REALM_MGMT_CLIENT_ID" == "null" ]; then
    echo "❌ realm-management client not found"
    exit 1
fi
echo "✅ Found realm-management client: $REALM_MGMT_CLIENT_ID"

# Get available realm-management roles
echo "🔍 Getting realm-management roles..."
ROLES=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients/$REALM_MGMT_CLIENT_ID/roles" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

# Get IDs for required roles
REALM_ADMIN_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="realm-admin")')
MANAGE_USERS_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="manage-users")')
VIEW_USERS_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="view-users")')
QUERY_USERS_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="query-users")')
MANAGE_CLIENTS_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="manage-clients")')
VIEW_CLIENTS_ROLE=$(echo "$ROLES" | jq '.[] | select(.name=="view-clients")')

# Assign roles to service account
echo "🔧 Assigning realm-management roles to service account..."
ROLES_TO_ASSIGN="[$REALM_ADMIN_ROLE, $MANAGE_USERS_ROLE, $VIEW_USERS_ROLE, $QUERY_USERS_ROLE, $MANAGE_CLIENTS_ROLE, $VIEW_CLIENTS_ROLE]"

curl -sf -X POST "$KEYCLOAK_URL/admin/realms/$REALM/users/$SERVICE_ACCOUNT_ID/role-mappings/clients/$REALM_MGMT_CLIENT_ID" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d "$ROLES_TO_ASSIGN"

echo "✅ Roles assigned successfully"

# Configure organization mapper in each client's dedicated scope
echo "🔧 Configuring organization mappers in client dedicated scopes..."

CLIENTS=("chapsmind-front" "chapsmind-screen-back" "chapsmind-admin")

for client_name in "${CLIENTS[@]}"; do
    # Get client UUID
    CLIENT_UUID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients" \
        -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r ".[] | select(.clientId==\"$client_name\") | .id")

    if [ -n "$CLIENT_UUID" ] && [ "$CLIENT_UUID" != "null" ]; then
        # Get the dedicated client scope for this client
        DEDICATED_SCOPE_NAME="${client_name}-dedicated"

        # Try to get the dedicated scope through the client's assigned scopes
        DEDICATED_SCOPE_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_UUID/default-client-scopes" \
            -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r ".[] | select(.name==\"$DEDICATED_SCOPE_NAME\") | .id")

        # If not in default scopes, check optional scopes
        if [ -z "$DEDICATED_SCOPE_ID" ] || [ "$DEDICATED_SCOPE_ID" == "null" ]; then
            DEDICATED_SCOPE_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_UUID/optional-client-scopes" \
                -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r ".[] | select(.name==\"$DEDICATED_SCOPE_NAME\") | .id")
        fi

        # If still not found, get it from global client-scopes (Keycloak 26+ may expose it there)
        if [ -z "$DEDICATED_SCOPE_ID" ] || [ "$DEDICATED_SCOPE_ID" == "null" ]; then
            DEDICATED_SCOPE_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/client-scopes" \
                -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r ".[] | select(.name==\"$DEDICATED_SCOPE_NAME\") | .id")
        fi

        if [ -n "$DEDICATED_SCOPE_ID" ] && [ "$DEDICATED_SCOPE_ID" != "null" ]; then
            # Check if organization-mapper already exists in this scope
            EXISTING_MAPPER=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models" \
                -H "Authorization: Bearer $ADMIN_TOKEN" | jq '.[] | select(.name=="organization-mapper")')
            EXISTING_MAPPER_ID=$(echo "$EXISTING_MAPPER" | jq -r '.id // empty')

            if [ -n "$EXISTING_MAPPER_ID" ]; then
                # Update existing mapper
                UPDATED_MAPPER=$(echo "$EXISTING_MAPPER" | jq '.config.addOrganizationId = "true"')
                curl -sf -X PUT "$KEYCLOAK_URL/admin/realms/$REALM/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models/$EXISTING_MAPPER_ID" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" \
                    -H "Content-Type: application/json" \
                    -d "$UPDATED_MAPPER"
                echo "   ✅ Updated organization mapper in $client_name dedicated scope"
            else
                # Create new organization mapper with addOrganizationId enabled
                curl -sf -X POST "$KEYCLOAK_URL/admin/realms/$REALM/client-scopes/$DEDICATED_SCOPE_ID/protocol-mappers/models" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" \
                    -H "Content-Type: application/json" \
                    -d '{
                        "name": "organization-mapper",
                        "protocol": "openid-connect",
                        "protocolMapper": "oidc-organization-membership-mapper",
                        "consentRequired": false,
                        "config": {
                            "id.token.claim": "true",
                            "access.token.claim": "true",
                            "claim.name": "organization",
                            "userinfo.token.claim": "true",
                            "addOrganizationId": "true"
                        }
                    }'
                echo "   ✅ Created organization mapper in $client_name dedicated scope"
            fi
        else
            # Fallback: add mapper directly to client's protocol mappers
            echo "   ℹ️  Dedicated scope not found for $client_name, adding mapper directly to client"

            # Check if organization-mapper already exists on the client
            EXISTING_MAPPER=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_UUID/protocol-mappers/models" \
                -H "Authorization: Bearer $ADMIN_TOKEN" | jq '.[] | select(.name=="organization-mapper")')
            EXISTING_MAPPER_ID=$(echo "$EXISTING_MAPPER" | jq -r '.id // empty')

            if [ -n "$EXISTING_MAPPER_ID" ]; then
                # Update existing mapper
                UPDATED_MAPPER=$(echo "$EXISTING_MAPPER" | jq '.config.addOrganizationId = "true"')
                curl -sf -X PUT "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_UUID/protocol-mappers/models/$EXISTING_MAPPER_ID" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" \
                    -H "Content-Type: application/json" \
                    -d "$UPDATED_MAPPER"
                echo "   ✅ Updated organization mapper on $client_name"
            else
                # Create new organization mapper
                curl -sf -X POST "$KEYCLOAK_URL/admin/realms/$REALM/clients/$CLIENT_UUID/protocol-mappers/models" \
                    -H "Authorization: Bearer $ADMIN_TOKEN" \
                    -H "Content-Type: application/json" \
                    -d '{
                        "name": "organization-mapper",
                        "protocol": "openid-connect",
                        "protocolMapper": "oidc-organization-membership-mapper",
                        "consentRequired": false,
                        "config": {
                            "id.token.claim": "true",
                            "access.token.claim": "true",
                            "claim.name": "organization",
                            "userinfo.token.claim": "true",
                            "addOrganizationId": "true"
                        }
                    }'
                echo "   ✅ Created organization mapper on $client_name"
            fi
        fi
    else
        echo "   ⚠️  Client $client_name not found"
    fi
done

# Disable organization authentication step in browser flow
echo "🔧 Configuring browser authentication flow..."

# Get browser flow ID
BROWSER_FLOW_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/authentication/flows" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[] | select(.alias=="browser") | .id')

if [ -n "$BROWSER_FLOW_ID" ] && [ "$BROWSER_FLOW_ID" != "null" ]; then
    # Get flow executions
    EXECUTIONS=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/authentication/flows/browser/executions" \
        -H "Authorization: Bearer $ADMIN_TOKEN")

    # Find and disable organization identity first execution if it exists
    ORG_EXEC_ID=$(echo "$EXECUTIONS" | jq -r '.[] | select(.displayName | contains("Organization")) | .id' 2>/dev/null || echo "")

    if [ -n "$ORG_EXEC_ID" ] && [ "$ORG_EXEC_ID" != "null" ]; then
        # Set organization step to DISABLED
        curl -sf -X PUT "$KEYCLOAK_URL/admin/realms/$REALM/authentication/flows/browser/executions" \
            -H "Authorization: Bearer $ADMIN_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"id\": \"$ORG_EXEC_ID\", \"requirement\": \"DISABLED\"}" 2>/dev/null || true
        echo "✅ Organization authentication step disabled"
    else
        echo "ℹ️  No organization authentication step found in browser flow"
    fi
else
    echo "⚠️  Browser flow not found, skipping authentication configuration"
fi

# Create organization.manage realm role if it doesn't exist
echo "🔧 Creating organization.manage realm role..."
REALM_ROLES=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/roles" \
    -H "Authorization: Bearer $ADMIN_TOKEN")

ORG_MANAGE_ROLE_EXISTS=$(echo "$REALM_ROLES" | jq '.[] | select(.name=="organization.manage")')

if [ -z "$ORG_MANAGE_ROLE_EXISTS" ]; then
    curl -sf -X POST "$KEYCLOAK_URL/admin/realms/$REALM/roles" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "organization.manage",
            "description": "Manage organization members and settings - grants access to team management features",
            "composite": false,
            "clientRole": false
        }'
    echo "✅ Created organization.manage role"
else
    echo "✅ organization.manage role already exists"
fi

# Create a default organization
echo "🏢 Checking for default organization..."
ORGS=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/organizations" \
    -H "Authorization: Bearer $ADMIN_TOKEN" 2>/dev/null || echo "[]")

ORG_EXISTS=$(echo "$ORGS" | jq -r '.[] | select(.name=="ChapsMind Dev") | .id')

if [ -z "$ORG_EXISTS" ] || [ "$ORG_EXISTS" == "null" ]; then
    echo "📝 Creating default organization..."
    curl -sf -X POST "$KEYCLOAK_URL/admin/realms/$REALM/organizations" \
        -H "Authorization: Bearer $ADMIN_TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "name": "ChapsMind Dev",
            "alias": "chapsmind-dev",
            "enabled": true,
            "domains": [
                {"name": "chapsmind.local", "verified": true}
            ]
        }'
    echo "✅ Organization created"
else
    echo "✅ Organization already exists"
fi

# Get organization ID
ORG_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/organizations" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[] | select(.name=="ChapsMind Dev") | .id')

if [ -z "$ORG_ID" ] || [ "$ORG_ID" == "null" ]; then
    echo "❌ Organization not found, cannot add users"
    exit 1
fi

# Add all test users to the organization
echo "👥 Adding all test users to organization..."
echo "   Organization ID: $ORG_ID"
TEST_USERS=("admin" "company_manager" "company_viewer" "team_manager" "no_access")

for username in "${TEST_USERS[@]}"; do
    USER_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/$REALM/users?username=$username" \
        -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[0].id')

    if [ -n "$USER_ID" ] && [ "$USER_ID" != "null" ]; then
        # Use POST with user ID in body (Keycloak 26 Organizations API)
        RESULT=$(curl -s -w "\n%{http_code}" -X POST "$KEYCLOAK_URL/admin/realms/$REALM/organizations/$ORG_ID/members" \
            -H "Authorization: Bearer $ADMIN_TOKEN" \
            -H "Content-Type: application/json" \
            -d "\"$USER_ID\"")
        HTTP_CODE=$(echo "$RESULT" | tail -n1)

        if [ "$HTTP_CODE" = "204" ] || [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
            echo "   ✅ Added $username to organization (User ID: $USER_ID)"
        elif [ "$HTTP_CODE" = "409" ]; then
            echo "   ℹ️  User $username already in organization"
        else
            echo "   ⚠️  Failed to add $username (HTTP: $HTTP_CODE)"
        fi
    else
        echo "   ⚠️  User $username not found, skipping"
    fi
done

echo ""
echo "🎉 Keycloak configuration complete!"
echo ""
echo "You can now:"
echo "  - Access Keycloak admin: $KEYCLOAK_URL/admin/master/console/"
echo "  - Login to app with any test user:"
echo "      admin / admin123"
echo "      company_manager / manager123"
echo "      company_viewer / viewer123"
echo "      team_manager / teammanager123"
echo "      no_access / noaccess123"
echo ""
