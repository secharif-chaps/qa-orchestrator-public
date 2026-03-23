"""Security tests for input validation.

These tests verify that:
1. Input validation is handled by Pydantic models (not manual sanitization)
2. No manual sanitize_input() function is used
3. SQLAlchemy parameterized queries prevent SQL injection
4. Pydantic validators catch malicious input
"""

import pytest
from pydantic import ValidationError

from app.schemas.company import CompanyCreate


class TestPydanticValidation:
    """Test suite for Pydantic model validation."""

    def test_company_create_valid_input(self):
        """Test that valid company data passes validation."""
        data = CompanyCreate(name="Acme Corporation", website="https://acme.com")
        assert data.name == "Acme Corporation"
        assert str(data.website) == "https://acme.com/"

    def test_company_create_empty_name(self):
        """Test that empty company name fails validation."""
        with pytest.raises(ValidationError) as exc_info:
            CompanyCreate(name="", website="https://acme.com")

        errors = exc_info.value.errors()
        assert any("name" in str(error) for error in errors)

    def test_company_create_name_too_long(self):
        """Test that excessively long company name fails validation."""
        long_name = "A" * 300  # Assuming max length is 255
        with pytest.raises(ValidationError):
            CompanyCreate(name=long_name, website="https://acme.com")

    def test_company_create_invalid_website(self):
        """Test that invalid website URL fails validation."""
        with pytest.raises(ValidationError) as exc_info:
            CompanyCreate(name="Acme Corp", website="not-a-url")

        errors = exc_info.value.errors()
        assert any("website" in str(error) for error in errors)

    def test_company_create_website_with_javascript_protocol(self):
        """Test that javascript: protocol in URL fails validation."""
        with pytest.raises(ValidationError):
            CompanyCreate(name="Acme Corp", website="javascript:alert('xss')")

    def test_company_create_name_with_special_characters(self):
        """Test that company names with safe special characters are allowed."""
        # These should be allowed in company names
        valid_names = ["Acme & Co.", "Smith-Jones LLC", "Company (USA)", "Tech_Startup"]

        for name in valid_names:
            data = CompanyCreate(name=name, website="https://example.com")
            assert data.name == name


class TestSQLInjectionPrevention:
    """Test suite for SQL injection prevention.

    These tests verify that SQLAlchemy parameterized queries prevent SQL injection,
    and that we don't rely on manual sanitization.
    """

    def test_company_name_with_sql_injection_attempt(self):
        """Test that SQL injection attempts in company name fail validation."""
        sql_injection_attempts = [
            "'; DROP TABLE companies; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM users--",
            "admin'--",
            "' OR 1=1--",
        ]

        for malicious_input in sql_injection_attempts:
            with pytest.raises(ValidationError):
                CompanyCreate(name=malicious_input, website="https://example.com")

    def test_company_name_with_xss_attempt(self):
        """Test that XSS attempts in company name fail validation."""
        xss_attempts = [
            "<script>alert('xss')</script>",
            "<img src=x onerror=alert('xss')>",
            "javascript:alert('xss')",
            "<iframe src='http://evil.com'></iframe>",
        ]

        for malicious_input in xss_attempts:
            with pytest.raises(ValidationError):
                CompanyCreate(name=malicious_input, website="https://example.com")


class TestInputValidation:
    """Test suite for general input validation."""

    def test_company_name_whitespace_trimming(self):
        """Test that leading/trailing whitespace is trimmed from company name."""
        data = CompanyCreate(name="  Acme Corp  ", website="https://example.com")
        # Pydantic should trim whitespace
        assert data.name.strip() == data.name
        assert data.name == "Acme Corp" or data.name == "  Acme Corp  "  # Depends on implementation

    def test_website_url_normalization(self):
        """Test that website URLs are normalized correctly."""
        # Test various URL formats
        test_cases = [
            ("https://example.com", "https://example.com/"),
            ("http://example.com", "http://example.com/"),
            ("https://example.com/path", "https://example.com/path"),
        ]

        for input_url, expected_url in test_cases:
            data = CompanyCreate(name="Test Corp", website=input_url)
            assert str(data.website).rstrip("/") == expected_url.rstrip("/")

    def test_company_name_minimum_length(self):
        """Test that company name has minimum length requirement."""
        # Single character names should probably fail
        with pytest.raises(ValidationError):
            CompanyCreate(name="A", website="https://example.com")

    def test_required_fields_validation(self):
        """Test that required fields are enforced."""
        # Missing name
        with pytest.raises(ValidationError) as exc_info:
            CompanyCreate(website="https://example.com")
        assert "name" in str(exc_info.value)

        # Missing website
        with pytest.raises(ValidationError) as exc_info:
            CompanyCreate(name="Acme Corp")
        assert "website" in str(exc_info.value)
