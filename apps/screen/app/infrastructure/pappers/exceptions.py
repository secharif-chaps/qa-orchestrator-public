"""Custom exceptions for Pappers API client."""


class PappersError(Exception):
    """Base exception for Pappers API errors."""

    def __init__(self, message: str, status_code: int | None = None, response_body: str | None = None):
        self.status_code = status_code
        self.response_body = response_body
        super().__init__(message)


class PappersAuthError(PappersError):
    """Raised on 401/403 - invalid or missing API key."""


class PappersNotFoundError(PappersError):
    """Raised on 404 - entity not found."""


class PappersRateLimitError(PappersError):
    """Raised on 429 - rate limit exceeded."""

    def __init__(self, message: str, retry_after: float | None = None, **kwargs):
        self.retry_after = retry_after
        super().__init__(message, **kwargs)
