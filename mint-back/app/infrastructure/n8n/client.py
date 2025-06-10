import httpx
from typing import Dict, Any, Optional
from app.core.config import settings

class N8nClient:
    """Client for interacting with n8n workflows"""
    
    def __init__(self, base_url: str = None, webhook_id: str = None):
        self.base_url = base_url or settings.N8N_BASE_URL
        self.webhook_id = webhook_id or settings.N8N_WEBHOOK_ID
        
    async def trigger_workflow(self, company: str, website: str, query: str) -> Dict[str, Any]:
        """
        Trigger an n8n workflow for a specific company data query
        
        Args:
            company: Company name
            website: Company website
            query: Type of query (profile, team, products, etc.)
            
        Returns:
            Response data from the n8n workflow
        """
        url = f"{self.base_url}/webhook/{self.webhook_id}"
        
        # Log the request details
        print(f"Triggering n8n workflow for {query}")
        print(f"URL: {url}")
        print(f"Request data: {{'company': {company}, 'website': {website}, 'query': {query}}}")
        
        try:
            async with httpx.AsyncClient() as client:
                # Increased timeout to 5 minutes
                response = await client.post(
                    url,
                    json={
                        "company": company,
                        "website": website,
                        "query": query
                    },
                    timeout=300.0  # 5 minutes timeout
                )
                
                # Log the raw response for debugging
                print(f"N8n raw response status: {response.status_code}")
                print(f"N8n raw response headers: {response.headers}")
                print(f"N8n raw response body: {response.text}")
                
                if response.status_code != 200:
                    error_msg = f"N8n workflow returned non-200 status code: {response.status_code}"
                    print(error_msg)
                    raise Exception(error_msg)
                
                try:
                    # Parse the response text directly
                    import json
                    json_response = json.loads(response.text)
                    
                    if json_response is None:
                        error_msg = "N8n workflow returned null response"
                        print(error_msg)
                        raise Exception(error_msg)
                    
                    # If the response is a list, take the first item
                    if isinstance(json_response, list):
                        if not json_response:
                            error_msg = "N8n workflow returned empty list"
                            print(error_msg)
                            raise Exception(error_msg)
                        json_response = json_response[0]
                    
                    # Ensure we have a dictionary
                    if not isinstance(json_response, dict):
                        error_msg = f"N8n workflow returned unexpected type: {type(json_response)}"
                        print(error_msg)
                        raise Exception(error_msg)
                    
                    return json_response
                except json.JSONDecodeError as e:
                    error_msg = f"Failed to parse n8n response as JSON: {str(e)}"
                    print(error_msg)
                    raise Exception(error_msg)
                except Exception as e:
                    error_msg = f"Error processing n8n response: {str(e)}"
                    print(error_msg)
                    raise Exception(error_msg)
                
        except httpx.TimeoutException as e:
            error_msg = f"Request to n8n timed out after 5 minutes. This might indicate that the workflow is taking longer than expected to complete."
            print(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred: {str(e)}"
            if hasattr(e, 'response') and e.response is not None:
                error_msg += f"\nStatus code: {e.response.status_code}"
                error_msg += f"\nResponse body: {e.response.text}"
            print(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error triggering n8n workflow: {str(e)}"
            print(error_msg)
            raise Exception(error_msg) from e
            
    async def check_workflow_status(self, execution_id: str) -> Dict[str, Any]:
        """
        Check status of a workflow execution
        
        Args:
            execution_id: ID of the workflow execution
            
        Returns:
            Status data for the execution
        """
        url = f"{self.base_url}/executions/{execution_id}"
        
        async with httpx.AsyncClient() as client:
            response = await client.get(url)
            await response.raise_for_status()
            return await response.json() 