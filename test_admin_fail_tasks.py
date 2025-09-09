#!/usr/bin/env python3
"""Test script for admin fail-stuck-tasks endpoint."""

import requests
import sys
import subprocess
import json

def get_admin_token():
    """Get authentication token for admin user."""
    try:
        result = subprocess.run(
            ["python3", "get_token.py", "admin", "admin123"],
            capture_output=True,
            text=True,
            check=True
        )
        # Extract token from output - look for the line starting with "curl -H"
        lines = result.stdout.strip().split('\n')
        for line in lines:
            if 'curl -H "Authorization: Bearer' in line:
                # Extract the Bearer token from the curl command
                start = line.find('Bearer ')
                end = line.find('"', start)
                if start != -1 and end != -1:
                    return line[start:end]
        print("Could not extract token from get_token.py output")
        return None
    except subprocess.CalledProcessError as e:
        print(f"Failed to get token: {e}")
        print(f"Error output: {e.stderr}")
        return None

def test_fail_stuck_tasks():
    """Test the fail-stuck-tasks admin endpoint."""
    # Get admin token
    token = get_admin_token()
    if not token:
        print("Failed to get admin token")
        sys.exit(1)
    
    print(f"Got admin token: {token[:50]}...")
    
    # Test the endpoint
    url = "http://localhost:8000/api/admin/tasks/fail-stuck"
    headers = {
        "Authorization": token,
        "Content-Type": "application/json"
    }
    
    print(f"\nCalling POST {url}")
    
    try:
        response = requests.post(url, headers=headers)
        
        print(f"Status Code: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print("\nSuccess! Response:")
            print(json.dumps(data, indent=2))
        elif response.status_code == 403:
            print("\nAccess denied - user doesn't have admin.workspaces permission")
            print(f"Response: {response.text}")
        elif response.status_code == 401:
            print("\nAuthentication failed")
            print(f"Response: {response.text}")
        else:
            print(f"\nUnexpected response:")
            print(f"Response: {response.text}")
            
    except requests.exceptions.RequestException as e:
        print(f"\nRequest failed: {e}")
        sys.exit(1)

if __name__ == "__main__":
    print("Testing Admin Fail Stuck Tasks Endpoint")
    print("=" * 50)
    test_fail_stuck_tasks()