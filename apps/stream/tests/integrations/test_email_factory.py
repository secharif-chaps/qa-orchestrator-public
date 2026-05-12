"""Tests for the EmailProvider factory and Null stub.

`get_email_provider()` returns `NP6EmailProvider` when NP6 settings are
configured and `NullEmailProvider` otherwise — the latter exists so dev
boots cleanly without NP6 credentials, with explicit failures the moment
something tries to actually send.
"""

from datetime import UTC, datetime
from unittest.mock import patch

import pytest

from app.integrations.email import (
    EmailProvider,
    EmailSendRequest,
    NP6EmailProvider,
    NullEmailProvider,
    get_email_provider,
)


class TestGetEmailProvider:
    def test_returns_email_provider_instance(self):
        provider = get_email_provider()
        assert isinstance(provider, EmailProvider)

    def test_returns_null_stub_when_np6_unconfigured(self):
        """Empty NP6 settings → factory falls back to Null (boot does not crash)."""
        with patch("app.integrations.email.factory.settings") as mock_settings:
            mock_settings.NP6_BASE_URL = ""
            mock_settings.NP6_API_KEY = ""
            mock_settings.NP6_FROM_EMAIL = ""
            provider = get_email_provider()
        assert isinstance(provider, NullEmailProvider)

    def test_returns_np6_provider_when_configured(self):
        """When all required NP6 settings are present, the factory wires NP6."""
        with patch("app.integrations.email.factory.settings") as mock_settings:
            mock_settings.NP6_BASE_URL = "https://np6.example"
            mock_settings.NP6_API_KEY = "secret"
            mock_settings.NP6_FROM_EMAIL = "noreply@chapsmind.fr"
            mock_settings.NP6_BAT_TEST_SEGMENT_ID = 1319
            provider = get_email_provider()
        assert isinstance(provider, NP6EmailProvider)

    @pytest.mark.parametrize(
        "missing_field",
        ["NP6_BASE_URL", "NP6_API_KEY", "NP6_FROM_EMAIL"],
    )
    def test_falls_back_to_null_when_any_required_field_missing(self, missing_field: str):
        """Missing any one required NP6 field → Null fallback (boot must not crash)."""
        with patch("app.integrations.email.factory.settings") as mock_settings:
            mock_settings.NP6_BASE_URL = "https://np6.example"
            mock_settings.NP6_API_KEY = "secret"
            mock_settings.NP6_FROM_EMAIL = "noreply@chapsmind.fr"
            setattr(mock_settings, missing_field, "")
            provider = get_email_provider()
        assert isinstance(provider, NullEmailProvider)


class TestNullEmailProviderRaises:
    """Every method must raise NotImplementedError so dev failures are loud."""

    @pytest.mark.asyncio
    async def test_send_raises(self):
        provider = NullEmailProvider()
        request = EmailSendRequest(subject="x", html="<p/>", recipients=["a@example.com"])
        with pytest.raises(NotImplementedError, match="No email provider is configured"):
            await provider.send(request)

    @pytest.mark.asyncio
    async def test_create_action_raises(self):
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().create_action(name="n", subject="s", html="<p/>")

    @pytest.mark.asyncio
    async def test_update_action_raises(self):
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().update_action("act-1", subject="s", html="<p/>")

    @pytest.mark.asyncio
    async def test_validate_action_raises(self):
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().validate_action("act-1", bat_test_segment_id=1319)

    @pytest.mark.asyncio
    async def test_upsert_target_raises(self):
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().upsert_target("a@example.com")

    @pytest.mark.asyncio
    async def test_execute_to_recipients_raises(self):
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().execute_to_recipients("act-1", ["a@example.com"])

    @pytest.mark.asyncio
    async def test_pull_events_raises(self):
        now = datetime.now(UTC)
        with pytest.raises(NotImplementedError):
            await NullEmailProvider().pull_events(since=now, until=now)
