#!/usr/bin/env python3
"""
Verify JWT token structure and organization_id claim
"""
import sys
import json
import base64
import requests


def get_token(username: str, password: str, client_id: str = "mint-back"):
    """Get access token from Keycloak"""
    keycloak_url = "http://localhost:8080"
    realm = "mint-dev"

    token_url = f"{keycloak_url}/realms/{realm}/protocol/openid-connect/token"

    data = {
        "grant_type": "password",
        "client_id": client_id,
        "username": username,
        "password": password,
    }

    response = requests.post(token_url, data=data)

    if response.status_code != 200:
        print(f"❌ Error getting token: {response.status_code}")
        print(response.text)
        sys.exit(1)

    return response.json()["access_token"]


def decode_jwt(token: str):
    """Decode JWT token (without verification, just for inspection)"""
    # Split the token
    parts = token.split(".")

    if len(parts) != 3:
        print("❌ Invalid JWT token format")
        sys.exit(1)

    # Decode the payload (second part)
    payload = parts[1]

    # Add padding if needed
    padding = len(payload) % 4
    if padding:
        payload += "=" * (4 - padding)

    decoded = base64.urlsafe_b64decode(payload)
    return json.loads(decoded)


def verify_token_structure(payload: dict, username: str):
    """Verify the token has required claims"""
    print(f"\n{'='*60}")
    print(f"JWT Token Verification for user: {username}")
    print(f"{'='*60}\n")

    # Check for organization_id
    if "organization_id" in payload:
        print(f"✅ organization_id claim present: {payload['organization_id']}")
    else:
        print("❌ organization_id claim MISSING")

    # Check for sub (user UUID)
    if "sub" in payload:
        print(f"✅ sub claim present (user UUID): {payload['sub']}")
    else:
        print("❌ sub claim MISSING")

    # Check for preferred_username
    if "preferred_username" in payload:
        print(f"✅ preferred_username claim present: {payload['preferred_username']}")
    else:
        print("❌ preferred_username claim MISSING")

    # Check for realm_access roles
    if "realm_access" in payload and "roles" in payload["realm_access"]:
        print(f"✅ realm_access.roles present: {len(payload['realm_access']['roles'])} roles")
    else:
        print("❌ realm_access.roles MISSING")

    print(f"\n{'='*60}")
    print("Full Token Payload (formatted):")
    print(f"{'='*60}\n")
    print(json.dumps(payload, indent=2))
    print()


if __name__ == "__main__":
    # Get username and password from command line or use defaults
    if len(sys.argv) >= 3:
        username = sys.argv[1]
        password = sys.argv[2]
    else:
        # Default test user
        username = "nmr"
        password = input(f"Enter password for {username}: ")

    # Get client_id if provided
    client_id = sys.argv[3] if len(sys.argv) >= 4 else "mint-back"

    print(f"Getting token for user: {username}")
    print(f"Using client: {client_id}\n")

    # Get the token
    token = get_token(username, password, client_id)

    # Decode and verify
    payload = decode_jwt(token)
    verify_token_structure(payload, username)

    # Print the full token for manual testing
    print(f"{'='*60}")
    print("Full Access Token (for testing):")
    print(f"{'='*60}\n")
    print(token)
    print()
