#!/usr/bin/env python3
"""
Quick test script to verify the timeline workflow ID with Dify API
"""
import httpx
import asyncio
import json

# Configuration from your settings
DIFY_API_KEY = "app-WpGZCTFDaBzCUS9M4LeoQHGa"
DIFY_URL = "http://10.0.1.1/v1"
PRODUCT_WORKFLOW_ID = "9d9c4884-342d-42d4-afcc-2ef15479db2f"
TIMELINE_WORKFLOW_ID = "f867cef4-8f39-4bd6-a2b2-b959ee0e3e3b"

async def test_workflow_endpoint(workflow_id: str, workflow_name: str):
    """Test if a workflow endpoint is accessible"""
    headers = {
        "Authorization": f"Bearer {DIFY_API_KEY}",
        "Content-Type": "application/json"
    }
    
    # Test payload (minimal)
    payload = {
        "inputs": {
            "company": "Test Company",
            "website": "https://test.com",
            "callback_webhook": "https://test.com/callback"
        },
        "response_mode": "blocking",
        "user": "test_user"
    }
    
    url = f"{DIFY_URL}/workflows/{workflow_id}/run"
    
    print(f"\n=== Testing {workflow_name} Workflow ===")
    print(f"Workflow ID: {workflow_id}")
    print(f"URL: {url}")
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                url,
                json=payload,
                headers=headers,
                timeout=10.0
            )
            
            print(f"Status Code: {response.status_code}")
            print(f"Response: {response.text[:500]}...")
            
            if response.status_code == 404:
                print(f"❌ {workflow_name} workflow NOT FOUND!")
                return False
            elif response.status_code in [200, 201, 202]:
                print(f"✅ {workflow_name} workflow is accessible!")
                return True
            else:
                print(f"⚠️ {workflow_name} workflow returned {response.status_code}")
                return False
                
    except Exception as e:
        print(f"💥 Error testing {workflow_name} workflow: {str(e)}")
        return False

async def main():
    print("🔍 Testing Dify Workflow Accessibility...")
    print(f"Base URL: {DIFY_URL}")
    print(f"API Key: {DIFY_API_KEY[:10]}...")
    
    # Test both workflows
    products_ok = await test_workflow_endpoint(PRODUCT_WORKFLOW_ID, "Products")
    timeline_ok = await test_workflow_endpoint(TIMELINE_WORKFLOW_ID, "Timeline")
    
    print(f"\n=== Summary ===")
    print(f"Products workflow: {'✅ OK' if products_ok else '❌ FAILED'}")
    print(f"Timeline workflow: {'✅ OK' if timeline_ok else '❌ FAILED'}")
    
    if not timeline_ok:
        print(f"\n🔍 Troubleshooting suggestions:")
        print(f"1. Check if timeline workflow is published (not draft)")
        print(f"2. Verify workflow ID in Dify dashboard")
        print(f"3. Check if API key has access to timeline workflow")
        print(f"4. Confirm workflow is in the same Dify instance")

if __name__ == "__main__":
    asyncio.run(main())