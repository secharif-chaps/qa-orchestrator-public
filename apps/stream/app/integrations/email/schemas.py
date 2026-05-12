"""DTOs for the email integration boundary (ADR-0020 §NP6 Integration).

These schemas are the contract between the Newsletter adapter and any
concrete email provider. The shape was redesigned after the 2026-04-30
NP6 spec audit and again after the 2026-05-04 live verification:
`EmailSendRequest` / `EmailSendResult` remain the single-recipient
ad-hoc envelope (test-send + smoke CLI), while the batch dispatch path
exposes `ExecutionResult` per recipient.

Recipients are addressed by `unicity` (the contact's email) at dispatch
time; we no longer keep a public `RecipientRef` DTO since the provider
builds the NP6 recipient body internally from a plain `email: str`.

Security note: `from`, `sender`, and `reply_to` are intentionally absent
from `EmailSendRequest` and rejected at the schema layer (`extra="forbid"`).
The sender is provider-managed (`NP6_FROM_EMAIL`) to guarantee SPF / DKIM /
DMARC alignment with NP6's authenticated domains. See ADR-0020 §Sender
Authentication.
"""

from datetime import datetime
from typing import Annotated, Any, Literal

from pydantic import BaseModel, ConfigDict, EmailStr, Field

EmailDeliveryEventType = Literal[
    "delivered",
    "bounce_hard",
    "bounce_soft",
    "complaint",
    "open",
    "click",
    "unsubscribe",
]


class EmailSendRequest(BaseModel):
    """Single-recipient ad-hoc envelope used by the test-send endpoint.

    Multiple emails in `recipients` are still accepted — the provider
    treats the list as a one-shot batch (one action, one validation, N
    recipients addressed by `unicity`). Production traffic flows through
    the batch path on `EmailProvider.execute_to_recipients`.

    `from` / `sender` / `reply_to` are rejected by `extra="forbid"`.
    """

    model_config = ConfigDict(extra="forbid", frozen=True)

    subject: Annotated[str, Field(min_length=1, max_length=998)]
    html: Annotated[str, Field(min_length=1)]
    recipients: Annotated[list[EmailStr], Field(min_length=1)]
    list_unsubscribe: str | None = None
    tags: dict[str, str] = Field(default_factory=dict)


class EmailSendResult(BaseModel):
    """Outcome of a single-recipient `send()` call.

    `message_id` is the provider's identifier for the dispatch — Stream
    persists it on `stream_deliveries.response_metadata` so inbound poller
    events can be correlated back to the originating delivery row.
    """

    model_config = ConfigDict(frozen=True)

    success: bool
    message_id: str | None = None
    provider_response: dict[str, Any] = Field(default_factory=dict)
    error: str | None = None


class ExecutionResult(BaseModel):
    """One recipient's outcome for a `/executions` batch call.

    Mirrors NP6's `ExecutionResult#Discriminable` (type: success | error).
    On success, `message_id` carries the per-recipient identifier we'll
    later see in delivery events. On error, `error_type` tags the cause
    (e.g. `recipient not found`) so the adapter can decide whether to
    retry, suppress, or surface to the operator.
    """

    model_config = ConfigDict(frozen=True)

    success: bool
    message_id: str | None = None
    email: EmailStr | None = None
    target_id: str | None = None
    unicity: str | None = None
    error_type: str | None = None
    raw: dict[str, Any] = Field(default_factory=dict)


class EmailDeliveryEvent(BaseModel):
    """Normalized delivery event surfaced by `EmailProvider.pull_events`.

    Provider-specific event payloads (NP6 `bounce` with verdict, `hit` with
    link type, etc.) are normalized into this shape so the suppression and
    delivery services never have to know which provider produced the event.
    """

    model_config = ConfigDict(frozen=True)

    event_id: str
    message_id: str | None
    event_type: EmailDeliveryEventType
    email: EmailStr
    timestamp: datetime
    metadata: dict[str, Any] = Field(default_factory=dict)
