#!/usr/bin/env python3
"""
Cleanup script for stuck tasks in running state.
Can be run manually or scheduled via cron.
"""
import argparse
import sys
import os
from datetime import datetime, timedelta
from pathlib import Path

# Add parent directory to path to import app modules
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlalchemy import text
from sqlalchemy.orm import Session
from app.database import SessionLocal
from app.models.task import Task, TaskStatus
from app.core.concurrency import DifyConcurrencyManager
import logging

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def cleanup_stuck_tasks(
    db: Session,
    timeout_minutes: int = 10,
    dry_run: bool = False
) -> tuple[int, list]:
    """
    Find and cleanup tasks stuck in running state.
    
    Args:
        db: Database session
        timeout_minutes: Consider tasks stuck if running for more than this many minutes
        dry_run: If True, only show what would be done without making changes
        
    Returns:
        Tuple of (count of affected tasks, list of task details)
    """
    cutoff_time = datetime.utcnow() - timedelta(minutes=timeout_minutes)
    
    # Find stuck tasks
    stuck_tasks = db.query(Task).filter(
        Task.status == TaskStatus.RUNNING,
        Task.updated_at < cutoff_time
    ).all()
    
    affected_tasks = []
    
    for task in stuck_tasks:
        time_stuck = datetime.utcnow() - task.updated_at
        hours = int(time_stuck.total_seconds() // 3600)
        minutes = int((time_stuck.total_seconds() % 3600) // 60)
        
        task_info = {
            'id': task.id,
            'company_id': task.company_id,
            'type': task.type.value if hasattr(task.type, 'value') else str(task.type),
            'updated_at': task.updated_at.isoformat(),
            'stuck_duration': f"{hours}h {minutes}m"
        }
        affected_tasks.append(task_info)
        
        logger.info(
            f"{'[DRY RUN] Would update' if dry_run else 'Updating'} "
            f"Task {task.id} (type: {task_info['type']}, company: {task.company_id}) - "
            f"stuck for {task_info['stuck_duration']}"
        )
        
        if not dry_run:
            task.status = TaskStatus.ERROR
            task.error = f"Task timeout - stuck in running state for more than {timeout_minutes} minutes"
    
    if not dry_run and affected_tasks:
        try:
            db.commit()
            logger.info(f"Successfully updated {len(affected_tasks)} stuck tasks to ERROR status")
        except Exception as e:
            db.rollback()
            logger.error(f"Failed to update tasks: {e}")
            raise
    
    return len(affected_tasks), affected_tasks


def check_concurrency_status(db: Session) -> dict:
    """Check current concurrency status for additional context."""
    try:
        manager = DifyConcurrencyManager(db)
        running_count = manager.get_running_count()
        max_concurrent = manager.max_concurrent
        
        return {
            'running_workflows': running_count,
            'max_concurrent': max_concurrent,
            'slots_available': max_concurrent - running_count
        }
    except Exception as e:
        logger.warning(f"Could not check concurrency status: {e}")
        return {}


def main():
    parser = argparse.ArgumentParser(
        description='Cleanup tasks stuck in running state',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Dry run - show what would be cleaned up (default 10 minutes)
  python scripts/cleanup_stuck_tasks.py --dry-run
  
  # Clean up tasks stuck for more than 30 minutes
  python scripts/cleanup_stuck_tasks.py --timeout 30
  
  # Clean up with specific timeout and verbose output
  python scripts/cleanup_stuck_tasks.py --timeout 5 --verbose
  
  # Run from Docker container
  docker compose -f docker-compose.dev.yml exec backend python scripts/cleanup_stuck_tasks.py --dry-run
        """
    )
    
    parser.add_argument(
        '--timeout',
        type=int,
        default=10,
        help='Consider tasks stuck if running for more than this many minutes (default: 10)'
    )
    
    parser.add_argument(
        '--dry-run',
        action='store_true',
        help='Show what would be done without making changes'
    )
    
    parser.add_argument(
        '--verbose',
        action='store_true',
        help='Show detailed information about each task'
    )
    
    parser.add_argument(
        '--check-only',
        action='store_true',
        help='Only check for stuck tasks without updating them (similar to dry-run but with less output)'
    )
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    # Create database session
    db = SessionLocal()
    
    try:
        # Check concurrency status first
        concurrency_status = check_concurrency_status(db)
        if concurrency_status:
            logger.info(f"Current concurrency status: {concurrency_status}")
        
        # Perform cleanup
        mode = "DRY RUN" if args.dry_run else "LIVE"
        logger.info(f"Starting stuck task cleanup [{mode}] - Timeout: {args.timeout} minutes")
        
        count, affected_tasks = cleanup_stuck_tasks(
            db=db,
            timeout_minutes=args.timeout,
            dry_run=args.dry_run or args.check_only
        )
        
        if count == 0:
            logger.info("No stuck tasks found")
        else:
            if args.check_only:
                logger.info(f"Found {count} stuck task(s)")
            elif args.dry_run:
                logger.info(f"Would clean up {count} stuck task(s)")
            else:
                logger.info(f"Cleaned up {count} stuck task(s)")
            
            if args.verbose and affected_tasks:
                logger.info("\nAffected tasks:")
                for task in affected_tasks:
                    logger.info(f"  - Task {task['id']}: {task}")
        
        # Show summary statistics
        if not args.check_only:
            all_running = db.query(Task).filter(Task.status == TaskStatus.RUNNING).count()
            all_error = db.query(Task).filter(Task.status == TaskStatus.ERROR).count()
            logger.info(f"\nCurrent task statistics:")
            logger.info(f"  - Running tasks: {all_running}")
            logger.info(f"  - Error tasks: {all_error}")
        
        return 0 if count == 0 or args.dry_run or args.check_only else 1
        
    except Exception as e:
        logger.error(f"Script failed: {e}")
        return 1
    finally:
        db.close()


if __name__ == "__main__":
    sys.exit(main())