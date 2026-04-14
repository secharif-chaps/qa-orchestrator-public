"""Microsoft Teams channel adapter using Adaptive Cards."""

import httpx

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

# Timeout for outbound HTTP calls
HTTP_TIMEOUT = 10.0


class TeamsAdapter(ChannelAdapter):
    """Sends Adaptive Card messages to a Microsoft Teams incoming webhook."""

    def format_payload(self, event: StreamEvent, stream: Stream) -> dict:
        """Format event as a user-friendly Adaptive Card for Teams."""
        body: list[dict] = [
            {
                "type": "TextBlock",
                "size": "Medium",
                "weight": "Bolder",
                "text": stream.name,
            },
        ]

        # Summary
        if event.summary:
            body.append({"type": "TextBlock", "text": event.summary, "wrap": True})

        # Payload details (company name, result stats)
        details = extract_payload_details(event)
        if details:
            body.append(
                {
                    "type": "FactSet",
                    "facts": [{"title": label, "value": value} for label, value in details],
                }
            )

        # Link to entity as action button
        actions: list[dict] = []
        url = build_entity_url(event)
        if url:
            actions.append(
                {
                    "type": "Action.OpenUrl",
                    "title": "Voir la fiche entreprise",
                    "url": url,
                }
            )

        # Footer: timestamp + source
        footer_text = f"{format_timestamp()} \u00b7 {get_source_label(str(event.source))}"
        body.append(
            {
                "type": "TextBlock",
                "text": footer_text,
                "size": "Small",
                "isSubtle": True,
                "wrap": True,
            }
        )

        card_content: dict = {
            "$schema": "http://adaptivecards.io/schemas/adaptive-card.json",
            "type": "AdaptiveCard",
            "version": "1.4",
            "body": body,
        }
        if actions:
            card_content["actions"] = actions

        return {
            "type": "message",
            "attachments": [
                {
                    "contentType": "application/vnd.microsoft.card.adaptive",
                    "contentUrl": None,
                    "content": card_content,
                }
            ],
        }

    async def send(self, event: StreamEvent, stream: Stream) -> DispatchResult:
        """POST the Adaptive Card to the Teams webhook URL."""
        config: dict = stream.channel_config or {}  # type: ignore[assignment]
        webhook_url = config.get("workflow_url")
        if not webhook_url:
            return DispatchResult(success=False, error="Missing workflow_url in channel_config")

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
                    "Teams dispatch success",
                    extra={"stream_id": stream.id, "status_code": response.status_code},
                )
                return DispatchResult(
                    success=True,
                    status_code=response.status_code,
                    response_body=body_text,
                )

            logger.warning(
                "Teams dispatch failed",
                extra={"stream_id": stream.id, "status_code": response.status_code, "body": body_text},
            )
            return DispatchResult(
                success=False,
                status_code=response.status_code,
                response_body=body_text,
                error=f"HTTP {response.status_code}",
            )

        except httpx.TimeoutException:
            logger.warning("Teams dispatch timeout", extra={"stream_id": stream.id})
            return DispatchResult(success=False, error="Request timed out")
        except httpx.RequestError as e:
            logger.warning("Teams dispatch error", extra={"stream_id": stream.id, "error": str(e)})
            return DispatchResult(success=False, error=str(e))
