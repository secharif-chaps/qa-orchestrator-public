#!/bin/bash

# Deployment script for Mint preprod environment
# This script handles git pull and docker-compose deployment

set -e  # Exit on error

# Configuration
DEPLOY_DIR="/home/deploy/mint-preprod"
SERVER_IP="10.0.1.1"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting Mint Preprod Deployment${NC}"
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

# Pull latest code
echo -e "${YELLOW}Pulling latest code...${NC}"
git pull origin main

# Also pull frontend code if it's a separate repository
if [ -d "../mint-front/.git" ]; then
    echo -e "${YELLOW}Updating mint-front repository...${NC}"
    cd ../mint-front
    git pull origin main
    cd ../mint-server
fi

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

# Build and deploy with docker-compose
echo -e "${YELLOW}Building and deploying services...${NC}"
docker-compose -f docker-compose.preprod.yml down
docker-compose -f docker-compose.preprod.yml build --no-cache
docker-compose -f docker-compose.preprod.yml up -d

# Wait for services to be healthy
echo -e "${YELLOW}Waiting for services to be healthy...${NC}"
sleep 10

# Check service status
echo -e "${YELLOW}Checking service status...${NC}"
docker-compose -f docker-compose.preprod.yml ps

# Run database migrations
echo -e "${YELLOW}Running database migrations...${NC}"
docker-compose -f docker-compose.preprod.yml exec -T backend alembic upgrade head

# Create initial users if needed
if [ -f "create_test_users.sh" ]; then
    echo -e "${YELLOW}Creating test users...${NC}"
    docker-compose -f docker-compose.preprod.yml exec -T backend bash < create_test_users.sh || echo "Test users may already exist"
fi

# Show logs
echo -e "${YELLOW}Recent logs:${NC}"
docker-compose -f docker-compose.preprod.yml logs --tail=50

echo -e "${GREEN}Deployment completed successfully!${NC}"
echo "========================================"
echo "Access the application at: http://$SERVER_IP"
echo "Keycloak admin: http://$SERVER_IP/auth"
echo ""
echo "To view logs: docker-compose -f docker-compose.preprod.yml logs -f"
echo "To restart services: docker-compose -f docker-compose.preprod.yml restart"