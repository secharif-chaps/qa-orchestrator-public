import asyncio
import json
from collections.abc import AsyncGenerator

from fastapi import APIRouter, Depends, HTTPException, Request, status
from fastapi.responses import StreamingResponse
from fastapi_keycloak import OIDCUser

from app.core.config import settings
from app.core.dependencies import get_company_service
from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.core.organization_context import OrganizationContext, get_user_organization
from app.core.security import verify_company_organization_access
from app.schemas.task import TaskResponse
from app.services.company import CompanyService
from app.services.task_events import task_event_manager
from app.services.task_service import TaskService

logger = get_logger(__name__)

router = APIRouter(prefix="/tasks", tags=["tasks"])

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


@router.get(
    "/company/{company_id}",
    response_model=list[TaskResponse],
    openapi_extra={"x-permissions": []},
    summary="List tasks for a company",
    description=(
        "Return every task associated with the given company. "
        "Stale tasks (stuck in RUNNING state beyond the configured timeout) "
        "are automatically cleaned up before the list is returned."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "Company does not belong to the user's organization"},
        404: {"description": "Company not found"},
    },
)
async def get_company_tasks(
    company_id: int,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
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


@router.post(
    "/{task_id}/restart",
    response_model=TaskResponse,
    openapi_extra={"x-permissions": []},
    summary="Restart a task",
    description=(
        "Re-queue a failed or completed task for re-execution. "
        "The user must have access to the company that owns the task through their organization."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "Company does not belong to the user's organization"},
        404: {"description": "Task not found"},
    },
)
async def restart_task(
    task_id: int,
    service: CompanyService = Depends(get_company_service),
    user: OIDCUser = Depends(idp.get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
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
            detail=f"Task with ID {task_id} not found or you don't have permission to access it",
        )

    # Restart the task (fire-and-forget via background worker)
    restarted_task = service.restart_task(task_id)
    return restarted_task


@router.get(
    "/events/stream",
    openapi_extra={"x-permissions": []},
    summary="Stream task events (SSE)",
    description=(
        "Open a Server-Sent Events (SSE) connection that pushes real-time task "
        "status updates for the authenticated user. Events include individual "
        "`task_update` events when a task status changes and an "
        "`all_tasks_completed` event when every task for a company finishes. "
        "A keepalive ping is sent every 30 seconds."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
    },
)
async def task_events_stream(request: Request, user: OIDCUser = Depends(idp.get_current_user())):
    """SSE endpoint for real-time task status updates.

    Streams task status updates for all companies the user has access to.
    Uses Server-Sent Events (SSE) format for efficient one-way communication.

    The connection stays open and pushes events when:
    - A task status changes (task_update event)
    - All tasks for a company complete (all_tasks_completed event)

    Keepalive pings are sent every 30 seconds to maintain the connection.

    Requires authentication via Bearer token.

    Returns:
        StreamingResponse with media_type="text/event-stream"
    """
    user_id = user.sub

    logger.info("SSE connection initiated", extra={"user_id": user_id})

    async def event_generator() -> AsyncGenerator[str, None]:
        """Generate SSE events for the connected client."""
        queue = task_event_manager.subscribe(user_id)

        try:
            # Send initial connection confirmation event
            connected_event = {"type": "connected", "data": {"message": "Connected to task events"}}
            yield f"data: {json.dumps(connected_event)}\n\n"

            while True:
                # Check if client disconnected
                if await request.is_disconnected():
                    logger.info("SSE client disconnected", extra={"user_id": user_id})
                    break

                try:
                    # Wait for events with timeout for keepalive
                    event = await asyncio.wait_for(queue.get(), timeout=30.0)
                    yield f"data: {json.dumps(event)}\n\n"

                except TimeoutError:
                    # Send keepalive comment (SSE spec: lines starting with : are comments)
                    yield ": keepalive\n\n"

        except Exception as e:
            logger.error("SSE stream error", extra={"user_id": user_id, "error": str(e)}, exc_info=True)
        finally:
            task_event_manager.unsubscribe(user_id, queue)
            logger.info("SSE connection closed", extra={"user_id": user_id})

    return StreamingResponse(
        event_generator(),
        media_type="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "Connection": "keep-alive",
            "X-Accel-Buffering": "no",  # Disable nginx buffering for SSE
            "Access-Control-Allow-Origin": settings.CORS_ORIGIN,
        },
    )
