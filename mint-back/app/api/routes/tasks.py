from typing import List
import logging
from fastapi import APIRouter, Depends, HTTPException, status, Request
from fastapi.responses import JSONResponse
from pydantic import ValidationError
from sqlalchemy.orm import Session

from app.database import get_db
from app.services.company import CompanyService
from app.services.n8n import N8nClient
from app.schemas.task import TaskCreate, TaskResponse
from app.models.task import TaskType

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/tasks",
    tags=["tasks"]
)

def get_company_service(db: Session = Depends(get_db)) -> CompanyService:
    return CompanyService(db, N8nClient())

@router.post("/", response_model=TaskResponse)
async def create_task(
    request: Request,
    task_data: TaskCreate,
    service: CompanyService = Depends(get_company_service)
):
    """Create a new task for a company"""
    try:
        company = service.get_company(task_data.company_id)
        if not company:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Company with ID {task_data.company_id} not found"
            )
        
        try:
            TaskType(task_data.type)
        except ValueError:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"Invalid task type: {task_data.type}. Valid types are: {', '.join(t.value for t in TaskType)}"
            )
        
        task = await service.create_and_start_task(task_data.company_id, task_data.type)
        return task
    except ValidationError as e:
        body = await request.json()
        logger.error("Validation error in task creation:")
        logger.error(f"Request body: {body}")
        logger.error(f"Validation errors: {e.errors()}")
        
        return JSONResponse(
            status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
            content={
                "detail": "Validation error",
                "errors": e.errors(),
                "body": body
            }
        )
    except Exception as e:
        logger.error(f"Unexpected error in task creation: {str(e)}", exc_info=True)
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"An unexpected error occurred: {str(e)}"
        )

@router.get("/company/{company_id}", response_model=List[TaskResponse])
async def get_company_tasks(
    company_id: int,
    service: CompanyService = Depends(get_company_service)
):
    """Get all tasks for a company"""
    company = service.get_company(company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {company_id} not found"
        )
    return company.tasks

@router.post("/{task_id}/restart", response_model=TaskResponse)
async def restart_task(
    task_id: int,
    service: CompanyService = Depends(get_company_service)
):
    """Restart a specific task"""
    task = await service.restart_task(task_id)
    if not task:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Task with ID {task_id} not found"
        )
    return task 