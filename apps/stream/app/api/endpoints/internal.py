"""Internal endpoints for service-to-service communication.

Mounted with include_in_schema=False so they don't appear in OpenAPI
and the gateway's ModuleRegistry.discover_all() won't see them.
"""

from datetime import datetime

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Query, status

from app.core.auth import AuthenticatedUser, verify_internal_jwt
from app.core.dependencies import get_dispatch_service, get_event_service
from app.core.logging_config import get_logger
from app.schemas.event import EventIngest, EventRead
from app.services.dispatch_service import DispatchService
from app.services.event_service import EventService

logger = get_logger(__name__)

router = APIRouter(tags=["Internal"])


async def _background_dispatch(
    dispatch_service: DispatchService,
    event_id: int,
    org_id: str,
) -> None:
    """Background task to dispatch pending deliveries for a newly ingested event."""
    try:
        count = await dispatch_service.dispatch_pending_for_event(
            event_id=event_id,
            org_id=org_id,
        )
        logger.info(
            "Background dispatch completed",
            extra={"event_id": event_id, "dispatched": count},
        )
    except Exception as e:
        logger.error(
            "Background dispatch failed",
            extra={"event_id": event_id, "error": str(e)},
        )


@router.post("/events/ingest", response_model=EventRead, status_code=201)
def ingest_event(
    data: EventIngest,
    background_tasks: BackgroundTasks,
    organization_id: str = Query(..., description="Organization ID for the event"),
    payload: AuthenticatedUser = Depends(verify_internal_jwt),
    service: EventService = Depends(get_event_service),
    dispatch_service: DispatchService = Depends(get_dispatch_service),
):
    """Ingest an event and create pending deliveries for matching streams.

    Called by other services (e.g., Screen) when domain events occur.
    Requires Internal JWT authentication.

    After ingestion, schedules background dispatch for live-mode streams.
    """
    if organization_id != payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Organization ID mismatch",
        )
    event = service.ingest_event(data, organization_id)

    # Schedule dispatch for live-mode streams in the background
    background_tasks.add_task(
        _background_dispatch,
        dispatch_service=dispatch_service,
        event_id=int(event.id),
        org_id=organization_id,
    )

    return EventRead.model_validate(event)


@router.delete("/events/cleanup", status_code=200)
def cleanup_events(
    before: datetime = Query(..., description="Soft-delete events created before this ISO datetime"),
    organization_id: str = Query(..., description="Organization ID to scope cleanup"),
    payload: AuthenticatedUser = Depends(verify_internal_jwt),
    service: EventService = Depends(get_event_service),
):
    """Soft-delete old events.

    Called by scheduled jobs to clean up stale event data.
    Requires Internal JWT authentication.
    """
    if organization_id != payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Organization ID mismatch",
        )
    count = service.cleanup_events(before, organization_id)
    return {"deleted_count": count}
