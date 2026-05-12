"""Generic `EmailProvider` contract for the Newsletter channel.

ADR-0020 §"NP6 Integration — Isolated Module Inside Stream" treats the
`integrations/email/` module as a provider-agnostic seam. The adapter,
dispatch service, and event poller depend on this ABC, not on NP6
specifics. A future swap to a second provider only needs a new
`EmailProvider` implementation; nothing else changes.

The contract is shaped after the validated NP6 workflow (live audit
2026-05-04, see `docs/np6/README.md`):

1. `create_action` / `update_action` hold the rendered HTML template.
2. `validate_action` runs NP6's mandatory 2-phase validation (BAT → prod).
3. `upsert_target` provisions a contact (idempotent on the unicity field).
4. `execute_to_recipients` dispatches the action against a list of emails
   (one HTTP call per batch), addressing each by `unicity`.

`send()` is preserved for the single-recipient ad-hoc path used by the
test-send endpoint and the smoke-test CLI; it is implemented on top of
the same primitives but with no persistent action reuse.
"""

from abc import ABC, abstractmethod
from datetime import datetime

from .schemas import (
    EmailDeliveryEvent,
    EmailSendRequest,
    EmailSendResult,
    ExecutionResult,
)


class EmailProvider(ABC):
    """Generic newsletter email provider contract.

    All methods are async; concrete implementations are expected to use
    a connection-pooled HTTP client and to surface terminal failures via
    exceptions (the adapter layer maps them to `DispatchResult`).
    """

    # ---------------------------------------------------------- action lifecycle

    @abstractmethod
    async def create_action(self, *, name: str, subject: str, html: str) -> str:
        """Create a provider-side email template (NP6 `mailMessage` action).

        The provider stamps its own `From` address from settings and never
        honors a caller-supplied sender — the schema layer rejects it but
        this is the second line of defense.

        Returns the provider-issued action id.
        """

    @abstractmethod
    async def update_action(self, action_id: str, *, subject: str, html: str) -> None:
        """Update an existing action's subject and HTML body in place."""

    @abstractmethod
    async def validate_action(self, action_id: str, *, bat_test_segment_id: int) -> None:
        """Run NP6's mandatory 2-phase validation, idempotent.

        Phase 1 sends a BAT to `bat_test_segment_id`; phase 2 promotes the
        action to "production-ready" (state 50). Implementations MUST be
        safe to re-call — the post-condition is "action is at state 50".
        """

    # --------------------------------------------------------- contact lifecycle

    @abstractmethod
    async def upsert_target(self, email: str) -> bool:
        """Provision a contact, idempotent on the unicity (email) field.

        Returns `True` when the contact was newly created, `False` when it
        already existed on the provider side. Callers address the contact
        by `unicity = email` at dispatch time, never by a persistent target
        id — the bool is a signal for metrics (acquisition rate), not a key.
        """

    # ------------------------------------------------------------------ dispatch

    @abstractmethod
    async def execute_to_recipients(
        self,
        action_id: str,
        emails: list[str],
    ) -> list[ExecutionResult]:
        """Trigger delivery of `action_id` to each email.

        Returns one `ExecutionResult` per input email, in NP6's response
        order. Implementations must address each recipient by `unicity`.
        """

    # ----------------------------------------------------- single-shot ad-hoc API

    @abstractmethod
    async def send(self, request: EmailSendRequest) -> EmailSendResult:
        """Single-recipient ad-hoc send, used by the smoke-test CLI.

        Implementations create an ephemeral action, run validation, upsert
        the recipient(s) as targets, dispatch, and return the first
        message id. Cleanup of ephemeral resources is left to NP6's
        retention policy.
        """

    # -------------------------------------------------------------------- events

    @abstractmethod
    async def pull_events(self, *, since: datetime, until: datetime) -> list[EmailDeliveryEvent]:
        """Pull delivery events from the provider's event endpoint.

        The caller persists the cursor; this method is stateless re: time.
        Returns events in ascending timestamp order; empty list when the
        window has no events.
        """
