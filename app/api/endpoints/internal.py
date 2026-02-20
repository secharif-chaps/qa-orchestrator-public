"""Internal API endpoints for service-to-service communication.

These endpoints use internal JWT authentication and are not exposed to end users.
Used by backend services (monolith) to call global-service functionality.
"""

from uuid import UUID
from fastapi import APIRouter, Depends, Path, status, HTTPException

from app.core.dependencies import get_token_manager
from app.core.internal_jwt import get_internal_token, InternalTokenPayload
from app.core.logging_config import get_logger
from app.models.organization import ModuleName, ReferenceType
from app.schemas.token import ConsumeTokensRequest, ConsumeTokensResponse, TokenTransactionRead
from app.services.token_manager import (
    TokenManager,
    InsufficientTokensException,
    ModuleNotEnabledException,
)

logger = get_logger(__name__)

router = APIRouter(prefix="/internal/organizations", tags=["internal"])


@router.post(
    "/{org_id}/tokens/consume",
    response_model=ConsumeTokensResponse,
    status_code=status.HTTP_200_OK,
)
async def consume_organization_tokens(
    org_id: UUID = Path(
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
    - Validates that org_id in path matches org_id in JWT token

    Args:
        org_id: Keycloak organization UUID from URL path
        request: Token consumption request with amount, module, reference details
        token_payload: Verified internal JWT payload (injected by dependency)
        token_manager: TokenManager service instance

    Returns:
        ConsumeTokensResponse with success status, new balance, and transaction details

    Raises:
        HTTPException 401: If internal JWT is invalid or missing
        HTTPException 403: If org_id doesn't match JWT or module not enabled
        HTTPException 402: If insufficient tokens
        HTTPException 422: If org_id is not a valid UUID format
    """
    org_id_str = str(org_id)

    # Verify that the org_id in the path matches the org_id in the JWT
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
            detail={
                "message": "Organization ID in path does not match token organization"
            },
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
