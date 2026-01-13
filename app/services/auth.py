from keycloak import KeycloakOpenID, KeycloakAdmin
from jose import jwt
from app.core.config import settings
from app.core.logging_config import get_logger
from app.schemas.user import TokenData
from typing import Optional, Dict, Any
import requests

logger = get_logger(__name__)


class KeycloakService:
    def __init__(self):
        # Remove trailing /auth if present (library adds it automatically)
        server_url = settings.KEYCLOAK_SERVER_URL.removesuffix('/auth').removesuffix('/')

        self.keycloak_openid = KeycloakOpenID(
            server_url=server_url,
            client_id=settings.KEYCLOAK_CLIENT_ID,
            realm_name=settings.KEYCLOAK_REALM,
            client_secret_key=settings.KEYCLOAK_CLIENT_SECRET
        )
        
        # For admin operations (optional)
        self.keycloak_admin = None
        if settings.KEYCLOAK_CLIENT_SECRET:
            try:
                self.keycloak_admin = KeycloakAdmin(
                    server_url=server_url,
                    username=settings.KEYCLOAK_ADMIN_USERNAME,
                    password=settings.KEYCLOAK_ADMIN_PASSWORD,
                    realm_name=settings.KEYCLOAK_REALM,
                    verify=True
                )
            except Exception:
                # Admin connection is optional
                pass

    async def authenticate_user(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """Authenticate user with Keycloak using direct HTTP request"""
        try:
            # Build token endpoint URL
            # Note: settings.KEYCLOAK_SERVER_URL should be https://sso.deveryware.net/auth
            server_url = settings.KEYCLOAK_SERVER_URL.removesuffix('/')
            token_url = f"{server_url}/realms/{settings.KEYCLOAK_REALM}/protocol/openid-connect/token"

            # Prepare request payload
            payload = {
                "grant_type": "password",
                "client_id": settings.KEYCLOAK_CLIENT_ID,
                "username": username,
                "password": password
            }

            # Add client_secret if configured
            if settings.KEYCLOAK_CLIENT_SECRET:
                payload["client_secret"] = settings.KEYCLOAK_CLIENT_SECRET

            logger.debug(f"Authenticating user {username} at {token_url}")

            # Make HTTP request
            response = requests.post(token_url, data=payload, timeout=10)

            if response.status_code == 200:
                logger.info(f"Successfully authenticated user {username}")
                return response.json()
            else:
                logger.warning(f"Authentication failed for user {username}: HTTP {response.status_code}")
                logger.debug(f"Response: {response.text[:200]}")
                return None

        except requests.exceptions.RequestException as e:
            logger.error(f"Network error during authentication for user {username}: {str(e)}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error during authentication for user {username}: {str(e)}")
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

# Global instance
keycloak_service = KeycloakService()