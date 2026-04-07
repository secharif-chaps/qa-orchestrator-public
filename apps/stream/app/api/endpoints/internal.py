"""Internal endpoints for service-to-service communication.

Mounted with include_in_schema=False so they don't appear in OpenAPI
and the gateway's ModuleRegistry.discover_all() won't see them.
"""

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException, Query, status

from app.core.auth import InternalTokenPayload, verify_internal_jwt
from app.core.dependencies import get_event_service
from app.schemas.event import EventIngest, EventRead
from app.services.event_service import EventService

router = APIRouter(tags=["Internal"])


@router.post("/events/ingest", response_model=EventRead, status_code=201)
def ingest_event(
    data: EventIngest,
    organization_id: str = Query(..., description="Organization ID for the event"),
    payload: InternalTokenPayload = Depends(verify_internal_jwt),
    service: EventService = Depends(get_event_service),
):
    """Ingest an event and create pending deliveries for matching streams.

    Called by other services (e.g., Screen) when domain events occur.
    Requires Internal JWT authentication.
    """
    if organization_id != payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Organization ID mismatch",
        )
    event = service.ingest_event(data, organization_id)
    return EventRead.model_validate(event)


@router.delete("/events/cleanup", status_code=200)
def cleanup_events(
    before: datetime = Query(..., description="Soft-delete events created before this ISO datetime"),
    organization_id: str = Query(..., description="Organization ID to scope cleanup"),
    payload: InternalTokenPayload = Depends(verify_internal_jwt),
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
