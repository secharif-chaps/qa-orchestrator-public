"""Slack webhook channel adapter using Block Kit."""

import httpx

from app.constants.messages import BUTTON_VIEW_COMPANY
from app.core.logging_config import get_logger
from app.models.event import StreamEvent
from app.models.stream import Stream

from .base import ChannelAdapter, DispatchResult
from .formatting import (
    build_entity_url,
    extract_payload_details,
    format_timestamp,
    get_source_label,
)

logger = get_logger(__name__)

HTTP_TIMEOUT = 10.0


class SlackWebhookAdapter(ChannelAdapter):
    """Sends Block Kit messages to a Slack incoming webhook."""

    def format_payload(self, event: StreamEvent, stream: Stream) -> dict:
        """Format event as a user-friendly Slack Block Kit message."""
        blocks: list[dict] = [
            {
                "type": "header",
                "text": {"type": "plain_text", "text": stream.name, "emoji": True},
            },
        ]

        # Summary
        if event.summary:
            blocks.append({"type": "section", "text": {"type": "mrkdwn", "text": event.summary}})

        # Payload details (company name, result stats)
        details = extract_payload_details(event)
        if details:
            fields = [{"type": "mrkdwn", "text": f"*{label}* : {value}"} for label, value in details]
            blocks.append({"type": "section", "fields": fields})

        # Link to entity
        url = build_entity_url(event)
        if url:
            blocks.append(
                {
                    "type": "section",
                    "text": {
                        "type": "mrkdwn",
                        "text": f":point_right: <{url}|{BUTTON_VIEW_COMPANY}>",
                    },
                }
            )

        # Footer: timestamp + source
        footer_text = f"{format_timestamp()} \u00b7 {get_source_label(str(event.source))}"
        blocks.append(
            {
                "type": "context",
                "elements": [{"type": "mrkdwn", "text": footer_text}],
            }
        )

        return {"blocks": blocks}

    async def send(self, event: StreamEvent, stream: Stream) -> DispatchResult:
        """POST the Block Kit message to the Slack webhook URL."""
        config: dict = stream.channel_config or {}  # type: ignore[assignment]
        webhook_url = config.get("webhook_url")
        if not webhook_url:
            return DispatchResult(success=False, error="Missing webhook_url in channel_config")

        payload = self.format_payload(event, stream)

        try:
            async with httpx.AsyncClient(timeout=HTTP_TIMEOUT) as client:
                response = await client.post(
                    str(webhook_url),
                    json=payload,
                    headers={"Content-Type": "application/json"},
                )

            body_text = response.text[:500] if response.text else None

            if 200 <= response.status_code < 300:
                logger.info(
                    "Slack dispatch success",
                    extra={"stream_id": stream.id, "status_code": response.status_code},
                )
                return DispatchResult(
                    success=True,
                    status_code=response.status_code,
                    response_body=body_text,
                )

            logger.warning(
                "Slack dispatch failed",
                extra={"stream_id": stream.id, "status_code": response.status_code, "body": body_text},
            )
            return DispatchResult(
                success=False,
                status_code=response.status_code,
                response_body=body_text,
                error=f"HTTP {response.status_code}",
            )

        except httpx.TimeoutException:
            logger.warning("Slack dispatch timeout", extra={"stream_id": stream.id})
            return DispatchResult(success=False, error="Request timed out")
        except httpx.RequestError as e:
            logger.warning("Slack dispatch error", extra={"stream_id": stream.id, "error": str(e)})
            return DispatchResult(success=False, error=str(e))
