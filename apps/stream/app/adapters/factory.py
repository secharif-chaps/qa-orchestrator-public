"""Channel adapter factory — resolves adapter from ChannelType."""

from app.models.stream import ChannelType

from .base import ChannelAdapter
from .slack import SlackWebhookAdapter
from .teams import TeamsAdapter
from .webhook import WebhookAdapter

_ADAPTER_MAP: dict[ChannelType, type[ChannelAdapter]] = {
    ChannelType.TEAMS: TeamsAdapter,
    ChannelType.SLACK_WEBHOOK: SlackWebhookAdapter,
    ChannelType.WEBHOOK: WebhookAdapter,
}


def get_adapter(channel_type: ChannelType) -> ChannelAdapter:
    """Resolve and instantiate the adapter for a given channel type.

    Args:
        channel_type: The channel type enum value

    Returns:
        An instantiated ChannelAdapter

    Raises:
        ValueError: If no adapter is registered for the channel type
    """
    adapter_cls = _ADAPTER_MAP.get(channel_type)
    if not adapter_cls:
        raise ValueError(f"No adapter registered for channel type: {channel_type}")
    return adapter_cls()
