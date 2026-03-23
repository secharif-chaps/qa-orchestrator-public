"""Tests for TaskService: lazy stale task cleanup and queries.

Covers: cleanup_stale_tasks_for_company, cleanup_all_stale_tasks,
        get_stale_task_count, get_stale_tasks.
"""

from datetime import UTC, datetime, timedelta
from unittest.mock import MagicMock, patch

import pytest
from sqlalchemy.orm import Session

from app.models.task import Task, TaskStatus
from app.services.task_service import DEFAULT_TASK_TIMEOUT_MINUTES, TaskService

# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------


@pytest.fixture
def mock_db():
    return MagicMock(spec=Session)


@pytest.fixture
def service(mock_db):
    with patch("app.services.task_service.settings") as mock_settings:
        mock_settings.TASK_TIMEOUT_MINUTES = 5
        svc = TaskService(mock_db)
    return svc


def _make_stale_task(task_id: int = 1, company_id: int = 10, org_id: str = "org-1") -> MagicMock:
    """Create a mock task that looks stale (RUNNING, old updated_at)."""
    task = MagicMock(spec=Task)
    task.id = task_id
    task.company_id = company_id
    task.organization_id = org_id
    task.status = TaskStatus.RUNNING
    task.type = MagicMock()
    task.type.value = "profile"
    task.updated_at = datetime.now(UTC) - timedelta(minutes=10)
    task.error = None
    return task


def _make_fresh_task(task_id: int = 2, company_id: int = 10) -> MagicMock:
    """Create a mock task that is RUNNING but recently updated."""
    task = MagicMock(spec=Task)
    task.id = task_id
    task.company_id = company_id
    task.status = TaskStatus.RUNNING
    task.type = MagicMock()
    task.type.value = "digital"
    task.updated_at = datetime.now(UTC) - timedelta(minutes=1)
    task.error = None
    return task


# ---------------------------------------------------------------------------
# cleanup_stale_tasks_for_company
# ---------------------------------------------------------------------------


class TestCleanupStaleTasksForCompany:
    def test_stale_tasks_marked_error(self, service, mock_db):
        stale = _make_stale_task()
        mock_db.query.return_value.filter.return_value.all.return_value = [stale]

        count = service.cleanup_stale_tasks_for_company(company_id=10)

        assert count == 1
        assert stale.status == TaskStatus.ERROR
        assert "timeout" in stale.error.lower()
        mock_db.commit.assert_called_once()

    def test_no_stale_tasks_returns_zero(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.all.return_value = []

        count = service.cleanup_stale_tasks_for_company(company_id=10)

        assert count == 0
        mock_db.commit.assert_not_called()

    def test_multiple_stale_tasks(self, service, mock_db):
        tasks = [_make_stale_task(task_id=i) for i in range(3)]
        mock_db.query.return_value.filter.return_value.all.return_value = tasks

        count = service.cleanup_stale_tasks_for_company(company_id=10)

        assert count == 3
        for t in tasks:
            assert t.status == TaskStatus.ERROR
        mock_db.commit.assert_called_once()

    def test_error_message_includes_timeout(self, service, mock_db):
        stale = _make_stale_task()
        mock_db.query.return_value.filter.return_value.all.return_value = [stale]

        service.cleanup_stale_tasks_for_company(company_id=10)

        assert "5 minutes" in stale.error


# ---------------------------------------------------------------------------
# cleanup_all_stale_tasks
# ---------------------------------------------------------------------------


class TestCleanupAllStaleTasks:
    def test_finds_and_marks_all_stale(self, service, mock_db):
        tasks = [_make_stale_task(task_id=1, company_id=10), _make_stale_task(task_id=2, company_id=20)]
        mock_db.query.return_value.filter.return_value.all.return_value = tasks

        count = service.cleanup_all_stale_tasks()

        assert count == 2
        for t in tasks:
            assert t.status == TaskStatus.ERROR
        mock_db.commit.assert_called_once()

    def test_no_stale_tasks_returns_zero(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.all.return_value = []

        count = service.cleanup_all_stale_tasks()

        assert count == 0
        mock_db.commit.assert_not_called()


# ---------------------------------------------------------------------------
# get_stale_task_count
# ---------------------------------------------------------------------------


class TestGetStaleTaskCount:
    def test_returns_count(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.count.return_value = 5

        assert service.get_stale_task_count() == 5

    def test_zero_count(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.count.return_value = 0

        assert service.get_stale_task_count() == 0

    def test_does_not_modify_tasks(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.count.return_value = 3

        service.get_stale_task_count()

        mock_db.commit.assert_not_called()


# ---------------------------------------------------------------------------
# get_stale_tasks
# ---------------------------------------------------------------------------


class TestGetStaleTasks:
    def test_returns_task_list(self, service, mock_db):
        tasks = [_make_stale_task(task_id=1), _make_stale_task(task_id=2)]
        mock_db.query.return_value.filter.return_value.all.return_value = tasks

        result = service.get_stale_tasks()

        assert result == tasks

    def test_respects_limit(self, service, mock_db):
        tasks = [_make_stale_task(task_id=1)]
        mock_db.query.return_value.filter.return_value.limit.return_value.all.return_value = tasks

        result = service.get_stale_tasks(limit=5)

        mock_db.query.return_value.filter.return_value.limit.assert_called_once_with(5)
        assert result == tasks

    def test_no_limit_returns_all(self, service, mock_db):
        tasks = [_make_stale_task(task_id=i) for i in range(10)]
        mock_db.query.return_value.filter.return_value.all.return_value = tasks

        result = service.get_stale_tasks()

        assert len(result) == 10

    def test_does_not_modify_tasks(self, service, mock_db):
        mock_db.query.return_value.filter.return_value.all.return_value = []

        service.get_stale_tasks()

        mock_db.commit.assert_not_called()


# ---------------------------------------------------------------------------
# Constructor
# ---------------------------------------------------------------------------


class TestTaskServiceInit:
    def test_default_timeout(self, mock_db):
        """When settings lacks TASK_TIMEOUT_MINUTES, uses default."""
        with patch("app.services.task_service.settings") as mock_settings:
            # Simulate missing attribute
            del mock_settings.TASK_TIMEOUT_MINUTES
            svc = TaskService(mock_db)

        assert svc.timeout_minutes == DEFAULT_TASK_TIMEOUT_MINUTES

    def test_custom_timeout(self, mock_db):
        with patch("app.services.task_service.settings") as mock_settings:
            mock_settings.TASK_TIMEOUT_MINUTES = 15
            svc = TaskService(mock_db)

        assert svc.timeout_minutes == 15
