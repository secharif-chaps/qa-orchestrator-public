import httpx
import logging
from typing import Dict, Any, Optional
from sqlalchemy.orm import Session
from app.core.config import settings
from app.services.workflow_config import WorkflowConfigService

logger = logging.getLogger(__name__)

class DifyClient:
    """Client for interacting with Dify workflows"""
    
    def __init__(self, db: Optional[Session] = None):
        self.db = db
        self.base_url = settings.DIFY_URL
        
        # Fallback to config settings if database is not available
        self.fallback_api_key = settings.DIFY_API_KEY
        self.fallback_timeline_api_key = settings.DIFY_TIMELINE_API_KEY
        self.fallback_product_workflow_id = settings.DIFY_PRODUCT_WORKFLOW_ID
        self.fallback_timeline_workflow_id = settings.DIFY_TIMELINE_WORKFLOW_ID
    
    def _get_workflow_config(self, task_type: str) -> tuple[Optional[str], Optional[str]]:
        """Get workflow ID and API key for task type from database or fallback to config"""
        if self.db is None:
            # Fallback to config-based settings
            if task_type == "products":
                return self.fallback_product_workflow_id, self.fallback_api_key
            elif task_type == "timeline":
                return self.fallback_timeline_workflow_id, self.fallback_timeline_api_key
            else:
                return None, None
        
        try:
            service = WorkflowConfigService(self.db)
            config = service.get_config_by_task_type(task_type)
            if config and config.workflow_id and config.api_key:
                return config.workflow_id, config.api_key
            else:
                # Fallback to config if database config is incomplete
                if task_type == "products":
                    return self.fallback_product_workflow_id, self.fallback_api_key
                elif task_type == "timeline":
                    return self.fallback_timeline_workflow_id, self.fallback_timeline_api_key
                else:
                    return None, None
        except Exception as e:
            logger.warning(f"Failed to get workflow config from database, using fallback: {e}")
            if task_type == "products":
                return self.fallback_product_workflow_id, self.fallback_api_key
            elif task_type == "timeline":
                return self.fallback_timeline_workflow_id, self.fallback_timeline_api_key
            else:
                return None, None
    
    async def trigger_workflow(
        self,
        task_type: str,
        company_name: str, 
        website: str, 
        success_callback: str,
        error_callback: str,
        task_id: int,
        company_id: int,
        async_mode: bool = True
    ) -> Dict[str, Any]:
        """
        Generic method to trigger any workflow type
        
        Args:
            task_type: Type of task (products, timeline, etc.)
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
        workflow_id, api_key = self._get_workflow_config(task_type)
        
        if not workflow_id or not api_key:
            error_msg = f"No workflow configuration found for task type: {task_type}"
            logger.error(error_msg)
            raise Exception(error_msg)
        
        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json"
        }
        
        # Prepare the callback payload that Dify should send back
        callback_payload_template = {
            "task_id": task_id,
            "company_id": company_id,
            "task_type": task_type
        }
        
        payload = {
            "inputs": {
                "company": company_name,
                "website": website,
                "callback_webhook": success_callback,
                "callback_payload": callback_payload_template
            },
            "response_mode": "blocking",
            "user": f"company_{company_id}"
        }
        
        logger.info(f"Triggering Dify {task_type} workflow for {company_name} ({website}) - Task ID: {task_id}")
        logger.info(f"Workflow ID: {workflow_id}")
        logger.info(f"Dify Base URL: {self.base_url}")
        logger.info(f"Full URL: {self.base_url}/workflows/{workflow_id}/run")
        logger.info(f"Mode: {'Async (fire-and-forget)' if async_mode else 'Sync (wait for response)'}")
        logger.info(f"Success callback: {success_callback}")
        logger.info(f"Error callback: {error_callback}")
        
        try:
            if async_mode:
                # Fire-and-forget mode: Send request but don't wait for workflow completion
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.base_url}/workflows/{workflow_id}/run",
                        json=payload,
                        headers=headers,
                        timeout=10.0  # Just wait for acknowledgment, not completion
                    )
                    
                    if response.status_code not in [200, 201, 202]:
                        error_msg = f"Dify {task_type} workflow trigger failed with status: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)
                    
                    logger.info(f"✅ Dify {task_type} workflow triggered successfully for task {task_id} (async mode)")
                    return {"status": "triggered", "task_id": task_id, "message": f"{task_type} workflow started, will callback when complete"}
            
            else:
                # Synchronous mode: Wait for the full response (fallback option)
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.base_url}/workflows/{workflow_id}/run",
                        json=payload,
                        headers=headers,
                        timeout=300.0  # 5 minutes timeout for blocking mode
                    )
                    
                    if response.status_code != 200:
                        error_msg = f"Dify {task_type} workflow returned non-200 status code: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)
                    
                    response_data = response.json()
                    logger.info(f"Dify {task_type} workflow response received for {company_name} (sync mode)")
                    
                    # Extract the actual workflow output
                    if "data" in response_data and "outputs" in response_data["data"]:
                        outputs = response_data["data"]["outputs"]
                        
                        # Parse the result if it's a string
                        if "result" in outputs and isinstance(outputs["result"], str):
                            import json
                            try:
                                parsed_result = json.loads(outputs["result"])
                                logger.info(f"Successfully parsed Dify {task_type} data for {company_name}")
                                return {task_type: parsed_result}
                            except json.JSONDecodeError:
                                logger.warning(f"Could not parse {task_type} result as JSON, returning as-is")
                                return {task_type: outputs}
                        else:
                            return {task_type: outputs}
                    else:
                        logger.warning(f"Unexpected Dify {task_type} response structure: {response_data}")
                        return {task_type: response_data}
                    
        except httpx.TimeoutException as e:
            if async_mode:
                # In async mode, timeout might mean Dify accepted but is still processing
                logger.warning(f"Dify {task_type} request timed out, but workflow may still be running (will callback)")
                return {"status": "timeout", "task_id": task_id, "message": f"{task_type} request timeout but workflow may be running"}
            else:
                error_msg = f"Dify {task_type} workflow request timed out"
                logger.error(error_msg)
                raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred with Dify {task_type} workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error triggering Dify {task_type} workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
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
        Trigger the Dify product workflow with callback URLs (backward compatibility)
        """
        return await self.trigger_workflow(
            "products", company_name, website, success_callback, 
            error_callback, task_id, company_id, async_mode
        )

    async def trigger_timeline_workflow(
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
        Trigger the Dify timeline workflow with callback URLs (backward compatibility)
        """
        return await self.trigger_workflow(
            "timeline", company_name, website, success_callback, 
            error_callback, task_id, company_id, async_mode
        )

    async def trigger_profile_workflow(
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
        Trigger the Dify profile workflow with callback URLs (backward compatibility)
        """
        return await self.trigger_workflow(
            "profile", company_name, website, success_callback, 
            error_callback, task_id, company_id, async_mode
        )