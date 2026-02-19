"""Tests for Dify error callback handling."""
import pytest
from app.services.dify_error_handler import DifyErrorHandler
from app.schemas.dify_errors import DifyErrorType


class TestDifyErrorHandler:
    """Test suite for DifyErrorHandler service."""

    @pytest.fixture
    def error_handler(self):
        """Create error handler instance with English language."""
        return DifyErrorHandler(language="en")

    @pytest.fixture
    def error_handler_fr(self):
        """Create error handler instance with French language."""
        return DifyErrorHandler(language="fr")

    def test_parse_rate_limit_error(self, error_handler):
        """Test parsing rate limit error with retry_after (English)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Too many requests to LLM service",
                    "retry_after": 120,
                    "service_name": "OpenAI GPT-4"
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=123,
            task_type="profile"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.RATE_LIMIT_LLM
        assert result.primary_error.retry_after_seconds == 120
        assert result.primary_error.is_recoverable is True
        assert result.has_whitelisted_error is True
        # Check English message
        assert "overloaded" in result.primary_error.user_message.lower() or "temporarily" in result.primary_error.user_message.lower()
        assert "2 minute" in result.primary_error.user_message.lower()

    def test_parse_rate_limit_error_french(self, error_handler_fr):
        """Test parsing rate limit error with retry_after (French)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Trop de requêtes au service LLM",
                    "retry_after": 120,
                    "service_name": "OpenAI GPT-4"
                }
            ]
        }

        result = error_handler_fr.parse_error_callback(
            callback_body=callback_body,
            task_id=123,
            task_type="profile"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.RATE_LIMIT_LLM
        # Check French message
        assert "saturé" in result.primary_error.user_message.lower()
        assert "2 minute" in result.primary_error.user_message.lower()

    def test_parse_auth_error(self, error_handler):
        """Test parsing authentication error (English)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "auth_invalid_key",
                    "error_message": "Invalid API key provided",
                    "http_status": 401
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=456,
            task_type="digital"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.AUTH_INVALID_KEY
        assert result.primary_error.is_recoverable is False
        assert result.has_whitelisted_error is True
        assert result.recommended_action == "contact_admin"
        assert "authentication" in result.primary_error.user_message.lower()

    def test_parse_multiple_errors(self, error_handler):
        """Test parsing multiple errors and priority selection."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "network_timeout",
                    "error_message": "Network timeout occurred"
                },
                {
                    "error_type": "rate_limit_api",
                    "error_message": "API rate limit exceeded",
                    "retry_after": 60
                },
                {
                    "error_type": "auth_invalid_key",
                    "error_message": "Authentication failed"
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=789,
            task_type="csr"
        )

        assert len(result.errors) == 3
        # Auth error should be primary (highest priority)
        assert result.primary_error.categorized_type == DifyErrorType.AUTH_INVALID_KEY
        assert result.has_whitelisted_error is True
        assert result.error_summary.startswith("3 erreurs")

    def test_parse_workflow_node_error(self, error_handler):
        """Test parsing workflow node error with node_id (English)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "workflow_node_error",
                    "error_message": "Node execution failed",
                    "node_id": "node_123",
                    "field_name": "company_name"
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=101,
            task_type="profile"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.WORKFLOW_NODE_ERROR
        assert result.primary_error.technical_details["node_id"] == "node_123"
        assert result.primary_error.technical_details["field_name"] == "company_name"
        assert "processing" in result.primary_error.user_message.lower() or "error" in result.primary_error.user_message.lower()

    def test_parse_old_error_format(self, error_handler):
        """Test parsing old error format (simple error string)."""
        callback_body = {
            "error": "Something went wrong during workflow execution",
            "status": "failed"
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=202,
            task_type="timeline"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.UNKNOWN
        assert result.has_whitelisted_error is False
        assert "Something went wrong" in result.primary_error.original_message

    def test_parse_validation_error(self, error_handler):
        """Test parsing validation error (English)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "validation_error",
                    "error_message": "Invalid input: company name is required",
                    "field_name": "company_name"
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body=callback_body,
            task_id=303,
            task_type="products"
        )

        assert len(result.errors) == 1
        assert result.primary_error.categorized_type == DifyErrorType.WORKFLOW_VALIDATION_ERROR
        assert result.recommended_action == "fix_input"
        assert "data" in result.primary_error.user_message.lower() or "valid" in result.primary_error.user_message.lower()

    def test_is_error_callback_detection(self, error_handler):
        """Test error callback detection."""
        # New format error
        assert error_handler.is_error_callback({
            "status": "failed",
            "message": [{"error_type": "rate_limit", "error_message": "Test"}]
        }) is True

        # Old format error
        assert error_handler.is_error_callback({
            "error": "Test error"
        }) is True

        # Status-based error
        assert error_handler.is_error_callback({
            "status": "failed",
            "message": "Simple error"
        }) is True

        # Success callback
        assert error_handler.is_error_callback({
            "status": "success",
            "data": {"result": "ok"}
        }) is False

    def test_retry_logic(self, error_handler):
        """Test retry logic for recoverable errors."""
        # Recoverable error
        recoverable_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Rate limited",
                    "retry_after": 30
                }
            ]
        }

        result = error_handler.parse_error_callback(
            recoverable_body, 111, "profile"
        )

        assert error_handler.should_retry_task(result) is True
        assert error_handler.get_retry_delay_seconds(result) == 30

        # Non-recoverable error
        non_recoverable_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "auth_invalid_key",
                    "error_message": "Invalid key"
                }
            ]
        }

        result2 = error_handler.parse_error_callback(
            non_recoverable_body, 222, "profile"
        )

        assert error_handler.should_retry_task(result2) is False
        assert error_handler.get_retry_delay_seconds(result2) is None

    def test_user_message_generation(self, error_handler):
        """Test user message generation (English)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Too many requests",
                    "retry_after": 180
                }
            ]
        }

        result = error_handler.parse_error_callback(
            callback_body, 333, "csr"
        )

        user_msg = error_handler.get_user_error_message(result, include_technical=False)
        assert "overloaded" in user_msg.lower() or "temporarily" in user_msg.lower()
        assert "3 minute" in user_msg.lower()

        # With technical details
        user_msg_tech = error_handler.get_user_error_message(result, include_technical=True)
        assert "Too many requests" in user_msg_tech

    def test_user_message_generation_french(self, error_handler_fr):
        """Test user message generation (French)."""
        callback_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Trop de requêtes",
                    "retry_after": 180
                }
            ]
        }

        result = error_handler_fr.parse_error_callback(
            callback_body, 333, "csr"
        )

        user_msg = error_handler_fr.get_user_error_message(result, include_technical=False)
        assert "saturé" in user_msg.lower()
        assert "3 minute" in user_msg.lower()

    def test_language_fallback(self):
        """Test language fallback to English for unsupported languages."""
        # Create handler with unsupported language
        handler = DifyErrorHandler(language="de")  # German not supported
        assert handler.language == "en"  # Should fall back to English

        callback_body = {
            "status": "failed",
            "message": [{"error_type": "rate_limit_llm", "error_message": "Error"}]
        }

        result = handler.parse_error_callback(callback_body, 999, "profile")
        # Should use English messages
        assert "overloaded" in result.primary_error.user_message.lower() or "temporarily" in result.primary_error.user_message.lower()

    def test_whitelist_detection(self, error_handler):
        """Test whitelist detection."""
        # Whitelisted error
        whitelisted_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "rate_limit_llm",
                    "error_message": "Rate limited"
                }
            ]
        }

        result = error_handler.parse_error_callback(
            whitelisted_body, 444, "digital"
        )

        assert result.has_whitelisted_error is True
        assert result.primary_error.should_display is True

        # Non-whitelisted error
        non_whitelisted_body = {
            "status": "failed",
            "message": [
                {
                    "error_type": "custom_unknown_error",
                    "error_message": "Some unknown error"
                }
            ]
        }

        result2 = error_handler.parse_error_callback(
            non_whitelisted_body, 555, "profile"
        )

        assert result2.has_whitelisted_error is False
        assert result2.primary_error.should_display is False
