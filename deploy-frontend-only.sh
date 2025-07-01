#!/bin/bash

# Deploy only frontend service to single server (10.0.1.2)
SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

echo "🚀 Deploying frontend service only to server: $SERVER_IP"

echo "📦 Copying frontend files to server..."

# Ensure remote directory exists
ssh $USER@$SERVER_IP "mkdir -p $REMOTE_DIR"

# Copy environment file
scp .env.production $USER@$SERVER_IP:$REMOTE_DIR/

# Copy only frontend directory (excluding large files)
tar --exclude=node_modules --exclude=.git --exclude=__pycache__ --exclude=.next --exclude=dist --exclude=.nuxt --exclude=.output -czf /tmp/mint-frontend-deploy.tar.gz mint-front/
scp /tmp/mint-frontend-deploy.tar.gz $USER@$SERVER_IP:$REMOTE_DIR/
rm /tmp/mint-frontend-deploy.tar.gz

echo "🔧 Building and restarting frontend service..."

# Deploy frontend on server
ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint

# Extract the frontend files
tar -xzf mint-frontend-deploy.tar.gz
rm mint-frontend-deploy.tar.gz

# Stop frontend service
docker-compose -f docker-compose.prod.yml stop frontend

# Remove old frontend image to force rebuild
docker-compose -f docker-compose.prod.yml rm -f frontend
docker rmi mint-frontend 2>/dev/null || true

# Build and start frontend service only
docker-compose -f docker-compose.prod.yml up -d --build frontend

# Wait for frontend to be ready
echo "⏳ Waiting for frontend to start..."
sleep 20

# Check frontend status
echo "🔍 Checking frontend status..."
docker-compose -f docker-compose.prod.yml ps frontend

# Test frontend health
echo "🏥 Testing frontend health..."
curl -s -o /dev/null -w "Frontend Status: %{http_code}\n" http://localhost:3000 || echo "Frontend not responding yet"

echo "✅ Frontend deployment complete!"
echo "🌐 Frontend: http://10.0.1.2:3000"
REMOTE_SCRIPT

echo "🎉 Frontend deployment finished!"