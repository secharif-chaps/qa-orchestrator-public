"""Tests for Stream service configuration.

Covers:
- Default values are correct
- Database URL uses correct Docker hostname
- Settings can be overridden via environment variables
"""

import os
from unittest.mock import patch

def _make_settings(**overrides):
    """Create a Settings instance with env_file disabled to test pure defaults."""
    from app.core.config import Settings

    return Settings(_env_file=None, **overrides)


class TestDefaultSettings:
    """Tests for default configuration values."""

    def test_api_host_default(self):
        s = _make_settings()
        assert s.API_HOST == "0.0.0.0"

    def test_api_port_default(self):
        s = _make_settings()
        assert s.API_PORT == 8000

    def test_log_level_default(self):
        s = _make_settings()
        assert s.LOG_LEVEL == "INFO"

    def test_database_url_uses_db_hostname(self):
        """Database URL must use 'db' as hostname (Docker Compose service name)."""
        s = _make_settings()
        assert "@db:" in s.DATABASE_URL, f"Expected hostname 'db', got: {s.DATABASE_URL}"

    def test_database_url_uses_stream_db_name(self):
        """Database URL must target the stream_db database."""
        s = _make_settings()
        assert s.DATABASE_URL.endswith("/stream_db"), f"Expected database 'stream_db', got: {s.DATABASE_URL}"

    def test_global_service_url_default(self):
        s = _make_settings()
        assert s.GLOBAL_SERVICE_URL == "http://global-service:8000/api"


class TestSettingsOverride:
    """Tests for environment variable overrides."""

    def test_database_url_override(self):
        from app.core.config import Settings

        with patch.dict(os.environ, {"DATABASE_URL": "postgresql://user:pass@custom-host:5432/custom_db"}):
            s = Settings(_env_file=None)
            assert s.DATABASE_URL == "postgresql://user:pass@custom-host:5432/custom_db"

    def test_log_level_override(self):
        from app.core.config import Settings

        with patch.dict(os.environ, {"LOG_LEVEL": "DEBUG"}):
            s = Settings(_env_file=None)
            assert s.LOG_LEVEL == "DEBUG"
