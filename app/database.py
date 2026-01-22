from typing import Generator
from sqlalchemy import create_engine, event
from sqlalchemy.engine import Engine
from sqlalchemy.orm import declarative_base
from sqlalchemy.orm import sessionmaker, Session
from app.core.config import settings

SQLALCHEMY_DATABASE_URL = settings.DATABASE_URL

# Schema names
GLOBAL_SCHEMA = "global_schema"
SCREEN_SCHEMA = "screen_schema"


def _create_engine_with_schema(schema: str | None = None) -> Engine:
    """Create a SQLAlchemy engine with optional schema search_path."""
    eng = create_engine(
        SQLALCHEMY_DATABASE_URL,
        pool_size=settings.DB_POOL_SIZE,
        max_overflow=settings.DB_MAX_OVERFLOW,
        pool_timeout=settings.DB_POOL_TIMEOUT,
        pool_recycle=settings.DB_POOL_RECYCLE,
        pool_pre_ping=True,
        future=True,
    )

    if schema:
        # Set search_path on each new connection
        @event.listens_for(eng, "connect")
        def set_search_path(dbapi_connection, connection_record):
            cursor = dbapi_connection.cursor()
            cursor.execute(f"SET search_path TO {schema}, public")
            cursor.close()

    return eng


# Default engine (public schema) - used for migrations and general operations
engine = _create_engine_with_schema()
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Schema-specific engines
global_engine = _create_engine_with_schema(GLOBAL_SCHEMA)
screen_engine = _create_engine_with_schema(SCREEN_SCHEMA)

# Schema-specific session factories
GlobalSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=global_engine)
ScreenSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=screen_engine)

# Base classes for models - use schema parameter in __table_args__
Base = declarative_base()
GlobalBase = declarative_base()
ScreenBase = declarative_base()


def get_db() -> Generator[Session, None, None]:
    """Get a database session (default/public schema)."""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def get_global_db() -> Generator[Session, None, None]:
    """Get a database session for global_schema."""
    db = GlobalSessionLocal()
    try:
        yield db
    finally:
        db.close()


def get_screen_db() -> Generator[Session, None, None]:
    """Get a database session for screen_schema."""
    db = ScreenSessionLocal()
    try:
        yield db
    finally:
        db.close() 