"""Unit tests for app.core.auth.

Covers:
- AuthenticatedUser model (roles property)
- verify_internal_jwt() request-state memoization
- get_current_user() factory validation (rejects empty role list)
"""

from unittest.mock import MagicMock, patch

import pytest
from fastapi import HTTPException

from app.core.auth import (
    AuthenticatedUser,
    get_current_user,
    verify_internal_jwt,
)
from app.core.internal_jwt import InternalTokenPayload, TokenInvalidError


def _make_request(headers: dict[str, str] | None = None) -> MagicMock:
    """Build a mock Request with a real `state` namespace."""
    request = MagicMock()
    request.headers = headers or {"Authorization": "Internal fake-token"}

    class _State:
        pass

    request.state = _State()
    return request


def _make_payload(sub: str = "user-1", roles: list[str] | None = None) -> InternalTokenPayload:
    return InternalTokenPayload(
        sub=sub,
        username="alice",
        email="alice@example.com",
        org_id="org-1",
        org_name="Acme",
        roles=roles or ["company.view"],
        iat=1_700_000_000,
        exp=1_700_000_060,
    )


class TestAuthenticatedUserRoles:
    def test_roles_defaults_to_empty_list(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice")
        assert user.roles == []

    def test_roles_stores_provided_list(self):
        user = AuthenticatedUser(
            sub="u",
            preferred_username="alice",
            roles=["admin", "company.view"],
        )
        assert user.roles == ["admin", "company.view"]


class TestVerifyInternalJwtCache:
    """Verify the request.state memoization in verify_internal_jwt."""

    def test_cache_miss_runs_verification(self):
        request = _make_request()

        with patch("app.core.auth.verify_internal_request", return_value=_make_payload()) as mock_verify:
            user = verify_internal_jwt(request)

        assert mock_verify.call_count == 1
        assert user.sub == "user-1"
        # Cache populated for subsequent calls
        assert request.state._authenticated_user is user

    def test_cache_hit_skips_verification(self):
        request = _make_request()

        with patch("app.core.auth.verify_internal_request", return_value=_make_payload()) as mock_verify:
            first = verify_internal_jwt(request)
            second = verify_internal_jwt(request)

        # verify_internal_request runs only once across two calls on the same request
        assert mock_verify.call_count == 1
        assert first is second

    def test_cache_is_per_request(self):
        """Two different requests must each trigger their own verification."""
        request_a = _make_request()
        request_b = _make_request()

        with patch("app.core.auth.verify_internal_request", return_value=_make_payload()) as mock_verify:
            verify_internal_jwt(request_a)
            verify_internal_jwt(request_b)

        assert mock_verify.call_count == 2

    def test_failed_verification_does_not_cache(self):
        """A failed verification must not poison request.state."""
        request = _make_request()

        with (
            patch("app.core.auth.verify_internal_request", side_effect=TokenInvalidError("bad")),
            pytest.raises(HTTPException) as exc_info,
        ):
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401
        assert getattr(request.state, "_authenticated_user", None) is None


class TestGetCurrentUserFactoryValidation:
    """get_current_user() rejects ambiguous role configurations at factory time."""

    def test_none_required_roles_is_allowed(self):
        # No exception — role checking is intentionally skipped
        dependency = get_current_user(required_roles=None)
        assert callable(dependency)

    def test_non_empty_required_roles_is_allowed(self):
        dependency = get_current_user(required_roles=["admin"])
        assert callable(dependency)

    def test_empty_required_roles_raises_value_error(self):
        """Empty list is a footgun (silently permissive); reject it loudly."""
        with pytest.raises(ValueError, match="must be None"):
            get_current_user(required_roles=[])
