"""Logging configuration for Stream service.

Usage:
    from app.core.logging_config import setup_logging, get_logger

    setup_logging(level="INFO")
    logger = get_logger(__name__)
"""

import logging
import sys


def setup_logging(level: str = "INFO") -> None:
    """Configure application logging with consistent format."""
    logging.basicConfig(
        level=getattr(logging, level.upper()),
        format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
        handlers=[logging.StreamHandler(sys.stdout)],
    )

    logging.getLogger("urllib3").setLevel(logging.WARNING)
    logging.getLogger("sqlalchemy.engine").setLevel(logging.WARNING)
    logging.getLogger("httpx").setLevel(logging.WARNING)


def get_logger(name: str) -> logging.Logger:
    """Get a logger instance for the given module."""
    return logging.getLogger(name)
