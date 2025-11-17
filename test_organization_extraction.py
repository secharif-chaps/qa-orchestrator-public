#!/usr/bin/env python3
"""
Test the organization extraction logic from JWT tokens.
"""

import json
import base64
import requests
from app.core.organization import extract_organization_from_token


def get_token(username: str, password: str) -> str:
    """Get access token from Keycloak"""
    token_url = "http://localhost:8080/realms/mint-dev/protocol/openid-connect/token"
    data = {
        "grant_type": "password",
        "client_id": "mint-back",
        "username": username,
        "password": password,
    }
    response = requests.post(token_url, data=data)
    if response.status_code != 200:
        raise Exception(f"Failed to get token: {response.text}")
    return response.json()["access_token"]


def decode_jwt(token: str) -> dict:
    """Decode JWT token payload"""
    parts = token.split(".")
    payload = parts[1]
    padding = len(payload) % 4
    if padding:
        payload += "=" * (4 - padding)
    decoded = base64.urlsafe_b64decode(payload)
    return json.loads(decoded)


def test_user(username: str, password: str, expected_org_id: str, expected_org_name: str):
    """Test organization extraction for a user"""
    print(f"\n{'='*80}")
    print(f"Testing user: {username}")
    print(f"{'='*80}")

    # Get and decode token
    token = get_token(username, password)
    payload = decode_jwt(token)

    print("\nToken 'organization' claim:")
    print(json.dumps(payload.get("organization"), indent=2))

    # Extract organization
    result = extract_organization_from_token(payload)

    if result:
        org_id, org_name = result
        print("\n✅ Extraction successful:")
        print(f"   Organization ID: {org_id}")
        print(f"   Organization Name: {org_name}")

        # Verify
        if org_id == expected_org_id and org_name == expected_org_name:
            print("\n✅ VERIFICATION PASSED")
            print(f"   Expected: {expected_org_name} ({expected_org_id})")
            print(f"   Got:      {org_name} ({org_id})")
        else:
            print("\n❌ VERIFICATION FAILED")
            print(f"   Expected: {expected_org_name} ({expected_org_id})")
            print(f"   Got:      {org_name} ({org_id})")
    else:
        print("\n❌ Extraction failed - no organization found")


if __name__ == "__main__":
    print("="*80)
    print("ORGANIZATION EXTRACTION TEST")
    print("="*80)

    # Test ChapsVision users
    test_user(
        username="nmr",
        password="Ch@psVision",
        expected_org_id="19226951-820a-4fc1-98eb-26010ac0890c",
        expected_org_name="Chapsvision"
    )

    test_user(
        username="company_manager",
        password="Ch@psVision",
        expected_org_id="19226951-820a-4fc1-98eb-26010ac0890c",
        expected_org_name="Chapsvision"
    )

    # Test TestCompany user
    test_user(
        username="company_viewer",
        password="Ch@psVision",
        expected_org_id="d3380347-17e9-4ef2-80ff-35b830ef161d",
        expected_org_name="TestCompany"
    )

    print(f"\n{'='*80}")
    print("ALL TESTS COMPLETE")
    print(f"{'='*80}\n")
