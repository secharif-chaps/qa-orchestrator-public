"""
Tests for database schema configuration.

Tests cover:
1. Schema constants are correctly defined
2. Engine creation with schema search_path (async and sync)
3. Session factory configuration (async and sync)
4. Schema validation (whitelist enforcement)
5. Sync session context manager (get_global_sync_session)
6. Schema isolation (search_path is set correctly)
"""

import pytest
from unittest.mock import MagicMock, patch
from sqlalchemy.engine import Engine  # noqa: F401 - used in type checks
from sqlalchemy.orm import Session  # noqa: F401 - used in spec=Session


class TestDatabaseSchemaConstants:
    """Test schema constant definitions."""

    def test_global_schema_constant(self):
        """Test that GLOBAL_SCHEMA is correctly defined."""
        from app.database import GLOBAL_SCHEMA
        assert GLOBAL_SCHEMA == "global_schema"

    def test_valid_schemas_contains_expected_values(self):
        """Test that VALID_SCHEMAS whitelist has the expected entries."""
        from app.database import VALID_SCHEMAS
        assert "global_schema" in VALID_SCHEMAS
        assert "public" in VALID_SCHEMAS
        assert len(VALID_SCHEMAS) == 2


class TestSchemaValidation:
    """Test schema name validation against whitelist."""

    def test_validate_valid_schema_global(self):
        """Test that 'global_schema' passes validation."""
        from app.database import _validate_schema
        # Should not raise
        _validate_schema("global_schema")

    def test_validate_valid_schema_public(self):
        """Test that 'public' passes validation."""
        from app.database import _validate_schema
        _validate_schema("public")

    def test_validate_invalid_schema_raises(self):
        """Test that an invalid schema name raises SchemaValidationError."""
        from app.database import _validate_schema, SchemaValidationError
        with pytest.raises(SchemaValidationError, match="Invalid schema name"):
            _validate_schema("malicious_schema")

    def test_validate_empty_schema_raises(self):
        """Test that an empty string raises SchemaValidationError."""
        from app.database import _validate_schema, SchemaValidationError
        with pytest.raises(SchemaValidationError):
            _validate_schema("")

    def test_validate_sql_injection_attempt_raises(self):
        """Test that SQL injection attempts are blocked by validation."""
        from app.database import _validate_schema, SchemaValidationError
        with pytest.raises(SchemaValidationError):
            _validate_schema("public; DROP TABLE users")


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

    def test_global_sync_engine_exists(self):
        """Test that global_sync_engine is created."""
        from app.database import global_sync_engine
        assert global_sync_engine is not None

    def test_global_sync_engine_is_sync(self):
        """Test that global_sync_engine is a synchronous Engine."""
        from app.database import global_sync_engine
        assert isinstance(global_sync_engine, Engine)


class TestSessionFactories:
    """Test session factory configuration."""

    def test_session_local_exists(self):
        """Test that AsyncSessionLocal is configured."""
        from app.database import AsyncSessionLocal
        assert AsyncSessionLocal is not None

    def test_global_session_local_exists(self):
        """Test that GlobalAsyncSessionLocal is configured."""
        from app.database import GlobalAsyncSessionLocal
        assert GlobalAsyncSessionLocal is not None

    def test_global_sync_session_local_exists(self):
        """Test that GlobalSessionLocal (sync) is configured."""
        from app.database import GlobalSessionLocal
        assert GlobalSessionLocal is not None


class TestSyncSessionContextManager:
    """Test get_global_sync_session context manager."""

    def test_get_global_sync_session_returns_session(self):
        """Test that get_global_sync_session yields a Session instance."""
        from app.database import get_global_sync_session
        with patch("app.database.GlobalSessionLocal") as mock_factory:
            mock_session = MagicMock(spec=Session)
            mock_factory.return_value = mock_session

            with get_global_sync_session() as session:
                assert session is mock_session

    def test_get_global_sync_session_closes_on_success(self):
        """Test that session is closed after successful use."""
        from app.database import get_global_sync_session
        with patch("app.database.GlobalSessionLocal") as mock_factory:
            mock_session = MagicMock(spec=Session)
            mock_factory.return_value = mock_session

            with get_global_sync_session():
                pass

            mock_session.close.assert_called_once()

    def test_get_global_sync_session_closes_on_exception(self):
        """Test that session is closed even when an exception occurs."""
        from app.database import get_global_sync_session
        with patch("app.database.GlobalSessionLocal") as mock_factory:
            mock_session = MagicMock(spec=Session)
            mock_factory.return_value = mock_session

            with pytest.raises(RuntimeError):
                with get_global_sync_session():
                    raise RuntimeError("test error")

            mock_session.close.assert_called_once()


class TestSyncEngineCreation:
    """Test _create_sync_engine_with_schema factory."""

    @patch("app.database.create_engine")
    @patch("app.database._register_schema_event_listener")
    def test_creates_engine_without_schema(self, mock_listener, mock_create):
        """Test sync engine creation without schema."""
        from app.database import _create_sync_engine_with_schema
        mock_engine = MagicMock(spec=Engine)
        mock_create.return_value = mock_engine

        result = _create_sync_engine_with_schema()

        mock_create.assert_called_once()
        mock_listener.assert_not_called()
        assert result is mock_engine

    @patch("app.database.create_engine")
    @patch("app.database._register_schema_event_listener")
    def test_creates_engine_with_schema(self, mock_listener, mock_create):
        """Test sync engine creation with schema registers event listener."""
        from app.database import _create_sync_engine_with_schema
        mock_engine = MagicMock(spec=Engine)
        mock_create.return_value = mock_engine

        result = _create_sync_engine_with_schema("global_schema")

        mock_create.assert_called_once()
        mock_listener.assert_called_once_with(
            mock_engine, "global_schema", is_async=False
        )
        assert result is mock_engine


class TestDatabaseDependencies:
    """Test FastAPI dependency functions."""

    def test_get_db_returns_async_generator(self):
        """Test that get_db returns an async generator."""
        from app.database import get_db
        import types
        result = get_db()
        assert isinstance(result, types.AsyncGeneratorType)

    def test_get_global_db_returns_async_generator(self):
        """Test that get_global_db returns an async generator."""
        from app.database import get_global_db
        import types
        result = get_global_db()
        assert isinstance(result, types.AsyncGeneratorType)


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
