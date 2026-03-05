"""
Advanced input validation and sanitization utilities
"""

import html
import re
from typing import Any, Optional
from urllib.parse import urlparse

from fastapi import HTTPException, status


class ValidationError(HTTPException):
    """Custom exception for validation errors"""
    def __init__(self, detail: str):
        super().__init__(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=detail
        )


class InputValidator:
    """Advanced input validation and sanitization"""
    
    # Security patterns to detect and block
    DANGEROUS_PATTERNS = [
        # SQL Injection patterns
        r"(union\s+select|select\s+.*\s+from|insert\s+into|update\s+.*\s+set|delete\s+from)",
        r"(drop\s+table|create\s+table|alter\s+table|truncate\s+table)",
        r"(exec\s*\(|execute\s*\(|sp_|xp_)",
        r"(\'\s*;\s*--|--\s*|\/\*.*\*\/)",
        
        # XSS patterns
        r"(<script[^>]*>.*?</script>|javascript:|vbscript:|onload\s*=|onerror\s*=)",
        r"(<iframe|<object|<embed|<applet|<form)",
        
        # Path traversal patterns
        r"(\.\./|\.\.\\|%2e%2e%2f|%2e%2e%5c)",
        
        # Command injection patterns
        r"(;\s*rm\s+|;\s*cat\s+|;\s*ls\s+|;\s*wget\s+|;\s*curl\s+)",
        r"(\|\s*rm\s+|\|\s*cat\s+|\|\s*ls\s+|\|\s*wget\s+|\|\s*curl\s+)",
    ]
    
    @staticmethod
    def sanitize_string(value: str, max_length: int = 255, allow_html: bool = False) -> str:
        """
        Sanitize string input to prevent various injection attacks
        
        Args:
            value: Input string to sanitize
            max_length: Maximum allowed length
            allow_html: Whether to allow HTML tags
            
        Returns:
            Sanitized string
            
        Raises:
            ValidationError: If input is invalid or dangerous
        """
        if not isinstance(value, str):
            raise ValidationError("Input must be a string")
        
        if not value.strip():
            raise ValidationError("Input cannot be empty or only whitespace")
        
        # Remove leading/trailing whitespace
        sanitized = value.strip()
        
        # Check length
        if len(sanitized) > max_length:
            raise ValidationError(f"Input too long (max {max_length} characters)")
        
        if len(sanitized) < 1:
            raise ValidationError("Input too short (minimum 1 character)")
        
        # Check for dangerous patterns
        value_lower = sanitized.lower()
        for pattern in InputValidator.DANGEROUS_PATTERNS:
            if re.search(pattern, value_lower, re.IGNORECASE):
                raise ValidationError("Input contains potentially dangerous content")
        
        # HTML encode if HTML is not allowed
        if not allow_html:
            sanitized = html.escape(sanitized)
        
        # Additional character restrictions
        # Allow alphanumeric, spaces, and common business characters
        allowed_chars = re.compile(r'^[a-zA-Z0-9\s\-_.&()]+$')
        
        # Check against the original value before HTML escaping
        original_value = value.strip()
        if not allowed_chars.match(original_value):
            raise ValidationError("Input contains invalid characters")
        
        return sanitized
    
    @staticmethod
    def validate_company_name(name: str) -> str:
        """
        Validate and sanitize company name
        
        Args:
            name: Company name to validate
            
        Returns:
            Sanitized company name
            
        Raises:
            ValidationError: If name is invalid
        """
        if not name:
            raise ValidationError("Company name is required")
        
        # Sanitize with specific rules for company names
        sanitized = InputValidator.sanitize_string(name, max_length=100, allow_html=False)
        
        # Company name specific validations
        if len(sanitized) < 2:
            raise ValidationError("Company name must be at least 2 characters long")
        
        # Check for reasonable company name patterns
        if sanitized.isdigit():
            raise ValidationError("Company name cannot be only numbers")
        
        # Check for repeated characters (potential spam)
        if re.search(r'(.)\1{5,}', sanitized):
            raise ValidationError("Company name contains too many repeated characters")
        
        return sanitized
    
    @staticmethod
    def validate_website_url(url: str) -> str:
        """
        Validate and sanitize website URL
        
        Args:
            url: Website URL to validate
            
        Returns:
            Sanitized and normalized URL
            
        Raises:
            ValidationError: If URL is invalid
        """
        if not url:
            raise ValidationError("Website URL is required")
        
        # Basic sanitization
        sanitized = url.strip()
        
        if len(sanitized) > 255:
            raise ValidationError("Website URL too long (max 255 characters)")
        
        # Add protocol if missing
        if not sanitized.startswith(('http://', 'https://')):
            sanitized = f"https://{sanitized}"
        
        # Parse and validate URL
        try:
            parsed = urlparse(sanitized)
        except Exception:
            raise ValidationError("Invalid URL format")
        
        # Validate URL components
        if not parsed.netloc:
            raise ValidationError("URL must have a valid domain")
        
        if parsed.scheme not in ['http', 'https']:
            raise ValidationError("URL must use HTTP or HTTPS protocol")
        
        # Check for suspicious patterns in URL
        url_lower = sanitized.lower()
        suspicious_patterns = [
            'localhost', '127.0.0.1', '0.0.0.0', '10.', '192.168.', '172.',
            'javascript:', 'data:', 'file:', 'ftp:'
        ]
        
        for pattern in suspicious_patterns:
            if pattern in url_lower:
                raise ValidationError(f"URL contains suspicious content: {pattern}")
        
        # Validate domain format
        domain_pattern = re.compile(
            r'^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'
        )
        
        if not domain_pattern.match(parsed.netloc.split(':')[0]):
            raise ValidationError("Invalid domain format")
        
        return sanitized
    
    @staticmethod
    def validate_json_field(data: Any, field_name: str, max_depth: int = 3, max_size: int = 10000) -> Any:
        """
        Validate JSON field data to prevent JSON-based attacks
        
        Args:
            data: JSON data to validate
            field_name: Name of the field (for error messages)
            max_depth: Maximum nesting depth allowed
            max_size: Maximum serialized size in characters
            
        Returns:
            Validated data
            
        Raises:
            ValidationError: If data is invalid
        """
        if data is None:
            return data
        
        # Convert to string to check size
        import json
        try:
            json_str = json.dumps(data)
        except (TypeError, ValueError):
            raise ValidationError(f"Invalid JSON data in field: {field_name}")
        
        if len(json_str) > max_size:
            raise ValidationError(f"Field {field_name} data too large (max {max_size} characters)")
        
        # Check nesting depth
        def check_depth(obj, current_depth=0):
            if current_depth > max_depth:
                raise ValidationError(f"Field {field_name} has too many nested levels (max {max_depth})")
            
            if isinstance(obj, dict):
                for value in obj.values():
                    check_depth(value, current_depth + 1)
            elif isinstance(obj, list):
                for item in obj:
                    check_depth(item, current_depth + 1)
        
        check_depth(data)
        
        # Check for dangerous content in JSON strings
        def sanitize_json_strings(obj):
            if isinstance(obj, str):
                # Check for dangerous patterns in JSON string values
                for pattern in InputValidator.DANGEROUS_PATTERNS:
                    if re.search(pattern, obj.lower(), re.IGNORECASE):
                        raise ValidationError(f"Field {field_name} contains potentially dangerous content")
                return html.escape(obj)
            elif isinstance(obj, dict):
                return {k: sanitize_json_strings(v) for k, v in obj.items()}
            elif isinstance(obj, list):
                return [sanitize_json_strings(item) for item in obj]
            else:
                return obj
        
        return sanitize_json_strings(data)


class RequestValidator:
    """Request-level validation utilities"""
    
    @staticmethod
    def validate_request_size(content_length: Optional[int], max_size: int = 1048576) -> None:  # 1MB default
        """
        Validate request size to prevent large payload attacks
        
        Args:
            content_length: Content length from request headers
            max_size: Maximum allowed size in bytes
            
        Raises:
            ValidationError: If request is too large
        """
        if content_length and content_length > max_size:
            raise ValidationError(f"Request too large (max {max_size} bytes)")
    
    @staticmethod
    def validate_content_type(content_type: Optional[str], allowed_types: list = None) -> None:
        """
        Validate request content type
        
        Args:
            content_type: Content type from request headers
            allowed_types: List of allowed content types
            
        Raises:
            ValidationError: If content type is not allowed
        """
        if allowed_types is None:
            allowed_types = ['application/json', 'application/x-www-form-urlencoded']
        
        if content_type:
            # Extract base content type (ignore charset, etc.)
            base_type = content_type.split(';')[0].strip().lower()
            if base_type not in allowed_types:
                raise ValidationError(f"Content type '{base_type}' not allowed")