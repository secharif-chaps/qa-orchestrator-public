#!/usr/bin/env python3
"""
Test script for cost analysis API endpoints
"""

import requests
from datetime import date, datetime, timedelta
import json

# Base URL for the API
BASE_URL = "http://localhost:8000/api"

# Test data - You'll need to get a valid token from Keycloak
# For testing, you can get a token by logging in through the frontend
# and checking the network tab in browser dev tools
TOKEN = "YOUR_JWT_TOKEN_HERE"  # Replace with actual token

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Content-Type": "application/json"
}

def test_global_cost_analysis():
    """Test the global cost analysis endpoint"""
    print("\n1. Testing Global Cost Analysis")
    print("-" * 40)
    
    # Test with default date range (current month)
    response = requests.get(f"{BASE_URL}/cost-analysis/global", headers=headers)
    print(f"Status Code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"Period: {data['period']['start_date']} to {data['period']['end_date']}")
        print(f"Total Cost: ${data['global_summary']['total_cost']:.2f}")
        print(f"Total Tasks: {data['global_summary']['total_tasks']}")
        print(f"Total Companies: {data['global_summary']['total_companies']}")
    else:
        print(f"Error: {response.json()}")
    
    # Test with custom date range
    print("\n   Testing with custom date range...")
    params = {
        "start_date": (date.today() - timedelta(days=30)).isoformat(),
        "end_date": date.today().isoformat()
    }
    response = requests.get(f"{BASE_URL}/cost-analysis/global", headers=headers, params=params)
    print(f"   Status Code: {response.status_code}")


def test_workspace_cost_analysis():
    """Test the workspace cost analysis endpoint"""
    print("\n2. Testing Workspace Cost Analysis")
    print("-" * 40)
    
    response = requests.get(f"{BASE_URL}/cost-analysis/by-workspace", headers=headers)
    print(f"Status Code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"Total Workspaces: {data['summary']['total_workspaces']}")
        print(f"Total Cost: ${data['summary']['total_cost']:.2f}")
        
        if data['workspaces']:
            print("\nTop 3 Workspaces by Cost:")
            for ws in data['workspaces'][:3]:
                print(f"  - {ws['workspace_name']}: ${ws['total_cost']:.2f} ({ws['task_count']} tasks)")
    else:
        print(f"Error: {response.json()}")


def test_task_type_cost_analysis():
    """Test the task type cost analysis endpoint"""
    print("\n3. Testing Task Type Cost Analysis")
    print("-" * 40)
    
    response = requests.get(f"{BASE_URL}/cost-analysis/by-task-type", headers=headers)
    print(f"Status Code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"Total Task Types: {data['summary']['total_task_types']}")
        print(f"Most Expensive Type: {data['summary']['most_expensive_type']}")
        print(f"Most Frequent Type: {data['summary']['most_frequent_type']}")
        
        if data['task_types']:
            print("\nCost by Task Type:")
            for tt in data['task_types']:
                print(f"  - {tt['task_type']}: ${tt['total_cost']:.2f} (avg: ${tt['avg_cost_per_task']:.4f})")
    else:
        print(f"Error: {response.json()}")


def test_cost_trends():
    """Test the cost trends endpoint"""
    print("\n4. Testing Cost Trends")
    print("-" * 40)
    
    params = {
        "granularity": "daily",
        "start_date": (date.today() - timedelta(days=7)).isoformat(),
        "end_date": date.today().isoformat()
    }
    
    response = requests.get(f"{BASE_URL}/cost-analysis/trends", headers=headers, params=params)
    print(f"Status Code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"Granularity: {data['granularity']}")
        print(f"Total Periods: {data['summary']['total_periods']}")
        print(f"Average Cost per Period: ${data['summary']['avg_cost_per_period']:.2f}")
        
        if data['trends']:
            print("\nLast 3 Days:")
            for trend in data['trends'][-3:]:
                print(f"  - {trend['period']}: ${trend['total_cost']:.2f} ({trend['task_count']} tasks)")
    else:
        print(f"Error: {response.json()}")


def test_refresh_views():
    """Test the refresh materialized views endpoint"""
    print("\n5. Testing Refresh Materialized Views")
    print("-" * 40)
    
    response = requests.post(f"{BASE_URL}/cost-analysis/refresh-materialized-views", headers=headers)
    print(f"Status Code: {response.status_code}")
    
    if response.status_code == 200:
        data = response.json()
        print(f"Status: {data['status']}")
        print(f"Message: {data['message']}")
        print(f"Timestamp: {data['timestamp']}")
    else:
        print(f"Error: {response.json()}")


if __name__ == "__main__":
    print("=" * 50)
    print("Cost Analysis API Test Suite")
    print("=" * 50)
    
    print("\nNOTE: You need to replace TOKEN variable with a valid JWT token")
    print("that has the 'admin.cost' permission.")
    print("\nYou can get a token by:")
    print("1. Logging in through the frontend as an admin user")
    print("2. Opening browser dev tools -> Network tab")
    print("3. Making any API request and copying the Authorization header")
    
    # Uncomment the following lines after setting a valid token
    # test_global_cost_analysis()
    # test_workspace_cost_analysis()
    # test_task_type_cost_analysis()
    # test_cost_trends()
    # test_refresh_views()
    
    print("\n" + "=" * 50)
    print("Test Suite Completed")
    print("=" * 50)