"""Encryption utilities for sensitive data."""

from cryptography.fernet import Fernet

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)

_fernet = None


def _validate_encryption_key() -> None:
    """Validate ENCRYPTION_KEY at import time — fail fast if invalid."""
    key = settings.ENCRYPTION_KEY
    if not key:
        raise ValueError("ENCRYPTION_KEY environment variable is not set")
    try:
        Fernet(key.encode())
    except ValueError as e:
        raise ValueError(
            f"ENCRYPTION_KEY is invalid ({e}). "
            'Generate a valid key with: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"'
        ) from e


# Validated at import time: if the key is missing or invalid, the service
# crashes before any request arrives. Callers of encrypt()/decrypt() do NOT
# need to catch ValueError — it cannot occur at runtime.
_validate_encryption_key()


def _get_fernet() -> Fernet:
    """Get or initialize Fernet instance."""
    global _fernet
    if _fernet is None:
        _fernet = Fernet(settings.ENCRYPTION_KEY.encode())
    return _fernet


def encrypt(value: str) -> str:
    """Encrypt a string value."""
    return _get_fernet().encrypt(value.encode()).decode()


def decrypt(value: str) -> str:
    """Decrypt an encrypted string value."""
    return _get_fernet().decrypt(value.encode()).decode()
