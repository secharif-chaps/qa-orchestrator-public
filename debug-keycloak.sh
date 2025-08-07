#!/bin/bash

# Keycloak Debugging Script
# This script helps troubleshoot Keycloak routing and connectivity issues
# Run this script on the deployment server to diagnose Keycloak problems

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration - update these paths if needed
COMPOSE_FILE="docker-compose.preprod.yml"
COMPOSE_DIR="/home/nmercier/mint"
SERVER_IP="${SERVER_IP:-10.0.1.2}"

# Detect if we're running locally or remotely
if [ -f "$COMPOSE_FILE" ]; then
    COMPOSE_DIR="."
    DOCKER_CMD="docker-compose -f $COMPOSE_FILE"
elif [ -d "$COMPOSE_DIR" ]; then
    DOCKER_CMD="cd $COMPOSE_DIR && docker-compose -f $COMPOSE_FILE"
else
    echo -e "${RED}❌ Could not find docker-compose file. Please run from project root or deployment server.${NC}"
    exit 1
fi

print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo
}

print_section() {
    echo -e "${PURPLE}--- $1 ---${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Function to execute docker commands
exec_docker() {
    if [ "$COMPOSE_DIR" = "." ]; then
        eval "$1"
    else
        eval "$DOCKER_CMD exec $1"
    fi
}

# Function to execute docker-compose commands
exec_compose() {
    if [ "$COMPOSE_DIR" = "." ]; then
        docker-compose -f "$COMPOSE_FILE" "$@"
    else
        eval "cd $COMPOSE_DIR && docker-compose -f $COMPOSE_FILE $*"
    fi
}

print_header "🔍 KEYCLOAK DEBUGGING SCRIPT"
echo "This script will help diagnose Keycloak routing and connectivity issues."
echo "Server IP: $SERVER_IP"
echo "Compose file: $COMPOSE_FILE"
echo "Working directory: $(pwd)"
echo

# 1. Check if Keycloak container is running
print_section "1. Checking Docker Container Status"
echo "Checking if all services are running..."
echo

if exec_compose ps; then
    print_success "Docker Compose services status retrieved"
else
    print_error "Failed to get docker-compose status"
fi

echo
echo "Specifically checking Keycloak container..."
KEYCLOAK_RUNNING=$(exec_compose ps keycloak | grep -c "Up\|running" || echo "0")

if [ "$KEYCLOAK_RUNNING" -gt 0 ]; then
    print_success "Keycloak container is running"
else
    print_error "Keycloak container is not running"
    echo "Attempting to start Keycloak..."
    exec_compose up -d keycloak
fi

# 2. Test direct access to Keycloak container
print_section "2. Testing Direct Container Access"
echo "Testing if Keycloak is responding inside the container..."
echo

# Test internal container connectivity
KEYCLOAK_CONTAINER_ID=$(exec_compose ps -q keycloak 2>/dev/null)
if [ -n "$KEYCLOAK_CONTAINER_ID" ]; then
    print_info "Keycloak container ID: $KEYCLOAK_CONTAINER_ID"
    
    # Test direct connection to Keycloak port 8080
    echo "Testing connection to localhost:8080 inside container..."
    if [ "$COMPOSE_DIR" = "." ]; then
        docker exec "$KEYCLOAK_CONTAINER_ID" curl -f -s http://localhost:8080/auth/realms/master || echo "Direct connection failed"
    else
        eval "cd $COMPOSE_DIR && docker exec $KEYCLOAK_CONTAINER_ID curl -f -s http://localhost:8080/auth/realms/master || echo 'Direct connection failed'"
    fi
    
    # Test if Keycloak is listening on port 8080
    echo
    echo "Checking if Keycloak process is listening on port 8080..."
    if [ "$COMPOSE_DIR" = "." ]; then
        docker exec "$KEYCLOAK_CONTAINER_ID" netstat -tlnp | grep :8080 || echo "Port 8080 not listening"
    else
        eval "cd $COMPOSE_DIR && docker exec $KEYCLOAK_CONTAINER_ID netstat -tlnp | grep :8080 || echo 'Port 8080 not listening'"
    fi
else
    print_error "Could not find Keycloak container ID"
fi

# 3. Test nginx routing to Keycloak
print_section "3. Testing Nginx Routing"
echo "Testing nginx configuration and routing to Keycloak..."
echo

# Check if nginx is running
NGINX_RUNNING=$(exec_compose ps nginx | grep -c "Up\|running" || echo "0")
if [ "$NGINX_RUNNING" -gt 0 ]; then
    print_success "Nginx container is running"
    
    # Test nginx routing from inside the nginx container
    NGINX_CONTAINER_ID=$(exec_compose ps -q nginx 2>/dev/null)
    if [ -n "$NGINX_CONTAINER_ID" ]; then
        echo "Testing internal nginx to keycloak routing..."
        if [ "$COMPOSE_DIR" = "." ]; then
            docker exec "$NGINX_CONTAINER_ID" curl -f -s http://keycloak:8080/auth/realms/master || echo "Internal nginx->keycloak routing failed"
        else
            eval "cd $COMPOSE_DIR && docker exec $NGINX_CONTAINER_ID curl -f -s http://keycloak:8080/auth/realms/master || echo 'Internal nginx->keycloak routing failed'"
        fi
    fi
    
    # Test external access through nginx
    echo
    echo "Testing external access through nginx..."
    curl -f -s "http://$SERVER_IP/auth/realms/master" | head -5 || print_error "External nginx routing failed"
    
else
    print_error "Nginx container is not running"
fi

# 4. Check Keycloak logs for errors
print_section "4. Keycloak Logs Analysis"
echo "Checking recent Keycloak logs for errors..."
echo

exec_compose logs --tail=50 keycloak | grep -E "(ERROR|WARN|Exception|Failed)" | tail -10 || echo "No recent errors found in logs"

echo
echo "Last 10 lines of Keycloak logs:"
exec_compose logs --tail=10 keycloak

# 5. Verify Keycloak paths and configuration
print_section "5. Keycloak Configuration Verification"
echo "Verifying Keycloak configuration and available paths..."
echo

# Check Keycloak environment variables
echo "Keycloak environment variables:"
if [ "$COMPOSE_DIR" = "." ]; then
    docker exec "$KEYCLOAK_CONTAINER_ID" env | grep -E "(KC_|KEYCLOAK_)" || echo "No Keycloak environment variables found"
else
    eval "cd $COMPOSE_DIR && docker exec $KEYCLOAK_CONTAINER_ID env | grep -E '(KC_|KEYCLOAK_)' || echo 'No Keycloak environment variables found'"
fi

echo
echo "Testing specific Keycloak endpoints..."

# Test well-known endpoints
ENDPOINTS=(
    "/auth/realms/master"
    "/auth/realms/mint-dev"
    "/auth/realms/mint-dev/.well-known/openid_configuration"
    "/auth/admin"
    "/auth/js/keycloak.js"
)

for endpoint in "${ENDPOINTS[@]}"; do
    echo -n "Testing $endpoint: "
    if curl -f -s -o /dev/null "http://$SERVER_IP$endpoint"; then
        print_success "Available"
    else
        print_error "Not accessible"
    fi
done

# 6. Network connectivity tests
print_section "6. Network Connectivity Tests"
echo "Testing network connectivity between containers..."
echo

# Test if containers can reach each other
if [ -n "$KEYCLOAK_CONTAINER_ID" ]; then
    echo "Testing if Keycloak can reach database..."
    if [ "$COMPOSE_DIR" = "." ]; then
        docker exec "$KEYCLOAK_CONTAINER_ID" nc -z db 5432 && print_success "Keycloak can reach database" || print_error "Cannot reach database"
    else
        eval "cd $COMPOSE_DIR && docker exec $KEYCLOAK_CONTAINER_ID nc -z db 5432 && print_success 'Keycloak can reach database' || print_error 'Cannot reach database'"
    fi
    
    echo "Testing if backend can reach Keycloak..."
    BACKEND_CONTAINER_ID=$(exec_compose ps -q backend 2>/dev/null)
    if [ -n "$BACKEND_CONTAINER_ID" ]; then
        if [ "$COMPOSE_DIR" = "." ]; then
            docker exec "$BACKEND_CONTAINER_ID" nc -z keycloak 8080 && print_success "Backend can reach Keycloak" || print_error "Backend cannot reach Keycloak"
        else
            eval "cd $COMPOSE_DIR && docker exec $BACKEND_CONTAINER_ID nc -z keycloak 8080 && print_success 'Backend can reach Keycloak' || print_error 'Backend cannot reach Keycloak'"
        fi
    fi
fi

# 7. Port and process information
print_section "7. Port and Process Information"
echo "Checking port usage and processes..."
echo

echo "Ports in use on the host:"
netstat -tlnp | grep -E ":80|:8080|:5432" || echo "Standard ports not found"

echo
echo "Docker network information:"
if [ "$COMPOSE_DIR" = "." ]; then
    docker network ls | grep mint || echo "Mint network not found"
    docker network inspect $(docker-compose -f "$COMPOSE_FILE" config | grep -A 5 networks | grep mint | cut -d: -f1 | tr -d ' ') 2>/dev/null | jq '.[] | {Name: .Name, Containers: .Containers}' 2>/dev/null || echo "Network details not available"
else
    eval "cd $COMPOSE_DIR && docker network ls | grep mint || echo 'Mint network not found'"
fi

# 8. Configuration file checks
print_section "8. Configuration Files Check"
echo "Verifying configuration files..."
echo

# Check nginx config
echo "Nginx configuration file exists:"
if [ -f "nginx-preprod.conf" ]; then
    print_success "nginx-preprod.conf found"
    echo "Keycloak routing configuration:"
    grep -A 10 -B 2 "location.*auth" nginx-preprod.conf || echo "No auth location found in nginx config"
elif [ -f "$COMPOSE_DIR/nginx-preprod.conf" ]; then
    print_success "nginx-preprod.conf found in $COMPOSE_DIR"
    echo "Keycloak routing configuration:"
    grep -A 10 -B 2 "location.*auth" "$COMPOSE_DIR/nginx-preprod.conf" || echo "No auth location found in nginx config"
else
    print_error "nginx-preprod.conf not found"
fi

echo
echo "Docker-compose configuration:"
if [ -f "$COMPOSE_FILE" ]; then
    print_success "$COMPOSE_FILE found"
    echo "Keycloak service configuration:"
    grep -A 20 "keycloak:" "$COMPOSE_FILE" || echo "Keycloak service not found in compose file"
elif [ -f "$COMPOSE_DIR/$COMPOSE_FILE" ]; then
    print_success "$COMPOSE_FILE found in $COMPOSE_DIR"
    echo "Keycloak service configuration:"
    grep -A 20 "keycloak:" "$COMPOSE_DIR/$COMPOSE_FILE" || echo "Keycloak service not found in compose file"
else
    print_error "$COMPOSE_FILE not found"
fi

# Summary
print_header "📋 DEBUGGING SUMMARY"

echo "If you found issues, here are some common solutions:"
echo
echo "1. Container not running:"
echo "   - Run: docker-compose -f $COMPOSE_FILE up -d keycloak"
echo
echo "2. Network connectivity issues:"
echo "   - Check docker network: docker network inspect <network-name>"
echo "   - Restart all services: docker-compose -f $COMPOSE_FILE restart"
echo
echo "3. Nginx routing problems:"
echo "   - Check nginx logs: docker-compose -f $COMPOSE_FILE logs nginx"
echo "   - Verify nginx config syntax: docker-compose -f $COMPOSE_FILE exec nginx nginx -t"
echo
echo "4. Keycloak startup issues:"
echo "   - Check full logs: docker-compose -f $COMPOSE_FILE logs keycloak"
echo "   - Check database connectivity"
echo "   - Verify environment variables"
echo
echo "5. Port conflicts:"
echo "   - Check what's using port 80: sudo netstat -tlnp | grep :80"
echo "   - Stop conflicting services"
echo
echo "For more detailed analysis, run:"
echo "  docker-compose -f $COMPOSE_FILE logs keycloak -f"
echo "  docker-compose -f $COMPOSE_FILE exec keycloak bash"
echo

print_info "Debug script completed. Check the output above for any issues."