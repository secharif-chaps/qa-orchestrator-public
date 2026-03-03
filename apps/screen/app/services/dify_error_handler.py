"""Service for handling and processing Dify error callbacks."""
import re
from typing import Dict, Any, List, Optional
from app.schemas.dify_errors import (
    DifyErrorDetail,
    DifyErrorType,
    ParsedError,
    ErrorHandlingResult,
)
from app.core.dify_error_config import (
    WHITELISTED_ERROR_TYPES,
    ERROR_TYPE_MAPPING,
    ERROR_PRIORITY,
    RECOVERABLE_ERROR_TYPES,
)
from app.core.dify_error_i18n import (
    get_message_template,
    DEFAULT_LANGUAGE,
    SUPPORTED_LANGUAGES,
)
from app.core.logging_config import get_logger

logger = get_logger(__name__)


def extract_retry_after_from_message(error_message: str) -> Optional[int]:
    """
    Extract retry_after value (in seconds) from error message.

    Parses various formats like:
    - "Please retry after 24 seconds"
    - "retry after 5 minutes"
    - "Retry in 2 hours"
    - "wait for 30 seconds"

    Args:
        error_message: The error message to parse

    Returns:
        Retry delay in seconds, or None if not found

    Examples:
        >>> extract_retry_after_from_message("Please retry after 24 seconds")
        24
        >>> extract_retry_after_from_message("Retry after 5 minutes")
        300
        >>> extract_retry_after_from_message("Wait for 2 hours")
        7200
    """
    if not error_message:
        return None

    # Patterns to match (case-insensitive):
    # - "retry after X seconds/minutes/hours"
    # - "retry in X seconds/minutes/hours"
    # - "wait for X seconds/minutes/hours"
    # - "please retry after X seconds/minutes/hours"
    patterns = [
        r"retry\s+(?:after|in)\s+(\d+)\s+(second|minute|hour)s?",
        r"wait\s+(?:for)?\s+(\d+)\s+(second|minute|hour)s?",
        r"please\s+retry\s+(?:after|in)\s+(\d+)\s+(second|minute|hour)s?",
    ]

    for pattern in patterns:
        match = re.search(pattern, error_message, re.IGNORECASE)
        if match:
            value = int(match.group(1))
            unit = match.group(2).lower()

            # Convert to seconds
            if unit == "second":
                return value
            elif unit == "minute":
                return value * 60
            elif unit == "hour":
                return value * 3600

            logger.debug(
                "Extracted retry_after from message",
                extra={
                    "value": value,
                    "unit": unit,
                    "seconds": value if unit == "second" else value * 60 if unit == "minute" else value * 3600,
                    "message_snippet": error_message[:100],
                }
            )

    return None


class DifyErrorHandler:
    """
    Handler for processing Dify error callbacks.

    This service:
    1. Parses error callbacks from Dify workflows
    2. Categorizes errors by type
    3. Applies whitelist logic for special handling
    4. Generates user-friendly messages (i18n support)
    5. Logs comprehensive error details
    """

    def __init__(self, language: str = DEFAULT_LANGUAGE):
        """
        Initialize error handler.

        Args:
            language: ISO 639-1 language code (en, fr). Default: en
        """
        self.whitelisted_types = WHITELISTED_ERROR_TYPES
        self.type_mapping = ERROR_TYPE_MAPPING
        self.error_priority = ERROR_PRIORITY
        self.recoverable_types = RECOVERABLE_ERROR_TYPES
        self.language = language if language in SUPPORTED_LANGUAGES else DEFAULT_LANGUAGE

    def is_error_callback(self, callback_body: Dict[str, Any]) -> bool:
        """
        Check if callback body represents an error.

        Args:
            callback_body: Raw callback body from Dify

        Returns:
            True if this is an error callback
        """
        # Check for new error format with status="failed" and message list
        if callback_body.get("status") == "failed" and "message" in callback_body:
            message = callback_body["message"]
            # Check if message is a list of error objects
            if isinstance(message, list) and len(message) > 0:
                first_item = message[0]
                if isinstance(first_item, dict) and "error_type" in first_item:
                    return True

        # Check for old error format (simple error field)
        if "error" in callback_body and callback_body["error"]:
            return True

        # Check for status-based error
        if "status" in callback_body and callback_body["status"] in ["failed", "error"]:
            return True

        return False

    def parse_error_callback(
        self,
        callback_body: Dict[str, Any],
        task_id: int,
        task_type: str,
    ) -> ErrorHandlingResult:
        """
        Parse and process error callback from Dify.

        Args:
            callback_body: Raw callback body from Dify
            task_id: Task ID for context
            task_type: Task type for context

        Returns:
            ErrorHandlingResult with parsed and categorized errors
        """
        logger.info(
            f"🔍 Parsing Dify error callback for task {task_id} ({task_type})",
            extra={
                "task_id": task_id,
                "task_type": task_type,
                "callback_keys": list(callback_body.keys()),
            }
        )

        # Try to parse new error format first
        parsed_errors = self._parse_new_error_format(callback_body)

        # Fall back to old format if no errors found
        if not parsed_errors:
            parsed_errors = self._parse_old_error_format(callback_body)

        # If still no errors, create a generic unknown error
        if not parsed_errors:
            parsed_errors = [self._create_generic_error(callback_body)]

        # Log all parsed errors
        self._log_errors(parsed_errors, task_id, task_type)

        # Determine primary error (highest priority)
        primary_error = self._select_primary_error(parsed_errors)

        # Check if any error is whitelisted (case-insensitive comparison)
        has_whitelisted = any(
            e.original_type.lower() in self.whitelisted_types for e in parsed_errors
        )

        # Create summary
        error_summary = self._create_error_summary(parsed_errors)

        # Generate recommended action
        recommended_action = self._get_recommended_action(primary_error)

        result = ErrorHandlingResult(
            errors=parsed_errors,
            primary_error=primary_error,
            error_summary=error_summary,
            has_whitelisted_error=has_whitelisted,
            recommended_action=recommended_action,
        )

        logger.info(
            f"✅ Error parsing complete for task {task_id}",
            extra={
                "task_id": task_id,
                "error_count": len(parsed_errors),
                "primary_error_type": primary_error.categorized_type.value,
                "has_whitelisted": has_whitelisted,
                "is_recoverable": primary_error.is_recoverable,
            }
        )

        return result

    def _parse_new_error_format(
        self, callback_body: Dict[str, Any]
    ) -> List[ParsedError]:
        """Parse new error format with structured error list."""
        parsed_errors = []

        try:
            # Check for status="failed" with message list
            if callback_body.get("status") != "failed":
                return []

            message = callback_body.get("message", [])
            if not isinstance(message, list):
                return []

            # Parse each error in the list
            for error_item in message:
                if not isinstance(error_item, dict):
                    continue

                # Parse error detail
                try:
                    error_detail = DifyErrorDetail(**error_item)
                    parsed_error = self._categorize_error(error_detail)
                    parsed_errors.append(parsed_error)

                    logger.debug(
                        f"Parsed error: {error_detail.error_type}",
                        extra={
                            "error_type": error_detail.error_type,
                            "error_message": error_detail.error_message[:200],
                            "categorized_as": parsed_error.categorized_type.value,
                        }
                    )
                except Exception as e:
                    logger.warning(
                        f"Failed to parse error item: {error_item}",
                        extra={"error": str(e), "error_item": error_item}
                    )
                    continue

        except Exception as e:
            logger.error(
                f"Failed to parse new error format: {e}",
                exc_info=True,
                extra={"callback_body": callback_body}
            )

        return parsed_errors

    def _parse_old_error_format(
        self, callback_body: Dict[str, Any]
    ) -> List[ParsedError]:
        """Parse old error format (simple error string)."""
        parsed_errors = []

        try:
            # Check for simple error field
            error_msg = None
            if "error" in callback_body and callback_body["error"]:
                error_msg = str(callback_body["error"])
            elif "status" in callback_body and callback_body["status"] in ["failed", "error"]:
                error_msg = callback_body.get("message", "Task failed without specific error")
                if not isinstance(error_msg, str):
                    error_msg = "Task failed without specific error"

            if error_msg:
                # Create a generic error detail
                error_detail = DifyErrorDetail(
                    error_type="unknown",
                    error_message=error_msg
                )
                parsed_error = self._categorize_error(error_detail)
                parsed_errors.append(parsed_error)

                logger.debug(
                    "Parsed old format error",
                    extra={"error_message": error_msg[:200]}
                )

        except Exception as e:
            logger.error(
                f"Failed to parse old error format: {e}",
                exc_info=True,
                extra={"callback_body": callback_body}
            )

        return parsed_errors

    def _create_generic_error(self, callback_body: Dict[str, Any]) -> ParsedError:
        """Create a generic unknown error."""
        error_detail = DifyErrorDetail(
            error_type="unknown",
            error_message="Une erreur inconnue s'est produite lors du traitement"
        )
        return self._categorize_error(error_detail)

    def _categorize_error(self, error_detail: DifyErrorDetail) -> ParsedError:
        """
        Categorize error and generate user-friendly message.

        Args:
            error_detail: Parsed error detail from Dify

        Returns:
            ParsedError with categorization and user message
        """
        # Map to categorized type
        error_type_lower = error_detail.error_type.lower()
        categorized_type = self.type_mapping.get(
            error_type_lower,
            DifyErrorType.UNKNOWN
        )

        # Build technical details
        technical_details = {
            "original_type": error_detail.error_type,
            "original_message": error_detail.error_message,
        }

        # Add optional fields if present
        if error_detail.node_id:
            technical_details["node_id"] = error_detail.node_id
        if error_detail.field_name:
            technical_details["field_name"] = error_detail.field_name
        if error_detail.service_name:
            technical_details["service_name"] = error_detail.service_name
        if error_detail.http_status:
            technical_details["http_status"] = error_detail.http_status
        if error_detail.extra_data:
            technical_details["extra_data"] = error_detail.extra_data

        # Calculate retry time
        retry_after_seconds = None
        if error_detail.retry_after:
            # Use explicit retry_after attribute if present
            retry_after_seconds = error_detail.retry_after
        elif error_detail.retry_after_ms:
            # Convert milliseconds to seconds
            retry_after_seconds = error_detail.retry_after_ms // 1000
        else:
            # Try to extract from error message (e.g., "Please retry after 24 seconds")
            extracted_retry = extract_retry_after_from_message(error_detail.error_message)
            if extracted_retry:
                retry_after_seconds = extracted_retry
                logger.info(
                    "Extracted retry_after from error message",
                    extra={
                        "error_type": error_detail.error_type,
                        "retry_after_seconds": retry_after_seconds,
                        "message_snippet": error_detail.error_message[:100],
                    }
                )

        # Create parsed error (without user_message first)
        parsed_error = ParsedError(
            original_type=error_detail.error_type,
            original_message=error_detail.error_message,
            categorized_type=categorized_type,
            user_message="",  # Will be set below
            technical_details=technical_details,
            retry_after_seconds=retry_after_seconds,
            should_display=error_detail.error_type.lower() in self.whitelisted_types,
            is_recoverable=categorized_type in self.recoverable_types,
        )

        # Generate user message using i18n template
        message_template = get_message_template(categorized_type, self.language)
        parsed_error.user_message = message_template(parsed_error)

        return parsed_error

    def _select_primary_error(self, errors: List[ParsedError]) -> ParsedError:
        """
        Select the most important error to display to user.

        Prioritizes based on ERROR_PRIORITY configuration.

        Args:
            errors: List of parsed errors

        Returns:
            Primary error to display
        """
        if not errors:
            # Should never happen, but handle gracefully
            return self._categorize_error(
                DifyErrorDetail(
                    error_type="unknown",
                    error_message="Une erreur inconnue s'est produite"
                )
            )

        # Sort by priority (highest first)
        sorted_errors = sorted(
            errors,
            key=lambda e: self.error_priority.get(e.categorized_type, 0),
            reverse=True
        )

        return sorted_errors[0]

    def _create_error_summary(self, errors: List[ParsedError]) -> str:
        """Create a brief summary of all errors."""
        if not errors:
            return "Aucune erreur détectée"

        if len(errors) == 1:
            return f"1 erreur: {errors[0].categorized_type.value}"

        error_types = [e.categorized_type.value for e in errors]
        return f"{len(errors)} erreurs: {', '.join(error_types)}"

    def _get_recommended_action(self, primary_error: ParsedError) -> Optional[str]:
        """Get recommended action based on error type."""
        if primary_error.is_recoverable:
            if primary_error.retry_after_seconds:
                return f"retry_after_{primary_error.retry_after_seconds}s"
            return "retry"

        if primary_error.categorized_type in [
            DifyErrorType.AUTH_INVALID_KEY,
            DifyErrorType.AUTH_EXPIRED
        ]:
            return "contact_admin"

        if primary_error.categorized_type in [
            DifyErrorType.DATA_INVALID_INPUT,
            DifyErrorType.DATA_MISSING_FIELD,
            DifyErrorType.WORKFLOW_VALIDATION_ERROR
        ]:
            return "fix_input"

        return "contact_support"

    def _log_errors(
        self,
        errors: List[ParsedError],
        task_id: int,
        task_type: str
    ) -> None:
        """
        Comprehensively log all errors.

        Args:
            errors: List of parsed errors
            task_id: Task ID for context
            task_type: Task type for context
        """
        for idx, error in enumerate(errors):
            log_extra = {
                "task_id": task_id,
                "task_type": task_type,
                "error_index": idx + 1,
                "total_errors": len(errors),
                "original_type": error.original_type,
                "categorized_type": error.categorized_type.value,
                "is_whitelisted": error.original_type in self.whitelisted_types,
                "is_recoverable": error.is_recoverable,
                "should_display": error.should_display,
                "priority": self.error_priority.get(
                    error.categorized_type,
                    0
                ),
            }

            # Add retry info if available
            if error.retry_after_seconds:
                log_extra["retry_after_seconds"] = error.retry_after_seconds

            # Add technical details
            log_extra.update(error.technical_details)

            logger.error(
                f"❌ Dify Error [{idx + 1}/{len(errors)}]: {error.original_type} - {error.original_message[:200]}",
                extra=log_extra
            )

        # Log summary
        logger.error(
            f"❌ Error Summary for task {task_id}: {len(errors)} error(s) detected",
            extra={
                "task_id": task_id,
                "task_type": task_type,
                "error_count": len(errors),
                "whitelisted_count": sum(
                    1 for e in errors if e.original_type in self.whitelisted_types
                ),
                "recoverable_count": sum(1 for e in errors if e.is_recoverable),
                "error_types": [e.categorized_type.value for e in errors],
            }
        )

    def get_user_error_message(
        self,
        result: ErrorHandlingResult,
        include_technical: bool = False
    ) -> str:
        """
        Get formatted error message for user display.

        Args:
            result: Error handling result
            include_technical: Whether to include technical details

        Returns:
            Formatted error message
        """
        message = result.primary_error.user_message

        if include_technical and result.primary_error.technical_details:
            tech_info = result.primary_error.technical_details.get(
                "original_message",
                ""
            )
            if tech_info:
                message += f"\n\nDétails techniques : {tech_info[:200]}"

        return message

    def should_retry_task(self, result: ErrorHandlingResult) -> bool:
        """
        Determine if task should be automatically retried.

        Args:
            result: Error handling result

        Returns:
            True if task should be retried
        """
        return result.primary_error.is_recoverable

    def get_retry_delay_seconds(self, result: ErrorHandlingResult) -> Optional[int]:
        """
        Get recommended retry delay in seconds.

        Args:
            result: Error handling result

        Returns:
            Delay in seconds, or None if no specific delay
        """
        return result.primary_error.retry_after_seconds
