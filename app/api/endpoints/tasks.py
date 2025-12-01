from typing import List

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi_keycloak import OIDCUser

from app.core.dependencies import get_company_service
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization import get_user_organization, OrganizationContext
from app.core.security import verify_company_organization_access
from app.schemas.task import TaskResponse, TaskTokenUpdate
from app.services.company import CompanyService
from app.services.task_service import TaskService

logger = get_logger(__name__)

router = APIRouter(
    prefix="/tasks",
    tags=["tasks"]
)

# COMMENTED OUT - Tasks are now automatically queued when creating companies
# @router.post("/", response_model=TaskResponse)
# async def create_task(
#     request: Request,
#     task_data: TaskCreate,
#     service: CompanyService = Depends(get_company_service),
#     current_user: TokenData = Depends(get_current_user)
# ):
#     """Create a new task for a company (only if user owns the company)"""
#     try:
#         # Check if company exists and user owns it
#         company = service.get_company(task_data.company_id)
#         verify_company_ownership(company, current_user)
#         
#         # Check if task type is valid
#         try:
#             TaskType(task_data.type)
#         except ValueError:
#             raise HTTPException(
#                 status_code=status.HTTP_400_BAD_REQUEST,
#                 detail=f"Invalid task type: {task_data.type}. Valid types are: {', '.join(t.value for t in TaskType)}"
#             )
#         
#         # Create and start the task
#         task = await service.create_and_start_task(task_data.company_id, task_data.type)
#         return task
#     except ValidationError as e:
#         # Get request body for logging
#         body = await request.json()
#         
#         # Log detailed validation errors
#         logger.error("Validation error in task creation:")
#         logger.error(f"Request body: {body}")
#         logger.error(f"Validation errors: {e.errors()}")
#         
#         return JSONResponse(
#             status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
#             content={
#                 "detail": "Validation error",
#                 "errors": e.errors(),
#                 "body": body
#             }
#         )
#     except Exception as e:
#         # Log unexpected errors
#         logger.error(f"Unexpected error in task creation: {str(e)}", exc_info=True)
#         raise HTTPException(
#             status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
#             detail=f"An unexpected error occurred: {str(e)}"
#         )

@router.get("/company/{company_id}", response_model=List[TaskResponse])
async def get_company_tasks(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Get all tasks for a company with automatic stale task cleanup.

    Performs lazy cleanup of tasks stuck in RUNNING state for more than
    the configured timeout (default 5 minutes) before returning task list.

    Requires authentication.
    """
    company = service.get_company(company_id)
    verify_company_organization_access(company, org_context)

    # Lazy cleanup of stale tasks before returning
    task_service = TaskService(service.db)
    cleaned_count = task_service.cleanup_stale_tasks_for_company(company_id)

    if cleaned_count > 0:
        # Refresh company to get updated task statuses
        service.db.refresh(company)

    return company.tasks

@router.post("/{task_id}/restart", response_model=TaskResponse)
async def restart_task(
    task_id: int,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Restart a specific task (if user has access to the company's organization).

    Requires authentication.
    """
    # Get companies for the user's organization
    user_companies = service.get_all_companies(organization_id=org_context.organization_id)

    task = None
    for company in user_companies:
        for company_task in company.tasks:
            if company_task.id == task_id:
                task = company_task
                break
        if task:
            break

    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Task with ID {task_id} not found or you don't have permission to access it"
        )

    # Restart the task
    restarted_task = await service.restart_task(task_id)
    return restarted_task

@router.patch("/{task_id}/tokens", response_model=TaskResponse)
async def update_task_tokens(
    task_id: int,
    token_data: TaskTokenUpdate,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization)
):
    """Update token usage information for a task (used by Dify workflows).

    Requires authentication.
    """
    # First, find the task and verify ownership
    user_companies = service.get_all_companies(organization_id=org_context.organization_id)

    task = None
    for company in user_companies:
        for company_task in company.tasks:
            if company_task.id == task_id:
                task = company_task
                break
        if task:
            break

    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Task with ID {task_id} not found or you don't have permission to access it"
        )

    # Update token information
    updated_task = service.update_task_tokens(task_id, token_data)
    return updated_task 