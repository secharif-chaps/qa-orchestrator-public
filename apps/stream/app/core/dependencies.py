"""FastAPI dependency factories for service injection."""

from __future__ import annotations

from functools import lru_cache

from fastapi import Depends
from sqlalchemy.orm import Session

from app.database import get_db
from app.services.dispatch_service import DispatchService
from app.services.event_service import EventService
from app.services.stream_service import StreamService
from app.services.token_client import TokenClient, create_token_client


@lru_cache(maxsize=1)
def _get_token_client() -> TokenClient | None:
    """Singleton token client (created once, reused across requests)."""
    return create_token_client()


def get_stream_service(db: Session = Depends(get_db)) -> StreamService:
    return StreamService(db)


def get_event_service(db: Session = Depends(get_db)) -> EventService:
    return EventService(db)


def get_dispatch_service(
    db: Session = Depends(get_db),
    token_client: TokenClient | None = Depends(_get_token_client),
) -> DispatchService:
    return DispatchService(db, token_client=token_client)
