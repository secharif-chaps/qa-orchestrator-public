#!/usr/bin/env python3
"""
Script to get authentication token from Keycloak for testing
Usage: python get_token.py [username] [password]
"""

import requests
import json
import sys
import os
from typing import Optional

# Keycloak configuration
KEYCLOAK_URL = "http://localhost:8080"
REALM = "mint-dev"
CLIENT_ID = "mint-back"
CLIENT_SECRET = ""  # No secret for public client

def get_token(username: str = "admin", password: str = "admin123") -> Optional[str]:
    """
    Get access token from Keycloak
    
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
        print(f"🔑 Authenticating as {username}...")
        response = requests.post(token_url, data=data)
        
        if response.status_code == 200:
            token_data = response.json()
            access_token = token_data.get("access_token")
            print("✅ Authentication successful!")
            print(f"\n📋 Access Token:\n{access_token}\n")
            
            # Also print curl command example
            print("📝 Example usage with curl:")
            print(f'curl -H "Authorization: Bearer {access_token}" http://localhost:8000/api/folders/')
            
            return access_token
        else:
            print(f"❌ Authentication failed: {response.status_code}")
            print(f"Response: {response.text}")
            return None
            
    except Exception as e:
        print(f"❌ Error connecting to Keycloak: {e}")
        return None

def check_keycloak_health():
    """Check if Keycloak is running and accessible"""
    try:
        response = requests.get(f"{KEYCLOAK_URL}/realms/{REALM}")
        if response.status_code == 200:
            print("✅ Keycloak is running and realm is accessible")
            return True
        else:
            print(f"⚠️ Keycloak realm returned status: {response.status_code}")
            return False
    except Exception as e:
        print(f"❌ Cannot connect to Keycloak at {KEYCLOAK_URL}: {e}")
        return False

def main():
    print("🔐 Keycloak Token Getter")
    print("=" * 50)
    
    # Check Keycloak health first
    if not check_keycloak_health():
        print("\n⚠️ Please ensure Keycloak is running:")
        print("docker compose -f docker-compose.dev.yml up -d keycloak")
        sys.exit(1)
    
    # Get credentials from command line or use defaults
    if len(sys.argv) >= 3:
        username = sys.argv[1]
        password = sys.argv[2]
    else:
        print("\n💡 Usage: python get_token.py [username] [password]")
        print("Using default admin credentials...")
        username = "admin"
        password = "admin123"
    
    token = get_token(username, password)
    
    if not token:
        print("\n💡 Tips:")
        print("1. Make sure the user exists in Keycloak")
        print("2. Check Keycloak admin at http://localhost:8080")
        print("3. Default admin credentials: admin/admin")
        print("4. Create test user in 'mint-dev' realm")

if __name__ == "__main__":
    main()