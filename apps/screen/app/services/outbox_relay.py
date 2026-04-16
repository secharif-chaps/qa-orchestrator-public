"""Outbox relay: polls unpublished events and POSTs them to Stream.

Runs as an asyncio background task within the FastAPI lifespan.
Uses its own DB session per polling cycle for isolation.
"""

import asyncio

import httpx

from app.core.config import settings
from app.core.internal_jwt import create_internal_token
from app.core.logging_config import get_logger
from app.database import SessionLocal
from app.services.outbox_service import OutboxService

logger = get_logger(__name__)

POLL_INTERVAL_SECONDS = 5
RELAY_TIMEOUT_SECONDS = 10

# System identity for outbox relay JWT
SYSTEM_USER_ID = "system"
SYSTEM_USERNAME = "screen-outbox-relay"
SYSTEM_ORG_ID = "system"


class OutboxRelay:
    """Async background relay that polls outbox and forwards events to Stream.

    Usage:
        relay = OutboxRelay()
        await relay.start()
        # ... application runs ...
        await relay.stop()
    """

    def __init__(self) -> None:
        self._task: asyncio.Task | None = None

    async def start(self) -> None:
        """Start the relay polling loop as a background task."""
        self._task = asyncio.create_task(self._poll_loop())

    async def stop(self) -> None:
        """Cancel the polling task and wait for clean shutdown."""
        if self._task:
            self._task.cancel()
            try:
                await self._task
            except asyncio.CancelledError:
                pass
            self._task = None

    async def _poll_loop(self) -> None:
        """Main polling loop: fetch pending events, relay, mark published."""
        logger.info("Outbox relay polling loop started")
        while True:
            try:
                await self._relay_cycle()
            except asyncio.CancelledError:
                logger.info("Outbox relay polling loop cancelled")
                raise
            except Exception:
                logger.exception("Unexpected error in outbox relay cycle")
            await asyncio.sleep(POLL_INTERVAL_SECONDS)

    async def _relay_cycle(self) -> None:
        """Single relay cycle: read pending events, POST to Stream, mark published."""
        db = SessionLocal()
        try:
            outbox = OutboxService(db)
            pending = outbox.get_pending(limit=100)
            if not pending:
                return

            logger.info("Relaying outbox events", extra={"count": len(pending)})

            published_ids: list[int] = []
            async with httpx.AsyncClient(timeout=RELAY_TIMEOUT_SECONDS) as client:
                for event in pending:
                    success = await self._relay_event(client, event)
                    if success:
                        published_ids.append(event.id)

            if published_ids:
                outbox.mark_published(published_ids)
        finally:
            db.close()

    async def _relay_event(self, client: httpx.AsyncClient, event) -> bool:
        """POST a single event to the Stream ingest endpoint.

        Args:
            client: Shared httpx async client
            event: OutboxEvent instance

        Returns:
            True if successfully relayed, False on failure (will be retried)
        """
        payload = {
            "event_type": event.event_type,
            "folder_id": event.folder_id or "",
            "payload": event.payload,
            "entity_id": event.aggregate_id,
            "entity_type": event.aggregate_type,
            "summary": event.summary,
        }

        token = create_internal_token(
            user_id=SYSTEM_USER_ID,
            username=SYSTEM_USERNAME,
            org_id=event.organization_id,
        )
        headers = {
            "Authorization": f"Internal {token}",
            "Content-Type": "application/json",
        }

        try:
            response = await client.post(
                settings.STREAM_INGEST_URL,
                json=payload,
                headers=headers,
                params={"organization_id": event.organization_id},
            )
            if response.status_code < 300:
                logger.debug(
                    "Outbox event relayed",
                    extra={"event_id": event.id, "event_type": event.event_type},
                )
                return True

            logger.warning(
                "Stream ingest returned error",
                extra={
                    "event_id": event.id,
                    "event_type": event.event_type,
                    "status_code": response.status_code,
                    "response": response.text[:200],
                },
            )
            return False

        except httpx.RequestError as e:
            logger.warning(
                "Failed to relay outbox event",
                extra={
                    "event_id": event.id,
                    "event_type": event.event_type,
                    "error": str(e),
                },
            )
            return False
