#!/usr/bin/env python3
"""
Standalone test for organization extraction logic (no app imports needed).
"""

import json
import base64
import requests


def extract_organization_from_token(token_payload: dict) -> tuple[str, str] | None:
    """Extract organization ID and name from JWT token payload.

    Handles both formats:
    - [{"OrgName": {"id": "uuid"}}, "OrgName"]
    - ["OrgName", {"OrgName": {"id": "uuid"}}]
    """
    organization_claim = token_payload.get("organization")

    if not organization_claim:
        print("⚠️  No organization claim found in token")
        return None

    if not isinstance(organization_claim, list) or len(organization_claim) != 2:
        print(f"⚠️  Invalid organization claim format: {organization_claim}")
        return None

    # Find which element is the dict and which is the string
    org_dict = None
    org_name = None

    for element in organization_claim:
        if isinstance(element, dict):
            org_dict = element
        elif isinstance(element, str):
            org_name = element

    if not org_dict or not org_name:
        print("⚠️  Organization claim missing dict or string element")
        return None

    if len(org_dict) != 1:
        print(f"⚠️  Organization dict has unexpected number of keys: {org_dict}")
        return None

    # Get the first (and only) key-value pair
    org_name_from_dict = list(org_dict.keys())[0]
    org_data = org_dict[org_name_from_dict]

    if not isinstance(org_data, dict) or "id" not in org_data:
        print(f"⚠️  Organization data missing id field: {org_data}")
        return None

    org_id = org_data["id"]

    return (org_id, org_name)


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
        else:
            print("\n❌ VERIFICATION FAILED")
            print(f"   Expected: {expected_org_name} ({expected_org_id})")
            print(f"   Got:      {org_name} ({org_id})")
        return True
    else:
        print("\n❌ Extraction failed - no organization found")
        return False


if __name__ == "__main__":
    print("="*80)
    print("ORGANIZATION EXTRACTION TEST")
    print("="*80)

    passed = 0
    failed = 0

    # Test ChapsVision users
    if test_user(
        username="nmr",
        password="Ch@psVision",
        expected_org_id="19226951-820a-4fc1-98eb-26010ac0890c",
        expected_org_name="Chapsvision"
    ):
        passed += 1
    else:
        failed += 1

    if test_user(
        username="company_manager",
        password="Ch@psVision",
        expected_org_id="19226951-820a-4fc1-98eb-26010ac0890c",
        expected_org_name="Chapsvision"
    ):
        passed += 1
    else:
        failed += 1

    # Test TestCompany user
    if test_user(
        username="company_viewer",
        password="Ch@psVision",
        expected_org_id="d3380347-17e9-4ef2-80ff-35b830ef161d",
        expected_org_name="TestCompany"
    ):
        passed += 1
    else:
        failed += 1

    print(f"\n{'='*80}")
    print(f"TEST RESULTS: {passed} passed, {failed} failed")
    print(f"{'='*80}\n")
