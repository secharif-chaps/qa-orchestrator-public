"""Tests for task Pydantic schemas.

Covers: TaskCreate, TaskTokenUpdate, TaskResponse.
"""

from datetime import UTC, datetime

import pytest
from pydantic import ValidationError

from app.models.task import TaskStatus, TaskType
from app.schemas.task import TaskCreate, TaskResponse, TaskTokenUpdate

# ---------------------------------------------------------------------------
# TaskCreate
# ---------------------------------------------------------------------------


class TestTaskCreate:
    def test_valid_creation(self):
        tc = TaskCreate(company_id=1, type=TaskType.profile)
        assert tc.company_id == 1
        assert tc.type == TaskType.profile

    def test_all_task_types(self):
        for tt in TaskType:
            tc = TaskCreate(company_id=1, type=tt)
            assert tc.type == tt

    def test_missing_company_id(self):
        with pytest.raises(ValidationError):
            TaskCreate(type=TaskType.profile)

    def test_missing_type(self):
        with pytest.raises(ValidationError):
            TaskCreate(company_id=1)

    def test_invalid_type(self):
        with pytest.raises(ValidationError):
            TaskCreate(company_id=1, type="invalid_type")


# ---------------------------------------------------------------------------
# TaskTokenUpdate
# ---------------------------------------------------------------------------


class TestTaskTokenUpdate:
    def test_all_fields(self):
        ttu = TaskTokenUpdate(input_tokens=100, output_tokens=50, total_cost=0.5)
        assert ttu.input_tokens == 100
        assert ttu.output_tokens == 50
        assert ttu.total_cost == 0.5

    def test_partial_fields(self):
        ttu = TaskTokenUpdate(input_tokens=100)
        assert ttu.input_tokens == 100
        assert ttu.output_tokens is None
        assert ttu.total_cost is None

    def test_all_none(self):
        ttu = TaskTokenUpdate()
        assert ttu.input_tokens is None
        assert ttu.output_tokens is None
        assert ttu.total_cost is None

    def test_zero_tokens(self):
        ttu = TaskTokenUpdate(input_tokens=0, output_tokens=0, total_cost=0.0)
        assert ttu.input_tokens == 0
        assert ttu.output_tokens == 0


# ---------------------------------------------------------------------------
# TaskResponse
# ---------------------------------------------------------------------------


class TestTaskResponse:
    def test_from_orm_model(self):
        """TaskResponse can be created from an ORM-like object."""

        class FakeTask:
            id = 1
            company_id = 10
            type = TaskType.profile
            status = TaskStatus.PENDING
            error = None
            error_details = None
            created_at = datetime(2025, 1, 1, tzinfo=UTC)
            updated_at = datetime(2025, 1, 1, tzinfo=UTC)
            input_tokens = 500
            output_tokens = 200
            total_cost = 0.01

        tr = TaskResponse.model_validate(FakeTask(), from_attributes=True)

        assert tr.id == 1
        assert tr.company_id == 10
        assert tr.type == TaskType.profile
        assert tr.status == TaskStatus.PENDING
        assert tr.input_tokens == 500
        assert tr.output_tokens == 200
        assert tr.total_cost == 0.01

    def test_nullable_fields(self):
        class FakeTask:
            id = 2
            company_id = 20
            type = TaskType.digital
            status = TaskStatus.RUNNING
            error = None
            error_details = None
            created_at = datetime(2025, 6, 1, tzinfo=UTC)
            updated_at = datetime(2025, 6, 1, tzinfo=UTC)
            input_tokens = None
            output_tokens = None
            total_cost = None

        tr = TaskResponse.model_validate(FakeTask(), from_attributes=True)

        assert tr.error is None
        assert tr.error_details is None
        assert tr.input_tokens is None
        assert tr.output_tokens is None
        assert tr.total_cost is None

    def test_error_fields(self):
        class FakeTask:
            id = 3
            company_id = 30
            type = TaskType.press
            status = TaskStatus.ERROR
            error = "Something went wrong"
            error_details = {"code": "TIMEOUT", "message": "Timed out"}
            created_at = datetime(2025, 3, 1, tzinfo=UTC)
            updated_at = datetime(2025, 3, 1, tzinfo=UTC)
            input_tokens = 100
            output_tokens = 0
            total_cost = 0.001

        tr = TaskResponse.model_validate(FakeTask(), from_attributes=True)

        assert tr.error == "Something went wrong"
        assert tr.error_details == {"code": "TIMEOUT", "message": "Timed out"}
        assert tr.status == TaskStatus.ERROR

    def test_serialization(self):
        tr = TaskResponse(
            id=1,
            company_id=10,
            type=TaskType.profile,
            status=TaskStatus.SUCCEEDED,
            error=None,
            error_details=None,
            created_at=datetime(2025, 1, 1, tzinfo=UTC),
            updated_at=datetime(2025, 1, 1, tzinfo=UTC),
            input_tokens=100,
            output_tokens=50,
            total_cost=0.005,
        )
        data = tr.model_dump()

        assert data["id"] == 1
        assert data["type"] == TaskType.profile
        assert data["status"] == TaskStatus.SUCCEEDED
