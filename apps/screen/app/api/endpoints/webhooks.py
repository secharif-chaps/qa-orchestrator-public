import asyncio
import json
import logging
from datetime import datetime
from typing import Any, Optional

from fastapi import APIRouter, Depends, HTTPException, Request, status
from pydantic import BaseModel, Field

from app.core.concurrency import DifyConcurrencyManager
from app.core.config import settings
from app.core.dependencies import get_company_service
from app.models.folder import FolderItem
from app.models.task import Task, TaskStatus
from app.schemas.task import TaskTokenUpdate
from app.services.company import CompanyService
from app.services.dify_error_handler import DifyErrorHandler
from app.services.task_events import task_event_manager

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
    data: Optional[dict[str, Any]] = Field(None, description="Task results data")
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
        
        # Try to extract company_id from various possible locations
        if "callback_payload" in body:
            company_id = body["callback_payload"].get("company_id")
            body["callback_payload"].get("task_type", "products")
        elif "inputs" in body and "callback_payload" in body["inputs"]:
            company_id = body["inputs"]["callback_payload"].get("company_id")
            body["inputs"]["callback_payload"].get("task_type", "products")
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

        # Extract language preference
        # Priority: 1) Query param, 2) Accept-Language header, 3) Default (en)
        language = "en"  # Default to English

        # Check for language query parameter
        if hasattr(request, "query_params") and "lang" in request.query_params:
            language = request.query_params["lang"]
        # Check Accept-Language header
        elif "accept-language" in request.headers:
            # Parse Accept-Language header (e.g., "fr-FR,fr;q=0.9,en;q=0.8")
            accept_lang = request.headers["accept-language"]
            # Take first language code (simple parsing)
            if accept_lang:
                first_lang = accept_lang.split(",")[0].split(";")[0].split("-")[0].strip()
                if first_lang in ["en", "fr"]:
                    language = first_lang

        # Initialize error handler with language
        error_handler = DifyErrorHandler(language=language)

        # Check if this is an error callback
        is_error = error_handler.is_error_callback(body)

        # Determine success/failure and extract data
        success = not is_error
        error_msg = None
        error_result = None
        task_data = None

        # Process error if present
        if is_error:
            success = False
            # Parse and categorize error
            error_result = error_handler.parse_error_callback(
                callback_body=body,
                task_id=task_id,
                task_type=task.type.value
            )
            # Get user-friendly error message
            error_msg = error_handler.get_user_error_message(
                error_result,
                include_technical=False
            )

            logger.error(
                f"❌ Error callback processed for task {task_id}",
                extra={
                    "task_id": task_id,
                    "task_type": task.type.value,
                    "error_count": len(error_result.errors),
                    "primary_error_type": error_result.primary_error.categorized_type.value,
                    "has_whitelisted": error_result.has_whitelisted_error,
                    "is_recoverable": error_result.primary_error.is_recoverable,
                    "user_message": error_msg[:200],
                }
            )

        # Extract the actual result data - now simplified for string-based responses
        if success:
            # For data_collection task, extract the 4 string fields directly
            if task.type.value == "data_collection":
                logger.info("🔍 DEBUG - Processing data_collection task")
                logger.info(f"🔍 DEBUG - Body keys: {list(body.keys())}")

                # Dify returns the fields directly in the body or in data.outputs
                if "data" in body and "outputs" in body["data"]:
                    logger.info("🔍 DEBUG - Found data.outputs")
                    outputs = body["data"]["outputs"]
                elif "outputs" in body:
                    logger.info("🔍 DEBUG - Found outputs")
                    outputs = body["outputs"]
                elif "knowledge" in body:
                    logger.info("🔍 DEBUG - Found knowledge field")
                    outputs = body["knowledge"]
                else:
                    logger.info("🔍 DEBUG - Using entire body as outputs")
                    outputs = body

                logger.info(f"🔍 DEBUG - Outputs keys: {list(outputs.keys()) if isinstance(outputs, dict) else 'not a dict'}")
                logger.info(f"🔍 DEBUG - Outputs type: {type(outputs)}")

                # Extract the 4 string fields
                task_data = {
                    "mistral": outputs.get("mistral", ""),
                    "gpt": outputs.get("gpt", ""),
                    "wikipedia": outputs.get("wikipedia", ""),
                    "scraped": outputs.get("scraped", "")
                }

                logger.info(f"🔍 DEBUG - Extracted task_data keys: {list(task_data.keys())}")
                logger.info(f"🔍 DEBUG - mistral length: {len(task_data['mistral'])}")
                logger.info(f"🔍 DEBUG - gpt length: {len(task_data['gpt'])}")
                logger.info(f"🔍 DEBUG - wikipedia length: {len(task_data['wikipedia'])}")
                logger.info(f"🔍 DEBUG - scraped length: {len(task_data['scraped'])}")
            else:
                # For other tasks (profile, digital, etc.), extract the single string field
                task_type_key = task.type.value

                # Try different possible locations for the result
                if "data" in body and "outputs" in body["data"]:
                    outputs = body["data"]["outputs"]
                elif "outputs" in body:
                    outputs = body["outputs"]
                else:
                    outputs = body

                # Extract the value for this task type
                # Handle two cases:
                # 1. Dify sends wrapped data: {"press": {...}}
                # 2. Dify sends unwrapped data: {"insights": "...", "articles": [...], ...}
                if task_type_key in outputs:
                    # Case 1: Data is wrapped in a key matching the task type
                    task_data = {task_type_key: outputs[task_type_key]}
                else:
                    # Case 2: The entire outputs object IS the data for this task
                    task_data = {task_type_key: outputs}
        
        # Update task and company based on result
        if success:
            task.status = TaskStatus.SUCCEEDED
            task.error = None

            if task_data:
                logger.info(f"Updating company data for task type: {task.type.value}")
                # Pass the task_data directly - it's already in the correct format
                # For data_collection: {"mistral": "", "gpt": "", "wikipedia": "", "scraped": ""}
                # For other tasks: {task_type_key: outputs}
                service._update_company_data(company, task.type.value, task_data)

            logger.info(f"✅ Task {task_id} completed successfully via Dify callback")

            # NEW: If this was a prerequisite task, trigger dependent tasks
            if task.is_prerequisite:
                from app.models.workflow_config import WorkflowConfig
                from app.services.task_dependency_service import TaskDependencyService
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

                        if not workflow_config or not workflow_config.api_key:
                            logger.error(f"No workflow config or API key for {unblocked_task.type.value}")
                            unblocked_task.status = TaskStatus.ERROR
                            unblocked_task.error = "No workflow configuration or API key found"
                            service.db.commit()
                            continue

                        # Prepare callback URLs in FastAPI context before queueing to Celery
                        success_callback, error_callback = service._prepare_task_callbacks(unblocked_task)

                        logger.info(f"🚀 Queueing task {unblocked_task.id} ({unblocked_task.type.value})")
                        execute_dify_workflow.delay(
                            task_id=unblocked_task.id,
                            company_id=company.id,
                            task_type=unblocked_task.type.value,
                            api_key=workflow_config.api_key,
                            success_callback=success_callback,
                            error_callback=error_callback
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

        # === SSE BROADCAST ===
        # Broadcast task update to connected frontend clients
        try:
            # Check if all tasks for this company are now complete
            all_tasks = service.db.query(Task).filter(Task.company_id == company.id).all()
            completed_statuses = [TaskStatus.SUCCEEDED, TaskStatus.ERROR]
            all_completed = all(t.status in completed_statuses for t in all_tasks)

            if all_completed:
                # All tasks done - send completion notification with company name
                success_count = sum(1 for t in all_tasks if t.status == TaskStatus.SUCCEEDED)
                error_count = sum(1 for t in all_tasks if t.status == TaskStatus.ERROR)

                # Get folder_id from FolderItem junction table
                folder_item = service.db.query(FolderItem).filter(
                    FolderItem.item_id == str(company.id),
                    FolderItem.item_type == "company"
                ).first()
                folder_id = str(folder_item.folder_id) if folder_item else None

                asyncio.create_task(
                    task_event_manager.broadcast_all_tasks_completed(
                        user_id=company.owner_id,
                        company_id=company.id,
                        company_name=company.name,
                        folder_id=folder_id,
                        success_count=success_count,
                        error_count=error_count
                    )
                )
                logger.info(
                    f"📢 All tasks completed notification sent for company {company.id}",
                    extra={
                        "company_id": company.id,
                        "success_count": success_count,
                        "error_count": error_count
                    }
                )
            else:
                # Individual task update - for cache invalidation
                asyncio.create_task(
                    task_event_manager.broadcast_task_update(
                        user_id=company.owner_id,
                        company_id=company.id,
                        task_id=task.id,
                        status=task.status.value,
                        task_type=task.type.value,
                        error=task.error
                    )
                )
        except Exception as sse_error:
            # SSE broadcast failure should not fail the webhook
            logger.warning(
                f"SSE broadcast failed for task {task_id}: {str(sse_error)}",
                exc_info=True
            )

        # Build response with error details if applicable
        response = {
            "message": "Dify callback processed successfully",
            "task_id": task_id,
            "new_status": task.status.value
        }

        # Add structured error information for failed tasks
        if not success and error_result:
            response["error_details"] = {
                "user_message": error_result.primary_error.user_message,
                "error_type": error_result.primary_error.categorized_type.value,
                "is_recoverable": error_result.primary_error.is_recoverable,
                "retry_after_seconds": error_result.primary_error.retry_after_seconds,
                "recommended_action": error_result.recommended_action,
                "error_count": len(error_result.errors),
                "has_whitelisted_error": error_result.has_whitelisted_error,
            }

            # Add technical details for debugging (optional, only for dev/admin)
            if logger.level == logging.DEBUG:
                response["error_details"]["technical"] = {
                    "errors": [
                        {
                            "type": e.categorized_type.value,
                            "original_type": e.original_type,
                            "message": e.original_message[:200],
                        }
                        for e in error_result.errors
                    ]
                }

        return response
        
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
        
        for company in companies:
            task = next((t for t in company.tasks if t.id == task_id), None)
            if task:
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