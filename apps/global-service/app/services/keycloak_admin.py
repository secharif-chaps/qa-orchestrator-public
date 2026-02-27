"""Keycloak Admin Service for user management operations.

Ported from the backend monolith to the global-service.
Handles admin-level user CRUD, role management, and organization assignment
via the Keycloak Admin REST API.
"""

import asyncio
import secrets
import string
import time
from typing import Any, Optional
from urllib.parse import quote

import httpx
from fastapi import HTTPException, status

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class KeycloakAdminService:
    """Service for Keycloak Admin API operations."""

    def __init__(self) -> None:
        self.server_url = settings.KEYCLOAK_SERVER_URL
        self.realm = settings.KEYCLOAK_REALM
        self.admin_client_id = settings.KEYCLOAK_ADMIN_CLIENT_ID
        self.admin_client_secret = settings.KEYCLOAK_ADMIN_CLIENT_SECRET
        self._admin_token: Optional[str] = None
        self._token_expiry: Optional[float] = None
        self._token_lock = asyncio.Lock()
        self._client = httpx.AsyncClient(timeout=httpx.Timeout(30.0, read=60.0))

    async def close(self) -> None:
        """Close the shared HTTP client. Call on application shutdown."""
        await self._client.aclose()

    # ── Token management ────────────────────────────────────────────

    def _is_token_valid(self) -> bool:
        """Check if cached token is still valid (with 30 second buffer)."""
        if not self._admin_token or not self._token_expiry:
            return False
        return time.time() < (self._token_expiry - 30)

    async def _get_admin_token(self) -> str:
        """Get admin access token using client credentials grant (with caching)."""
        if self._is_token_valid():
            return self._admin_token

        async with self._token_lock:
            if self._is_token_valid():
                return self._admin_token
            return await self._refresh_admin_token()

    async def _refresh_admin_token(self) -> str:
        """Refresh admin token with retry logic (called within lock)."""
        token_url = f"{self.server_url}/realms/{self.realm}/protocol/openid-connect/token"
        max_retries = 3
        retry_delay = 1.0

        for attempt in range(1, max_retries + 1):
            try:
                logger.info(
                    "Requesting admin token",
                    extra={"attempt": attempt, "max_retries": max_retries, "realm": self.realm},
                )

                token_timeout = httpx.Timeout(10.0, read=30.0)
                response = await self._client.post(
                    token_url,
                    headers={"Content-Type": "application/x-www-form-urlencoded"},
                    data={
                        "grant_type": "client_credentials",
                        "client_id": self.admin_client_id,
                        "client_secret": self.admin_client_secret,
                    },
                    timeout=token_timeout,
                )

                if response.status_code == 200:
                    token_data = response.json()
                    self._admin_token = token_data["access_token"]
                    expires_in = token_data.get("expires_in", 300)
                    self._token_expiry = time.time() + expires_in
                    logger.info("Successfully obtained admin token", extra={"expires_in_seconds": expires_in})
                    return self._admin_token

                logger.error(
                    "Failed to get admin token",
                    extra={"status_code": response.status_code},
                )

                if response.status_code in [401, 403]:
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail="Authentication failed with identity provider",
                    )

                raise httpx.HTTPStatusError(f"HTTP {response.status_code}", request=response.request, response=response)

            except httpx.RequestError as e:
                if attempt == max_retries:
                    logger.error("All retry attempts exhausted for admin token refresh", exc_info=True)
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail="Unable to connect to Keycloak server after multiple retries",
                    )
                logger.warning("Network error during admin token refresh", extra={"attempt": attempt, "max_retries": max_retries, "error_type": type(e).__name__})
                await asyncio.sleep(retry_delay)
                retry_delay *= 2

            except httpx.HTTPStatusError:
                if attempt == max_retries:
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail="Failed to get admin token after multiple retries",
                    )
                await asyncio.sleep(retry_delay)
                retry_delay *= 2

        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail="Failed to obtain admin token")

    async def _make_admin_request(
        self, method: str, endpoint: str, data: Optional[Any] = None
    ) -> httpx.Response:
        """Make authenticated request to Keycloak Admin API."""
        token = await self._get_admin_token()
        headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
        url = f"{self.server_url}/admin/realms/{self.realm}{endpoint}"

        if method.upper() == "GET":
            return await self._client.get(url, headers=headers)
        elif method.upper() == "POST":
            return await self._client.post(url, headers=headers, json=data)
        elif method.upper() == "PUT":
            return await self._client.put(url, headers=headers, json=data)
        elif method.upper() == "DELETE":
            # Support DELETE with body (needed for role removal)
            if data:
                return await self._client.request("DELETE", url, headers=headers, json=data)
            return await self._client.delete(url, headers=headers)
        else:
            raise ValueError(f"Unsupported HTTP method: {method}")

    # ── User methods ────────────────────────────────────────────────

    async def get_user(self, user_id: str) -> Optional[dict[str, Any]]:
        """Get user details by ID."""
        try:
            response = await self._make_admin_request("GET", f"/users/{user_id}")
            if response.status_code == 200:
                return response.json()
            if response.status_code == 404:
                return None
            logger.error("Failed to get user", extra={"user_id": user_id, "status_code": response.status_code})
            return None
        except Exception as e:
            logger.error("Exception getting user", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return None

    async def search_users(
        self, search: Optional[str] = None, first: int = 0, max_results: int = 100
    ) -> list[dict[str, Any]]:
        """Search users using Keycloak's native search API."""
        try:
            params = [f"first={first}", f"max={max_results}"]
            if search and search.strip():
                params.append(f"search={quote(search.strip())}")

            response = await self._make_admin_request("GET", f"/users?{'&'.join(params)}")
            if response.status_code == 200:
                return response.json()
            logger.error("Failed to search users", extra={"status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Exception searching users", exc_info=True, extra={"error_type": type(e).__name__})
            return []

    async def count_users_with_search(self, search: Optional[str] = None) -> int:
        """Count users with optional search filter."""
        try:
            endpoint = "/users/count"
            if search and search.strip():
                endpoint = f"/users/count?search={quote(search.strip())}"

            response = await self._make_admin_request("GET", endpoint)
            if response.status_code == 200:
                return int(response.text)
            logger.error("Failed to count users", extra={"status_code": response.status_code})
            return 0
        except Exception as e:
            logger.error("Exception counting users", exc_info=True, extra={"error_type": type(e).__name__})
            return 0

    async def update_user(self, user_id: str, user_data: dict[str, Any]) -> bool:
        """Update user details in Keycloak."""
        try:
            update_payload = {}
            for key in ("username", "email", "firstName", "lastName", "enabled"):
                if key in user_data:
                    update_payload[key] = user_data[key]

            response = await self._make_admin_request("PUT", f"/users/{user_id}", update_payload)
            if response.status_code == 204:
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")
            logger.error("Failed to update user", extra={"user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error updating user", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return False
    
    async def get_users(self, first: int = 0, max_results: int = 100) -> list[dict[str, Any]]:
        """Get paginated list of users."""
        try:
            endpoint = f"/users?first={first}&max={max_results}"
            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                return response.json()
            else:
                logger.error(f"Failed to get users: {response.status_code} - {response.text}")
                return []

        except Exception as e:
            logger.error(f"Error getting users: {e}")
            return []

    async def create_user(self, user_data: dict[str, Any]) -> dict[str, Any]:
        """Create a new user in Keycloak."""
        try:
            logger.info(
                "Starting user creation in Keycloak",
                extra={
                    "username": user_data.get("username"),
                    "email": user_data.get("email"),
                    "firstName": user_data.get("firstName"),
                    "lastName": user_data.get("lastName"),
                }
            )

            # Generate temporary password if not provided
            temp_password = user_data.get("temporaryPassword") or self.generate_temp_password()

            # Prepare Keycloak user payload
            keycloak_payload = {
                "username": user_data["username"],
                "email": user_data["email"],
                "firstName": user_data.get("firstName", ""),
                "lastName": user_data.get("lastName", ""),
                "enabled": True,
                "emailVerified": True,
                "credentials": [{
                    "type": "password",
                    "value": temp_password,
                    "temporary": True,
                }],
            }

            response = await self._make_admin_request("POST", "/users", keycloak_payload)

            if response.status_code == 201:
                # Get the created user's ID from Location header
                location = response.headers.get("Location", "")
                user_id = location.split("/")[-1] if location else None

                logger.info(
                    "User created successfully in Keycloak",
                    extra={
                        "user_id": user_id,
                        "username": user_data["username"],
                    }
                )

                if user_id:
                    user_details = await self.get_user(user_id)
                    if user_details:
                        user_details["temporaryPassword"] = temp_password
                        return user_details

                logger.error(
                    "User created but could not retrieve user ID or details",
                    extra={"username": user_data["username"]},
                )
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail="User created but could not retrieve details",
                )

            elif response.status_code == 409:
                error_detail = (
                    response.json()
                    if response.headers.get("content-type", "").startswith("application/json")
                    else {"errorMessage": "User already exists"}
                )
                logger.warning(
                    "User creation failed - conflict",
                    extra={
                        "username": user_data["username"],
                        "email": user_data["email"],
                        "error_detail": error_detail,
                    }
                )
                error_msg = error_detail.get("errorMessage", "").lower()
                if "username" in error_msg:
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="Username already exists",
                    )
                elif "email" in error_msg:
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="Email already exists",
                    )
                else:
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="User already exists",
                    )
            else:
                error_text = response.text
                logger.error(
                    "Failed to create user in Keycloak",
                    extra={
                        "status_code": response.status_code,
                        "error_text": error_text,
                        "username": user_data["username"],
                        "email": user_data["email"],
                    }
                )
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail={
                        "error": "user_creation_failed",
                        "message": "Failed to create user",
                    },
                )

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Unexpected error creating user in Keycloak",
                exc_info=True,
                extra={
                    "error_type": type(e).__name__,
                    "error_message": str(e),
                    "username": user_data.get("username"),
                    "email": user_data.get("email"),
                }
            )
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Internal error creating user",
            )
        
    def generate_temp_password(self, length: int = 12) -> str:
        """Generate a secure temporary password."""
        if length < 8:
            length = 8

        # Ensure we have at least one of each required character type
        alphabet_upper = string.ascii_uppercase
        alphabet_lower = string.ascii_lowercase
        alphabet_digits = string.digits
        alphabet_special = "!@#$%^&*"

        # Start with at least one of each type
        password = [
            secrets.choice(alphabet_upper),
            secrets.choice(alphabet_lower),
            secrets.choice(alphabet_digits),
            secrets.choice(alphabet_special),
        ]

        # Fill the rest with random choices from all characters
        all_chars = alphabet_upper + alphabet_lower + alphabet_digits + alphabet_special
        for _ in range(length - 4):
            password.append(secrets.choice(all_chars))

        # Shuffle to avoid predictable pattern
        secrets.SystemRandom().shuffle(password)

        return ''.join(password)

    async def set_user_password(self, user_id: str, password: str, temporary: bool = True) -> bool:
        """Set a new password for a user directly."""
        try:
            payload = {"type": "password", "value": password, "temporary": temporary}
            response = await self._make_admin_request("PUT", f"/users/{user_id}/reset-password", payload)

            if response.status_code == 204:
                logger.info("User password set successfully", extra={"user_id": user_id})
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")
            logger.error("Failed to set user password", extra={"user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error setting user password", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return False

    # ── Role methods ────────────────────────────────────────────────

    async def get_realm_roles(self) -> list[dict[str, Any]]:
        """Get all realm roles."""
        try:
            response = await self._make_admin_request("GET", "/roles")
            if response.status_code == 200:
                return response.json()
            logger.error("Failed to get realm roles", extra={"status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Error getting realm roles", exc_info=True, extra={"error_type": type(e).__name__})
            return []

    async def get_user_realm_roles(self, user_id: str) -> list[dict[str, Any]]:
        """Get realm roles assigned to a user."""
        try:
            response = await self._make_admin_request("GET", f"/users/{user_id}/role-mappings/realm")
            if response.status_code == 200:
                return response.json()
            if response.status_code == 404:
                return []
            logger.error("Failed to get user roles", extra={"user_id": user_id, "status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Error getting user roles", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return []

    async def assign_realm_roles_to_user(self, user_id: str, roles: list[dict[str, Any]]) -> bool:
        """Assign realm roles to a user."""
        try:
            response = await self._make_admin_request("POST", f"/users/{user_id}/role-mappings/realm", roles)
            if response.status_code == 204:
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")
            logger.error("Failed to assign roles", extra={"user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error assigning roles", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return False

    async def remove_realm_roles_from_user(self, user_id: str, roles: list[dict[str, Any]]) -> bool:
        """Remove realm roles from a user."""
        try:
            response = await self._make_admin_request("DELETE", f"/users/{user_id}/role-mappings/realm", roles)
            if response.status_code == 204:
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="User not found")
            logger.error("Failed to remove roles", extra={"user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error removing roles", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return False

    async def sync_user_realm_roles(self, user_id: str, target_roles: list[str]) -> bool:
        """Sync user realm roles to match target roles list (add missing, remove extra)."""
        try:
            internal_roles = {
                "uma_authorization",
                "offline_access",
                f"default-roles-{settings.KEYCLOAK_REALM.lower()}",
            }

            all_realm_roles = await self.get_realm_roles()
            role_name_to_obj = {role["name"]: role for role in all_realm_roles}

            current_user_roles = await self.get_user_realm_roles(user_id)
            current_role_names = {
                role["name"]
                for role in current_user_roles
                if role["name"] not in internal_roles and not role["name"].startswith("realm-management")
            }

            target_role_names = set(target_roles)
            roles_to_add = target_role_names - current_role_names
            roles_to_remove = current_role_names - target_role_names

            logger.info(
                "Role sync for user",
                extra={
                    "user_id": user_id,
                    "current": list(current_role_names),
                    "target": list(target_role_names),
                    "to_add": list(roles_to_add),
                    "to_remove": list(roles_to_remove),
                },
            )

            success = True

            if roles_to_add:
                roles_to_add_objs = []
                for role_name in roles_to_add:
                    if role_name in role_name_to_obj:
                        roles_to_add_objs.append(role_name_to_obj[role_name])
                    else:
                        logger.warning("Role not found in realm", extra={"role_name": role_name})
                if roles_to_add_objs:
                    if not await self.assign_realm_roles_to_user(user_id, roles_to_add_objs):
                        success = False

            if roles_to_remove:
                roles_to_remove_objs = [role for role in current_user_roles if role["name"] in roles_to_remove]
                if roles_to_remove_objs:
                    if not await self.remove_realm_roles_from_user(user_id, roles_to_remove_objs):
                        success = False

            return success
        except Exception as e:
            logger.error("Error syncing user roles", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return False

    # ── Organization methods ────────────────────────────────────────

    async def get_organization(self, organization_id: str) -> Optional[dict[str, Any]]:
        """Get a specific organization by ID from Keycloak.

        Returns:
            Organization dictionary if found, None on unexpected errors.

        Raises:
            HTTPException 404: If organization not found.
        """
        try:
            endpoint = f"/organizations/{organization_id}"
            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                org = response.json()
                logger.info(
                    "Successfully fetched organization from Keycloak",
                    extra={
                        "organization_id": organization_id,
                        "organization_name": org.get("name"),
                    }
                )
                return org
            elif response.status_code == 404:
                logger.warning(
                    "Organization not found in Keycloak",
                    extra={"organization_id": organization_id},
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail=f"Organization {organization_id} not found",
                )
            else:
                logger.error(
                    "Failed to get organization from Keycloak",
                    extra={
                        "organization_id": organization_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500],
                    }
                )
                return None

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while getting organization from Keycloak",
                exc_info=True,
                extra={
                    "organization_id": organization_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e),
                }
            )
            return None

    async def get_organizations(self) -> list[dict[str, Any]]:
        """Get all organizations from Keycloak."""
        try:
            response = await self._make_admin_request("GET", "/organizations")
            if response.status_code == 200:
                return response.json()
            logger.error("Failed to get organizations", extra={"status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Exception getting organizations", exc_info=True, extra={"error_type": type(e).__name__})
            return []

    async def search_organizations(self, search: str) -> list[dict[str, Any]]:
        """Search organizations by name."""
        try:
            response = await self._make_admin_request("GET", f"/organizations?search={quote(search.strip())}")
            if response.status_code == 200:
                return response.json()
            logger.error("Failed to search organizations", extra={"status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Exception searching organizations", exc_info=True, extra={"error_type": type(e).__name__})
            return []

    async def get_organization_members(
        self, organization_id: str, first: int = 0, max_results: int = 100, search: Optional[str] = None
    ) -> list[dict[str, Any]]:
        """Get members of a specific Keycloak organization."""
        try:
            endpoint = f"/organizations/{organization_id}/members?first={first}&max={max_results}"
            if search:
                endpoint += f"&search={search}"

            response = await self._make_admin_request("GET", endpoint)
            if response.status_code == 200:
                return response.json()
            if response.status_code == 404:
                logger.warning("Organization not found", extra={"organization_id": organization_id})
            else:
                logger.error("Failed to get org members", extra={"organization_id": organization_id, "status_code": response.status_code})
            return []
        except Exception as e:
            logger.error("Error getting organization members", exc_info=True, extra={"organization_id": organization_id, "error_type": type(e).__name__})
            return []

    async def count_organization_members(self, organization_id: str) -> int:
        """Get total count of members in an organization."""
        try:
            # Fetch with max=1 to check X-Total-Count header first
            endpoint = f"/organizations/{organization_id}/members?first=0&max=1"
            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                total = response.headers.get("X-Total-Count")
                if total:
                    return int(total)
                # Fallback: fetch all and count
                all_members = await self.get_organization_members(
                    organization_id, first=0, max_results=10000
                )
                return len(all_members)
            if response.status_code == 404:
                logger.warning("Organization not found", extra={"organization_id": organization_id, "status_code": response.status_code})
                return 0
            logger.error("Failed to count organization members", extra={"organization_id": organization_id, "status_code": response.status_code})
            return 0
        except Exception as e:
            logger.error("Exception counting organization members", exc_info=True, extra={"organization_id": organization_id, "error_type": type(e).__name__})
            return 0

    async def add_user_to_organization(self, organization_id: str, user_id: str) -> bool:
        """Add a user to an organization."""
        try:
            response = await self._make_admin_request(
                "POST", f"/organizations/{organization_id}/members", data=user_id
            )
            if response.status_code in [204, 201]:
                logger.info("Added user to organization", extra={"organization_id": organization_id, "user_id": user_id})
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Organization or user not found")
            logger.error("Failed to add user to org", extra={"organization_id": organization_id, "user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error adding user to organization", exc_info=True, extra={"organization_id": organization_id, "user_id": user_id, "error_type": type(e).__name__})
            return False

    async def remove_user_from_organization(self, organization_id: str, user_id: str) -> bool:
        """Remove a user from an organization."""
        try:
            response = await self._make_admin_request("DELETE", f"/organizations/{organization_id}/members/{user_id}")
            if response.status_code == 204:
                return True
            if response.status_code == 404:
                raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Organization or user not found")
            logger.error("Failed to remove user from org", extra={"organization_id": organization_id, "user_id": user_id, "status_code": response.status_code})
            return False
        except HTTPException:
            raise
        except Exception as e:
            logger.error("Error removing user from organization", exc_info=True, extra={"organization_id": organization_id, "user_id": user_id, "error_type": type(e).__name__})
            return False

    async def get_user_organization_optimized(self, user_id: str) -> dict[str, Any] | None:
        """Get the organization for a single user efficiently.

        Fetches all organizations and checks membership in parallel.

        Returns:
            Organization dict with 'id' and 'name', or None if user has no organization.
        """
        try:
            all_orgs = await self.get_organizations()
            if not all_orgs:
                return None

            async def check_membership(org: dict[str, Any]) -> dict[str, Any] | None:
                org_id = org.get("id")
                if not org_id:
                    return None
                try:
                    members = await self.get_organization_members(org_id, first=0, max_results=10000)
                    for member in members:
                        if member.get("id") == user_id:
                            return {"id": org_id, "name": org.get("name")}
                except Exception as e:
                    logger.warning("Failed to check membership for org", extra={"organization_id": org_id, "error_type": type(e).__name__})
                return None

            results = await asyncio.gather(
                *[check_membership(org) for org in all_orgs],
                return_exceptions=True,
            )

            for result in results:
                if isinstance(result, dict) and result is not None:
                    logger.info(
                        "Found user organization",
                        extra={"user_id": user_id, "organization_id": result.get("id")},
                    )
                    return result

            return None
        except Exception:
            logger.error("Exception while getting user organization", exc_info=True, extra={"user_id": user_id})
            return None

    # ── Session Management Methods ─────────────────────────────────

    async def get_user_sessions(self, user_id: str) -> list[dict[str, Any]]:
        """Get all active sessions for a user.

        Args:
            user_id: Keycloak user UUID.

        Returns:
            List of session dicts with id, ipAddress, start, lastAccess, clients.

        Raises:
            HTTPException 404: If user not found.
            HTTPException 502: If Keycloak returns an unexpected status code.
            HTTPException 503: If Keycloak is unreachable.
        """
        try:
            logger.info("Fetching user sessions from Keycloak", extra={"user_id": user_id})

            response = await self._make_admin_request("GET", f"/users/{user_id}/sessions")

            if response.status_code == 200:
                sessions = response.json()
                logger.info(
                    "Successfully fetched user sessions",
                    extra={"user_id": user_id, "session_count": len(sessions)},
                )
                return sessions
            if response.status_code == 404:
                logger.warning("User not found when fetching sessions", extra={"user_id": user_id})
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found",
                )
            logger.error(
                "Failed to get user sessions from Keycloak",
                extra={
                    "user_id": user_id,
                    "status_code": response.status_code,
                },
            )
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail="Unexpected response from identity provider",
            )

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while getting user sessions",
                exc_info=True,
                extra={"user_id": user_id, "error_type": type(e).__name__},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Identity provider unavailable",
            )

    async def revoke_session(self, session_id: str) -> bool:
        """Revoke a specific session by ID.

        Args:
            session_id: Keycloak session UUID.

        Returns:
            True if session was revoked successfully.

        Raises:
            HTTPException 404: If session not found.
            HTTPException 502: If Keycloak returns an unexpected status code.
            HTTPException 503: If Keycloak is unreachable.
        """
        try:
            logger.info("Revoking session in Keycloak", extra={"session_id": session_id})

            response = await self._make_admin_request("DELETE", f"/sessions/{session_id}")

            if response.status_code == 204:
                logger.info("Successfully revoked session", extra={"session_id": session_id})
                return True
            if response.status_code == 404:
                logger.warning("Session not found when revoking", extra={"session_id": session_id})
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Session not found",
                )
            logger.error(
                "Failed to revoke session in Keycloak",
                extra={
                    "session_id": session_id,
                    "status_code": response.status_code,
                },
            )
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail="Unexpected response from identity provider",
            )

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while revoking session",
                exc_info=True,
                extra={"session_id": session_id, "error_type": type(e).__name__},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Identity provider unavailable",
            )

    async def revoke_all_user_sessions(self, user_id: str) -> bool:
        """Revoke all sessions for a user (logout from all devices).

        Args:
            user_id: Keycloak user UUID.

        Returns:
            True if all sessions were revoked successfully.

        Raises:
            HTTPException 404: If user not found.
            HTTPException 502: If Keycloak returns an unexpected status code.
            HTTPException 503: If Keycloak is unreachable.
        """
        try:
            logger.info("Revoking all user sessions in Keycloak", extra={"user_id": user_id})

            response = await self._make_admin_request("DELETE", f"/users/{user_id}/sessions")

            if response.status_code == 204:
                logger.info("Successfully revoked all user sessions", extra={"user_id": user_id})
                return True
            if response.status_code == 404:
                logger.warning("User not found when revoking all sessions", extra={"user_id": user_id})
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found",
                )
            logger.error(
                "Failed to revoke all user sessions in Keycloak",
                extra={
                    "user_id": user_id,
                    "status_code": response.status_code,
                },
            )
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail="Unexpected response from identity provider",
            )

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while revoking all user sessions",
                exc_info=True,
                extra={"user_id": user_id, "error_type": type(e).__name__},
            )
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Identity provider unavailable",
            )

    async def get_user_organizations(self, user_id: str) -> list[dict[str, Any]]:
        """Get all organizations that a user belongs to."""
        try:
            all_orgs = await self.get_organizations()
            user_orgs: list[dict[str, Any]] = []

            for org in all_orgs:
                org_id = org.get("id")
                if not org_id:
                    continue
                try:
                    members = await self.get_organization_members(org_id, first=0, max_results=10000)
                    for member in members:
                        if member.get("id") == user_id:
                            user_orgs.append({"id": org_id, "name": org.get("name"), "description": org.get("description", "")})
                            break
                except Exception as e:
                    logger.warning("Failed to check membership for org", extra={"organization_id": org_id, "error_type": type(e).__name__})
                    continue

            return user_orgs
        except Exception as e:
            logger.error("Exception getting user organizations", exc_info=True, extra={"user_id": user_id, "error_type": type(e).__name__})
            return []


# Global singleton
keycloak_admin_service = KeycloakAdminService()
