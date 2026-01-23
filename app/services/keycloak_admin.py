"""
Keycloak Admin Service for user management operations
"""

import asyncio
import httpx
import secrets
import string
import time
from typing import Dict, List, Optional, Any
from fastapi import HTTPException, status
from app.core.config import settings
import logging

logger = logging.getLogger(__name__)


class KeycloakAdminService:
    """Service for Keycloak Admin API operations"""

    def __init__(self):
        self.server_url = settings.KEYCLOAK_SERVER_URL
        self.realm = settings.KEYCLOAK_REALM
        self.admin_client_id = settings.KEYCLOAK_ADMIN_CLIENT_ID
        self.admin_client_secret = settings.KEYCLOAK_ADMIN_CLIENT_SECRET
        self._admin_token: Optional[str] = None
        self._token_expiry: Optional[float] = None
        # Async lock to prevent concurrent token refreshes
        self._token_lock = asyncio.Lock()

    def _is_token_valid(self) -> bool:
        """Check if cached token is still valid (with 30 second buffer)"""
        if not self._admin_token or not self._token_expiry:
            return False
        # Add 30 second buffer to refresh token before it actually expires
        return time.time() < (self._token_expiry - 30)

    async def _get_admin_token(self) -> str:
        """Get admin access token using client credentials grant (with caching and thread-safety)"""
        # First check without lock (fast path)
        if self._is_token_valid():
            logger.debug("🔑 Using cached admin token")
            return self._admin_token

        # Acquire lock for token refresh
        async with self._token_lock:
            # Double-check pattern: another request might have refreshed while we waited for lock
            if self._is_token_valid():
                logger.debug("🔒 Token refreshed by another request, using cached token")
                return self._admin_token

            # Token refresh with retry logic
            return await self._refresh_admin_token()

    async def _refresh_admin_token(self) -> str:
        """Internal method to refresh the admin token (called within lock) with retry logic"""
        token_url = f"{self.server_url}/realms/{self.realm}/protocol/openid-connect/token"
        max_retries = 3
        retry_delay = 1.0  # Start with 1 second

        for attempt in range(1, max_retries + 1):
            try:
                logger.info(
                    f"🔄 Requesting new admin token from Keycloak (attempt {attempt}/{max_retries})",
                    extra={
                        "token_url": token_url,
                        "client_id": self.admin_client_id,
                        "realm": self.realm,
                        "grant_type": "client_credentials",
                        "attempt": attempt
                    }
                )

                # Configure timeout: 10 seconds connect, 30 seconds read
                timeout = httpx.Timeout(10.0, read=30.0)

                async with httpx.AsyncClient(timeout=timeout) as client:
                    response = await client.post(
                        token_url,
                        headers={"Content-Type": "application/x-www-form-urlencoded"},
                        data={
                            "grant_type": "client_credentials",
                            "client_id": self.admin_client_id,
                            "client_secret": self.admin_client_secret
                        }
                    )

                # Check response status
                if response.status_code == 200:
                    # Success! Cache token and return
                    token_data = response.json()
                    self._admin_token = token_data["access_token"]
                    expires_in = token_data.get("expires_in", 300)  # Default 5 minutes
                    self._token_expiry = time.time() + expires_in

                    logger.info(
                        "✅ Successfully obtained and cached admin token",
                        extra={
                            "expires_in_seconds": expires_in,
                            "expires_at": time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(self._token_expiry)),
                            "attempt": attempt
                        }
                    )
                    return self._admin_token

                # Non-200 response - log and raise (don't retry auth errors)
                logger.error(
                    "❌ Failed to get admin token from Keycloak",
                    extra={
                        "status_code": response.status_code,
                        "response_text": response.text,
                        "token_url": token_url,
                        "client_id": self.admin_client_id,
                        "attempt": attempt
                    }
                )

                # Don't retry authentication errors (401, 403)
                if response.status_code in [401, 403]:
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail=f"Authentication failed with Keycloak: {response.text}"
                    )

                # For other errors, raise to trigger retry
                raise httpx.HTTPStatusError(
                    f"HTTP {response.status_code}",
                    request=response.request,
                    response=response
                )

            except httpx.RequestError as e:
                # Network/timeout errors - retry with exponential backoff
                is_last_attempt = attempt == max_retries

                logger.warning(
                    f"⚠️ Network error on attempt {attempt}/{max_retries}: {type(e).__name__}",
                    extra={
                        "error_type": type(e).__name__,
                        "error_message": str(e),
                        "server_url": self.server_url,
                        "attempt": attempt,
                        "will_retry": not is_last_attempt
                    }
                )

                if is_last_attempt:
                    # Last attempt failed, give up
                    logger.error(
                        "❌ All retry attempts exhausted for admin token refresh",
                        exc_info=True,
                        extra={
                            "max_retries": max_retries,
                            "error_type": type(e).__name__,
                            "error_message": str(e)
                        }
                    )
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail="Unable to connect to Keycloak server after multiple retries"
                    )

                # Exponential backoff: 1s, 2s, 4s
                await asyncio.sleep(retry_delay)
                retry_delay *= 2

            except httpx.HTTPStatusError as e:
                # HTTP errors (non-200 responses) - retry
                is_last_attempt = attempt == max_retries

                if is_last_attempt:
                    logger.error(
                        "❌ All retry attempts exhausted for admin token refresh",
                        extra={
                            "status_code": e.response.status_code,
                            "response_text": e.response.text
                        }
                    )
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail=f"Failed to get admin token: HTTP {e.response.status_code}"
                    )

                # Exponential backoff
                logger.warning(f"⚠️ HTTP error on attempt {attempt}/{max_retries}, retrying in {retry_delay}s")
                await asyncio.sleep(retry_delay)
                retry_delay *= 2

        # Should never reach here due to exceptions, but for type safety
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to obtain admin token"
        )
    
    async def _make_admin_request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> httpx.Response:
        """Make authenticated request to Keycloak Admin API"""
        token = await self._get_admin_token()

        # Configure timeout: 30 seconds for connect, 60 seconds for read
        timeout = httpx.Timeout(30.0, read=60.0)

        async with httpx.AsyncClient(timeout=timeout) as client:
            headers = {
                "Authorization": f"Bearer {token}",
                "Content-Type": "application/json"
            }

            url = f"{self.server_url}/admin/realms/{self.realm}{endpoint}"

            if method.upper() == "GET":
                response = await client.get(url, headers=headers)
            elif method.upper() == "POST":
                response = await client.post(url, headers=headers, json=data)
            elif method.upper() == "PUT":
                response = await client.put(url, headers=headers, json=data)
            elif method.upper() == "DELETE":
                response = await client.delete(url, headers=headers)
            else:
                raise ValueError(f"Unsupported HTTP method: {method}")

            return response
    
    def _generate_temp_password(self, length: int = 12) -> str:
        """Generate a secure temporary password"""
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
            secrets.choice(alphabet_special)
        ]
        
        # Fill the rest with random choices from all characters
        all_chars = alphabet_upper + alphabet_lower + alphabet_digits + alphabet_special
        for _ in range(length - 4):
            password.append(secrets.choice(all_chars))
        
        # Shuffle to avoid predictable pattern
        secrets.SystemRandom().shuffle(password)
        
        return ''.join(password)
    
    async def create_user(self, user_data: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new user in Keycloak"""
        try:
            logger.info(
                "Starting user creation in Keycloak",
                extra={
                    "username": user_data.get("username"),
                    "email": user_data.get("email"),
                    "firstName": user_data.get("firstName"),
                    "lastName": user_data.get("lastName")
                }
            )

            # Generate temporary password if not provided
            temp_password = user_data.get("temporaryPassword") or self._generate_temp_password()

            # Prepare Keycloak user payload
            keycloak_payload = {
                "username": user_data["username"],
                "email": user_data["email"],
                "firstName": user_data.get("firstName", ""),
                "lastName": user_data.get("lastName", ""),
                "enabled": True,
                "emailVerified": True,  # Mark email as verified since no mail server
                "credentials": [{
                    "type": "password",
                    "value": temp_password,
                    "temporary": True  # Forces password reset on first login
                }]
            }

            logger.info(
                "Sending user creation request to Keycloak",
                extra={
                    "endpoint": f"{self.server_url}/admin/realms/{self.realm}/users",
                    "username": user_data["username"],
                    "email": user_data["email"]
                }
            )

            # Create user
            response = await self._make_admin_request("POST", "/users", keycloak_payload)

            logger.info(
                "Received response from Keycloak user creation",
                extra={
                    "status_code": response.status_code,
                    "response_headers": dict(response.headers),
                    "response_body_preview": response.text[:500] if response.status_code != 201 else "Created"
                }
            )

            if response.status_code == 201:
                # Get the created user's ID from Location header
                location = response.headers.get("Location", "")
                user_id = location.split("/")[-1] if location else None

                logger.info(
                    "User created successfully in Keycloak",
                    extra={
                        "user_id": user_id,
                        "location_header": location,
                        "username": user_data["username"]
                    }
                )

                if user_id:
                    # Get the created user details
                    user_details = await self.get_user(user_id)
                    if user_details:
                        user_details["temporaryPassword"] = temp_password
                        logger.info(
                            "Successfully retrieved created user details",
                            extra={"user_id": user_id, "username": user_data["username"]}
                        )
                        return user_details

                logger.error(
                    "User created but could not retrieve user ID or details",
                    extra={"location_header": location, "username": user_data["username"]}
                )
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail="User created but could not retrieve details"
                )

            elif response.status_code == 409:
                error_detail = response.json() if response.headers.get("content-type", "").startswith("application/json") else {"errorMessage": "User already exists"}
                logger.warning(
                    "User creation failed - conflict (user already exists)",
                    extra={
                        "username": user_data["username"],
                        "email": user_data["email"],
                        "error_detail": error_detail
                    }
                )
                if "username" in error_detail.get("errorMessage", "").lower():
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="Username already exists"
                    )
                elif "email" in error_detail.get("errorMessage", "").lower():
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="Email already exists"
                    )
                else:
                    raise HTTPException(
                        status_code=status.HTTP_409_CONFLICT,
                        detail="User already exists"
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
                        "response_headers": dict(response.headers)
                    }
                )
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail=f"Failed to create user: {error_text}"
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
                    "email": user_data.get("email")
                }
            )
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Internal error creating user"
            )
    
    async def get_user(self, user_id: str) -> Optional[Dict[str, Any]]:
        """Get user details by ID"""
        try:
            response = await self._make_admin_request("GET", f"/users/{user_id}")

            if response.status_code == 200:
                return response.json()
            elif response.status_code == 404:
                return None
            else:
                logger.error(
                    "Failed to get user from Keycloak",
                    extra={
                        "user_id": user_id,
                        "status_code": response.status_code,
                        "response_text": response.text
                    }
                )
                return None

        except Exception as e:
            logger.error(
                "Exception while getting user from Keycloak",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return None
    
    async def get_users(self, first: int = 0, max_results: int = 100) -> List[Dict[str, Any]]:
        """Get paginated list of users"""
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

    async def search_users(
        self,
        search: Optional[str] = None,
        first: int = 0,
        max_results: int = 100
    ) -> List[Dict[str, Any]]:
        """
        Search users using Keycloak's native search API.

        This method leverages Keycloak's built-in search functionality which searches
        across username, email, first name, and last name fields.

        Args:
            search: Search string for username, email, first name, last name.
                    If None or empty, returns all users paginated.
            first: Pagination offset (0-indexed)
            max_results: Maximum results to return

        Returns:
            List of user dictionaries from Keycloak
        """
        try:
            # Build query parameters
            params = [f"first={first}", f"max={max_results}"]

            if search and search.strip():
                # URL encode the search parameter
                from urllib.parse import quote
                encoded_search = quote(search.strip())
                params.append(f"search={encoded_search}")

            endpoint = f"/users?{'&'.join(params)}"

            logger.info(
                "Searching users in Keycloak",
                extra={
                    "search": search,
                    "first": first,
                    "max_results": max_results,
                    "endpoint": endpoint
                }
            )

            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                users = response.json()
                logger.info(
                    "Successfully searched users",
                    extra={"result_count": len(users), "search": search}
                )
                return users
            else:
                logger.error(
                    "Failed to search users",
                    extra={
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return []

        except Exception as e:
            logger.error(
                "Exception while searching users",
                exc_info=True,
                extra={
                    "search": search,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return []

    async def count_users_with_search(self, search: Optional[str] = None) -> int:
        """
        Count users using Keycloak's native count API with optional search filter.

        Args:
            search: Optional search string to filter count.
                    If None or empty, counts all users.

        Returns:
            Total count of matching users
        """
        try:
            # Build endpoint with optional search parameter
            endpoint = "/users/count"

            if search and search.strip():
                from urllib.parse import quote
                encoded_search = quote(search.strip())
                endpoint = f"/users/count?search={encoded_search}"

            logger.info(
                "Counting users in Keycloak",
                extra={"search": search, "endpoint": endpoint}
            )

            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                # Keycloak returns count as plain text
                count = int(response.text)
                logger.info(
                    "Successfully counted users",
                    extra={"count": count, "search": search}
                )
                return count
            else:
                logger.error(
                    "Failed to count users",
                    extra={
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return 0

        except ValueError as e:
            logger.error(
                "Failed to parse user count response",
                extra={"response_text": response.text, "error": str(e)}
            )
            return 0
        except Exception as e:
            logger.error(
                "Exception while counting users",
                exc_info=True,
                extra={
                    "search": search,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return 0

    async def get_user_organization_optimized(self, user_id: str) -> Optional[Dict[str, Any]]:
        """
        Get the organization for a single user efficiently.

        This method fetches all organizations and checks membership in parallel
        using asyncio.gather() for better performance.

        Args:
            user_id: Keycloak user UUID

        Returns:
            Organization dict with 'id' and 'name', or None if user has no organization
        """
        try:
            logger.info(
                "Getting organization for user",
                extra={"user_id": user_id}
            )

            # First, get all organizations
            all_orgs = await self.get_organizations()

            if not all_orgs:
                logger.info(
                    "No organizations found",
                    extra={"user_id": user_id}
                )
                return None

            # Check membership in all organizations in parallel
            async def check_membership(org: Dict[str, Any]) -> Optional[Dict[str, Any]]:
                org_id = org.get("id")
                if not org_id:
                    return None

                try:
                    # Get members of this organization (limited to check if user is member)
                    members = await self.get_organization_members(
                        org_id,
                        first=0,
                        max_results=10000  # Need to check all members
                    )

                    # Check if user is in members list
                    for member in members:
                        if member.get("id") == user_id:
                            return {
                                "id": org_id,
                                "name": org.get("name")
                            }
                except Exception as e:
                    logger.warning(
                        f"Failed to check membership for org {org_id}",
                        extra={"error": str(e), "org_id": org_id}
                    )

                return None

            # Run all membership checks in parallel
            results = await asyncio.gather(
                *[check_membership(org) for org in all_orgs],
                return_exceptions=True
            )

            # Find the first non-None result (user's organization)
            for result in results:
                if isinstance(result, dict) and result is not None:
                    logger.info(
                        "Found user organization",
                        extra={
                            "user_id": user_id,
                            "organization_id": result.get("id"),
                            "organization_name": result.get("name")
                        }
                    )
                    return result

            logger.info(
                "User has no organization",
                extra={"user_id": user_id}
            )
            return None

        except Exception as e:
            logger.error(
                "Exception while getting user organization",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return None
    
    async def update_user(self, user_id: str, user_data: Dict[str, Any]) -> bool:
        """Update user details"""
        try:
            # Prepare update payload
            update_payload = {}
            
            if "username" in user_data:
                update_payload["username"] = user_data["username"]
            if "email" in user_data:
                update_payload["email"] = user_data["email"]
            if "firstName" in user_data:
                update_payload["firstName"] = user_data["firstName"]
            if "lastName" in user_data:
                update_payload["lastName"] = user_data["lastName"]
            if "enabled" in user_data:
                update_payload["enabled"] = user_data["enabled"]
            
            response = await self._make_admin_request("PUT", f"/users/{user_id}", update_payload)
            
            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(f"Failed to update user: {response.status_code} - {response.text}")
                return False
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error updating user: {e}")
            return False
    
    async def delete_user(self, user_id: str) -> bool:
        """Delete user from Keycloak"""
        try:
            response = await self._make_admin_request("DELETE", f"/users/{user_id}")
            
            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(f"Failed to delete user: {response.status_code} - {response.text}")
                return False
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error deleting user: {e}")
            return False
    
    async def send_password_reset_email(self, user_id: str) -> bool:
        """Send password reset email to user"""
        try:
            # Execute UPDATE_PASSWORD action via email
            response = await self._make_admin_request(
                "PUT", 
                f"/users/{user_id}/execute-actions-email",
                ["UPDATE_PASSWORD"]
            )
            
            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(f"Failed to send password reset: {response.status_code} - {response.text}")
                return False
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error sending password reset: {e}")
            return False

    async def set_user_password(
        self, user_id: str, password: str, temporary: bool = True
    ) -> bool:
        """Set a new password for a user directly (without sending email).

        This allows admins to reset a user's password by generating a new one
        and sharing it with the user directly.

        Args:
            user_id: Keycloak user UUID
            password: The new password to set
            temporary: If True, user must change password on next login

        Returns:
            True if password was set successfully, False otherwise

        Raises:
            HTTPException 404: If user not found
        """
        try:
            logger.info(
                "Setting user password directly",
                extra={"user_id": user_id, "temporary": temporary}
            )

            # Keycloak reset-password endpoint expects a credential representation
            payload = {
                "type": "password",
                "value": password,
                "temporary": temporary
            }

            response = await self._make_admin_request(
                "PUT",
                f"/users/{user_id}/reset-password",
                payload
            )

            if response.status_code == 204:
                logger.info(
                    "User password set successfully",
                    extra={"user_id": user_id, "temporary": temporary}
                )
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(
                    f"Failed to set user password: {response.status_code} - {response.text}",
                    extra={"user_id": user_id}
                )
                return False

        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error setting user password: {e}", extra={"user_id": user_id})
            return False

    async def count_users(self) -> int:
        """Get total count of users"""
        try:
            response = await self._make_admin_request("GET", "/users/count")
            
            if response.status_code == 200:
                return int(response.text)
            else:
                logger.error(f"Failed to count users: {response.status_code} - {response.text}")
                return 0
                
        except Exception as e:
            logger.error(f"Error counting users: {e}")
            return 0
    
    async def get_realm_roles(self) -> List[Dict[str, Any]]:
        """Get all realm roles"""
        try:
            response = await self._make_admin_request("GET", "/roles")
            
            if response.status_code == 200:
                return response.json()
            else:
                logger.error(f"Failed to get realm roles: {response.status_code} - {response.text}")
                return []
                
        except Exception as e:
            logger.error(f"Error getting realm roles: {e}")
            return []
    
    async def get_user_realm_roles(self, user_id: str) -> List[Dict[str, Any]]:
        """Get realm roles assigned to a user"""
        try:
            response = await self._make_admin_request("GET", f"/users/{user_id}/role-mappings/realm")
            
            if response.status_code == 200:
                return response.json()
            elif response.status_code == 404:
                return []
            else:
                logger.error(f"Failed to get user roles: {response.status_code} - {response.text}")
                return []
                
        except Exception as e:
            logger.error(f"Error getting user roles: {e}")
            return []
    
    async def assign_realm_roles_to_user(self, user_id: str, roles: List[Dict[str, Any]]) -> bool:
        """Assign realm roles to a user"""
        try:
            response = await self._make_admin_request("POST", f"/users/{user_id}/role-mappings/realm", roles)
            
            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(f"Failed to assign roles: {response.status_code} - {response.text}")
                return False
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error assigning roles: {e}")
            return False
    
    async def remove_realm_roles_from_user(self, user_id: str, roles: List[Dict[str, Any]]) -> bool:
        """Remove realm roles from a user"""
        try:
            response = await self._make_admin_request("DELETE", f"/users/{user_id}/role-mappings/realm", roles)
            
            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(f"Failed to remove roles: {response.status_code} - {response.text}")
                return False
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error removing roles: {e}")
            return False
    
    async def sync_user_realm_roles(self, user_id: str, target_roles: List[str]) -> bool:
        """
        Sync user realm roles to match target roles list
        This will add missing roles and remove extra roles

        Args:
            user_id: Keycloak user ID
            target_roles: List of role names that the user should have

        Returns:
            True if sync was successful, False otherwise
        """
        try:
            # Define internal Keycloak roles that should never be removed
            internal_roles = {
                "uma_authorization",
                "offline_access",
                "default-roles-" + settings.KEYCLOAK_REALM.lower()
            }

            # Get all available realm roles
            all_realm_roles = await self.get_realm_roles()
            role_name_to_obj = {role['name']: role for role in all_realm_roles}

            # Get current user roles
            current_user_roles = await self.get_user_realm_roles(user_id)

            # Filter out internal roles and realm-management roles from current roles
            # We only want to manage application-level permissions
            current_role_names = {
                role['name'] for role in current_user_roles
                if role['name'] not in internal_roles and
                   not role['name'].startswith("realm-management")
            }

            target_role_names = set(target_roles)

            # Determine roles to add and remove
            # Now we're only comparing application roles, not internal Keycloak roles
            roles_to_add = target_role_names - current_role_names
            roles_to_remove = current_role_names - target_role_names

            logger.info(
                f"Role sync calculation for user {user_id}: "
                f"current={list(current_role_names)}, "
                f"target={list(target_role_names)}, "
                f"to_add={list(roles_to_add)}, "
                f"to_remove={list(roles_to_remove)}"
            )

            success = True

            # Add missing roles
            if roles_to_add:
                roles_to_add_objs = []
                for role_name in roles_to_add:
                    if role_name in role_name_to_obj:
                        roles_to_add_objs.append(role_name_to_obj[role_name])
                    else:
                        logger.warning(f"Role '{role_name}' not found in realm")

                if roles_to_add_objs:
                    if not await self.assign_realm_roles_to_user(user_id, roles_to_add_objs):
                        success = False

            # Remove extra roles
            if roles_to_remove:
                roles_to_remove_objs = [role for role in current_user_roles if role['name'] in roles_to_remove]
                if roles_to_remove_objs:
                    if not await self.remove_realm_roles_from_user(user_id, roles_to_remove_objs):
                        success = False

            if success:
                logger.info(f"Successfully synced roles for user {user_id}: +{len(roles_to_add)}, -{len(roles_to_remove)}")

            return success

        except Exception as e:
            logger.error(f"Error syncing user roles: {e}")
            return False

    # Organization-specific methods

    async def get_organization_members(
        self,
        organization_id: str,
        first: int = 0,
        max_results: int = 100,
        search: Optional[str] = None
    ) -> List[Dict[str, Any]]:
        """
        Get members of a specific Keycloak organization

        Args:
            organization_id: Keycloak organization UUID
            first: Offset for pagination (0-indexed)
            max_results: Maximum number of results to return
            search: Optional search query for filtering users by name, email, or username

        Returns:
            List of organization members with their details
        """
        try:
            # Build endpoint with pagination
            endpoint = f"/organizations/{organization_id}/members?first={first}&max={max_results}"

            # Add search parameter if provided
            if search:
                endpoint += f"&search={search}"

            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                return response.json()
            elif response.status_code == 404:
                logger.warning(f"Organization {organization_id} not found")
                return []
            else:
                logger.error(f"Failed to get organization members: {response.status_code} - {response.text}")
                return []

        except Exception as e:
            logger.error(f"Error getting organization members: {e}")
            return []

    async def count_organization_members(self, organization_id: str) -> int:
        """
        Get total count of members in an organization

        Args:
            organization_id: Keycloak organization UUID

        Returns:
            Total number of members
        """
        try:
            # Keycloak doesn't have a direct count endpoint for org members
            # So we need to fetch all members and count them
            # For better performance in production, consider caching this value
            endpoint = f"/organizations/{organization_id}/members?first=0&max=1"
            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                # Try to get count from headers if available
                total = response.headers.get('X-Total-Count')
                if total:
                    return int(total)

                # Fallback: fetch all and count (not ideal for large orgs)
                all_members = await self.get_organization_members(organization_id, first=0, max_results=10000)
                return len(all_members)
            elif response.status_code == 404:
                logger.warning(f"Organization {organization_id} not found")
                return 0
            else:
                logger.error(f"Failed to count organization members: {response.status_code}")
                return 0

        except Exception as e:
            logger.error(f"Error counting organization members: {e}")
            return 0

    async def add_user_to_organization(self, organization_id: str, user_id: str) -> bool:
        """
        Add a user to an organization

        Args:
            organization_id: Keycloak organization UUID
            user_id: Keycloak user UUID

        Returns:
            True if successful, False otherwise
        """
        try:
            logger.info(
                "Adding user to organization in Keycloak",
                extra={
                    "organization_id": organization_id,
                    "user_id": user_id,
                    "endpoint": f"{self.server_url}/admin/realms/{self.realm}/organizations/{organization_id}/members"
                }
            )

            # POST to /organizations/{id}/members with user_id in body
            # Keycloak expects the user ID as a JSON string in the request body
            response = await self._make_admin_request(
                "POST",
                f"/organizations/{organization_id}/members",
                data=user_id  # Send user_id as JSON string in body
            )

            logger.info(
                "Received response from Keycloak add user to organization",
                extra={
                    "status_code": response.status_code,
                    "response_headers": dict(response.headers),
                    "response_body": response.text[:500] if response.status_code not in [204, 201] else "Success",
                    "organization_id": organization_id,
                    "user_id": user_id
                }
            )

            if response.status_code in [204, 201]:
                logger.info(
                    "Successfully added user to organization",
                    extra={"organization_id": organization_id, "user_id": user_id}
                )
                return True
            elif response.status_code == 404:
                logger.error(
                    "Organization or user not found when adding user to organization",
                    extra={
                        "organization_id": organization_id,
                        "user_id": user_id,
                        "response_text": response.text
                    }
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Organization or user not found"
                )
            else:
                logger.error(
                    "Failed to add user to organization",
                    extra={
                        "status_code": response.status_code,
                        "response_text": response.text,
                        "organization_id": organization_id,
                        "user_id": user_id,
                        "response_headers": dict(response.headers)
                    }
                )
                return False

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Unexpected error adding user to organization",
                exc_info=True,
                extra={
                    "error_type": type(e).__name__,
                    "error_message": str(e),
                    "organization_id": organization_id,
                    "user_id": user_id
                }
            )
            return False

    async def remove_user_from_organization(self, organization_id: str, user_id: str) -> bool:
        """
        Remove a user from an organization

        Args:
            organization_id: Keycloak organization UUID
            user_id: Keycloak user UUID

        Returns:
            True if successful, False otherwise
        """
        try:
            response = await self._make_admin_request(
                "DELETE",
                f"/organizations/{organization_id}/members/{user_id}"
            )

            if response.status_code == 204:
                return True
            elif response.status_code == 404:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Organization or user not found"
                )
            else:
                logger.error(f"Failed to remove user from organization: {response.status_code} - {response.text}")
                return False

        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error removing user from organization: {e}")
            return False

    async def get_organizations(self) -> List[Dict[str, Any]]:
        """
        Get all organizations from Keycloak.

        This method fetches all organizations using the Keycloak Admin API
        with built-in retry logic, token caching, and connection pooling.

        Returns:
            List of organization dictionaries from Keycloak
            Empty list if request fails

        Note:
            Search/filtering should be done by the caller after fetching all organizations
        """
        try:
            endpoint = "/organizations"
            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                orgs = response.json()
                logger.info(
                    "Successfully fetched organizations from Keycloak",
                    extra={"organization_count": len(orgs)}
                )
                return orgs
            else:
                logger.error(
                    "Failed to get organizations from Keycloak",
                    extra={
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return []

        except Exception as e:
            logger.error(
                "Exception while getting organizations from Keycloak",
                exc_info=True,
                extra={
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return []

    async def get_organization(self, organization_id: str) -> Optional[Dict[str, Any]]:
        """
        Get a specific organization by ID from Keycloak.

        This method fetches organization details using the Keycloak Admin API
        with built-in retry logic, token caching, and connection pooling.

        Args:
            organization_id: Keycloak organization UUID

        Returns:
            Organization dictionary if found
            None if organization not found or request fails

        Raises:
            HTTPException: If organization not found (404)
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
                        "organization_name": org.get('name')
                    }
                )
                return org
            elif response.status_code == 404:
                logger.warning(
                    "Organization not found in Keycloak",
                    extra={"organization_id": organization_id}
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail=f"Organization {organization_id} not found"
                )
            else:
                logger.error(
                    "Failed to get organization from Keycloak",
                    extra={
                        "organization_id": organization_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
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
                    "error_message": str(e)
                }
            )
            return None

    # Session Management Methods

    async def get_user_sessions(self, user_id: str) -> List[Dict[str, Any]]:
        """
        Get all active sessions for a user.

        Args:
            user_id: Keycloak user UUID

        Returns:
            List of session dictionaries with id, ipAddress, start, lastAccess, clients

        Raises:
            HTTPException 404: If user not found
        """
        try:
            logger.info(
                "Fetching user sessions from Keycloak",
                extra={"user_id": user_id}
            )

            response = await self._make_admin_request("GET", f"/users/{user_id}/sessions")

            if response.status_code == 200:
                sessions = response.json()
                logger.info(
                    "Successfully fetched user sessions",
                    extra={"user_id": user_id, "session_count": len(sessions)}
                )
                return sessions
            elif response.status_code == 404:
                logger.warning(
                    "User not found when fetching sessions",
                    extra={"user_id": user_id}
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(
                    "Failed to get user sessions from Keycloak",
                    extra={
                        "user_id": user_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return []

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while getting user sessions",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return []

    async def revoke_session(self, session_id: str) -> bool:
        """
        Revoke a specific session by ID.

        Args:
            session_id: Keycloak session UUID

        Returns:
            True if session was revoked successfully

        Raises:
            HTTPException 404: If session not found
        """
        try:
            logger.info(
                "Revoking session in Keycloak",
                extra={"session_id": session_id}
            )

            response = await self._make_admin_request("DELETE", f"/sessions/{session_id}")

            if response.status_code == 204:
                logger.info(
                    "Successfully revoked session",
                    extra={"session_id": session_id}
                )
                return True
            elif response.status_code == 404:
                logger.warning(
                    "Session not found when revoking",
                    extra={"session_id": session_id}
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Session not found"
                )
            else:
                logger.error(
                    "Failed to revoke session in Keycloak",
                    extra={
                        "session_id": session_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return False

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while revoking session",
                exc_info=True,
                extra={
                    "session_id": session_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return False

    async def revoke_all_user_sessions(self, user_id: str) -> bool:
        """
        Revoke all sessions for a user (logout from all devices).

        Args:
            user_id: Keycloak user UUID

        Returns:
            True if all sessions were revoked successfully

        Raises:
            HTTPException 404: If user not found
        """
        try:
            logger.info(
                "Revoking all user sessions in Keycloak",
                extra={"user_id": user_id}
            )

            response = await self._make_admin_request("DELETE", f"/users/{user_id}/sessions")

            if response.status_code == 204:
                logger.info(
                    "Successfully revoked all user sessions",
                    extra={"user_id": user_id}
                )
                return True
            elif response.status_code == 404:
                logger.warning(
                    "User not found when revoking all sessions",
                    extra={"user_id": user_id}
                )
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="User not found"
                )
            else:
                logger.error(
                    "Failed to revoke all user sessions in Keycloak",
                    extra={
                        "user_id": user_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return False

        except HTTPException:
            raise
        except Exception as e:
            logger.error(
                "Exception while revoking all user sessions",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return False

    async def get_user_events(
        self,
        user_id: str,
        first: int = 0,
        max_results: int = 20,
        event_types: Optional[List[str]] = None
    ) -> List[Dict[str, Any]]:
        """
        Get user events (activity log) from Keycloak.

        Note: Events must be enabled in Keycloak realm settings.

        Args:
            user_id: Keycloak user UUID
            first: Offset for pagination (0-indexed)
            max_results: Maximum number of events to return
            event_types: Optional list of event types to filter (LOGIN, LOGIN_ERROR, LOGOUT, etc.)

        Returns:
            List of event dictionaries with time, type, ipAddress, details
        """
        try:
            logger.info(
                "Fetching user events from Keycloak",
                extra={
                    "user_id": user_id,
                    "first": first,
                    "max_results": max_results,
                    "event_types": event_types
                }
            )

            # Build query parameters
            endpoint = f"/events?user={user_id}&first={first}&max={max_results}"

            # Add event type filters if specified
            if event_types:
                for event_type in event_types:
                    endpoint += f"&type={event_type}"

            response = await self._make_admin_request("GET", endpoint)

            if response.status_code == 200:
                events = response.json()
                logger.info(
                    "Successfully fetched user events",
                    extra={"user_id": user_id, "event_count": len(events)}
                )
                return events
            else:
                logger.error(
                    "Failed to get user events from Keycloak",
                    extra={
                        "user_id": user_id,
                        "status_code": response.status_code,
                        "response_text": response.text[:500]
                    }
                )
                return []

        except Exception as e:
            logger.error(
                "Exception while getting user events",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return []

    async def get_user_organizations(self, user_id: str) -> List[Dict[str, Any]]:
        """
        Get all organizations that a user belongs to.

        Since Keycloak doesn't have a direct endpoint to get organizations for a user,
        we iterate through all organizations and check membership.

        Args:
            user_id: Keycloak user UUID

        Returns:
            List of organization dictionaries with id and name
        """
        try:
            logger.info(
                "Fetching organizations for user from Keycloak",
                extra={"user_id": user_id}
            )

            # Get all organizations
            all_orgs = await self.get_organizations()
            user_orgs: List[Dict[str, Any]] = []

            # Check each organization for user membership
            for org in all_orgs:
                org_id = org.get("id")
                if not org_id:
                    continue

                try:
                    # Get members of this organization
                    members = await self.get_organization_members(org_id, first=0, max_results=10000)

                    # Check if user is a member
                    for member in members:
                        if member.get("id") == user_id:
                            user_orgs.append({
                                "id": org_id,
                                "name": org.get("name"),
                                "description": org.get("description", "")
                            })
                            break  # User found in this org, move to next org

                except Exception as e:
                    logger.warning(
                        f"Failed to check membership for organization {org_id}",
                        extra={"error": str(e), "org_id": org_id}
                    )
                    continue

            logger.info(
                "Successfully retrieved user organizations",
                extra={
                    "user_id": user_id,
                    "organization_count": len(user_orgs),
                    "organization_names": [o.get("name") for o in user_orgs]
                }
            )
            return user_orgs

        except Exception as e:
            logger.error(
                "Exception while getting user organizations",
                exc_info=True,
                extra={
                    "user_id": user_id,
                    "error_type": type(e).__name__,
                    "error_message": str(e)
                }
            )
            return []


# Global instance
keycloak_admin_service = KeycloakAdminService()