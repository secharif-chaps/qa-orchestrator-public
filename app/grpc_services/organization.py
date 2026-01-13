# app/grpc_services/organization.py
from app.grpc_generated import (
    organization_pb2,
    organization_pb2_grpc,
)
import grpc
import logging

logger = logging.getLogger(__name__)

class OrganizationService(
    organization_pb2_grpc.OrganizationServiceServicer
):
    def GetOrganizationContext(self, request, context):
        """
        Get organization context - requires authentication.
        
        Can access authentication info via:
        - context.user (for user JWT)
        - context.service_client (for client credentials)
        """
        try:
            # Check authentication
            auth_info = self._get_auth_info(context)
            logger.info(f"GetOrganizationContext called by: {auth_info}")
            
            # Validate input
            if not request.organization_id:
                context.abort(
                    grpc.StatusCode.INVALID_ARGUMENT,
                    "organization_id is required"
                )
                return None
            
            # use auth info for authorization
            # Example: Check if user has access to requested organization
            if hasattr(context, 'user') and context.user:
                user_org_id = context.user.get("organization_id")
                if user_org_id and user_org_id != request.organization_id:
                    context.abort(
                        grpc.StatusCode.PERMISSION_DENIED,
                        f"User not authorized for organization {request.organization_id}"
                    )
                    return None
            
            return organization_pb2.GetOrganizationContextResponse(
                organization_id=request.organization_id,
                name="Demo Org",
                enabled_modules=["Screen", "Target", "Explore"],
            )
            
        except Exception as e:
            logger.error(f"Error in GetOrganizationContext: {str(e)}")
            context.abort(
                grpc.StatusCode.INTERNAL,
                f"Internal error: {str(e)}"
            )
            return None
    
    def IsModuleEnabled(self, request, context):
        try:
            # Check authentication
            auth_info = self._get_auth_info(context)
            logger.info(f"IsModuleEnabled called by: {auth_info}")
            
            # Validate inputs
            if not request.organization_id or not request.module_name:
                context.abort(
                    grpc.StatusCode.INVALID_ARGUMENT,
                    "organization_id and module_name are required"
                )
                return None
            
            enabled = request.module_name in {"Screen", "Target", "Explore"}
            return organization_pb2.IsModuleEnabledResponse(enabled=enabled)
            
        except Exception as e:
            logger.error(f"Error in IsModuleEnabled: {str(e)}")
            context.abort(
                grpc.StatusCode.INTERNAL,
                f"Internal error: {str(e)}"
            )
            return None
    
    def _get_auth_info(self, context):
        """Extract authentication information from context."""
        if hasattr(context, 'user') and context.user:
            return f"user:{context.user.get('username', 'unknown')}"
        elif hasattr(context, 'service_client') and context.service_client:
            return f"client:{context.service_client.get('client_id', 'unknown')}"
        return "unauthenticated"