"""Tests for task REST API endpoint functions.

Covers: get_company_tasks, restart_task, task_events_stream.

Tests the endpoint functions directly (not via TestClient).
"""

from datetime import UTC, datetime
from unittest.mock import AsyncMock, MagicMock, patch

import pytest
from fastapi import HTTPException

from app.core.auth import AuthenticatedUser
from app.core.organization_context import OrganizationContext
from app.models.task import Task, TaskStatus, TaskType
from app.services.company import CompanyService

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_user():
    return AuthenticatedUser(
        sub="user-uuid-123",
        preferred_username="testuser",
        roles=["company.view"],
    )


@pytest.fixture
def mock_org():
    return OrganizationContext(
        organization_id="org-uuid-1",
        organization_name="TestOrg",
        user_id="user-uuid-123",
        username="testuser",
    )


@pytest.fixture
def mock_service():
    return MagicMock(spec=CompanyService)


def _make_task_mock(
    task_id: int = 1,
    company_id: int = 10,
    task_type: str = "profile",
    status: TaskStatus = TaskStatus.PENDING,
) -> MagicMock:
    """Create a mock Task object."""
    task = MagicMock(spec=Task)
    task.id = task_id
    task.company_id = company_id
    task.type = TaskType(task_type)
    task.status = status
    task.error = None
    task.error_details = None
    task.created_at = datetime(2025, 1, 1, tzinfo=UTC)
    task.updated_at = datetime(2025, 1, 1, tzinfo=UTC)
    task.input_tokens = None
    task.output_tokens = None
    task.total_cost = None
    task.organization_id = "org-uuid-1"
    return task


# ---------------------------------------------------------------------------
# GET /tasks/company/{company_id} — get_company_tasks
# ---------------------------------------------------------------------------


class TestGetCompanyTasks:
    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.verify_company_organization_access")
    @patch("app.api.endpoints.tasks.TaskService")
    async def test_returns_tasks_for_company(self, MockTaskService, mock_verify, mock_service, mock_user, mock_org):
        task1 = _make_task_mock(task_id=1)
        task2 = _make_task_mock(task_id=2, task_type="digital")

        company = MagicMock()
        company.tasks = [task1, task2]
        mock_service.get_company.return_value = company
        mock_service.db = MagicMock()
        MockTaskService.return_value.cleanup_stale_tasks_for_company.return_value = 0

        from app.api.endpoints.tasks import get_company_tasks

        result = await get_company_tasks(
            company_id=10,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        assert result == [task1, task2]

    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.verify_company_organization_access")
    @patch("app.api.endpoints.tasks.TaskService")
    async def test_calls_stale_cleanup(self, MockTaskService, mock_verify, mock_service, mock_user, mock_org):
        company = MagicMock()
        company.tasks = []
        mock_service.get_company.return_value = company
        mock_service.db = MagicMock()
        mock_ts = MockTaskService.return_value
        mock_ts.cleanup_stale_tasks_for_company.return_value = 0

        from app.api.endpoints.tasks import get_company_tasks

        await get_company_tasks(
            company_id=10,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        mock_ts.cleanup_stale_tasks_for_company.assert_called_once_with(10)

    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.verify_company_organization_access")
    @patch("app.api.endpoints.tasks.TaskService")
    async def test_refreshes_on_cleanup(self, MockTaskService, mock_verify, mock_service, mock_user, mock_org):
        company = MagicMock()
        company.tasks = []
        mock_service.get_company.return_value = company
        mock_service.db = MagicMock()
        MockTaskService.return_value.cleanup_stale_tasks_for_company.return_value = 2

        from app.api.endpoints.tasks import get_company_tasks

        await get_company_tasks(
            company_id=10,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        mock_service.db.refresh.assert_called_once_with(company)

    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.verify_company_organization_access")
    @patch("app.api.endpoints.tasks.TaskService")
    async def test_no_refresh_when_no_cleanup(self, MockTaskService, mock_verify, mock_service, mock_user, mock_org):
        company = MagicMock()
        company.tasks = []
        mock_service.get_company.return_value = company
        mock_service.db = MagicMock()
        MockTaskService.return_value.cleanup_stale_tasks_for_company.return_value = 0

        from app.api.endpoints.tasks import get_company_tasks

        await get_company_tasks(
            company_id=10,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        mock_service.db.refresh.assert_not_called()


# ---------------------------------------------------------------------------
# POST /tasks/{task_id}/restart — restart_task
# ---------------------------------------------------------------------------


class TestRestartTask:
    @pytest.mark.asyncio
    async def test_restart_success(self, mock_service, mock_user, mock_org):
        task = _make_task_mock(task_id=5)
        company = MagicMock()
        company.tasks = [task]
        mock_service.get_all_companies.return_value = [company]
        mock_service.restart_task.return_value = task

        from app.api.endpoints.tasks import restart_task

        result = await restart_task(
            task_id=5,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        assert result == task
        mock_service.restart_task.assert_called_once_with(5)

    @pytest.mark.asyncio
    async def test_restart_not_found(self, mock_service, mock_user, mock_org):
        mock_service.get_all_companies.return_value = []

        from app.api.endpoints.tasks import restart_task

        with pytest.raises(HTTPException) as exc_info:
            await restart_task(
                task_id=999,
                service=mock_service,
                user=mock_user,
                org_context=mock_org,
            )
        assert exc_info.value.status_code == 404

    @pytest.mark.asyncio
    async def test_restart_wrong_task_id(self, mock_service, mock_user, mock_org):
        other_task = _make_task_mock(task_id=99)
        company = MagicMock()
        company.tasks = [other_task]
        mock_service.get_all_companies.return_value = [company]

        from app.api.endpoints.tasks import restart_task

        with pytest.raises(HTTPException) as exc_info:
            await restart_task(
                task_id=5,
                service=mock_service,
                user=mock_user,
                org_context=mock_org,
            )
        assert exc_info.value.status_code == 404

    @pytest.mark.asyncio
    async def test_restart_filters_by_org(self, mock_service, mock_user, mock_org):
        """get_all_companies is called with the user's organization_id."""
        task = _make_task_mock(task_id=1)
        company = MagicMock()
        company.tasks = [task]
        mock_service.get_all_companies.return_value = [company]
        mock_service.restart_task.return_value = task

        from app.api.endpoints.tasks import restart_task

        await restart_task(
            task_id=1,
            service=mock_service,
            user=mock_user,
            org_context=mock_org,
        )

        mock_service.get_all_companies.assert_called_once_with(organization_id="org-uuid-1")


# ---------------------------------------------------------------------------
# GET /tasks/events/stream — task_events_stream
# ---------------------------------------------------------------------------


class TestSSEStream:
    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.task_event_manager")
    async def test_returns_streaming_response(self, mock_mgr, mock_user):
        mock_queue = AsyncMock()
        mock_mgr.subscribe.return_value = mock_queue

        request = MagicMock()
        request.is_disconnected = AsyncMock(return_value=True)

        from app.api.endpoints.tasks import task_events_stream

        response = await task_events_stream(request=request, user=mock_user)

        assert response.media_type == "text/event-stream"
        assert response.headers.get("Cache-Control") == "no-cache"
        assert response.headers.get("X-Accel-Buffering") == "no"

    @pytest.mark.asyncio
    @patch("app.api.endpoints.tasks.task_event_manager")
    async def test_subscribes_with_user_id(self, mock_mgr, mock_user):
        mock_queue = AsyncMock()
        mock_mgr.subscribe.return_value = mock_queue

        request = MagicMock()
        request.is_disconnected = AsyncMock(return_value=True)

        from app.api.endpoints.tasks import task_events_stream

        response = await task_events_stream(request=request, user=mock_user)

        # The generator inside StreamingResponse hasn't started yet;
        # iterate once to trigger subscribe
        gen = response.body_iterator
        await gen.__anext__()

        mock_mgr.subscribe.assert_called_once_with("user-uuid-123")
