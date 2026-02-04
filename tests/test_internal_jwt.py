"""
Tests for the internal JWT module.

Tests cover:
1. Token creation with valid inputs
2. Token expiration settings
3. Error handling for missing configuration
4. Token payload structure
"""

import pytest
import time
import jwt as pyjwt
from unittest.mock import patch, MagicMock

from app.core.internal_jwt import (
    create_internal_token,
    InternalJWTError,
    InternalTokenPayload,
    ALGORITHM,
    ISSUER,
)


class TestCreateInternalToken:
    """Test internal token creation."""

    @pytest.fixture
    def mock_settings(self):
        """Mock settings with valid configuration."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
            yield mock

    def test_creates_valid_token(self, mock_settings):
        """Test that a valid token is created with correct payload."""
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=["company.view", "company.create"],
            email="test@example.com",
        )

        # Token should be a non-empty string
        assert isinstance(token, str)
        assert len(token) > 0

        # Decode and verify payload
        payload = pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

        assert payload["sub"] == "user-123"
        assert payload["username"] == "testuser"
        assert payload["org_id"] == "org-456"
        assert payload["org_name"] == "Test Org"
        assert payload["roles"] == ["company.view", "company.create"]
        assert payload["email"] == "test@example.com"
        assert payload["iss"] == ISSUER

    def test_token_has_correct_expiry(self, mock_settings):
        """Test that token has correct expiry time."""
        mock_settings.INTERNAL_JWT_EXPIRY_SECONDS = 120

        before = int(time.time())
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )
        after = int(time.time())

        payload = pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

        # Expiry should be within 120 seconds of now
        assert payload["exp"] >= before + 120
        assert payload["exp"] <= after + 120

    def test_token_has_issued_at(self, mock_settings):
        """Test that token has issued at timestamp."""
        before = int(time.time())
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )
        after = int(time.time())

        payload = pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

        assert payload["iat"] >= before
        assert payload["iat"] <= after

    def test_token_optional_email(self, mock_settings):
        """Test that email is optional."""
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
            email=None,
        )

        payload = pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

        assert payload["email"] is None

    def test_token_empty_roles(self, mock_settings):
        """Test that empty roles list is handled."""
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )

        payload = pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

        assert payload["roles"] == []

    def test_raises_error_without_secret(self):
        """Test that error is raised when secret is not configured."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = ""
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60

            with pytest.raises(InternalJWTError, match="not configured"):
                create_internal_token(
                    user_id="user-123",
                    username="testuser",
                    org_id="org-456",
                    org_name="Test Org",
                    roles=[],
                )


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


class TestTokenSignature:
    """Test token signature verification."""

    @pytest.fixture
    def mock_settings(self):
        """Mock settings with valid configuration."""
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
            yield mock

    def test_token_signature_valid(self, mock_settings):
        """Test that token has valid signature."""
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )

        # Should not raise with correct secret
        pyjwt.decode(
            token,
            "test-secret-at-least-32-characters-long",
            algorithms=[ALGORITHM],
        )

    def test_token_signature_invalid_with_wrong_secret(self, mock_settings):
        """Test that token fails verification with wrong secret."""
        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )

        # Should raise with wrong secret
        with pytest.raises(pyjwt.InvalidSignatureError):
            pyjwt.decode(
                token,
                "wrong-secret-also-at-least-32-characters",
                algorithms=[ALGORITHM],
            )


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
