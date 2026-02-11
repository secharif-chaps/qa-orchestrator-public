"""Tests for token transaction history endpoints.

Tests the GET /organizations/{org_id}/tokens/history endpoint with:
- Basic transaction history retrieval
- Filtering by transaction type, reference type, date range
- Pagination
- Permission checks (own org vs admin)
"""

import pytest
from datetime import datetime, timezone, timedelta

from app.models.organization import (
    Organization,
    TokenTransaction,
    TransactionType,
    ReferenceType,
)
from app.core.keycloak import OIDCUser


@pytest.fixture
def test_org_id():
    """Test organization ID (valid UUID v4 format)."""
    return "12345678-1234-4234-a234-123456789abc"


@pytest.fixture
def test_user():
    """Create a test user with organization context."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub="test-user-123",
        preferred_username="testuser",
        email="test@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=["Test Org", {"Test Org": {"id": "12345678-1234-4234-a234-123456789abc"}}],
        enabled_modules=["screen"],
        realm_access={"roles": ["company.view", "organization.read"]},
    )


@pytest.fixture
def admin_user():
    """Create an admin user with admin.organizations role."""
    now = int(datetime.now(timezone.utc).timestamp())
    return OIDCUser(
        sub="admin-user-123",
        preferred_username="adminuser",
        email="admin@example.com",
        email_verified=True,
        iat=now,
        exp=now + 3600,
        organization=["Admin Org", {"Admin Org": {"id": "87654321-4321-4321-8321-cba987654321"}}],
        enabled_modules=["screen"],
        realm_access={"roles": ["admin.organizations"]},
    )


@pytest.fixture
async def setup_test_data(global_db_session, test_org_id):
    """Set up test organization and transactions."""
    # Create organization
    org = Organization(organization_id=test_org_id, token_balance=1000)
    global_db_session.add(org)
    await global_db_session.commit()

    # Create various transactions
    now = datetime.now(timezone.utc)
    transactions = [
        # Recent add transaction
        TokenTransaction(
            organization_id=test_org_id,
            amount=500,
            balance_after=1500,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_at=now - timedelta(hours=1),
            created_by="admin-user-123",
        ),
        # Company creation (consume)
        TokenTransaction(
            organization_id=test_org_id,
            amount=-35,
            balance_after=1465,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.company,
            reference_id="company-1",
            created_at=now - timedelta(hours=2),
            created_by="test-user-123",
        ),
        # CSV import (consume)
        TokenTransaction(
            organization_id=test_org_id,
            amount=-350,
            balance_after=1115,
            transaction_type=TransactionType.consume,
            reference_type=ReferenceType.csv_import,
            reference_id="import-batch-1",
            created_at=now - timedelta(days=1),
            created_by="test-user-123",
        ),
        # Old add transaction
        TokenTransaction(
            organization_id=test_org_id,
            amount=1000,
            balance_after=1000,
            transaction_type=TransactionType.add,
            reference_type=ReferenceType.manual,
            created_at=now - timedelta(days=7),
            created_by="admin-user-123",
        ),
        # Adjustment
        TokenTransaction(
            organization_id=test_org_id,
            amount=100,
            balance_after=1100,
            transaction_type=TransactionType.adjustment,
            reference_type=ReferenceType.system,
            created_at=now - timedelta(days=5),
            created_by="system",
        ),
    ]

    for tx in transactions:
        global_db_session.add(tx)

    await global_db_session.commit()

    return {
        "organization": org,
        "transactions": transactions,
        "count": len(transactions),
    }


def test_get_transaction_history_success(
    client, test_org_id, test_user, setup_test_data
):
    """Test successfully retrieving transaction history."""
    # Mock the dependencies
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    # Create mock organization context
    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    # Override dependencies - use the actual dependency object from the mock
    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Make request
    response = client.get(f"/api/organizations/{test_org_id}/tokens/history")

    assert response.status_code == 200
    data = response.json()

    # Verify pagination structure
    assert "items" in data
    assert "total" in data
    assert "page" in data
    assert "size" in data
    assert "pages" in data

    # Verify data
    assert data["total"] == setup_test_data["count"]
    assert len(data["items"]) <= data["size"]

    # Verify items are ordered by created_at descending (most recent first)
    if len(data["items"]) > 1:
        for i in range(len(data["items"]) - 1):
            current = datetime.fromisoformat(
                data["items"][i]["created_at"].replace("Z", "+00:00")
            )
            next_item = datetime.fromisoformat(
                data["items"][i + 1]["created_at"].replace("Z", "+00:00")
            )
            assert current >= next_item


def test_filter_by_transaction_type(client, test_org_id, test_user, setup_test_data):
    """Test filtering by transaction type."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Filter for 'consume' transactions
    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={"transaction_type": "consume"},
    )

    assert response.status_code == 200
    data = response.json()

    # Verify all returned items are 'consume' type
    for item in data["items"]:
        assert item["transaction_type"] == "consume"

    # Should have 2 consume transactions
    assert data["total"] == 2


def test_filter_by_reference_type(client, test_org_id, test_user, setup_test_data):
    """Test filtering by reference type."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Filter for 'manual' reference type
    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={"reference_type": "manual"},
    )

    assert response.status_code == 200
    data = response.json()

    # Verify all returned items are 'manual' reference type
    for item in data["items"]:
        assert item["reference_type"] == "manual"

    # Should have 2 manual transactions
    assert data["total"] == 2


def test_filter_by_date_range(client, test_org_id, test_user, setup_test_data):
    """Test filtering by date range."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Filter for last 3 days
    now = datetime.now(timezone.utc)
    date_from = now - timedelta(days=3)

    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={"date_from": date_from.isoformat()},
    )

    assert response.status_code == 200
    data = response.json()

    # Should have 3 transactions in the last 3 days
    assert data["total"] == 3

    # Verify all dates are after date_from
    for item in data["items"]:
        item_date_str = item["created_at"].replace("Z", "+00:00")
        item_date = datetime.fromisoformat(item_date_str)

        # Ensure both dates are timezone-aware for comparison
        if item_date.tzinfo is None:
            item_date = item_date.replace(tzinfo=timezone.utc)
        if date_from.tzinfo is None:
            date_from = date_from.replace(tzinfo=timezone.utc)

        assert item_date >= date_from


def test_pagination(client, test_org_id, test_user, setup_test_data):
    """Test pagination works correctly."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Page 1 with size 2
    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={"page": 1, "size": 2},
    )

    assert response.status_code == 200
    data = response.json()

    assert data["page"] == 1
    assert data["size"] == 2
    assert len(data["items"]) == 2
    assert data["total"] == setup_test_data["count"]
    assert data["pages"] == 3  # 5 items / 2 per page = 3 pages

    # Page 2 with size 2
    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={"page": 2, "size": 2},
    )

    assert response.status_code == 200
    data = response.json()

    assert data["page"] == 2
    assert len(data["items"]) == 2


def test_permission_denied_different_org(
    client, test_org_id, test_user, setup_test_data
):
    """Test that users cannot access other organizations' history."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    # Create context for a different organization
    org_context = OrganizationContext(
        organization_id="11111111-2222-4333-8444-555555555555",
        organization_name="Different Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Try to access test_org_id's history (should fail)
    response = client.get(f"/api/organizations/{test_org_id}/tokens/history")

    assert response.status_code == 403
    assert "Access denied" in response.json()["detail"]


def test_admin_can_access_any_org(client, test_org_id, admin_user, setup_test_data):
    """Test that admins can access any organization's history."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    # Admin's own org is different
    org_context = OrganizationContext(
        organization_id="87654321-4321-4321-8321-cba987654321",
        organization_name="Admin Org",
        user_id=admin_user.sub,
        username=admin_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_admin_user():
        return admin_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_admin_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Admin should be able to access test_org_id's history
    response = client.get(f"/api/organizations/{test_org_id}/tokens/history")

    assert response.status_code == 200
    data = response.json()
    assert data["total"] == setup_test_data["count"]


async def test_empty_history(client, test_user, global_db_session):
    """Test querying history for organization with no transactions."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    # Create a new org with no transactions
    empty_org_id = "99999999-8888-4777-8666-555555555555"
    org = Organization(organization_id=empty_org_id, token_balance=0)
    global_db_session.add(org)
    await global_db_session.commit()

    org_context = OrganizationContext(
        organization_id=empty_org_id,
        organization_name="Empty Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    response = client.get(f"/api/organizations/{empty_org_id}/tokens/history")

    assert response.status_code == 200
    data = response.json()

    assert data["total"] == 0
    assert len(data["items"]) == 0
    assert data["pages"] == 0


def test_combined_filters(client, test_org_id, test_user, setup_test_data):
    """Test using multiple filters together."""
    from tests.conftest import _mock_idp
    from app.api.endpoints.tokens import get_user_organization
    from app.core.organization import OrganizationContext

    org_context = OrganizationContext(
        organization_id=test_org_id,
        organization_name="Test Org",
        user_id=test_user.sub,
        username=test_user.preferred_username,
        enabled_modules=["screen"],
    )

    def get_test_user():
        return test_user

    client.app.dependency_overrides[_mock_idp.get_current_user.return_value] = (
        get_test_user
    )
    client.app.dependency_overrides[get_user_organization] = lambda: org_context

    # Filter for consume transactions with company reference type
    response = client.get(
        f"/api/organizations/{test_org_id}/tokens/history",
        params={
            "transaction_type": "consume",
            "reference_type": "company",
        },
    )

    assert response.status_code == 200
    data = response.json()

    # Should have exactly 1 matching transaction
    assert data["total"] == 1
    assert len(data["items"]) == 1

    item = data["items"][0]
    assert item["transaction_type"] == "consume"
    assert item["reference_type"] == "company"
    assert item["reference_id"] == "company-1"
    assert item["amount"] == -35


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
