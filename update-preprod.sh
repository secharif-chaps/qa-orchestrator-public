#!/bin/bash

# Quick update script for Mint preprod
# Use this for code updates without full rebuild

set -e

# Configuration
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
DEPLOY_DIR="$SCRIPT_DIR"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Quick Update - Mint Preprod${NC}"
echo "==============================="

cd "$DEPLOY_DIR"

# Pull latest code
echo -e "${YELLOW}Pulling latest code...${NC}"
git pull origin main

# Also pull frontend code if it's a separate repository
if [ -d "../mint-front/.git" ]; then
    cd ../mint-front
    git pull origin main
    cd ../mint-server
fi

# Restart only changed services
echo -e "${YELLOW}Restarting services...${NC}"
docker-compose -f docker-compose.preprod.yml up -d --build

# Show status
docker-compose -f docker-compose.preprod.yml ps

echo -e "${GREEN}Update completed!${NC}"