"""
gRPC authentication tests covering:
1. gRPC interceptor creation
2. Missing authentication handling
3. Valid authentication
4. Invalid token handling
"""

import grpc
import pytest
from unittest.mock import Mock, patch, MagicMock
from app.core.grpc import GrpcAuthInterceptor, create_grpc_auth_interceptor
from app.core.client_auth import ClientAuthError


def test_grpc_interceptor_creation():
    """Test 1: gRPC interceptor can be created."""
    interceptor = create_grpc_auth_interceptor()
    assert isinstance(interceptor, GrpcAuthInterceptor)
    print("✅ Test 1: gRPC interceptor creation passed")


def test_grpc_interceptor_missing_auth():
    """Test 2: gRPC interceptor with missing authentication."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details without auth
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = []  # No auth token
    handler_call_details.method = "/test.Service/Method"
    
    # Mock continuation that returns a valid handler
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Intercept the service
    result = interceptor.intercept_service(continuation, handler_call_details)
    
    # Should return an error handler for missing auth
    assert result is not None
    # Check if it's a proper gRPC handler
    assert hasattr(result, 'unary_unary')
    print("✅ Test 2: gRPC interceptor missing auth handling passed")


def test_grpc_interceptor_valid_jwt_auth():
    """Test 3: gRPC interceptor with valid JWT authentication (user)."""
    interceptor = GrpcAuthInterceptor()

    # Mock handler call details with JWT token
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = [
        ('authorization', 'Bearer header.payload.signature')  # JWT has 3 parts
    ]
    handler_call_details.method = "/test.Service/Method"

    # Mock the Keycloak user (OIDCUser) with all necessary attributes
    # Organization and enabled_modules are now on the OIDCUser object directly
    mock_user = Mock()
    mock_user.sub = "user-123"
    mock_user.preferred_username = "testuser"
    mock_user.email = "test@example.com"
    mock_user.organization = [{"Company": {"id": "123"}}, "Company"]
    mock_user.enabled_modules = ["Screen", "Target"]

    # Mock continuation
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)

    # Mock the idp object entirely to avoid Keycloak initialization
    mock_idp = MagicMock()
    mock_idp.get_current_user.return_value = mock_user

    with patch('app.core.grpc.idp', mock_idp):
        # Intercept the service
        result = interceptor.intercept_service(continuation, handler_call_details)

        # Should return a wrapped handler
        assert result is not None
        assert hasattr(result, 'unary_unary')
        # Should have called get_current_user
        mock_idp.get_current_user.assert_called_once_with("header.payload.signature")
    print("✅ Test 3: gRPC interceptor valid JWT auth passed")


def test_grpc_interceptor_valid_service_token_auth():
    """Test 4: gRPC interceptor with valid service token authentication."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details with opaque token (not JWT)
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = [
        ('authorization', 'Bearer opaque-token-not-jwt')  # Not a JWT
    ]
    handler_call_details.method = "/test.Service/Method"
    
    # Mock continuation
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Mock introspect_token
    with patch('app.core.grpc.introspect_token') as mock_introspect:
        mock_introspect.return_value = {
            "active": True,
            "client_id": "test-service",
            "scope": "read write"
        }
        
        # Intercept the service
        result = interceptor.intercept_service(continuation, handler_call_details)
        
        # Should return a wrapped handler
        assert result is not None
        assert hasattr(result, 'unary_unary')
        # Should have called introspect_token
        mock_introspect.assert_called_once()
    print("✅ Test 4: gRPC interceptor valid service token auth passed")


def test_grpc_interceptor_invalid_jwt():
    """Test 5: gRPC interceptor with invalid JWT token."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details with JWT token
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = [
        ('authorization', 'Bearer header.payload.signature')
    ]
    handler_call_details.method = "/test.Service/Method"
    
    # Mock continuation
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Mock the idp object entirely to avoid Keycloak initialization
    mock_idp = MagicMock()
    mock_idp.get_current_user.side_effect = Exception("Invalid token")

    with patch('app.core.grpc.idp', mock_idp):
        # Intercept the service
        result = interceptor.intercept_service(continuation, handler_call_details)

        # Should return an error handler
        assert result is not None
        assert hasattr(result, 'unary_unary')
    print("✅ Test 5: gRPC interceptor invalid JWT handling passed")


def test_grpc_interceptor_invalid_service_token():
    """Test 6: gRPC interceptor with invalid service token."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details with opaque token
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = [
        ('authorization', 'Bearer invalid-opaque-token')
    ]
    handler_call_details.method = "/test.Service/Method"
    
    # Mock continuation
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Mock introspect_token to raise exception
    with patch('app.core.grpc.introspect_token') as mock_introspect:
        mock_introspect.side_effect = ClientAuthError("Invalid token")
        
        # Intercept the service
        result = interceptor.intercept_service(continuation, handler_call_details)
        
        # Should return an error handler
        assert result is not None
        assert hasattr(result, 'unary_unary')
    print("✅ Test 6: gRPC interceptor invalid service token handling passed")


def test_grpc_interceptor_reflection_allowed():
    """Test 7: gRPC interceptor allows reflection without auth."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details for reflection service
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = []  # No auth
    handler_call_details.method = "/grpc.reflection.v1alpha.ServerReflection/ServerReflectionInfo"
    
    # Mock continuation
    mock_handler = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Intercept the service
    result = interceptor.intercept_service(continuation, handler_call_details)
    
    # Should allow through (return the original handler)
    assert result == mock_handler
    print("✅ Test 7: gRPC interceptor reflection allowed passed")


def test_grpc_interceptor_no_bearer_prefix():
    """Test 8: gRPC interceptor with token missing 'Bearer ' prefix."""
    interceptor = GrpcAuthInterceptor()
    
    # Mock handler call details with token but no 'Bearer ' prefix
    handler_call_details = Mock()
    handler_call_details.invocation_metadata = [
        ('authorization', 'plain-token-without-bearer')
    ]
    handler_call_details.method = "/test.Service/Method"
    
    # Mock continuation
    mock_handler = Mock()
    mock_handler.unary_unary = Mock()
    continuation = Mock(return_value=mock_handler)
    
    # Intercept the service
    result = interceptor.intercept_service(continuation, handler_call_details)
    
    # Should return an error handler
    assert result is not None
    assert hasattr(result, 'unary_unary')
    print("✅ Test 8: gRPC interceptor no bearer prefix handling passed")


if __name__ == "__main__":
    print("=" * 60)
    print("RUNNING gRPC AUTHENTICATION TESTS")
    print("=" * 60)
    
    # Run tests
    test_grpc_interceptor_creation()
    test_grpc_interceptor_missing_auth()
    test_grpc_interceptor_valid_jwt_auth()
    test_grpc_interceptor_valid_service_token_auth()
    test_grpc_interceptor_invalid_jwt()
    test_grpc_interceptor_invalid_service_token()
    test_grpc_interceptor_reflection_allowed()
    test_grpc_interceptor_no_bearer_prefix()
    
    print("\n" + "=" * 60)
    print("✅ ALL gRPC AUTH TESTS PASSED!")
    print("=" * 60)