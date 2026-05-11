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

    def test_stream_cost_teams_default(self):
        s = _make_settings()
        assert s.STREAM_COST_TEAMS == 5

    def test_stream_cost_slack_default(self):
        s = _make_settings()
        assert s.STREAM_COST_SLACK == 5

    def test_stream_cost_webhook_default(self):
        s = _make_settings()
        assert s.STREAM_COST_WEBHOOK == 2


# Env keys that Pydantic should fall back to class defaults for in default-tests.
# Includes both empty-string defaults (NP6_*, STREAM_UNSUBSCRIBE_SECRET) and
# non-empty defaults (NP6_BAT_TEST_SEGMENT_ID, STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH):
# we delete the env keys entirely so the class default wins, instead of overriding
# with "" which would corrupt the int defaults.
_NP6_ENV_KEYS = (
    "NP6_BASE_URL",
    "NP6_API_KEY",
    "NP6_FROM_EMAIL",
    "NP6_BAT_TEST_SEGMENT_ID",
    "STREAM_UNSUBSCRIBE_SECRET",
    "STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH",
)


def _make_settings_without_np6_env(**overrides):
    """Build Settings with newsletter env vars unset — tests genuine class defaults regardless of CI env."""
    saved = {key: os.environ.pop(key, None) for key in _NP6_ENV_KEYS}
    try:
        return _make_settings(**overrides)
    finally:
        for key, value in saved.items():
            if value is not None:
                os.environ[key] = value


class TestNewsletterDefaults:
    """ADR-0021 Phase 1a — newsletter / NP6 settings must default to safe empties.

    Absence of NP6 config in dev must not block Stream boot. The runtime modules
    are responsible for failing fast when these values are actually required.
    """

    def test_np6_base_url_defaults_empty(self):
        s = _make_settings_without_np6_env()
        assert s.NP6_BASE_URL == ""

    def test_np6_api_key_defaults_empty(self):
        s = _make_settings_without_np6_env()
        assert s.NP6_API_KEY == ""

    def test_np6_from_email_defaults_empty(self):
        s = _make_settings_without_np6_env()
        assert s.NP6_FROM_EMAIL == ""

    def test_np6_bat_test_segment_id_default(self):
        # Tenant CHAP/02C: id 1319 is the "BAT TEST" segment used in phase-1 validation.
        s = _make_settings_without_np6_env()
        assert s.NP6_BAT_TEST_SEGMENT_ID == 1319

    def test_stream_unsubscribe_secret_defaults_empty(self):
        s = _make_settings_without_np6_env()
        assert s.STREAM_UNSUBSCRIBE_SECRET == ""

    def test_max_events_per_batch_defaults_to_50(self):
        s = _make_settings_without_np6_env()
        assert s.STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH == 50


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

    def test_stream_cost_override(self):
        from app.core.config import Settings

        with patch.dict(os.environ, {"STREAM_COST_TEAMS": "10", "STREAM_COST_WEBHOOK": "1"}):
            s = Settings(_env_file=None)
            assert s.STREAM_COST_TEAMS == 10
            assert s.STREAM_COST_WEBHOOK == 1

    def test_np6_settings_override(self):
        from app.core.config import Settings

        env = {
            "NP6_BASE_URL": "https://np6.example",
            "NP6_API_KEY": "secret-key",
            "NP6_FROM_EMAIL": "noreply@chapsmind.fr",
        }
        with patch.dict(os.environ, env):
            s = Settings(_env_file=None)
            assert s.NP6_BASE_URL == "https://np6.example"
            assert s.NP6_API_KEY == "secret-key"
            assert s.NP6_FROM_EMAIL == "noreply@chapsmind.fr"

    def test_unsubscribe_secret_override(self):
        from app.core.config import Settings

        with patch.dict(os.environ, {"STREAM_UNSUBSCRIBE_SECRET": "rotate-me"}):
            s = Settings(_env_file=None)
            assert s.STREAM_UNSUBSCRIBE_SECRET == "rotate-me"

    def test_max_events_per_batch_override(self):
        from app.core.config import Settings

        with patch.dict(os.environ, {"STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH": "25"}):
            s = Settings(_env_file=None)
            assert s.STREAM_NEWSLETTER_MAX_EVENTS_PER_BATCH == 25
