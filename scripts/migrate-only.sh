#!/bin/bash

# Run database migrations on remote server
# This script runs migrations on the existing backend container

set -e  # Exit on any error

SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

echo "🔄 Running database migrations on server: $SERVER_IP"

# Run migrations on remote server
ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint

# Get backend container name
BACKEND_CONTAINER=$(docker-compose -f docker-compose.prod.yml ps -q backend)

if [ -z "$BACKEND_CONTAINER" ]; then
    echo "❌ Backend container not found. Is it running?"
    docker-compose -f docker-compose.prod.yml ps
    exit 1
fi

echo "📦 Found backend container: $BACKEND_CONTAINER"

# Run migrations
echo "🚀 Executing alembic upgrade head..."
docker exec $BACKEND_CONTAINER alembic upgrade head

echo "✅ Migrations completed successfully!"

# Show current database revision
echo "📋 Current database revision:"
docker exec $BACKEND_CONTAINER alembic current

# Show backend logs to verify everything is working
echo "📋 Recent backend logs:"
docker logs $BACKEND_CONTAINER --tail 10

REMOTE_SCRIPT

echo "🎉 Database migrations completed!"