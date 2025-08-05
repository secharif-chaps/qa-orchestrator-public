from keycloak import KeycloakOpenID, KeycloakAdmin
from jose import JWTError, jwt
from fastapi import HTTPException, status
from app.core.config import settings
from app.schemas.user import TokenData
from typing import Optional, Dict, Any
import json


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
        except Exception as e:
            # Log error without exposing sensitive information
            print(f"Authentication failed for user {username}: Authentication error")
            return None

    async def refresh_token(self, refresh_token: str) -> Optional[Dict[str, Any]]:
        """Refresh access token"""
        try:
            token = self.keycloak_openid.refresh_token(refresh_token)
            return token
        except Exception as e:
            return None

    async def logout(self, refresh_token: str) -> bool:
        """Logout user"""
        try:
            self.keycloak_openid.logout(refresh_token)
            return True
        except Exception as e:
            return False

    async def get_user_info(self, access_token: str) -> Optional[Dict[str, Any]]:
        """Get user info from access token"""
        try:
            print(f"Calling userinfo endpoint with token: {access_token[:20]}...")
            print(f"Keycloak server URL: {settings.KEYCLOAK_SERVER_URL}")
            userinfo = self.keycloak_openid.userinfo(access_token)
            print(f"Userinfo response: {userinfo}")
            return userinfo
        except Exception as e:
            print(f"Userinfo error: {type(e).__name__}: {str(e)}")
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
                
                return TokenData(username=username, sub=sub, roles=roles)
                
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
                    
                    return TokenData(username=username, sub=sub, roles=roles)
            except Exception:
                pass
            
            return None
            
        except Exception as e:
            print(f"Token verification error: {str(e)}")
            return None

    async def introspect_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Introspect token (server-side validation)"""
        try:
            print(f"Calling introspect endpoint with token: {token[:20]}...")
            token_info = self.keycloak_openid.introspect(token)
            print(f"Introspect response: {token_info}")
            if token_info.get("active"):
                return token_info
            return None
        except Exception as e:
            print(f"Introspect error: {type(e).__name__}: {str(e)}")
            return None


# Global instance
keycloak_service = KeycloakService()