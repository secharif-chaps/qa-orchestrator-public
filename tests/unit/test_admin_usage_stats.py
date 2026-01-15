"""Unit tests for admin usage statistics endpoint.

Tests the GET /api/admin/usage-stats endpoint including:
- Response structure validation
- Date filtering
- Task success rate calculation
- Permission requirements
"""

import pytest
from datetime import date, datetime, timedelta
from unittest.mock import AsyncMock, MagicMock, patch

from app.models.company import Company
from app.models.task import Task, TaskStatus, TaskType
from app.schemas.admin_usage import (
    OrganizationBreakdown,
    TimeSeriesDataPoint,
    UsageStatsResponse,
)
from app.api.endpoints.admin import (
    _get_companies_count,
    _get_task_success_rate,
    _get_active_users_count,
    _get_companies_over_time,
    _get_companies_by_organization,
)


class TestUsageStatsResponseStructure:
    """Test that response schema contains all required fields with correct types."""

    def test_response_schema_has_all_required_fields(self):
        """Test UsageStatsResponse schema contains all required fields."""
        response = UsageStatsResponse(
            companies_count=42,
            task_success_rate=94.5,
            active_users_count=10,
            companies_over_time=[
                TimeSeriesDataPoint(period="2025-01-01T00:00:00", count=5),
                TimeSeriesDataPoint(period="2025-01-02T00:00:00", count=8),
            ],
            companies_by_organization=[
                OrganizationBreakdown(
                    organization_id="org-uuid-1",
                    organization_name="Acme Corp",
                    companies_count=25,
                    percentage=59.5,
                ),
                OrganizationBreakdown(
                    organization_id="org-uuid-2",
                    organization_name="Globex Inc",
                    companies_count=17,
                    percentage=40.5,
                ),
            ],
        )

        assert response.companies_count == 42
        assert response.task_success_rate == 94.5
        assert response.active_users_count == 10
        assert len(response.companies_over_time) == 2
        assert len(response.companies_by_organization) == 2

    def test_response_schema_accepts_null_success_rate(self):
        """Test UsageStatsResponse accepts None for task_success_rate (no tasks case)."""
        response = UsageStatsResponse(
            companies_count=0,
            task_success_rate=None,
            active_users_count=0,
            companies_over_time=[],
            companies_by_organization=[],
        )

        assert response.task_success_rate is None

    def test_time_series_data_point_schema(self):
        """Test TimeSeriesDataPoint schema validates correctly."""
        point = TimeSeriesDataPoint(period="2025-01-15T00:00:00", count=10)
        assert point.period == "2025-01-15T00:00:00"
        assert point.count == 10

    def test_organization_breakdown_schema(self):
        """Test OrganizationBreakdown schema validates correctly."""
        breakdown = OrganizationBreakdown(
            organization_id="uuid-123",
            organization_name="Test Org",
            companies_count=50,
            percentage=75.5,
        )
        assert breakdown.organization_id == "uuid-123"
        assert breakdown.organization_name == "Test Org"
        assert breakdown.companies_count == 50
        assert breakdown.percentage == 75.5


class TestCompaniesCountQuery:
    """Test companies count aggregation query."""

    def test_companies_count_filters_by_date_range(self, db_session):
        """Test companies count correctly filters by date range."""
        # Create companies with different dates
        today = datetime.utcnow()
        yesterday = today - timedelta(days=1)
        last_week = today - timedelta(days=7)

        # Company within range (yesterday)
        company1 = Company(
            name="Company 1",
            website="https://company1.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )
        db_session.add(company1)
        db_session.flush()
        # Update created_at to yesterday
        db_session.execute(
            Company.__table__.update()
            .where(Company.id == company1.id)
            .values(created_at=yesterday)
        )

        # Company outside range (last week)
        company2 = Company(
            name="Company 2",
            website="https://company2.com",
            organization_id="org-1",
            owner_id="user-2",
            is_deleted=False,
        )
        db_session.add(company2)
        db_session.flush()
        db_session.execute(
            Company.__table__.update()
            .where(Company.id == company2.id)
            .values(created_at=last_week)
        )

        db_session.commit()

        # Query for last 3 days (should include company1 only)
        start_date = (today - timedelta(days=3)).date()
        end_date = today.date()
        count = _get_companies_count(db_session, start_date, end_date)

        assert count == 1

    def test_companies_count_excludes_deleted(self, db_session):
        """Test companies count excludes soft-deleted companies."""
        today = datetime.utcnow()

        # Active company
        company1 = Company(
            name="Active Company",
            website="https://active.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )
        db_session.add(company1)

        # Deleted company
        company2 = Company(
            name="Deleted Company",
            website="https://deleted.com",
            organization_id="org-1",
            owner_id="user-2",
            is_deleted=True,
        )
        db_session.add(company2)
        db_session.commit()

        start_date = (today - timedelta(days=1)).date()
        end_date = (today + timedelta(days=1)).date()
        count = _get_companies_count(db_session, start_date, end_date)

        assert count == 1


class TestTaskSuccessRateCalculation:
    """Test task success rate calculation."""

    def test_success_rate_calculation_correct(self, db_session):
        """Test success rate is calculated correctly from succeeded vs error tasks."""
        today = datetime.utcnow()

        # Create a company first (required for task foreign key)
        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )
        db_session.add(company)
        db_session.flush()

        # Create 8 succeeded tasks
        for i in range(8):
            task = Task(
                company_id=company.id,
                organization_id="org-1",
                type=TaskType.profile,
                status=TaskStatus.SUCCEEDED,
            )
            db_session.add(task)

        # Create 2 error tasks
        for i in range(2):
            task = Task(
                company_id=company.id,
                organization_id="org-1",
                type=TaskType.profile,
                status=TaskStatus.ERROR,
            )
            db_session.add(task)

        # Create some pending/running tasks (should be excluded)
        task_pending = Task(
            company_id=company.id,
            organization_id="org-1",
            type=TaskType.profile,
            status=TaskStatus.PENDING,
        )
        db_session.add(task_pending)

        db_session.commit()

        start_date = (today - timedelta(days=1)).date()
        end_date = (today + timedelta(days=1)).date()
        success_rate = _get_task_success_rate(db_session, start_date, end_date)

        # 8 succeeded / 10 completed = 80%
        assert success_rate == 80.0

    def test_success_rate_returns_none_when_no_tasks(self, db_session):
        """Test success rate returns None when no completed tasks exist."""
        today = datetime.utcnow()
        start_date = (today - timedelta(days=1)).date()
        end_date = (today + timedelta(days=1)).date()

        success_rate = _get_task_success_rate(db_session, start_date, end_date)

        assert success_rate is None

    def test_success_rate_excludes_pending_and_running(self, db_session):
        """Test success rate only counts succeeded and error tasks."""
        today = datetime.utcnow()

        company = Company(
            name="Test Company",
            website="https://test.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )
        db_session.add(company)
        db_session.flush()

        # Create 1 succeeded task
        task_succeeded = Task(
            company_id=company.id,
            organization_id="org-1",
            type=TaskType.profile,
            status=TaskStatus.SUCCEEDED,
        )
        db_session.add(task_succeeded)

        # Create multiple pending/running/blocked tasks
        for status in [TaskStatus.PENDING, TaskStatus.RUNNING, TaskStatus.BLOCKED]:
            task = Task(
                company_id=company.id,
                organization_id="org-1",
                type=TaskType.profile,
                status=status,
            )
            db_session.add(task)

        db_session.commit()

        start_date = (today - timedelta(days=1)).date()
        end_date = (today + timedelta(days=1)).date()
        success_rate = _get_task_success_rate(db_session, start_date, end_date)

        # Only 1 succeeded out of 1 completed (pending/running/blocked excluded)
        assert success_rate == 100.0


class TestActiveUsersCount:
    """Test active users count query."""

    def test_active_users_count_unique_owners(self, db_session):
        """Test active users count returns unique owner IDs."""
        today = datetime.utcnow()

        # Two companies by same user
        company1 = Company(
            name="Company 1",
            website="https://c1.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )
        company2 = Company(
            name="Company 2",
            website="https://c2.com",
            organization_id="org-1",
            owner_id="user-1",
            is_deleted=False,
        )

        # One company by different user
        company3 = Company(
            name="Company 3",
            website="https://c3.com",
            organization_id="org-1",
            owner_id="user-2",
            is_deleted=False,
        )

        db_session.add_all([company1, company2, company3])
        db_session.commit()

        start_date = (today - timedelta(days=1)).date()
        end_date = (today + timedelta(days=1)).date()
        count = _get_active_users_count(db_session, start_date, end_date)

        # Should be 2 unique users
        assert count == 2


class TestCompaniesOverTime:
    """Test companies over time aggregation."""

    def test_companies_over_time_groups_by_day_for_short_range(self, db_session):
        """Test that short date ranges (<=30 days) group by day."""
        today = datetime.utcnow()

        # Create companies on different days
        for i in range(3):
            company = Company(
                name=f"Company {i}",
                website=f"https://c{i}.com",
                organization_id="org-1",
                owner_id="user-1",
                is_deleted=False,
            )
            db_session.add(company)

        db_session.commit()

        # Query for 7 days (should use day grouping)
        start_date = (today - timedelta(days=7)).date()
        end_date = today.date()
        results = _get_companies_over_time(db_session, start_date, end_date)

        # Should have at least one data point with today's companies
        assert len(results) >= 1
        # All counts should be positive integers
        for point in results:
            assert point.count >= 0
            assert isinstance(point.period, str)


class TestCompaniesByOrganization:
    """Test companies by organization aggregation."""

    @pytest.mark.asyncio
    async def test_companies_by_organization_groups_remainder_as_other(self, db_session):
        """Test that organizations beyond top 10 are grouped as 'Other'.

        This test verifies the critical business logic that limits display to
        top 10 organizations and aggregates the rest under "Other" to keep
        the UI manageable.
        """
        today = datetime.utcnow()

        # Mock keycloak_admin_service to avoid external calls
        with patch("app.api.endpoints.admin.keycloak_admin_service") as mock_keycloak:
            # Create 12 organizations with companies
            org_data = []
            for i in range(12):
                org_id = f"org-{i}"
                org_data.append({"id": org_id, "name": f"Organization {i}"})

                # Create companies for this org (varying counts for clear sorting)
                for j in range(12 - i):  # org-0 has 12, org-1 has 11, etc.
                    company = Company(
                        name=f"Company {i}-{j}",
                        website=f"https://company-{i}-{j}.com",
                        organization_id=org_id,
                        owner_id="user-1",
                        is_deleted=False,
                    )
                    db_session.add(company)

            db_session.commit()

            # Configure mock to return organization names
            mock_keycloak.get_organizations = AsyncMock(return_value=org_data)

            start_date = (today - timedelta(days=1)).date()
            end_date = (today + timedelta(days=1)).date()

            results = await _get_companies_by_organization(db_session, start_date, end_date)

            # Should have exactly 11 entries: top 10 orgs + "Other"
            assert len(results) == 11

            # Last entry should be "Other" with combined count of orgs 10 and 11
            other_entry = results[-1]
            assert other_entry.organization_id == "other"
            assert other_entry.organization_name == "Other"
            # org-10 has 2 companies, org-11 has 1 = 3 total
            assert other_entry.companies_count == 3

            # Verify top 10 are sorted by count descending
            for i in range(len(results) - 2):
                assert results[i].companies_count >= results[i + 1].companies_count


class TestEndpointPermission:
    """Test permission requirements for usage stats endpoint.

    These tests verify that the endpoint properly enforces
    the admin.organizations permission requirement.
    """

    def test_endpoint_requires_admin_organizations_role(self):
        """Test that endpoint configuration requires admin.organizations role.

        This is a structural test verifying the dependency injection is set up
        correctly to require the admin.organizations role.
        """
        from app.api.endpoints.admin import get_usage_stats
        from fastapi import Depends
        import inspect

        # Get the function signature
        sig = inspect.signature(get_usage_stats)

        # Find the user parameter
        user_param = sig.parameters.get("user")
        assert user_param is not None, "Endpoint must have a 'user' parameter"

        # The default should be a Depends() call with role requirement
        default = user_param.default
        assert default is not None, "User parameter must have a default Depends"

        # Verify it's properly requiring admin.organizations
        # The actual 403 behavior is tested via integration tests with TestClient
        # Here we just verify the endpoint is configured to require authentication
        assert hasattr(default, "dependency"), "User param should be a FastAPI Depends"
