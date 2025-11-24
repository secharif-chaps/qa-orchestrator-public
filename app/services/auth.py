from keycloak import KeycloakOpenID, KeycloakAdmin
from jose import jwt
from app.core.config import settings
from app.core.logging_config import get_logger
from app.schemas.user import TokenData
from typing import Optional, Dict, Any

logger = get_logger(__name__)


class KeycloakService:
    def __init__(self):
        self.keycloak_openid = KeycloakOpenID(
            server_url=settings.KEYCLOAK_SERVER_URL,
            client_id=settings.KEYCLOAK_CLIENT_ID,
            realm_name=settings.KEYCLOAK_REALM,
            client_secret_key=settings.KEYCLOAK_CLIENT_SECRET
        )
        
        # For admin operations (optional)
        self.keycloak_admin = None
        if settings.KEYCLOAK_CLIENT_SECRET:
            try:
                self.keycloak_admin = KeycloakAdmin(
                    server_url=settings.KEYCLOAK_SERVER_URL,
                    username=settings.KEYCLOAK_ADMIN_USERNAME,
                    password=settings.KEYCLOAK_ADMIN_PASSWORD,
                    realm_name=settings.KEYCLOAK_REALM,
                    verify=True
                )
            except Exception:
                # Admin connection is optional
                pass

    async def authenticate_user(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """Authenticate user with Keycloak"""
        try:
            token = self.keycloak_openid.token(username, password)
            return token
        except Exception:
            # Log error without exposing sensitive information
            logger.debug(f"Authentication failed for user {username}: Authentication error")
            return None

    async def refresh_token(self, refresh_token: str) -> Optional[Dict[str, Any]]:
        """Refresh access token"""
        try:
            token = self.keycloak_openid.refresh_token(refresh_token)
            return token
        except Exception:
            return None

    async def logout(self, refresh_token: str) -> bool:
        """Logout user"""
        try:
            self.keycloak_openid.logout(refresh_token)
            return True
        except Exception:
            return False

    async def get_user_info(self, access_token: str) -> Optional[Dict[str, Any]]:
        """Get user info from access token"""
        try:
            logger.debug(f"Calling userinfo endpoint with token: {access_token[:20]}...")
            logger.debug(f"Keycloak server URL: {settings.KEYCLOAK_SERVER_URL}")
            userinfo = self.keycloak_openid.userinfo(access_token)
            logger.debug(f"Userinfo response: {userinfo}")
            return userinfo
        except Exception as e:
            logger.debug(f"Userinfo error: {type(e).__name__}: {str(e)}")
            return None

    async def verify_token(self, token: str) -> Optional[TokenData]:
        """Verify and decode JWT token with proper signature verification"""
        try:
            # Try JWT decoding first (works with any valid token from the realm)
            try:
                # Get the public key from Keycloak for JWT verification
                public_key = self.keycloak_openid.public_key()
                key = f"-----BEGIN PUBLIC KEY-----\n{public_key}\n-----END PUBLIC KEY-----"
                
                # Decode and verify the JWT token
                options = {
                    "verify_signature": True,
                    "verify_aud": False,  # Don't verify audience for now
                    "verify_exp": True,   # Verify expiration
                }
                
                payload = jwt.decode(
                    token, 
                    key, 
                    algorithms=[settings.JWT_ALGORITHM],
                    options=options
                )
                
                # Extract user information
                username = payload.get("preferred_username")
                sub = payload.get("sub")
                
                # Extract roles
                realm_access = payload.get("realm_access", {})
                roles = realm_access.get("roles", ["user"])

                # Ensure admin role is properly assigned
                if username == "admin":
                    if "admin" not in roles:
                        roles.append("admin")

                return TokenData(
                    username=username,
                    sub=sub,
                    roles=roles
                )
                
            except jwt.ExpiredSignatureError:
                return None
            except jwt.JWTError:
                pass
            except Exception:
                pass
            
            # Fallback to userinfo endpoint if JWT decoding fails
            try:
                userinfo = await self.get_user_info(token)
                if userinfo:
                    username = userinfo.get("preferred_username")
                    sub = userinfo.get("sub")
                    
                    # Check if user is admin (you can customize this logic)
                    roles = ["user"]
                    if username == "admin":
                        roles.append("admin")

                    return TokenData(
                        username=username,
                        sub=sub,
                        roles=roles
                    )
            except Exception:
                pass
            
            return None
            
        except Exception as e:
            logger.debug(f"Token verification error: {str(e)}")
            return None

    async def introspect_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Introspect token (server-side validation)"""
        try:
            logger.debug(f"Calling introspect endpoint with token: {token[:20]}...")
            token_info = self.keycloak_openid.introspect(token)
            logger.debug(f"Introspect response: {token_info}")
            if token_info.get("active"):
                return token_info
            return None
        except Exception as e:
            logger.debug(f"Introspect error: {type(e).__name__}: {str(e)}")
            return None

    def assign_user_to_organization(self, user_id: str, organization_id: str) -> bool:
        """Assign a user to a Keycloak organization.

        This method uses the Keycloak Admin API to manage organization membership.
        The user will be removed from their current organization (if any) and
        added to the specified organization.

        Args:
            user_id: Keycloak user UUID (from JWT sub claim)
            organization_id: Keycloak organization UUID

        Returns:
            True if successful, False otherwise

        Raises:
            ValueError: If keycloak_admin is not initialized
            Exception: If Keycloak API call fails
        """
        if not self.keycloak_admin:
            raise ValueError("Keycloak admin connection not initialized")

        try:
            import logging
            logger = logging.getLogger(__name__)
            logger.info(f"Assigning user {user_id} to organization {organization_id}")

            # Get user's current organizations
            current_orgs = self.keycloak_admin.get_user_organizations(user_id)
            logger.debug(f"User {user_id} current organizations: {current_orgs}")

            # Remove user from current organizations
            for org in current_orgs:
                org_id = org.get('id')
                if org_id:
                    logger.info(f"Removing user {user_id} from organization {org_id}")
                    self.keycloak_admin.delete_user_from_organization(
                        user_id=user_id,
                        organization_id=org_id
                    )

            # Add user to new organization
            logger.info(f"Adding user {user_id} to organization {organization_id}")
            self.keycloak_admin.add_user_to_organization(
                user_id=user_id,
                organization_id=organization_id
            )

            logger.info(f"Successfully assigned user {user_id} to organization {organization_id}")
            return True

        except Exception as e:
            import logging
            logger = logging.getLogger(__name__)
            logger.error(f"Failed to assign user to organization: {str(e)}", exc_info=True)
            raise


# Global instance
keycloak_service = KeycloakService()