#!/bin/bash

# Enhanced deployment script for Mint Backend
# This script handles the complete deployment process following your patterns

set -e  # Exit on any error

SERVER_IP="10.0.1.2"
USER="nmercier"
REMOTE_DIR="/home/nmercier/mint"

echo "🚀 Deploying backend service with migrations to server: $SERVER_IP"

echo "📦 Copying backend files to server..."

# Ensure remote directory exists
ssh $USER@$SERVER_IP "mkdir -p $REMOTE_DIR"

# Copy environment file
scp .env.production $USER@$SERVER_IP:$REMOTE_DIR/

# Copy only backend directory (excluding large files)
tar --exclude=node_modules --exclude=.git --exclude=__pycache__ --exclude=.next --exclude=dist -czf /tmp/mint-backend-deploy.tar.gz mint-back/
scp /tmp/mint-backend-deploy.tar.gz $USER@$SERVER_IP:$REMOTE_DIR/
rm /tmp/mint-backend-deploy.tar.gz

echo "🔧 Building and restarting backend service with migrations..."

# Deploy backend on server
ssh $USER@$SERVER_IP << 'REMOTE_SCRIPT'
cd /home/nmercier/mint

# Extract the backend files
tar -xzf mint-backend-deploy.tar.gz
rm mint-backend-deploy.tar.gz

# Stop backend service
docker-compose -f docker-compose.prod.yml stop backend

# Remove old backend image to force rebuild
docker-compose -f docker-compose.prod.yml rm -f backend
docker rmi mint-backend 2>/dev/null || true

# Build and start backend service only
docker-compose -f docker-compose.prod.yml up -d --build backend

# Wait for backend to be ready
echo "⏳ Waiting for backend to start..."
sleep 15

# Check backend status
echo "🔍 Checking backend status..."
docker-compose -f docker-compose.prod.yml ps backend

# Test backend health
echo "🏥 Testing backend health..."
curl -s -o /dev/null -w "Backend API Status: %{http_code}\n" http://localhost:8000/docs || echo "Backend not responding yet"

echo "✅ Backend deployment complete!"
echo "🔧 Backend API: http://10.0.1.2:8000"
REMOTE_SCRIPT

echo "🎉 Backend deployment finished!"