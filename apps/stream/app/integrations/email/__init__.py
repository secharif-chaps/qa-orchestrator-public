"""Email provider integration boundary (ADR-0020 §"NP6 API Contract — Verified").

Public surface re-exported here so callers import from
`app.integrations.email`, not from internal submodules. This keeps the
seam stable as the underlying NP6 implementation evolves.
"""

from .factory import NullEmailProvider, get_email_provider
from .np6 import NP6EmailProvider
from .np6_client import NP6Client
from .provider import EmailProvider
from .schemas import (
    EmailDeliveryEvent,
    EmailDeliveryEventType,
    EmailSendRequest,
    EmailSendResult,
    ExecutionResult,
)

__all__ = [
    "EmailDeliveryEvent",
    "EmailDeliveryEventType",
    "EmailProvider",
    "EmailSendRequest",
    "EmailSendResult",
    "ExecutionResult",
    "NP6Client",
    "NP6EmailProvider",
    "NullEmailProvider",
    "get_email_provider",
]
