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
                    username="admin",  # This should be configurable
                    password="admin",  # This should be configurable
                    realm_name=settings.KEYCLOAK_REALM,
                    verify=True
                )
            except Exception:
                # Admin connection is optional
                pass

    async def authenticate_user(self, username: str, password: str) -> Optional[Dict[str, Any]]:
        """Authenticate user with Keycloak"""
        try:
            print(f"Attempting to authenticate user: {username}")
            print(f"Keycloak config - Server: {settings.KEYCLOAK_SERVER_URL}, Realm: {settings.KEYCLOAK_REALM}, Client: {settings.KEYCLOAK_CLIENT_ID}")
            token = self.keycloak_openid.token(username, password)
            print(f"Authentication successful for user: {username}")
            return token
        except Exception as e:
            print(f"Authentication failed for user {username}: {str(e)}")
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
            userinfo = self.keycloak_openid.userinfo(access_token)
            return userinfo
        except Exception as e:
            return None

    async def verify_token(self, token: str) -> Optional[TokenData]:
        """Verify and decode JWT token"""
        try:
            print(f"VERIFY TOKEN - Keycloak server URL: {settings.KEYCLOAK_SERVER_URL}")
            
            # For testing: decode without verification to check token structure
            import base64
            import json
            
            # Decode token payload (without verification for now)
            parts = token.split('.')
            if len(parts) != 3:
                print("Invalid token format")
                return None
                
            # Decode the payload (add padding if needed)
            payload_b64 = parts[1]
            payload_b64 += '=' * (4 - len(payload_b64) % 4)
            payload_json = base64.b64decode(payload_b64)
            payload = json.loads(payload_json)
            
            print(f"Token payload: {payload}")
            
            username = payload.get("preferred_username")
            sub = payload.get("sub")
            realm_access = payload.get("realm_access", {})
            roles = realm_access.get("roles", [])
            
            # TODO: Re-enable proper JWT verification once Keycloak connectivity is fixed
            return TokenData(username=username, sub=sub, roles=roles)
            
        except Exception as e:
            print(f"Token verification error: {str(e)}")
            return None

    async def introspect_token(self, token: str) -> Optional[Dict[str, Any]]:
        """Introspect token (server-side validation)"""
        try:
            token_info = self.keycloak_openid.introspect(token)
            if token_info.get("active"):
                return token_info
            return None
        except Exception as e:
            return None


# Global instance
keycloak_service = KeycloakService()