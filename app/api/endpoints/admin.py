"""Admin endpoints requiring admin role.

This module contains all admin-only endpoints that require specific admin roles.
Uses fastapi-keycloak for automatic role-based access control via dependency injection.
"""

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session

from app.core.keycloak import idp
from app.database import get_db
from app.models.task import Task, TaskStatus
from app.services.workflow_config import (
    WorkflowConfigResponse,
    WorkflowConfigService,
    WorkflowConfigUpdate,
)

router = APIRouter(
    prefix="/admin",
    tags=["admin"]
)


# Admin company routes removed - not used in frontend
# Companies are managed via /companies endpoints with proper permission checks

# Module management endpoints moved to /organizations/{id}/modules (no /admin/ prefix)
# See app/api/endpoints/modules.py


# Workflow Configuration Endpoints

@router.get("/workflows", response_model=list[WorkflowConfigResponse])
async def get_all_workflow_configs(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"]))
):
    """Get all workflow configurations with obfuscated API keys (workflow admin only).

    Requires admin.workflows role for access.
    """
    
    service = WorkflowConfigService(db)
    return service.get_all_configs()


@router.put("/workflows/{task_type}", response_model=WorkflowConfigResponse)
async def update_workflow_config(
    task_type: str,
    update_data: WorkflowConfigUpdate,
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.workflows"]))
):
    """Update workflow configuration (workflow admin only).

    Requires admin.workflows role for access.
    """
    
    service = WorkflowConfigService(db)
    updated_config = service.update_config(task_type, update_data)
    
    if not updated_config:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Workflow configuration for task type '{task_type}' not found"
        )
    
    # Return response with obfuscated API key
    return WorkflowConfigResponse(
        task_type=updated_config.task_type,
        title=updated_config.title,
        api_key_obfuscated=service.obfuscate_api_key(updated_config.api_key),
        has_api_key=bool(updated_config.api_key),
        llm=updated_config.llm
    )


# User organization assignment moved to /users/{user_id}/organization
# See app/api/endpoints/users.py


# Task Management Endpoints

@router.post("/tasks/fail-stuck")
async def fail_stuck_tasks(
    db: Session = Depends(get_db),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.organizations"]))
):
    """Fail all pending and running tasks and clear the task queue.

    This is used to unlock the system when tasks get stuck.
    Requires admin.organizations permission.
    """
    
    # Get all pending and running tasks
    stuck_tasks = db.query(Task).filter(
        Task.status.in_([TaskStatus.PENDING, TaskStatus.RUNNING])
    ).all()
    
    failed_count = 0
    for task in stuck_tasks:
        task.status = TaskStatus.ERROR
        task.error = "Task failed by admin to unlock stuck queue"
        task.updated_at = datetime.utcnow()
        failed_count += 1
    
    # Commit the database changes
    db.commit()
    
    # Clear the Celery queue
    # Purge all messages from the dify_workflows queue
    try:
        # Get the queue with the same parameters as defined in celery_app
        from kombu import Connection, Queue as KombuQueue
        from app.core.celery_app import RABBITMQ_URL
        
        with Connection(RABBITMQ_URL) as conn:
            channel = conn.channel()
            # Declare the queue with the same arguments as in celery_app
            queue = KombuQueue(
                'dify_workflows', 
                channel=channel, 
                durable=True,
                queue_arguments={'x-max-priority': 10}  # Match the celery config
            )
            queue.declare()
            # Purge the queue
            purged_count = queue.purge()
            
        queue_cleared = True
        queue_message = f"Purged {purged_count} messages from queue"
    except Exception as e:
        queue_cleared = False
        queue_message = f"Failed to clear queue: {str(e)}"
    
    return {
        "success": True,
        "tasks_failed": failed_count,
        "queue_cleared": queue_cleared,
        "queue_status": queue_message,
        "message": f"Failed {failed_count} stuck tasks and {'successfully cleared' if queue_cleared else 'attempted to clear'} the queue"
    }