"""Unit tests for app.core.auth.

Mirrors apps/screen/tests/unit/test_auth.py — the two services share the same
auth mechanism after TAR-1610. Covers:
- AuthenticatedUser model
- verify_internal_jwt() translation of typed exceptions to HTTP responses
- get_current_user() factory rejects empty role list
- FastAPI dedupes verify_internal_jwt across two dependencies on a single request
"""

from unittest.mock import MagicMock, patch

import pytest
from fastapi import Depends, FastAPI, HTTPException
from fastapi.testclient import TestClient

from app.core.auth import (
    AuthenticatedUser,
    AuthorizationError,
    get_current_user,
    verify_any_role_access,
    verify_internal_jwt,
    verify_role_access,
)
from app.core.internal_jwt import (
    InternalTokenPayload,
    IPNotAllowedError,
    TokenExpiredError,
    TokenInvalidError,
)
from app.core.organization_context import OrganizationContext, get_user_organization


def _make_request(headers: dict[str, str] | None = None) -> MagicMock:
    request = MagicMock()
    request.headers = headers if headers is not None else {"Authorization": "Internal fake-token"}

    class _State:
        pass

    request.state = _State()
    return request


def _make_payload(roles: list[str] | None = None) -> InternalTokenPayload:
    return InternalTokenPayload(
        sub="user-1",
        username="alice",
        email="alice@example.com",
        org_id="org-1",
        org_name="Acme",
        roles=roles or ["stream.read"],
        iat=1_700_000_000,
        exp=1_700_000_060,
    )


class TestAuthenticatedUser:
    def test_roles_default_empty(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice")
        assert user.roles == []

    def test_carries_enriched_fields(self):
        user = AuthenticatedUser(
            sub="u",
            preferred_username="alice",
            given_name="Alice",
            iat=1_700_000_000,
            exp=1_700_000_060,
            iss="global-gateway",
        )
        assert user.given_name == "Alice"
        assert user.iat == 1_700_000_000
        assert user.exp == 1_700_000_060
        assert user.iss == "global-gateway"


class TestVerifyInternalJwt:
    def test_missing_internal_header_raises_401(self):
        request = _make_request(headers={})

        with pytest.raises(HTTPException) as exc_info:
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401
        assert "Missing Internal authorization header" in exc_info.value.detail

    def test_bearer_header_raises_401(self):
        request = _make_request(headers={"Authorization": "Bearer x"})

        with pytest.raises(HTTPException) as exc_info:
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401

    def test_ip_not_allowed_translates_to_403(self):
        request = _make_request()

        with (
            patch(
                "app.core.auth.verify_internal_request",
                side_effect=IPNotAllowedError("blocked"),
            ),
            pytest.raises(HTTPException) as exc_info,
        ):
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 403
        assert "Access denied: IP not in allowed range" in exc_info.value.detail

    def test_expired_token_translates_to_401(self):
        request = _make_request()

        with (
            patch(
                "app.core.auth.verify_internal_request",
                side_effect=TokenExpiredError("expired"),
            ),
            pytest.raises(HTTPException) as exc_info,
        ):
            verify_internal_jwt(request)

        assert exc_info.value.status_code == 401
        assert "expired" in exc_info.value.detail.lower()

    def test_invalid_token_translates_to_401(self):
        request = _make_request()

        with (
            patch(
                "app.core.auth.verify_internal_request",
                side_effect=TokenInvalidError("nope"),
            ),
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
    def test_none_required_roles_is_allowed(self):
        assert callable(get_current_user(required_roles=None))

    def test_non_empty_required_roles_is_allowed(self):
        assert callable(get_current_user(required_roles=["stream.read"]))

    def test_empty_required_roles_raises_value_error(self):
        with pytest.raises(ValueError, match="must be None"):
            get_current_user(required_roles=[])


class TestRoleAccessHelpers:
    def test_verify_role_access_grants(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice", roles=["admin"])
        assert verify_role_access(user, "admin") is user

    def test_verify_role_access_denies(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice", roles=["other"])
        with pytest.raises(AuthorizationError):
            verify_role_access(user, "admin")

    def test_verify_any_role_access_grants(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice", roles=["admin"])
        assert verify_any_role_access(user, ["admin", "admin.organizations"]) is user

    def test_verify_any_role_access_denies(self):
        user = AuthenticatedUser(sub="u", preferred_username="alice", roles=["other"])
        with pytest.raises(AuthorizationError):
            verify_any_role_access(user, ["admin", "admin.organizations"])


class TestSingleVerificationPerRequest:
    """FastAPI dedupes Depends(verify_internal_jwt) by callable identity within
    a single request. Endpoints that depend on both get_current_user(...) and
    get_user_organization should pay the verification cost only once.
    """

    def test_one_call_per_request_with_two_dependents(self):
        call_count = {"n": 0}

        def _stub_verify() -> AuthenticatedUser:
            call_count["n"] += 1
            return AuthenticatedUser(
                sub="user-1",
                preferred_username="alice",
                roles=["stream.read"],
                org_id="org-1",
                org_name="Acme",
            )

        app = FastAPI()
        app.dependency_overrides[verify_internal_jwt] = _stub_verify

        @app.get("/probe")
        def _probe(
            user: AuthenticatedUser = Depends(get_current_user(required_roles=["stream.read"])),
            org: OrganizationContext = Depends(get_user_organization),
        ):
            return {"user": user.preferred_username, "org": org.org_id}

        with TestClient(app) as client:
            resp = client.get("/probe")

        assert resp.status_code == 200, resp.text
        assert call_count["n"] == 1


class TestRoleEnforcement:
    def test_or_logic_grants_when_any_role_matches(self):
        def _stub_verify() -> AuthenticatedUser:
            return AuthenticatedUser(
                sub="user-1",
                preferred_username="alice",
                roles=["stream.read"],
                org_id="org-1",
                org_name="Acme",
            )

        app = FastAPI()
        app.dependency_overrides[verify_internal_jwt] = _stub_verify

        @app.get("/probe")
        def _probe(
            user: AuthenticatedUser = Depends(get_current_user(required_roles=["stream.read", "stream.write"])),
        ):
            return {"u": user.preferred_username}

        with TestClient(app) as client:
            resp = client.get("/probe")

        assert resp.status_code == 200

    def test_denies_when_no_role_matches(self):
        def _stub_verify() -> AuthenticatedUser:
            return AuthenticatedUser(
                sub="user-1",
                preferred_username="alice",
                roles=["other.role"],
                org_id="org-1",
                org_name="Acme",
            )

        app = FastAPI()
        app.dependency_overrides[verify_internal_jwt] = _stub_verify

        @app.get("/probe")
        def _probe(
            user: AuthenticatedUser = Depends(get_current_user(required_roles=["stream.read"])),
        ):
            return {"u": user.preferred_username}

        with TestClient(app) as client:
            resp = client.get("/probe")

        assert resp.status_code == 403
