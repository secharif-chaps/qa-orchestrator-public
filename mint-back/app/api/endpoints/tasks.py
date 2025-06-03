from typing import List
from fastapi import APIRouter, Depends, HTTPException, status

from app.domain.services.company_service import CompanyService
from app.core.dependencies import get_company_service
from app.domain.entities.schema import TaskCreate, TaskResponse
from app.domain.entities.task import TaskType

router = APIRouter(
    prefix="/tasks",
    tags=["tasks"]
)

@router.post("/", response_model=TaskResponse)
async def create_task(
    task_data: TaskCreate,
    service: CompanyService = Depends(get_company_service)
):
    """Create a new task for a company"""
    # Check if company exists
    company = service.get_company(task_data.company_id)
    if not company:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Company with ID {task_data.company_id} not found"
        )
    
    # Check if task type is valid
    try:
        TaskType(task_data.type)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid task type: {task_data.type}. Valid types are: {', '.join(t.value for t in TaskType)}"
        )
    
    # Create and start the task
    task = await service.create_and_start_task(task_data.company_id, task_data.type)
    return task

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