from typing import List, Dict, Any, Optional
import logging
from sqlalchemy.orm import Session
from app.models.company import Company
from app.models.task import Task, TaskType, TaskStatus
from app.schemas.company import CompanyCreate, CompanyUpdate
from app.services.n8n import N8nClient
from app.core.database_security import SecureQueryBuilder
from app.core.validators import ValidationError

logger = logging.getLogger(__name__)

class CompanyService:
    def __init__(self, db: Session, n8n_client: N8nClient):
        self.db = db
        self.n8n_client = n8n_client
        self.secure_query = SecureQueryBuilder(db)
    
    def get_company(self, company_id: int) -> Optional[Company]:
        """Securely get company by ID"""
        company = self.secure_query.safe_filter_by_id(Company, company_id).first()
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
        """Securely get company by name"""
        company = self.secure_query.safe_filter_by_string(Company, Company.name, name, exact_match=True).first()
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
    
    def get_all_companies(self, username: Optional[str] = None) -> List[Company]:
        """Securely get all companies, optionally filtered by owner"""
        if username:
            companies = self.secure_query.safe_filter_by_owner(Company, Company.owner_username, username).all()
        else:
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
    
    def create_company(self, name: str, website: str, owner_username: str) -> Company:
        """Securely create a new company"""
        print(f"🏭 CompanyService.create_company - START - Name: {name[:50]}, Owner: {owner_username}")
        
        # Additional validation
        print(f"🔍 Validating owner username: {owner_username}")
        if not owner_username or len(owner_username) > 100:
            print(f"❌ Invalid owner username: {owner_username}")
            raise ValidationError("Invalid owner username")
        
        print(f"🔄 Creating company entity via secure_query...")
        company = self.secure_query.safe_create_entity(
            Company,
            name=name,
            website=website,
            owner_username=owner_username
        )
        print(f"✅ Company entity created - ID: {company.id}")
        
        # Create the 8 default tasks with pending status
        default_tasks = [
            ('profile', 'Profil'),
            ('digital', 'Digital'),
            ('csr', 'RSE'),
            ('press', 'Presse'),
            ('timeline', 'Timeline'),
            ('products', 'Produits'),
            ('team', 'Équipe'),
            ('jobs', 'Emplois')
        ]
        
        for task_type, task_name in default_tasks:
            task = Task(
                company_id=company.id,
                type=TaskType(task_type),
                status=TaskStatus.PENDING
            )
            company.tasks.append(task)
        
        self.db.commit()
        self.db.refresh(company)
        
        logger.info(f"Created company '{name}' with {len(default_tasks)} pending tasks for user '{owner_username}'")
        
        return company
    
    def update_company(self, company: Company) -> Company:
        """Securely update company"""
        with self.secure_query.secure_query_context():
            self.db.commit()
            self.db.refresh(company)
        return company
    
    def delete_company(self, company_id: int) -> bool:
        """Securely delete company"""
        company = self.get_company(company_id)
        if not company:
            return False
        
        return self.secure_query.safe_delete_entity(company)

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
            print(f"parsed response to be updated: {result}")
            
            self._update_company_data(company, task.type.value, result)
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
            logger.info(f"Raw n8n response for {task_type}: {result}")
            return result
            
        except Exception as e:
            logger.error(f"Error parsing n8n response for {task_type}: {str(e)}", exc_info=True)
            return {}

    def _update_company_data(self, company: Company, query_type: str, data: Dict[str, Any]) -> None:
        # Store the data directly since it's already been extracted properly
        if query_type == "profile":
            company.profile = data.get("profile", {}) if isinstance(data, dict) else {}
        elif query_type == "digital":
            company.digital = data.get("digital", {}) if isinstance(data, dict) else {}
        elif query_type == "timeline":
            company.timeline = data.get("timeline", {}) if isinstance(data, dict) else {}
        elif query_type == "products":
            company.products = data.get("products", {}) if isinstance(data, dict) else {}
        elif query_type == "jobs":
            company.jobs = data.get("jobs", {}) if isinstance(data, dict) else {}
        elif query_type == "csr":   
            company.csr = data.get("csr", {}) if isinstance(data, dict) else {}
        elif query_type == "press":
            company.press = data.get("press", {}) if isinstance(data, dict) else {}
        elif query_type == "team":
            company.team = data.get("team", []) if isinstance(data, dict) else []