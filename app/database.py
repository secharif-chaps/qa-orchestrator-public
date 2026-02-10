from typing import AsyncGenerator
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession
from sqlalchemy.orm import declarative_base
from sqlalchemy import event
from app.core.config import settings

# Convert DATABASE_URL to async format (postgresql:// -> postgresql+asyncpg://)
SQLALCHEMY_DATABASE_URL = settings.DATABASE_URL
if SQLALCHEMY_DATABASE_URL.startswith("postgresql://"):
    SQLALCHEMY_DATABASE_URL = SQLALCHEMY_DATABASE_URL.replace(
        "postgresql://", "postgresql+asyncpg://", 1
    )

# Schema names
GLOBAL_SCHEMA = "global_schema"


def _create_async_engine_with_schema(schema: str | None = None):
    """Create an async SQLAlchemy engine with optional schema search_path.

    Args:
        schema: PostgreSQL schema name to set in search_path

    Returns:
        AsyncEngine configured for the specified schema
    """
    # Create async engine with asyncpg driver
    eng = create_async_engine(
        SQLALCHEMY_DATABASE_URL,
        pool_size=settings.DB_POOL_SIZE,
        max_overflow=settings.DB_MAX_OVERFLOW,
        pool_timeout=settings.DB_POOL_TIMEOUT,
        pool_recycle=settings.DB_POOL_RECYCLE,
        pool_pre_ping=True,
        future=True,
    )

    if schema:
        # Set search_path for async engine using event listener
        @event.listens_for(eng.sync_engine, "connect")
        def set_search_path(dbapi_connection, connection_record):
            cursor = dbapi_connection.cursor()
            cursor.execute(f"SET search_path TO {schema}, public")
            cursor.close()

    return eng


# Default engine (public schema) - used for migrations and general operations
engine = _create_async_engine_with_schema()
AsyncSessionLocal = async_sessionmaker(
    engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)

# Schema-specific engine for global_schema
global_engine = _create_async_engine_with_schema(GLOBAL_SCHEMA)

# Schema-specific async session factory
GlobalAsyncSessionLocal = async_sessionmaker(
    global_engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autocommit=False,
    autoflush=False,
)

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
