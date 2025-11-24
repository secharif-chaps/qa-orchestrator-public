"""
Security middleware for request validation and sanitization
"""

import time
import json
from typing import Callable
from fastapi import Request, Response, HTTPException, status
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.core.logging_config import get_logger
from app.core.validators import RequestValidator, ValidationError

logger = get_logger(__name__)


class SecurityMiddleware(BaseHTTPMiddleware):
    """Comprehensive security middleware for input validation and attack prevention"""
    
    def __init__(
        self,
        app,
        max_request_size: int = 1048576,  # 1MB
        allowed_content_types: list = None,
        rate_limit_requests: int = 100,
        rate_limit_window: int = 60,  # seconds
    ):
        super().__init__(app)
        self.max_request_size = max_request_size
        self.allowed_content_types = allowed_content_types or [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data'
        ]
        self.rate_limit_requests = rate_limit_requests
        self.rate_limit_window = rate_limit_window
        
        # Simple in-memory rate limiting (use Redis in production)
        self.rate_limit_store = {}
    
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Main middleware processing"""
        # Skip logging for OPTIONS preflight requests
        if request.method == "OPTIONS":
            return await call_next(request)

        try:
            await self._check_rate_limit(request)
            await self._validate_request_size(request)
            await self._validate_content_type(request)
            await self._validate_security_headers(request)
            
            response = await call_next(request)

            # Only log errors
            if response.status_code >= 400:
                logger.warning(
                    "Request returned error status",
                    extra={
                        "method": request.method,
                        "path": request.url.path,
                        "status_code": response.status_code
                    }
                )

            response = self._add_security_headers(response)
            return response

        except ValidationError as e:
            logger.warning(
                "Security validation failed",
                extra={
                    "method": request.method,
                    "path": request.url.path,
                    "error": e.detail
                }
            )
            return JSONResponse(
                status_code=e.status_code,
                content={"detail": e.detail, "type": "validation_error"}
            )
        except HTTPException as e:
            logger.warning(
                "Security check blocked request",
                extra={
                    "method": request.method,
                    "path": request.url.path,
                    "error": e.detail
                }
            )
            return JSONResponse(
                status_code=e.status_code,
                content={"detail": e.detail}
            )
        except Exception as e:
            logger.error(
                "Security middleware error",
                exc_info=True,
                extra={
                    "method": request.method,
                    "path": request.url.path,
                    "error_type": type(e).__name__
                }
            )
            return JSONResponse(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                content={"detail": "Internal server error"}
            )
    
    async def _check_rate_limit(self, request: Request) -> None:
        """Simple rate limiting implementation"""
        # Get client IP (consider using X-Forwarded-For in production with proper validation)
        client_ip = request.client.host if request.client else "unknown"
        current_time = time.time()
        
        # Clean old entries
        cutoff_time = current_time - self.rate_limit_window
        self.rate_limit_store = {
            ip: requests for ip, requests in self.rate_limit_store.items()
            if any(req_time > cutoff_time for req_time in requests)
        }
        
        # Check current IP
        if client_ip not in self.rate_limit_store:
            self.rate_limit_store[client_ip] = []
        
        # Filter recent requests
        recent_requests = [
            req_time for req_time in self.rate_limit_store[client_ip]
            if req_time > cutoff_time
        ]
        
        if len(recent_requests) >= self.rate_limit_requests:
            raise HTTPException(
                status_code=status.HTTP_429_TOO_MANY_REQUESTS,
                detail=f"Rate limit exceeded. Max {self.rate_limit_requests} requests per {self.rate_limit_window} seconds"
            )
        
        # Add current request
        recent_requests.append(current_time)
        self.rate_limit_store[client_ip] = recent_requests
    
    async def _validate_request_size(self, request: Request) -> None:
        """Validate request size to prevent large payload attacks"""
        content_length = request.headers.get("content-length")
        if content_length:
            try:
                size = int(content_length)
                RequestValidator.validate_request_size(size, self.max_request_size)
            except ValueError:
                raise ValidationError("Invalid content-length header")
    
    async def _validate_content_type(self, request: Request) -> None:
        """Validate request content type"""
        # Skip validation for GET requests
        if request.method in ["GET", "HEAD", "OPTIONS"]:
            return
        
        content_type = request.headers.get("content-type")
        if content_type:
            RequestValidator.validate_content_type(content_type, self.allowed_content_types)
    
    async def _validate_security_headers(self, request: Request) -> None:
        """Validate security-related headers"""
        # Check for suspicious User-Agent patterns
        user_agent = request.headers.get("user-agent", "").lower()
        suspicious_agents = [
            'sqlmap', 'nmap', 'nikto', 'burp', 'zap', 'w3af',
            'masscan', 'nessus', 'openvas'
        ]
        
        for agent in suspicious_agents:
            if agent in user_agent:
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Suspicious user agent detected"
                )
        
        # Check for header injection attempts
        for header_name, header_value in request.headers.items():
            if any(char in header_value for char in ['\r', '\n', '\0']):
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="Invalid characters in headers"
                )
    
    def _add_security_headers(self, response: Response) -> Response:
        """Add security headers to response"""
        # More permissive CSP for documentation pages (Swagger UI needs inline scripts/styles)
        # Strict CSP for API endpoints
        csp_policy = "default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data: cdn.jsdelivr.net"

        security_headers = {
            "X-Content-Type-Options": "nosniff",
            "X-Frame-Options": "DENY",
            "X-XSS-Protection": "1; mode=block",
            "Strict-Transport-Security": "max-age=31536000; includeSubDomains",
            "Content-Security-Policy": csp_policy,
            "Referrer-Policy": "strict-origin-when-cross-origin",
            "Permissions-Policy": "geolocation=(), camera=(), microphone=()"
        }

        for header, value in security_headers.items():
            response.headers[header] = value

        return response


class JSONValidationMiddleware(BaseHTTPMiddleware):
    """Middleware for validating JSON payloads"""
    
    async def dispatch(self, request: Request, call_next: Callable) -> Response:
        """Validate JSON payload structure and content"""
        # Skip OPTIONS preflight requests
        if request.method == "OPTIONS":
            return await call_next(request)
        
        # Only process JSON requests
        if (request.method not in ["GET", "HEAD", "OPTIONS"] and 
            request.headers.get("content-type", "").startswith("application/json")):
            
            try:
                body = await request.body()
                
                if body:
                    try:
                        data = json.loads(body)
                        await self._validate_json_structure(data)
                        
                        # Restore the request body for FastAPI
                        async def receive():
                            return {"type": "http.request", "body": body}
                        request._receive = receive
                        
                    except json.JSONDecodeError:
                        logger.warning(
                            "Invalid JSON in request",
                            extra={
                                "method": request.method,
                                "path": request.url.path
                            }
                        )
                        return JSONResponse(
                            status_code=status.HTTP_400_BAD_REQUEST,
                            content={"detail": "Invalid JSON format"}
                        )
                    except ValidationError as e:
                        logger.warning(
                            "JSON validation failed",
                            extra={
                                "method": request.method,
                                "path": request.url.path,
                                "error": e.detail
                            }
                        )
                        return JSONResponse(
                            status_code=e.status_code,
                            content={"detail": e.detail}
                        )
            except Exception:
                logger.error(
                    "JSON processing error",
                    exc_info=True,
                    extra={
                        "method": request.method,
                        "path": request.url.path
                    }
                )
                return JSONResponse(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    content={"detail": "Request processing error"}
                )
        
        return await call_next(request)
    
    async def _validate_json_structure(self, data: dict) -> None:
        """Validate JSON structure for security issues"""
        # Check for excessively nested structures
        max_depth = 10
        
        def check_depth(obj, current_depth=0):
            if current_depth > max_depth:
                raise ValidationError(f"JSON structure too deeply nested (max {max_depth} levels)")
            
            if isinstance(obj, dict):
                if len(obj) > 100:  # Prevent objects with too many keys
                    raise ValidationError("JSON object has too many keys (max 100)")
                
                for key, value in obj.items():
                    if len(str(key)) > 100:  # Prevent extremely long keys
                        raise ValidationError("JSON key too long (max 100 characters)")
                    check_depth(value, current_depth + 1)
            elif isinstance(obj, list):
                if len(obj) > 1000:  # Prevent extremely large arrays
                    raise ValidationError("JSON array too large (max 1000 items)")
                
                for item in obj:
                    check_depth(item, current_depth + 1)
        
        check_depth(data)