"""Base channel adapter interface for dispatching events."""

from abc import ABC, abstractmethod
from dataclasses import dataclass

from app.models.event import StreamEvent
from app.models.stream import Stream


@dataclass
class DispatchResult:
    """Result of a channel dispatch attempt.

    Attributes:
        success: Whether the dispatch was successful
        status_code: HTTP status code from the target (if applicable)
        response_body: Response body from the target (truncated)
        error: Error message if dispatch failed
    """

    success: bool
    status_code: int | None = None
    response_body: str | None = None
    error: str | None = None


class ChannelAdapter(ABC):
    """Abstract base class for channel adapters.

    Each adapter knows how to format a payload for its channel
    and send it via the appropriate transport (HTTP POST, etc.).
    """

    @abstractmethod
    def format_payload(self, event: StreamEvent, stream: Stream) -> dict:
        """Format the event into a channel-specific payload.

        Args:
            event: The stream event to format
            stream: The stream configuration (channel_config, etc.)

        Returns:
            Channel-specific payload dict ready for sending
        """
        ...

    @abstractmethod
    async def send(self, event: StreamEvent, stream: Stream) -> DispatchResult:
        """Send the formatted event to the channel.

        Args:
            event: The stream event to send
            stream: The stream configuration

        Returns:
            DispatchResult with success/failure details
        """
        ...
