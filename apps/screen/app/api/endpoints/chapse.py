"""Chapse Chatbot API Endpoints.

Provides endpoints for:
- Streaming chat with company context
- Conversation management (list, get, delete, rename)
- Company context management per conversation
"""

from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi.responses import StreamingResponse
from sqlalchemy.orm import Session

from app.core.auth import AuthenticatedUser, get_current_user
from app.core.dependencies import get_db
from app.core.exceptions import AuthorizationError, ExternalServiceError, ResourceNotFoundError
from app.core.logging_config import get_logger
from app.core.organization_context import OrganizationContext, get_user_organization
from app.core.rate_limit import check_chapse_chat_rate_limit
from app.schemas.chapse import (
    ChapseChatRequest,
    ContextResponse,
    ConversationDetailResponse,
    ConversationsResponse,
    RenameRequest,
    RenameResponse,
    UpdateContextRequest,
)
from app.services.chat_service import ChatService

router = APIRouter(prefix="/chapse", tags=["chapse"])
logger = get_logger(__name__)


def get_chapse_service(db: Session = Depends(get_db)) -> ChatService:
    """Dependency to get ChatService instance."""
    return ChatService(db)


# =============================================================================
# Chat Endpoint
# =============================================================================


@router.post(
    "/chat",
    openapi_extra={"x-permissions": []},
    summary="Send a chat message (streaming)",
    description=(
        "Send a message to the Chapse AI assistant and receive a streamed SSE response. "
        "Optionally provide company IDs to give the assistant context about specific companies. "
        "Rate-limited per user."
    ),
    responses={
        400: {"description": "Invalid query or company IDs"},
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "User does not belong to any organization"},
        429: {"description": "Chat rate limit exceeded — retry after the `Retry-After` header value"},
    },
)
async def chat(
    request: ChapseChatRequest,
    user: AuthenticatedUser = Depends(check_chapse_chat_rate_limit),
    org_context: OrganizationContext = Depends(get_user_organization),
    service: ChatService = Depends(get_chapse_service),
):
    """Send a chat message and stream the response.

    Requires authentication. Streams SSE events.

    Args:
        request: Chat request with query, optional conversation_id, and company_ids
        user: Authenticated user from Keycloak
        org_context: User's organization context
        service: ChatService instance

    Returns:
        StreamingResponse with SSE events
    """
    logger.info(
        "Chat request",
        extra={
            "user_id": user.sub,
            "conversation_id": request.conversation_id,
            "company_ids": request.company_ids,
            "query_length": len(request.query),
        },
    )

    # Get user's first name for the AI to address them personally
    username = user.given_name or user.preferred_username or "User"

    try:
        return StreamingResponse(
            service.stream_chat(
                query=request.query,
                user_id=user.sub,
                organization_id=org_context.organization_id,
                conversation_id=request.conversation_id,
                company_ids=request.company_ids,
                username=username,
                messages=[m.model_dump() for m in request.messages] if request.messages else None,
            ),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
                "X-Accel-Buffering": "no",  # Disable nginx buffering
            },
        )
    except AuthorizationError as e:
        logger.warning(f"Authorization error in chat: {e}")
        raise HTTPException(status_code=403, detail=str(e))
    except ExternalServiceError as e:
        logger.error(f"Service error in chat: {e}")
        raise HTTPException(status_code=500, detail="Chat service error")
    except ValueError as e:
        logger.warning(f"Validation error in chat: {e}")
        raise HTTPException(status_code=400, detail=str(e))


# =============================================================================
# Conversation Management Endpoints
# =============================================================================


@router.get(
    "/conversations",
    response_model=ConversationsResponse,
    openapi_extra={"x-permissions": []},
    summary="List conversations",
    description=(
        "Return the authenticated user's conversations ordered by most recent, "
        "with cursor-based pagination. Each conversation includes its company context."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "User does not belong to any organization"},
    },
)
async def list_conversations(
    limit: int = Query(default=20, ge=1, le=100),
    last_id: str | None = Query(default=None),
    user: AuthenticatedUser = Depends(get_current_user()),
    service: ChatService = Depends(get_chapse_service),
):
    """List user's conversations with company context.

    Requires authentication. Returns conversations sorted by most recent.

    Args:
        limit: Number of conversations to return (1-100)
        last_id: Pagination cursor for next page
        user: Authenticated user from Keycloak
        service: ChatService instance

    Returns:
        ConversationsResponse with data, has_more, and limit
    """
    logger.info("List conversations", extra={"user_id": user.sub, "limit": limit})

    try:
        result = await service.list_conversations(user_id=user.sub, limit=limit, last_id=last_id)
        return ConversationsResponse(**result)
    except ExternalServiceError as e:
        logger.error(f"Service error listing conversations: {e}")
        raise HTTPException(status_code=500, detail="Failed to list conversations")


@router.get(
    "/conversations/{conversation_id}",
    response_model=ConversationDetailResponse,
    openapi_extra={"x-permissions": []},
    summary="Get conversation detail",
    description=(
        "Retrieve a single conversation with its full message history and company context. "
        "Messages are paginated via cursor-based pagination."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        404: {"description": "Conversation not found or belongs to another user"},
    },
)
async def get_conversation(
    conversation_id: str,
    limit: int = Query(default=50, ge=1, le=100),
    first_id: str | None = Query(default=None),
    user: AuthenticatedUser = Depends(get_current_user()),
    service: ChatService = Depends(get_chapse_service),
):
    """Get conversation detail with messages.

    Requires authentication. Returns conversation with messages and context.

    Args:
        conversation_id: Conversation ID
        limit: Number of messages to return (1-100)
        first_id: Pagination cursor for messages
        user: Authenticated user from Keycloak
        service: ChatService instance

    Returns:
        ConversationDetailResponse with messages and context
    """
    logger.info("Get conversation", extra={"user_id": user.sub, "conversation_id": conversation_id})

    try:
        result = await service.get_conversation(
            conversation_id=conversation_id, user_id=user.sub, limit=limit, first_id=first_id
        )
        return ConversationDetailResponse(**result)
    except ResourceNotFoundError:
        raise HTTPException(status_code=404, detail="Conversation not found")
    except ExternalServiceError as e:
        logger.error(f"Service error getting conversation: {e}")
        raise HTTPException(status_code=500, detail="Failed to get conversation")


@router.delete(
    "/conversations/{conversation_id}",
    status_code=204,
    openapi_extra={"x-permissions": []},
    summary="Delete a conversation",
    description=(
        "Permanently delete a conversation along with its messages and company context. "
        "Returns 204 No Content on success."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "Conversation belongs to another user"},
        404: {"description": "Conversation not found"},
    },
)
async def delete_conversation(
    conversation_id: str,
    user: AuthenticatedUser = Depends(get_current_user()),
    service: ChatService = Depends(get_chapse_service),
):
    """Delete a conversation.

    Requires authentication. Deletes conversation and removes context.

    Args:
        conversation_id: Conversation ID
        user: Authenticated user from Keycloak
        service: ChatService instance

    Returns:
        204 No Content on success
    """
    logger.info("Delete conversation", extra={"user_id": user.sub, "conversation_id": conversation_id})

    try:
        await service.delete_conversation(conversation_id=conversation_id, user_id=user.sub)
    except ResourceNotFoundError:
        raise HTTPException(status_code=404, detail="Conversation not found")
    except AuthorizationError as e:
        raise HTTPException(status_code=403, detail=str(e))
    except ExternalServiceError as e:
        logger.error(f"Service error deleting conversation: {e}")
        raise HTTPException(status_code=500, detail="Failed to delete conversation")


@router.post(
    "/conversations/{conversation_id}/rename",
    response_model=RenameResponse,
    openapi_extra={"x-permissions": []},
    summary="Rename a conversation",
    description=(
        "Set a custom name for a conversation or auto-generate one from its content. "
        "Provide `name` for a manual rename or set `auto_generate: true`."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        404: {"description": "Conversation not found"},
    },
)
async def rename_conversation(
    conversation_id: str,
    request: RenameRequest,
    user: AuthenticatedUser = Depends(get_current_user()),
    service: ChatService = Depends(get_chapse_service),
):
    """Rename a conversation.

    Requires authentication. Can set manual name or auto-generate.

    Args:
        conversation_id: Conversation ID
        request: Rename request with name or auto_generate flag
        user: Authenticated user from Keycloak
        service: ChatService instance

    Returns:
        RenameResponse with id and new name
    """
    logger.info(
        "Rename conversation",
        extra={"user_id": user.sub, "conversation_id": conversation_id, "auto_generate": request.auto_generate},
    )

    try:
        result = await service.rename_conversation(
            conversation_id=conversation_id, user_id=user.sub, name=request.name, auto_generate=request.auto_generate
        )
        return RenameResponse(**result)
    except ResourceNotFoundError:
        raise HTTPException(status_code=404, detail="Conversation not found")
    except ExternalServiceError as e:
        logger.error(f"Service error renaming conversation: {e}")
        raise HTTPException(status_code=500, detail="Failed to rename conversation")


# =============================================================================
# Context Management Endpoints
# =============================================================================


@router.get(
    "/conversations/{conversation_id}/context",
    response_model=ContextResponse,
    openapi_extra={"x-permissions": []},
    summary="Get conversation company context",
    description=(
        "Return the list of companies attached as context to a conversation. "
        "Each company is returned with its ID and name summary."
    ),
    responses={
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "User does not belong to any organization"},
        404: {"description": "Conversation not found"},
    },
)
async def get_context(
    conversation_id: str,
    user: AuthenticatedUser = Depends(get_current_user()),
    service: ChatService = Depends(get_chapse_service),
):
    """Get company context for a conversation.

    Requires authentication. Returns company IDs and summaries.

    Args:
        conversation_id: Conversation ID
        user: Authenticated user from Keycloak
        service: ChatService instance

    Returns:
        ContextResponse with company_ids and companies
    """
    logger.info("Get context", extra={"user_id": user.sub, "conversation_id": conversation_id})

    result = service.get_context(conversation_id=conversation_id, user_id=user.sub)
    return ContextResponse(**result)


@router.put(
    "/conversations/{conversation_id}/context",
    response_model=ContextResponse,
    openapi_extra={"x-permissions": []},
    summary="Update conversation company context",
    description=(
        "Replace the company context for a conversation (maximum 3 companies). "
        "All provided company IDs are validated against the user's organization."
    ),
    responses={
        400: {"description": "More than 3 companies or invalid company IDs"},
        401: {"description": "Missing or invalid authentication token"},
        403: {"description": "One or more companies do not belong to the user's organization"},
    },
)
async def update_context(
    conversation_id: str,
    request: UpdateContextRequest,
    user: AuthenticatedUser = Depends(get_current_user()),
    org_context: OrganizationContext = Depends(get_user_organization),
    service: ChatService = Depends(get_chapse_service),
):
    """Update company context for a conversation.

    Requires authentication. Validates companies belong to organization.

    Args:
        conversation_id: Conversation ID
        request: Update request with company_ids (max 3)
        user: Authenticated user from Keycloak
        org_context: User's organization context
        service: ChatService instance

    Returns:
        ContextResponse with updated company_ids and companies
    """
    logger.info(
        "Update context",
        extra={"user_id": user.sub, "conversation_id": conversation_id, "company_ids": request.company_ids},
    )

    try:
        result = service.update_context(
            conversation_id=conversation_id,
            user_id=user.sub,
            organization_id=org_context.organization_id,
            company_ids=request.company_ids,
        )
        return ContextResponse(**result)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except AuthorizationError as e:
        raise HTTPException(status_code=403, detail=str(e))
