#!/bin/bash

# Backend-only deployment script for Mint preprod environment
# This script handles git pull and docker-compose deployment for backend services only

set -e  # Exit on error

# Configuration
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DEPLOY_DIR="$SCRIPT_DIR"
SERVER_IP="10.0.1.2"  # Updated to new server IP

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting Mint Backend-Only Preprod Deployment${NC}"
echo "=============================================="

# Function to handle errors
handle_error() {
    echo -e "${RED}Error occurred during deployment!${NC}"
    exit 1
}

# Set error handler
trap handle_error ERR

# Navigate to deployment directory
cd "$DEPLOY_DIR" || handle_error

# Pull latest code
echo -e "${YELLOW}Pulling latest backend code...${NC}"
git pull origin main

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    cat > .env << EOF
# Server Configuration
SERVER_IP=$SERVER_IP

# Database
DB_PASSWORD=postgres_preprod_password

# Keycloak
KEYCLOAK_ADMIN_PASSWORD=admin_preprod_password
KEYCLOAK_CLIENT_SECRET=

# You can override these as needed
EOF
    echo -e "${GREEN}.env file created. Please update passwords before continuing!${NC}"
    echo -e "${RED}Deployment paused. Update .env file and run this script again.${NC}"
    exit 0
fi

# Load environment variables
export $(cat .env | grep -v '^#' | xargs)

# Build and deploy backend services only with docker-compose
echo -e "${YELLOW}Stopping backend services...${NC}"
docker-compose -f docker-compose.preprod.yml stop backend

echo -e "${YELLOW}Building backend service...${NC}"
docker-compose -f docker-compose.preprod.yml build --no-cache backend

echo -e "${YELLOW}Starting backend service...${NC}"
docker-compose -f docker-compose.preprod.yml up -d backend

# Wait for backend service to be healthy
echo -e "${YELLOW}Waiting for backend service to be healthy...${NC}"
sleep 15

# Check backend service status
echo -e "${YELLOW}Checking backend service status...${NC}"
docker-compose -f docker-compose.preprod.yml ps backend

# Run database migrations
echo -e "${YELLOW}Running database migrations...${NC}"
docker-compose -f docker-compose.preprod.yml exec -T backend alembic upgrade head

# Show backend logs
echo -e "${YELLOW}Recent backend logs:${NC}"
docker-compose -f docker-compose.preprod.yml logs --tail=50 backend

echo -e "${GREEN}Backend deployment completed successfully!${NC}"
echo "=============================================="
echo "Backend API available at: http://$SERVER_IP/api"
echo ""
echo "To view backend logs: docker-compose -f docker-compose.preprod.yml logs -f backend"
echo "To restart backend: docker-compose -f docker-compose.preprod.yml restart backend"