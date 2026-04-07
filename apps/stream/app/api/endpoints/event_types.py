"""Event types endpoint — public, no auth required."""

from fastapi import APIRouter

from app.schemas.event_catalog import EventSourceGroup, get_grouped_catalog

router = APIRouter(tags=["Event Types"])


@router.get("/event-types", response_model=list[EventSourceGroup])
def list_event_types():
    """Return the hardcoded event catalog grouped by source.

    No authentication required — event types are public metadata.
    """
    return get_grouped_catalog()
