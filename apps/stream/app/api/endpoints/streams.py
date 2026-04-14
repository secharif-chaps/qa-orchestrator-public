"""Stream CRUD + dispatch endpoints — all require Internal JWT auth."""

from typing import NoReturn

from fastapi import APIRouter, Depends, HTTPException, Query

from app.core.auth import (
    InternalTokenPayload,
    OrganizationContext,
    get_current_user,
    get_user_organization,
)
from app.core.dependencies import get_dispatch_service, get_stream_service
from app.schemas.delivery import DeliveryRead
from app.schemas.pagination import PaginatedResponse, create_pagination_meta
from app.schemas.stream import (
    DispatchResponse,
    StreamCreate,
    StreamRead,
    StreamStatusUpdate,
    StreamUpdate,
    TestConnectionRequest,
    TestConnectionResponse,
)
from app.services.dispatch_service import DispatchService
from app.services.stream_service import StreamService, StreamServiceError

router = APIRouter(tags=["Streams"])


def _handle_service_error(e: StreamServiceError) -> NoReturn:
    raise HTTPException(status_code=e.status_code, detail=e.message)


# --- Folder-scoped endpoints ---


@router.get(
    "/folders/{folder_id}/streams",
    response_model=PaginatedResponse[StreamRead],
)
def list_streams(
    folder_id: str,
    page: int = Query(default=1, ge=1),
    per_page: int = Query(default=10, ge=1, le=100),
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.read"])),
    service: StreamService = Depends(get_stream_service),
):
    """List streams in a folder (paginated).

    Requires stream.read role for access.
    """
    offset = (page - 1) * per_page
    streams, total = service.list_streams(org.org_id, folder_id, offset, per_page)
    return PaginatedResponse(
        data=[StreamRead.model_validate(s) for s in streams],
        meta=create_pagination_meta(total, page, per_page),
    )


@router.post(
    "/folders/{folder_id}/streams",
    response_model=StreamRead,
    status_code=201,
)
def create_stream(
    folder_id: str,
    data: StreamCreate,
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    service: StreamService = Depends(get_stream_service),
):
    """Create a stream in a folder.

    Requires stream.write role for access.
    """
    try:
        stream = service.create_stream(
            data=data,
            folder_id=folder_id,
            organization_id=org.org_id,
            owner_id=org.user_id,
            owner_username=org.username,
        )
        return StreamRead.model_validate(stream)
    except StreamServiceError as e:
        _handle_service_error(e)


# --- Stream-scoped endpoints ---


@router.get(
    "/streams/{stream_id}",
    response_model=StreamRead,
)
def get_stream(
    stream_id: int,
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.read"])),
    service: StreamService = Depends(get_stream_service),
):
    """Get a stream by ID.

    Requires stream.read role for access.
    """
    try:
        stream = service.get_stream(stream_id, org.org_id)
        return StreamRead.model_validate(stream)
    except StreamServiceError as e:
        _handle_service_error(e)


@router.put(
    "/streams/{stream_id}",
    response_model=StreamRead,
)
def update_stream(
    stream_id: int,
    data: StreamUpdate,
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    service: StreamService = Depends(get_stream_service),
):
    """Update a stream.

    Requires stream.write role for access.
    """
    try:
        stream = service.update_stream(stream_id, data, org.org_id)
        return StreamRead.model_validate(stream)
    except StreamServiceError as e:
        _handle_service_error(e)


@router.delete(
    "/streams/{stream_id}",
    status_code=204,
)
def delete_stream(
    stream_id: int,
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    service: StreamService = Depends(get_stream_service),
):
    """Delete a stream (cascade deletes deliveries).

    Requires stream.write role for access.
    """
    try:
        service.delete_stream(stream_id, org.org_id)
    except StreamServiceError as e:
        _handle_service_error(e)


@router.patch(
    "/streams/{stream_id}/status",
    response_model=StreamRead,
)
def update_stream_status(
    stream_id: int,
    data: StreamStatusUpdate,
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    service: StreamService = Depends(get_stream_service),
):
    """Transition a stream's status.

    Allowed transitions: active->paused, active->archived, paused->active, paused->archived.
    Archived is terminal.

    Requires stream.write role for access.
    """
    try:
        stream = service.update_status(stream_id, data.status, org.org_id)
        return StreamRead.model_validate(stream)
    except StreamServiceError as e:
        _handle_service_error(e)


@router.get(
    "/streams/{stream_id}/deliveries",
    response_model=PaginatedResponse[DeliveryRead],
)
def list_deliveries(
    stream_id: int,
    page: int = Query(default=1, ge=1),
    per_page: int = Query(default=10, ge=1, le=100),
    org: OrganizationContext = Depends(get_user_organization),
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.read"])),
    service: StreamService = Depends(get_stream_service),
):
    """List delivery history for a stream (paginated).

    Requires stream.read role for access.
    """
    try:
        offset = (page - 1) * per_page
        deliveries, total = service.list_deliveries(stream_id, org.org_id, offset, per_page)
        return PaginatedResponse(
            data=[DeliveryRead.model_validate(d) for d in deliveries],
            meta=create_pagination_meta(total, page, per_page),
        )
    except StreamServiceError as e:
        _handle_service_error(e)


# --- Dispatch endpoints ---


@router.post(
    "/streams/{stream_id}/dispatch",
    response_model=DispatchResponse,
)
async def dispatch_stream(
    stream_id: int,
    org: OrganizationContext = Depends(get_user_organization),
    user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    dispatch_svc: DispatchService = Depends(get_dispatch_service),
    stream_svc: StreamService = Depends(get_stream_service),
):
    """Manually dispatch all pending deliveries for a stream.

    Requires stream.write role for access.
    """
    try:
        stream_svc.get_stream(stream_id, org.org_id)
    except StreamServiceError as e:
        _handle_service_error(e)

    dispatched, failed = await dispatch_svc.dispatch_pending_for_stream(
        stream_id=stream_id,
        org_id=org.org_id,
    )
    return DispatchResponse(dispatched_count=dispatched, failed_count=failed)


@router.post(
    "/test-connection",
    response_model=TestConnectionResponse,
)
async def test_connection(
    data: TestConnectionRequest,
    _user: InternalTokenPayload = Depends(get_current_user(required_roles=["stream.write"])),
    dispatch_svc: DispatchService = Depends(get_dispatch_service),
):
    """Test a channel connection by sending a test message.

    Requires stream.write role for access.
    """
    result = await dispatch_svc.test_connection(
        channel_type=data.channel_type,
        channel_config=data.channel_config,
    )
    return TestConnectionResponse(success=result.success, error=result.error)
