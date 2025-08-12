#!/usr/bin/env python3
"""
Test script for Dify integration with products task
"""

import asyncio
import httpx
import json
from datetime import datetime

# Configuration
BASE_URL = "http://localhost:8000/api/v1"
TEST_COMPANY_NAME = "Figma"
TEST_COMPANY_WEBSITE = "https://www.figma.com"

# You'll need a valid token - get this from your Keycloak or authentication system
# For testing, you might want to temporarily disable auth or use a test token
AUTH_TOKEN = "YOUR_AUTH_TOKEN_HERE"  # Replace with actual token

async def test_dify_integration():
    """Test the Dify workflow integration for products task"""
    
    headers = {
        "Authorization": f"Bearer {AUTH_TOKEN}",
        "Content-Type": "application/json"
    }
    
    async with httpx.AsyncClient() as client:
        print(f"\n🚀 Testing Dify Integration - {datetime.now()}")
        print("=" * 60)
        
        # Step 1: Get or create a test company
        print(f"\n1️⃣ Creating test company: {TEST_COMPANY_NAME}")
        
        # Try to create the company (might already exist)
        try:
            create_response = await client.post(
                f"{BASE_URL}/companies",
                json={
                    "name": TEST_COMPANY_NAME,
                    "website": TEST_COMPANY_WEBSITE
                },
                headers=headers
            )
            if create_response.status_code == 201:
                company_data = create_response.json()
                company_id = company_data["id"]
                print(f"✅ Company created with ID: {company_id}")
            else:
                print(f"⚠️ Could not create company: {create_response.status_code}")
                print(f"Response: {create_response.text}")
                
                # Try to get existing company
                search_response = await client.get(
                    f"{BASE_URL}/companies",
                    params={"name": TEST_COMPANY_NAME},
                    headers=headers
                )
                if search_response.status_code == 200:
                    companies = search_response.json()
                    if companies and len(companies) > 0:
                        company_id = companies[0]["id"]
                        print(f"✅ Using existing company with ID: {company_id}")
                    else:
                        print("❌ No company found and could not create one")
                        return
                else:
                    print(f"❌ Could not search for company: {search_response.status_code}")
                    return
        except Exception as e:
            print(f"❌ Error creating/finding company: {e}")
            return
        
        # Step 2: Create a products task
        print(f"\n2️⃣ Creating products task for company ID: {company_id}")
        
        task_response = await client.post(
            f"{BASE_URL}/tasks",
            json={
                "company_id": company_id,
                "type": "products"
            },
            headers=headers
        )
        
        if task_response.status_code == 200:
            task_data = task_response.json()
            task_id = task_data["id"]
            print(f"✅ Task created with ID: {task_id}")
            print(f"   Status: {task_data['status']}")
            print(f"   Type: {task_data['type']}")
        else:
            print(f"❌ Could not create task: {task_response.status_code}")
            print(f"Response: {task_response.text}")
            return
        
        # Step 3: Monitor task status
        print(f"\n3️⃣ Monitoring task status...")
        print("   (This will use Dify workflow for products task)")
        
        max_attempts = 60  # Wait up to 5 minutes
        attempt = 0
        
        while attempt < max_attempts:
            await asyncio.sleep(5)  # Check every 5 seconds
            attempt += 1
            
            # Get task status
            tasks_response = await client.get(
                f"{BASE_URL}/tasks/company/{company_id}",
                headers=headers
            )
            
            if tasks_response.status_code == 200:
                tasks = tasks_response.json()
                product_task = next((t for t in tasks if t["id"] == task_id), None)
                
                if product_task:
                    status = product_task["status"]
                    print(f"   Attempt {attempt}: Status = {status}")
                    
                    if status == "succeeded":
                        print(f"\n✅ Task completed successfully!")
                        
                        # Get company data to see the results
                        company_response = await client.get(
                            f"{BASE_URL}/companies/{company_id}",
                            headers=headers
                        )
                        
                        if company_response.status_code == 200:
                            company = company_response.json()
                            products_data = company.get("products", {})
                            
                            print(f"\n📦 Products Data Retrieved:")
                            print("-" * 40)
                            print(json.dumps(products_data, indent=2)[:1000])  # First 1000 chars
                            if len(json.dumps(products_data)) > 1000:
                                print("... (truncated)")
                        break
                    
                    elif status == "error":
                        error_msg = product_task.get("error", "Unknown error")
                        print(f"\n❌ Task failed with error: {error_msg}")
                        break
                    
                    elif status == "running":
                        continue
                    
            else:
                print(f"⚠️ Could not get tasks: {tasks_response.status_code}")
        
        if attempt >= max_attempts:
            print(f"\n⏱️ Task timed out after {max_attempts * 5} seconds")
        
        print("\n" + "=" * 60)
        print("Test completed!")

if __name__ == "__main__":
    print("""
    ⚠️  IMPORTANT: Before running this test:
    
    1. Make sure you have a valid AUTH_TOKEN
       - Get it from Keycloak or your authentication system
       - Update the AUTH_TOKEN variable in this script
    
    2. Ensure Dify is running and accessible at http://10.0.1.1
    
    3. Ensure the backend service is running:
       docker compose -f docker-compose.dev.yml ps
    
    Press Ctrl+C to cancel, or wait to continue...
    """)
    
    try:
        asyncio.run(test_dify_integration())
    except KeyboardInterrupt:
        print("\n\nTest cancelled by user")
    except Exception as e:
        print(f"\n❌ Test failed with error: {e}")