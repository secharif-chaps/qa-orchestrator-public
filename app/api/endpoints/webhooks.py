import logging
from fastapi import APIRouter, HTTPException, status, Request
from pydantic import BaseModel, Field
from typing import Dict, Any, Optional
from datetime import datetime
import json

from app.services.company import CompanyService
from app.core.dependencies import get_company_service
from app.models.task import TaskStatus
from app.core.config import settings
from app.schemas.task import TaskTokenUpdate
from app.core.concurrency import DifyConcurrencyManager
from fastapi import Depends

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/webhooks",
    tags=["webhooks"]
)

class TaskCallbackPayload(BaseModel):
    task_id: int
    company_id: int
    task_type: str
    status: str = Field(..., description="succeeded or failed")
    data: Optional[Dict[str, Any]] = Field(None, description="Task results data")
    error: Optional[str] = Field(None, description="Error message if failed")

@router.post("/tasks/{task_id}/callback")
async def task_callback(
    task_id: int,
    payload: TaskCallbackPayload,
    service: CompanyService = Depends(get_company_service)
):
    """
    Webhook endpoint for workflow services to call when a task completes
    """
    logger.info(f"🔄 Task callback received - Task ID: {task_id}, Status: {payload.status}")
    
    try:
        # Validate that task_id in URL matches payload
        if task_id != payload.task_id:
            logger.error(f"Task ID mismatch - URL: {task_id}, Payload: {payload.task_id}")
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Task ID in URL doesn't match payload"
            )
        
        # Get the company and find the task
        company = service.get_company(payload.company_id)
        if not company:
            logger.error(f"Company not found - ID: {payload.company_id}")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Company with ID {payload.company_id} not found"
            )
        
        # Find the specific task
        task = next((t for t in company.tasks if t.id == task_id), None)
        if not task:
            logger.error(f"Task not found - ID: {task_id} in company {payload.company_id}")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Task with ID {task_id} not found"
            )
        
        # Check if task is already completed
        if task.status in [TaskStatus.SUCCEEDED, TaskStatus.ERROR]:
            logger.warning(f"Task {task_id} already completed with status: {task.status}")
            return {"message": "Task already completed", "current_status": task.status.value}
        
        # Update task based on callback status
        if payload.status == "succeeded":
            task.status = TaskStatus.SUCCEEDED
            task.error = None
            
            # Update company data if provided
            if payload.data:
                logger.info(f"Updating company data for task type: {payload.task_type}")
                service._update_company_data(company, payload.task_type, payload.data)
            
            logger.info(f"✅ Task {task_id} completed successfully")
            
        elif payload.status == "failed":
            task.status = TaskStatus.ERROR
            task.error = payload.error or "Task failed without specific error message"
            
            logger.error(f"❌ Task {task_id} failed: {task.error}")
            
        else:
            logger.error(f"Invalid status received: {payload.status}")
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Invalid status: {payload.status}. Must be 'succeeded' or 'failed'"
            )
        
        # Update the task's updated_at timestamp
        task.updated_at = datetime.utcnow()
        
        # Save changes to database
        service.db.commit()
        service.db.refresh(task)
        
        # Log concurrency status when workflow completes
        if payload.status in ["succeeded", "failed"]:
            concurrency_manager = DifyConcurrencyManager(service.db)
            running_count = concurrency_manager.get_running_count()
            logger.info(f"🎉 Task {task_id} callback processed successfully - Running workflows: {running_count}/{concurrency_manager.max_concurrent}")
        
        logger.info(f"🎉 Task {task_id} callback processed successfully")
        
        return {
            "message": "Task callback processed successfully",
            "task_id": task_id,
            "new_status": task.status.value
        }
        
    except HTTPException:
        # Re-raise HTTP exceptions (they're already properly formatted)
        raise
    except Exception as e:
        logger.error(f"💥 Unexpected error processing task callback {task_id}: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error processing task callback"
        )

@router.post("/dify/tasks/{task_id}/callback")
async def dify_task_callback(
    task_id: int,
    request: Request,
    service: CompanyService = Depends(get_company_service)
):
    """
    Flexible webhook endpoint for Dify callbacks
    Handles various response formats from Dify workflows
    """
    logger.info(f"🔄 Dify callback received for Task ID: {task_id}")
    
    try:
        # Get raw body for logging
        body = await request.json()
        logger.info(f"Dify callback raw payload: {json.dumps(body, indent=2)[:500]}")  # Log first 500 chars
        
        # Extract task metadata (might be in different places depending on Dify configuration)
        company_id = None
        task_type = "products"  # Default to products, but will be overridden if found in payload
        
        # Try to extract company_id from various possible locations
        if "callback_payload" in body:
            company_id = body["callback_payload"].get("company_id")
            task_type = body["callback_payload"].get("task_type", "products")
        elif "inputs" in body and "callback_payload" in body["inputs"]:
            company_id = body["inputs"]["callback_payload"].get("company_id")
            task_type = body["inputs"]["callback_payload"].get("task_type", "products")
        elif "company_id" in body:
            company_id = body["company_id"]
        
        # If no company_id, try to find it from the task
        if not company_id:
            # Get all companies and find the task
            companies = service.get_all_companies()
            for company in companies:
                task = next((t for t in company.tasks if t.id == task_id), None)
                if task:
                    company_id = company.id
                    break
        
        if not company_id:
            logger.error(f"Could not determine company_id for task {task_id}")
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Could not determine company_id from callback"
            )
        
        # Get the company and task
        company = service.get_company(company_id)
        if not company:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Company with ID {company_id} not found"
            )
        
        task = next((t for t in company.tasks if t.id == task_id), None)
        if not task:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Task with ID {task_id} not found"
            )
        
        # Check if task is already completed
        if task.status in [TaskStatus.SUCCEEDED, TaskStatus.ERROR]:
            logger.warning(f"Task {task_id} already completed with status: {task.status}")
            return {"message": "Task already completed", "current_status": task.status.value}
        
        # Determine success/failure and extract data
        success = True
        error_msg = None
        task_data = None
        
        # Check for error indicators
        if "error" in body and body["error"]:
            success = False
            error_msg = str(body["error"])
        elif "status" in body and body["status"] in ["failed", "error"]:
            success = False
            error_msg = body.get("message", "Task failed without specific error")
        
        # Extract the actual result data (could be in various places)
        if success:
            # Determine the key to use based on actual task type from database
            data_key = task.type.value if task.type.value in ["products", "timeline", "profile", "digital", "jobs", "csr", "press", "team"] else "products"
            
            # Try different possible locations for the result
            if "result" in body:
                # Direct result
                if isinstance(body["result"], str):
                    try:
                        task_data = {data_key: json.loads(body["result"])}
                    except json.JSONDecodeError:
                        task_data = {data_key: body["result"]}
                else:
                    task_data = {data_key: body["result"]}
            elif "data" in body:
                # Result in data field
                if "outputs" in body["data"]:
                    outputs = body["data"]["outputs"]
                    if "result" in outputs:
                        if isinstance(outputs["result"], str):
                            try:
                                task_data = {data_key: json.loads(outputs["result"])}
                            except json.JSONDecodeError:
                                task_data = {data_key: outputs["result"]}
                        else:
                            task_data = {data_key: outputs["result"]}
                    else:
                        task_data = {data_key: outputs}
                else:
                    task_data = {data_key: body["data"]}
            elif "outputs" in body:
                # Direct outputs
                task_data = {data_key: body["outputs"]}
            elif data_key in body:
                # Data is sent directly with the field name as key (e.g., {"timeline": {...}})
                task_data = {data_key: body[data_key]}
            else:
                # Use entire body as result if nothing else matches
                logger.warning(f"Could not find standard result location, using entire body")
                task_data = {data_key: body}
        
        # Update task and company based on result
        if success:
            task.status = TaskStatus.SUCCEEDED
            task.error = None

            if task_data:
                logger.info(f"Updating company data for task type: {task.type.value}")
                service._update_company_data(company, task.type.value, task_data)

            logger.info(f"✅ Task {task_id} completed successfully via Dify callback")

            # NEW: If this was a prerequisite task, trigger dependent tasks
            if task.is_prerequisite:
                from app.services.task_dependency_service import TaskDependencyService
                from app.models.workflow_config import WorkflowConfig
                from app.workers.dify_tasks import execute_dify_workflow

                logger.info(f"🔓 Task {task_id} is a prerequisite - checking for dependent tasks")
                dependency_service = TaskDependencyService(service.db)
                unblocked_tasks = dependency_service.unblock_dependent_tasks(task_id)

                if unblocked_tasks:
                    logger.info(f"🚀 Triggering {len(unblocked_tasks)} unblocked tasks")

                    # Queue all unblocked tasks
                    for unblocked_task in unblocked_tasks:
                        workflow_config = service.db.query(WorkflowConfig).filter(
                            WorkflowConfig.task_type == unblocked_task.type.value
                        ).first()

                        if not workflow_config or not workflow_config.workflow_id:
                            logger.error(f"No workflow config for {unblocked_task.type.value}")
                            unblocked_task.status = TaskStatus.ERROR
                            unblocked_task.error = "No workflow configuration found"
                            service.db.commit()
                            continue

                        logger.info(f"🚀 Queueing task {unblocked_task.id} ({unblocked_task.type.value})")
                        execute_dify_workflow.delay(
                            task_id=unblocked_task.id,
                            company_id=company.id,
                            task_type=unblocked_task.type.value,
                            workflow_id=workflow_config.workflow_id,
                            api_key=workflow_config.api_key,
                            llm=workflow_config.llm
                        )
                else:
                    logger.info(f"ℹ️  No dependent tasks to unblock for task {task_id}")
        else:
            task.status = TaskStatus.ERROR
            task.error = error_msg or "Task failed without specific error message"
            logger.error(f"❌ Task {task_id} failed via Dify callback: {task.error}")

            # NEW: If prerequisite task failed, mark dependent tasks as error
            if task.is_prerequisite:
                from app.services.task_dependency_service import TaskDependencyService

                logger.error(f"🚫 Prerequisite task {task_id} failed - marking dependent tasks as error")
                dependency_service = TaskDependencyService(service.db)
                failed_tasks = dependency_service.mark_dependents_as_failed(task_id, task.error)

                if failed_tasks:
                    logger.error(f"🚫 Marked {len(failed_tasks)} dependent tasks as failed")

        # Update timestamp and save
        task.updated_at = datetime.utcnow()
        service.db.commit()
        service.db.refresh(task)
        
        # Log concurrency status when workflow completes
        concurrency_manager = DifyConcurrencyManager(service.db)
        running_count = concurrency_manager.get_running_count()
        logger.info(f"🎉 Dify callback for task {task_id} processed successfully - Running workflows: {running_count}/{concurrency_manager.max_concurrent}")
        
        logger.info(f"🎉 Dify callback for task {task_id} processed successfully")
        
        return {
            "message": "Dify callback processed successfully",
            "task_id": task_id,
            "new_status": task.status.value
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"💥 Unexpected error processing Dify callback for task {task_id}: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error processing Dify callback"
        )

@router.patch("/dify/tasks/{task_id}/tokens")
async def dify_task_tokens(
    task_id: int,
    token_data: TaskTokenUpdate,
    service: CompanyService = Depends(get_company_service)
):
    """
    Update token usage information for a task (called by Dify workflows)
    No authentication required as this is a webhook endpoint
    """
    logger.info(f"📊 Token update received for Task ID: {task_id}")
    logger.info(f"Tokens - Input: {token_data.input_tokens}, Output: {token_data.output_tokens}, Cost: {token_data.total_cost}")
    
    try:
        # Find the task across all companies
        companies = service.get_all_companies()
        task = None
        company_id = None
        
        for company in companies:
            task = next((t for t in company.tasks if t.id == task_id), None)
            if task:
                company_id = company.id
                break
        
        if not task:
            logger.error(f"Task {task_id} not found in any company")
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Task with ID {task_id} not found"
            )
        
        # Update token information using the service
        updated_task = service.update_task_tokens(task_id, token_data)
        
        logger.info(f"✅ Token information updated for task {task_id}")
        
        return {
            "message": "Token information updated successfully",
            "task_id": task_id,
            "input_tokens": updated_task.input_tokens,
            "output_tokens": updated_task.output_tokens,
            "total_cost": updated_task.total_cost
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"💥 Error updating tokens for task {task_id}: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error updating token information"
        )

@router.get("/debug/dify-config")
async def debug_dify_config():
    """
    Debug endpoint to check Dify configuration values
    """
    return {
        "dify_url": settings.DIFY_URL,
        "dify_api_key": settings.DIFY_API_KEY[:10] + "..." if settings.DIFY_API_KEY else "None",
        "message": "Configuration loaded successfully",
        "note": "Workflow IDs are now managed in the database via workflow_configs table"
    }