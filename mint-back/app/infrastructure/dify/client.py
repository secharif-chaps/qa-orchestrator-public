import httpx
import logging
from typing import Dict, Any, Optional
from app.core.config import settings

logger = logging.getLogger(__name__)

class DifyClient:
    """Client for interacting with Dify workflows"""
    
    def __init__(self):
        self.api_key = settings.DIFY_API_KEY
        self.base_url = settings.DIFY_URL
        self.product_workflow_url = settings.DIFY_PRODUCT_WORKFLOW_URL
        
    async def trigger_product_workflow(
        self, 
        company_name: str, 
        website: str, 
        success_callback: str,
        error_callback: str,
        task_id: int,
        company_id: int,
        async_mode: bool = True
    ) -> Dict[str, Any]:
        """
        Trigger the Dify product workflow with callback URLs
        
        Args:
            company_name: Company name
            website: Company website
            success_callback: URL to call on successful completion
            error_callback: URL to call on error
            task_id: Task ID for callback reference
            company_id: Company ID for callback reference
            async_mode: If True, returns immediately (fire-and-forget)
            
        Returns:
            Response data from Dify (acknowledgment if async, results if sync)
        """
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }
        
        # Prepare the callback payload that Dify should send back
        callback_payload_template = {
            "task_id": task_id,
            "company_id": company_id,
            "task_type": "products"
        }
        
        payload = {
            "inputs": {
                "company_name": company_name,
                "company_website": website,
                "success_callback": success_callback,
                "error_callback": error_callback,
                "callback_payload": callback_payload_template  # Include task metadata for callback
            },
            "response_mode": "blocking",  # Dify still processes synchronously but we don't wait
            "user": f"company_{company_id}"  # Use company ID for unique identifier
        }
        
        logger.info(f"Triggering Dify product workflow for {company_name} ({website}) - Task ID: {task_id}")
        logger.info(f"Mode: {'Async (fire-and-forget)' if async_mode else 'Sync (wait for response)'}")
        logger.info(f"Success callback: {success_callback}")
        logger.info(f"Error callback: {error_callback}")
        
        try:
            if async_mode:
                # Fire-and-forget mode: Send request but don't wait for workflow completion
                async with httpx.AsyncClient() as client:
                    # Use a short timeout just for the initial request acknowledgment
                    response = await client.post(
                        f"{self.product_workflow_url}/run",
                        json=payload,
                        headers=headers,
                        timeout=10.0  # Just wait for acknowledgment, not completion
                    )
                    
                    if response.status_code not in [200, 201, 202]:
                        error_msg = f"Dify workflow trigger failed with status: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)
                    
                    logger.info(f"✅ Dify workflow triggered successfully for task {task_id} (async mode)")
                    return {"status": "triggered", "task_id": task_id, "message": "Workflow started, will callback when complete"}
            
            else:
                # Synchronous mode: Wait for the full response (fallback option)
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.product_workflow_url}/run",
                        json=payload,
                        headers=headers,
                        timeout=300.0  # 5 minutes timeout for blocking mode
                    )
                    
                    if response.status_code != 200:
                        error_msg = f"Dify workflow returned non-200 status code: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)
                    
                    response_data = response.json()
                    logger.info(f"Dify workflow response received for {company_name} (sync mode)")
                    
                    # Extract the actual workflow output
                    if "data" in response_data and "outputs" in response_data["data"]:
                        outputs = response_data["data"]["outputs"]
                        
                        # Parse the result if it's a string
                        if "result" in outputs and isinstance(outputs["result"], str):
                            import json
                            try:
                                parsed_result = json.loads(outputs["result"])
                                logger.info(f"Successfully parsed Dify product data for {company_name}")
                                return {"products": parsed_result}
                            except json.JSONDecodeError:
                                logger.warning(f"Could not parse result as JSON, returning as-is")
                                return {"products": outputs}
                        else:
                            return {"products": outputs}
                    else:
                        logger.warning(f"Unexpected Dify response structure: {response_data}")
                        return {"products": response_data}
                    
        except httpx.TimeoutException as e:
            if async_mode:
                # In async mode, timeout might mean Dify accepted but is still processing
                logger.warning(f"Dify request timed out, but workflow may still be running (will callback)")
                return {"status": "timeout", "task_id": task_id, "message": "Request timeout but workflow may be running"}
            else:
                error_msg = f"Dify workflow request timed out"
                logger.error(error_msg)
                raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred with Dify: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error triggering Dify workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e