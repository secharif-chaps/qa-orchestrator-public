"""
Tests for the internal JWT verification module.

Tests cover:
1. Token verification with valid tokens
2. Token expiration handling
3. Invalid token handling (bad signature, malformed)
4. IP validation (when configured)
5. Error handling for missing configuration
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
    is_internal_request,
    validate_source_ip,
    verify_internal_request,
    verify_internal_token,
)


# Reset the cached networks before each test
@pytest.fixture(autouse=True)
def reset_network_cache():
    """Reset the network cache before each test."""
    import app.core.internal_jwt as jwt_module

    jwt_module._allowed_networks = None
    jwt_module._allowed_networks_parsed = False
    yield


class TestIsInternalRequest:
    """Test internal request detection."""

    def test_detects_internal_request(self):
        """Test detection of internal Authorization header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Internal some-token-here"}

        assert is_internal_request(mock_request) is True

    def test_rejects_bearer_token(self):
        """Test that Bearer tokens are not detected as internal."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Bearer some-token"}

        assert is_internal_request(mock_request) is False

    def test_rejects_missing_header(self):
        """Test handling of missing Authorization header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}

        assert is_internal_request(mock_request) is False

    def test_rejects_empty_header(self):
        """Test handling of empty Authorization header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": ""}

        assert is_internal_request(mock_request) is False


class TestVerifyInternalToken:
    """Test internal token verification."""

    @pytest.fixture
    def mock_settings(self):
        """Mock settings with valid configuration."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_ALLOWED_IPS = ""  # Disable IP validation for these tests
            yield mock

    def _create_test_token(
        self,
        secret: str = "test-secret-at-least-32-characters-long",
        expired: bool = False,
        invalid_issuer: bool = False,
        missing_claims: bool = False,
    ) -> str:
        """Create a test token for verification tests."""
        now = int(time.time())

        payload = {
            "sub": "user-123",
            "username": "testuser",
            "email": "test@example.com",
            "org_id": "org-456",
            "org_name": "Test Org",
            "roles": ["company.view", "company.create"],
            "iss": "invalid-issuer" if invalid_issuer else ISSUER,
            "iat": now,
            "exp": now - 100 if expired else now + 60,
        }

        if missing_claims:
            del payload["org_id"]

        return pyjwt.encode(payload, secret, algorithm=ALGORITHM)

    def test_verifies_valid_token(self, mock_settings):
        """Test verification of a valid token."""
        token = self._create_test_token()

        payload = verify_internal_token(token)

        assert payload.sub == "user-123"
        assert payload.username == "testuser"
        assert payload.email == "test@example.com"
        assert payload.org_id == "org-456"
        assert payload.org_name == "Test Org"
        assert payload.roles == ["company.view", "company.create"]
        assert payload.iss == ISSUER

    def test_rejects_expired_token(self, mock_settings):
        """Test rejection of expired tokens."""
        token = self._create_test_token(expired=True)

        with pytest.raises(TokenExpiredError, match="expired"):
            verify_internal_token(token)

    def test_rejects_invalid_signature(self, mock_settings):
        """Test rejection of tokens with invalid signature."""
        token = self._create_test_token(secret="wrong-secret-also-32-characters-long")

        with pytest.raises(TokenInvalidError):
            verify_internal_token(token)

    def test_rejects_invalid_issuer(self, mock_settings):
        """Test rejection of tokens with invalid issuer."""
        token = self._create_test_token(invalid_issuer=True)

        with pytest.raises(TokenInvalidError, match="issuer"):
            verify_internal_token(token)

    def test_rejects_missing_claims(self, mock_settings):
        """Test rejection of tokens missing required claims."""
        token = self._create_test_token(missing_claims=True)

        with pytest.raises(TokenInvalidError):
            verify_internal_token(token)

    def test_raises_error_without_secret(self):
        """Test that error is raised when secret is not configured."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = ""

            with pytest.raises(InternalJWTError, match="not configured"):
                verify_internal_token("any-token")


class TestInternalTokenPayload:
    """Test InternalTokenPayload model."""

    def test_payload_model_creation(self):
        """Test that payload model can be created."""
        payload = InternalTokenPayload(
            sub="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=["admin"],
            email="test@example.com",
        )

        assert payload.sub == "user-123"
        assert payload.username == "testuser"
        assert payload.org_id == "org-456"
        assert payload.org_name == "Test Org"
        assert payload.roles == ["admin"]
        assert payload.email == "test@example.com"
        assert payload.iss == ISSUER

    def test_payload_defaults(self):
        """Test that payload has correct defaults."""
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
    """Test IP allowlist validation."""

    def _create_mock_request(self, client_ip: str = None, forwarded_for: str = None, real_ip: str = None):
        """Create a mock request with IP headers."""
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
        """Test that IP validation is skipped when INTERNAL_ALLOWED_IPS is empty."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = ""
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request(client_ip="192.168.1.100")

            # Should not raise any exception
            validate_source_ip(request)

    def test_allows_ip_in_range(self):
        """Test that IPs in allowed range are accepted."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16,10.0.0.0/8"
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request(client_ip="192.168.1.100")

            # Should not raise any exception
            validate_source_ip(request)

    def test_rejects_ip_not_in_range(self):
        """Test that IPs not in allowed range are rejected."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request(client_ip="10.0.0.1")

            with pytest.raises(IPNotAllowedError):
                validate_source_ip(request)

    def test_uses_x_forwarded_for_header(self):
        """Test that X-Forwarded-For header is checked first."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"
            _get_allowed_networks.cache_clear()

            # X-Forwarded-For is in allowed range, but client IP is not
            request = self._create_mock_request(
                client_ip="10.0.0.1",
                forwarded_for="192.168.1.100, 10.0.0.1",
            )

            # Should use X-Forwarded-For (first IP)
            validate_source_ip(request)

    def test_uses_x_real_ip_header(self):
        """Test that X-Real-IP header is used as fallback."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request(
                client_ip="10.0.0.1",
                real_ip="192.168.1.100",
            )

            # Should use X-Real-IP
            validate_source_ip(request)

    def test_rejects_invalid_ip_format(self):
        """Test that invalid IP format is rejected."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request(client_ip="not-an-ip")

            with pytest.raises(IPNotAllowedError, match="Invalid IP"):
                validate_source_ip(request)

    def test_rejects_missing_ip(self):
        """Test that missing IP is rejected."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_ALLOWED_IPS = "192.168.0.0/16"
            _get_allowed_networks.cache_clear()

            request = self._create_mock_request()  # No IP at all

            with pytest.raises(IPNotAllowedError, match="Could not determine"):
                validate_source_ip(request)


class TestGetClientIP:
    """Test client IP extraction."""

    def _create_mock_request(self, client_ip: str = None, forwarded_for: str = None, real_ip: str = None):
        """Create a mock request with IP headers."""
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
        """Test that X-Forwarded-For is checked first."""
        request = self._create_mock_request(
            client_ip="10.0.0.1",
            forwarded_for="192.168.1.100, 10.0.0.1",
            real_ip="172.16.0.1",
        )

        ip = _get_client_ip(request)
        assert ip == "192.168.1.100"

    def test_uses_x_real_ip_as_fallback(self):
        """Test that X-Real-IP is used when X-Forwarded-For is missing."""
        request = self._create_mock_request(
            client_ip="10.0.0.1",
            real_ip="172.16.0.1",
        )

        ip = _get_client_ip(request)
        assert ip == "172.16.0.1"

    def test_uses_client_host_as_last_resort(self):
        """Test that client.host is used when headers are missing."""
        request = self._create_mock_request(client_ip="10.0.0.1")

        ip = _get_client_ip(request)
        assert ip == "10.0.0.1"

    def test_returns_empty_when_no_ip_available(self):
        """Test that empty string is returned when no IP is available."""
        request = self._create_mock_request()

        ip = _get_client_ip(request)
        assert ip == ""


class TestVerifyInternalRequest:
    """Test full internal request verification (IP + JWT)."""

    @pytest.fixture
    def mock_settings(self):
        """Mock settings with valid configuration."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_ALLOWED_IPS = ""  # Disable IP validation
            yield mock

    def _create_test_token(self) -> str:
        """Create a valid test token."""
        now = int(time.time())
        payload = {
            "sub": "user-123",
            "username": "testuser",
            "email": "test@example.com",
            "org_id": "org-456",
            "org_name": "Test Org",
            "roles": ["company.view"],
            "iss": ISSUER,
            "iat": now,
            "exp": now + 60,
        }
        return pyjwt.encode(payload, "test-secret-at-least-32-characters-long", algorithm=ALGORITHM)

    def _create_mock_request(self, token: str, client_ip: str = "192.168.1.100"):
        """Create a mock request with internal auth header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": f"{INTERNAL_AUTH_PREFIX}{token}"}
        mock_request.client = MagicMock()
        mock_request.client.host = client_ip
        return mock_request

    def test_verifies_valid_request(self, mock_settings):
        """Test verification of valid internal request."""
        token = self._create_test_token()
        request = self._create_mock_request(token)

        payload = verify_internal_request(request)

        assert payload.sub == "user-123"
        assert payload.username == "testuser"

    def test_rejects_missing_auth_header(self, mock_settings):
        """Test rejection of request without internal auth header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {}
        mock_request.client = MagicMock()
        mock_request.client.host = "192.168.1.100"

        with pytest.raises(TokenInvalidError, match="Missing"):
            verify_internal_request(mock_request)

    def test_rejects_bearer_auth_header(self, mock_settings):
        """Test rejection of request with Bearer auth header."""
        mock_request = MagicMock(spec=Request)
        mock_request.headers = {"Authorization": "Bearer some-token"}
        mock_request.client = MagicMock()
        mock_request.client.host = "192.168.1.100"

        with pytest.raises(TokenInvalidError, match="Missing"):
            verify_internal_request(mock_request)


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
