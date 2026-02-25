"""Email domain validation tests for ChapsVision restriction.

These tests verify the is_chapsvision_email() helper function which validates
whether a user email belongs to the ChapsVision domain (@chapsvision.com).

This is used for restricting admin.organizations permission to ChapsVision employees only.
"""

import pytest
from app.core.email_utils import is_chapsvision_email


class TestEmailDomainValidation:
    """Test suite for ChapsVision email domain validation."""

    def test_valid_chapsvision_email(self):
        """Test that valid ChapsVision email returns True."""
        assert is_chapsvision_email("adnane.saber@chapsvision.com") is True

    def test_non_chapsvision_email(self):
        """Test that non-ChapsVision email returns False."""
        assert is_chapsvision_email("user@example.com") is False

    def test_none_email_returns_false(self):
        """Test that None email gracefully returns False."""
        assert is_chapsvision_email(None) is False

    def test_case_insensitive_check(self):
        """Test that email domain check is case-insensitive."""
        assert is_chapsvision_email("user@CHAPSVISION.COM") is True
        assert is_chapsvision_email("user@ChapsVision.Com") is True
        assert is_chapsvision_email("User@ChapsVision.COM") is True

    def test_empty_string_returns_false(self):
        """Test that empty string gracefully returns False."""
        assert is_chapsvision_email("") is False

    def test_whitespace_only_returns_false(self):
        """Test that whitespace-only string returns False."""
        assert is_chapsvision_email("   ") is False
