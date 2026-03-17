"""Custom exceptions for WorldCheck API client."""


class WorldCheckError(Exception):
    """Base exception for WorldCheck API errors.

    Attributes:
        message: Human-readable error description
        status_code: HTTP status code from the API (if applicable)
        response_body: Raw response body (if available)
    """

    def __init__(
        self,
        message: str,
        status_code: int | None = None,
        response_body: str | None = None,
    ):
        self.message = message
        self.status_code = status_code
        self.response_body = response_body
        super().__init__(message)


class WorldCheckAuthError(WorldCheckError):
    """Raised on HTTP 401 - invalid HMAC signature or credentials."""

    def __init__(self, message: str = "Invalid WorldCheck credentials or signature", response_body: str | None = None):
        super().__init__(message, status_code=401, response_body=response_body)


class WorldCheckRateLimitError(WorldCheckError):
    """Raised on HTTP 429 - rate limit exceeded.

    Attributes:
        retry_after: Seconds to wait before retrying (from Retry-After header)
    """

    def __init__(
        self,
        message: str = "WorldCheck API rate limit exceeded",
        retry_after: int | None = None,
        response_body: str | None = None,
    ):
        self.retry_after = retry_after
        super().__init__(message, status_code=429, response_body=response_body)


class WorldCheckNotFoundError(WorldCheckError):
    """Raised on HTTP 404 - resource not found."""

    def __init__(self, message: str = "WorldCheck resource not found", response_body: str | None = None):
        super().__init__(message, status_code=404, response_body=response_body)


class WorldCheckAPIError(WorldCheckError):
    """Raised for other non-success HTTP responses from WorldCheck API."""
