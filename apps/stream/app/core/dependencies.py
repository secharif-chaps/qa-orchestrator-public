"""FastAPI dependency factories for service injection."""

from fastapi import Depends
from sqlalchemy.orm import Session

from app.database import get_db
from app.services.dispatch_service import DispatchService
from app.services.event_service import EventService
from app.services.stream_service import StreamService


def get_stream_service(db: Session = Depends(get_db)) -> StreamService:
    return StreamService(db)


def get_event_service(db: Session = Depends(get_db)) -> EventService:
    return EventService(db)


def get_dispatch_service(db: Session = Depends(get_db)) -> DispatchService:
    return DispatchService(db)
