"""Smoke tests for the `send_test_email` CLI.

The CLI is a thin orchestrator around `get_email_provider().send()`. We
inject fakes by monkey-patching the script's `get_email_provider` symbol
— no DB, no HTTP. The tests pin the three exit-code paths plus the
argparse usage error.
"""

from __future__ import annotations

from collections.abc import Callable

import pytest

from app.integrations.email import EmailSendRequest, EmailSendResult
from app.scripts import send_test_email as cli


class _FakeProvider:
    """Minimal EmailProvider stand-in for CLI tests.

    Doesn't subclass `EmailProvider` — duck-typing keeps the test isolated
    from the abstract interface and makes the failure modes obvious.
    """

    def __init__(self, send_impl: Callable[[EmailSendRequest], EmailSendResult]):
        self._send_impl = send_impl
        self.received: list[EmailSendRequest] = []

    async def send(self, request: EmailSendRequest) -> EmailSendResult:
        self.received.append(request)
        return self._send_impl(request)


@pytest.fixture
def patch_provider(monkeypatch):
    """Inject a custom provider into the CLI for the duration of the test."""

    def _patch(send_impl: Callable[[EmailSendRequest], EmailSendResult]) -> _FakeProvider:
        provider = _FakeProvider(send_impl)
        monkeypatch.setattr(cli, "get_email_provider", lambda: provider)
        return provider

    return _patch


class TestExitCodes:
    def test_success_path_exits_zero_and_prints_message_id(self, patch_provider, capsys):
        patch_provider(lambda req: EmailSendResult(success=True, message_id="msg-42"))

        exit_code = cli.main(["--to", "alice@example.com"])

        captured = capsys.readouterr()
        assert exit_code == cli.EXIT_OK
        assert "msg-42" in captured.out

    def test_provider_failure_exits_one_and_prints_error(self, patch_provider, capsys):
        patch_provider(lambda req: EmailSendResult(success=False, error="boom"))

        exit_code = cli.main(["--to", "alice@example.com"])

        captured = capsys.readouterr()
        assert exit_code == cli.EXIT_PROVIDER_FAILURE
        assert "boom" in captured.err

    def test_provider_failure_with_no_error_message_still_exits_one(self, patch_provider, capsys):
        patch_provider(lambda req: EmailSendResult(success=False))

        exit_code = cli.main(["--to", "alice@example.com"])

        captured = capsys.readouterr()
        assert exit_code == cli.EXIT_PROVIDER_FAILURE
        assert "success=False" in captured.err

    def test_null_provider_exits_two_with_hint(self, monkeypatch, capsys):
        class _NullLikeProvider:
            async def send(self, request):
                raise NotImplementedError("No email provider is configured.")

        monkeypatch.setattr(cli, "get_email_provider", lambda: _NullLikeProvider())

        exit_code = cli.main(["--to", "alice@example.com"])

        captured = capsys.readouterr()
        assert exit_code == cli.EXIT_NO_PROVIDER
        assert "NP6_BASE_URL" in captured.err
        assert "NP6_API_KEY" in captured.err
        assert "NP6_FROM_EMAIL" in captured.err

    def test_missing_to_argument_argparse_exits(self, capsys):
        with pytest.raises(SystemExit) as excinfo:
            cli.main([])
        assert excinfo.value.code == 2  # standard argparse usage exit code
        captured = capsys.readouterr()
        assert "--to" in captured.err


class TestRequestShape:
    def test_request_uses_provided_recipient(self, patch_provider):
        provider = patch_provider(lambda req: EmailSendResult(success=True, message_id="msg-1"))

        cli.main(["--to", "alice@example.com"])

        assert len(provider.received) == 1
        assert provider.received[0].recipients == ["alice@example.com"]

    def test_request_carries_phase_1a_smoke_test_marker(self, patch_provider):
        """The hardcoded subject + tags identify the source for ops/log triage."""
        provider = patch_provider(lambda req: EmailSendResult(success=True, message_id="msg-1"))

        cli.main(["--to", "alice@example.com"])

        request = provider.received[0]
        assert "Test send" in request.subject
        assert request.tags == {"source": "send_test_email_cli"}

    def test_request_does_not_carry_sender_fields(self, patch_provider):
        """The sender invariant locked in MR-02 is honored end-to-end."""
        provider = patch_provider(lambda req: EmailSendResult(success=True, message_id="msg-1"))

        cli.main(["--to", "alice@example.com"])

        dumped = provider.received[0].model_dump()
        assert "from" not in dumped
        assert "sender" not in dumped
        assert "reply_to" not in dumped
