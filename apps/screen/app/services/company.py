"""Company service module for business logic operations.

This module provides the CompanyService class for managing company entities,
including CRUD operations, task management, and LangGraph agent execution.
"""

from __future__ import annotations

import asyncio
import logging
from typing import TYPE_CHECKING

from sqlalchemy.orm import Session, joinedload

if TYPE_CHECKING:
    from app.services.token_manager import TokenManager

from app.core.database_security import SecureQueryBuilder
from app.core.exceptions import ResourceNotFoundError
from app.core.validators import InputValidator, ValidationError
from app.models.company import Company
from app.models.task import Task, TaskStatus, TaskType
from app.repositories.company_repository_impl import SQLAlchemyCompanyRepository
from app.schemas.company import (
    CompanyCSVImportResponse,
    CompanyCSVImportResult,
    CompanyCSVRow,
    CompanyCSVValidationError,
    CompanyCSVValidationResponse,
    CompanyResponse,
)
from app.schemas.pagination import PaginatedResponse, PaginationParams, create_pagination_meta
from app.schemas.task import TaskTokenUpdate
from app.services.company_section_service import (
    apply_translations_to_section_data,
    read_all_section_data,
)
from app.services.global_service_client import TOKENS_PER_COMPANY
from app.services.translation import (
    SUPPORTED_LANGUAGE_CODES,
    TranslationService,
)

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

    Returns:
        CompanyResponse with all section data populated
    """
    # Read all section data from normalized tables
    section_data = read_all_section_data(db, company.id)

    # Apply translations if a supported language is requested
    if language and language in SUPPORTED_LANGUAGE_CODES:
        translation_service = TranslationService(db)
        translations_map = translation_service.get_translations_map(company.id, language)
        section_data = apply_translations_to_section_data(section_data, translations_map, company.id)

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
        error=company.error,
        is_deleted=company.is_deleted,
        created_at=company.created_at,
        updated_at=company.updated_at,
        tasks=[],  # Tasks loaded separately if needed
    )


class CompanyService:
    def __init__(self, db: Session):
        self.db = db
        self.secure_query = SecureQueryBuilder(db)
        self.repository = SQLAlchemyCompanyRepository(db)

    def get_company(self, company_id: int, include_archived: bool = False) -> Company | None:
        """Securely get company by ID."""
        query = self.secure_query.safe_filter_by_id(Company, company_id)
        if not include_archived:
            query = query.filter(~Company.is_deleted)
        return query.options(joinedload(Company.tasks)).first()

    def get_company_response(self, company_id: int, include_archived: bool = False) -> CompanyResponse | None:
        """Get company as CompanyResponse with all section data."""
        company = self.get_company(company_id, include_archived)
        if not company:
            return None
        return _build_company_response(self.db, company)

    def get_company_by_name(self, name: str, include_archived: bool = False) -> Company | None:
        """Securely get company by name"""
        query = self.secure_query.safe_filter_by_string(Company, Company.name, name, exact_match=True)
        if not include_archived:
            query = query.filter(~Company.is_deleted)
        return query.first()

    def get_all_companies(self, organization_id: str | None = None, include_archived: bool = False) -> list[Company]:
        """Securely get all companies, optionally filtered by organization"""
        query = self.db.query(Company)
        if not include_archived:
            query = query.filter(~Company.is_deleted)
        if organization_id:
            query = query.filter(Company.organization_id == organization_id)
        return query.all()

    def get_paginated_companies(
        self,
        pagination_params: PaginationParams,
        organization_id: str | None = None,
        name_filter: str | None = None,
        include_archived: bool = False,
    ) -> PaginatedResponse[CompanyResponse]:
        """Get paginated companies with sorting and filtering"""
        companies, total_count = self.repository.get_paginated(
            pagination_params, organization_id, name_filter, include_archived
        )

        company_responses = []
        for company in companies:
            try:
                company_response = _build_company_response(self.db, company)
                company_responses.append(company_response)
            except Exception as e:
                logger.error(f"Failed to build response for company {company.id}: {e}")
                continue

        meta = create_pagination_meta(
            total=total_count, page=pagination_params.page, per_page=pagination_params.per_page
        )

        return PaginatedResponse(data=company_responses, meta=meta)

    def create_company(
        self,
        name: str,
        website: str,
        owner_id: str,
        owner_username: str,
        organization_id: str,
    ) -> Company:
        """Create a new company and launch LangGraph analysis.

        Args:
            name: Company name
            website: Company website URL
            owner_id: Keycloak user UUID
            owner_username: Username for display
            organization_id: Organization UUID
        """
        logger.info(
            f"Creating company: {name[:50]}, Owner: {owner_username} ({owner_id}), Organization: {organization_id}"
        )

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
            organization_id=organization_id,
        )
        logger.info(f"Company entity created - ID: {company.id}")

        # Create 8 tasks (all PENDING, no data_collection, no dependencies)
        for task_type in TaskType:
            task = Task(
                company_id=company.id,
                organization_id=organization_id,
                type=task_type,
                status=TaskStatus.PENDING,
            )
            company.tasks.append(task)

        self.db.commit()
        self.db.refresh(company)

        # Launch LangGraph analysis in background (non-blocking)
        self._launch_analysis(company, owner_id)

        logger.info(f"Created company '{name}' with {len(list(TaskType))} tasks")
        return company

    def _launch_analysis(self, company: Company, owner_id: str) -> None:
        """Launch LangGraph analysis as a background task."""
        from app.agents.runner import CompanyAnalysisRunner

        runner = CompanyAnalysisRunner()

        async def _run() -> None:
            from app.database import SessionLocal

            db = SessionLocal()
            try:
                await runner.run(
                    db=db,
                    company_id=company.id,
                    company_name=company.name,
                    website=company.website,
                    organization_id=company.organization_id,
                    owner_id=owner_id,
                )
            except Exception as e:
                logger.error(f"Analysis failed for company {company.id}: {e}", exc_info=True)
            finally:
                db.close()

        try:
            loop = asyncio.get_running_loop()
            loop.create_task(_run())
        except RuntimeError:
            # No running loop — should not happen in FastAPI but handle gracefully
            logger.error("No running event loop for analysis launch")

    def restart_task(self, task_id: int) -> Task | None:
        """Restart a task by running the corresponding agent.

        Args:
            task_id: The ID of the task to restart

        Returns:
            The restarted Task instance, or None if task not found
        """
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
        task.error_details = None
        self.db.commit()

        # Launch single agent in background
        self._launch_single_agent(task, company)

        return task

    def _launch_single_agent(self, task: Task, company: Company) -> None:
        """Launch a single agent restart as a background task."""
        from app.agents.runner import CompanyAnalysisRunner

        runner = CompanyAnalysisRunner()
        # Capture IDs to re-fetch in the new session (avoid detached ORM objects)
        task_id = task.id
        company_id = company.id

        async def _run() -> None:
            from app.database import SessionLocal

            db = SessionLocal()
            try:
                bg_task = db.query(Task).filter(Task.id == task_id).first()
                bg_company = db.query(Company).filter(Company.id == company_id).first()
                if not bg_task or not bg_company:
                    logger.error(f"Task {task_id} or company {company_id} not found in background session")
                    return
                await runner.run_single_agent(
                    db=db,
                    task=bg_task,
                    company=bg_company,
                )
            except Exception as e:
                logger.error(f"Single agent restart failed for task {task_id}: {e}", exc_info=True)
            finally:
                db.close()

        try:
            loop = asyncio.get_running_loop()
            loop.create_task(_run())
        except RuntimeError:
            logger.error("No running event loop for single agent launch")

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

    def update_task_tokens(self, task_id: int, token_data: TaskTokenUpdate) -> Task:
        """Update token usage information for a task"""
        task = self.db.query(Task).filter(Task.id == task_id).first()
        if not task:
            raise ValueError(f"Task with ID {task_id} not found")

        if token_data.input_tokens is not None:
            task.input_tokens = token_data.input_tokens
        if token_data.output_tokens is not None:
            task.output_tokens = token_data.output_tokens
        if token_data.total_cost is not None:
            task.total_cost = token_data.total_cost

        if task.total_cost is None and task.input_tokens is not None and task.output_tokens is not None:
            input_cost_per_1m = 1.25
            output_cost_per_1m = 10.00
            input_cost = (task.input_tokens / 1_000_000) * input_cost_per_1m
            output_cost = (task.output_tokens / 1_000_000) * output_cost_per_1m
            task.total_cost = input_cost + output_cost

        self.db.commit()
        self.db.refresh(task)
        return task

    def soft_delete_company(self, company_id: int) -> Company | None:
        """Soft delete a company"""
        company = self.get_company(company_id)
        if company:
            company.is_deleted = True
            self.db.commit()
            self.db.refresh(company)
        return company

    def restore_company(self, company_id: int) -> Company | None:
        """Restore a soft-deleted company"""
        company = self.db.query(Company).filter(Company.id == company_id, Company.is_deleted).first()
        if company:
            company.is_deleted = False
            self.db.commit()
            self.db.refresh(company)
        return company

    def get_archived_companies(self, organization_id: str | None = None) -> list[Company]:
        """Get all soft-deleted (archived) companies"""
        query = self.db.query(Company).filter(Company.is_deleted)
        if organization_id:
            query = query.filter(Company.organization_id == organization_id)
        return query.all()

    def get_recent_companies(
        self,
        organization_id: str,
        limit: int = 5,
        accessible_company_ids: set | None = None,
    ) -> list[CompanyResponse]:
        """Get recent companies in the organization.

        Args:
            organization_id: Organization UUID to filter by
            limit: Maximum number of companies to return
            accessible_company_ids: Optional set of company IDs the user has access to.
                If provided, only companies in this set are returned.

        Returns:
            List of CompanyResponse objects
        """
        query = (
            self.db.query(Company)
            .filter(Company.organization_id == organization_id)
            .filter(~Company.is_deleted)
        )

        if accessible_company_ids is not None:
            if not accessible_company_ids:
                return []
            query = query.filter(Company.id.in_(accessible_company_ids))

        companies = query.order_by(Company.created_at.desc()).limit(limit).all()

        return [_build_company_response(self.db, company) for company in companies]

    def validate_csv_companies(
        self,
        companies: list[CompanyCSVRow],
        organization_id: str,
        token_manager: TokenManager | None = None,
    ) -> CompanyCSVValidationResponse:
        """Validate a list of companies from CSV without creating them.

        Token validation is skipped since token consumption is handled by
        global-service.

        Each company creation costs TOKENS_PER_COMPANY (35) tokens.

        Args:
            companies: List of CSV row data to validate
            organization_id: Organization UUID
            token_manager: Deprecated, kept for backward compatibility. Always pass None.

        Returns:
            CompanyCSVValidationResponse with validation results
        """
        errors = []

        name_count: dict[str, list[int]] = {}
        for company_row in companies:
            if company_row.name.strip():
                if company_row.name in name_count:
                    name_count[company_row.name].append(company_row.row_number)
                else:
                    name_count[company_row.name] = [company_row.row_number]

        duplicates = {name: rows for name, rows in name_count.items() if len(rows) > 1}

        for company_row in companies:
            if company_row.name in duplicates:
                other_rows = [r for r in duplicates[company_row.name] if r != company_row.row_number]
                errors.append(
                    CompanyCSVValidationError(
                        row_number=company_row.row_number,
                        field="name",
                        error=f"Duplicate company name in CSV (also on row {other_rows[0]})",
                    )
                )
                continue

            if company_row.name.strip():
                existing = self.get_company_by_name(company_row.name)
                if existing and existing.organization_id == organization_id:
                    errors.append(
                        CompanyCSVValidationError(
                            row_number=company_row.row_number,
                            field="name",
                            error=f"Company '{company_row.name}' already exists in this organization",
                        )
                    )

            try:
                InputValidator.validate_company_name(company_row.name)
            except ValidationError as e:
                errors.append(CompanyCSVValidationError(row_number=company_row.row_number, field="name", error=str(e)))

            try:
                InputValidator.validate_website_url(company_row.website)
            except ValidationError as e:
                errors.append(
                    CompanyCSVValidationError(row_number=company_row.row_number, field="website", error=str(e))
                )

        error_rows = set(error.row_number for error in errors if error.row_number > 0)
        valid_count = len(companies) - len(error_rows)
        tokens_required = valid_count * TOKENS_PER_COMPANY

        if token_manager is not None:
            available_tokens = token_manager.get_balance(organization_id)
            has_sufficient_tokens = available_tokens >= tokens_required
            if not has_sufficient_tokens and valid_count > 0:
                errors.append(
                    CompanyCSVValidationError(
                        row_number=0,
                        field="tokens",
                        error=f"Insufficient tokens. Required: {tokens_required}, Available: {available_tokens}",
                    )
                )
        else:
            available_tokens = None
            has_sufficient_tokens = True

        return CompanyCSVValidationResponse(
            valid_count=valid_count,
            error_count=len(errors),
            errors=errors,
            has_sufficient_tokens=has_sufficient_tokens,
            tokens_required=tokens_required,
            tokens_available=available_tokens,
        )

    def import_csv_companies(
        self,
        companies: list[CompanyCSVRow],
        owner_id: str,
        owner_username: str,
        organization_id: str,
        skip_invalid: bool = True,
    ) -> CompanyCSVImportResponse:
        """Import companies from CSV, creating them with tasks"""
        results = []
        successful = 0

        validation_errors = []
        invalid_rows: set[int] = set()

        name_count: dict[str, list[int]] = {}
        for company_row in companies:
            if company_row.name.strip():
                if company_row.name in name_count:
                    name_count[company_row.name].append(company_row.row_number)
                else:
                    name_count[company_row.name] = [company_row.row_number]

        duplicates = {name: rows for name, rows in name_count.items() if len(rows) > 1}

        for company_row in companies:
            if company_row.name in duplicates:
                validation_errors.append(company_row.row_number)
                invalid_rows.add(company_row.row_number)
                continue

            if company_row.name.strip():
                existing = self.get_company_by_name(company_row.name)
                if existing and existing.organization_id == organization_id:
                    validation_errors.append(company_row.row_number)
                    invalid_rows.add(company_row.row_number)
                    continue

            try:
                InputValidator.validate_company_name(company_row.name)
                InputValidator.validate_website_url(company_row.website)
            except ValidationError:
                validation_errors.append(company_row.row_number)
                invalid_rows.add(company_row.row_number)

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
                        error="Import cancelled due to validation errors",
                    )
                    for company in companies
                ],
            )

        for company_row in companies:
            if company_row.row_number in invalid_rows:
                results.append(
                    CompanyCSVImportResult(
                        row_number=company_row.row_number,
                        success=False,
                        name=company_row.name,
                        error="Validation error",
                    )
                )
                continue

            try:
                company = self.create_company(
                    name=company_row.name,
                    website=company_row.website,
                    owner_id=owner_id,
                    owner_username=owner_username,
                    organization_id=organization_id,
                )

                results.append(
                    CompanyCSVImportResult(
                        row_number=company_row.row_number,
                        success=True,
                        company_id=company.id,
                        name=company.name,
                        error=None,
                    )
                )
                successful += 1

            except Exception as e:
                logger.error(f"Failed to create company from CSV row {company_row.row_number}: {str(e)}")
                results.append(
                    CompanyCSVImportResult(
                        row_number=company_row.row_number, success=False, name=company_row.name, error=str(e)
                    )
                )

        return CompanyCSVImportResponse(
            total_rows=len(companies), successful=successful, failed=len(companies) - successful, results=results
        )

    def refresh_company(self, company_id: int) -> Company:
        """Reset all tasks and restart analysis.

        Args:
            company_id: ID of the company to refresh

        Returns:
            Company instance with refreshed task states
        """
        logger.info("Refreshing company tasks", extra={"company_id": company_id})

        company = self.get_company(company_id)
        if not company:
            raise ResourceNotFoundError("Company not found", details={"company_id": company_id})

        # Reset all tasks to PENDING
        for task in company.tasks:
            task.status = TaskStatus.PENDING
            task.error = None
            task.error_details = None

        self.db.commit()

        # Launch full analysis in background
        self._launch_analysis(company, company.owner_id)

        return company

    def get_company_tasks(self, company_id: int) -> list[Task]:
        """Get all tasks for a company."""
        company = self.get_company(company_id)
        if not company:
            return []
        return company.tasks
