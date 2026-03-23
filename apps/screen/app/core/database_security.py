"""
Database security utilities and enhanced query protection
"""

import logging
import time
from contextlib import contextmanager
from typing import Any

from sqlalchemy import event
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session

from app.core.validators import ValidationError

logger = logging.getLogger(__name__)


class DatabaseSecurityManager:
    """Enhanced database security and monitoring"""

    def __init__(self):
        self.query_log = []
        self.slow_query_threshold = 5.0  # seconds
        self.max_query_log_size = 1000

    def setup_query_monitoring(self, engine: Engine):
        """Set up database query monitoring and logging"""

        @event.listens_for(engine, "before_cursor_execute")
        def receive_before_cursor_execute(conn, cursor, statement, parameters, context, executemany):
            """Log queries before execution for security monitoring"""
            context._query_start_time = time.time()

            # Log potentially dangerous queries
            statement_lower = statement.lower().strip()
            dangerous_patterns = [
                "drop ",
                "truncate ",
                "delete from",
                "alter table",
                "create table",
                "insert into",
                "update ",
            ]

            for pattern in dangerous_patterns:
                if statement_lower.startswith(pattern):
                    logger.warning(f"Potentially dangerous query executed: {statement[:100]}...")
                    break

        @event.listens_for(engine, "after_cursor_execute")
        def receive_after_cursor_execute(conn, cursor, statement, parameters, context, executemany):
            """Log query completion and performance"""
            total_time = time.time() - context._query_start_time

            # Log slow queries
            if total_time > self.slow_query_threshold:
                logger.warning(f"Slow query detected ({total_time:.2f}s): {statement[:100]}...")

            # Store query info for analysis
            query_info = {
                "statement": statement[:200],  # Truncate long statements
                "parameters": str(parameters)[:100] if parameters else None,
                "execution_time": total_time,
                "timestamp": time.time(),
            }

            self.query_log.append(query_info)

            # Limit log size
            if len(self.query_log) > self.max_query_log_size:
                self.query_log = self.query_log[-self.max_query_log_size // 2 :]

    def validate_query_parameters(self, parameters: dict[str, Any]) -> dict[str, Any]:
        """Validate and sanitize query parameters"""
        if not parameters:
            return {}

        sanitized = {}
        for key, value in parameters.items():
            # Validate parameter names
            if not isinstance(key, str) or not key.replace("_", "").isalnum():
                raise ValidationError(f"Invalid parameter name: {key}")

            # Validate parameter values
            if isinstance(value, str):
                if len(value) > 10000:  # Prevent extremely large strings
                    raise ValidationError(f"Parameter value too long: {key}")

                # Check for SQL injection patterns in parameter values
                dangerous_sql_patterns = [
                    "';",
                    '"',
                    "--",
                    "/*",
                    "*/",
                    "xp_",
                    "sp_",
                    "union select",
                    "drop table",
                    "delete from",
                ]

                value_lower = value.lower()
                for pattern in dangerous_sql_patterns:
                    if pattern in value_lower:
                        logger.warning(f"Suspicious SQL pattern in parameter {key}: {pattern}")
                        # Don't raise error, just log - might be legitimate content

            sanitized[key] = value

        return sanitized

    def get_query_stats(self) -> dict[str, Any]:
        """Get query execution statistics"""
        if not self.query_log:
            return {"total_queries": 0, "average_time": 0, "slow_queries": 0}

        total_queries = len(self.query_log)
        total_time = sum(q["execution_time"] for q in self.query_log)
        average_time = total_time / total_queries
        slow_queries = sum(1 for q in self.query_log if q["execution_time"] > self.slow_query_threshold)

        return {
            "total_queries": total_queries,
            "average_time": round(average_time, 3),
            "slow_queries": slow_queries,
            "slow_query_percentage": round((slow_queries / total_queries) * 100, 2),
        }


class SecureQueryBuilder:
    """Secure query builder with additional validation"""

    def __init__(self, session: Session):
        self.session = session
        self.security_manager = DatabaseSecurityManager()

    @contextmanager
    def secure_query_context(self):
        """Context manager for secure database operations"""
        start_time = time.time()
        try:
            yield
        except Exception as e:
            logger.error(f"Database operation failed: {str(e)}")
            self.session.rollback()
            raise
        finally:
            execution_time = time.time() - start_time
            if execution_time > 10.0:  # Log very long operations
                logger.warning(f"Long database operation detected: {execution_time:.2f}s")

    def safe_filter_by_id(self, model_class, entity_id: int):
        """Safely filter by ID with validation"""
        if not isinstance(entity_id, int) or entity_id <= 0:
            raise ValidationError("Invalid ID provided")

        if entity_id > 2147483647:  # Max 32-bit integer
            raise ValidationError("ID too large")

        with self.secure_query_context():
            return self.session.query(model_class).filter(model_class.id == entity_id)

    def safe_filter_by_string(self, model_class, field, value: str, exact_match: bool = True):
        """Safely filter by string field with validation"""
        if not isinstance(value, str):
            raise ValidationError("String value required")

        if len(value) > 255:
            raise ValidationError("Search value too long")

        # Basic sanitization
        sanitized_value = value.strip()
        if not sanitized_value:
            raise ValidationError("Empty search value")

        with self.secure_query_context():
            if exact_match:
                return self.session.query(model_class).filter(field == sanitized_value)
            else:
                # For LIKE queries, escape special characters
                escaped_value = sanitized_value.replace("%", "\\%").replace("_", "\\_")
                return self.session.query(model_class).filter(field.like(f"%{escaped_value}%"))

    def safe_filter_by_owner(self, model_class, owner_field, username: str):
        """Safely filter by owner with validation"""
        if not isinstance(username, str):
            raise ValidationError("Username must be a string")

        if len(username) > 100:
            raise ValidationError("Username too long")

        # Validate username format (alphanumeric, underscore, hyphen)
        import re

        if not re.match(r"^[a-zA-Z0-9_-]+$", username):
            raise ValidationError("Invalid username format")

        with self.secure_query_context():
            return self.session.query(model_class).filter(owner_field == username)

    def safe_create_entity(self, model_class, **kwargs):
        """Safely create entity with validation"""
        # Validate all input parameters
        validated_kwargs = self.security_manager.validate_query_parameters(kwargs)

        with self.secure_query_context():
            entity = model_class(**validated_kwargs)
            self.session.add(entity)
            self.session.commit()
            self.session.refresh(entity)
            return entity

    def safe_update_entity(self, entity, **kwargs):
        """Safely update entity with validation"""
        # Validate all input parameters
        validated_kwargs = self.security_manager.validate_query_parameters(kwargs)

        with self.secure_query_context():
            for key, value in validated_kwargs.items():
                if hasattr(entity, key):
                    setattr(entity, key, value)
                else:
                    logger.warning(f"Attempted to set non-existent attribute: {key}")

            self.session.commit()
            self.session.refresh(entity)
            return entity

    def safe_delete_entity(self, entity):
        """Safely delete entity"""
        with self.secure_query_context():
            self.session.delete(entity)
            self.session.commit()
            return True


# Global security manager instance
db_security_manager = DatabaseSecurityManager()


def setup_database_security(engine: Engine):
    """Initialize database security monitoring"""
    db_security_manager.setup_query_monitoring(engine)
    logger.info("Database security monitoring initialized")


def get_database_stats() -> dict[str, Any]:
    """Get current database security statistics"""
    return db_security_manager.get_query_stats()
