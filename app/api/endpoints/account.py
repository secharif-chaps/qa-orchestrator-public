"""Account self-service endpoints for the current user.

Provides REST endpoints for session management:
- GET  /users/me/sessions           - List active sessions
- DELETE /users/me/sessions/{id}    - Revoke a specific session
- DELETE /users/me/sessions         - Revoke all sessions
"""

from datetime import datetime, timezone
from typing import Optional
from uuid import UUID

import jwt
from fastapi import APIRouter, Depends, HTTPException, Query, Request, status

from app.core.dependencies import get_keycloak_admin
from app.core.logging_config import get_logger
from app.core.organization import OrganizationContext, get_user_organization
from app.schemas.account import SessionListResponse, SessionResponse
from app.services.keycloak_admin import KeycloakAdminService

logger = get_logger(__name__)

router = APIRouter(prefix="/users/me", tags=["account"])


def _get_current_session_id(request: Request) -> Optional[str]:
    """Extract the caller's session ID from the JWT ``sid`` claim.

    This is a best-effort helper: returns *None* when the claim is absent
    or the token cannot be decoded.

    Safety: Signature verification is skipped because the JWT has already
    been validated by the auth middleware (``get_user_organization`` dependency)
    before this function is called.  We only read the ``sid`` claim — no
    trust decision is made on the decoded payload.
    """
    auth_header = request.headers.get("Authorization", "")
    if auth_header.startswith("Bearer "):
        try:
            token = auth_header.split(" ", 1)[1]
            # Signature already verified by auth middleware — only reading sid claim
            payload = jwt.decode(token, options={"verify_signature": False})
            return payload.get("sid")
        except Exception:
            pass
    return None


@router.get("/sessions", response_model=SessionListResponse)
async def get_sessions(
    request: Request,
    org_context: OrganizationContext = Depends(get_user_organization),
    kc_admin: KeycloakAdminService = Depends(get_keycloak_admin),
) -> SessionListResponse:
    """List all active sessions for the current user.

    The caller's own session is marked with ``is_current=True``.

    Args:
        request: FastAPI request (used to read the JWT ``sid`` claim).
        org_context: Organization context with user_id from JWT.

    Returns:
        SessionListResponse containing the session list and current session ID.
    """
    user_id = org_context.user_id
    logger.info(
        "Fetching sessions for current user",
        extra={"user_id": user_id, "username": org_context.username},
    )

    try:
        current_session_id = _get_current_session_id(request)
        kc_sessions = await kc_admin.get_user_sessions(user_id)

        sessions = [
            SessionResponse(
                id=s.get("id", ""),
                ip_address=s.get("ipAddress", "Unknown"),
                started_at=datetime.fromtimestamp(s.get("start", 0) / 1000, tz=timezone.utc),
                last_access=datetime.fromtimestamp(s.get("lastAccess", 0) / 1000, tz=timezone.utc),
                clients=s.get("clients", {}),
                is_current=(s.get("id") == current_session_id) if current_session_id else False,
            )
            for s in kc_sessions
        ]

        logger.info(
            "Successfully fetched user sessions",
            extra={
                "user_id": user_id,
                "session_count": len(sessions),
                "current_session_id": current_session_id,
            },
        )

        return SessionListResponse(
            sessions=sessions,
            current_session_id=current_session_id,
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to fetch user sessions",
            exc_info=True,
            extra={"user_id": user_id, "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to fetch sessions",
        )


@router.delete("/sessions/{session_id}", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_session(
    session_id: UUID,
    request: Request,
    org_context: OrganizationContext = Depends(get_user_organization),
    kc_admin: KeycloakAdminService = Depends(get_keycloak_admin),
) -> None:
    """Revoke a specific session.

    The current session cannot be revoked (use logout instead).

    Args:
        session_id: Keycloak session UUID to revoke.
        request: FastAPI request (used to detect the current session).
        org_context: Organization context with user_id from JWT.

    Raises:
        HTTPException 400: If the caller tries to revoke their own session.
        HTTPException 404: If the session does not belong to the user.
    """
    user_id = org_context.user_id
    sid = str(session_id)
    logger.info(
        "Revoking session for current user",
        extra={"user_id": user_id, "username": org_context.username, "session_id": sid},
    )

    try:
        current_session_id = _get_current_session_id(request)
        if current_session_id and sid == current_session_id:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Cannot revoke current session. Use logout instead.",
            )

        # SECURITY: Keycloak admin API has no user-scoped session revoke —
        # revoke_session() can terminate ANY session in the realm.
        # This ownership check is the ONLY authorization barrier. Do not remove.
        #
        # TOCTOU note: if the session expires between check and revoke,
        # Keycloak returns 404 which revoke_session() surfaces as HTTPException.
        user_sessions = await kc_admin.get_user_sessions(user_id)
        session_ids = {s.get("id") for s in user_sessions}

        if sid not in session_ids:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Session not found",
            )

        await kc_admin.revoke_session(sid)

        logger.info(
            "Successfully revoked session",
            extra={"user_id": user_id, "session_id": sid},
        )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to revoke session",
            exc_info=True,
            extra={"user_id": user_id, "session_id": sid, "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to revoke session",
        )


@router.delete("/sessions", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_all_sessions(
    request: Request,
    keep_current: bool = Query(True, description="Keep the current session active"),
    org_context: OrganizationContext = Depends(get_user_organization),
    kc_admin: KeycloakAdminService = Depends(get_keycloak_admin),
) -> None:
    """Revoke all sessions (sign out from all devices).

    By default the current session is kept alive (``keep_current=True``).
    Set ``keep_current=False`` to revoke every session including the current one,
    which effectively logs the user out everywhere.

    Args:
        request: FastAPI request (used to detect the current session).
        keep_current: When *True*, the caller's own session is preserved.
        org_context: Organization context with user_id from JWT.
    """
    user_id = org_context.user_id
    logger.info(
        "Revoking all sessions for current user",
        extra={"user_id": user_id, "username": org_context.username, "keep_current": keep_current},
    )

    try:
        current_session_id = _get_current_session_id(request) if keep_current else None

        if keep_current and current_session_id:
            # Revoke every session except the current one
            user_sessions = await kc_admin.get_user_sessions(user_id)

            revoked_count = 0
            failed_count = 0
            other_sessions = [s for s in user_sessions if s.get("id") and s.get("id") != current_session_id]

            for session in other_sessions:
                sid = session["id"]
                try:
                    await kc_admin.revoke_session(sid)
                    revoked_count += 1
                except Exception as e:
                    failed_count += 1
                    logger.warning(
                        "Failed to revoke individual session",
                        extra={"session_id": sid, "error": str(e)},
                    )

            if failed_count:
                logger.warning(
                    "Partial failure revoking sessions — some sessions may still be active",
                    extra={
                        "user_id": user_id,
                        "revoked_count": revoked_count,
                        "failed_count": failed_count,
                        "total_targeted": len(other_sessions),
                    },
                )
            else:
                logger.info(
                    "Successfully revoked other sessions",
                    extra={
                        "user_id": user_id,
                        "revoked_count": revoked_count,
                        "kept_session": current_session_id,
                    },
                )
        else:
            # Revoke all sessions including the current one
            await kc_admin.revoke_all_user_sessions(user_id)

            logger.info(
                "Successfully revoked all sessions",
                extra={"user_id": user_id},
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to revoke all sessions",
            exc_info=True,
            extra={"user_id": user_id, "error": str(e)},
        )
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to revoke all sessions",
        )
