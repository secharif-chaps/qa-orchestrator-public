"""Dify workflow task worker with dynamic concurrency control."""
from app.core.celery_app import celery_app, MAX_CONCURRENT_WORKFLOWS
from celery import Task
from app.database import SessionLocal
from app.models.task import Task as TaskModel, TaskStatus
from app.models.company import Company
from app.core.concurrency import DifyConcurrencyManager
import logging
import asyncio

logger = logging.getLogger(__name__)


class DifyWorkflowTask(Task):
    """Base task class for Dify workflow execution."""
    autoretry_for = ()  # No automatic retry for now
    max_retries = 0  # Disabled for now, will implement smart retry logic later
    default_retry_delay = 60

    def on_failure(self, exc, task_id, args, kwargs, einfo):
        """Handle task failure - update DB status."""
        task_db_id = kwargs.get('task_id')
        if not task_db_id:
            logger.error(f"No task_id provided in kwargs for failed Celery task {task_id}")
            return
            
        try:
            with SessionLocal() as db:
                task = db.query(TaskModel).filter(TaskModel.id == task_db_id).first()
                if task:
                    task.status = TaskStatus.ERROR
                    task.error = str(exc)
                    db.commit()
                    logger.error(f"Task {task_db_id} marked as ERROR in database: {exc}")
                else:
                    logger.error(f"Task {task_db_id} not found in database during failure handling")
        except Exception as e:
            logger.error(f"Failed to update task {task_db_id} status on failure: {e}")


def run_async_task(coro):
    """Helper to run async code in sync context."""
    loop = None
    try:
        loop = asyncio.get_event_loop()
        if loop.is_running():
            # If loop is already running, create a new one
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)
            return loop.run_until_complete(coro)
        else:
            return loop.run_until_complete(coro)
    except RuntimeError:
        # No event loop, create one
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        return loop.run_until_complete(coro)
    finally:
        if loop and not loop.is_running():
            loop.close()


@celery_app.task(
    bind=True,
    base=DifyWorkflowTask,
    name='execute_dify_workflow',
    queue='dify_workflows'
)
def execute_dify_workflow(
    self,
    task_id: int,
    company_id: int,
    task_type: str,
    api_key: str,
    success_callback: str,
    error_callback: str
):
    """Execute Dify workflow with dynamic concurrency control."""
    logger.info(f"Starting workflow execution: Task {task_id}, Type: {task_type}, Company: {company_id}")

    # Import here to avoid circular import
    from app.services.dify import DifyService

    with SessionLocal() as db:
        # Initialize concurrency manager
        concurrency_manager = DifyConcurrencyManager(db)
        
        # Query company and task directly
        company = db.query(Company).filter(Company.id == company_id).first()
        task = db.query(TaskModel).filter(TaskModel.id == task_id).first()
        
        if not task:
            logger.error(f"Task {task_id} not found in database")
            return {"status": "error", "message": f"Task {task_id} not found"}
            
        if not company:
            logger.error(f"Company {company_id} not found in database")
            task.status = TaskStatus.ERROR
            task.error = f"Company {company_id} not found"
            db.commit()
            return {"status": "error", "message": f"Company {company_id} not found"}
        
        # Wait for available workflow slot (dynamic concurrency control)
        logger.info(f"Task {task_id}: Checking workflow concurrency...")
        if not concurrency_manager.wait_for_available_slot(task_id, max_wait_time=300):
            # Timeout waiting for slot
            task.status = TaskStatus.ERROR
            task.error = "Timeout waiting for available workflow slot"
            db.commit()
            return {"status": "error", "message": "Timeout waiting for workflow slot"}
        
        # Now we have a slot - update task to RUNNING
        logger.info(f"Task {task_id}: Workflow slot available, starting execution...")
        task.status = TaskStatus.RUNNING
        db.commit()
        
        running_count = concurrency_manager.get_running_count()
        logger.info(f"Task {task_id}: Started workflow ({running_count}/{concurrency_manager.max_concurrent} running)")
        
        try:
            # Callback URLs are passed as parameters from the backend
            # No need to construct them here - eliminates BACKEND_BASE_URL dependency in worker

            # Debug logging for callback URLs
            logger.info(
                f"🔗 URL DEBUG [dify_tasks.execute_dify_workflow] Task {task_type}",
                extra={
                    "task_id": task_id,
                    "task_type": task_type,
                    "success_callback": success_callback,
                    "error_callback": error_callback,
                    "note": "URLs passed as parameters from backend",
                }
            )

            logger.info(f"Triggering Dify workflow for task {task_id} ({task_type}) - Company: {company.name}")

            # Create Dify service and trigger workflow (pass db session for knowledge data access)
            dify_service = DifyService(db=db)

            # Execute the async Dify workflow trigger with provided api_key
            result = run_async_task(
                dify_service.run_workflow(
                    task_type=task_type,
                    company_name=company.name,
                    website=company.website,
                    success_callback=success_callback,
                    error_callback=error_callback,
                    task_id=task.id,
                    company_id=company.id,
                    response_mode="blocking",  # blocking mode for async execution
                    api_key=api_key
                )
            )
            
            logger.info(f"✅ Workflow triggered for task {task_id}: {result}")
            
            # Task remains in RUNNING state - will be updated via webhook callback
            return {"status": "success", "message": f"Workflow triggered for task {task_id}", "result": result}
            
        except Exception as e:
            logger.error(f"Failed to trigger workflow for task {task_id}: {e}")
            # Revert task status to allow retry or manual intervention
            task.status = TaskStatus.ERROR
            task.error = str(e)
            db.commit()
            
            # Log current concurrency state for debugging
            running_count = concurrency_manager.get_running_count()
            logger.error(f"Task {task_id} failed, running workflows: {running_count}/{concurrency_manager.max_concurrent}")
            
            # Re-raise to trigger on_failure handler
            raise


@celery_app.task(name='check_queue_health')
def check_queue_health() -> dict:
    """Health check task to monitor queue status."""
    return {
        "status": "healthy",
        "max_concurrent_workflows": MAX_CONCURRENT_WORKFLOWS,
        "worker": "active"
    }


@celery_app.task(name='cleanup_stale_running_tasks')
def cleanup_stale_running_tasks() -> dict:
    """
    Periodic task to clean up tasks stuck in RUNNING state.
    Runs every 2 minutes and marks tasks as ERROR if they've been running for more than 10 minutes.
    """
    from datetime import datetime, timedelta

    logger.info("🧹 Running stale task cleanup...")

    with SessionLocal() as db:
        # Find tasks that have been in RUNNING state for more than 10 minutes
        timeout_threshold = datetime.utcnow() - timedelta(minutes=10)

        stale_tasks = db.query(TaskModel).filter(
            TaskModel.status == TaskStatus.RUNNING,
            TaskModel.updated_at < timeout_threshold
        ).all()

        if not stale_tasks:
            logger.info("✅ No stale tasks found")
            return {"cleaned_count": 0, "status": "success"}

        logger.warning(f"🚨 Found {len(stale_tasks)} stale tasks (running > 10 minutes)")

        cleaned_count = 0
        for task in stale_tasks:
            logger.warning(
                f"🚨 Marking task {task.id} as ERROR - "
                f"Type: {task.type.value}, Company: {task.company_id}, "
                f"Running since: {task.updated_at}"
            )
            task.status = TaskStatus.ERROR
            task.error = "Task timeout - stuck in running state for more than 10 minutes"
            cleaned_count += 1

        db.commit()

        logger.info(f"✅ Cleaned up {cleaned_count} stale tasks")

        # Log current concurrency status after cleanup
        from app.core.concurrency import DifyConcurrencyManager
        concurrency_manager = DifyConcurrencyManager(db)
        running_count = concurrency_manager.get_running_count()
        logger.info(f"📊 After cleanup: {running_count}/{concurrency_manager.max_concurrent} workflows running")

        return {
            "cleaned_count": cleaned_count,
            "status": "success",
            "running_workflows": running_count
        }