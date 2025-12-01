"""Task Event Manager for SSE (Server-Sent Events) real-time updates.

This service manages SSE connections per user and broadcasts task status updates
to connected clients in real-time, eliminating the need for frontend polling.

Architecture:
- Each authenticated user can have multiple SSE connections (multiple browser tabs)
- When a task completes (via webhook), we broadcast to all connections for that user
- Events are queued per connection using asyncio.Queue for non-blocking delivery
"""

import asyncio
import json
from collections import defaultdict

from app.core.logging_config import get_logger

logger = get_logger(__name__)


class TaskEventManager:
    """Manages SSE connections for real-time task updates per user.

    This class maintains a registry of connected users and their event queues.
    When a task status changes, events are pushed to all active connections
    for the affected user.

    Attributes:
        _connections: Dict mapping user_id to list of asyncio.Queue instances
    """

    def __init__(self) -> None:
        """Initialize the TaskEventManager with empty connection registry."""
        # user_id -> list of asyncio.Queue (one per browser tab/connection)
        self._connections: dict[str, list[asyncio.Queue]] = defaultdict(list)
        logger.info("TaskEventManager initialized")

    def subscribe(self, user_id: str) -> asyncio.Queue:
        """Subscribe a user to task events.

        Creates a new asyncio.Queue for receiving events and registers it
        under the user's ID. Multiple queues per user are supported for
        users with multiple browser tabs open.

        Args:
            user_id: Keycloak user UUID (from JWT sub claim)

        Returns:
            asyncio.Queue instance for receiving task events
        """
        queue: asyncio.Queue = asyncio.Queue()
        self._connections[user_id].append(queue)
        connection_count = len(self._connections[user_id])
        logger.info(
            "User subscribed to task events",
            extra={"user_id": user_id, "connection_count": connection_count}
        )
        return queue

    def unsubscribe(self, user_id: str, queue: asyncio.Queue) -> None:
        """Remove a user's subscription.

        Called when an SSE connection closes (client disconnect, page close, etc.).
        Removes the specific queue from the user's connection list.

        Args:
            user_id: Keycloak user UUID
            queue: The specific queue to remove
        """
        if user_id in self._connections:
            try:
                self._connections[user_id].remove(queue)
                if not self._connections[user_id]:
                    del self._connections[user_id]
                logger.info(
                    "User unsubscribed from task events",
                    extra={
                        "user_id": user_id,
                        "remaining_connections": len(self._connections.get(user_id, []))
                    }
                )
            except ValueError:
                # Queue was already removed
                pass

    def get_connection_count(self, user_id: str | None = None) -> int:
        """Get the number of active connections.

        Args:
            user_id: Optional user ID to get count for specific user.
                     If None, returns total connections across all users.

        Returns:
            Number of active SSE connections
        """
        if user_id:
            return len(self._connections.get(user_id, []))
        return sum(len(queues) for queues in self._connections.values())

    async def broadcast_to_user(self, user_id: str, event: dict) -> int:
        """Broadcast an event to all connections for a specific user.

        Args:
            user_id: Keycloak user UUID to broadcast to
            event: Event dict to send (will be JSON serialized by SSE endpoint)

        Returns:
            Number of connections the event was sent to
        """
        if user_id not in self._connections:
            return 0

        sent_count = 0
        for queue in self._connections[user_id]:
            try:
                await queue.put(event)
                sent_count += 1
            except Exception as e:
                logger.warning(
                    "Failed to queue event for user",
                    extra={"user_id": user_id, "error": str(e)}
                )

        if sent_count > 0:
            logger.debug(
                "Event broadcast to user",
                extra={
                    "user_id": user_id,
                    "event_type": event.get("type"),
                    "connections": sent_count
                }
            )

        return sent_count

    async def broadcast_task_update(
        self,
        user_id: str,
        company_id: int,
        task_id: int,
        status: str,
        task_type: str,
        error: str | None = None
    ) -> int:
        """Broadcast a task status update event.

        Called when a task's status changes (typically from webhook callbacks).
        The frontend uses this to invalidate cache and refresh task data.

        Args:
            user_id: Keycloak user UUID of the company owner
            company_id: ID of the company the task belongs to
            task_id: ID of the task that was updated
            status: New task status (pending, running, succeeded, error)
            task_type: Type of task (profile, digital, timeline, etc.)
            error: Error message if status is 'error'

        Returns:
            Number of connections the event was sent to
        """
        event = {
            "type": "task_update",
            "data": {
                "company_id": company_id,
                "task_id": task_id,
                "status": status,
                "task_type": task_type,
                "error": error
            }
        }

        sent_count = await self.broadcast_to_user(user_id, event)

        logger.info(
            "Task update broadcast",
            extra={
                "user_id": user_id,
                "company_id": company_id,
                "task_id": task_id,
                "status": status,
                "task_type": task_type,
                "connections": sent_count
            }
        )

        return sent_count

    async def broadcast_all_tasks_completed(
        self,
        user_id: str,
        company_id: int,
        company_name: str,
        folder_id: str | None,
        success_count: int,
        error_count: int
    ) -> int:
        """Broadcast when all tasks for a company are complete.

        This triggers a toast notification in the frontend to inform the user
        that their company search has finished, even if they're on another page.

        Args:
            user_id: Keycloak user UUID of the company owner
            company_id: ID of the company whose tasks completed
            company_name: Name of the company (for toast message)
            folder_id: UUID of the folder containing the company (for navigation)
            success_count: Number of tasks that succeeded
            error_count: Number of tasks that failed

        Returns:
            Number of connections the event was sent to
        """
        event = {
            "type": "all_tasks_completed",
            "data": {
                "company_id": company_id,
                "company_name": company_name,
                "folder_id": folder_id,
                "success_count": success_count,
                "error_count": error_count
            }
        }

        sent_count = await self.broadcast_to_user(user_id, event)

        logger.info(
            "All tasks completed broadcast",
            extra={
                "user_id": user_id,
                "company_id": company_id,
                "company_name": company_name,
                "success_count": success_count,
                "error_count": error_count,
                "connections": sent_count
            }
        )

        return sent_count


# Global singleton instance
# This is shared across all FastAPI requests in the same process
task_event_manager = TaskEventManager()
