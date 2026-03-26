"""Tests for CompanyAnalysisRunner — orchestration layer.

Covers all methods: _update_task_status, _extract_result, _persist_agent_result,
_broadcast_completion, _mark_tasks_errored, run_single_agent, run.

Regression tests for:
- error_details is dict (not json.dumps string)
- folder_id via global-service client (not local FolderItem table)
- task.total_cost (not task.cost)
"""

from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from sqlalchemy.orm import Session

from app.agents.config import INPUT_PRICE_PER_MILLION, OUTPUT_PRICE_PER_MILLION
from app.agents.runner import CompanyAnalysisRunner
from app.agents.state import AgentResult
from app.models.task import Task, TaskStatus

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def runner():
    return CompanyAnalysisRunner()


@pytest.fixture
def mock_db():
    db = MagicMock(spec=Session)
    return db


@pytest.fixture
def success_result() -> AgentResult:
    return AgentResult(
        agent_name="profile",
        status="success",
        data={"profile": {"insights": "test"}},
        sources=["https://example.com"],
        error=None,
        input_tokens=500,
        output_tokens=200,
        duration_ms=1500,
    )


@pytest.fixture
def error_result() -> AgentResult:
    return AgentResult(
        agent_name="profile",
        status="error",
        data={},
        sources=[],
        error="API call failed",
        input_tokens=100,
        output_tokens=0,
        duration_ms=500,
    )


@pytest.fixture
def mock_task():
    task = MagicMock(spec=Task)
    task.id = 1
    task.type = MagicMock()
    task.type.value = "profile"
    task.status = TaskStatus.RUNNING
    task.error = None
    task.error_details = None
    task.input_tokens = None
    task.output_tokens = None
    task.total_cost = None
    task.updated_at = None
    return task


# ---------------------------------------------------------------------------
# TestUpdateTaskStatus
# ---------------------------------------------------------------------------


class TestUpdateTaskStatus:
    """Tests for _update_task_status."""

    @pytest.mark.asyncio
    async def test_success_result_sets_succeeded(self, runner, mock_db, mock_task, success_result):
        await runner._update_task_status(mock_db, mock_task, success_result)

        assert mock_task.status == TaskStatus.SUCCEEDED
        assert mock_task.error is None
        assert mock_task.error_details is None
        mock_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_error_result_sets_error_status(self, runner, mock_db, mock_task, error_result):
        await runner._update_task_status(mock_db, mock_task, error_result)

        assert mock_task.status == TaskStatus.ERROR
        assert mock_task.error == "API call failed"

    @pytest.mark.asyncio
    async def test_error_details_is_dict_not_string(self, runner, mock_db, mock_task, error_result):
        """REGRESSION: error_details must be a dict, not json.dumps() string."""
        await runner._update_task_status(mock_db, mock_task, error_result)

        assert isinstance(mock_task.error_details, dict)
        assert mock_task.error_details["error_type"] == "agent_error"
        assert mock_task.error_details["message"] == "API call failed"
        assert mock_task.error_details["is_recoverable"] is True
        assert mock_task.error_details["agent_name"] == "profile"

    @pytest.mark.asyncio
    async def test_token_counts_stored(self, runner, mock_db, mock_task, success_result):
        await runner._update_task_status(mock_db, mock_task, success_result)

        assert mock_task.input_tokens == 500
        assert mock_task.output_tokens == 200

    @pytest.mark.asyncio
    async def test_total_cost_stored_not_cost(self, runner, mock_db, mock_task, success_result):
        """BUG FIX: Cost must be stored on task.total_cost, not task.cost."""
        await runner._update_task_status(mock_db, mock_task, success_result)

        expected_cost = (500 / 1_000_000) * INPUT_PRICE_PER_MILLION + (200 / 1_000_000) * OUTPUT_PRICE_PER_MILLION
        assert mock_task.total_cost == expected_cost
        # Ensure we're NOT setting a nonexistent 'cost' attribute
        assert not hasattr(mock_task, "cost") or mock_task.cost != expected_cost

    @pytest.mark.asyncio
    async def test_updated_at_set(self, runner, mock_db, mock_task, success_result):
        await runner._update_task_status(mock_db, mock_task, success_result)

        assert mock_task.updated_at is not None

    @pytest.mark.asyncio
    async def test_db_commit_called(self, runner, mock_db, mock_task, success_result):
        await runner._update_task_status(mock_db, mock_task, success_result)
        mock_db.commit.assert_called_once()

    @pytest.mark.asyncio
    async def test_success_clears_previous_error(self, runner, mock_db, mock_task, success_result):
        mock_task.error = "previous error"
        mock_task.error_details = {"old": "details"}

        await runner._update_task_status(mock_db, mock_task, success_result)

        assert mock_task.error is None
        assert mock_task.error_details is None


# ---------------------------------------------------------------------------
# TestExtractResult
# ---------------------------------------------------------------------------


class TestExtractResult:
    """Tests for _extract_result."""

    def test_returns_matching_result(self, runner):
        event = {
            "data": {
                "output": {
                    "agent_results": [
                        {"agent_name": "profile", "status": "success"},
                        {"agent_name": "digital", "status": "success"},
                    ]
                }
            }
        }
        result = runner._extract_result(event, "profile")
        assert result is not None
        assert result["agent_name"] == "profile"

    def test_returns_none_for_no_match(self, runner):
        event = {
            "data": {
                "output": {
                    "agent_results": [
                        {"agent_name": "digital", "status": "success"},
                    ]
                }
            }
        }
        result = runner._extract_result(event, "profile")
        assert result is None

    def test_returns_none_for_empty_results(self, runner):
        event = {"data": {"output": {"agent_results": []}}}
        result = runner._extract_result(event, "profile")
        assert result is None

    def test_returns_none_for_missing_keys(self, runner):
        event = {"data": {}}
        result = runner._extract_result(event, "profile")
        assert result is None

    def test_returns_none_for_malformed_event(self, runner):
        event = {"something_else": True}
        result = runner._extract_result(event, "profile")
        assert result is None

    def test_returns_none_for_empty_event(self, runner):
        result = runner._extract_result({}, "profile")
        assert result is None


# ---------------------------------------------------------------------------
# TestPersistAgentResult
# ---------------------------------------------------------------------------


class TestPersistAgentResult:
    """Tests for _persist_agent_result."""

    @pytest.mark.asyncio
    @patch("app.agents.runner.write_section_data")
    async def test_success_calls_write_section_data(self, mock_write, runner, mock_db, success_result):
        await runner._persist_agent_result(mock_db, 1, "profile", success_result)
        mock_write.assert_called_once_with(mock_db, 1, "profile", success_result["data"])

    @pytest.mark.asyncio
    @patch("app.agents.runner.write_section_data")
    async def test_error_result_skips_persistence(self, mock_write, runner, mock_db, error_result):
        await runner._persist_agent_result(mock_db, 1, "profile", error_result)
        mock_write.assert_not_called()

    @pytest.mark.asyncio
    @patch("app.agents.runner.write_section_data")
    async def test_empty_data_skips_persistence(self, mock_write, runner, mock_db):
        result = AgentResult(
            agent_name="profile",
            status="success",
            data={},
            sources=[],
            error=None,
            input_tokens=0,
            output_tokens=0,
            duration_ms=0,
        )
        await runner._persist_agent_result(mock_db, 1, "profile", result)
        mock_write.assert_not_called()

    @pytest.mark.asyncio
    @patch("app.agents.runner.write_section_data", side_effect=Exception("DB error"))
    async def test_exception_caught_and_logged(self, mock_write, runner, mock_db, success_result):
        """Exception in write_section_data is caught, not propagated."""
        # Should not raise
        await runner._persist_agent_result(mock_db, 1, "profile", success_result)


# ---------------------------------------------------------------------------
# TestBroadcastCompletion
# ---------------------------------------------------------------------------


class TestBroadcastCompletion:
    """Tests for _broadcast_completion."""

    @pytest.mark.asyncio
    @patch("app.agents.runner.get_global_service_client")
    @patch("app.agents.runner.task_event_manager")
    async def test_folder_id_via_global_service(self, mock_events, mock_get_client, runner, mock_db):
        """REGRESSION: folder_id must be looked up via GlobalServiceClient."""
        mock_client = MagicMock()
        mock_client.get_company_folder_id = AsyncMock(return_value="folder-uuid-123")
        mock_get_client.return_value = mock_client

        task_map = {"profile": MagicMock(status=TaskStatus.SUCCEEDED)}
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        await runner._broadcast_completion(mock_db, "owner-1", 1, "Test Co", "org-1", task_map)

        mock_events.broadcast_all_tasks_completed.assert_called_once()
        call_kwargs = mock_events.broadcast_all_tasks_completed.call_args[1]
        assert call_kwargs["folder_id"] == "folder-uuid-123"

    @pytest.mark.asyncio
    @patch("app.agents.runner.get_global_service_client")
    @patch("app.agents.runner.task_event_manager")
    async def test_no_folder_item_returns_none(self, mock_events, mock_get_client, runner, mock_db):
        mock_client = MagicMock()
        mock_client.get_company_folder_id = AsyncMock(return_value=None)
        mock_get_client.return_value = mock_client

        task_map = {"profile": MagicMock(status=TaskStatus.SUCCEEDED)}
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        await runner._broadcast_completion(mock_db, "owner-1", 1, "Test Co", "org-1", task_map)

        call_kwargs = mock_events.broadcast_all_tasks_completed.call_args[1]
        assert call_kwargs["folder_id"] is None

    @pytest.mark.asyncio
    @patch("app.agents.runner.get_global_service_client")
    @patch("app.agents.runner.task_event_manager")
    async def test_correct_success_error_counts(self, mock_events, mock_get_client, runner, mock_db):
        mock_client = MagicMock()
        mock_client.get_company_folder_id = AsyncMock(return_value=None)
        mock_get_client.return_value = mock_client
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        task_map = {
            "profile": MagicMock(status=TaskStatus.SUCCEEDED),
            "digital": MagicMock(status=TaskStatus.SUCCEEDED),
            "press": MagicMock(status=TaskStatus.ERROR),
        }

        await runner._broadcast_completion(mock_db, "owner-1", 1, "Test Co", "org-1", task_map)

        call_kwargs = mock_events.broadcast_all_tasks_completed.call_args[1]
        assert call_kwargs["success_count"] == 2
        assert call_kwargs["error_count"] == 1


# ---------------------------------------------------------------------------
# TestMarkTasksErrored
# ---------------------------------------------------------------------------


class TestMarkTasksErrored:
    """Tests for _mark_tasks_errored."""

    @pytest.mark.asyncio
    async def test_running_tasks_marked_error(self, runner, mock_db):
        running_task = MagicMock(status=TaskStatus.RUNNING)
        task_map = {"profile": running_task}

        await runner._mark_tasks_errored(mock_db, task_map, "Pipeline crashed")

        assert running_task.status == TaskStatus.ERROR
        assert running_task.error == "Pipeline crashed"

    @pytest.mark.asyncio
    async def test_error_details_is_dict_with_pipeline_type(self, runner, mock_db):
        """REGRESSION: error_details must be dict, not string."""
        running_task = MagicMock(status=TaskStatus.RUNNING)
        task_map = {"profile": running_task}

        await runner._mark_tasks_errored(mock_db, task_map, "Pipeline crashed")

        assert isinstance(running_task.error_details, dict)
        assert running_task.error_details["error_type"] == "pipeline_error"
        assert running_task.error_details["is_recoverable"] is True

    @pytest.mark.asyncio
    async def test_succeeded_tasks_untouched(self, runner, mock_db):
        succeeded_task = MagicMock(status=TaskStatus.SUCCEEDED)
        running_task = MagicMock(status=TaskStatus.RUNNING)
        task_map = {"profile": succeeded_task, "digital": running_task}

        await runner._mark_tasks_errored(mock_db, task_map, "Pipeline crashed")

        assert succeeded_task.status == TaskStatus.SUCCEEDED
        assert running_task.status == TaskStatus.ERROR

    @pytest.mark.asyncio
    async def test_single_commit(self, runner, mock_db):
        task_map = {
            "profile": MagicMock(status=TaskStatus.RUNNING),
            "digital": MagicMock(status=TaskStatus.RUNNING),
        }

        await runner._mark_tasks_errored(mock_db, task_map, "error")

        mock_db.commit.assert_called_once()


# ---------------------------------------------------------------------------
# TestRunSingleAgent
# ---------------------------------------------------------------------------


class TestRunSingleAgent:
    """Tests for run_single_agent."""

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    @patch("app.agents.runner.run_agent")
    async def test_task_reset_to_running(self, mock_run, mock_write, mock_events, runner, mock_db, mock_task):
        mock_run.return_value = AgentResult(
            agent_name="profile",
            status="success",
            data={"profile": {"insights": "ok"}},
            sources=[],
            error=None,
            input_tokens=10,
            output_tokens=5,
            duration_ms=100,
        )
        mock_events.broadcast_task_update = AsyncMock()

        company = MagicMock()
        company.id = 1
        company.name = "Test"
        company.website = "https://test.com"
        company.owner_id = "owner-1"

        await runner.run_single_agent(mock_db, mock_task, company)

        assert mock_task.status == TaskStatus.SUCCEEDED  # after update
        assert mock_task.error is None

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    @patch("app.agents.runner.run_agent")
    async def test_success_persists_and_broadcasts(self, mock_run, mock_write, mock_events, runner, mock_db, mock_task):
        result = AgentResult(
            agent_name="profile",
            status="success",
            data={"profile": {"insights": "ok"}},
            sources=["https://x.com"],
            error=None,
            input_tokens=10,
            output_tokens=5,
            duration_ms=100,
        )
        mock_run.return_value = result
        mock_events.broadcast_task_update = AsyncMock()

        company = MagicMock()
        company.id = 1
        company.name = "Test"
        company.website = "https://test.com"
        company.owner_id = "owner-1"

        await runner.run_single_agent(mock_db, mock_task, company)

        mock_write.assert_called_once()
        # broadcast called at least twice: running + result
        assert mock_events.broadcast_task_update.call_count >= 2

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.run_agent", side_effect=Exception("Agent blew up"))
    async def test_exception_sets_error_details_dict(self, mock_run, mock_events, runner, mock_db, mock_task):
        """REGRESSION: error_details on exception must be dict, not string."""
        mock_events.broadcast_task_update = AsyncMock()

        company = MagicMock()
        company.id = 1
        company.name = "Test"
        company.website = "https://test.com"
        company.owner_id = "owner-1"

        await runner.run_single_agent(mock_db, mock_task, company)

        assert mock_task.status == TaskStatus.ERROR
        assert isinstance(mock_task.error_details, dict)
        assert mock_task.error_details["error_type"] == "agent_execution_error"
        assert "Agent blew up" in mock_task.error_details["message"]

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.run_agent", side_effect=Exception("fail"))
    async def test_exception_broadcasts_error_sse(self, mock_run, mock_events, runner, mock_db, mock_task):
        mock_events.broadcast_task_update = AsyncMock()

        company = MagicMock()
        company.id = 1
        company.name = "Test"
        company.website = "https://test.com"
        company.owner_id = "owner-1"

        await runner.run_single_agent(mock_db, mock_task, company)

        # Last broadcast should be error
        last_call = mock_events.broadcast_task_update.call_args_list[-1]
        assert last_call[1]["status"] == "error"

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    @patch("app.agents.runner.run_agent")
    async def test_sse_running_broadcast_before_execution(
        self, mock_run, mock_write, mock_events, runner, mock_db, mock_task
    ):
        result = AgentResult(
            agent_name="profile",
            status="success",
            data={"profile": {"insights": "ok"}},
            sources=[],
            error=None,
            input_tokens=10,
            output_tokens=5,
            duration_ms=100,
        )
        mock_run.return_value = result
        mock_events.broadcast_task_update = AsyncMock()

        company = MagicMock()
        company.id = 1
        company.name = "Test"
        company.website = "https://test.com"
        company.owner_id = "owner-1"

        await runner.run_single_agent(mock_db, mock_task, company)

        first_call = mock_events.broadcast_task_update.call_args_list[0]
        assert first_call[1]["status"] == "running"


# ---------------------------------------------------------------------------
# TestRunFullAnalysis
# ---------------------------------------------------------------------------


class TestRunFullAnalysis:
    """Tests for run() — full analysis pipeline."""

    def _build_tasks(self, agent_names):
        tasks = []
        for i, name in enumerate(agent_names):
            t = MagicMock(spec=Task)
            t.id = i + 1
            t.type = MagicMock()
            t.type.value = name
            t.status = TaskStatus.PENDING
            t.error = None
            t.error_details = None
            t.updated_at = None
            tasks.append(t)
        return tasks

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    async def test_all_tasks_set_running_before_graph(self, mock_write, mock_events, runner, mock_db):
        tasks = self._build_tasks(["profile", "digital"])
        mock_db.query.return_value.filter.return_value.all.return_value = tasks
        mock_events.broadcast_task_update = AsyncMock()
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        # Mock the graph to yield no events (empty analysis)
        mock_graph = MagicMock()
        mock_graph.astream_events = MagicMock(return_value=aiter([]))
        with (
            patch("app.agents.graph.get_analysis_graph", new=AsyncMock(return_value=mock_graph)),
            patch("app.agents.runner.get_global_service_client") as mock_get_gsc,
        ):
            mock_gsc_instance = MagicMock()
            mock_gsc_instance.get_company_folder_id = AsyncMock(return_value=None)
            mock_get_gsc.return_value = mock_gsc_instance

            await runner.run(mock_db, 1, "Test", "https://test.com", "org-1", "owner-1")

        # All tasks should have been set to RUNNING
        for t in tasks:
            assert t.status == TaskStatus.RUNNING or t.status in (TaskStatus.SUCCEEDED, TaskStatus.ERROR)

    @pytest.mark.asyncio
    @patch("app.agents.runner.get_global_service_client")
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    async def test_graph_exception_marks_all_running_error(
        self, mock_write, mock_events, mock_get_gsc, runner, mock_db
    ):
        tasks = self._build_tasks(["profile", "digital"])
        mock_db.query.return_value.filter.return_value.all.return_value = tasks
        mock_events.broadcast_task_update = AsyncMock()
        mock_events.broadcast_all_tasks_completed = AsyncMock()
        mock_gsc_instance = MagicMock()
        mock_gsc_instance.get_company_folder_id = AsyncMock(return_value=None)
        mock_get_gsc.return_value = mock_gsc_instance

        mock_graph = MagicMock()
        mock_graph.astream_events = MagicMock(side_effect=RuntimeError("Graph crashed"))
        with patch("app.agents.graph.get_analysis_graph", new=AsyncMock(return_value=mock_graph)):
            await runner.run(mock_db, 1, "Test", "https://test.com", "org-1", "owner-1")

        # Tasks should be marked ERROR (they were set to RUNNING before graph)
        for t in tasks:
            assert t.status == TaskStatus.ERROR
        # Completion should still be broadcast on error
        mock_events.broadcast_all_tasks_completed.assert_called_once()

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    async def test_completion_broadcast_after_success(self, mock_events, runner, mock_db):
        tasks = self._build_tasks(["profile"])
        mock_db.query.return_value.filter.return_value.all.return_value = tasks
        mock_db.query.return_value.filter.return_value.first.return_value = None
        mock_events.broadcast_task_update = AsyncMock()
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        mock_graph = MagicMock()
        mock_graph.astream_events = MagicMock(return_value=aiter([]))
        with (
            patch("app.agents.graph.get_analysis_graph", new=AsyncMock(return_value=mock_graph)),
            patch("app.agents.runner.write_section_data"),
        ):
            await runner.run(mock_db, 1, "Test", "https://test.com", "org-1", "owner-1")

        mock_events.broadcast_all_tasks_completed.assert_called_once()

    @pytest.mark.asyncio
    @patch("app.agents.runner.task_event_manager")
    @patch("app.agents.runner.write_section_data")
    async def test_run_processes_stream_events(self, mock_write, mock_events, runner, mock_db):
        """Verify the streaming loop extracts results and persists them."""
        tasks = self._build_tasks(["profile"])
        mock_db.query.return_value.filter.return_value.all.return_value = tasks
        mock_db.query.return_value.filter.return_value.first.return_value = None
        mock_events.broadcast_task_update = AsyncMock()
        mock_events.broadcast_all_tasks_completed = AsyncMock()

        # Build a realistic stream event matching on_chain_end shape
        event = {
            "event": "on_chain_end",
            "name": "agent_profile",
            "data": {
                "output": {
                    "agent_results": [
                        AgentResult(
                            agent_name="profile",
                            status="success",
                            data={"key": "val"},
                            sources=["https://example.com"],
                            error=None,
                            input_tokens=100,
                            output_tokens=50,
                            duration_ms=500,
                        )
                    ]
                }
            },
        }

        mock_graph = MagicMock()
        mock_graph.astream_events = MagicMock(return_value=aiter([event]))
        with patch("app.agents.graph.get_analysis_graph", new=AsyncMock(return_value=mock_graph)):
            await runner.run(mock_db, 1, "Test", "https://test.com", "org-1", "owner-1")

        # write_section_data should have been called for the successful result
        mock_write.assert_called_once_with(mock_db, 1, "profile", {"key": "val"})

    @pytest.mark.asyncio
    @patch("app.agents.runner.GLOBAL_ANALYSIS_TIMEOUT_SECONDS", 0.01)
    @patch("app.agents.runner.get_global_service_client")
    @patch("app.agents.runner.task_event_manager")
    async def test_global_timeout_marks_tasks_errored(self, mock_events, mock_get_gsc, runner, mock_db):
        """Graph that exceeds timeout should mark running tasks as ERROR."""
        tasks = self._build_tasks(["profile", "digital"])
        mock_db.query.return_value.filter.return_value.all.return_value = tasks
        mock_events.broadcast_task_update = AsyncMock()
        mock_events.broadcast_all_tasks_completed = AsyncMock()
        mock_gsc_instance = MagicMock()
        mock_gsc_instance.get_company_folder_id = AsyncMock(return_value=None)
        mock_get_gsc.return_value = mock_gsc_instance

        mock_graph = MagicMock()
        mock_graph.astream_events = MagicMock(return_value=slow_aiter())
        with patch("app.agents.graph.get_analysis_graph", new=AsyncMock(return_value=mock_graph)):
            await runner.run(mock_db, 1, "Test", "https://test.com", "org-1", "owner-1")

        # All tasks should be marked ERROR with timeout message
        for t in tasks:
            assert t.status == TaskStatus.ERROR
            assert "timed out" in t.error
        # Completion should still be broadcast on timeout
        mock_events.broadcast_all_tasks_completed.assert_called_once()


# ---------------------------------------------------------------------------
# Helper: async iterator from list
# ---------------------------------------------------------------------------


async def aiter(items):
    for item in items:
        yield item


async def slow_aiter():
    """Async iterator that sleeps long enough to trigger a timeout."""
    import asyncio

    await asyncio.sleep(10)
    yield {}
