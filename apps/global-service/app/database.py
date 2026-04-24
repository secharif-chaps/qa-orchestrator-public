from collections.abc import AsyncGenerator, Generator
from contextlib import contextmanager
from typing import Literal, overload

from sqlalchemy import create_engine, event
from sqlalchemy.engine import Engine
from sqlalchemy.ext.asyncio import (
    AsyncEngine,
    AsyncSession,
    async_sessionmaker,
    create_async_engine,
)
from sqlalchemy.orm import Session, declarative_base, sessionmaker

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


# Custom Exceptions
class SchemaValidationError(ValueError):
    """Raised when schema name fails validation against whitelist."""

    pass


# Convert DATABASE_URL to async format (postgresql:// -> postgresql+asyncpg://)
SQLALCHEMY_DATABASE_URL = settings.DATABASE_URL
if SQLALCHEMY_DATABASE_URL.startswith("postgresql://"):
    SQLALCHEMY_DATABASE_URL = SQLALCHEMY_DATABASE_URL.replace("postgresql://", "postgresql+asyncpg://", 1)

# Schema names
GLOBAL_SCHEMA = "global_schema"

# Valid schema names (whitelist for SQL injection prevention)
VALID_SCHEMAS = {"global_schema", "public"}

# Engine pool configuration (shared by both async and sync engines)
ENGINE_POOL_CONFIG = {
    "pool_size": settings.DB_POOL_SIZE,
    "max_overflow": settings.DB_MAX_OVERFLOW,
    "pool_timeout": settings.DB_POOL_TIMEOUT,
    "pool_recycle": settings.DB_POOL_RECYCLE,
    "pool_pre_ping": True,
    "future": True,
}

# Session factory configuration (shared by all session makers)
SESSION_CONFIG = {
    "expire_on_commit": False,
    "autocommit": False,
    "autoflush": False,
}


def _validate_schema(schema: str) -> None:
    """Validate schema name against whitelist to prevent SQL injection.

    Args:
        schema: Schema name to validate

    Raises:
        SchemaValidationError: If schema is not in the whitelist
    """
    if schema not in VALID_SCHEMAS:
        logger.error(
            "Schema validation failed",
            extra={
                "schema": schema,
                "valid_schemas": sorted(VALID_SCHEMAS),
                "error": "schema_not_in_whitelist",
            },
        )
        raise SchemaValidationError(
            f"Invalid schema name: {schema}. Must be one of: {', '.join(sorted(VALID_SCHEMAS))}"
        )
    logger.debug("Schema validation passed", extra={"schema": schema})


@overload
def _register_schema_event_listener(engine: AsyncEngine, schema: str, *, is_async: Literal[True]) -> None: ...


@overload
def _register_schema_event_listener(engine: Engine, schema: str, *, is_async: Literal[False] = ...) -> None: ...


def _register_schema_event_listener(engine: Engine | AsyncEngine, schema: str, *, is_async: bool = False) -> None:
    """Register event listener to set search_path on connection.

    Validates schema immediately before use to prevent TOCTOU vulnerabilities.

    Args:
        engine: AsyncEngine when is_async=True, Engine when is_async=False
        schema: Schema name to set in search_path (validated here)
        is_async: True for AsyncEngine (uses sync_engine for event), False for Engine

    Raises:
        SchemaValidationError: If schema validation fails
    """
    # Validate schema immediately before use (TOCTOU prevention)
    _validate_schema(schema)

    # For async engines, attach listener to sync_engine
    target_engine = engine.sync_engine if is_async else engine

    @event.listens_for(target_engine, "connect")
    def set_search_path(dbapi_connection, connection_record):
        cursor = dbapi_connection.cursor()
        # Safe to use f-string here - schema validated immediately above
        cursor.execute(f"SET search_path TO {schema}, public")
        cursor.close()

    logger.debug(
        "Schema event listener registered",
        extra={"schema": schema, "is_async": is_async},
    )


def _create_async_engine_with_schema(schema: str | None = None) -> AsyncEngine:
    """Create an async SQLAlchemy engine with optional schema search_path.

    Args:
        schema: PostgreSQL schema name to set in search_path

    Returns:
        AsyncEngine configured for the specified schema

    Raises:
        SchemaValidationError: If schema validation fails
    """
    logger.debug(
        "Creating async engine",
        extra={"schema": schema or "public", "driver": "asyncpg"},
    )

    # Create async engine with asyncpg driver
    eng = create_async_engine(SQLALCHEMY_DATABASE_URL, **ENGINE_POOL_CONFIG)

    if schema:
        # Register event listener (validates schema internally - TOCTOU safe)
        _register_schema_event_listener(eng, schema, is_async=True)

    logger.info(
        "Async engine created successfully",
        extra={
            "schema": schema or "public",
            "pool_size": ENGINE_POOL_CONFIG["pool_size"],
        },
    )

    return eng


# Default engine (public schema) - used for migrations and general operations
engine = _create_async_engine_with_schema()
AsyncSessionLocal = async_sessionmaker(engine, class_=AsyncSession, **SESSION_CONFIG)

# Schema-specific engine for global_schema
global_engine = _create_async_engine_with_schema(GLOBAL_SCHEMA)

# Schema-specific async session factory
GlobalAsyncSessionLocal = async_sessionmaker(global_engine, class_=AsyncSession, **SESSION_CONFIG)

# Synchronous engines and sessions for scripts/migrations
# Use original DATABASE_URL (without asyncpg driver) for sync operations
SYNC_DATABASE_URL = settings.DATABASE_URL


def _create_sync_engine_with_schema(schema: str | None = None) -> Engine:
    """Create a synchronous SQLAlchemy engine with optional schema search_path.

    Used by migration scripts and other synchronous operations.

    Args:
        schema: PostgreSQL schema name to set in search_path

    Returns:
        Engine configured for the specified schema

    Raises:
        SchemaValidationError: If schema validation fails
    """
    logger.debug(
        "Creating sync engine",
        extra={"schema": schema or "public", "driver": "psycopg2"},
    )

    # Create sync engine with psycopg2 driver
    eng = create_engine(SYNC_DATABASE_URL, **ENGINE_POOL_CONFIG)

    if schema:
        # Register event listener (validates schema internally - TOCTOU safe)
        _register_schema_event_listener(eng, schema, is_async=False)

    logger.info(
        "Sync engine created successfully",
        extra={
            "schema": schema or "public",
            "pool_size": ENGINE_POOL_CONFIG["pool_size"],
        },
    )

    return eng


# Synchronous session factory for scripts/migrations (global_schema)
global_sync_engine = _create_sync_engine_with_schema(GLOBAL_SCHEMA)
GlobalSessionLocal = sessionmaker(bind=global_sync_engine, class_=Session, **SESSION_CONFIG)


@contextmanager
def get_global_sync_session() -> Generator[Session, None, None]:
    """Get a synchronous database session for global_schema.

    Convenience context manager for scripts and migrations,
    consistent with the async get_global_db() pattern.

    Yields:
        Session for global_schema
    """
    session = GlobalSessionLocal()
    try:
        yield session
    finally:
        session.close()


# Base classes for models - use schema parameter in __table_args__
Base = declarative_base()
GlobalBase = declarative_base()


async def get_db() -> AsyncGenerator[AsyncSession, None]:
    """Get an async database session (default/public schema).

    Yields:
        AsyncSession for public schema
    """
    async with AsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()


async def get_global_db() -> AsyncGenerator[AsyncSession, None]:
    """Get an async database session for global_schema.

    Yields:
        AsyncSession for global_schema
    """
    async with GlobalAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()


from contextlib import asynccontextmanager  # noqa: E402


@asynccontextmanager
async def get_global_db_context() -> AsyncGenerator[AsyncSession, None]:
    """Async context manager for global_schema sessions outside of FastAPI Depends."""
    async with GlobalAsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()
