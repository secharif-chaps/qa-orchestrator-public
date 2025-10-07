#!/bin/bash

echo "🚀 Setting up local Dify callbacks..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get your public IP
PUBLIC_IP=$(curl -s ifconfig.me)
echo "📡 Your public IP: $PUBLIC_IP"

# Check if Docker services are running
echo "🐳 Checking Docker services..."
if docker compose -f docker-compose.dev.yml ps | grep -q "Up"; then
    echo -e "${GREEN}✅ Docker services are running${NC}"
else
    echo -e "${RED}❌ Docker services not running. Starting them...${NC}"
    docker compose -f docker-compose.dev.yml up -d
    echo "⏳ Waiting for services to start..."
    sleep 10
fi

# Test if backend is responding
if curl -s http://localhost:8000/api/ > /dev/null; then
    echo -e "${GREEN}✅ Backend is responding on localhost:8000${NC}"
else
    echo -e "${RED}❌ Backend is not responding${NC}"
    exit 1
fi

echo ""
echo "🔧 Configuration Options:"
echo "1. Use Public IP + Port Forwarding (Recommended if you can configure router)"
echo "2. Sign up for ngrok (Free, but requires account)"
echo "3. Test callbacks with production webhook URLs (Temporary solution)"

read -p "Choose option (1-3): " choice

case $choice in
    1)
        echo ""
        echo -e "${YELLOW}📝 Setting up Public IP configuration...${NC}"
        
        # Create .env with public IP
        cat > .env << EOF
BACKEND_BASE_URL=http://$PUBLIC_IP:8000
ENVIRONMENT=development
EOF
        
        echo -e "${GREEN}✅ Created .env file with:${NC}"
        echo "   BACKEND_BASE_URL=http://$PUBLIC_IP:8000"
        echo ""
        echo -e "${YELLOW}⚠️  IMPORTANT: You need to configure your router to forward port 8000${NC}"
        echo "   1. Access your router admin panel (usually 192.168.1.1 or 192.168.0.1)"
        echo "   2. Find 'Port Forwarding' or 'NAT' settings"
        echo "   3. Forward external port 8000 to internal IP $(hostname -I | awk '{print $1}'):8000"
        echo ""
        echo -e "${GREEN}🎯 Callback URL will be: http://$PUBLIC_IP:8000/api/webhooks/dify/tasks/{task_id}/callback${NC}"
        ;;
    2)
        echo ""
        echo -e "${YELLOW}📝 Setting up ngrok...${NC}"
        echo "1. Sign up at: https://dashboard.ngrok.com/signup"
        echo "2. Get your authtoken from: https://dashboard.ngrok.com/get-started/your-authtoken"
        echo "3. Run: ngrok config add-authtoken YOUR_TOKEN"
        echo "4. Run: ngrok http 8000"
        echo "5. Update .env with the ngrok URL"
        echo ""
        echo "Then run this script again to complete setup."
        ;;
    3)
        echo ""
        echo -e "${YELLOW}📝 Temporary solution using production URLs...${NC}"
        
        # Keep production URL temporarily
        cat > .env << EOF
BACKEND_BASE_URL=http://10.0.1.2
ENVIRONMENT=development
EOF
        
        echo -e "${GREEN}✅ Using production callback URLs temporarily${NC}"
        echo "   Note: This means callbacks will go to production server, not local"
        echo "   Only use this for testing non-callback functionality"
        ;;
    *)
        echo -e "${RED}❌ Invalid choice${NC}"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}🎉 Local callback setup complete!${NC}"
echo ""
echo "📋 Next steps:"
echo "   1. Test the configuration with a sample task"
echo "   2. Check webhook callback URLs in logs"
echo "   3. Verify Dify can reach your callback endpoints"

# Test configuration
echo ""
echo "🧪 Testing configuration..."
source .env
echo "   Backend URL: $BACKEND_BASE_URL"
echo "   Environment: $ENVIRONMENT"

if [ "$choice" = "1" ]; then
    echo ""
    echo "🔍 Testing external access..."
    echo "   Testing: curl http://$PUBLIC_IP:8000/api/"
    
    # This will likely fail without port forwarding, but shows what needs to work
    if curl -s --max-time 5 "http://$PUBLIC_IP:8000/api/" > /dev/null; then
        echo -e "${GREEN}✅ External access working!${NC}"
    else
        echo -e "${YELLOW}⚠️  External access not working yet. Configure port forwarding.${NC}"
    fi
fi