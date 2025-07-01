from typing import List, Dict, Any, Optional
import logging
from sqlalchemy.orm import Session
from app.models.company import Company
from app.models.task import Task, TaskType, TaskStatus
from app.schemas.company import CompanyCreate, CompanyUpdate
from app.services.n8n import N8nClient

logger = logging.getLogger(__name__)

class CompanyService:
    def __init__(self, db: Session, n8n_client: N8nClient):
        self.db = db
        self.n8n_client = n8n_client
    
    def get_company(self, company_id: int) -> Optional[Company]:
        company = self.db.query(Company).filter(Company.id == company_id).first()
        if company:
            # Ensure all JSON fields have default values to prevent validation errors
            if company.profile is None:
                company.profile = {}
            if company.digital is None:
                company.digital = {}
            if company.timeline is None:
                company.timeline = {}
            if company.products is None:
                company.products = {}
            if company.jobs is None:
                company.jobs = {}
            if company.csr is None:
                company.csr = {}
            if company.press is None:
                company.press = {}
            if company.team is None:
                company.team = []
        return company
    
    def get_company_by_name(self, name: str) -> Optional[Company]:
        company = self.db.query(Company).filter(Company.name == name).first()
        if company:
            # Ensure all JSON fields have default values to prevent validation errors
            if company.profile is None:
                company.profile = {}
            if company.digital is None:
                company.digital = {}
            if company.timeline is None:
                company.timeline = {}
            if company.products is None:
                company.products = {}
            if company.jobs is None:
                company.jobs = {}
            if company.csr is None:
                company.csr = {}
            if company.press is None:
                company.press = {}
            if company.team is None:
                company.team = []
        return company
    
    def get_all_companies(self) -> List[Company]:
        companies = self.db.query(Company).all()
        # Ensure all JSON fields have default values to prevent validation errors
        for company in companies:
            if company.profile is None:
                company.profile = {}
            if company.digital is None:
                company.digital = {}
            if company.timeline is None:
                company.timeline = {}
            if company.products is None:
                company.products = {}
            if company.jobs is None:
                company.jobs = {}
            if company.csr is None:
                company.csr = {}
            if company.press is None:
                company.press = {}
            if company.team is None:
                company.team = []
        return companies
    
    def create_company(self, name: str, website: str) -> Company:
        company = Company(name=name, website=website)
        self.db.add(company)
        self.db.commit()
        self.db.refresh(company)
        return company
    
    def update_company(self, company: Company) -> Company:
        self.db.commit()
        self.db.refresh(company)
        return company
    
    def delete_company(self, company_id: int) -> bool:
        company = self.get_company(company_id)
        if not company:
            return False
        self.db.delete(company)
        self.db.commit()
        return True

    async def create_and_start_task(self, company_id: int, task_type: str) -> Task:
        company = self.get_company(company_id)
        if not company:
            raise ValueError(f"Company with ID {company_id} not found")
        
        task_type = TaskType(task_type)
        existing_task = next((t for t in company.tasks if t.type == task_type), None)
        
        if existing_task:
            if existing_task.status != TaskStatus.RUNNING:
                existing_task.status = TaskStatus.PENDING
                self.db.commit()
                await self._execute_task(existing_task, company)
                return existing_task
            return existing_task
        
        task = Task(company_id=company_id, type=task_type)
        company.tasks.append(task)
        self.db.commit()
        await self._execute_task(task, company)
        return task

    async def restart_task(self, task_id: int) -> Optional[Task]:
        for company in self.get_all_companies():
            task = next((t for t in company.tasks if t.id == task_id), None)
            if task:
                task.status = TaskStatus.PENDING
                task.error = None
                self.db.commit()
                await self._execute_task(task, company)
                return task
        return None

    async def _execute_task(self, task: Task, company: Company) -> None:
        try:
            task.status = TaskStatus.RUNNING
            self.db.commit()
            
            result = await self.n8n_client.trigger_workflow(
                company.name,
                company.website,
                task.type.value
            )
            
            # Debug logging to understand the n8n response structure
            logger.info(f"Raw n8n response for {task.type.value}: {result}")
            
            # Parse the n8n response to extract the actual data
            data = self._parse_n8n_response(result, task.type.value)
            
            logger.info(f"Parsed data for {task.type.value}: {data}")
            
            self._update_company_data(company, task.type.value, data)
            task.status = TaskStatus.SUCCEEDED
            self.db.commit()
            
        except Exception as e:
            task.status = TaskStatus.ERROR
            task.error = str(e)
            self.db.commit()
            # Log the error for debugging but don't re-raise to avoid crashing the backend
            logger.error(f"Task execution failed for company {company.name} (task {task.type.value}): {str(e)}", exc_info=True)

    def _parse_n8n_response(self, result: Any, task_type: str) -> Dict[str, Any]:
        """
        Parse n8n response and extract the actual data, removing nested wrapper structures.
        
        Expected n8n response structure:
        [{"output": {"timeline": {"insights": "...", "events": [...]}}}]
        
        Should return:
        {"insights": "...", "events": [...]}
        """
        try:
            # Handle empty/null results
            if result is None:
                logger.info(f"N8n returned null result for {task_type}, using empty dict")
                return {}
            
            # Handle empty dict
            if isinstance(result, dict) and not result:
                logger.info(f"N8n returned empty dict for {task_type}")
                return {}
            
            # Handle list response (most common case)
            if isinstance(result, list):
                if len(result) == 0:
                    logger.info(f"N8n returned empty list for {task_type}, using empty dict")
                    return {}
                result_item = result[0]
            else:
                result_item = result
            
            # Handle empty result item
            if not result_item:
                logger.info(f"N8n returned empty result item for {task_type}, using empty dict")
                return {}
            
            # Extract from 'output' wrapper if it exists
            if isinstance(result_item, dict) and 'output' in result_item:
                output_data = result_item['output']
                
                # Extract the specific task type data if it exists
                if isinstance(output_data, dict) and task_type in output_data:
                    data = output_data[task_type]
                else:
                    data = output_data
            else:
                data = result_item
            
            # Handle empty data
            if not data:
                logger.info(f"Extracted empty data for {task_type}, using empty dict")
                return {}
            
            # Clean up string data by removing newlines
            if isinstance(data, dict):
                cleaned_data = {}
                for k, v in data.items():
                    if isinstance(v, str):
                        cleaned_data[k] = v.replace('\n', '')
                    else:
                        cleaned_data[k] = v
                data = cleaned_data
            elif isinstance(data, str):
                data = data.replace('\n', '')
            
            # Ensure we return a dict for consistency
            if not isinstance(data, dict):
                logger.warning(f"Expected dict for {task_type} but got {type(data)}: {data}")
                return {}
                
            return data
            
        except Exception as e:
            logger.error(f"Error parsing n8n response for {task_type}: {str(e)}", exc_info=True)
            return {}

    def _update_company_data(self, company: Company, query_type: str, data: Dict[str, Any]) -> None:
        # Store the data directly since it's already been extracted properly
        if query_type == "profile":
            company.profile = data if isinstance(data, dict) else {}
        elif query_type == "digital":
            company.digital = data if isinstance(data, dict) else {}
        elif query_type == "timeline":
            company.timeline = data if isinstance(data, dict) else {}
        elif query_type == "products":
            company.products = data if isinstance(data, dict) else {}
        elif query_type == "jobs":
            company.jobs = data if isinstance(data, dict) else {}
        elif query_type == "csr":   
            company.csr = data if isinstance(data, dict) else {}
        elif query_type == "press":
            company.press = data if isinstance(data, dict) else {}
        elif query_type == "team":
            company.team = data if isinstance(data, list) else [] 