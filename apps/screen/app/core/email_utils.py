"""Email validation utilities.

This module contains email-related validation functions that don't depend
on other app modules to avoid circular imports.
"""


def is_chapsvision_email(email: str | None) -> bool:
    """Check if email belongs to ChapsVision domain.

    This function validates whether a user email belongs to the ChapsVision
    organization by checking if it ends with @chapsvision.com. Used for
    restricting admin.organizations permission to ChapsVision employees only.

    Args:
        email: Email address to check (can be None)

    Returns:
        True if email ends with @chapsvision.com (case-insensitive), False otherwise

    Examples:
        >>> is_chapsvision_email("adnane.saber@chapsvision.com")
        True
        >>> is_chapsvision_email("user@CHAPSVISION.COM")
        True
        >>> is_chapsvision_email("user@example.com")
        False
        >>> is_chapsvision_email(None)
        False
        >>> is_chapsvision_email("")
        False
    """
    if not email:
        return False

    # Strip whitespace and check for empty string
    email = email.strip()
    if not email:
        return False

    return email.lower().endswith("@chapsvision.com")
