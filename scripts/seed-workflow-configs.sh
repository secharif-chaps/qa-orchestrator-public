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
    ('csr', 'CSR & Sustainability', 'app-k8W9ZQkZUIcsvBo5ZWgY3RLE', 'mistral', NOW(), NOW()),
    ('digital', 'Digital Presence', 'app-sU5GPQX6og1nwtckA57iCDeL', 'mistral', NOW(), NOW()),
    ('jobs', 'Job Offers', 'app-nZiwseUyw8f6H208ohPx0o1K', 'mistral', NOW(), NOW()),
    ('press', 'Press & Media', 'app-ET1gBTLFPsVD8qlYSL46O3Hd', 'mistral', NOW(), NOW()),
    ('products', 'Products & Services', 'app-WpGZCTFDaBzCUS9M4LeoQHGa', 'mistral', NOW(), NOW()),
    ('profile', 'Company Profile', 'app-4K16XfNZP4ZhUutfoLEZYiKy', 'mistral', NOW(), NOW()),
    ('team', 'Team & Leadership', 'app-LTspKNxtk6nTJOH4YkBdyU2Q', 'mistral', NOW(), NOW()),
    ('timeline', 'Company Timeline', 'app-qX4RISdrrif2aSPAaLVz7tto', 'mistral', NOW(), NOW());

SELECT task_type, title, api_key, llm FROM workflow_configs ORDER BY task_type;
EOF

echo ""
echo "✅ Workflow configs seeded successfully!"
echo ""
echo "You can verify with:"
echo "  $DC exec db psql -U postgres -d mint_db -c 'SELECT * FROM workflow_configs;'"
