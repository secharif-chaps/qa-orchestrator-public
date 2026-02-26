#!/bin/bash
# Seed sample data: folder and companies for local development
# Run this after the database is initialized and migrations are applied
#
# This script creates:
# - 1 folder named "Sample Companies"
# - 3 sample companies with completed profile data (EDF, ChapsVision, Palantir)
# - Links companies to the folder via folder_items
#
# The organization_id and owner_id will use the local Keycloak IDs

set -e

# Use the dc alias format or full command
DC="${DC:-docker compose -f compose.yaml -f compose.local.yaml}"

echo "🔧 Seeding sample data..."

# First, get the organization ID from local Keycloak
echo "📋 Getting organization ID from Keycloak..."
KEYCLOAK_URL="${KEYCLOAK_URL:-http://localhost:8080}"

# Get admin token
ADMIN_TOKEN=$(curl -sf -X POST "$KEYCLOAK_URL/realms/master/protocol/openid-connect/token" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password&client_id=admin-cli&username=admin&password=admin" | jq -r '.access_token')

if [ -z "$ADMIN_TOKEN" ] || [ "$ADMIN_TOKEN" == "null" ]; then
    echo "❌ Failed to get Keycloak admin token. Is Keycloak running?"
    exit 1
fi

# Get organization ID
ORG_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/chapsmind/organizations" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[0].id')

if [ -z "$ORG_ID" ] || [ "$ORG_ID" == "null" ]; then
    echo "❌ No organization found. Run ./scripts/init-keycloak.sh first."
    exit 1
fi

# Get admin user ID
ADMIN_USER_ID=$(curl -sf "$KEYCLOAK_URL/admin/realms/chapsmind/users?username=admin" \
    -H "Authorization: Bearer $ADMIN_TOKEN" | jq -r '.[0].id')

echo "   Organization ID: $ORG_ID"
echo "   Admin User ID: $ADMIN_USER_ID"

echo ""
echo "📦 Inserting sample data into database..."

$DC exec -T db psql -U postgres -d chapsmind_db << EOF
-- Create a sample folder
INSERT INTO folders (id, organization_id, owner_id, owner, name, color, icon, tags, is_deleted, created_at, updated_at)
VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    '$ORG_ID',
    '$ADMIN_USER_ID',
    'admin',
    'Sample Companies',
    'blue',
    'fa-jelly-duo fa-building',
    ARRAY['demo', 'sample'],
    false,
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    updated_at = NOW();

-- Insert sample companies (only if they don't exist)
-- Company 1: EDF (French energy company)
INSERT INTO companies (name, website, organization_id, owner_id, owner_username, is_deleted, created_at, updated_at, profile, digital, products)
SELECT
    'EDF',
    'https://www.edf.fr/',
    '$ORG_ID',
    '$ADMIN_USER_ID',
    'admin',
    false,
    NOW(),
    NOW(),
    '{"groupName": {"value": "Électricité de France", "source": "Claude Knowledge Base"}, "businessLine": {"value": "Energy production and distribution, electricity and gas supply, nuclear power generation", "source": "https://www.edf.fr"}, "catchphrase": {"value": "Creating a sustainable electricity future", "source": "Mistral Knowledge Base"}, "establishmentYear": {"value": "1946", "source": "Claude Knowledge Base"}, "employeeCount": {"value": "165k", "source": "Claude Knowledge Base"}, "revenue": {"value": "€140B", "source": "Claude Knowledge Base"}, "ceo": {"value": "Luc Rémont", "source": "Claude Knowledge Base"}, "hq": {"value": "Paris, France", "source": "Claude Knowledge Base"}}',
    '{"insights": "EDF is focused on comprehensive digital transformation and innovation across its energy services.", "digitalStrategy": {"value": {"overallStrategy": "Focus on digital transformation to enhance operational efficiency", "digitalTransformation": "Strategic plan Ambitions 2035 with 12 CSR commitments"}}}',
    '{"insights": "EDF offers a wide range of energy production and distribution solutions.", "customerType": "B2B and B2C customers", "range": [{"value": "Electricity supply", "sources": ["https://www.edf.fr"]}, {"value": "Nuclear power generation", "sources": ["https://www.edf.fr"]}]}'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'EDF' AND organization_id = '$ORG_ID');

-- Company 2: ChapsVision (AI company)
INSERT INTO companies (name, website, organization_id, owner_id, owner_username, is_deleted, created_at, updated_at, profile, digital, products)
SELECT
    'ChapsVision',
    'https://www.chapsvision.com/',
    '$ORG_ID',
    '$ADMIN_USER_ID',
    'admin',
    false,
    NOW(),
    NOW(),
    '{"businessLine": {"value": "AI and data analytics software, enterprise AI solutions", "source": "https://www.chapsvision.com"}, "catchphrase": {"value": "The trusted partner for your Agentic AI journey", "source": "https://www.chapsvision.com"}, "establishmentYear": {"value": "2019", "source": "https://www.chapsvision.com"}, "employeeCount": {"value": "1k", "source": "https://www.chapsvision.com"}, "ceo": {"value": "Olivier Dellenbach", "source": "https://www.chapsvision.com"}, "hq": {"value": "Suresnes, France", "source": "https://www.chapsvision.com"}}',
    '{"insights": "ChapsVision is focused on delivering sovereign, secure AI solutions to businesses and governments.", "digitalStrategy": {"value": {"overallStrategy": "Positioned as the trusted partner for Agentic AI journey"}}}',
    '{"insights": "ChapsVision offers AI-driven enterprise solutions.", "customerType": "B2B enterprise customers and government agencies", "range": [{"value": "Chaps Agents - Enterprise platform for Agentic AI", "sources": ["https://www.chapsvision.com"]}, {"value": "ArgonOS - Full-stack data platform", "sources": ["https://www.chapsvision.com"]}]}'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'ChapsVision' AND organization_id = '$ORG_ID');

-- Company 3: Palantir (Data analytics)
INSERT INTO companies (name, website, organization_id, owner_id, owner_username, is_deleted, created_at, updated_at, profile, digital, products)
SELECT
    'Palantir',
    'https://www.palantir.com/',
    '$ORG_ID',
    '$ADMIN_USER_ID',
    'admin',
    false,
    NOW(),
    NOW(),
    '{"groupName": {"value": "Independent", "source": "Claude Knowledge Base"}, "businessLine": {"value": "Data analytics and software platforms for government and enterprise", "source": "https://www.palantir.com"}, "catchphrase": {"value": "Building the worlds most important software", "source": "https://www.palantir.com"}, "establishmentYear": {"value": "2003", "source": "Claude Knowledge Base"}, "employeeCount": {"value": "3.5k", "source": "Claude Knowledge Base"}, "revenue": {"value": "\$2.2B", "source": "Claude Knowledge Base"}, "ceo": {"value": "Alex Karp", "source": "Claude Knowledge Base"}, "hq": {"value": "Denver, Colorado, USA", "source": "Claude Knowledge Base"}}',
    '{"insights": "Palantir is a leading data analytics company serving government and commercial clients.", "digitalStrategy": {"value": {"overallStrategy": "Enterprise software for data integration and analysis"}}}',
    '{"insights": "Palantir offers data platforms for various sectors.", "customerType": "Government agencies and large enterprises", "range": [{"value": "Gotham - Government data platform", "sources": ["https://www.palantir.com"]}, {"value": "Foundry - Enterprise data platform", "sources": ["https://www.palantir.com"]}, {"value": "AIP - AI Platform", "sources": ["https://www.palantir.com"]}]}'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Palantir' AND organization_id = '$ORG_ID');

-- Get the inserted company IDs and link them to the folder
DO \$\$
DECLARE
    edf_id INTEGER;
    chapsvision_id INTEGER;
    palantir_id INTEGER;
    folder_uuid UUID := 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
BEGIN
    SELECT id INTO edf_id FROM companies WHERE name = 'EDF' AND organization_id = '$ORG_ID' LIMIT 1;
    SELECT id INTO chapsvision_id FROM companies WHERE name = 'ChapsVision' AND organization_id = '$ORG_ID' LIMIT 1;
    SELECT id INTO palantir_id FROM companies WHERE name = 'Palantir' AND organization_id = '$ORG_ID' LIMIT 1;

    -- Insert folder items (link companies to folder)
    IF edf_id IS NOT NULL THEN
        INSERT INTO folder_items (folder_id, item_id, item_type, position, owner, added_at)
        VALUES (folder_uuid, edf_id::text, 'company', 1, 'admin', NOW())
        ON CONFLICT (folder_id, item_id, item_type) DO NOTHING;
    END IF;

    IF chapsvision_id IS NOT NULL THEN
        INSERT INTO folder_items (folder_id, item_id, item_type, position, owner, added_at)
        VALUES (folder_uuid, chapsvision_id::text, 'company', 2, 'admin', NOW())
        ON CONFLICT (folder_id, item_id, item_type) DO NOTHING;
    END IF;

    IF palantir_id IS NOT NULL THEN
        INSERT INTO folder_items (folder_id, item_id, item_type, position, owner, added_at)
        VALUES (folder_uuid, palantir_id::text, 'company', 3, 'admin', NOW())
        ON CONFLICT (folder_id, item_id, item_type) DO NOTHING;
    END IF;

    RAISE NOTICE 'Linked companies to folder: EDF=%, ChapsVision=%, Palantir=%', edf_id, chapsvision_id, palantir_id;
END \$\$;

-- Show what was created
\echo ''
\echo 'Folders:'
SELECT id, name, owner, color FROM folders WHERE is_deleted = false;

\echo ''
\echo 'Companies:'
SELECT id, name, website, owner_username FROM companies WHERE is_deleted = false ORDER BY id;

\echo ''
\echo 'Folder Items:'
SELECT fi.folder_id, f.name as folder_name, fi.item_id, fi.item_type, fi.position
FROM folder_items fi
JOIN folders f ON f.id = fi.folder_id;
EOF

echo ""
echo "✅ Sample data seeded successfully!"
echo ""
echo "You now have:"
echo "  - 1 folder: 'Sample Companies'"
echo "  - 3 companies: EDF, ChapsVision, Palantir"
echo "  - Companies linked to the folder"
echo ""
echo "Login with admin/admin123 to see the data."
