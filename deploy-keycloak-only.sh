#!/bin/bash

# Deploy only Keycloak service to single server (10.0.1.2)
SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

echo "🚀 Deploying Keycloak service only to server: $SERVER_IP"

echo "📦 Copying Keycloak files to server..."

# Ensure remote directory exists
ssh $USER@$SERVER_IP "mkdir -p $REMOTE_DIR"

# Copy environment file
scp .env.production $USER@$SERVER_IP:$REMOTE_DIR/

# Copy docker-compose.prod.yml and Keycloak config
scp docker-compose.prod.yml $USER@$SERVER_IP:$REMOTE_DIR/
scp -r docker/ $USER@$SERVER_IP:$REMOTE_DIR/

echo "🔧 Restarting Keycloak service..."

# Deploy Keycloak on server
ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint

# Pull latest Keycloak image
docker-compose -f docker-compose.prod.yml pull keycloak

# Stop and remove Keycloak service
docker-compose -f docker-compose.prod.yml stop keycloak
docker-compose -f docker-compose.prod.yml rm -f keycloak

# Start Keycloak service
docker-compose -f docker-compose.prod.yml up -d keycloak

# Wait for Keycloak to be ready
echo "⏳ Waiting for Keycloak to start..."
sleep 30

# Check Keycloak status
echo "🔍 Checking Keycloak status..."
docker-compose -f docker-compose.prod.yml ps keycloak

# Test Keycloak health
echo "🏥 Testing Keycloak health..."
curl -s -o /dev/null -w "Keycloak Status: %{http_code}\n" http://localhost:8080 || echo "Keycloak not responding yet"

echo "✅ Keycloak deployment complete!"
echo "🔧 Keycloak Admin Console: http://10.0.1.2:8080"
REMOTE_SCRIPT

echo "🎉 Keycloak deployment finished!"