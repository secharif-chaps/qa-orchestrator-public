"""HTTP client for global-service API.

This module provides the GlobalServiceClient class for making authenticated
HTTP calls from the monolith to the global-service, primarily for token
consumption during company creation.

The client uses internal JWT authentication for service-to-service
communication and includes retry logic with exponential backoff for
transient failures.
"""

import asyncio
from collections.abc import Awaitable, Callable
from dataclasses import dataclass

import httpx
from fastapi import HTTPException, status

from app.core.config import settings
from app.core.internal_jwt import create_internal_token
from app.core.logging_config import get_logger
from app.models.organization import ModuleName, ReferenceType

logger = get_logger(__name__)

# Token cost for company creation - consistent with global-service
TOKENS_PER_COMPANY = 35

# Retry configuration
MAX_RETRIES = 3
BASE_DELAY_SECONDS = 0.5
MAX_DELAY_SECONDS = 4.0


class InsufficientTokensError(HTTPException):
    """Exception raised when organization has insufficient tokens.

    Raised by GlobalServiceClient when global-service returns 402.
    """

    def __init__(self, message: str, current_balance: int | None = None, required_tokens: int | None = None):
        detail = {"message": message}
        if current_balance is not None:
            detail["current_balance"] = current_balance
        if required_tokens is not None:
            detail["required_tokens"] = required_tokens

        super().__init__(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=detail,
        )


class ModuleNotEnabledError(HTTPException):
    """Exception raised when module is not enabled for organization.

    Raised by GlobalServiceClient when global-service returns 403.
    """

    def __init__(self, message: str):
        super().__init__(
            status_code=status.HTTP_403_FORBIDDEN,
            detail={"message": message},
        )


@dataclass
class TokenConsumeResult:
    """Result of a successful token consumption operation.

    Attributes:
        success: Always True for successful operations
        balance: Remaining token balance after consumption
        transaction_id: ID of the created transaction record
    """

    success: bool
    balance: int
    transaction_id: int | None = None


class GlobalServiceClient:
    """HTTP client for global-service API calls.

    Provides methods for consuming tokens via the global-service internal API.
    Uses internal JWT authentication and includes retry logic for transient
    failures.

    Attributes:
        base_url: Base URL of the global-service (from settings)
        timeout: Request timeout in seconds
    """

    def __init__(self, base_url: str | None = None, timeout: float = 10.0):
        """Initialize the global-service client.

        Args:
            base_url: Override base URL (defaults to GLOBAL_SERVICE_URL setting)
            timeout: Request timeout in seconds (default 10)
        """
        self.base_url = base_url or settings.GLOBAL_SERVICE_URL
        self.timeout = timeout

    def _create_internal_token(
        self,
        user_id: str,
        username: str,
        org_id: str,
        org_name: str = "Unknown",
    ) -> str:
        """Create an internal JWT for service-to-service authentication.

        Args:
            user_id: Keycloak user UUID
            username: Username of the user
            org_id: Organization UUID
            org_name: Organization name (optional)

        Returns:
            Signed internal JWT string
        """
        return create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
            org_name=org_name,
            roles=[],  # Roles are not checked for internal calls
        )

    async def _retry_with_backoff(
        self,
        operation: Callable[[], Awaitable[httpx.Response]],
        operation_name: str,
    ) -> httpx.Response:
        """Execute an async operation with exponential backoff retry.

        Retries on transient failures:
        - Network errors (timeouts, connection errors)
        - Transient HTTP errors (502 Bad Gateway, 503 Service Unavailable, 504 Gateway Timeout)

        Does NOT retry on:
        - 4xx client errors (business logic errors)
        - Other 5xx server errors (non-transient)

        Args:
            operation: Async callable that makes the HTTP request
            operation_name: Description for logging

        Returns:
            HTTP response from successful request

        Raises:
            httpx.RequestError: After all retries exhausted for network errors
            HTTPException: After all retries exhausted for transient server errors
        """
        last_exception: Exception | None = None
        # Transient HTTP status codes that should be retried
        transient_status_codes = {502, 503, 504}

        for attempt in range(MAX_RETRIES):
            try:
                response = await operation()

                # Don't retry on client errors (4xx) - these are business logic errors
                if 400 <= response.status_code < 500:
                    return response

                # Retry on transient server errors (502, 503, 504)
                if response.status_code in transient_status_codes:
                    delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                    logger.warning(
                        f"{operation_name} received {response.status_code} on attempt {attempt + 1}/{MAX_RETRIES}, retrying in {delay}s",
                        extra={
                            "attempt": attempt + 1,
                            "delay": delay,
                            "status_code": response.status_code,
                        },
                    )
                    if attempt < MAX_RETRIES - 1:
                        await asyncio.sleep(delay)
                        continue
                    # Last attempt failed, raise exception
                    raise HTTPException(
                        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                        detail=f"Token service temporarily unavailable after {MAX_RETRIES} attempts",
                    )

                # Don't retry on other server errors - let them propagate
                response.raise_for_status()
                return response

            except httpx.TimeoutException as e:
                last_exception = e
                delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                logger.warning(
                    f"{operation_name} timeout on attempt {attempt + 1}/{MAX_RETRIES}, retrying in {delay}s",
                    extra={"attempt": attempt + 1, "delay": delay},
                )
                await asyncio.sleep(delay)

            except httpx.ConnectError as e:
                last_exception = e
                delay = min(BASE_DELAY_SECONDS * (2**attempt), MAX_DELAY_SECONDS)
                logger.warning(
                    f"{operation_name} connection error on attempt {attempt + 1}/{MAX_RETRIES}, retrying in {delay}s",
                    extra={"attempt": attempt + 1, "delay": delay, "error": str(e)},
                )
                await asyncio.sleep(delay)

        # All retries exhausted
        logger.error(
            f"{operation_name} failed after {MAX_RETRIES} attempts",
            extra={"error": str(last_exception)},
        )
        raise last_exception

    async def consume_tokens(
        self,
        org_id: str,
        amount: int,
        module_name: ModuleName,
        reference_type: ReferenceType,
        reference_id: str | None,
        user_id: str,
        username: str = "unknown",
        description: str | None = None,
    ) -> TokenConsumeResult:
        """Consume tokens from organization balance via global-service.

        Makes an async HTTP call to the global-service internal API to consume tokens.
        Uses internal JWT authentication and includes retry logic for network
        failures.

        Args:
            org_id: Keycloak organization UUID
            amount: Number of tokens to consume
            module_name: Module consuming the tokens (must be enabled)
            reference_type: Type of operation consuming tokens
            reference_id: Optional ID of the referenced entity
            user_id: Keycloak user ID performing the operation
            username: Username for logging/audit
            description: Optional description of the transaction

        Returns:
            TokenConsumeResult with success status and new balance

        Raises:
            InsufficientTokensError: If organization has insufficient tokens (402)
            ModuleNotEnabledError: If module is not enabled for organization (403)
            HTTPException: On other API errors
        """
        url = f"{self.base_url}/internal/organizations/{org_id}/tokens/consume"

        # Create internal JWT for authentication
        token = self._create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
        )

        headers = {
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        }

        payload = {
            "amount": amount,
            "module_name": module_name.value,
            "reference_type": reference_type.value,
            "reference_id": str(reference_id) if reference_id is not None else None,
            "created_by": user_id,
            "description": description,
        }

        logger.info(
            f"Calling global-service to consume {amount} tokens for organization {org_id}",
            extra={
                "organization_id": org_id,
                "amount": amount,
                "module_name": module_name.value,
                "reference_type": reference_type.value,
            },
        )

        async def make_request() -> httpx.Response:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                return await client.post(url, json=payload, headers=headers)

        try:
            response = await self._retry_with_backoff(make_request, "Token consumption")

            if response.status_code == 200:
                data = response.json()
                logger.info(
                    f"Token consumption successful. New balance: {data.get('balance')}",
                    extra={
                        "organization_id": org_id,
                        "new_balance": data.get("balance"),
                        "transaction_id": data.get("transaction", {}).get("id") if data.get("transaction") else None,
                    },
                )
                return TokenConsumeResult(
                    success=True,
                    balance=data.get("balance", 0),
                    transaction_id=data.get("transaction", {}).get("id") if data.get("transaction") else None,
                )

            # Handle specific error codes
            if response.status_code == 402:
                error_data = response.json().get("detail", {})
                message = error_data.get("message", "Insufficient token balance")
                current_balance = error_data.get("current_balance")
                required_tokens = error_data.get("required_tokens")

                logger.warning(
                    f"Insufficient tokens for organization {org_id}",
                    extra={
                        "organization_id": org_id,
                        "current_balance": current_balance,
                        "required_tokens": required_tokens,
                    },
                )
                raise InsufficientTokensError(
                    message=message,
                    current_balance=current_balance,
                    required_tokens=required_tokens,
                )

            if response.status_code == 403:
                error_data = response.json().get("detail", {})
                message = error_data.get("message", f"Module {module_name.value} is not enabled")

                logger.warning(
                    f"Module not enabled for organization {org_id}",
                    extra={
                        "organization_id": org_id,
                        "module_name": module_name.value,
                    },
                )
                raise ModuleNotEnabledError(message=message)

            # Other error responses
            logger.error(
                f"Global-service returned error: {response.status_code}",
                extra={
                    "organization_id": org_id,
                    "status_code": response.status_code,
                    "response_body": response.text[:500],
                },
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=f"Token service error: {response.status_code}",
            )

        except (InsufficientTokensError, ModuleNotEnabledError):
            # Re-raise our custom exceptions
            raise

        except httpx.RequestError as e:
            logger.error(
                f"Network error calling global-service: {str(e)}",
                extra={"organization_id": org_id, "error": str(e)},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Token service temporarily unavailable",
            )

    async def get_balance(
        self,
        org_id: str,
        user_id: str,
        username: str = "unknown",
    ) -> int:
        """Get token balance for an organization via global-service.

        Makes an async HTTP call to global-service to get the current token balance.
        Uses internal JWT authentication.

        Args:
            org_id: Keycloak organization UUID
            user_id: Keycloak user ID performing the operation
            username: Username for logging/audit

        Returns:
            Current token balance as integer

        Raises:
            HTTPException: On API errors or service unavailability
        """
        url = f"{self.base_url}/organizations/{org_id}/tokens"

        # Create internal JWT for authentication
        token = self._create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
        )

        headers = {
            "Authorization": f"Bearer {token}",
        }

        logger.debug(
            f"Calling global-service to get token balance for organization {org_id}",
            extra={"organization_id": org_id},
        )

        async def make_request() -> httpx.Response:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                return await client.get(url, headers=headers)

        try:
            response = await self._retry_with_backoff(make_request, "Get token balance")

            if response.status_code == 200:
                data = response.json()
                balance = data.get("balance", 0)
                logger.debug(
                    f"Token balance for organization {org_id}: {balance}",
                    extra={"organization_id": org_id, "balance": balance},
                )
                return balance

            # Handle errors
            logger.error(
                f"Global-service returned error getting balance: {response.status_code}",
                extra={
                    "organization_id": org_id,
                    "status_code": response.status_code,
                    "response_body": response.text[:500],
                },
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=f"Token service error: {response.status_code}",
            )

        except httpx.HTTPStatusError as e:
            logger.error(
                f"Global-service returned error getting balance: {e.response.status_code}",
                extra={"organization_id": org_id, "status_code": e.response.status_code, "error": str(e)},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=f"Token service error: {e.response.status_code}",
            )

        except httpx.RequestError as e:
            logger.error(
                f"Network error calling global-service: {str(e)}",
                extra={"organization_id": org_id, "error": str(e)},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Token service temporarily unavailable",
            )


    async def get_accessible_company_ids(
        self,
        org_id: str,
        user_id: str,
        username: str = "unknown",
    ) -> set[int]:
        """Get company IDs accessible to a user via folder sharing.

        Calls global-service internal API to get the list of company IDs
        from all folders the user owns or has been shared with.

        Args:
            org_id: Keycloak organization UUID
            user_id: Keycloak user ID
            username: Username for internal JWT

        Returns:
            Set of accessible company IDs

        Raises:
            HTTPException: On API errors or service unavailability
        """
        url = f"{self.base_url}/internal/organizations/{org_id}/folders/accessible-company-ids"

        token = self._create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
        )

        headers = {"Authorization": f"Bearer {token}"}

        logger.debug(
            f"Calling global-service to get accessible company IDs for user {user_id}",
            extra={"organization_id": org_id, "user_id": user_id},
        )

        async def make_request() -> httpx.Response:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                return await client.get(url, headers=headers)

        try:
            response = await self._retry_with_backoff(make_request, "Get accessible company IDs")

            if response.status_code == 200:
                company_ids = response.json()
                logger.debug(
                    f"Got {len(company_ids)} accessible company IDs",
                    extra={"organization_id": org_id, "count": len(company_ids)},
                )
                return set(company_ids)

            logger.error(
                f"Global-service returned error getting accessible company IDs: {response.status_code}",
                extra={
                    "organization_id": org_id,
                    "status_code": response.status_code,
                    "response_body": response.text[:500],
                },
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=f"Folder service error: {response.status_code}",
            )

        except httpx.RequestError as e:
            logger.error(
                f"Network error calling global-service: {str(e)}",
                extra={"organization_id": org_id, "error": str(e)},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Folder service temporarily unavailable",
            )

    async def user_has_company_access(
        self,
        org_id: str,
        company_id: int,
        user_id: str,
        username: str = "unknown",
        user_roles: list[str] | None = None,
    ) -> bool:
        """Check if a user has access to a company via folder sharing.

        Calls global-service internal API to check if the user has access
        to the specified company through folder ownership or sharing.

        Args:
            org_id: Keycloak organization UUID
            company_id: Company ID to check access for
            user_id: Keycloak user ID
            username: Username for internal JWT
            user_roles: User roles for manager check

        Returns:
            True if user has access, False otherwise

        Raises:
            HTTPException: On API errors or service unavailability
        """
        roles_param = ",".join(user_roles) if user_roles else ""
        url = (
            f"{self.base_url}/internal/organizations/{org_id}"
            f"/folders/company-access/{company_id}?user_roles={roles_param}"
        )

        token = self._create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
        )

        headers = {"Authorization": f"Bearer {token}"}

        logger.debug(
            "Calling global-service to check company access",
            extra={
                "organization_id": org_id,
                "company_id": company_id,
                "user_id": user_id,
            },
        )

        async def make_request() -> httpx.Response:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                return await client.get(url, headers=headers)

        try:
            response = await self._retry_with_backoff(make_request, "Check company access")

            if response.status_code == 200:
                return response.json()

            logger.error(
                f"Global-service returned error checking company access: {response.status_code}",
                extra={
                    "organization_id": org_id,
                    "company_id": company_id,
                    "status_code": response.status_code,
                },
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail=f"Folder service error: {response.status_code}",
            )

        except httpx.RequestError as e:
            logger.error(
                f"Network error calling global-service: {str(e)}",
                extra={"organization_id": org_id, "error": str(e)},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Folder service temporarily unavailable",
            )


    async def get_company_folder_id(
        self,
        org_id: str,
        company_id: int,
        user_id: str,
        username: str = "unknown",
    ) -> str | None:
        """Get the folder ID containing a company.

        Calls global-service internal API to get the folder_id of the first
        folder containing this company in the organization.

        Args:
            org_id: Keycloak organization UUID
            company_id: Company ID
            user_id: Keycloak user ID for internal JWT
            username: Username for internal JWT

        Returns:
            Folder ID as string, or None if company is not in any folder
        """
        url = (
            f"{self.base_url}/internal/organizations/{org_id}"
            f"/folders/company/{company_id}/folder-id"
        )

        token = self._create_internal_token(
            user_id=user_id,
            username=username,
            org_id=org_id,
        )

        headers = {"Authorization": f"Bearer {token}"}

        async def make_request() -> httpx.Response:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                return await client.get(url, headers=headers)

        try:
            response = await self._retry_with_backoff(make_request, "Get company folder ID")

            if response.status_code == 200:
                return response.json()

            logger.warning(
                f"Global-service returned {response.status_code} getting company folder ID",
                extra={"organization_id": org_id, "company_id": company_id},
            )
            return None

        except (httpx.RequestError, HTTPException) as e:
            logger.warning(
                f"Failed to get company folder ID: {e}",
                extra={"organization_id": org_id, "company_id": company_id},
            )
            return None


def get_global_service_client() -> GlobalServiceClient:
    """Dependency function to get a GlobalServiceClient instance.

    Returns:
        GlobalServiceClient configured with settings
    """
    return GlobalServiceClient()
