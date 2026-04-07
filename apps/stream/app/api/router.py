from fastapi import APIRouter

from app.api.endpoints.event_types import router as event_types_router
from app.api.endpoints.streams import router as streams_router

api_router = APIRouter()

api_router.include_router(event_types_router)
api_router.include_router(streams_router)
