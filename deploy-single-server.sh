#!/bin/bash

# Deploy all services to single server (10.0.1.2)
SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

echo "🚀 Deploying all services to single server: $SERVER_IP"

echo "✅ Using existing docker-compose.prod.yml and .env.production files"

echo "📦 Copying files to server..."

# Ensure remote directory exists
ssh $USER@$SERVER_IP "mkdir -p $REMOTE_DIR"

# Copy docker-compose file and environment file
scp docker-compose.prod.yml $USER@$SERVER_IP:$REMOTE_DIR/
scp .env.production $USER@$SERVER_IP:$REMOTE_DIR/

# Copy project directories (excluding large files)
tar --exclude=node_modules --exclude=.git --exclude=__pycache__ --exclude=.next --exclude=dist -czf /tmp/mint-deploy.tar.gz .
scp /tmp/mint-deploy.tar.gz $USER@$SERVER_IP:$REMOTE_DIR/
rm /tmp/mint-deploy.tar.gz

echo "🔧 Building and starting services..."

# Deploy on server
ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint

# Extract the project files
tar -xzf mint-deploy.tar.gz
rm mint-deploy.tar.gz

# Stop any existing services
docker-compose -f docker-compose.prod.yml down 2>/dev/null || true

# Remove old images to avoid conflicts
docker-compose -f docker-compose.prod.yml down --rmi local 2>/dev/null || true

# Build and start services
docker-compose -f docker-compose.prod.yml up -d --build

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 30

# Check status
docker-compose -f docker-compose.prod.yml ps

echo "✅ Single-server deployment complete!"
echo "🌐 Frontend: http://10.0.1.2:3000"
echo "🔧 Backend API: http://10.0.1.2:8000"
echo "🔐 Keycloak: http://10.0.1.2:8080"
REMOTE_SCRIPT

echo "🎉 Deployment finished! All services are running on server 10.0.1.2"