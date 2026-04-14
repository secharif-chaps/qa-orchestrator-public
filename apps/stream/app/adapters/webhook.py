"""Generic webhook channel adapter with optional HMAC signing."""

import hashlib
import hmac
import json
from datetime import UTC, datetime

import httpx

from app.core.logging_config import get_logger
from app.models.event import StreamEvent
from app.models.stream import Stream

from .base import ChannelAdapter, DispatchResult

logger = get_logger(__name__)

HTTP_TIMEOUT = 10.0
ALLOWED_HTTP_METHODS = {"POST"}


class WebhookAdapter(ChannelAdapter):
    """Sends raw JSON payloads to a generic webhook URL.

    Supports custom headers and optional HMAC-SHA256 signing
    when a secret is configured in channel_config.
    """

    def format_payload(self, event: StreamEvent, stream: Stream) -> dict:
        """Format event as a raw JSON payload."""
        payload = {
            "stream_id": stream.id,
            "stream_name": stream.name,
            "event_type": event.event_type,
            "source": event.source,
            "payload": event.payload,
            "entity_id": event.entity_id,
            "entity_type": event.entity_type,
            "summary": event.summary,
            "organization_id": event.organization_id,
            "timestamp": datetime.now(UTC).isoformat(),
        }
        return payload

    def _sign_payload(self, body: bytes, secret: str) -> str:
        """Compute HMAC-SHA256 signature for the payload body.

        Args:
            body: Raw JSON bytes to sign
            secret: Shared secret for HMAC

        Returns:
            Hex-encoded HMAC-SHA256 signature prefixed with 'sha256='
        """
        signature = hmac.new(
            secret.encode("utf-8"),
            body,
            hashlib.sha256,
        ).hexdigest()
        return f"sha256={signature}"

    async def send(self, event: StreamEvent, stream: Stream) -> DispatchResult:
        """Send the JSON payload to the webhook URL using the configured HTTP method."""
        config: dict = stream.channel_config or {}  # type: ignore[assignment]
        url = config.get("url")
        if not url:
            return DispatchResult(success=False, error="Missing url in channel_config")

        payload = self.format_payload(event, stream)
        body = json.dumps(payload, default=str).encode("utf-8")

        headers: dict[str, str] = {"Content-Type": "application/json"}

        # Merge custom headers
        custom_headers = config.get("headers") or {}
        headers.update(custom_headers)

        # HMAC signing if secret is set
        secret = config.get("secret")
        if secret:
            headers["X-Signature-256"] = self._sign_payload(body, secret)

        try:
            async with httpx.AsyncClient(timeout=HTTP_TIMEOUT) as client:
                method = config.get("method", "POST").upper()
                if method not in ALLOWED_HTTP_METHODS:
                    return DispatchResult(
                        success=False,
                        error=f"HTTP method '{method}' not allowed. Must be one of: {', '.join(sorted(ALLOWED_HTTP_METHODS))}",
                    )
                response = await client.request(
                    method,
                    str(url),
                    content=body,
                    headers=headers,
                )

            body_text = response.text[:500] if response.text else None

            if 200 <= response.status_code < 300:
                logger.info(
                    "Webhook dispatch success",
                    extra={"stream_id": stream.id, "status_code": response.status_code},
                )
                return DispatchResult(
                    success=True,
                    status_code=response.status_code,
                    response_body=body_text,
                )

            logger.warning(
                "Webhook dispatch failed",
                extra={"stream_id": stream.id, "status_code": response.status_code, "body": body_text},
            )
            return DispatchResult(
                success=False,
                status_code=response.status_code,
                response_body=body_text,
                error=f"HTTP {response.status_code}",
            )

        except httpx.TimeoutException:
            logger.warning("Webhook dispatch timeout", extra={"stream_id": stream.id})
            return DispatchResult(success=False, error="Request timed out")
        except httpx.RequestError as e:
            logger.warning("Webhook dispatch error", extra={"stream_id": stream.id, "error": str(e)})
            return DispatchResult(success=False, error=str(e))
