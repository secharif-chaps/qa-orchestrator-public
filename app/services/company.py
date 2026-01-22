"""Company service module for business logic operations.

This module provides the CompanyService class for managing company entities,
including CRUD operations, task management, and data updates from Dify callbacks.

After Task Group 11 cleanup, this service:
- Writes section data to normalized tables only (no JSON columns)
- Reads section data from normalized tables for API responses
- Maintains raw_*_knowledge fields on Company model for data collection
"""

from typing import List, Dict, Any, Optional
import logging
from sqlalchemy.orm import Session, joinedload
from app.models.company import Company
from app.models.task import Task, TaskType, TaskStatus
from app.models.workflow_config import WorkflowConfig
from app.schemas.company import (
    CompanyResponse,
    CompanyCSVRow, CompanyCSVValidationError, CompanyCSVValidationResponse,
    CompanyCSVImportResponse, CompanyCSVImportResult
)
from app.schemas.task import TaskTokenUpdate
from app.schemas.pagination import PaginationParams, PaginatedResponse, create_pagination_meta
from app.services.dify import DifyService
from app.services.company_section_service import (
    write_section_data,
    read_all_section_data,
    apply_translations_to_section_data,
)
from app.services.translation import (
    TranslationService,
    SUPPORTED_LANGUAGE_CODES,
)
from app.core.database_security import SecureQueryBuilder
from app.core.exceptions import ResourceNotFoundError, ValidationError as ValidationException
from app.core.validators import ValidationError, InputValidator
from app.repositories.company_repository_impl import SQLAlchemyCompanyRepository
from app.core.config import settings
from app.workers.dify_tasks import execute_dify_workflow
from app.services.token_manager import TokenManager, TOKENS_PER_COMPANY

logger = logging.getLogger(__name__)


def _build_company_response(
    db: Session,
    company: Company,
    language: str | None = None,
) -> CompanyResponse:
    """Build CompanyResponse from Company model and normalized tables.

    Reads section data from normalized tables instead of JSON columns.
    Optionally applies translations for the specified language.

    Args:
        db: Database session for reading section data
        company: Company model instance
        language: Optional language code (fr, es, de, pt) for translations.
                  All languages read from the translations table.

    Returns:
        CompanyResponse with all section data populated
    """
    # Read all section data from normalized tables
    section_data = read_all_section_data(db, company.id)

    # Apply translations if a supported language is requested
    if language and language in SUPPORTED_LANGUAGE_CODES:
        translation_service = TranslationService(db)
        translations_map = translation_service.get_translations_map(company.id, language)
        section_data = apply_translations_to_section_data(
            section_data, translations_map, company.id
        )

    # Build response with section data
    return CompanyResponse(
        id=company.id,
        name=company.name,
        website=company.website,
        owner_id=company.owner_id,
        owner_username=company.owner_username or "",
        profile=section_data.get("profile", {}),
        digital=section_data.get("digital", {}),
        timeline=section_data.get("timeline", {}),
        products=section_data.get("products", {}),
        jobs=section_data.get("jobs", {}),
        csr=section_data.get("csr", {}),
        press=section_data.get("press", {}),
        team=section_data.get("team", []),
        raw_mistral_knowledge=company.raw_mistral_knowledge,
        raw_gpt_knowledge=company.raw_gpt_knowledge,
        raw_wikipedia_knowledge=company.raw_wikipedia_knowledge,
        raw_scraped_website_knowledge=company.raw_scraped_website_knowledge,
        error=company.error,
        is_deleted=company.is_deleted,
        created_at=company.created_at,
        updated_at=company.updated_at,
        tasks=[],  # Tasks loaded separately if needed
    )


class CompanyService:
    def __init__(self, db: Session):
        self.db = db
        self.dify_service = DifyService(db)  # Pass database session for workflow config access
        self.secure_query = SecureQueryBuilder(db)
        self.repository = SQLAlchemyCompanyRepository(db)

    def get_company(self, company_id: int, include_deleted: bool = False) -> Optional[Company]:
        """Securely get company by ID.

        Returns the Company model. Use get_company_response() for API responses
        that need section data from normalized tables.
        """
        query = self.secure_query.safe_filter_by_id(Company, company_id)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        return query.options(joinedload(Company.tasks)).first()

    def get_company_response(self, company_id: int, include_deleted: bool = False) -> Optional[CompanyResponse]:
        """Get company as CompanyResponse with all section data.

        This method reads section data from normalized tables and builds
        a complete CompanyResponse for API responses.
        """
        company = self.get_company(company_id, include_deleted)
        if not company:
            return None
        return _build_company_response(self.db, company)

    def get_company_by_name(self, name: str, include_deleted: bool = False) -> Optional[Company]:
        """Securely get company by name"""
        query = self.secure_query.safe_filter_by_string(Company, Company.name, name, exact_match=True)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        return query.first()

    def get_all_companies(self, organization_id: Optional[str] = None, include_deleted: bool = False) -> List[Company]:
        """Securely get all companies, optionally filtered by organization"""
        query = self.db.query(Company)
        if not include_deleted:
            query = query.filter(Company.is_deleted == False)
        if organization_id:
            query = query.filter(Company.organization_id == organization_id)
        return query.all()

    def get_paginated_companies(self, pagination_params: PaginationParams, organization_id: Optional[str] = None, name_filter: Optional[str] = None, include_archived: bool = False) -> PaginatedResponse[CompanyResponse]:
        """Get paginated companies with sorting and filtering"""
        companies, total_count = self.repository.get_paginated(pagination_params, organization_id, name_filter, include_archived)

        # Convert SQLAlchemy models to Pydantic response models
        company_responses = []
        for company in companies:
            try:
                # Build response from normalized tables
                company_response = _build_company_response(self.db, company)
                company_responses.append(company_response)
            except Exception as e:
                # Log the error but don't fail the entire request
                logger.error(f"Failed to build response for company {company.id}: {e}")
                continue

        # Create pagination metadata
        meta = create_pagination_meta(
            total=total_count,
            page=pagination_params.page,
            per_page=pagination_params.per_page
        )

        return PaginatedResponse(data=company_responses, meta=meta)

    def create_company(self, name: str, website: str, owner_id: str, owner_username: str, organization_id: str) -> Company:
        """Securely create a new company"""
        from app.services.task_dependency_service import TaskDependencyService

        logger.info(f"Creating company: {name[:50]}, Owner: {owner_username} ({owner_id}), Organization: {organization_id}")

        # Additional validation
        if not owner_username or len(owner_username) > 100:
            logger.error(f"Invalid owner username: {owner_username}")
            raise ValidationError("Invalid owner username")

        # Create company
        company = self.secure_query.safe_create_entity(
            Company,
            name=name,
            website=website,
            owner_id=owner_id,
            owner_username=owner_username,
            organization_id=organization_id
        )
        logger.info(f"Company entity created - ID: {company.id}")

        # Define task configurations with dependency information
        # data_collection is the prerequisite that must run first
        task_configs = [
            {'type': 'data_collection', 'is_prerequisite': True},  # Must run first
            {'type': 'profile', 'is_prerequisite': False},
            {'type': 'digital', 'is_prerequisite': False},
            {'type': 'csr', 'is_prerequisite': False},
            {'type': 'press', 'is_prerequisite': False},
            {'type': 'timeline', 'is_prerequisite': False},
            {'type': 'products', 'is_prerequisite': False},
            {'type': 'team', 'is_prerequisite': False},
            {'type': 'jobs', 'is_prerequisite': False},
        ]

        # Create all tasks with appropriate initial status
        prerequisite_task = None
        dependent_tasks = []

        for config in task_configs:
            if config['is_prerequisite']:
                # Prerequisite task starts as PENDING (ready to run)
                task = Task(
                    company_id=company.id,
                    type=TaskType(config['type']),
                    status=TaskStatus.PENDING,
                    is_prerequisite=True
                )
                prerequisite_task = task
                logger.info(f"Created prerequisite task: {task.type.value}")
            else:
                # Dependent tasks start as BLOCKED (waiting for prerequisite)
                task = Task(
                    company_id=company.id,
                    type=TaskType(config['type']),
                    status=TaskStatus.BLOCKED,
                    is_prerequisite=False
                )
                dependent_tasks.append(task)

            company.tasks.append(task)

        self.db.commit()
        self.db.refresh(company)

        # Create dependency relationships
        dependency_service = TaskDependencyService(self.db)
        for dependent_task in dependent_tasks:
            dependency_service.create_dependency(
                task_id=dependent_task.id,
                depends_on_task_id=prerequisite_task.id
            )

        logger.info(f"Created {len(dependent_tasks)} task dependencies")

        # Queue ONLY the prerequisite task (data_collection)
        workflow_config = self.db.query(WorkflowConfig).filter(
            WorkflowConfig.task_type == prerequisite_task.type.value
        ).first()

        if not workflow_config or not workflow_config.api_key:
            logger.error(f"Invalid workflow configuration for {prerequisite_task.type.value}")
            prerequisite_task.status = TaskStatus.ERROR
            prerequisite_task.error = "No workflow configuration found"
            self.db.commit()
        else:
            # Prepare callback URLs in FastAPI context before queueing to Celery
            success_callback, error_callback = self._prepare_task_callbacks(prerequisite_task)

            logger.info(f"Queueing prerequisite task {prerequisite_task.id} ({prerequisite_task.type.value})")
            execute_dify_workflow.delay(
                task_id=prerequisite_task.id,
                company_id=company.id,
                task_type=prerequisite_task.type.value,
                api_key=workflow_config.api_key,
                success_callback=success_callback,
                error_callback=error_callback
            )

        logger.info(f"Created company '{name}' with 1 prerequisite task and {len(dependent_tasks)} dependent tasks")

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

    def restart_task(self, task_id: int) -> Optional[Task]:
        """Restart a task by queueing it via Celery (fire-and-forget).

        This method resets the task status and queues it for execution via Celery,
        returning immediately without waiting for the workflow to complete.

        Args:
            task_id: The ID of the task to restart

        Returns:
            The restarted Task instance, or None if task not found
        """
        # Query task directly with its company relationship
        task = self.db.query(Task).filter(Task.id == task_id).first()
        if not task:
            logger.warning(f"Task {task_id} not found for restart")
            return None

        company = self.db.query(Company).filter(Company.id == task.company_id).first()
        if not company:
            logger.error(f"Company {task.company_id} not found for task {task_id}")
            return None

        # Reset task status
        task.status = TaskStatus.PENDING
        task.error = None
        self.db.commit()

        # Get workflow configuration for this task type
        workflow_config = self.db.query(WorkflowConfig).filter(
            WorkflowConfig.task_type == task.type.value
        ).first()

        if not workflow_config or not workflow_config.api_key:
            logger.error(f"Invalid workflow configuration for {task.type.value}")
            task.status = TaskStatus.ERROR
            task.error = "No workflow configuration found"
            self.db.commit()
            return task

        # Prepare callback URLs
        success_callback, error_callback = self._prepare_task_callbacks(task)

        # Queue task via Celery (fire-and-forget)
        logger.info(f"Queueing restart of task {task.id} ({task.type.value}) for company {company.name}")
        execute_dify_workflow.delay(
            task_id=task.id,
            company_id=company.id,
            task_type=task.type.value,
            api_key=workflow_config.api_key,
            success_callback=success_callback,
            error_callback=error_callback
        )

        return task

    def _prepare_task_callbacks(self, task: Task) -> tuple[str, str]:
        """Helper method to prepare callback URLs for task execution"""
        success_callback = f"{settings.BACKEND_BASE_URL}/webhooks/dify/tasks/{task.id}/callback"
        error_callback = success_callback  # Same endpoint, different status in payload

        # Debug logging for callback URLs
        logger.info(
            f"URL DEBUG [CompanyService._prepare_task_callbacks] Task {task.type.value}",
            extra={
                "task_id": task.id,
                "task_type": task.type.value,
                "BACKEND_BASE_URL": settings.BACKEND_BASE_URL,
                "success_callback": success_callback,
                "error_callback": error_callback,
            }
        )

        return success_callback, error_callback

    async def _execute_task(self, task: Task, company: Company) -> None:
        try:
            task.status = TaskStatus.RUNNING
            self.db.commit()

            logger.info(f"Using Dify workflow (async) for {task.type.value} task - Company: {company.name}")

            # Prepare callback URLs using helper method
            success_callback, error_callback = self._prepare_task_callbacks(task)

            # Trigger Dify workflow with callbacks (streaming mode - fire and forget)
            result = await self.dify_service.run_workflow(
                task_type=task.type.value,
                company_name=company.name,
                website=company.website,
                success_callback=success_callback,
                error_callback=error_callback,
                task_id=task.id,
                company_id=company.id,
                response_mode="streaming"  # Fire-and-forget mode
            )

            logger.info(f"Dify {task.type.value} workflow triggered (fire-and-forget): {result}")

            # Task remains in RUNNING state - will be updated via webhook callback
            # The callback at /webhooks/dify/tasks/{task_id}/callback will handle:
            # - Processing the workflow data
            # - Marking task as SUCCEEDED
            # - Unblocking dependent tasks
            # - Queueing dependent tasks
            self.db.commit()

        except Exception as e:
            task.status = TaskStatus.ERROR
            task.error = str(e)
            self.db.commit()
            # Log the error for debugging but don't re-raise to avoid crashing the backend
            logger.error(f"Task execution failed for company {company.name} (task {task.type.value}): {str(e)}", exc_info=True)

    def _update_company_data(self, company: Company, query_type: str, data: Dict[str, Any]) -> None:
        """Update company data from Dify callback.

        After Task Group 11 cleanup, this method:
        - Writes section data to normalized tables only (profile, digital, etc.)
        - Writes data_collection results to Company raw_*_knowledge fields

        Args:
            company: Company model instance
            query_type: Type of data (profile, digital, data_collection, etc.)
            data: Dify callback data
        """
        if query_type == "data_collection":
            # data_collection writes to Company model raw_*_knowledge fields
            if isinstance(data, dict):
                # Check if data is nested under "knowledge" key
                if "knowledge" in data and isinstance(data["knowledge"], dict):
                    knowledge_data = data["knowledge"]
                else:
                    # Flat structure - data contains the fields directly
                    knowledge_data = data

                company.raw_mistral_knowledge = knowledge_data.get("mistral", "")
                company.raw_gpt_knowledge = knowledge_data.get("gpt", "")
                company.raw_wikipedia_knowledge = knowledge_data.get("wikipedia", "")
                company.raw_scraped_website_knowledge = knowledge_data.get("scraped", "")
            else:
                # Not a dict, set all to empty
                company.raw_mistral_knowledge = ""
                company.raw_gpt_knowledge = ""
                company.raw_wikipedia_knowledge = ""
                company.raw_scraped_website_knowledge = ""
        else:
            # All other query types write to normalized tables
            # Data comes from webhook wrapped as {query_type: actual_data}
            # Extract the actual data for the section writers which expect root-level data
            section_data = data.get(query_type, data) if isinstance(data, dict) else data
            write_section_data(self.db, company.id, query_type, section_data)

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
            Company.is_deleted
        ).first()
        if company:
            company.is_deleted = False
            self.db.commit()
            self.db.refresh(company)
        return company

    def get_archived_companies(self, organization_id: Optional[str] = None) -> List[Company]:
        """Get all soft-deleted (archived) companies"""
        query = self.db.query(Company).filter(Company.is_deleted)
        if organization_id:
            query = query.filter(Company.organization_id == organization_id)
        return query.all()

    def get_recent_companies(
        self,
        organization_id: str,
        limit: int = 5,
        accessible_company_ids: set | None = None
    ) -> List[CompanyResponse]:
        """Get recent companies with their folder information.

        Args:
            organization_id: Organization UUID to filter by
            limit: Maximum number of companies to return
            accessible_company_ids: Optional set of company IDs the user has access to.
                If provided, only companies in this set are returned.
                If None, all companies in the organization are returned (legacy behavior).

        Returns:
            List of CompanyResponse objects with folder information
        """
        from app.models.folder import FolderItem, Folder

        # Build base query
        query = (
            self.db.query(Company)
            .filter(Company.organization_id == organization_id)
            .filter(Company.is_deleted == False)
        )

        # Filter by accessible company IDs if provided
        if accessible_company_ids is not None:
            if not accessible_company_ids:
                # User has no accessible companies
                return []
            query = query.filter(Company.id.in_(accessible_company_ids))

        # Get recent companies ordered by created_at
        companies = query.order_by(Company.created_at.desc()).limit(limit).all()

        # Build response with folder information
        company_responses = []
        for company in companies:
            # Build response from normalized tables
            company_response = _build_company_response(self.db, company)

            # Find the first folder this company belongs to
            folder_item = (
                self.db.query(FolderItem, Folder)
                .join(Folder, FolderItem.folder_id == Folder.id)
                .filter(FolderItem.item_id == str(company.id))
                .filter(FolderItem.item_type == 'company')
                .filter(Folder.is_deleted == False)
                .order_by(FolderItem.added_at.desc())  # Most recent folder first
                .first()
            )

            if folder_item:
                folder_item_obj, folder_obj = folder_item
                company_response.folder_id = str(folder_obj.id)
                company_response.folder_name = folder_obj.name

            company_responses.append(company_response)

        return company_responses

    def validate_csv_companies(self, companies: List[CompanyCSVRow], organization_id: str,
                               token_manager: TokenManager) -> CompanyCSVValidationResponse:
        """Validate a list of companies from CSV without creating them.

        Uses global token balance instead of module-specific tokens.
        Each company creation costs TOKENS_PER_COMPANY (35) tokens.
        """
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
                if existing and existing.organization_id == organization_id:
                    errors.append(CompanyCSVValidationError(
                        row_number=company_row.row_number,
                        field="name",
                        error=f"Company '{company_row.name}' already exists in this organization"
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

        # Calculate tokens required - each company costs TOKENS_PER_COMPANY tokens
        tokens_required = valid_count * TOKENS_PER_COMPANY

        # Check available tokens from global balance
        available_tokens = token_manager.get_balance(organization_id)
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
                            organization_id: str, skip_invalid: bool = True) -> CompanyCSVImportResponse:
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
                if existing and existing.organization_id == organization_id:
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
                    organization_id=organization_id
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

    def refresh_company(self, company_id: int) -> Company:
        """Reset all tasks and restart data collection workflow.

        Args:
            company_id: ID of the company to refresh

        Returns:
            Company instance with refreshed task states

        Raises:
            ResourceNotFoundError: If company doesn't exist
            ValidationError: If data collection task is missing or workflow config is invalid
        """
        logger.info(
            "Refreshing company tasks",
            extra={"company_id": company_id}
        )

        company = self.get_company(company_id)
        if not company:
            raise ResourceNotFoundError(
                "Company not found",
                details={"company_id": company_id}
            )

        data_collection_task = self._find_data_collection_task(company)
        self._reset_task_statuses(company, data_collection_task)
        self.db.commit()

        workflow_config = self._get_workflow_config(data_collection_task)
        self._queue_refresh_workflow(company, data_collection_task, workflow_config)

        return company

    def _find_data_collection_task(self, company: Company) -> Task:
        """Find the data collection task for the company."""
        for task in company.tasks:
            if task.type == TaskType.data_collection:
                return task

        logger.error(
            "Data collection task not found",
            extra={"company_id": company.id}
        )
        raise ValidationException(
            "Data collection task not found for company",
            details={"company_id": company.id}
        )

    def _reset_task_statuses(self, company: Company, data_collection_task: Task) -> None:
        """Reset task statuses: data_collection to PENDING, others to BLOCKED."""
        data_collection_task.status = TaskStatus.PENDING
        data_collection_task.error = None

        for task in company.tasks:
            if task.type != TaskType.data_collection:
                task.status = TaskStatus.BLOCKED
                task.error = None

        logger.info(
            "Reset task statuses for company refresh",
            extra={
                "company_id": company.id,
                "total_tasks": len(company.tasks)
            }
        )

    def _get_workflow_config(self, task: Task) -> WorkflowConfig:
        """Get workflow configuration for the task."""
        workflow_config = self.db.query(WorkflowConfig).filter(
            WorkflowConfig.task_type == task.type.value
        ).first()

        if not workflow_config or not workflow_config.api_key:
            logger.error(
                "Invalid workflow configuration",
                extra={"task_type": task.type.value}
            )
            task.status = TaskStatus.ERROR
            task.error = "No workflow configuration found"
            self.db.commit()

            raise ValidationException(
                "No workflow configuration found for task type",
                details={"task_type": task.type.value}
            )

        return workflow_config

    def _queue_refresh_workflow(
        self, company: Company, task: Task, workflow_config: WorkflowConfig
    ) -> None:
        """Queue the refresh workflow via Celery."""
        success_callback, error_callback, token_callback = self._prepare_task_callbacks(task)

        logger.info(
            "Queueing refresh workflow",
            extra={
                "company_id": company.id,
                "company_name": company.name,
                "task_id": task.id,
                "task_type": task.type.value,
            }
        )

        execute_dify_workflow.delay(
            task_id=task.id,
            company_id=company.id,
            task_type=task.type.value,
            api_key=workflow_config.api_key,
            success_callback=success_callback,
            error_callback=error_callback,
            token_callback=token_callback,
            llm=workflow_config.llm
        )

    def get_company_tasks(self, company_id: int) -> list[Task]:
        """Get all tasks for a company.

        Args:
            company_id: ID of the company

        Returns:
            List of Task instances for the company
        """
        company = self.get_company(company_id)
        if not company:
            return []
        return company.tasks
