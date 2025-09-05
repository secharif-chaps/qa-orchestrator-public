#!/usr/bin/env python3
"""Test script for CSV company import endpoints"""

import json
import requests
import sys

# Get token from command line or use the one we just generated
if len(sys.argv) > 1:
    TOKEN = sys.argv[1]
else:
    # Get fresh token
    import subprocess
    result = subprocess.run(['python3', 'get_token.py'], capture_output=True, text=True)
    # Extract token from output
    for line in result.stdout.split('\n'):
        if line.startswith('eyJ'):
            TOKEN = line.strip()
            break
    else:
        print("Failed to get token")
        sys.exit(1)

BASE_URL = "http://localhost:8000/api"
HEADERS = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

def test_csv_validation():
    """Test CSV validation endpoint"""
    print("\n📋 Testing CSV Validation Endpoint")
    print("=" * 50)
    
    # Test data with mixed valid and invalid companies
    test_data = {
        "companies": [
            {
                "row_number": 1,
                "name": "Test Company A",
                "website": "https://testcompanya.com"
            },
            {
                "row_number": 2,
                "name": "Test Company B",
                "website": "https://testcompanyb.com"
            },
            {
                "row_number": 3,
                "name": "",  # Invalid - empty name
                "website": "https://testcompanyc.com"
            },
            {
                "row_number": 4,
                "name": "Test Company D",
                "website": "not-a-valid-url"  # Invalid URL
            },
            {
                "row_number": 5,
                "name": "Test Company A",  # Duplicate in CSV
                "website": "https://duplicate.com"
            }
        ]
    }
    
    response = requests.post(
        f"{BASE_URL}/companies/csv/validate",
        headers=HEADERS,
        json=test_data
    )
    
    if response.status_code == 200:
        result = response.json()
        print(f"✅ Validation successful!")
        print(f"   Valid companies: {result['valid_count']}")
        print(f"   Invalid companies: {result['error_count']}")
        print(f"   Sufficient tokens: {result['has_sufficient_tokens']}")
        print(f"   Tokens required: {result['tokens_required']}")
        print(f"   Tokens available: {result['tokens_available']}")
        
        if result['errors']:
            print("\n   Validation errors:")
            for error in result['errors']:
                print(f"   - Row {error['row_number']}, Field '{error['field']}': {error['error']}")
    else:
        print(f"❌ Validation failed: {response.status_code}")
        print(f"   {response.text}")
    
    return response.status_code == 200

def test_csv_import():
    """Test CSV import endpoint with valid companies only"""
    print("\n📋 Testing CSV Import Endpoint")
    print("=" * 50)
    
    # Test data with only valid companies
    test_data = {
        "companies": [
            {
                "row_number": 1,
                "name": "CSV Import Test 1",
                "website": "https://csvtest1.com"
            },
            {
                "row_number": 2,
                "name": "CSV Import Test 2",
                "website": "https://csvtest2.com"
            },
            {
                "row_number": 3,
                "name": "CSV Import Test 3",
                "website": "https://csvtest3.com"
            }
        ],
        "skip_invalid": True
    }
    
    # First validate
    print("🔍 Validating companies first...")
    validate_response = requests.post(
        f"{BASE_URL}/companies/csv/validate",
        headers=HEADERS,
        json={"companies": test_data["companies"]}
    )
    
    if validate_response.status_code != 200:
        print(f"❌ Validation failed: {validate_response.status_code}")
        return False
    
    validation_result = validate_response.json()
    if not validation_result['has_sufficient_tokens']:
        print(f"❌ Insufficient tokens for import")
        return False
    
    # Now import
    print("📥 Importing companies...")
    response = requests.post(
        f"{BASE_URL}/companies/csv/import",
        headers=HEADERS,
        json=test_data
    )
    
    if response.status_code == 200:
        result = response.json()
        print(f"✅ Import successful!")
        print(f"   Total rows: {result['total_rows']}")
        print(f"   Successful: {result['successful']}")
        print(f"   Failed: {result['failed']}")
        
        print("\n   Results by row:")
        for res in result['results']:
            status = "✅" if res['success'] else "❌"
            print(f"   {status} Row {res['row_number']}: {res['name']}")
            if res['company_id']:
                print(f"      Company ID: {res['company_id']}")
            if res['error']:
                print(f"      Error: {res['error']}")
    else:
        print(f"❌ Import failed: {response.status_code}")
        print(f"   {response.text}")
    
    return response.status_code == 200

def test_insufficient_tokens():
    """Test import with insufficient tokens (simulate by requesting many companies)"""
    print("\n📋 Testing Insufficient Tokens Scenario")
    print("=" * 50)
    
    # Create many companies to trigger token insufficiency
    companies = []
    for i in range(100):  # Request 100 companies
        companies.append({
            "row_number": i + 1,
            "name": f"Token Test Company {i + 1}",
            "website": f"https://tokentest{i + 1}.com"
        })
    
    test_data = {
        "companies": companies,
        "skip_invalid": True
    }
    
    response = requests.post(
        f"{BASE_URL}/companies/csv/import",
        headers=HEADERS,
        json=test_data
    )
    
    if response.status_code == 402:
        print("✅ Correctly returned 402 Payment Required for insufficient tokens")
        print(f"   Message: {response.json()['detail']}")
        return True
    elif response.status_code == 200:
        print("⚠️  Import succeeded (user has sufficient tokens)")
        return True
    else:
        print(f"❌ Unexpected response: {response.status_code}")
        print(f"   {response.text}")
        return False

def cleanup_test_companies():
    """Clean up test companies created during testing"""
    print("\n🧹 Cleaning up test companies...")
    
    # Get all companies
    response = requests.get(
        f"{BASE_URL}/companies/",
        headers=HEADERS,
        params={"per_page": 100}
    )
    
    if response.status_code == 200:
        companies = response.json()['items']
        test_companies = [c for c in companies if 
                         'CSV Import Test' in c['name'] or 
                         'Token Test Company' in c['name']]
        
        for company in test_companies:
            delete_response = requests.delete(
                f"{BASE_URL}/companies/{company['id']}",
                headers=HEADERS
            )
            if delete_response.status_code == 200:
                print(f"   Deleted: {company['name']}")
    
    print("   Cleanup complete!")

if __name__ == "__main__":
    print("🚀 Starting CSV Import API Tests")
    print("=" * 50)
    
    all_passed = True
    
    # Run tests
    if not test_csv_validation():
        all_passed = False
    
    if not test_csv_import():
        all_passed = False
    
    if not test_insufficient_tokens():
        all_passed = False
    
    # Cleanup
    cleanup_test_companies()
    
    # Summary
    print("\n" + "=" * 50)
    if all_passed:
        print("✅ All tests passed!")
    else:
        print("❌ Some tests failed")
    
    sys.exit(0 if all_passed else 1)