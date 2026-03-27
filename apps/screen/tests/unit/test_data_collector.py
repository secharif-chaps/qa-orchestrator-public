"""Tests for the data collector LangGraph node."""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.agents.nodes.data_collector import data_collector_node


def _make_state(**overrides) -> dict:
    base = {
        "company_id": 1,
        "company_name": "TestCo",
        "website": "https://testco.com",
        "organization_id": "org-1",
        "owner_id": "user-1",
        "country_code": "FR",
        "company_brief": "A test company",
        "agents_to_run": [],
        "agent_results": [],
        "quality_issues": [],
        "agents_to_retry": [],
        "total_tokens": 0,
        "total_cost": 0.0,
    }
    base.update(overrides)
    return base


class TestDataCollectorNode:
    """Tests for data_collector_node."""

    @pytest.mark.asyncio
    async def test_returns_enrichment_data(self):
        mock_db = MagicMock()
        enrichment = {"pappers": {"siren": "123"}}

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            mock_to_thread.side_effect = lambda fn, *args, **kwargs: fn(*args, **kwargs) if args or kwargs else fn()
            mock_collect.return_value = enrichment

            # Make SessionLocal return our mock
            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                result = await data_collector_node(_make_state())

        assert result == {"enrichment_data": {"pappers": {"siren": "123"}}}

    @pytest.mark.asyncio
    async def test_passes_correct_params_to_collect(self):
        mock_db = MagicMock()

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            mock_to_thread.side_effect = lambda fn, *args, **kwargs: fn(*args, **kwargs) if args or kwargs else fn()
            mock_collect.return_value = {}

            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                await data_collector_node(_make_state())

            mock_collect.assert_called_once_with(
                db=mock_db,
                company_id=1,
                company_name="TestCo",
                organization_id="org-1",
                country_code="FR",
            )

    @pytest.mark.asyncio
    async def test_returns_empty_on_exception(self):
        """Pipeline continues with empty enrichment_data on failure."""
        mock_db = MagicMock()

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            mock_to_thread.side_effect = lambda fn, *args, **kwargs: fn(*args, **kwargs) if args or kwargs else fn()
            mock_collect.side_effect = RuntimeError("DB down")

            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                result = await data_collector_node(_make_state())

        assert result == {"enrichment_data": {}}

    @pytest.mark.asyncio
    async def test_closes_db_session_on_success(self):
        mock_db = MagicMock()

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            # Track calls to to_thread
            calls = []

            async def tracked_to_thread(fn, *args, **kwargs):
                calls.append(fn)
                if args or kwargs:
                    return fn(*args, **kwargs)
                return fn()

            mock_to_thread.side_effect = tracked_to_thread
            mock_collect.return_value = {}

            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                await data_collector_node(_make_state())

        # db.close should have been passed to to_thread
        assert mock_db.close in calls

    @pytest.mark.asyncio
    async def test_closes_db_session_on_failure(self):
        mock_db = MagicMock()

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            calls = []

            async def tracked_to_thread(fn, *args, **kwargs):
                calls.append(fn)
                if args or kwargs:
                    return fn(*args, **kwargs)
                return fn()

            mock_to_thread.side_effect = tracked_to_thread
            mock_collect.side_effect = RuntimeError("fail")

            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                await data_collector_node(_make_state())

        assert mock_db.close in calls

    @pytest.mark.asyncio
    async def test_handles_missing_country_code(self):
        """Uses None for country_code when key is absent from state."""
        mock_db = MagicMock()
        state = _make_state()
        state.pop("country_code", None)

        with (
            patch("app.agents.nodes.data_collector.asyncio.to_thread", new_callable=AsyncMock) as mock_to_thread,
            patch("app.agents.nodes.data_collector.EnrichmentService.collect", new_callable=AsyncMock) as mock_collect,
        ):
            mock_to_thread.side_effect = lambda fn, *args, **kwargs: fn(*args, **kwargs) if args or kwargs else fn()
            mock_collect.return_value = {}

            with patch("app.agents.nodes.data_collector.SessionLocal", return_value=mock_db):
                await data_collector_node(state)

            assert mock_collect.call_args.kwargs["country_code"] is None
