#!/bin/bash

# Frontend-only deployment script for Mint preprod environment
# This script updates and rebuilds only the frontend service

set -e  # Exit on error

# Configuration
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DEPLOY_DIR="$SCRIPT_DIR"  # mint-server root directory
SERVER_IP="10.0.1.2"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting Frontend-Only Preprod Deployment${NC}"
echo "========================================"

# Function to handle errors
handle_error() {
    echo -e "${RED}Error occurred during deployment!${NC}"
    exit 1
}

# Set error handler
trap handle_error ERR

# Navigate to deployment directory
cd "$DEPLOY_DIR" || handle_error

# Pull frontend code
if [ -d "../mint-front/.git" ]; then
    echo -e "${YELLOW}Updating mint-front repository...${NC}"
    cd ../mint-front
    git pull origin main
    cd "$DEPLOY_DIR"
else
    echo -e "${RED}Frontend repository not found at ../mint-front${NC}"
    handle_error
fi

# Load environment variables from backend .env
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo -e "${RED}.env file not found. Please run the full deploy script first.${NC}"
    handle_error
fi

# Create frontend .env.production file with correct URLs
echo -e "${YELLOW}Configuring frontend environment...${NC}"
cat > ../mint-front/.env.production << EOF
VITE_KEYCLOAK_URL=http://$SERVER_IP:8080
VITE_KEYCLOAK_REALM=mint-dev
VITE_KEYCLOAK_CLIENT_ID=mint-front

VITE_BASE_URL=http://$SERVER_IP
VITE_BACKEND_API=http://$SERVER_IP
EOF
echo -e "${GREEN}Frontend .env.production created with server IP: $SERVER_IP${NC}"

# Build and deploy only frontend service
echo -e "${YELLOW}Building and deploying frontend service...${NC}"
docker-compose -f docker-compose.preprod.yml build --no-cache frontend
docker-compose -f docker-compose.preprod.yml up -d frontend

# Wait for service to be healthy
echo -e "${YELLOW}Waiting for frontend service to be healthy...${NC}"
sleep 5

# Check frontend service status
echo -e "${YELLOW}Checking frontend service status...${NC}"
docker-compose -f docker-compose.preprod.yml ps frontend

# Show recent frontend logs
echo -e "${YELLOW}Recent frontend logs:${NC}"
docker-compose -f docker-compose.preprod.yml logs --tail=50 frontend

echo -e "${GREEN}Frontend deployment completed successfully!${NC}"
echo "========================================"
echo "Access the application at: http://$SERVER_IP"
echo ""
echo "To view frontend logs: docker-compose -f docker-compose.preprod.yml logs -f frontend"
echo "To restart frontend: docker-compose -f docker-compose.preprod.yml restart frontend"
