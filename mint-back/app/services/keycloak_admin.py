"""
Keycloak Admin Service for user management operations
"""

import httpx
import secrets
import string
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
        self.admin_username = settings.KEYCLOAK_ADMIN_USERNAME
        self.admin_password = settings.KEYCLOAK_ADMIN_PASSWORD
        self.admin_client_id = settings.KEYCLOAK_ADMIN_CLIENT_ID
        self.admin_client_secret = settings.KEYCLOAK_ADMIN_CLIENT_SECRET
        self._admin_token = None
        self._token_expiry = None
    
    async def _get_admin_token(self) -> str:
        """Get admin access token using admin credentials"""
        try:
            token_url = f"{self.server_url}/realms/master/protocol/openid-connect/token"
            
            async with httpx.AsyncClient() as client:
                response = await client.post(
                    token_url,
                    headers={"Content-Type": "application/x-www-form-urlencoded"},
                    data={
                        "grant_type": "password",
                        "client_id": "admin-cli",
                        "username": self.admin_username,
                        "password": self.admin_password
                    }
                )
                
                if response.status_code != 200:
                    logger.error(f"Failed to get admin token: {response.status_code} - {response.text}")
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail="Failed to authenticate with Keycloak admin"
                    )
                
                token_data = response.json()
                return token_data["access_token"]
                
        except httpx.RequestError as e:
            logger.error(f"Error connecting to Keycloak: {e}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Unable to connect to Keycloak server"
            )
    
    async def _make_admin_request(self, method: str, endpoint: str, data: Optional[Dict] = None) -> httpx.Response:
        """Make authenticated request to Keycloak Admin API"""
        token = await self._get_admin_token()
        
        async with httpx.AsyncClient() as client:
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
            
            # Create user
            response = await self._make_admin_request("POST", "/users", keycloak_payload)
            
            if response.status_code == 201:
                # Get the created user's ID from Location header
                location = response.headers.get("Location", "")
                user_id = location.split("/")[-1] if location else None
                
                if user_id:
                    # Get the created user details
                    user_details = await self.get_user(user_id)
                    if user_details:
                        user_details["temporaryPassword"] = temp_password
                        return user_details
                
                raise HTTPException(
                    status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                    detail="User created but could not retrieve details"
                )
                
            elif response.status_code == 409:
                error_detail = response.json() if response.headers.get("content-type", "").startswith("application/json") else {"errorMessage": "User already exists"}
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
                logger.error(f"Failed to create user: {response.status_code} - {error_text}")
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail=f"Failed to create user: {error_text}"
                )
                
        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"Error creating user: {e}")
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
                logger.error(f"Failed to get user: {response.status_code} - {response.text}")
                return None
                
        except Exception as e:
            logger.error(f"Error getting user: {e}")
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


# Global instance
keycloak_admin_service = KeycloakAdminService()