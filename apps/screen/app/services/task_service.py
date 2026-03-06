"""
Service for task management and lazy cleanup of stale tasks.

This service provides lazy evaluation for tasks stuck in RUNNING state,
cleaning them up on read operations without requiring Celery Beat.
"""

from datetime import datetime, timedelta, timezone
from typing import Optional

from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.logging_config import get_logger
from app.models.task import Task, TaskStatus
from app.services.task_dependency_service import TaskDependencyService

logger = get_logger(__name__)

# Default timeout in minutes - tasks running longer than this are considered stale
DEFAULT_TASK_TIMEOUT_MINUTES = 5


class TaskService:
    """Service for task management with lazy stale task cleanup."""

    def __init__(self, db: Session):
        self.db = db
        self.timeout_minutes = getattr(
            settings, "TASK_TIMEOUT_MINUTES", DEFAULT_TASK_TIMEOUT_MINUTES
        )
        self.dependency_service = TaskDependencyService(db)

    def cleanup_stale_tasks_for_company(self, company_id: int) -> int:
        """
        Lazily clean up tasks stuck in RUNNING state for a specific company.

        Called on read operations to ensure accurate task status.
        Also marks dependent tasks as failed when a prerequisite task times out.

        Args:
            company_id: The company ID to clean up tasks for

        Returns:
            The number of tasks cleaned up
        """
        timeout_threshold = datetime.now(timezone.utc) - timedelta(
            minutes=self.timeout_minutes
        )

        stale_tasks = (
            self.db.query(Task)
            .filter(
                Task.company_id == company_id,
                Task.status == TaskStatus.RUNNING,
                Task.updated_at < timeout_threshold,
            )
            .all()
        )

        if not stale_tasks:
            return 0

        cleaned_count = 0
        error_message = (
            f"Task timeout - stuck in running state for more than "
            f"{self.timeout_minutes} minutes"
        )

        for task in stale_tasks:
            logger.warning(
                f"🚨 Lazy cleanup: Task {task.id} ({task.type.value}) stuck in RUNNING "
                f"for company {company_id} since {task.updated_at}"
            )
            task.status = TaskStatus.ERROR
            task.error = error_message
            cleaned_count += 1

            # If this was a prerequisite task, mark dependent tasks as failed
            if task.is_prerequisite:
                failed_dependents = self.dependency_service.mark_dependents_as_failed(
                    task.id, error_message
                )
                if failed_dependents:
                    logger.warning(
                        f"🚫 Marked {len(failed_dependents)} dependent tasks as failed "
                        f"due to prerequisite task {task.id} timeout"
                    )

        self.db.commit()
        logger.info(f"✅ Cleaned up {cleaned_count} stale tasks for company {company_id}")

        return cleaned_count

    def cleanup_all_stale_tasks(self) -> int:
        """
        Clean up all stale tasks across all companies.

        Useful for admin endpoints or global cleanup operations.

        Returns:
            The number of tasks cleaned up
        """
        timeout_threshold = datetime.now(timezone.utc) - timedelta(
            minutes=self.timeout_minutes
        )

        stale_tasks = (
            self.db.query(Task)
            .filter(
                Task.status == TaskStatus.RUNNING,
                Task.updated_at < timeout_threshold,
            )
            .all()
        )

        if not stale_tasks:
            logger.debug("✅ No stale tasks found during global cleanup")
            return 0

        logger.warning(
            f"🚨 Found {len(stale_tasks)} stale tasks globally "
            f"(running > {self.timeout_minutes} minutes)"
        )

        cleaned_count = 0
        error_message = (
            f"Task timeout - stuck in running state for more than "
            f"{self.timeout_minutes} minutes"
        )

        for task in stale_tasks:
            logger.warning(
                f"🚨 Global cleanup: Task {task.id} ({task.type.value}) "
                f"company_id={task.company_id}, org_id={task.organization_id}, "
                f"running since {task.updated_at}"
            )
            task.status = TaskStatus.ERROR
            task.error = error_message
            cleaned_count += 1

            # If this was a prerequisite task, mark dependent tasks as failed
            if task.is_prerequisite:
                failed_dependents = self.dependency_service.mark_dependents_as_failed(
                    task.id, error_message
                )
                if failed_dependents:
                    logger.warning(
                        f"🚫 Marked {len(failed_dependents)} dependent tasks as failed "
                        f"due to prerequisite task {task.id} timeout"
                    )

        self.db.commit()
        logger.info(f"✅ Global cleanup completed: {cleaned_count} stale tasks cleaned")

        return cleaned_count

    def get_stale_task_count(self) -> int:
        """
        Get the count of stale tasks without cleaning them up.

        Useful for monitoring and stats endpoints.

        Returns:
            The number of stale tasks
        """
        timeout_threshold = datetime.now(timezone.utc) - timedelta(
            minutes=self.timeout_minutes
        )

        return (
            self.db.query(Task)
            .filter(
                Task.status == TaskStatus.RUNNING,
                Task.updated_at < timeout_threshold,
            )
            .count()
        )

    def get_stale_tasks(self, limit: Optional[int] = None) -> list[Task]:
        """
        Get stale tasks without cleaning them up.

        Useful for displaying stale tasks in admin UI before cleanup.

        Args:
            limit: Maximum number of tasks to return

        Returns:
            List of stale tasks
        """
        timeout_threshold = datetime.now(timezone.utc) - timedelta(
            minutes=self.timeout_minutes
        )

        query = self.db.query(Task).filter(
            Task.status == TaskStatus.RUNNING,
            Task.updated_at < timeout_threshold,
        )

        if limit:
            query = query.limit(limit)

        return query.all()
