"""Celery configuration for Mint application."""
import os

from celery import Celery
from kombu import Queue

from app.core.logging_config import setup_logging

# Initialize logging for Celery workers
LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO")
setup_logging(level=LOG_LEVEL)

# Get broker URL from environment or use default
RABBITMQ_URL = os.getenv("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672//")
MAX_CONCURRENT_WORKFLOWS = int(os.getenv("MAX_CONCURRENT_WORKFLOWS", "10"))

# Create Celery app
# Note: Translation tasks removed - now using FastAPI BackgroundTasks
# See docs/architecture/adr-001-translation-background-tasks.md
celery_app = Celery(
    "mint_tasks",
    broker=RABBITMQ_URL,
    backend='rpc://',  # RabbitMQ as result backend
    include=['app.workers.dify_tasks', 'app.workers.translation_tasks']
)

# Configure Celery
celery_app.conf.update(
    task_serializer='json',
    accept_content=['json'],
    result_serializer='json',
    timezone='UTC',
    enable_utc=True,
    task_acks_late=True,  # Task persistence
    worker_prefetch_multiplier=1,  # Better rate control
    task_track_started=True,
    task_reject_on_worker_lost=True,  # Re-queue on worker failure
    
    # RabbitMQ specific settings for durability
    task_queue_durable=True,
    task_queue_arguments={
        'x-message-ttl': 3600000,  # 1 hour TTL
        'x-max-priority': 10,  # For future priority support
    },
    
    # Define queues
    # Note: translations queue removed - now using FastAPI BackgroundTasks
    task_default_queue='dify_workflows',
    task_queues=(
        Queue('dify_workflows',
              routing_key='workflow.#',
              queue_arguments={'x-max-priority': 10}),
        Queue('translations',
              routing_key='translation.#',
              queue_arguments={'x-max-priority': 5}),
    ),
    
    # Task time limits
    task_soft_time_limit=600,  # 10 minutes soft limit
    task_time_limit=900,  # 15 minutes hard limit
    
    # Result backend settings
    result_expires=3600,  # Results expire after 1 hour
    result_persistent=True,  # Persist results to survive restart

    # Celery Beat schedule for periodic tasks
    beat_schedule={
        'cleanup-stale-tasks': {
            'task': 'cleanup_stale_running_tasks',
            'schedule': 120.0,  # Run every 2 minutes (120 seconds)
        },
    },
)

# Export the app
__all__ = ['celery_app']