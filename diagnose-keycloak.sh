#!/bin/bash

echo "========================================"
echo "Keycloak Diagnostic Script"
echo "========================================"

# Check if Keycloak is responding internally
echo ""
echo "1. Testing Keycloak directly inside container:"
echo "-----------------------------------------------"
docker-compose -f docker-compose.preprod.yml exec keycloak sh -c "curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' http://localhost:8080/"
docker-compose -f docker-compose.preprod.yml exec keycloak sh -c "curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' http://localhost:8080/admin"
docker-compose -f docker-compose.preprod.yml exec keycloak sh -c "curl -s -o /dev/null -w 'HTTP Status: %{http_code}\n' http://localhost:8080/realms/master"

echo ""
echo "2. Testing from nginx container to keycloak:"
echo "---------------------------------------------"
docker-compose -f docker-compose.preprod.yml exec nginx sh -c "wget -q -O - http://keycloak:8080/ | head -20"

echo ""
echo "3. Checking Keycloak environment variables:"
echo "--------------------------------------------"
docker-compose -f docker-compose.preprod.yml exec keycloak sh -c "env | grep KC_"

echo ""
echo "4. Checking nginx configuration:"
echo "---------------------------------"
docker-compose -f docker-compose.preprod.yml exec nginx cat /etc/nginx/conf.d/default.conf | grep -A 20 "location /auth"

echo ""
echo "5. Testing external access:"
echo "----------------------------"
curl -I http://10.0.1.2/auth/
curl -I http://10.0.1.2/auth/admin
curl -I http://10.0.1.2/auth/realms/master

echo ""
echo "6. Checking Keycloak logs for errors:"
echo "--------------------------------------"
docker-compose -f docker-compose.preprod.yml logs keycloak | tail -20

echo ""
echo "7. Check if containers can communicate:"
echo "----------------------------------------"
docker-compose -f docker-compose.preprod.yml exec backend sh -c "wget -q -O - http://keycloak:8080/realms/master/.well-known/openid-configuration | jq '.issuer' 2>/dev/null || echo 'Connection failed'"