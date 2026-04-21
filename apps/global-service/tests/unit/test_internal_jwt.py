"""
Tests for the internal JWT module.

Tests cover:
1. Token creation with valid inputs
2. Token expiration settings
3. Error handling for missing configuration
4. Token payload structure
"""

import pathlib
import time
from unittest.mock import MagicMock, patch

import jwt as pyjwt
import pytest

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


class TestInternalAuthSchemeContract:
    """Contract tests: verify Internal auth scheme is enforced.

    These tests ensure that services using internal JWT communication
    must use the "Internal" scheme (not "Bearer"). This catches
    mismatches like screen sending "Bearer {token}" while global-service
    expects "Internal {token}".
    """

    @pytest.fixture
    def mock_settings(self):
        with patch("app.core.internal_jwt.settings") as mock:
            mock.INTERNAL_JWT_SECRET = "test-secret-at-least-32-characters-long"
            mock.INTERNAL_JWT_EXPIRY_SECONDS = 60
            yield mock

    @pytest.mark.asyncio
    async def test_internal_scheme_accepted(self, mock_settings):
        """Internal scheme must be accepted by get_internal_token."""
        from app.core.internal_jwt import get_internal_token

        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=["company.view"],
        )

        payload = await get_internal_token(f"Internal {token}")
        assert payload.sub == "user-123"
        assert payload.username == "testuser"

    @pytest.mark.asyncio
    async def test_bearer_scheme_rejected(self, mock_settings):
        """Bearer scheme must be rejected — internal endpoints use Internal scheme only."""
        from fastapi import HTTPException
        from app.core.internal_jwt import get_internal_token

        token = create_internal_token(
            user_id="user-123",
            username="testuser",
            org_id="org-456",
            org_name="Test Org",
            roles=[],
        )

        with pytest.raises(HTTPException) as exc_info:
            await get_internal_token(f"Bearer {token}")

        assert exc_info.value.status_code == 401
        assert "Internal" in exc_info.value.detail

    @pytest.mark.asyncio
    async def test_empty_authorization_rejected(self, mock_settings):
        """Empty authorization header must be rejected."""
        from fastapi import HTTPException
        from app.core.internal_jwt import get_internal_token

        with pytest.raises(HTTPException) as exc_info:
            await get_internal_token("")

        assert exc_info.value.status_code == 401

    @pytest.mark.asyncio
    async def test_missing_token_value_rejected(self, mock_settings):
        """Scheme without token value must be rejected."""
        from fastapi import HTTPException
        from app.core.internal_jwt import get_internal_token

        with pytest.raises(HTTPException) as exc_info:
            await get_internal_token("Internal")

        assert exc_info.value.status_code == 401


class TestInternalSchemeSourceContract:
    """Contract tests: verify all modules use Internal scheme for service-to-service calls.

    Scans Python source files across all app modules for Authorization headers
    that incorrectly use Bearer for internal JWT tokens (created via create_internal_token).
    This prevents regressions where someone changes Internal back to Bearer.

    Excluded from scanning:
    - keycloak_admin.py (uses Bearer for Keycloak Admin API — legitimate)
    - tests/ (test files may intentionally use Bearer to test rejection)
    """

    EXCLUDED_PATTERNS = ["keycloak_admin", "/tests/"]

    @staticmethod
    def _find_monorepo_root() -> pathlib.Path | None:
        """Walk up from this file to find the monorepo root (has apps/ dir with subdirs)."""
        for parent in pathlib.Path(__file__).resolve().parents:
            apps_dir = parent / "apps"
            if apps_dir.is_dir() and any(apps_dir.iterdir()):
                return parent
        return None

    def _scan_module(self, root: pathlib.Path, module: str) -> list[str]:
        """Scan a module's source for Bearer used with internal tokens."""
        module_dir = root / "apps" / module / "app"
        if not module_dir.exists():
            return []

        offending: list[str] = []
        for py_file in module_dir.rglob("*.py"):
            rel = str(py_file.relative_to(root))
            if any(excl in rel for excl in self.EXCLUDED_PATTERNS):
                continue

            content = py_file.read_text()
            for i, line in enumerate(content.splitlines(), 1):
                if '"Authorization"' in line and "Bearer" in line and "internal" in line.lower():
                    offending.append(f"{rel}:{i}: {line.strip()}")

        return offending

    def test_no_bearer_for_internal_calls_across_modules(self):
        """No module should use Bearer scheme for internal service-to-service calls."""
        root = self._find_monorepo_root()
        if root is None:
            pytest.skip("Monorepo root not found (running in Docker container)")

        apps_dir = root / "apps"
        modules = [d.name for d in apps_dir.iterdir() if d.is_dir() and (d / "app").is_dir()]

        all_offending: list[str] = []
        for module in modules:
            all_offending.extend(self._scan_module(root, module))

        assert all_offending == [], (
            "Found Bearer scheme used for internal service calls. "
            "All internal JWT calls must use 'Internal' scheme.\n"
            "Offending lines:\n" + "\n".join(all_offending)
        )


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
