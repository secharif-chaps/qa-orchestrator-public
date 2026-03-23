"""Account management endpoints for current user.

This module provides endpoints for users to manage their own account:
- Session management (view, revoke sessions)
- Activity log (view security events)

All endpoints are self-service and require authentication.
"""

from datetime import datetime

from fastapi import APIRouter, Depends, HTTPException, Query, Request, status
from fastapi_keycloak import OIDCUser
from pydantic import BaseModel

from app.core.keycloak import idp
from app.core.logging_config import get_logger
from app.services.keycloak_admin import keycloak_admin_service

router = APIRouter(prefix="/users/me", tags=["account"])
logger = get_logger(__name__)


# Pydantic Schemas


class SessionResponse(BaseModel):
    """Response schema for a single session."""

    id: str
    ip_address: str
    started_at: datetime
    last_access: datetime
    clients: dict[str, str]
    is_current: bool


class SessionListResponse(BaseModel):
    """Response schema for session list."""

    sessions: list[SessionResponse]
    current_session_id: str | None = None


class ActivityEventResponse(BaseModel):
    """Response schema for a single activity event."""

    id: str
    type: str
    display_type: str
    icon: str
    title: str
    description: str
    ip_address: str | None = None
    timestamp: datetime


class ActivityEventsResponse(BaseModel):
    """Response schema for activity events list."""

    events: list[ActivityEventResponse]
    page: int
    size: int
    has_more: bool


# Helper functions


def get_event_display_info(event_type: str) -> dict:
    """Map Keycloak event type to user-friendly display information."""
    event_mapping = {
        "LOGIN": {
            "display_type": "login",
            "icon": "fas fa-sign-in-alt",
            "title": "Successful login",
            "description": "Signed in successfully",
        },
        "LOGIN_ERROR": {
            "display_type": "security",
            "icon": "fas fa-exclamation-triangle",
            "title": "Failed login attempt",
            "description": "Invalid credentials or authentication failed",
        },
        "LOGOUT": {
            "display_type": "login",
            "icon": "fas fa-sign-out-alt",
            "title": "Signed out",
            "description": "Logged out of session",
        },
        "UPDATE_PROFILE": {
            "display_type": "update",
            "icon": "fas fa-user-edit",
            "title": "Profile updated",
            "description": "Account profile was modified",
        },
        "UPDATE_PASSWORD": {
            "display_type": "security",
            "icon": "fas fa-key",
            "title": "Password changed",
            "description": "Account password was updated",
        },
        "UPDATE_EMAIL": {
            "display_type": "update",
            "icon": "fas fa-envelope",
            "title": "Email updated",
            "description": "Account email was changed",
        },
        "UPDATE_TOTP": {
            "display_type": "security",
            "icon": "fas fa-mobile-alt",
            "title": "Two-factor updated",
            "description": "Two-factor authentication was modified",
        },
        "REMOVE_TOTP": {
            "display_type": "security",
            "icon": "fas fa-mobile-alt",
            "title": "Two-factor removed",
            "description": "Two-factor authentication was disabled",
        },
        "REFRESH_TOKEN": {
            "display_type": "login",
            "icon": "fas fa-sync",
            "title": "Session refreshed",
            "description": "Authentication token was refreshed",
        },
        "CODE_TO_TOKEN": {
            "display_type": "login",
            "icon": "fas fa-exchange-alt",
            "title": "Token exchanged",
            "description": "Authorization code exchanged for token",
        },
    }

    return event_mapping.get(
        event_type,
        {
            "display_type": "update",
            "icon": "fas fa-info-circle",
            "title": event_type.replace("_", " ").title(),
            "description": f"Event: {event_type}",
        },
    )


def get_current_session_id(request: Request) -> str | None:
    """Extract current session ID from the request if available.

    The session ID might be in the JWT token or other request context.
    This is a best-effort attempt to identify the current session.
    """
    # Try to get session ID from authorization header's JWT
    # Keycloak tokens often contain 'sid' (session ID) claim
    auth_header = request.headers.get("Authorization", "")
    if auth_header.startswith("Bearer "):
        try:
            import jwt

            token = auth_header.split(" ")[1]
            # Decode without verification just to read claims
            payload = jwt.decode(token, options={"verify_signature": False})
            return payload.get("sid")
        except Exception:
            pass
    return None


# Endpoints


@router.get("/sessions", response_model=SessionListResponse)
async def get_sessions(request: Request, user: OIDCUser = Depends(idp.get_current_user())):
    """Get all active sessions for the current user.

    Returns a list of all sessions associated with the authenticated user,
    including the current session which is marked with is_current=True.

    Returns:
        SessionListResponse with list of sessions and current session ID
    """
    logger.info("Fetching sessions for current user", extra={"user_id": user.sub, "username": user.preferred_username})

    try:
        # Get current session ID from request
        current_session_id = get_current_session_id(request)

        # Fetch sessions from Keycloak
        kc_sessions = await keycloak_admin_service.get_user_sessions(user.sub)

        sessions = []
        for kc_session in kc_sessions:
            session_id = kc_session.get("id", "")

            # Determine if this is the current session
            is_current = session_id == current_session_id if current_session_id else False

            # Parse timestamps (Keycloak returns epoch milliseconds)
            started_at = datetime.fromtimestamp(kc_session.get("start", 0) / 1000)
            last_access = datetime.fromtimestamp(kc_session.get("lastAccess", 0) / 1000)

            sessions.append(
                SessionResponse(
                    id=session_id,
                    ip_address=kc_session.get("ipAddress", "Unknown"),
                    started_at=started_at,
                    last_access=last_access,
                    clients=kc_session.get("clients", {}),
                    is_current=is_current,
                )
            )

        logger.info(
            "Successfully fetched user sessions",
            extra={"user_id": user.sub, "session_count": len(sessions), "current_session_id": current_session_id},
        )

        return SessionListResponse(sessions=sessions, current_session_id=current_session_id)

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to fetch user sessions", exc_info=True, extra={"user_id": user.sub, "error": str(e)})
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to fetch sessions")


@router.delete("/sessions/{session_id}", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_session(session_id: str, request: Request, user: OIDCUser = Depends(idp.get_current_user())):
    """Revoke a specific session.

    Terminates the specified session, logging out that device/browser.
    Cannot revoke the current session (use logout instead).

    Args:
        session_id: The session ID to revoke

    Raises:
        HTTPException 400: If trying to revoke current session
        HTTPException 404: If session not found
    """
    logger.info(
        "Revoking session for current user",
        extra={"user_id": user.sub, "username": user.preferred_username, "session_id": session_id},
    )

    try:
        # Check if trying to revoke current session
        current_session_id = get_current_session_id(request)
        if current_session_id and session_id == current_session_id:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST, detail="Cannot revoke current session. Use logout instead."
            )

        # Verify the session belongs to this user before revoking
        user_sessions = await keycloak_admin_service.get_user_sessions(user.sub)
        session_ids = [s.get("id") for s in user_sessions]

        if session_id not in session_ids:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Session not found")

        # Revoke the session
        success = await keycloak_admin_service.revoke_session(session_id)

        if not success:
            raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to revoke session")

        logger.info("Successfully revoked session", extra={"user_id": user.sub, "session_id": session_id})

    except HTTPException:
        raise
    except Exception as e:
        logger.error(
            "Failed to revoke session",
            exc_info=True,
            extra={"user_id": user.sub, "session_id": session_id, "error": str(e)},
        )
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to revoke session")


@router.delete("/sessions", status_code=status.HTTP_204_NO_CONTENT)
async def revoke_all_sessions(
    request: Request,
    keep_current: bool = Query(True, description="Keep the current session active"),
    user: OIDCUser = Depends(idp.get_current_user()),
):
    """Revoke all sessions (sign out all devices).

    Terminates all sessions for the current user. By default, keeps the
    current session active (keep_current=True).

    Args:
        keep_current: If True, keeps the current session active.
                     If False, revokes all sessions including current (will log out).

    Note:
        If keep_current=False, the user will be logged out of the current session too.
    """
    logger.info(
        "Revoking all sessions for current user",
        extra={"user_id": user.sub, "username": user.preferred_username, "keep_current": keep_current},
    )

    try:
        current_session_id = get_current_session_id(request) if keep_current else None

        if keep_current and current_session_id:
            # Revoke all sessions except current
            user_sessions = await keycloak_admin_service.get_user_sessions(user.sub)

            revoked_count = 0
            for session in user_sessions:
                session_id = session.get("id")
                if session_id and session_id != current_session_id:
                    try:
                        await keycloak_admin_service.revoke_session(session_id)
                        revoked_count += 1
                    except Exception as e:
                        logger.warning(
                            "Failed to revoke individual session", extra={"session_id": session_id, "error": str(e)}
                        )

            logger.info(
                "Successfully revoked other sessions",
                extra={"user_id": user.sub, "revoked_count": revoked_count, "kept_session": current_session_id},
            )
        else:
            # Revoke all sessions including current
            success = await keycloak_admin_service.revoke_all_user_sessions(user.sub)

            if not success:
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to revoke all sessions"
                )

            logger.info("Successfully revoked all sessions", extra={"user_id": user.sub})

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Failed to revoke all sessions", exc_info=True, extra={"user_id": user.sub, "error": str(e)})
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to revoke all sessions")


@router.get("/events", response_model=ActivityEventsResponse)
async def get_activity_events(
    page: int = Query(1, ge=1, description="Page number (starting from 1)"),
    size: int = Query(20, ge=1, le=100, description="Items per page (max 100)"),
    event_type: str | None = Query(None, description="Filter by event type: login, security, profile, all"),
    user: OIDCUser = Depends(idp.get_current_user()),
):
    """Get activity events (security log) for the current user.

    Returns a paginated list of security-relevant events from Keycloak,
    including logins, logouts, password changes, and profile updates.

    Args:
        page: Page number (1-indexed)
        size: Number of items per page (max 100)
        event_type: Filter by category:
            - "login": LOGIN, LOGOUT, LOGIN_ERROR events
            - "security": LOGIN_ERROR, UPDATE_PASSWORD, UPDATE_TOTP events
            - "profile": UPDATE_PROFILE, UPDATE_EMAIL events
            - "all" or None: All events

    Returns:
        ActivityEventsResponse with paginated events

    Note:
        Events must be enabled in Keycloak realm settings for this to work.
    """
    logger.info(
        "Fetching activity events for current user",
        extra={
            "user_id": user.sub,
            "username": user.preferred_username,
            "page": page,
            "size": size,
            "event_type": event_type,
        },
    )

    try:
        # Map frontend event type filter to Keycloak event types
        event_type_mapping = {
            "login": ["LOGIN", "LOGOUT", "LOGIN_ERROR", "CODE_TO_TOKEN"],
            "security": ["LOGIN_ERROR", "UPDATE_PASSWORD", "UPDATE_TOTP", "REMOVE_TOTP"],
            "profile": ["UPDATE_PROFILE", "UPDATE_EMAIL"],
        }

        keycloak_types = None
        if event_type and event_type != "all":
            keycloak_types = event_type_mapping.get(event_type)

        # Calculate offset
        first = (page - 1) * size

        # Fetch events from Keycloak (fetch one extra to check if there are more)
        kc_events = await keycloak_admin_service.get_user_events(
            user_id=user.sub, first=first, max_results=size + 1, event_types=keycloak_types
        )

        # Check if there are more events
        has_more = len(kc_events) > size
        if has_more:
            kc_events = kc_events[:size]

        # Transform events to response format
        events = []
        for kc_event in kc_events:
            event_type_str = kc_event.get("type", "UNKNOWN")
            display_info = get_event_display_info(event_type_str)

            # Parse timestamp (Keycloak returns epoch milliseconds)
            timestamp = datetime.fromtimestamp(kc_event.get("time", 0) / 1000)

            # Build description with additional details
            description = display_info["description"]
            details = kc_event.get("details", {})
            if details and "auth_method" in details:
                description += f" ({details['auth_method']})"

            events.append(
                ActivityEventResponse(
                    id=str(kc_event.get("time", 0)),  # Use timestamp as ID if no ID provided
                    type=event_type_str,
                    display_type=display_info["display_type"],
                    icon=display_info["icon"],
                    title=display_info["title"],
                    description=description,
                    ip_address=kc_event.get("ipAddress"),
                    timestamp=timestamp,
                )
            )

        logger.info(
            "Successfully fetched activity events",
            extra={"user_id": user.sub, "event_count": len(events), "has_more": has_more},
        )

        return ActivityEventsResponse(events=events, page=page, size=size, has_more=has_more)

    except Exception as e:
        logger.error("Failed to fetch activity events", exc_info=True, extra={"user_id": user.sub, "error": str(e)})
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to fetch activity events")
