"""Database-based concurrency manager for Dify workflows."""
import time
import logging
from datetime import datetime, timedelta
from sqlalchemy.orm import Session
from app.models.task import Task, TaskStatus
from app.core.config import settings

logger = logging.getLogger(__name__)


class DifyConcurrencyManager:
    """Manages concurrent Dify workflow execution using database state"""
    
    def __init__(self, db: Session, max_concurrent: int = None):
        self.db = db
        self.max_concurrent = max_concurrent or int(settings.MAX_CONCURRENT_WORKFLOWS)
    
    def can_start_workflow(self) -> bool:
        """Check if we can start a new workflow based on RUNNING tasks in database"""
        try:
            running_count = self.db.query(Task).filter(Task.status == TaskStatus.RUNNING).count()
            can_start = running_count < self.max_concurrent
            
            if not can_start:
                logger.info(f"Max concurrent workflows reached: {running_count}/{self.max_concurrent} running")
            
            return can_start
        except Exception as e:
            logger.error(f"Error checking workflow concurrency: {e}")
            # In case of DB error, be conservative and don't start
            return False
    
    def get_running_count(self) -> int:
        """Get current count of running workflows"""
        try:
            return self.db.query(Task).filter(Task.status == TaskStatus.RUNNING).count()
        except Exception as e:
            logger.error(f"Error getting running workflow count: {e}")
            return 0
    
    def wait_for_available_slot(self, task_id: int, max_wait_time: int = 300, check_interval: int = 5) -> bool:
        """
        Wait for an available workflow slot
        
        Args:
            task_id: Task ID for logging
            max_wait_time: Maximum time to wait in seconds (default: 5 minutes)
            check_interval: How often to check for availability in seconds
            
        Returns:
            True if slot becomes available, False if timeout
        """
        start_time = time.time()
        
        while not self.can_start_workflow():
            elapsed = time.time() - start_time
            
            if elapsed > max_wait_time:
                logger.error(f"Timeout waiting for workflow slot for task {task_id} after {elapsed:.1f}s")
                return False
            
            running_count = self.get_running_count()
            logger.info(f"Task {task_id} waiting for workflow slot... ({running_count}/{self.max_concurrent} running, waited {elapsed:.1f}s)")
            
            time.sleep(check_interval)
        
        return True
    
    def cleanup_stuck_tasks(self, stuck_threshold_minutes: int = 30):
        """
        Clean up tasks that have been RUNNING for too long (safety mechanism)
        
        Args:
            stuck_threshold_minutes: Consider tasks stuck after this many minutes
        """
        try:
            cutoff_time = datetime.utcnow() - timedelta(minutes=stuck_threshold_minutes)
            
            stuck_tasks = self.db.query(Task).filter(
                Task.status == TaskStatus.RUNNING,
                Task.updated_at < cutoff_time
            ).all()
            
            if stuck_tasks:
                logger.warning(f"Found {len(stuck_tasks)} potentially stuck tasks (running > {stuck_threshold_minutes}min)")
                
                for task in stuck_tasks:
                    logger.warning(f"Task {task.id} ({task.type.value}) has been running since {task.updated_at}")
                    # Optionally mark as ERROR or leave for manual investigation
                    # task.status = TaskStatus.ERROR
                    # task.error = f"Task stuck in RUNNING state for over {stuck_threshold_minutes} minutes"
                
                # self.db.commit()  # Uncomment if auto-cleanup is desired
            
        except Exception as e:
            logger.error(f"Error during stuck task cleanup: {e}")
    
    def get_status_summary(self) -> dict:
        """Get summary of task statuses for monitoring"""
        try:
            status_counts = {}
            for status in TaskStatus:
                count = self.db.query(Task).filter(Task.status == status).count()
                status_counts[status.value] = count
            
            return {
                "status_counts": status_counts,
                "max_concurrent": self.max_concurrent,
                "can_start_new": self.can_start_workflow()
            }
        except Exception as e:
            logger.error(f"Error getting status summary: {e}")
            return {"error": str(e)}