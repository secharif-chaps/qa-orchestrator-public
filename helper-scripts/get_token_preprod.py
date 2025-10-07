#!/usr/bin/env python3
"""
Script to get authentication token from Preprod Keycloak for testing
Usage: python get_token_preprod.py [username] [password]
"""

import requests
import json
import sys
import os
from typing import Optional

# Preprod Keycloak configuration
KEYCLOAK_URL = "http://10.0.2.1:8080"
REALM = "mint-dev"
CLIENT_ID = "mint-back"
CLIENT_SECRET = ""  # No secret for public client
BACKEND_URL = "http://10.0.1.2:8000"  # Preprod backend URL

def get_token(username: str = "admin", password: str = "admin123") -> Optional[str]:
    """
    Get access token from Preprod Keycloak
    
    Args:
        username: Username or email
        password: Password
    
    Returns:
        Access token string or None if failed
    """
    token_url = f"{KEYCLOAK_URL}/realms/{REALM}/protocol/openid-connect/token"
    
    data = {
        "grant_type": "password",
        "client_id": CLIENT_ID,
        "username": username,
        "password": password
    }
    
    if CLIENT_SECRET:
        data["client_secret"] = CLIENT_SECRET
    
    try:
        print(f"🔑 Authenticating as {username} on preprod Keycloak...")
        response = requests.post(token_url, data=data, timeout=10)
        
        if response.status_code == 200:
            token_data = response.json()
            access_token = token_data.get("access_token")
            print("✅ Authentication successful!")
            print(f"\n📋 Access Token:\n{access_token}\n")
            
            # Print curl command examples for preprod
            print("📝 Example usage with preprod backend:")
            print(f'curl -H "Authorization: Bearer {access_token}" {BACKEND_URL}/api/folders/')
            print(f'curl -H "Authorization: Bearer {access_token}" {BACKEND_URL}/api/companies/')
            
            return access_token
        else:
            print(f"❌ Authentication failed: {response.status_code}")
            print(f"Response: {response.text}")
            return None
            
    except Exception as e:
        print(f"❌ Error connecting to Preprod Keycloak: {e}")
        return None

def check_keycloak_health():
    """Check if Preprod Keycloak is running and accessible"""
    try:
        print(f"🔍 Checking preprod Keycloak at {KEYCLOAK_URL}...")
        response = requests.get(f"{KEYCLOAK_URL}/realms/{REALM}", timeout=10)
        if response.status_code == 200:
            print("✅ Preprod Keycloak is running and realm is accessible")
            return True
        else:
            print(f"⚠️ Preprod Keycloak realm returned status: {response.status_code}")
            return False
    except Exception as e:
        print(f"❌ Cannot connect to Preprod Keycloak at {KEYCLOAK_URL}: {e}")
        print("💡 Make sure you can access the preprod server network")
        return False

def test_backend_connectivity():
    """Test if preprod backend is accessible"""
    try:
        print(f"🔍 Testing preprod backend at {BACKEND_URL}...")
        response = requests.get(f"{BACKEND_URL}/docs", timeout=10)
        if response.status_code == 200:
            print("✅ Preprod backend is accessible")
            return True
        else:
            print(f"⚠️ Preprod backend returned status: {response.status_code}")
            return False
    except Exception as e:
        print(f"❌ Cannot connect to Preprod backend at {BACKEND_URL}: {e}")
        return False

def main():
    print("🔐 Preprod Keycloak Token Getter")
    print("=" * 50)
    
    # Check Keycloak health first
    if not check_keycloak_health():
        print("\n⚠️ Cannot access preprod Keycloak")
        print("Make sure you're connected to the right network and the server is running")
        sys.exit(1)
    
    # Test backend connectivity
    test_backend_connectivity()
    
    # Get credentials from command line or use defaults
    if len(sys.argv) >= 3:
        username = sys.argv[1]
        password = sys.argv[2]
    else:
        print("\n💡 Usage: python get_token_preprod.py [username] [password]")
        print("Using default admin credentials...")
        username = "admin"
        password = "admin123"
    
    token = get_token(username, password)
    
    if not token:
        print("\n💡 Tips:")
        print("1. Make sure the user exists in Preprod Keycloak")
        print("2. Check Preprod Keycloak admin at http://10.0.2.1:8080")
        print("3. Ensure network connectivity to preprod environment")
        print("4. Verify the preprod deployment is running")
        
        return False
    
    print("\n🧪 Testing folder endpoint to verify migration...")
    try:
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.get(f"{BACKEND_URL}/api/folders/", headers=headers, timeout=10)
        
        if response.status_code == 200:
            folders = response.json()
            print(f"✅ Folders endpoint working - found {len(folders)} folders")
            
            # Test a specific folder if available
            if folders:
                folder_id = folders[0]['id']
                response = requests.get(f"{BACKEND_URL}/api/folders/{folder_id}", headers=headers, timeout=10)
                if response.status_code == 200:
                    folder_data = response.json()
                    items = folder_data.get('items', [])
                    print(f"✅ Individual folder endpoint working - found {len(items)} items")
                    
                    # Check if website field is present in company items
                    company_items = [item for item in items if item.get('type') == 'company']
                    if company_items:
                        first_company = company_items[0]
                        if 'website' in first_company:
                            print(f"✅ Migration successful - website field present: {first_company.get('website', 'None')}")
                        else:
                            print("❌ Migration may have failed - website field missing from company items")
                    else:
                        print("ℹ️ No company items found to verify website field")
                else:
                    print(f"❌ Individual folder endpoint failed: {response.status_code}")
        else:
            print(f"❌ Folders endpoint failed: {response.status_code} - {response.text}")
            
    except Exception as e:
        print(f"❌ Error testing endpoints: {e}")
    
    return True

if __name__ == "__main__":
    main()