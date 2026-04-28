"""Unit tests for app.core.auth.

Covers:
- AuthenticatedUser model (roles property)
- verify_internal_jwt() rejects requests without an Internal authorization header
- get_current_user() factory validation (rejects empty role list)
- FastAPI dedupes verify_internal_jwt across dependencies on a single request
"""

from unittest.mock import MagicMock, patch

import pytest
from fastapi import Depends, FastAPI, HTTPException
from fastapi.testclient import TestClient

from app.core.auth import (
    AuthenticatedUser,
    get_current_user,
    verify_internal_jwt,
)
from app.core.internal_jwt import InternalTokenPayload, TokenInvalidError
from app.core.organization_context import OrganizationContext, get_user_organization


def _make_request(headers: dict[str, str] | None = None) -> MagicMock:
    """Build a mock Request with a real `state` namespace."""
    request = MagicMock()
    request.headers = headers if headers is not None else {"Authorization": "Internal fake-token"}

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


class TestVerifyInternalJwt:
    """verify_internal_jwt translates internal-jwt outcomes to HTTP responses."""

    def test_missing_internal_header_raises_401(self):
        request = _make_request(headers={})

        with pytest.raises(HTTPException) as exc_info:
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401
        assert "Missing Internal authorization header" in exc_info.value.detail

    def test_bearer_header_raises_401(self):
        request = _make_request(headers={"Authorization": "Bearer something"})

        with pytest.raises(HTTPException) as exc_info:
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401

    def test_invalid_token_raises_401(self):
        request = _make_request()

        with (
            patch("app.core.auth.verify_internal_request", side_effect=TokenInvalidError("bad")),
            pytest.raises(HTTPException) as exc_info,
        ):
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401

    def test_valid_payload_builds_user(self):
        request = _make_request()

        with patch("app.core.auth.verify_internal_request", return_value=_make_payload()):
            user = verify_internal_jwt(request)

        assert isinstance(user, AuthenticatedUser)
        assert user.sub == "user-1"
        assert user.preferred_username == "alice"
        assert user.org_id == "org-1"


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


class TestSingleVerificationPerRequest:
    """FastAPI's per-request dependency cache must dedupe verify_internal_jwt
    when an endpoint depends on both get_current_user(...) and
    get_user_organization on the same request.
    """

    def test_one_call_per_request_with_two_dependents(self):
        call_count = {"n": 0}

        def _stub_verify() -> AuthenticatedUser:
            call_count["n"] += 1
            return AuthenticatedUser(
                sub="user-1",
                preferred_username="alice",
                roles=["company.view"],
                org_id="org-1",
                org_name="Acme",
            )

        app = FastAPI()
        app.dependency_overrides[verify_internal_jwt] = _stub_verify

        @app.get("/probe")
        def _probe(
            user: AuthenticatedUser = Depends(get_current_user(required_roles=["company.view"])),
            org: OrganizationContext = Depends(get_user_organization),
        ):
            return {"user": user.preferred_username, "org": org.organization_id}

        with TestClient(app) as client:
            resp = client.get("/probe")

        assert resp.status_code == 200, resp.text
        assert call_count["n"] == 1
