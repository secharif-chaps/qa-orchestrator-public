"""HMAC-SHA256 authentication for WorldCheck One API.

Implements the signature scheme required by the WorldCheck One API where
each request is individually signed. The API secret is never transmitted
over the network.

Signature algorithm:
    1. Build the data-to-sign string from request components
    2. Compute HMAC-SHA256(api_secret, data_to_sign)
    3. Base64-encode the result
    4. Set Authorization header with Signature scheme

Reference: WorldCheck One API Security documentation
"""

import base64
import hashlib
import hmac
from datetime import datetime, timezone
from email.utils import formatdate


def _format_date_rfc1123() -> str:
    """Generate current date in RFC 1123 format.

    Returns:
        Date string like 'Thu, 27 Feb 2026 14:30:00 GMT'
    """
    now = datetime.now(timezone.utc)
    return formatdate(timeval=now.timestamp(), localtime=False, usegmt=True)


def build_auth_headers(
    method: str,
    path: str,
    api_key: str,
    api_secret: str,
    host: str = "api.risk.lseg.com",
    body: str = "",
    date: str | None = None,
    content_type: str = "application/json",
) -> dict[str, str]:
    """Build HMAC-SHA256 signed headers for a WorldCheck API request.

    Constructs the data-to-sign string and computes the HMAC-SHA256
    signature as required by the WorldCheck One API.

    IMPORTANT: WorldCheck diverges from the HTTP Signature RFC by including
    the full request body in the signing text (after the headers). The body
    is treated as a verbatim text blob.

    Args:
        method: HTTP method (GET, POST, etc.)
        path: Request path (e.g., '/screening/v2/cases/screeningRequest')
        api_key: WorldCheck API key (keyId)
        api_secret: WorldCheck API secret (used to compute HMAC, never sent)
        host: API host (default: api.risk.lseg.com)
        body: Request body as string (empty for GET requests)
        date: RFC 1123 date string (auto-generated if None)
        content_type: Content-Type header value

    Returns:
        Dict of headers to include in the HTTP request:
        - Authorization: Signature header
        - Date: RFC 1123 formatted date
        - Content-Type: application/json
        - Content-Length: byte length of body (for POST)
    """
    if date is None:
        date = _format_date_rfc1123()

    method_lower = method.lower()
    content_length = str(len(body.encode("utf-8")))

    # Build the data-to-sign
    # Separators MUST be \n (0x0A only, not \r\n)
    # WorldCheck includes the full request body after the headers.
    if method_lower in ("post", "put", "patch"):
        # POST/PUT/PATCH: headers + full body appended after \n
        signed_headers = "(request-target) host date content-type content-length"
        data_to_sign = (
            f"(request-target): {method_lower} {path}\n"
            f"host: {host}\n"
            f"date: {date}\n"
            f"content-type: {content_type}\n"
            f"content-length: {content_length}\n"
            f"{body}"
        )
    else:
        # GET/DELETE: no body-related headers, no body in signing text
        signed_headers = "(request-target) host date"
        data_to_sign = f"(request-target): {method_lower} {path}\nhost: {host}\ndate: {date}"

    # Compute HMAC-SHA256 signature
    signature = base64.b64encode(
        hmac.new(
            api_secret.encode("utf-8"),
            data_to_sign.encode("utf-8"),
            hashlib.sha256,
        ).digest()
    ).decode("utf-8")

    # Build Authorization header
    authorization = (
        f'Signature keyId="{api_key}",algorithm="hmac-sha256",headers="{signed_headers}",signature="{signature}"'
    )

    headers = {
        "Authorization": authorization,
        "Date": date,
        "Content-Type": content_type,
    }

    if method_lower in ("post", "put", "patch"):
        headers["Content-Length"] = content_length

    return headers
