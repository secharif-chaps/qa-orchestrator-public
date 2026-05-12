"""DI helper resolving the active `EmailProvider`.

`get_email_provider()` returns `NP6EmailProvider` when `NP6_BASE_URL`,
`NP6_API_KEY`, and `NP6_FROM_EMAIL` are configured; otherwise it falls
back to the inert `NullEmailProvider` so local dev can boot without NP6
credentials.
"""

import logging
from datetime import datetime

from app.core.config import settings

from .provider import EmailProvider
from .schemas import (
    EmailDeliveryEvent,
    EmailSendRequest,
    EmailSendResult,
    ExecutionResult,
)

logger = logging.getLogger(__name__)

_MISSING = (
    "No email provider is configured. Set NP6_BASE_URL / NP6_API_KEY / "
    "NP6_FROM_EMAIL in the environment to wire NP6EmailProvider."
)


class NullEmailProvider(EmailProvider):
    """Inert provider for local dev when NP6 settings are missing.

    Every method raises `NotImplementedError` so failures during local dev
    are unambiguous instead of silent.
    """

    async def create_action(self, *, name: str, subject: str, html: str) -> str:
        raise NotImplementedError(_MISSING)

    async def update_action(self, action_id: str, *, subject: str, html: str) -> None:
        raise NotImplementedError(_MISSING)

    async def validate_action(self, action_id: str, *, bat_test_segment_id: int) -> None:
        raise NotImplementedError(_MISSING)

    async def upsert_target(self, email: str) -> bool:
        raise NotImplementedError(_MISSING)

    async def execute_to_recipients(
        self,
        action_id: str,
        emails: list[str],
    ) -> list[ExecutionResult]:
        raise NotImplementedError(_MISSING)

    async def send(self, request: EmailSendRequest) -> EmailSendResult:
        raise NotImplementedError(_MISSING)

    async def pull_events(self, *, since: datetime, until: datetime) -> list[EmailDeliveryEvent]:
        raise NotImplementedError(_MISSING)


def get_email_provider() -> EmailProvider:
    """Return the active `EmailProvider` instance.

    Live NP6 wiring activates as soon as `NP6_BASE_URL`, `NP6_API_KEY`, and
    `NP6_FROM_EMAIL` are set. Without them, the inert `NullEmailProvider`
    is returned so Stream still boots cleanly in dev.
    """
    if settings.NP6_BASE_URL and settings.NP6_API_KEY and settings.NP6_FROM_EMAIL:
        from .np6 import NP6EmailProvider

        return NP6EmailProvider(settings)

    logger.info("NP6 settings missing; using NullEmailProvider stub")
    return NullEmailProvider()
