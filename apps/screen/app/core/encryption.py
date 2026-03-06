"""Encryption utilities for sensitive data."""

from cryptography.fernet import Fernet

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

_fernet = None


def _get_fernet() -> Fernet:
    """Get or initialize Fernet instance."""
    global _fernet
    if _fernet is None:
        key = settings.ENCRYPTION_KEY
        if not key:
            raise ValueError("ENCRYPTION_KEY environment variable is not set")
        _fernet = Fernet(key.encode())
    return _fernet


def encrypt(value: str) -> str:
    """Encrypt a string value."""
    return _get_fernet().encrypt(value.encode()).decode()


def decrypt(value: str) -> str:
    """Decrypt an encrypted string value."""
    return _get_fernet().decrypt(value.encode()).decode()
