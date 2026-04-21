"""Tests for internal API endpoints (service-to-service).

Tests the folder access control endpoints used by the screen backend:
- GET /{org_id}/folders/accessible-company-ids
- GET /{org_id}/folders/company-access/{company_id}
- GET /{org_id}/folders/company/{company_id}/folder-info
"""

import uuid

import pytest

from app.core.internal_jwt import InternalTokenPayload, get_internal_token
from app.database import get_global_db
from app.models.folder import Folder, FolderItem, FolderShare, ItemType, ShareRole


# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------

ORG_ID = "12345678-1234-4234-a234-123456789abc"
USER_ID = "user-123"
USERNAME = "testuser"
OTHER_USER_ID = "other-user-456"
OTHER_USERNAME = "otheruser"


def _make_token_payload(
    org_id: str = ORG_ID,
    user_id: str = USER_ID,
    username: str = USERNAME,
    roles: list[str] | None = None,
) -> InternalTokenPayload:
    return InternalTokenPayload(
        sub=user_id,
        username=username,
        org_id=org_id,
        org_name="Test Org",
        roles=roles or ["company.view"],
    )


@pytest.fixture
async def seed_folders(global_db_session):
    """Create folders with items and shares for testing access control."""
    # Folder owned by USER_ID with 2 companies
    folder_owned = Folder(
        organization_id=ORG_ID,
        owner_id=USER_ID,
        owner=USERNAME,
        name="My Folder",
        tags=[],
    )
    global_db_session.add(folder_owned)
    await global_db_session.flush()

    item1 = FolderItem(
        folder_id=folder_owned.id,
        item_id="100",
        item_type=ItemType.company,
    )
    item2 = FolderItem(
        folder_id=folder_owned.id,
        item_id="200",
        item_type=ItemType.company,
    )
    global_db_session.add_all([item1, item2])

    # Folder owned by OTHER_USER_ID, shared with USER_ID as reader
    folder_shared = Folder(
        organization_id=ORG_ID,
        owner_id=OTHER_USER_ID,
        owner=OTHER_USERNAME,
        name="Shared Folder",
        tags=[],
    )
    global_db_session.add(folder_shared)
    await global_db_session.flush()

    item3 = FolderItem(
        folder_id=folder_shared.id,
        item_id="300",
        item_type=ItemType.company,
    )
    global_db_session.add(item3)

    share = FolderShare(
        folder_id=folder_shared.id,
        user_id=USER_ID,
        user_username=USERNAME,
        role=ShareRole.reader,
    )
    global_db_session.add(share)

    # Folder owned by OTHER_USER_ID, NOT shared — USER_ID should NOT access
    folder_private = Folder(
        organization_id=ORG_ID,
        owner_id=OTHER_USER_ID,
        owner=OTHER_USERNAME,
        name="Private Folder",
        tags=[],
    )
    global_db_session.add(folder_private)
    await global_db_session.flush()

    item_private = FolderItem(
        folder_id=folder_private.id,
        item_id="999",
        item_type=ItemType.company,
    )
    global_db_session.add(item_private)

    await global_db_session.commit()

    return {
        "folder_owned": folder_owned,
        "folder_shared": folder_shared,
        "folder_private": folder_private,
    }


# ---------------------------------------------------------------------------
# Tests: get_accessible_company_ids
# ---------------------------------------------------------------------------


class TestGetAccessibleCompanyIds:
    """Tests for GET /{org_id}/folders/accessible-company-ids."""

    @pytest.mark.asyncio
    async def test_returns_owned_and_shared_company_ids(self, client, seed_folders):
        """User should see companies from owned folders and shared folders."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/accessible-company-ids")

        assert response.status_code == 200
        company_ids = response.json()
        assert 100 in company_ids
        assert 200 in company_ids
        assert 300 in company_ids
        # Private folder company should NOT be included
        assert 999 not in company_ids

    @pytest.mark.asyncio
    async def test_returns_empty_for_user_without_folders(self, client, seed_folders):
        """User with no folders should get empty list."""
        payload = _make_token_payload(user_id="no-folders-user", username="nofolder")
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/accessible-company-ids")

        assert response.status_code == 200
        assert response.json() == []

    @pytest.mark.asyncio
    async def test_rejects_mismatched_org_id(self, client, seed_folders):
        """Should return 403 when path org_id doesn't match token org_id."""
        payload = _make_token_payload(org_id="different-org-id")
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        wrong_org = str(uuid.uuid4())
        response = await client.get(f"/api/internal/organizations/{wrong_org}/folders/accessible-company-ids")

        assert response.status_code == 403


# ---------------------------------------------------------------------------
# Tests: check_company_access
# ---------------------------------------------------------------------------


class TestCheckCompanyAccess:
    """Tests for GET /{org_id}/folders/company-access/{company_id}."""

    @pytest.mark.asyncio
    async def test_access_to_owned_company(self, client, seed_folders):
        """User should have access to companies in their own folders."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company-access/100")

        assert response.status_code == 200
        assert response.json() is True

    @pytest.mark.asyncio
    async def test_access_to_shared_company(self, client, seed_folders):
        """User should have access to companies in shared folders."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company-access/300")

        assert response.status_code == 200
        assert response.json() is True

    @pytest.mark.asyncio
    async def test_no_access_to_private_company(self, client, seed_folders):
        """User should NOT have access to companies in non-shared folders."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company-access/999")

        assert response.status_code == 200
        assert response.json() is False

    @pytest.mark.asyncio
    async def test_no_access_to_nonexistent_company(self, client, seed_folders):
        """User should NOT have access to companies that don't exist."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company-access/99999")

        assert response.status_code == 200
        assert response.json() is False

    @pytest.mark.asyncio
    async def test_rejects_mismatched_org_id(self, client, seed_folders):
        """Should return 403 when path org_id doesn't match token org_id."""
        payload = _make_token_payload(org_id="different-org-id")
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        wrong_org = str(uuid.uuid4())
        response = await client.get(f"/api/internal/organizations/{wrong_org}/folders/company-access/100")

        assert response.status_code == 403


# ---------------------------------------------------------------------------
# Tests: get_company_folder_info
# ---------------------------------------------------------------------------


class TestGetCompanyFolderInfo:
    """Tests for GET /{org_id}/folders/company/{company_id}/folder-info."""

    @pytest.mark.asyncio
    async def test_returns_folder_info_for_existing_company(self, client, seed_folders):
        """Should return folder_id and folder_name for a company in a folder."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company/100/folder-info")

        assert response.status_code == 200
        data = response.json()
        assert data["folder_id"] == str(seed_folders["folder_owned"].id)
        assert "folder_name" in data

    @pytest.mark.asyncio
    async def test_returns_null_for_nonexistent_company(self, client, seed_folders):
        """Should return null when company is not in any folder."""
        payload = _make_token_payload()
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        response = await client.get(f"/api/internal/organizations/{ORG_ID}/folders/company/99999/folder-info")

        assert response.status_code == 200
        assert response.json() is None

    @pytest.mark.asyncio
    async def test_rejects_mismatched_org_id(self, client, seed_folders):
        """Should return 403 when path org_id doesn't match token org_id."""
        payload = _make_token_payload(org_id="different-org-id")
        client.app.dependency_overrides[get_internal_token] = lambda: payload

        wrong_org = str(uuid.uuid4())
        response = await client.get(f"/api/internal/organizations/{wrong_org}/folders/company/100/folder-info")

        assert response.status_code == 403
