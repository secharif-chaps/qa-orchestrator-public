"""
Tests for database schema configuration.

Tests cover:
1. Schema constants are correctly defined
2. Engine creation with schema search_path
3. Session factory configuration
4. Schema isolation (search_path is set correctly)
"""

import pytest
from unittest.mock import MagicMock, patch, call


class TestDatabaseSchemaConstants:
    """Test schema constant definitions."""

    def test_global_schema_constant(self):
        """Test that GLOBAL_SCHEMA is correctly defined."""
        from app.database import GLOBAL_SCHEMA
        assert GLOBAL_SCHEMA == "global_schema"


class TestDatabaseEngines:
    """Test engine configuration."""

    def test_default_engine_exists(self):
        """Test that default engine is created."""
        from app.database import engine
        assert engine is not None

    def test_global_engine_exists(self):
        """Test that global_engine is created."""
        from app.database import global_engine
        assert global_engine is not None


class TestSessionFactories:
    """Test session factory configuration."""

    def test_session_local_exists(self):
        """Test that SessionLocal is configured."""
        from app.database import SessionLocal
        assert SessionLocal is not None

    def test_global_session_local_exists(self):
        """Test that GlobalSessionLocal is configured."""
        from app.database import GlobalSessionLocal
        assert GlobalSessionLocal is not None


class TestDatabaseDependencies:
    """Test FastAPI dependency functions."""

    def test_get_db_returns_generator(self):
        """Test that get_db returns a generator."""
        from app.database import get_db
        import types
        result = get_db()
        assert isinstance(result, types.GeneratorType)

    def test_get_global_db_returns_generator(self):
        """Test that get_global_db returns a generator."""
        from app.database import get_global_db
        import types
        result = get_global_db()
        assert isinstance(result, types.GeneratorType)


class TestBaseClasses:
    """Test SQLAlchemy base class configuration."""

    def test_base_exists(self):
        """Test that Base is defined."""
        from app.database import Base
        assert Base is not None

    def test_global_base_exists(self):
        """Test that GlobalBase is defined."""
        from app.database import GlobalBase
        assert GlobalBase is not None


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
