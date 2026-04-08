"""Internal API endpoints for service-to-service communication.

These endpoints use internal JWT authentication and are not exposed to end users.
Used by backend services (monolith) to call global-service functionality.
"""

from typing import Literal
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Path, Query, status
from pydantic import BaseModel, Field
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.dependencies import get_token_manager
from app.core.internal_jwt import InternalTokenPayload, get_internal_token
from app.core.logging_config import get_logger
from app.database import get_global_db
from app.models.organization import ModuleName, ReferenceType
from app.proxy.registry import ModuleName as ProxyModuleName
from app.proxy.routes import get_module_registry
from app.schemas.folder import CompanyFolderInfoResponse
from app.schemas.token import ConsumeTokensRequest, ConsumeTokensResponse, TokenTransactionRead
from app.services.folder import FolderService
from app.services.token_manager import (
    InsufficientTokensException,
    ModuleNotEnabledException,
    TokenManager,
)

logger = get_logger(__name__)

router = APIRouter(prefix="/internal", tags=["internal"])


# ── Registry announce models ──


class AnnounceRequest(BaseModel):
    """Payload sent by a backend service to announce a schema change."""

    openapi_hash: str = Field(
        min_length=64,
        max_length=64,
        pattern=r"^[a-f0-9]{64}$",
        description="SHA-256 hex digest of the OpenAPI schema JSON",
    )


class AnnounceResponse(BaseModel):
    """Response from the announce endpoint."""

    module: str
    action: Literal["rediscovered", "unchanged", "error"]


@router.post(
    "/registry/announce/{module_name}",
    response_model=AnnounceResponse,
    tags=["internal"],
)
async def announce_module(
    module_name: str,
    request: AnnounceRequest,
    token_payload: InternalTokenPayload = Depends(get_internal_token),
) -> AnnounceResponse:
    """Handle a self-announce from a backend service.

    Compares the provided hash with the stored hash. If they differ,
    triggers rediscovery of the module's OpenAPI schema.

    Args:
        module_name: Name of the backend module (e.g. "screen")
        request: Announce payload containing the new openapi_hash
        token_payload: Verified internal JWT payload

    Returns:
        AnnounceResponse with the action taken

    Raises:
        HTTPException 400: If module_name is not a valid module
        HTTPException 401: If internal JWT is invalid or missing
    """
    # Validate module name
    try:
        name = ProxyModuleName(module_name)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"message": f"Unknown module: {module_name}"},
        )

    registry = get_module_registry()
    if not registry:
        logger.warning("Announce received but registry not initialized", extra={"module_name": module_name})
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Registry not initialized",
        )

    current_hash = registry.get_module_hash(name)

    if current_hash == request.openapi_hash:
        logger.info(
            "Announce: hash unchanged",
            extra={"module_name": module_name, "hash": request.openapi_hash},
        )
        return AnnounceResponse(module=module_name, action="unchanged")

    logger.info(
        "Announce: hash changed, rediscovering",
        extra={
            "module_name": module_name,
            "old_hash": current_hash,
            "new_hash": request.openapi_hash,
        },
    )
    try:
        await registry.rediscover_module(name)
        return AnnounceResponse(module=module_name, action="rediscovered")
    except Exception as e:
        logger.error(
            "Announce: rediscovery failed",
            extra={"module_name": module_name, "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=f"Rediscovery failed for module {module_name}",
        )


# ── Organization token and folder endpoints ──


@router.post(
    "/organizations/{organization_id}/tokens/consume",
    response_model=ConsumeTokensResponse,
    status_code=status.HTTP_200_OK,
)
async def consume_organization_tokens(
    organization_id: UUID = Path(
        ...,
        description="Organization UUID",
        examples=["550e8400-e29b-41d4-a716-446655440000"],
    ),
    request: ConsumeTokensRequest = ...,
    token_payload: InternalTokenPayload = Depends(get_internal_token),
    token_manager: TokenManager = Depends(get_token_manager),
) -> ConsumeTokensResponse:
    """Consume tokens from organization balance (internal API).

    This endpoint is called by backend services to consume tokens for operations
    like company creation. Uses internal JWT authentication for service-to-service
    communication.

    Security:
    - Requires valid internal JWT token in Authorization header
    - Validates that organization_id in path matches org_id in JWT token

    Args:
        organization_id: Keycloak organization UUID from URL path
        request: Token consumption request with amount, module, reference details
        token_payload: Verified internal JWT payload (injected by dependency)
        token_manager: TokenManager service instance

    Returns:
        ConsumeTokensResponse with success status, new balance, and transaction details

    Raises:
        HTTPException 401: If internal JWT is invalid or missing
        HTTPException 403: If org_id doesn't match JWT or module not enabled
        HTTPException 402: If insufficient tokens
        HTTPException 422: If organization_id is not a valid UUID format
    """
    org_id_str = str(organization_id)

    # Verify that the organization_id in the path matches the org_id in the JWT
    if org_id_str != token_payload.org_id:
        logger.warning(
            "Organization ID mismatch in internal token consumption",
            extra={
                "path_org_id": org_id_str,
                "token_org_id": token_payload.org_id,
                "user_id": token_payload.sub,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Organization ID in path does not match token organization"},
        )

    # Parse enum values from strings
    try:
        module_name = ModuleName(request.module_name)
        reference_type = ReferenceType(request.reference_type)
    except ValueError as e:
        logger.warning(
            "Invalid enum value in token consumption request",
            extra={
                "module_name": request.module_name,
                "reference_type": request.reference_type,
                "error": str(e),
            },
        )
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={"message": f"Invalid enum value: {str(e)}"},
        )

    logger.info(
        "Internal token consumption request",
        extra={
            "organization_id": org_id_str,
            "amount": request.amount,
            "module_name": request.module_name,
            "reference_type": request.reference_type,
            "user_id": token_payload.sub,
        },
    )

    try:
        # Consume tokens via token manager
        org = await token_manager.consume_tokens(
            org_id=org_id_str,
            amount=request.amount,
            module_name=module_name,
            reference_type=reference_type,
            reference_id=request.reference_id,
            user_id=token_payload.sub,
        )

        # Get the most recent transaction for this operation
        transactions = await token_manager.get_transaction_history(
            org_id=org_id_str,
            reference_type=reference_type,
            page=1,
            size=1,
        )

        transaction = transactions[0] if transactions else None

        logger.info(
            "Tokens consumed successfully",
            extra={
                "organization_id": org_id_str,
                "amount": request.amount,
                "new_balance": org.token_balance,
                "transaction_id": transaction.id if transaction else None,
                "user_id": token_payload.sub,
            },
        )

        return ConsumeTokensResponse(
            success=True,
            balance=org.token_balance,
            transaction=TokenTransactionRead.model_validate(transaction) if transaction else None,
        )

    except ModuleNotEnabledException as e:
        logger.warning(
            "Module not enabled for token consumption",
            extra={
                "organization_id": org_id_str,
                "module_name": request.module_name,
                "user_id": token_payload.sub,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": str(e)},
        )

    except InsufficientTokensException as e:
        logger.warning(
            "Insufficient tokens for consumption",
            extra={
                "organization_id": org_id_str,
                "requested_amount": request.amount,
                "current_balance": e.current_balance,
                "user_id": token_payload.sub,
            },
        )
        raise HTTPException(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail={
                "message": str(e),
                "current_balance": e.current_balance,
                "required_tokens": request.amount,
            },
        )


@router.get(
    "/organizations/{organization_id}/folders/accessible-company-ids",
    response_model=list[int],
    status_code=status.HTTP_200_OK,
)
async def get_accessible_company_ids(
    organization_id: UUID = Path(
        ...,
        description="Organization UUID",
    ),
    token_payload: InternalTokenPayload = Depends(get_internal_token),
    db: AsyncSession = Depends(get_global_db),
) -> list[int]:
    """Get company IDs accessible to a user via folder sharing (internal API).

    Returns the list of company IDs from all folders the user owns or
    has been shared with in the specified organization.

    Security:
    - Requires valid internal JWT token in Authorization header
    - Validates that organization_id in path matches org_id in JWT token
    """
    org_id_str = str(organization_id)

    if org_id_str != token_payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Organization ID in path does not match token organization"},
        )

    company_ids = await FolderService.get_accessible_company_ids(
        db=db,
        user_id=token_payload.sub,
        organization_id=org_id_str,
        username=token_payload.username,
    )

    logger.debug(
        "Accessible company IDs retrieved",
        extra={
            "organization_id": org_id_str,
            "user_id": token_payload.sub,
            "count": len(company_ids),
        },
    )

    return sorted(company_ids)


@router.get(
    "/organizations/{organization_id}/folders/company-access/{company_id}",
    response_model=bool,
    status_code=status.HTTP_200_OK,
)
async def check_company_access(
    organization_id: UUID = Path(..., description="Organization UUID"),
    company_id: int = Path(..., description="Company ID to check access for"),
    user_roles: str = Query("", description="Comma-separated list of user roles"),
    token_payload: InternalTokenPayload = Depends(get_internal_token),
    db: AsyncSession = Depends(get_global_db),
) -> bool:
    """Check if a user has access to a company via folder sharing (internal API).

    A user has access if they are a manager or the company belongs to a folder
    the user owns or has been shared with.

    Security:
    - Requires valid internal JWT token in Authorization header
    - Validates that organization_id in path matches org_id in JWT token
    """
    org_id_str = str(organization_id)

    if org_id_str != token_payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Organization ID in path does not match token organization"},
        )

    roles = [r.strip() for r in user_roles.split(",") if r.strip()] if user_roles else []

    has_access = await FolderService.user_has_company_access(
        db=db,
        company_id=company_id,
        user_id=token_payload.sub,
        organization_id=org_id_str,
        username=token_payload.username,
        user_roles=roles,
    )

    logger.debug(
        "Company access check completed",
        extra={
            "organization_id": org_id_str,
            "user_id": token_payload.sub,
            "company_id": company_id,
            "has_access": has_access,
        },
    )

    return has_access


@router.get(
    "/organizations/{organization_id}/folders/company/{company_id}/folder-info",
    response_model=CompanyFolderInfoResponse | None,
    status_code=status.HTTP_200_OK,
)
async def get_company_folder_info(
    organization_id: UUID = Path(..., description="Organization UUID"),
    company_id: int = Path(..., description="Company ID"),
    token_payload: InternalTokenPayload = Depends(get_internal_token),
    db: AsyncSession = Depends(get_global_db),
) -> CompanyFolderInfoResponse | None:
    """Get folder info (id + name) for a company (internal API).

    Returns the folder_id and folder_name of the first folder containing
    this company in the specified organization, or null if not found.

    Security:
    - Requires valid internal JWT token in Authorization header
    - Validates that organization_id in path matches org_id in JWT token
    """
    org_id_str = str(organization_id)

    if org_id_str != token_payload.org_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": "Organization ID in path does not match token organization"},
        )

    folders = await FolderService.get_folders_for_item(
        db=db,
        item_id=str(company_id),
        item_type="company",
        organization_id=org_id_str,
    )

    if not folders:
        return None

    return CompanyFolderInfoResponse(
        folder_id=str(folders[0].id),
        folder_name=folders[0].name,
    )
