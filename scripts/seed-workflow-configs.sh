#!/bin/bash
# Seed workflow_configs table with Dify API keys
# Run this after the database is initialized and migrations are applied

set -e

# Use the dc alias format or full command
DC="${DC:-docker compose -f docker-compose.yml -f docker-compose.local.yml}"

echo "🔧 Seeding workflow_configs with Dify API keys..."

$DC exec -T db psql -U postgres -d mint_db << 'EOF'
-- Clear existing configs (if any) and insert fresh data
TRUNCATE TABLE workflow_configs RESTART IDENTITY CASCADE;

INSERT INTO workflow_configs (task_type, title, api_key, llm, created_at, updated_at) VALUES
    ('data_collection', 'Data Collection', 'app-qSdKHTLoR0WiESMRcVBKSzlI', 'mistral', NOW(), NOW()),
    ('csr', 'CSR & Sustainability', 'app-jizNzZPfLpljyHTqStbGtMvt', 'mistral', NOW(), NOW()),
    ('digital', 'Digital Presence', 'app-C8hqxmezKsu9u7PysTIDCn58', 'mistral', NOW(), NOW()),
    ('jobs', 'Job Offers', 'app-4LwahL1q2ivVs7cg32qLKY75', 'mistral', NOW(), NOW()),
    ('press', 'Press & Media', 'app-jROOB0dHSSP8bPBt4TdF5NGt', 'mistral', NOW(), NOW()),
    ('products', 'Products & Services', 'app-dFdONFETxJDqzAhiYQMDvKGf', 'mistral', NOW(), NOW()),
    ('profile', 'Company Profile', 'app-UDymomMk5nxIZm8CLQ8jsGxh', 'mistral', NOW(), NOW()),
    ('team', 'Team & Leadership', 'app-l1c9mbAxNitn7sEJyxLH2Dsg', 'mistral', NOW(), NOW()),
    ('timeline', 'Company Timeline', 'app-8g6lEf82QJBABiTzAPzHHM69', 'mistral', NOW(), NOW());

SELECT task_type, title, api_key, llm FROM workflow_configs ORDER BY task_type;
EOF

echo ""
echo "✅ Workflow configs seeded successfully!"
echo ""
echo "You can verify with:"
echo "  $DC exec db psql -U postgres -d mint_db -c 'SELECT * FROM workflow_configs;'"
