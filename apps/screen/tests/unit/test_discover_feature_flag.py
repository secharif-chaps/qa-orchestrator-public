"""Tests for DISCOVER feature flag enum and API integration.

This module contains tests for:
- Task Group 1: FeatureFlag enum with DISCOVER value
- Task Group 2: API schema validation for config with HTTPS URL
"""

import pytest
from pydantic import ValidationError

from app.models.organization import FeatureFlag


class TestFeatureFlagEnum:
    """Tests for FeatureFlag enum with DISCOVER value (Task Group 1)."""

    def test_discover_enum_value_exists(self):
        """Test that DISCOVER enum value exists in FeatureFlag."""
        assert hasattr(FeatureFlag, "DISCOVER")
        assert FeatureFlag.DISCOVER is not None

    def test_discover_enum_serializes_to_string(self):
        """Test that DISCOVER enum value serializes correctly to 'discover' string."""
        assert FeatureFlag.DISCOVER.value == "discover"
        assert str(FeatureFlag.DISCOVER.value) == "discover"

    def test_existing_translation_flag_unchanged(self):
        """Test that existing TRANSLATION feature flag still works correctly."""
        assert hasattr(FeatureFlag, "TRANSLATION")
        assert FeatureFlag.TRANSLATION.value == "translation"


class TestFeatureFlagApiConfig:
    """Tests for API config handling with URL validation (Task Group 2)."""

    def test_valid_https_url_accepted_in_config(self):
        """Test that valid HTTPS URL is accepted in config."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # Valid HTTPS URLs should be accepted
        valid_urls = [
            "https://discover.example.com",
            "https://discover.example.com/dashboard",
            "https://discover.example.com:8443/app",
            "https://sub.domain.example.com/path?query=value",
        ]

        for url in valid_urls:
            request = FeatureFlagToggleRequest(
                enabled=True, config={"url": url}
            )
            assert request.enabled is True
            assert request.config["url"] == url

    def test_invalid_http_url_rejected(self):
        """Test that HTTP (non-HTTPS) URL is rejected with validation error."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # HTTP URLs should be rejected
        with pytest.raises(ValidationError) as exc_info:
            FeatureFlagToggleRequest(
                enabled=True, config={"url": "http://insecure.example.com"}
            )

        errors = exc_info.value.errors()
        assert len(errors) > 0
        # Check that the error is about HTTPS requirement
        assert any("https" in str(e).lower() for e in errors)

    def test_malformed_url_rejected(self):
        """Test that malformed URLs are rejected with validation error."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # Malformed URLs should be rejected
        invalid_urls = [
            "not-a-url",
            "ftp://ftp.example.com",
            "://missing-scheme.com",
            "",
        ]

        for url in invalid_urls:
            with pytest.raises(ValidationError):
                FeatureFlagToggleRequest(enabled=True, config={"url": url})

    def test_toggle_without_config_still_works(self):
        """Test that existing toggle functionality (enabled=true/false) still works without config."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # Toggle without config should still work (backwards compatibility)
        request_enable = FeatureFlagToggleRequest(enabled=True)
        assert request_enable.enabled is True
        assert request_enable.config is None

        request_disable = FeatureFlagToggleRequest(enabled=False)
        assert request_disable.enabled is False
        assert request_disable.config is None

    def test_config_with_empty_dict_allowed(self):
        """Test that config with empty dict is allowed."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # Empty config should be allowed
        request = FeatureFlagToggleRequest(enabled=True, config={})
        assert request.enabled is True
        assert request.config == {}

    def test_config_without_url_allowed(self):
        """Test that config without url field is allowed (for future extensibility)."""
        from app.api.endpoints.feature_flags import FeatureFlagToggleRequest

        # Config with other fields (not url) should be allowed
        request = FeatureFlagToggleRequest(
            enabled=True, config={"some_other_field": "value"}
        )
        assert request.enabled is True
        assert request.config == {"some_other_field": "value"}
