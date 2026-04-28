"""Tests for the internal JWT verification module.

Mirrors apps/screen/tests/unit/test_internal_jwt.py — the two services share
the same security primitives (only the local settings import differs).

Tests cover:
1. Token verification (valid / expired / bad signature / bad issuer / missing claims)
2. is_internal_request detection
3. IP allowlist (disabled / in-range / out-of-range / X-Forwarded-For / X-Real-IP / invalid / missing)
4. validate_internal_auth_config startup fail-fast logic
5. _get_client_ip extraction precedence
"""

import time
from unittest.mock import MagicMock, patch

import jwt as pyjwt
import pytest
from fastapi import Request

from app.core.internal_jwt import (
    ALGORITHM,
    INTERNAL_AUTH_PREFIX,
    ISSUER,
    InternalJWTError,
    InternalTokenPayload,
    IPNotAllowedError,
    TokenExpiredError,
    TokenInvalidError,
    _get_allowed_networks,
    _get_client_ip,
    create_internal_token,
    is_internal_request,
    validate_internal_auth_config,
    validate_source_ip,
    verify_internal_request,
    verify_internal_token,
)


@pytest.fixture(autouse=True)
def reset_network_cache():
    """Reset the IP-allowlist memoization before/after each test."""
    _get_allowed_networks.cache_clear()
    yield
    _get_allowed_networks.cache_clear()


class TestIsInternalRequest:
    """Detect requests carrying an Internal authorization header."""

    def test_detects_internal_request(self):
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Internal some-token-here"}

        assert is_internal_request(mock_request) is True

    def test_rejects_bearer_token(self):
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Bearer some-token"}

        assert is_internal_request(mock_request) is False

    def test_rejects_missing_header(self):
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}

        assert is_internal_request(mock_request) is False


class TestVerifyInternalToken:
    @pytest.fixture
    def mock_settings(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_ALLOWED_IPS = ""
            yield mock

    def _create_test_token(
        self,
        secret: str = "test-secret-at-least-32-characters-long",
        expired: bool = False,
        invalid_issuer: bool = False,
        missing_claims: bool = False,
    ) -> str:
        now = int(time.time())
        payload = {
            "sub": "user-123",
            "username": "testuser",
            "email": "test@example.com",
            "org_id": "org-456",
            "org_name": "Test Org",
            "roles": ["stream.read"],
            "iss": "invalid-issuer" if invalid_issuer else ISSUER,
            "iat": now,
            "exp": now - 100 if expired else now + 60,
        }

        if missing_claims:
            del payload["org_id"]

        return pyjwt.encode(payload, secret, algorithm=ALGORITHM)

    def test_verifies_valid_token(self, mock_settings):
        token = self._create_test_token()

        payload = verify_internal_token(token)

        assert payload.sub == "user-123"
        assert payload.username == "testuser"
        assert payload.email == "test@example.com"
        assert payload.org_id == "org-456"
        assert payload.org_name == "Test Org"
        assert payload.roles == ["stream.read"]
        assert payload.iss == ISSUER

    def test_rejects_expired_token(self, mock_settings):
        token = self._create_test_token(expired=True)

        with pytest.raises(TokenExpiredError, match="expired"):
            verify_internal_token(token)

    def test_rejects_invalid_signature(self, mock_settings):
        token = self._create_test_token(secret="wrong-secret-also-32-characters-long")

        with pytest.raises(TokenInvalidError):
            verify_internal_token(token)

    def test_rejects_invalid_issuer(self, mock_settings):
        token = self._create_test_token(invalid_issuer=True)

        with pytest.raises(TokenInvalidError, match="issuer"):
            verify_internal_token(token)

    def test_rejects_missing_claims(self, mock_settings):
        token = self._create_test_token(missing_claims=True)

        with pytest.raises(TokenInvalidError):
            verify_internal_token(token)

    def test_raises_error_without_secret(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = ""

            with pytest.raises(InternalJWTError, match="not configured"):
                verify_internal_token("any-token")


class TestCreateInternalToken:
    @pytest.fixture
    def mock_settings(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 300
            yield mock

    def test_creates_valid_token(self, mock_settings):
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=["stream.read"],
            email="test@example.com",
        )

        payload = verify_internal_token(token)
        assert payload.sub == "user-123"
        assert payload.org_id == "org-456"
        assert payload.roles == ["stream.read"]

    def test_creates_token_with_defaults(self, mock_settings):
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
        )

        payload = verify_internal_token(token)
        assert payload.org_name == "Unknown"
        assert payload.roles == []
        assert payload.email is None


class TestInternalTokenPayload:
    def test_payload_defaults(self):
        payload = InternalTokenPayload(
            sub="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )

        assert payload.email is None
        assert payload.iss == ISSUER
        assert payload.iat == 0
        assert payload.exp == 0


class TestIPValidation:
    """Layer-1 defense — restrict where Internal JWTs can be replayed from."""

    def _create_mock_request(self, client_ip=None, forwarded_for=None, real_ip=None):
        mock_request = MagicMock(spec=Request)
        headers = {}
        if forwarded_for:
            headers["X-Forwarded-For"] = forwarded_for
        if real_ip:
            headers["X-Real-IP"] = real_ip
        mock_request.headers = headers
        if client_ip:
            mock_request.client = MagicMock()
            mock_request.client.host = client_ip
        else:
            mock_request.client = None
        return mock_request

    def test_ip_validation_disabled_when_not_configured(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = ""

            request = self._create_mock_request(client_ip="192.168.1.100")

            validate_source_ip(request)

    def test_allows_ip_in_range(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16,10.0.0.0/8"

            request = self._create_mock_request(client_ip="192.168.1.100")

            validate_source_ip(request)

    def test_rejects_ip_not_in_range(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            request = self._create_mock_request(client_ip="10.0.0.1")

            with pytest.raises(IPNotAllowedError):
                validate_source_ip(request)

    def test_uses_x_forwarded_for_header(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            request = self._create_mock_request(
                client_ip="10.0.0.1",
                forwarded_for="192.168.1.100, 10.0.0.1",
            )

            validate_source_ip(request)

    def test_uses_x_real_ip_header(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            request = self._create_mock_request(
                client_ip="10.0.0.1",
                real_ip="192.168.1.100",
            )

            validate_source_ip(request)

    def test_rejects_invalid_ip_format(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            request = self._create_mock_request(client_ip="not-an-ip")

            with pytest.raises(IPNotAllowedError, match="Invalid IP"):
                validate_source_ip(request)

    def test_rejects_missing_ip(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            request = self._create_mock_request()

            with pytest.raises(IPNotAllowedError, match="Could not determine"):
                validate_source_ip(request)


class TestGetClientIP:
    def _create_mock_request(self, client_ip=None, forwarded_for=None, real_ip=None):
        mock_request = MagicMock(spec=Request)
        headers = {}
        if forwarded_for:
            headers["X-Forwarded-For"] = forwarded_for
        if real_ip:
            headers["X-Real-IP"] = real_ip
        mock_request.headers = headers
        if client_ip:
            mock_request.client = MagicMock()
            mock_request.client.host = client_ip
        else:
            mock_request.client = None
        return mock_request

    def test_prefers_x_forwarded_for(self):
        request = self._create_mock_request(
            client_ip="10.0.0.1",
            forwarded_for="192.168.1.100, 10.0.0.1",
            real_ip="172.16.0.1",
        )

        assert _get_client_ip(request) == "192.168.1.100"

    def test_uses_x_real_ip_as_fallback(self):
        request = self._create_mock_request(
            client_ip="10.0.0.1",
            real_ip="172.16.0.1",
        )

        assert _get_client_ip(request) == "172.16.0.1"

    def test_uses_client_host_as_last_resort(self):
        request = self._create_mock_request(client_ip="10.0.0.1")

        assert _get_client_ip(request) == "10.0.0.1"

    def test_returns_empty_when_no_ip_available(self):
        request = self._create_mock_request()

        assert _get_client_ip(request) == ""


class TestVerifyInternalRequest:
    @pytest.fixture
    def mock_settings(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_ALLOWED_IPS = ""
            yield mock

    def _create_test_token(self) -> str:
        now = int(time.time())
        payload = {
            "sub": "user-123",
            "username": "testuser",
            "email": "test@example.com",
            "org_id": "org-456",
            "org_name": "Test Org",
            "roles": ["stream.read"],
            "iss": ISSUER,
            "iat": now,
            "exp": now + 60,
        }
        return pyjwt.encode(payload, "test-secret-at-least-32-characters-long", algorithm=ALGORITHM)

    def _create_mock_request(self, token: str, client_ip: str = "192.168.1.100"):
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": f"{INTERNAL_AUTH_PREFIX}{token}"}
        mock_request.client = MagicMock()
        mock_request.client.host = client_ip
        return mock_request

    def test_verifies_valid_request(self, mock_settings):
        token = self._create_test_token()
        request = self._create_mock_request(token)

        payload = verify_internal_request(request)

        assert payload.sub == "user-123"

    def test_rejects_missing_auth_header(self, mock_settings):
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}
        mock_request.client = MagicMock()
        mock_request.client.host = "192.168.1.100"

        with pytest.raises(TokenInvalidError, match="Missing"):
            verify_internal_request(mock_request)


class TestValidateInternalAuthConfig:
    """Fail-fast startup hardening — defense in depth around the shared secret."""

    def test_raises_when_secret_missing(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = ""
            mock.DEV_MODE = True
            mock.INTERNAL_ALLOWED_IPS = ""

            with pytest.raises(InternalJWTError, match="not configured"):
                validate_internal_auth_config()

    def test_raises_when_secret_too_short(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "short"
            mock.DEV_MODE = True
            mock.INTERNAL_ALLOWED_IPS = ""

            with pytest.raises(InternalJWTError, match="too short"):
                validate_internal_auth_config()

    def test_raises_when_allowlist_empty_in_prod(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.DEV_MODE = False
            mock.INTERNAL_ALLOWED_IPS = ""

            with pytest.raises(InternalJWTError, match="INTERNAL_ALLOWED_IPS is empty"):
                validate_internal_auth_config()

    def test_passes_in_dev_mode_without_allowlist(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.DEV_MODE = True
            mock.INTERNAL_ALLOWED_IPS = ""

            validate_internal_auth_config()

    def test_passes_in_prod_with_allowlist(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.DEV_MODE = False
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"

            validate_internal_auth_config()


class TestGetAllowedNetworksEdgeCases:
    def test_skips_empty_cidrs(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16,,  ,10.0.0.0/8"

            networks = _get_allowed_networks()
            assert len(networks) == 2

    def test_handles_invalid_cidr(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "not-a-cidr,192.168.0.0/16"

            networks = _get_allowed_networks()
            assert len(networks) == 1
