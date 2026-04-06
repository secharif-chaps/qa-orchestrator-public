"""Checkpoint persistence for the LangGraph analysis graph."""

from langgraph.checkpoint.postgres.aio import AsyncPostgresSaver
from psycopg.rows import dict_row
from psycopg_pool import AsyncConnectionPool

from app.core.config import settings

# Module-level pool, created once and reused for the app lifetime.
_pool: AsyncConnectionPool | None = None


def _get_conn_string() -> str:
    """Return a plain postgresql:// URI suitable for psycopg."""
    conn_string = settings.DATABASE_URL
    if conn_string.startswith("postgresql+psycopg://"):
        conn_string = conn_string.replace("postgresql+psycopg://", "postgresql://", 1)
    return conn_string


async def get_checkpointer() -> AsyncPostgresSaver:
    """Create an async PostgreSQL checkpointer.

    Uses a long-lived connection pool so the checkpointer stays usable
    outside an ``async with`` block.  The pool is opened once and kept
    for the process lifetime.
    """
    global _pool
    if _pool is None:
        _pool = AsyncConnectionPool(
            conninfo=_get_conn_string(),
            max_size=5,
            kwargs={"autocommit": True, "row_factory": dict_row},
            open=False,
        )
        await _pool.open()

    checkpointer = AsyncPostgresSaver(conn=_pool)
    await checkpointer.setup()
    return checkpointer
