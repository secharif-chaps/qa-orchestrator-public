"""Operator CLI — send a hardcoded HTML email through the active EmailProvider.

This is the Phase 1a smoke test (ADR-0020 §Phased Delivery) — proves the
`integrations/email/` seam is wired end-to-end. When NP6 staging
credentials are configured, an actual email arrives in the recipient's
inbox; with no NP6 settings the CLI surfaces a clear hint and exits 2.

Usage (inside the container):
    python -m app.scripts.send_test_email --to alice@example.com

Or via the Taskfile from the repo root:
    task stream:send-test-email -- --to alice@example.com

Exit codes:
    0  Success — provider returned a `message_id`.
    1  Provider returned `success=False`.
    2  No NP6 settings configured (or unrecognized argparse usage).
"""

from __future__ import annotations

import argparse
import asyncio
import sys
from collections.abc import Sequence

from app.core.config import settings
from app.core.logging_config import get_logger, setup_logging
from app.integrations.email import EmailSendRequest, get_email_provider

logger = get_logger(__name__)

EXIT_OK = 0
EXIT_PROVIDER_FAILURE = 1
EXIT_NO_PROVIDER = 2

_NP6_HINT = (
    "NP6 settings missing — set NP6_BASE_URL / NP6_API_KEY / NP6_FROM_EMAIL "
    "in the environment (or .env) before running this CLI."
)


def _build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="send_test_email",
        description="Send a hardcoded HTML email through the active EmailProvider (Phase 1a smoke test).",
    )
    parser.add_argument(
        "--to",
        required=True,
        help="Recipient email address.",
    )
    return parser


def _build_request(recipient: str) -> EmailSendRequest:
    return EmailSendRequest(
        subject="[Stream] Test send",
        html="<p>Hello from Stream — this is a Phase 1a smoke test.</p>",
        recipients=[recipient],
        tags={"source": "send_test_email_cli"},
    )


async def _run(recipient: str) -> int:
    request = _build_request(recipient)
    provider = get_email_provider()

    try:
        result = await provider.send(request)
    except NotImplementedError:
        # NullEmailProvider raises this when NP6 settings are unset (factory.py
        # in MR-03 falls back to it explicitly so the app boots without NP6).
        print(_NP6_HINT, file=sys.stderr)
        logger.error("send_test_email aborted: no email provider is configured")
        return EXIT_NO_PROVIDER

    if result.success:
        print(result.message_id)
        logger.info(
            "send_test_email succeeded",
            extra={"message_id": result.message_id, "recipient": recipient},
        )
        return EXIT_OK

    error = result.error or "provider returned success=False without an error message"
    print(error, file=sys.stderr)
    logger.error(
        "send_test_email failed",
        extra={"error": error, "recipient": recipient},
    )
    return EXIT_PROVIDER_FAILURE


def main(argv: Sequence[str] | None = None) -> int:
    """Entry point. Returns the process exit code (does not call sys.exit)."""
    setup_logging(level=getattr(settings, "LOG_LEVEL", "INFO"))
    args = _build_parser().parse_args(argv)
    return asyncio.run(_run(args.to))


if __name__ == "__main__":
    sys.exit(main())
