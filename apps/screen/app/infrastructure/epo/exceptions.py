"""Custom exceptions for EPO OPS API client."""


class EpoError(Exception):
    """Base exception for EPO OPS API errors.

    Attributes:
        message: Human-readable error description
        status_code: HTTP status code from the API (if applicable)
        response_body: Raw response body (if available) — never contains credentials
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


class EpoAuthError(EpoError):
    """Raised when the OAuth2 handshake fails or a request returns 401.

    Covers invalid Consumer Key / Consumer Secret and expired tokens
    that cannot be refreshed.
    """

    def __init__(
        self,
        message: str = "EPO authentication failed",
        status_code: int | None = 401,
        response_body: str | None = None,
    ):
        super().__init__(message, status_code=status_code, response_body=response_body)


class EpoQuotaExceededError(EpoError):
    """Raised on HTTP 403 with an EPO quota-exceeded rejection header.

    EPO OPS signals rate limiting via HTTP 403 + `X-Rejection-Reason`
    (per-hour or per-week). After MAX_RETRIES exponential-backoff
    attempts, this exception is raised.

    Attributes:
        retry_after: Seconds to wait before retrying, if the server hinted one.
    """

    def __init__(
        self,
        message: str = "EPO API quota exceeded",
        retry_after: int | None = None,
        response_body: str | None = None,
    ):
        self.retry_after = retry_after
        super().__init__(message, status_code=403, response_body=response_body)


class EpoNotFoundError(EpoError):
    """Raised on HTTP 404 (document or resource not found)."""

    def __init__(
        self,
        message: str = "EPO resource not found",
        response_body: str | None = None,
    ):
        super().__init__(message, status_code=404, response_body=response_body)


class EpoParsingError(EpoError):
    """Raised when an EPO XML response cannot be parsed into a schema.

    Either the XML is malformed or a required element is missing.
    """

    def __init__(
        self,
        message: str = "Failed to parse EPO XML response",
        response_body: str | None = None,
    ):
        super().__init__(message, status_code=None, response_body=response_body)
