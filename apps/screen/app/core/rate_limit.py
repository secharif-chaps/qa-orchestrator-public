"""Per-user rate limiting for expensive endpoints.

Uses in-memory sliding window counters keyed by Keycloak user ID (JWT sub).
"""

import time

from fastapi import Depends, HTTPException, status
from fastapi_keycloak import OIDCUser

from app.core.config import settings
from app.core.keycloak import idp
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class UserRateLimiter:
    """In-memory sliding window rate limiter keyed by user ID."""

    def __init__(self, max_requests: int, window_seconds: int = 60):
        self.max_requests = max_requests
        self.window_seconds = window_seconds
        self._store: dict[str, list[float]] = {}

    def check(self, user_id: str) -> None:
        """Check if user has exceeded the rate limit.

        Args:
            user_id: Keycloak user UUID (JWT sub)

        Raises:
            HTTPException: 429 if rate limit exceeded
        """
        now = time.time()
        cutoff = now - self.window_seconds

        # Get and filter timestamps for this user
        timestamps = [t for t in self._store.get(user_id, []) if t > cutoff]

        if len(timestamps) >= self.max_requests:
            # Calculate wait time from the oldest request in the window
            wait_seconds = int(timestamps[0] - cutoff) + 1
            logger.warning(
                "User rate limit exceeded on chapse chat",
                extra={"user_id": user_id, "requests": len(timestamps), "limit": self.max_requests},
            )
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail=(
                    f"Rate limit exceeded: maximum {self.max_requests} requests "
                    f"per {self.window_seconds} seconds. "
                    f"Please retry in {wait_seconds} seconds."
                ),
                headers={"Retry-After": str(wait_seconds)},
            )

        timestamps.append(now)
        self._store[user_id] = timestamps

        # Periodic cleanup: remove users with no recent requests
        if len(self._store) > 1000:
            self._store = {uid: ts for uid, ts in self._store.items() if any(t > cutoff for t in ts)}


# Singleton limiter for the chapse chat endpoint
_chapse_chat_limiter = UserRateLimiter(
    max_requests=settings.CHAPSE_CHAT_RATE_LIMIT,
    window_seconds=60,
)


async def check_chapse_chat_rate_limit(
    user: OIDCUser = Depends(idp.get_current_user()),
) -> OIDCUser:
    """FastAPI dependency that enforces per-user rate limit on chapse chat.

    Returns the authenticated user so the endpoint doesn't need a separate Depends.
    """
    _chapse_chat_limiter.check(user.sub)
    return user
