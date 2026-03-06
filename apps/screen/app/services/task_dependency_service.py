"""
Service for managing task dependencies and execution order
"""
import logging

from sqlalchemy.orm import Session

from app.models.task import Task, TaskDependency, TaskStatus

logger = logging.getLogger(__name__)


class TaskDependencyService:
    """Manages task dependencies and determines execution order"""

    def __init__(self, db: Session):
        self.db = db

    def create_dependency(self, task_id: int, depends_on_task_id: int) -> TaskDependency:
        """Create a dependency relationship between two tasks"""
        dependency = TaskDependency(
            task_id=task_id,
            depends_on_task_id=depends_on_task_id
        )
        self.db.add(dependency)
        self.db.commit()
        self.db.refresh(dependency)

        # Update task status to BLOCKED if prerequisite is not complete
        task = self.db.query(Task).filter(Task.id == task_id).first()
        prerequisite = self.db.query(Task).filter(Task.id == depends_on_task_id).first()

        if prerequisite and prerequisite.status != TaskStatus.SUCCEEDED:
            task.status = TaskStatus.BLOCKED
            self.db.commit()

        logger.info(f"📌 Created dependency: Task {task_id} depends on Task {depends_on_task_id}")
        return dependency

    def get_dependencies(self, task_id: int) -> list[Task]:
        """Get all prerequisite tasks for a given task"""
        dependencies = (
            self.db.query(TaskDependency)
            .filter(TaskDependency.task_id == task_id)
            .all()
        )

        prerequisite_ids = [dep.depends_on_task_id for dep in dependencies]
        if not prerequisite_ids:
            return []

        prerequisites = (
            self.db.query(Task)
            .filter(Task.id.in_(prerequisite_ids))
            .all()
        )

        return prerequisites

    def get_dependent_tasks(self, task_id: int) -> list[Task]:
        """Get all tasks that depend on this task"""
        dependencies = (
            self.db.query(TaskDependency)
            .filter(TaskDependency.depends_on_task_id == task_id)
            .all()
        )

        dependent_ids = [dep.task_id for dep in dependencies]
        if not dependent_ids:
            return []

        dependents = (
            self.db.query(Task)
            .filter(Task.id.in_(dependent_ids))
            .all()
        )

        return dependents

    def can_task_run(self, task_id: int) -> bool:
        """Check if all prerequisites for a task are satisfied"""
        prerequisites = self.get_dependencies(task_id)

        if not prerequisites:
            return True  # No dependencies, can run

        # All prerequisites must be SUCCEEDED
        all_succeeded = all(prereq.status == TaskStatus.SUCCEEDED for prereq in prerequisites)

        if all_succeeded:
            logger.debug(f"✅ Task {task_id} can run - all prerequisites satisfied")
        else:
            logger.debug(f"⏳ Task {task_id} cannot run - waiting for prerequisites")

        return all_succeeded

    def get_ready_tasks(self, company_id: int) -> list[Task]:
        """Get all tasks for a company that are ready to run (prerequisites satisfied)"""
        tasks = (
            self.db.query(Task)
            .filter(Task.company_id == company_id)
            .filter(Task.status.in_([TaskStatus.PENDING, TaskStatus.BLOCKED]))
            .all()
        )

        ready_tasks = []
        for task in tasks:
            if self.can_task_run(task.id):
                ready_tasks.append(task)

        return ready_tasks

    def unblock_dependent_tasks(self, completed_task_id: int) -> list[Task]:
        """
        When a task completes, check its dependents and unblock any that are ready
        Returns list of tasks that were unblocked
        """
        dependent_tasks = self.get_dependent_tasks(completed_task_id)

        if not dependent_tasks:
            logger.info(f"ℹ️  Task {completed_task_id} has no dependent tasks")
            return []

        unblocked_tasks = []

        for task in dependent_tasks:
            if task.status == TaskStatus.BLOCKED and self.can_task_run(task.id):
                task.status = TaskStatus.PENDING
                unblocked_tasks.append(task)
                logger.info(f"🔓 Unblocked task {task.id} ({task.type.value}) - prerequisites satisfied")

        if unblocked_tasks:
            self.db.commit()
            logger.info(f"🔓 Unblocked {len(unblocked_tasks)} tasks total")

        return unblocked_tasks

    def mark_dependents_as_failed(self, failed_task_id: int, error_message: str) -> list[Task]:
        """
        When a prerequisite task fails, mark all dependent tasks as failed
        Returns list of tasks that were marked as failed
        """
        dependent_tasks = self.get_dependent_tasks(failed_task_id)

        if not dependent_tasks:
            return []

        failed_task = self.db.query(Task).filter(Task.id == failed_task_id).first()
        failed_tasks = []

        for task in dependent_tasks:
            if task.status in [TaskStatus.BLOCKED, TaskStatus.PENDING]:
                task.status = TaskStatus.ERROR
                task.error = f"Prerequisite task '{failed_task.type.value}' failed: {error_message}"
                failed_tasks.append(task)
                logger.error(f"🚫 Marked task {task.id} ({task.type.value}) as failed due to prerequisite failure")

        if failed_tasks:
            self.db.commit()
            logger.error(f"🚫 Marked {len(failed_tasks)} dependent tasks as failed")

        return failed_tasks
