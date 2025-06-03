from typing import List, Dict, Any, Optional
from app.domain.entities.company import Company
from app.domain.entities.task import Task, TaskType, TaskStatus
from app.domain.repositories.company_repository import CompanyRepository
from app.infrastructure.n8n.client import N8nClient

class CompanyService:
    """Service for company-related business logic"""
    
    def __init__(self, repository: CompanyRepository, n8n_client: N8nClient):
        self.repository = repository
        self.n8n_client = n8n_client
        
    def get_company(self, company_id: int) -> Optional[Company]:
        """Get a company by ID"""
        return self.repository.get_by_id(company_id)
    
    def get_company_by_name(self, name: str) -> Optional[Company]:
        """Get a company by name"""
        return self.repository.get_by_name(name)
    
    def get_all_companies(self) -> List[Company]:
        """Get all companies"""
        return self.repository.get_all()
    
    def create_company(self, name: str, website: str) -> Company:
        """Create a new company"""
        # Check if company already exists
        existing = self.repository.get_by_name(name)
        if existing:
            return existing
            
        return self.repository.create(name, website)
    
    def update_company(self, company: Company) -> Company:
        """Update an existing company"""
        return self.repository.update(company)
    
    def delete_company(self, company_id: int) -> bool:
        """Delete a company"""
        return self.repository.delete(company_id)

    async def create_and_start_task(self, company_id: int, task_type: str) -> Task:
        """
        Create a new task for a company and start it immediately
        
        Args:
            company_id: ID of the company
            task_type: Type of task to create
            
        Returns:
            The created and started task
        """
        # Get the company
        company = self.repository.get_by_id(company_id)
        if not company:
            raise ValueError(f"Company with ID {company_id} not found")
        
        # Convert task_type to TaskType enum
        task_type = TaskType(task_type)
        
        # Find existing task of this type
        existing_task = next((t for t in company.tasks if t.type == task_type), None)
        
        if existing_task:
            # If task exists and is not running, restart it
            if existing_task.status != TaskStatus.RUNNING:
                existing_task.status = TaskStatus.PENDING
                self.repository.update(company)
                await self._execute_task(existing_task, company)
                return existing_task
            else:
                # Task is already running
                return existing_task
        else:
            # Create new task
            task = Task(company_id=company_id, type=task_type)
            company.tasks.append(task)
            self.repository.update(company)
            
            # Start the task
            await self._execute_task(task, company)
            return task

    async def restart_task(self, task_id: int) -> Optional[Task]:
        """
        Restart a specific task
        
        Args:
            task_id: ID of the task to restart
            
        Returns:
            The restarted task if found, None otherwise
        """
        # Find the task
        for company in self.repository.get_all():
            task = next((t for t in company.tasks if t.id == task_id), None)
            if task:
                # Reset task status
                task.status = TaskStatus.PENDING
                task.error = None
                self.repository.update(company)
                
                # Start the task
                await self._execute_task(task, company)
                return task
        
        return None

    async def _execute_task(self, task: Task, company: Company) -> None:
        """
        Execute a task by triggering the appropriate n8n workflow
        
        Args:
            task: The task to execute
            company: The company the task belongs to
        """
        try:
            # Update task status to running
            task.status = TaskStatus.RUNNING
            self.repository.update(company)
            
            # Trigger the n8n workflow
            result = await self.n8n_client.trigger_workflow(
                company.name,
                company.website,
                task.type.value
            )
            
            # Process the result
            if isinstance(result, list) and len(result) > 0:
                output_data = result[0].get('output', {})
                if task.type == TaskType.timeline and isinstance(output_data, dict):
                    data = output_data.get('timeline', {})
                else:
                    data = output_data
            else:
                data = result
            
            # Update company data
            company.update_from_n8n(task.type.value, data)
            
            # Update task status to succeeded
            task.status = TaskStatus.SUCCEEDED
            self.repository.update(company)
            
        except Exception as e:
            # Update task status to error
            task.status = TaskStatus.ERROR
            task.error = str(e)
            self.repository.update(company)
            raise 