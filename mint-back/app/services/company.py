from typing import List, Dict, Any, Optional
import logging
from sqlalchemy.orm import Session
from app.models.company import Company
from app.models.task import Task, TaskType, TaskStatus
from app.models.workflow_config import WorkflowConfig
from app.schemas.company import (
    CompanyCreate, CompanyUpdate, CompanyResponse,
    CompanyCSVRow, CompanyCSVValidationError, CompanyCSVValidationResponse,
    CompanyCSVImportResponse, CompanyCSVImportResult
)
from app.schemas.task import TaskTokenUpdate
from app.schemas.pagination import PaginationParams, PaginatedResponse, create_pagination_meta
from app.infrastructure.dify.client import DifyClient
from app.core.database_security import SecureQueryBuilder
from app.core.validators import ValidationError, InputValidator
from app.infrastructure.database.repositories.company_repository_impl import SQLAlchemyCompanyRepository
from app.core.config import settings
from app.workers.dify_tasks import execute_dify_workflow
from app.services.token_manager import TokenManager
from app.models.workspace import ModuleName

logger = logging.getLogger(__name__)

def _parse_json_fields(company: Company) -> Company:
    """Helper function to parse JSON string fields into proper JSON objects"""
    if not company:
        return company
    
    # Parse profile field
    if company.profile is None:
        company.profile = {}
    
    # Parse digital field if it's a JSON string
    if company.digital is None:
        company.digital = {}
    elif isinstance(company.digital, str):
        try:
            import json
            company.digital = json.loads(company.digital)
        except (json.JSONDecodeError, TypeError) as e:
            logger.warning(f"Failed to parse digital field for company {company.id}: {e}")
            company.digital = {}
    
    # Parse csr field if it's a JSON string (handle markdown code blocks)
    if company.csr is None:
        company.csr = {}
    elif isinstance(company.csr, str):
        try:
            import json
            # Remove markdown code block wrapper if present
            csr_text = company.csr.strip()
            if csr_text.startswith('```json') and csr_text.endswith('```'):
                csr_text = csr_text[7:-3].strip()  # Remove ```json and ```
            company.csr = json.loads(csr_text)
        except (json.JSONDecodeError, TypeError) as e:
            logger.warning(f"Failed to parse csr field for company {company.id}: {e}")
            company.csr = {}
    
    # Parse other fields
    for field_name in ['timeline', 'products', 'jobs', 'press']:
        field_value = getattr(company, field_name)
        if field_value is None:
            setattr(company, field_name, {})
        elif isinstance(field_value, str):
            try:
                import json
                setattr(company, field_name, json.loads(field_value))
            except (json.JSONDecodeError, TypeError) as e:
                logger.warning(f"Failed to parse {field_name} field for company {company.id}: {e}")
                setattr(company, field_name, {})
    
    # Handle team field specially - needs to be a list for CompanyResponse
    if company.team is None:
        company.team = []
    elif isinstance(company.team, dict):
        # Handle different dictionary structures
        if 'team' in company.team:
            # Handle nested team structure from data processing
            company.team = company.team.get('team', [])
        elif 'teamAnalysis' in company.team:
            # Handle new teamAnalysis structure - extract the team members
            team_analysis = company.team.get('teamAnalysis', {})
            if isinstance(team_analysis, dict):
                # Try to extract team members from different possible locations
                team_members = []
                
                # Check for team members in various possible keys
                if 'team' in team_analysis:
                    team_members = team_analysis.get('team', [])
                elif 'members' in team_analysis:
                    team_members = team_analysis.get('members', [])
                elif 'subordinates' in team_analysis:
                    team_members = team_analysis.get('subordinates', [])
                
                # If still no team members found, convert the whole structure to a list
                if not team_members and team_analysis:
                    # Store the team analysis as a single-item list to preserve the data
                    team_members = [team_analysis]
                
                company.team = team_members if isinstance(team_members, list) else []
            else:
                company.team = []
        else:
            # Unknown dictionary structure - try to convert to list or set empty
            logger.warning(f"Unknown team dictionary structure for company {company.id}: {list(company.team.keys())[:5]}")
            # Store as single-item list to preserve data
            company.team = [company.team] if company.team else []
    elif isinstance(company.team, str):
        try:
            import json
            team_data = json.loads(company.team)
            if isinstance(team_data, dict):
                # Recursively handle the parsed dictionary
                company.team = team_data
                # Call this section again to handle the dictionary
                return _parse_json_fields(company)
            elif isinstance(team_data, list):
                company.team = team_data
            else:
                logger.warning(f"Unexpected team data type for company {company.id}: {type(team_data)}")
                company.team = []
        except (json.JSONDecodeError, TypeError) as e:
            logger.warning(f"Failed to parse team field for company {company.id}: {e}")
            company.team = []
    elif not isinstance(company.team, list):
        # If it's not a list, dict, string or None, log and set to empty list
        logger.warning(f"Unexpected team field type for company {company.id}: {type(company.team)}")
        company.team = []
    
    return company

class CompanyService:
    def __init__(self, db: Session):
        self.db = db
        self.dify_client = DifyClient(db)  # Pass database session for workflow config access
        self.secure_query = SecureQueryBuilder(db)
        self.repository = SQLAlchemyCompanyRepository(db)
    
    def get_company(self, company_id: int, include_deleted: bool = False) -> Optional[Company]:
        """Securely get company by ID"""
        query = self.secure_query.safe_filter_by_id(Company, company_id)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        company = query.first()
        return _parse_json_fields(company)
    
    def get_company_by_name(self, name: str, include_deleted: bool = False) -> Optional[Company]:
        """Securely get company by name"""
        query = self.secure_query.safe_filter_by_string(Company, Company.name, name, exact_match=True)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        company = query.first()
        return _parse_json_fields(company)
    
    def get_all_companies(self, workspace_id: Optional[int] = None, include_deleted: bool = False) -> List[Company]:
        """Securely get all companies, optionally filtered by workspace"""
        query = self.db.query(Company)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        if workspace_id:
            query = query.filter(Company.workspace_id == workspace_id)
        companies = query.all()
        # Parse JSON fields for all companies
        return [_parse_json_fields(company) for company in companies]
    
    def get_paginated_companies(self, pagination_params: PaginationParams, workspace_id: Optional[int] = None, name_filter: Optional[str] = None, include_archived: bool = False) -> PaginatedResponse[CompanyResponse]:
        """Get paginated companies with sorting and filtering"""
        companies, total_count = self.repository.get_paginated(pagination_params, workspace_id, name_filter, include_archived)
        
        # Convert SQLAlchemy models to Pydantic response models
        company_responses = []
        for company in companies:
            try:
                # Parse JSON fields
                company = _parse_json_fields(company)
                
                # Convert to CompanyResponse using from_attributes
                company_response = CompanyResponse.model_validate(company)
                company_responses.append(company_response)
            except Exception as e:
                # Log the error but don't fail the entire request
                logger.error(f"Failed to validate company {company.id}: {e}")
                
                # Try to create a minimal valid response
                try:
                    # Ensure team is a list
                    if not isinstance(company.team, list):
                        company.team = []
                    
                    # Ensure all dict fields are dicts
                    for field in ['profile', 'digital', 'timeline', 'products', 'jobs', 'csr', 'press']:
                        if not isinstance(getattr(company, field, None), dict):
                            setattr(company, field, {})
                    
                    # Try validation again
                    company_response = CompanyResponse.model_validate(company)
                    company_responses.append(company_response)
                    logger.info(f"Successfully created fallback response for company {company.id}")
                except Exception as fallback_error:
                    # If still failing, skip this company but log it
                    logger.error(f"Failed to create fallback response for company {company.id}: {fallback_error}")
                    # Optionally, you could add a placeholder or continue without this company
                    continue
        
        # Create pagination metadata
        meta = create_pagination_meta(
            total=total_count,
            page=pagination_params.page,
            per_page=pagination_params.per_page
        )
        
        return PaginatedResponse(data=company_responses, meta=meta)
    
    def create_company(self, name: str, website: str, owner_username: str, workspace_id: int) -> Company:
        """Securely create a new company"""
        print(f"🏭 CompanyService.create_company - START - Name: {name[:50]}, Owner: {owner_username}, Workspace: {workspace_id}")
        
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
            owner_username=owner_username,
            workspace_id=workspace_id
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
        
        # Queue tasks for execution instead of executing directly
        for task in company.tasks:
            # Get workflow configuration from database before queuing
            workflow_config = self.db.query(WorkflowConfig).filter(
                WorkflowConfig.task_type == task.type.value
            ).first()
            
            if not workflow_config:
                logger.error(f"No workflow configuration found for task type: {task.type.value}")
                task.status = TaskStatus.ERROR
                task.error = f"No workflow configuration found for task type: {task.type.value}"
                self.db.commit()
                continue
            
            if not workflow_config.workflow_id or not workflow_config.api_key:
                logger.error(f"Incomplete workflow configuration for task type: {task.type.value}")
                task.status = TaskStatus.ERROR
                task.error = f"Incomplete workflow configuration for task type: {task.type.value}"
                self.db.commit()
                continue
            
            logger.info(f"Queueing task {task.id} ({task.type.value}) for company {company.id}")
            execute_dify_workflow.delay(
                task_id=task.id,
                company_id=company.id,
                task_type=task.type.value,
                workflow_id=workflow_config.workflow_id,
                api_key=workflow_config.api_key,
                llm=workflow_config.llm
            )
        
        logger.info(f"Created company '{name}' and queued {len(default_tasks)} tasks for user '{owner_username}'")
        
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

    def _prepare_task_callbacks(self, task: Task) -> tuple[str, str, str]:
        """Helper method to prepare callback URLs for task execution"""
        success_callback = f"{settings.BACKEND_BASE_URL}/api/webhooks/dify/tasks/{task.id}/callback"
        error_callback = success_callback  # Same endpoint, different status in payload
        token_callback = f"{settings.BACKEND_BASE_URL}/api/webhooks/dify/tasks/{task.id}/tokens"
        
        # Debug logging for callback URLs
        logger.info(f"🔗 CALLBACK URL DEBUG - Task {task.type.value} - BACKEND_BASE_URL: {settings.BACKEND_BASE_URL}")
        logger.info(f"🔗 CALLBACK URL DEBUG - Task {task.type.value} - Success callback: {success_callback}")
        logger.info(f"🔗 CALLBACK URL DEBUG - Task {task.type.value} - Token callback: {token_callback}")
        
        return success_callback, error_callback, token_callback

    async def _execute_task(self, task: Task, company: Company) -> None:
        try:
            task.status = TaskStatus.RUNNING
            self.db.commit()
            
            logger.info(f"Using Dify workflow (async) for {task.type.value} task - Company: {company.name}")
            
            # Prepare callback URLs using helper method
            success_callback, error_callback, token_callback = self._prepare_task_callbacks(task)
            
            # Trigger Dify workflow with callbacks (ASYNC mode - fire and forget)
            result = await self.dify_client.trigger_workflow(
                task_type=task.type.value,
                company_name=company.name,
                website=company.website,
                success_callback=success_callback,
                error_callback=error_callback,
                task_id=task.id,
                company_id=company.id,
                async_mode=True,
                token_callback_url=token_callback
            )
            
            # In async mode, we just log the trigger confirmation
            logger.info(f"✅ Dify {task.type.value} workflow triggered (async): {result}")
            
            # Task remains in RUNNING state - will be updated via callback
            # No need to update company data here - callback will handle it
            self.db.commit()
            
        except Exception as e:
            task.status = TaskStatus.ERROR
            task.error = str(e)
            self.db.commit()
            # Log the error for debugging but don't re-raise to avoid crashing the backend
            logger.error(f"Task execution failed for company {company.name} (task {task.type.value}): {str(e)}", exc_info=True)


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
    
    def update_task_tokens(self, task_id: int, token_data: TaskTokenUpdate) -> Task:
        """Update token usage information for a task"""
        task = self.db.query(Task).filter(Task.id == task_id).first()
        if not task:
            raise ValueError(f"Task with ID {task_id} not found")
        
        # Update token fields
        if token_data.input_tokens is not None:
            task.input_tokens = token_data.input_tokens
        if token_data.output_tokens is not None:
            task.output_tokens = token_data.output_tokens
        if token_data.total_cost is not None:
            task.total_cost = token_data.total_cost
        
        # Calculate cost if not provided but tokens are available
        if (task.total_cost is None and 
            task.input_tokens is not None and 
            task.output_tokens is not None):
            # Claude Sonnet 4 pricing per your specification
            input_cost_per_1m = 3.15  # USD per 1M input tokens
            output_cost_per_1m = 15.75  # USD per 1M output tokens
            
            input_cost = (task.input_tokens / 1_000_000) * input_cost_per_1m
            output_cost = (task.output_tokens / 1_000_000) * output_cost_per_1m
            task.total_cost = input_cost + output_cost
        
        self.db.commit()
        self.db.refresh(task)
        return task
    
    def soft_delete_company(self, company_id: int) -> Optional[Company]:
        """Soft delete a company"""
        company = self.get_company(company_id)
        if company:
            company.is_deleted = True
            self.db.commit()
            self.db.refresh(company)
        return company
    
    def restore_company(self, company_id: int) -> Optional[Company]:
        """Restore a soft-deleted company"""
        company = self.db.query(Company).filter(
            Company.id == company_id,
            Company.is_deleted == True
        ).first()
        if company:
            company.is_deleted = False
            self.db.commit()
            self.db.refresh(company)
        return _parse_json_fields(company)
    
    def get_archived_companies(self, workspace_id: Optional[int] = None) -> List[Company]:
        """Get all soft-deleted (archived) companies"""
        query = self.db.query(Company).filter(Company.is_deleted == True)
        if workspace_id:
            query = query.filter(Company.workspace_id == workspace_id)
        companies = query.all()
        return [_parse_json_fields(company) for company in companies]
    
    def validate_csv_companies(self, companies: List[CompanyCSVRow], workspace_id: int,
                               token_manager: TokenManager) -> CompanyCSVValidationResponse:
        """Validate a list of companies from CSV without creating them"""
        errors = []
        
        # First, identify duplicate names and mark all instances as duplicates
        name_count = {}
        for company_row in companies:
            if company_row.name.strip():  # Only count non-empty names
                if company_row.name in name_count:
                    name_count[company_row.name].append(company_row.row_number)
                else:
                    name_count[company_row.name] = [company_row.row_number]
        
        duplicates = {name: rows for name, rows in name_count.items() if len(rows) > 1}
        
        for company_row in companies:
            # Check for duplicate names within the CSV
            if company_row.name in duplicates:
                other_rows = [r for r in duplicates[company_row.name] if r != company_row.row_number]
                errors.append(CompanyCSVValidationError(
                    row_number=company_row.row_number,
                    field="name",
                    error=f"Duplicate company name in CSV (also on row {other_rows[0]})"
                ))
                continue  # Skip other validations for duplicate rows
            
            # Check if company already exists in database (skip if name is empty - will be caught by validation)
            if company_row.name.strip():
                existing = self.get_company_by_name(company_row.name)
                if existing and existing.workspace_id == workspace_id:
                    errors.append(CompanyCSVValidationError(
                        row_number=company_row.row_number,
                        field="name",
                        error=f"Company '{company_row.name}' already exists in this workspace"
                    ))
            
            # Validate name
            try:
                InputValidator.validate_company_name(company_row.name)
            except ValidationError as e:
                errors.append(CompanyCSVValidationError(
                    row_number=company_row.row_number,
                    field="name",
                    error=str(e)
                ))
            
            # Validate website
            try:
                InputValidator.validate_website_url(company_row.website)
            except ValidationError as e:
                errors.append(CompanyCSVValidationError(
                    row_number=company_row.row_number,
                    field="website",
                    error=str(e)
                ))
        
        # Calculate valid companies count (count unique error row numbers since a row can have multiple errors)
        error_rows = set(error.row_number for error in errors if error.row_number > 0)
        valid_count = len(companies) - len(error_rows)
        tokens_required = valid_count
        
        # Check available tokens
        module = token_manager.get_module_tokens(workspace_id, ModuleName.SCREEN)
        available_tokens = module.token_count if module else 0
        has_sufficient_tokens = available_tokens >= tokens_required
        
        # Add token insufficiency as a validation error if needed
        if not has_sufficient_tokens and valid_count > 0:
            errors.append(CompanyCSVValidationError(
                row_number=0,  # Global error, not specific to a row
                field="tokens",
                error=f"Insufficient tokens. Required: {tokens_required}, Available: {available_tokens}"
            ))
        
        return CompanyCSVValidationResponse(
            valid_count=valid_count,
            error_count=len(errors),
            errors=errors,
            has_sufficient_tokens=has_sufficient_tokens,
            tokens_required=tokens_required,
            tokens_available=available_tokens
        )
    
    def import_csv_companies(self, companies: List[CompanyCSVRow], owner_username: str,
                            workspace_id: int, skip_invalid: bool = True) -> CompanyCSVImportResponse:
        """Import companies from CSV, creating them with tasks"""
        results = []
        successful = 0
        
        # First validate all companies (without token manager for internal validation)
        validation_errors = []
        invalid_rows = set()
        
        # First, identify duplicate names and mark all instances as invalid
        name_count = {}
        for company_row in companies:
            if company_row.name.strip():  # Only count non-empty names
                if company_row.name in name_count:
                    name_count[company_row.name].append(company_row.row_number)
                else:
                    name_count[company_row.name] = [company_row.row_number]
        
        duplicates = {name: rows for name, rows in name_count.items() if len(rows) > 1}
        
        for company_row in companies:
            # Check for duplicate names within the CSV
            if company_row.name in duplicates:
                validation_errors.append(company_row.row_number)
                invalid_rows.add(company_row.row_number)
                continue
            
            # Check if company already exists in database (skip if name is empty - will be caught by validation)
            if company_row.name.strip():
                existing = self.get_company_by_name(company_row.name)
                if existing and existing.workspace_id == workspace_id:
                    validation_errors.append(company_row.row_number)
                    invalid_rows.add(company_row.row_number)
                    continue
            
            # Validate name and website
            try:
                InputValidator.validate_company_name(company_row.name)
                InputValidator.validate_website_url(company_row.website)
            except ValidationError:
                validation_errors.append(company_row.row_number)
                invalid_rows.add(company_row.row_number)
        
        # If skip_invalid is False and there are errors, fail the entire import
        if not skip_invalid and len(validation_errors) > 0:
            return CompanyCSVImportResponse(
                total_rows=len(companies),
                successful=0,
                failed=len(companies),
                results=[
                    CompanyCSVImportResult(
                        row_number=company.row_number,
                        success=False,
                        name=company.name,
                        error="Import cancelled due to validation errors"
                    ) for company in companies
                ]
            )
        
        # Process each valid company
        for company_row in companies:
            if company_row.row_number in invalid_rows:
                # Add failure result for invalid rows
                results.append(CompanyCSVImportResult(
                    row_number=company_row.row_number,
                    success=False,
                    name=company_row.name,
                    error="Validation error"
                ))
                continue
            
            try:
                # Create the company (reusing existing create_company logic)
                company = self.create_company(
                    name=company_row.name,
                    website=company_row.website,
                    owner_username=owner_username,
                    workspace_id=workspace_id
                )
                
                results.append(CompanyCSVImportResult(
                    row_number=company_row.row_number,
                    success=True,
                    company_id=company.id,
                    name=company.name,
                    error=None
                ))
                successful += 1
                
            except Exception as e:
                logger.error(f"Failed to create company from CSV row {company_row.row_number}: {str(e)}")
                results.append(CompanyCSVImportResult(
                    row_number=company_row.row_number,
                    success=False,
                    name=company_row.name,
                    error=str(e)
                ))
        
        return CompanyCSVImportResponse(
            total_rows=len(companies),
            successful=successful,
            failed=len(companies) - successful,
            results=results
        )