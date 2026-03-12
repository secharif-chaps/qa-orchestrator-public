#!/bin/bash
# Seed workflow_configs table with Dify API keys
# Run this after the database is initialized and migrations are applied

set -e

# Use the dc alias format or full command
DC="${DC:-docker compose -f infra/compose.yaml -f infra/compose.local.yaml}"

echo "🔧 Seeding workflow_configs with Dify API keys..."

$DC exec -T db psql -U postgres -d chapsmind_db << 'EOF'
SET search_path TO screen_schema;

-- Clear existing configs (if any) and insert fresh data
TRUNCATE TABLE workflow_configs RESTART IDENTITY CASCADE;

INSERT INTO workflow_configs (task_type, title, api_key, created_at, updated_at) VALUES
    ('data_collection', 'Data Collection', 'app-qSdKHTLoR0WiESMRcVBKSzlI', NOW(), NOW()),
    ('csr', 'CSR & Sustainability', 'app-jizNzZPfLpljyHTqStbGtMvt', NOW(), NOW()),
    ('digital', 'Digital Presence', 'app-C8hqxmezKsu9u7PysTIDCn58', NOW(), NOW()),
    ('jobs', 'Job Offers', 'app-4LwahL1q2ivVs7cg32qLKY75', NOW(), NOW()),
    ('press', 'Press & Media', 'app-jROOB0dHSSP8bPBt4TdF5NGt', NOW(), NOW()),
    ('products', 'Products & Services', 'app-dFdONFETxJDqzAhiYQMDvKGf', NOW(), NOW()),
    ('profile', 'Company Profile', 'app-UDymomMk5nxIZm8CLQ8jsGxh', NOW(), NOW()),
    ('team', 'Team & Leadership', 'app-l1c9mbAxNitn7sEJyxLH2Dsg', NOW(), NOW()),
    ('timeline', 'Company Timeline', 'app-8g6lEf82QJBABiTzAPzHHM69', NOW(), NOW());

SELECT task_type, title, api_key FROM workflow_configs ORDER BY task_type;
EOF

echo ""
echo "✅ Workflow configs seeded successfully!"
echo ""
echo "You can verify with:"
echo "  $DC exec db psql -U postgres -d chapsmind_db -c 'SELECT * FROM workflow_configs;'"
