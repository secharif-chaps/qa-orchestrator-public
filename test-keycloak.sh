#!/bin/bash

echo "Testing Keycloak directly inside container..."
docker-compose -f docker-compose.preprod.yml exec keycloak curl -s http://localhost:8080/ | head -5
echo ""
echo "Testing with /auth path..."
docker-compose -f docker-compose.preprod.yml exec keycloak curl -s http://localhost:8080/auth/ | head -5
echo ""
echo "Testing realms endpoint..."
docker-compose -f docker-compose.preprod.yml exec keycloak curl -s http://localhost:8080/realms/master | head -5
echo ""
echo "Testing /auth/realms endpoint..."
docker-compose -f docker-compose.preprod.yml exec keycloak curl -s http://localhost:8080/auth/realms/master | head -5