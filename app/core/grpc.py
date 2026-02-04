"""
gRPC authentication interceptor with support for:
1. User JWT tokens (frontend)
2. Client credentials (service-to-service)
"""

import grpc
import asyncio
from typing import Callable, Optional
from google.protobuf import message
import logging

from app.core.keycloak import idp, OIDCUser
from app.core.client_auth import introspect_token, ClientAuthError

logger = logging.getLogger(__name__)


# Async helper (safe for gRPC threads)
def run_async(coro):
    """
    Run an async coroutine in a synchronous context.
    Creates a new event loop to avoid conflicts with running loops.
    """
    loop = asyncio.new_event_loop()
    asyncio.set_event_loop(loop)
    try:
        return loop.run_until_complete(coro)
    finally:
        loop.close()


class GrpcAuthInterceptor(grpc.ServerInterceptor):
    """
    gRPC authentication interceptor.

    Rules:
    - JWT (3 parts)  -> USER auth ONLY (Keycloak)
    - Opaque token   -> SERVICE auth ONLY (introspection)
    """

    def intercept_service(
        self,
        continuation: Callable,
        handler_call_details: grpc.HandlerCallDetails,
    ) -> Optional[grpc.RpcMethodHandler]:

        method = handler_call_details.method

        # ✅ Allow reflection without auth (Postman / grpcurl)
        if method and ("grpc.reflection" in method or "/grpc.reflection" in method):
            return continuation(handler_call_details)

        metadata = dict(handler_call_details.invocation_metadata or [])
        auth_header = metadata.get("authorization")

        logger.debug(
            "Auth interceptor called",
            extra={"method": method, "auth_present": bool(auth_header)},
        )

        if not auth_header or not auth_header.startswith("Bearer "):
            return self._unauthenticated(
                grpc.StatusCode.UNAUTHENTICATED,
                "Missing or invalid Authorization header. Expected: Bearer <token>",
            )

        token = auth_header.replace("Bearer ", "").strip()
        is_jwt = token.count(".") == 2

        logger.debug(
            "Token received",
            extra={"is_jwt": is_jwt, "token_preview": token[:20]},
        )

        handler = continuation(handler_call_details)
        if handler is None:
            return None

        # USER JWT FLOW
        if is_jwt:
            try:
                # idp.get_current_user validates the JWT and returns OIDCUser
                # with organization and enabled_modules already extracted
                user = idp.get_current_user(token)

                logger.info(
                    "✅ gRPC authenticated as USER",
                    extra={"user_id": user.sub, "username": user.preferred_username},
                )

                return self._wrap_handler_with_user(
                    handler=handler,
                    user=user,
                )

            except Exception as exc:
                logger.warning(
                    "❌ User JWT authentication failed",
                    extra={"reason": str(exc)},
                )
                return self._unauthenticated(
                    grpc.StatusCode.UNAUTHENTICATED,
                    "Invalid or expired user access token",
                )

        # SERVICE TOKEN FLOW
        try:
            client_info = run_async(introspect_token(token))

            if not client_info or not client_info.get("active"):
                raise ClientAuthError("Inactive token")

            logger.info(
                "✅ gRPC authenticated as SERVICE",
                extra={"client_id": client_info.get("client_id")},
            )

            return self._wrap_handler_with_client(
                handler=handler,
                client_info=client_info,
            )

        except Exception as exc:
            logger.warning(
                "❌ Service token authentication failed",
                extra={"reason": str(exc)},
            )
            return self._unauthenticated(
                grpc.StatusCode.UNAUTHENTICATED,
                "Invalid service authentication token",
            )

    # Handler wrappers
    def _wrap_handler_with_user(
        self,
        handler: grpc.RpcMethodHandler,
        user: OIDCUser,
    ) -> grpc.RpcMethodHandler:
        """Wrap handler with validated user context.

        Args:
            handler: The gRPC handler to wrap
            user: Validated OIDCUser from fastapi-keycloak (guarantees token was verified)
        """
        if not handler:
            return None

        def attach_user_context(original_handler):
            def wrapper(request: message.Message, context: grpc.ServicerContext):
                # Build user context from validated OIDCUser
                context.user = {
                    "type": "user",
                    "id": user.sub,
                    "username": user.preferred_username,
                    "email": getattr(user, "email", ""),
                    "organization": user.organization,
                    "enabled_modules": user.enabled_modules or [],
                }

                # Extract organization ID and name from validated user
                if user.organization:
                    try:
                        from app.core.organization import extract_organization_from_validated_user

                        org_info = extract_organization_from_validated_user(user)
                        if org_info:
                            org_id, org_name = org_info
                            context.user["organization_id"] = org_id
                            context.user["organization_name"] = org_name
                    except Exception:
                        logger.debug("Organization extraction failed")

                return original_handler(request, context)

            return wrapper

        if handler.unary_unary:
            return grpc.unary_unary_rpc_method_handler(
                attach_user_context(handler.unary_unary),
                request_deserializer=handler.request_deserializer,
                response_serializer=handler.response_serializer,
            )

        return handler

    def _wrap_handler_with_client(
        self,
        handler: grpc.RpcMethodHandler,
        client_info: dict,
    ) -> grpc.RpcMethodHandler:
        if not handler:
            return None

        def attach_client_context(original_handler):
            def wrapper(request: message.Message, context: grpc.ServicerContext):
                context.service_client = {
                    "type": "client",
                    "client_id": client_info.get("client_id"),
                    "scope": client_info.get("scope", "").split(),
                    "active": client_info.get("active", False),
                }
                return original_handler(request, context)

            return wrapper

        if handler.unary_unary:
            return grpc.unary_unary_rpc_method_handler(
                attach_client_context(handler.unary_unary),
                request_deserializer=handler.request_deserializer,
                response_serializer=handler.response_serializer,
            )

        return handler

    # Error handler
    def _unauthenticated(
        self, status: grpc.StatusCode, mess: str
    ) -> grpc.RpcMethodHandler:
        """Return a gRPC handler that immediately aborts with UNAUTHENTICATED."""
        def terminate(request: message.Message, context: grpc.ServicerContext):
            context.abort(status, mess)

        return grpc.unary_unary_rpc_method_handler(terminate)


def create_grpc_auth_interceptor() -> GrpcAuthInterceptor:
    return GrpcAuthInterceptor()


# No-auth interceptor (dev / tests)
class NoAuthInterceptor(grpc.ServerInterceptor):
    def intercept_service(
        self,
        continuation: Callable,
        handler_call_details: grpc.HandlerCallDetails,
    ) -> Optional[grpc.RpcMethodHandler]:
        logger.info("NoAuthInterceptor: authentication disabled")
        return continuation(handler_call_details)
