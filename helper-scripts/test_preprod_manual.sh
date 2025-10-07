#!/bin/bash
# Manual testing script for preprod environment
# Run this on a machine that has access to the preprod network

echo "🔐 Testing Preprod Environment"
echo "================================"

# 1. Test Keycloak connectivity
echo "1. Testing Keycloak connectivity..."
curl -s --connect-timeout 10 http://10.0.2.1:8080/realms/mint-dev > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ Keycloak is accessible"
else
    echo "❌ Cannot connect to Keycloak"
    exit 1
fi

# 2. Get authentication token
echo "2. Getting authentication token..."
TOKEN=$(curl -s -X POST http://10.0.2.1:8080/realms/mint-dev/protocol/openid-connect/token \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "grant_type=password" \
    -d "client_id=mint-back" \
    -d "username=admin" \
    -d "password=admin123" \
    | jq -r '.access_token')

if [ "$TOKEN" == "null" ] || [ -z "$TOKEN" ]; then
    echo "❌ Failed to get authentication token"
    echo "Response:"
    curl -s -X POST http://10.0.2.1:8080/realms/mint-dev/protocol/openid-connect/token \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "grant_type=password" \
        -d "client_id=mint-back" \
        -d "username=admin" \
        -d "password=admin123"
    exit 1
fi

echo "✅ Got authentication token: ${TOKEN:0:50}..."

# 3. Test backend connectivity
echo "3. Testing backend connectivity..."
curl -s --connect-timeout 10 http://10.0.1.2:8000/docs > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ Backend is accessible"
else
    echo "❌ Cannot connect to backend"
    exit 1
fi

# 4. Test folders endpoint
echo "4. Testing folders endpoint..."
FOLDERS_RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" http://10.0.1.2:8000/api/folders/)
FOLDERS_COUNT=$(echo "$FOLDERS_RESPONSE" | jq '. | length' 2>/dev/null)

if [ $? -eq 0 ] && [ "$FOLDERS_COUNT" != "null" ]; then
    echo "✅ Folders endpoint working - found $FOLDERS_COUNT folders"
    
    # Check if folders exist
    if [ "$FOLDERS_COUNT" -gt 0 ]; then
        # Test individual folder endpoint
        FIRST_FOLDER_ID=$(echo "$FOLDERS_RESPONSE" | jq -r '.[0].id')
        echo "5. Testing individual folder endpoint with folder ID: $FIRST_FOLDER_ID"
        
        FOLDER_RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" http://10.0.1.2:8000/api/folders/$FIRST_FOLDER_ID)
        ITEMS_COUNT=$(echo "$FOLDER_RESPONSE" | jq '.items | length' 2>/dev/null)
        
        if [ $? -eq 0 ] && [ "$ITEMS_COUNT" != "null" ]; then
            echo "✅ Individual folder endpoint working - found $ITEMS_COUNT items"
            
            # Check for website field in company items
            COMPANY_WITH_WEBSITE=$(echo "$FOLDER_RESPONSE" | jq -r '.items[] | select(.type == "company" and .website != null) | .name + " - " + .website' | head -1)
            
            if [ -n "$COMPANY_WITH_WEBSITE" ] && [ "$COMPANY_WITH_WEBSITE" != "null" ]; then
                echo "✅ Migration successful - website field found: $COMPANY_WITH_WEBSITE"
            else
                echo "❌ Migration may have failed - no website field found in company items"
                echo "Company items:"
                echo "$FOLDER_RESPONSE" | jq '.items[] | select(.type == "company")'
            fi
        else
            echo "❌ Individual folder endpoint failed"
            echo "Response: $FOLDER_RESPONSE"
        fi
    else
        echo "ℹ️ No folders found to test individual endpoint"
    fi
else
    echo "❌ Folders endpoint failed"
    echo "Response: $FOLDERS_RESPONSE"
fi

echo "================================"
echo "🏁 Testing complete"