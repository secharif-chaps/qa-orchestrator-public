"""Tests for checkpoint connection string handling and pool lifecycle."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.agents import checkpoint as checkpoint_mod
from app.agents.checkpoint import _get_conn_string, get_checkpointer

# ---------------------------------------------------------------------------
# _get_conn_string
# ---------------------------------------------------------------------------


class TestGetConnString:
    """Connection string must always be plain postgresql:// for psycopg."""

    def test_strips_psycopg_dialect(self):
        with patch.object(checkpoint_mod, "settings") as mock_settings:
            mock_settings.DATABASE_URL = "postgresql+psycopg://user:pass@db:5432/mydb"
            assert _get_conn_string() == "postgresql://user:pass@db:5432/mydb"

    def test_keeps_plain_postgresql(self):
        with patch.object(checkpoint_mod, "settings") as mock_settings:
            mock_settings.DATABASE_URL = "postgresql://user:pass@db:5432/mydb"
            assert _get_conn_string() == "postgresql://user:pass@db:5432/mydb"

    def test_only_replaces_first_occurrence(self):
        with patch.object(checkpoint_mod, "settings") as mock_settings:
            mock_settings.DATABASE_URL = "postgresql+psycopg://host/postgresql+psycopg"
            result = _get_conn_string()
            # Only the scheme prefix should be replaced
            assert result == "postgresql://host/postgresql+psycopg"


# ---------------------------------------------------------------------------
# get_checkpointer
# ---------------------------------------------------------------------------


class TestGetCheckpointer:
    """Async checkpointer factory creates pool once and calls setup."""

    @pytest.fixture(autouse=True)
    def _reset_pool(self):
        """Reset module-level pool between tests."""
        checkpoint_mod._pool = None
        yield
        checkpoint_mod._pool = None

    @pytest.mark.asyncio
    async def test_creates_pool_and_calls_setup(self):
        mock_pool = MagicMock()
        mock_pool.open = AsyncMock()

        mock_saver = MagicMock()
        mock_saver.setup = AsyncMock()

        with (
            patch.object(checkpoint_mod, "settings") as mock_settings,
            patch("app.agents.checkpoint.AsyncConnectionPool", return_value=mock_pool),
            patch("app.agents.checkpoint.AsyncPostgresSaver", return_value=mock_saver),
        ):
            mock_settings.DATABASE_URL = "postgresql://user:pass@db:5432/mydb"

            result = await get_checkpointer()

            mock_pool.open.assert_awaited_once()
            mock_saver.setup.assert_awaited_once()
            assert result is mock_saver

    @pytest.mark.asyncio
    async def test_reuses_existing_pool(self):
        """Second call should not create a new pool."""
        mock_pool = MagicMock()
        mock_pool.open = AsyncMock()

        mock_saver = MagicMock()
        mock_saver.setup = AsyncMock()

        with (
            patch.object(checkpoint_mod, "settings") as mock_settings,
            patch("app.agents.checkpoint.AsyncConnectionPool", return_value=mock_pool) as pool_cls,
            patch("app.agents.checkpoint.AsyncPostgresSaver", return_value=mock_saver),
        ):
            mock_settings.DATABASE_URL = "postgresql://user:pass@db:5432/mydb"

            await get_checkpointer()
            await get_checkpointer()

            # Pool constructor called only once
            pool_cls.assert_called_once()
            mock_pool.open.assert_awaited_once()

    @pytest.mark.asyncio
    async def test_passes_conn_string_to_pool(self):
        mock_pool = MagicMock()
        mock_pool.open = AsyncMock()

        mock_saver = MagicMock()
        mock_saver.setup = AsyncMock()

        with (
            patch.object(checkpoint_mod, "settings") as mock_settings,
            patch("app.agents.checkpoint.AsyncConnectionPool", return_value=mock_pool) as pool_cls,
            patch("app.agents.checkpoint.AsyncPostgresSaver", return_value=mock_saver),
        ):
            mock_settings.DATABASE_URL = "postgresql+psycopg://user:pass@db:5432/mydb"

            await get_checkpointer()

            # Should strip +psycopg before passing to pool
            call_kwargs = pool_cls.call_args
            assert call_kwargs.kwargs["conninfo"] == "postgresql://user:pass@db:5432/mydb"
