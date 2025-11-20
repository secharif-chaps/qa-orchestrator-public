"""Logging configuration for MINT backend.

This module provides centralized logging configuration with consistent formatting
and appropriate log levels for different components.

Usage:
    from app.core.logging_config import setup_logging, get_logger

    # Initialize logging at application startup
    setup_logging(level="INFO")

    # Get logger in any module
    logger = get_logger(__name__)
    logger.info("Processing company", extra={"company_id": company_id})
"""

import logging
import sys


def setup_logging(level: str = "INFO") -> None:
    """Configure application logging with consistent format.

    Args:
        level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)

    Example:
        setup_logging(level="DEBUG")  # For development
        setup_logging(level="INFO")   # For production
    """
    logging.basicConfig(
        level=getattr(logging, level.upper()),
        format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
        handlers=[logging.StreamHandler(sys.stdout)],
    )

    # Silence noisy third-party libraries
    logging.getLogger("urllib3").setLevel(logging.WARNING)
    logging.getLogger("sqlalchemy.engine").setLevel(logging.WARNING)
    logging.getLogger("httpx").setLevel(logging.WARNING)


def get_logger(name: str) -> logging.Logger:
    """Get a logger instance for the given module.

    Args:
        name: Typically __name__ from the calling module

    Returns:
        Configured logger instance

    Example:
        logger = get_logger(__name__)
        logger.info("User logged in", extra={"username": user.username})

    Best Practices:
        - Use structured logging with extra={} for context
        - Choose appropriate log levels:
            * DEBUG: Detailed diagnostic information
            * INFO: General informational messages
            * WARNING: Unexpected events that don't prevent operation
            * ERROR: Error conditions that need attention
            * CRITICAL: Critical conditions that may cause system failure
        - Never log sensitive data (passwords, tokens, PII)
        - Include relevant context (IDs, operation names) in extra dict
    """
    return logging.getLogger(name)
