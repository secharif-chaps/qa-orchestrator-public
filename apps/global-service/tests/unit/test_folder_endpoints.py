"""Tests for folder API endpoints.

Tests cover:
- Folder CRUD (create, list, get, update, patch, delete, restore)
- Folder sharing (create, list, update, delete shares)
- User favorites (add, remove)
- Folder items (add, remove, move)
- Folder items pagination, sorting, and name filtering
- Access control (owner-only operations, reader/writer permissions)

All tests use SQLite in-memory database and mock external dependencies
(Keycloak auth, backend_client for company enrichment, keycloak_admin_service).
"""

from datetime import UTC
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest

from app.core.organization import OrganizationContext, get_user_organization
from app.models.folder import Folder, FolderItem, FolderShare, ItemType, ShareRole
from app.models.user_folder_favorite import UserFolderFavorite

# Import the mock idp to access the dependency object for overrides
from tests.conftest import _mock_idp

# ============================================================================
# Constants
# ============================================================================

TEST_ORG_ID = "test-org-123"
OWNER_USER_ID = "owner-user-id"
OWNER_USERNAME = "owner_user"
OTHER_USER_ID = "other-user-id"
OTHER_USERNAME = "other_user"
READER_USER_ID = "reader-user-id"
READER_USERNAME = "reader_user"
WRITER_USER_ID = "writer-user-id"
WRITER_USERNAME = "writer_user"


# ============================================================================
# Helpers
# ============================================================================


def _make_org_context(
    user_id: str = OWNER_USER_ID,
    username: str = OWNER_USERNAME,
    org_id: str = TEST_ORG_ID,
) -> OrganizationContext:
    return OrganizationContext(
        organization_id=org_id,
        organization_name="Test Org",
        user_id=user_id,
        username=username,
        enabled_modules=["Screen", "Target"],
    )


def _make_mock_user(roles: list[str] | None = None):
    """Create a mock OIDCUser with realm_access roles."""
    user = MagicMock()
    user.realm_access = {"roles": roles or []}
    return user


def _override_auth(app, user_id=OWNER_USER_ID, username=OWNER_USERNAME, org_id=TEST_ORG_ID, roles=None):
    """Apply auth dependency overrides on the app.

    Overrides both the Keycloak user dependency and the organization context.
    """
    if roles is None:
        roles = ["organization.write", "organization.read", "company.create"]

    mock_user = _make_mock_user(roles=roles)
    org_ctx = _make_org_context(user_id=user_id, username=username, org_id=org_id)

    # Override the keycloak user dependency (same MagicMock returned by idp.get_current_user())
    app.dependency_overrides[_mock_idp.get_current_user.return_value] = lambda: mock_user
    # Override the organization context dependency
    app.dependency_overrides[get_user_organization] = lambda: org_ctx


# ============================================================================
# Fixtures
# ============================================================================


@pytest.fixture
async def folder_client(client):
    """Client with auth overrides for the folder owner."""
    _override_auth(client.app)
    yield client


@pytest.fixture
async def seed_folder(global_db_session):
    """Create a folder in the DB and return it."""
    folder = Folder(
        id=uuid4(),
        organization_id=TEST_ORG_ID,
        owner_id=OWNER_USER_ID,
        owner=OWNER_USERNAME,
        name="Test Folder",
        color="blue",
        icon="fa-folder",
        tags=["tag1", "tag2"],
    )
    global_db_session.add(folder)
    await global_db_session.commit()
    await global_db_session.refresh(folder)
    return folder


@pytest.fixture
async def seed_folder_with_share(global_db_session, seed_folder):
    """Create a folder with a reader share."""
    share = FolderShare(
        id=uuid4(),
        folder_id=seed_folder.id,
        user_id=READER_USER_ID,
        user_username=READER_USERNAME,
        role=ShareRole.reader,
    )
    global_db_session.add(share)
    await global_db_session.commit()
    await global_db_session.refresh(share)
    return seed_folder, share


@pytest.fixture
async def seed_folder_with_writer(global_db_session, seed_folder):
    """Create a folder with a writer share."""
    share = FolderShare(
        id=uuid4(),
        folder_id=seed_folder.id,
        user_id=WRITER_USER_ID,
        user_username=WRITER_USERNAME,
        role=ShareRole.writer,
    )
    global_db_session.add(share)
    await global_db_session.commit()
    await global_db_session.refresh(share)
    return seed_folder, share


SEED_ITEM_ID = str(uuid4())  # UUID-formatted item_id for endpoint compatibility


@pytest.fixture
async def seed_folder_with_item(global_db_session, seed_folder):
    """Create a folder with a company item."""
    item = FolderItem(
        id=uuid4(),
        folder_id=seed_folder.id,
        item_id=SEED_ITEM_ID,
        item_type=ItemType.company,
        owner=OWNER_USERNAME,
        position=0,
    )
    global_db_session.add(item)
    await global_db_session.commit()
    await global_db_session.refresh(item)
    return seed_folder, item


@pytest.fixture
async def seed_folder_with_many_items(global_db_session, seed_folder):
    """Create a folder with 15 company items for pagination/sort/filter tests.

    Items are named 'Alpha', 'Beta', 'Charlie', … 'test-Alpha', 'test-Beta', …
    to allow deterministic assertions on name sort and name filter.
    Company IDs are simple integers 1-15.
    """
    from datetime import datetime

    names = [
        "Alpha",
        "Beta",
        "Charlie",
        "Delta",
        "Echo",
        "Foxtrot",
        "Golf",
        "Hotel",
        "India",
        "Juliet",
        "test-Kilo",
        "test-Lima",
        "test-Mike",
        "November",
        "Oscar",
    ]
    items = []
    for i, name in enumerate(names, start=1):
        item = FolderItem(
            id=uuid4(),
            folder_id=seed_folder.id,
            item_id=str(i),
            item_type=ItemType.company,
            owner=OWNER_USERNAME,
            position=i,
            added_at=datetime(2024, 1, i, tzinfo=UTC),
        )
        global_db_session.add(item)
        items.append((i, name))

    await global_db_session.commit()

    # Build the CompanyInfo mock map that get_companies_by_ids will return
    from app.services.backend_client import CompanyInfo

    company_map = {
        i: CompanyInfo(
            id=i,
            name=name,
            website=f"https://{name.lower()}.example.com",
            is_deleted=False,
            owner_username=OWNER_USERNAME,
            created_at="2024-01-01T00:00:00",
        )
        for i, name in items
    }
    return seed_folder, company_map


@pytest.fixture
async def seed_folder_with_mixed_positions(global_db_session, seed_folder):
    """Create a folder with 3 positioned items (positions 1, 2, 3) and 2 null-position items.

    Used to verify nullslast/nullsfirst behaviour on the position sort.
    """
    from datetime import datetime

    from app.services.backend_client import CompanyInfo

    # (company_id, name, position)
    items_data = [
        (1, "First", 1),
        (2, "Second", 2),
        (3, "Third", 3),
        (4, "NoPos-A", None),
        (5, "NoPos-B", None),
    ]

    for company_id, _name, position in items_data:
        item = FolderItem(
            id=uuid4(),
            folder_id=seed_folder.id,
            item_id=str(company_id),
            item_type=ItemType.company,
            owner=OWNER_USERNAME,
            position=position,
            added_at=datetime(2024, 1, company_id, tzinfo=UTC),
        )
        global_db_session.add(item)

    await global_db_session.commit()

    company_map = {
        company_id: CompanyInfo(
            id=company_id,
            name=name,
            website=f"https://{name.lower()}.example.com",
            is_deleted=False,
            owner_username=OWNER_USERNAME,
            created_at="2024-01-01T00:00:00",
        )
        for company_id, name, _position in items_data
    }
    return seed_folder, company_map


@pytest.fixture
async def seed_folder_with_archived_items(global_db_session, seed_folder):
    """Create a folder with a mix of active and archived company items.

    5 archived items named 'Archived-{n}', 5 active items named 'Active-{n}'.
    """
    from datetime import datetime

    from app.services.backend_client import CompanyInfo

    items_data = [(i, f"Archived-{i}", True) for i in range(1, 6)] + [(i, f"Active-{i}", False) for i in range(6, 11)]

    for company_id, _name, _is_deleted in items_data:
        item = FolderItem(
            id=uuid4(),
            folder_id=seed_folder.id,
            item_id=str(company_id),
            item_type=ItemType.company,
            owner=OWNER_USERNAME,
            position=company_id,
            added_at=datetime(2024, 1, company_id, tzinfo=UTC),
        )
        global_db_session.add(item)

    await global_db_session.commit()

    company_map = {
        company_id: CompanyInfo(
            id=company_id,
            name=name,
            website=f"https://{name.lower()}.example.com",
            is_deleted=is_deleted,
            owner_username=OWNER_USERNAME,
            created_at="2024-01-01T00:00:00",
        )
        for company_id, name, is_deleted in items_data
    }
    return seed_folder, company_map


# ============================================================================
# CRUD Tests
# ============================================================================


class TestCreateFolder:
    """POST /api/folders/"""

    async def test_create_folder_success(self, folder_client):
        response = await folder_client.post(
            "/api/folders/",
            json={"name": "New Folder", "color": "green", "icon": "fa-star", "tags": ["a", "b"]},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["name"] == "New Folder"
        assert data["color"] == "green"
        assert data["icon"] == "fa-star"
        assert data["is_owner"] is True
        assert data["share_role"] == "owner"
        assert data["is_deleted"] is False

    async def test_create_folder_minimal(self, folder_client):
        """Create folder with only required field (name)."""
        response = await folder_client.post(
            "/api/folders/",
            json={"name": "Minimal"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["name"] == "Minimal"

    async def test_create_folder_missing_name(self, folder_client):
        """Missing name should return 422."""
        response = await folder_client.post("/api/folders/", json={})
        assert response.status_code == 422


class TestListFolders:
    """GET /api/folders/"""

    async def test_list_folders_empty(self, folder_client):
        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        body = response.json()
        assert body["data"] == []
        assert body["pagination"]["total"] == 0

    async def test_list_folders_returns_owned(self, folder_client, seed_folder):
        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        body = response.json()
        assert len(body["data"]) == 1
        assert body["data"][0]["name"] == "Test Folder"

    async def test_list_folders_excludes_deleted(self, folder_client, seed_folder, global_db_session):
        """Deleted folders should not appear in default list."""
        seed_folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        assert len(response.json()["data"]) == 0

    async def test_list_folders_archived_flag(self, folder_client, seed_folder, global_db_session):
        """archived=true should only return deleted folders."""
        seed_folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/", params={"archived": "true"})
        assert response.status_code == 200
        body = response.json()
        assert len(body["data"]) == 1
        assert body["data"][0]["is_deleted"] is True

    async def test_list_folders_favorites_filter(self, folder_client, seed_folder, global_db_session):
        """favorites=true should only return favorited folders."""
        fav = UserFolderFavorite(id=uuid4(), folder_id=seed_folder.id, user_id=OWNER_USER_ID)
        global_db_session.add(fav)
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/", params={"favorites": "true"})
        assert response.status_code == 200
        body = response.json()
        assert len(body["data"]) == 1

    async def test_list_folders_other_org_excluded(self, folder_client, global_db_session):
        """Folders from other organizations should not be listed."""
        other_folder = Folder(
            id=uuid4(),
            organization_id="other-org-999",
            owner_id=OWNER_USER_ID,
            owner=OWNER_USERNAME,
            name="Other Org Folder",
            tags=[],
        )
        global_db_session.add(other_folder)
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        assert len(response.json()["data"]) == 0


class TestListFoldersSortAndSearch:
    """GET /api/folders/ — name search and sort_by/sort_order (TAR-1446)."""

    @pytest.fixture
    async def seed_sortable_folders(self, global_db_session):
        """Create 3 folders with distinct names and staggered timestamps."""
        from datetime import UTC, datetime, timedelta

        base = datetime(2026, 1, 1, tzinfo=UTC)
        folders = [
            Folder(
                id=uuid4(),
                organization_id=TEST_ORG_ID,
                owner_id=OWNER_USER_ID,
                owner=OWNER_USERNAME,
                name=name,
                tags=[],
                created_at=base + timedelta(days=i),
                updated_at=base + timedelta(days=updated_offset),
            )
            for i, (name, updated_offset) in enumerate(
                [
                    ("Alpha test project", 30),  # oldest created, most recently updated
                    ("Beta initiative", 20),
                    ("Gamma TEST", 10),  # newest created, least recently updated
                ]
            )
        ]
        for f in folders:
            global_db_session.add(f)
        await global_db_session.commit()
        return folders

    async def test_name_search_filters_case_insensitive(self, folder_client, seed_sortable_folders):
        """?name=test matches both 'Alpha test project' and 'Gamma TEST'."""
        response = await folder_client.get("/api/folders/", params={"name": "test"})
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert set(names) == {"Alpha test project", "Gamma TEST"}

    async def test_name_search_partial_match(self, folder_client, seed_sortable_folders):
        """Partial substring returns only matching folders."""
        response = await folder_client.get("/api/folders/", params={"name": "init"})
        assert response.status_code == 200
        data = response.json()["data"]
        assert len(data) == 1
        assert data[0]["name"] == "Beta initiative"

    async def test_name_search_no_match(self, folder_client, seed_sortable_folders):
        response = await folder_client.get("/api/folders/", params={"name": "nomatch"})
        assert response.status_code == 200
        assert response.json()["data"] == []

    async def test_name_search_escapes_like_wildcards(self, folder_client, global_db_session):
        """User-supplied % and _ must be matched literally, not as SQL wildcards."""
        for name in [
            "Plain folder",
            "Report 50% off",
            "snake_case folder",
            "100%_weird",
        ]:
            global_db_session.add(
                Folder(
                    id=uuid4(),
                    organization_id=TEST_ORG_ID,
                    owner_id=OWNER_USER_ID,
                    owner=OWNER_USERNAME,
                    name=name,
                    tags=[],
                )
            )
        await global_db_session.commit()

        # "%%" must match "50%" literally (not behave as a wildcard that matches everything)
        response = await folder_client.get("/api/folders/", params={"name": "%"})
        assert response.status_code == 200
        names = {f["name"] for f in response.json()["data"]}
        assert names == {"Report 50% off", "100%_weird"}

        # "_" must match an underscore literally (not any single character)
        response = await folder_client.get("/api/folders/", params={"name": "_"})
        assert response.status_code == 200
        names = {f["name"] for f in response.json()["data"]}
        assert names == {"snake_case folder", "100%_weird"}

    async def test_sort_by_name_asc(self, folder_client, seed_sortable_folders):
        response = await folder_client.get("/api/folders/", params={"sort_by": "name", "sort_order": "asc"})
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Alpha test project", "Beta initiative", "Gamma TEST"]

    async def test_sort_by_name_desc(self, folder_client, seed_sortable_folders):
        response = await folder_client.get("/api/folders/", params={"sort_by": "name", "sort_order": "desc"})
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Gamma TEST", "Beta initiative", "Alpha test project"]

    async def test_default_sort_is_created_at_desc(self, folder_client, seed_sortable_folders):
        """Backward-compatibility: no params → created_at DESC (newest first)."""
        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Gamma TEST", "Beta initiative", "Alpha test project"]

    async def test_sort_by_created_at_asc(self, folder_client, seed_sortable_folders):
        response = await folder_client.get("/api/folders/", params={"sort_by": "created_at", "sort_order": "asc"})
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Alpha test project", "Beta initiative", "Gamma TEST"]

    async def test_sort_by_updated_at(self, folder_client, seed_sortable_folders):
        """sort_by=updated_at works; default order is desc (most recent updates first)."""
        response = await folder_client.get("/api/folders/", params={"sort_by": "updated_at"})
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        # Alpha has updated_at = base+30d (most recent), Gamma = base+10d (oldest)
        assert names == ["Alpha test project", "Beta initiative", "Gamma TEST"]

    async def test_invalid_sort_by_returns_422(self, folder_client):
        response = await folder_client.get("/api/folders/", params={"sort_by": "owner"})
        assert response.status_code == 422

    async def test_invalid_sort_order_returns_422(self, folder_client):
        response = await folder_client.get("/api/folders/", params={"sort_order": "random"})
        assert response.status_code == 422

    async def test_combined_name_sort_favorites(
        self,
        folder_client,
        seed_sortable_folders,
        global_db_session,
    ):
        """Name search + sort + favorites all combine correctly."""
        # Favorite only the two folders matching "test"
        for folder in seed_sortable_folders:
            if "test" in folder.name.lower():
                global_db_session.add(
                    UserFolderFavorite(
                        id=uuid4(),
                        folder_id=folder.id,
                        user_id=OWNER_USER_ID,
                    )
                )
        await global_db_session.commit()

        response = await folder_client.get(
            "/api/folders/",
            params={
                "name": "test",
                "favorites": "true",
                "sort_by": "name",
                "sort_order": "asc",
            },
        )
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Alpha test project", "Gamma TEST"]

    async def test_combined_with_archived(
        self,
        folder_client,
        seed_sortable_folders,
        global_db_session,
    ):
        """archived=true + name + sort combine correctly."""
        # Soft-delete folders matching "test"
        for folder in seed_sortable_folders:
            if "test" in folder.name.lower():
                folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.get(
            "/api/folders/",
            params={
                "archived": "true",
                "name": "test",
                "sort_by": "name",
                "sort_order": "desc",
            },
        )
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Gamma TEST", "Alpha test project"]

    async def test_include_all_with_sort_and_search(
        self,
        folder_client,
        global_db_session,
    ):
        """include_all=true (manager view) applies name search and sort."""
        # Manager sees every folder regardless of ownership/shares
        _override_auth(
            folder_client.app,
            user_id="manager-user-id",
            username="manager_user",
            roles=["organization.read", "organization.manage"],
        )

        # Create folders owned by someone else in the same org
        for name in ["Zebra report", "Alpha deep dive", "Mid-range brief"]:
            global_db_session.add(
                Folder(
                    id=uuid4(),
                    organization_id=TEST_ORG_ID,
                    owner_id="someone-else",
                    owner="someone_else",
                    name=name,
                    tags=[],
                )
            )
        await global_db_session.commit()

        response = await folder_client.get(
            "/api/folders/",
            params={"include_all": "true", "sort_by": "name", "sort_order": "asc"},
        )
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Alpha deep dive", "Mid-range brief", "Zebra report"]

        # With name filter
        response = await folder_client.get(
            "/api/folders/",
            params={
                "include_all": "true",
                "name": "alpha",
                "sort_by": "name",
                "sort_order": "asc",
            },
        )
        assert response.status_code == 200
        names = [f["name"] for f in response.json()["data"]]
        assert names == ["Alpha deep dive"]


class TestGetFolder:
    """GET /api/folders/{folder_id}"""

    async def test_get_folder(self, folder_client, seed_folder):
        response = await folder_client.get(f"/api/folders/{seed_folder.id}")
        assert response.status_code == 200
        data = response.json()
        assert data["name"] == "Test Folder"
        assert data["is_owner"] is True
        assert data["share_role"] == "owner"

    async def test_get_folder_not_found(self, folder_client):
        fake_id = uuid4()
        response = await folder_client.get(f"/api/folders/{fake_id}")
        assert response.status_code == 404

    async def test_get_folder_no_access(self, folder_client, seed_folder):
        """User without access gets 404 (not 403, for security)."""
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.get(f"/api/folders/{seed_folder.id}")
        assert response.status_code == 404


class TestUpdateFolder:
    """PUT /api/folders/{folder_id}"""

    async def test_update_folder_owner(self, folder_client, seed_folder):
        response = await folder_client.put(
            f"/api/folders/{seed_folder.id}",
            json={"name": "Updated Name", "color": "red"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["name"] == "Updated Name"
        assert data["color"] == "red"

    async def test_update_folder_non_owner_forbidden(self, folder_client, seed_folder):
        """Non-owner gets 403 on update."""
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.put(
            f"/api/folders/{seed_folder.id}",
            json={"name": "Hacked Name"},
        )
        assert response.status_code == 403

    async def test_update_folder_not_found(self, folder_client):
        fake_id = uuid4()
        response = await folder_client.put(
            f"/api/folders/{fake_id}",
            json={"name": "Ghost"},
        )
        assert response.status_code == 404


class TestPatchFolder:
    """PATCH /api/folders/{folder_id}"""

    async def test_patch_folder_partial(self, folder_client, seed_folder):
        """Patch should allow partial updates."""
        response = await folder_client.patch(
            f"/api/folders/{seed_folder.id}",
            json={"color": "purple"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["color"] == "purple"
        assert data["name"] == "Test Folder"  # Unchanged

    async def test_patch_folder_non_owner_forbidden(self, folder_client, seed_folder):
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.patch(
            f"/api/folders/{seed_folder.id}",
            json={"name": "Hacked"},
        )
        assert response.status_code == 403


class TestDeleteFolder:
    """DELETE /api/folders/{folder_id}"""

    async def test_delete_folder_owner(self, folder_client, seed_folder):
        response = await folder_client.delete(f"/api/folders/{seed_folder.id}")
        assert response.status_code == 200
        data = response.json()
        assert data["is_deleted"] is True

    async def test_delete_folder_non_owner_forbidden(self, folder_client, seed_folder):
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.delete(f"/api/folders/{seed_folder.id}")
        assert response.status_code == 403

    async def test_delete_folder_not_found(self, folder_client):
        fake_id = uuid4()
        response = await folder_client.delete(f"/api/folders/{fake_id}")
        assert response.status_code == 404


class TestRestoreFolder:
    """POST /api/folders/{folder_id}/restore"""

    async def test_restore_folder_success(self, folder_client, seed_folder, global_db_session):
        seed_folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.post(f"/api/folders/{seed_folder.id}/restore")
        assert response.status_code == 200
        data = response.json()
        assert data["is_deleted"] is False

    async def test_restore_folder_not_deleted(self, folder_client, seed_folder):
        """Restoring a non-deleted folder returns 400."""
        response = await folder_client.post(f"/api/folders/{seed_folder.id}/restore")
        assert response.status_code == 400

    async def test_restore_folder_non_owner_forbidden(self, folder_client, seed_folder, global_db_session):
        seed_folder.is_deleted = True
        await global_db_session.commit()

        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.post(f"/api/folders/{seed_folder.id}/restore")
        assert response.status_code == 403


# ============================================================================
# Sharing Tests
# ============================================================================


class TestCreateShare:
    """POST /api/folders/{folder_id}/shares"""

    async def test_create_share_success(self, folder_client, seed_folder):
        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/shares",
            json={
                "user_id": OTHER_USER_ID,
                "user_username": OTHER_USERNAME,
                "role": "reader",
            },
        )
        assert response.status_code == 201
        data = response.json()
        assert data["user_id"] == OTHER_USER_ID
        assert data["role"] == "reader"

    async def test_create_share_writer(self, folder_client, seed_folder):
        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/shares",
            json={
                "user_id": OTHER_USER_ID,
                "user_username": OTHER_USERNAME,
                "role": "writer",
            },
        )
        assert response.status_code == 201
        assert response.json()["role"] == "writer"

    async def test_share_with_self_rejected(self, folder_client, seed_folder):
        """Cannot share folder with yourself."""
        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/shares",
            json={
                "user_id": OWNER_USER_ID,
                "user_username": OWNER_USERNAME,
                "role": "reader",
            },
        )
        assert response.status_code == 400
        assert "yourself" in response.json()["detail"].lower()

    async def test_create_share_duplicate_rejected(self, folder_client, seed_folder_with_share):
        """Duplicate share returns 400."""
        folder, share = seed_folder_with_share
        response = await folder_client.post(
            f"/api/folders/{folder.id}/shares",
            json={
                "user_id": READER_USER_ID,
                "user_username": READER_USERNAME,
                "role": "reader",
            },
        )
        assert response.status_code == 400
        assert "already shared" in response.json()["detail"].lower()

    async def test_create_share_non_owner_forbidden(self, folder_client, seed_folder):
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/shares",
            json={
                "user_id": READER_USER_ID,
                "user_username": READER_USERNAME,
                "role": "reader",
            },
        )
        assert response.status_code == 403

    async def test_create_share_folder_not_found(self, folder_client):
        fake_id = uuid4()
        response = await folder_client.post(
            f"/api/folders/{fake_id}/shares",
            json={
                "user_id": OTHER_USER_ID,
                "user_username": OTHER_USERNAME,
                "role": "reader",
            },
        )
        assert response.status_code == 404


class TestGetShares:
    """GET /api/folders/{folder_id}/shares"""

    @patch("app.api.endpoints.folder.keycloak_admin_service")
    async def test_get_shares_owner(self, mock_kc_admin, folder_client, seed_folder_with_share):
        folder, share = seed_folder_with_share

        # Mock keycloak admin for role enrichment
        mock_kc_admin.get_user_realm_roles = AsyncMock(return_value=[{"name": "organization.read"}])

        response = await folder_client.get(f"/api/folders/{folder.id}/shares")

        assert response.status_code == 200
        data = response.json()
        assert len(data) == 1
        assert data[0]["user_id"] == READER_USER_ID
        assert data[0]["role"] == "reader"

    async def test_get_shares_non_owner_forbidden(self, folder_client, seed_folder):
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.get(f"/api/folders/{seed_folder.id}/shares")
        assert response.status_code == 403


class TestUpdateShare:
    """PATCH /api/folders/{folder_id}/shares/{share_user_id}"""

    async def test_update_share_role(self, folder_client, seed_folder_with_share):
        folder, share = seed_folder_with_share

        response = await folder_client.patch(
            f"/api/folders/{folder.id}/shares/{READER_USER_ID}",
            json={"role": "writer"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["role"] == "writer"

    async def test_update_share_not_found(self, folder_client, seed_folder):
        response = await folder_client.patch(
            f"/api/folders/{seed_folder.id}/shares/nonexistent-user",
            json={"role": "writer"},
        )
        assert response.status_code == 404

    async def test_update_share_non_owner_forbidden(self, folder_client, seed_folder_with_share):
        folder, _ = seed_folder_with_share

        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.patch(
            f"/api/folders/{folder.id}/shares/{READER_USER_ID}",
            json={"role": "writer"},
        )
        assert response.status_code == 403


class TestDeleteShare:
    """DELETE /api/folders/{folder_id}/shares/{share_user_id}"""

    async def test_delete_share_success(self, folder_client, seed_folder_with_share):
        folder, share = seed_folder_with_share

        response = await folder_client.delete(f"/api/folders/{folder.id}/shares/{READER_USER_ID}")
        assert response.status_code == 200
        assert "removed" in response.json()["message"].lower()

    async def test_delete_share_not_found(self, folder_client, seed_folder):
        response = await folder_client.delete(f"/api/folders/{seed_folder.id}/shares/nonexistent-user")
        assert response.status_code == 404

    async def test_delete_share_non_owner_forbidden(self, folder_client, seed_folder_with_share):
        folder, _ = seed_folder_with_share

        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.delete(f"/api/folders/{folder.id}/shares/{READER_USER_ID}")
        assert response.status_code == 403


# ============================================================================
# Favorites Tests
# ============================================================================


class TestAddFavorite:
    """POST /api/folders/{folder_id}/favorite"""

    async def test_add_favorite_success(self, folder_client, seed_folder):
        response = await folder_client.post(f"/api/folders/{seed_folder.id}/favorite")
        assert response.status_code == 201
        data = response.json()
        assert data["is_favorite"] is True

    async def test_add_favorite_duplicate(self, folder_client, seed_folder, global_db_session):
        """Adding already-favorited folder returns 400."""
        fav = UserFolderFavorite(id=uuid4(), folder_id=seed_folder.id, user_id=OWNER_USER_ID)
        global_db_session.add(fav)
        await global_db_session.commit()

        response = await folder_client.post(f"/api/folders/{seed_folder.id}/favorite")
        assert response.status_code == 400
        assert "already" in response.json()["detail"].lower()

    async def test_add_favorite_no_access(self, folder_client, seed_folder):
        """User without access gets 404."""
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.post(f"/api/folders/{seed_folder.id}/favorite")
        assert response.status_code == 404


class TestRemoveFavorite:
    """DELETE /api/folders/{folder_id}/favorite"""

    async def test_remove_favorite_success(self, folder_client, seed_folder, global_db_session):
        fav = UserFolderFavorite(id=uuid4(), folder_id=seed_folder.id, user_id=OWNER_USER_ID)
        global_db_session.add(fav)
        await global_db_session.commit()

        response = await folder_client.delete(f"/api/folders/{seed_folder.id}/favorite")
        assert response.status_code == 200
        data = response.json()
        assert data["is_favorite"] is False

    async def test_remove_favorite_not_favorited(self, folder_client, seed_folder):
        """Removing non-favorited folder returns 400."""
        response = await folder_client.delete(f"/api/folders/{seed_folder.id}/favorite")
        assert response.status_code == 400
        assert "not in favorites" in response.json()["detail"].lower()


# ============================================================================
# Items Tests
# ============================================================================


class TestAddItem:
    """POST /api/folders/{folder_id}/items"""

    async def test_add_item_owner(self, folder_client, seed_folder):
        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/items",
            json={"item_id": "100", "item_type": "company"},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["item_id"] == "100"
        assert data["item_type"] == "company"

    async def test_add_item_writer(self, folder_client, seed_folder_with_writer):
        """Writer can add items to folder."""
        folder, share = seed_folder_with_writer

        _override_auth(folder_client.app, user_id=WRITER_USER_ID, username=WRITER_USERNAME)

        response = await folder_client.post(
            f"/api/folders/{folder.id}/items",
            json={"item_id": "200", "item_type": "company"},
        )
        assert response.status_code == 200

    async def test_add_item_reader_forbidden(self, folder_client, seed_folder_with_share):
        """Reader cannot add items."""
        folder, share = seed_folder_with_share

        _override_auth(folder_client.app, user_id=READER_USER_ID, username=READER_USERNAME)

        response = await folder_client.post(
            f"/api/folders/{folder.id}/items",
            json={"item_id": "300", "item_type": "company"},
        )
        assert response.status_code == 403
        assert "readers" in response.json()["detail"].lower()

    async def test_add_item_no_access(self, folder_client, seed_folder):
        """User without any access gets 404."""
        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.post(
            f"/api/folders/{seed_folder.id}/items",
            json={"item_id": "400", "item_type": "company"},
        )
        assert response.status_code == 404

    async def test_add_item_folder_not_found(self, folder_client):
        fake_id = uuid4()
        response = await folder_client.post(
            f"/api/folders/{fake_id}/items",
            json={"item_id": "500", "item_type": "company"},
        )
        assert response.status_code == 404


class TestRemoveItem:
    """DELETE /api/folders/{folder_id}/items/{item_id}"""

    async def test_remove_item_owner(self, folder_client, seed_folder_with_item):
        folder, item = seed_folder_with_item

        response = await folder_client.delete(
            f"/api/folders/{folder.id}/items/{item.item_id}",
            params={"item_type": "company"},
        )
        assert response.status_code == 200
        assert "removed" in response.json()["message"].lower()

    async def test_remove_item_non_owner_forbidden(self, folder_client, seed_folder_with_item):
        folder, item = seed_folder_with_item

        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.delete(
            f"/api/folders/{folder.id}/items/{item.item_id}",
            params={"item_type": "company"},
        )
        assert response.status_code == 403

    async def test_remove_item_not_found(self, folder_client, seed_folder):
        fake_item_id = uuid4()
        response = await folder_client.delete(
            f"/api/folders/{seed_folder.id}/items/{fake_item_id}",
            params={"item_type": "company"},
        )
        assert response.status_code == 404


class TestMoveItem:
    """PATCH /api/folders/{folder_id}/items/{item_id}"""

    async def test_move_item_success(self, folder_client, seed_folder_with_item, global_db_session):
        folder, item = seed_folder_with_item

        # Create destination folder (same owner)
        dest = Folder(
            id=uuid4(),
            organization_id=TEST_ORG_ID,
            owner_id=OWNER_USER_ID,
            owner=OWNER_USERNAME,
            name="Destination",
            tags=[],
        )
        global_db_session.add(dest)
        await global_db_session.commit()
        await global_db_session.refresh(dest)

        response = await folder_client.patch(
            f"/api/folders/{folder.id}/items/{item.item_id}",
            params={"item_type": "company"},
            json={"folder_id": str(dest.id)},
        )
        assert response.status_code == 200
        data = response.json()
        assert data["message"] == "Item moved successfully"

    async def test_move_item_dest_not_found(self, folder_client, seed_folder_with_item):
        folder, item = seed_folder_with_item
        fake_dest = uuid4()

        response = await folder_client.patch(
            f"/api/folders/{folder.id}/items/{item.item_id}",
            params={"item_type": "company"},
            json={"folder_id": str(fake_dest)},
        )
        assert response.status_code == 404


# ============================================================================
# Access Control Tests
# ============================================================================


class TestAccessControl:
    """Test cross-cutting access control patterns."""

    async def test_shared_user_can_view_folder(self, folder_client, seed_folder_with_share):
        """A user with a share (reader) can GET the folder."""
        folder, share = seed_folder_with_share

        _override_auth(folder_client.app, user_id=READER_USER_ID, username=READER_USERNAME)

        response = await folder_client.get(f"/api/folders/{folder.id}")
        assert response.status_code == 200
        data = response.json()
        assert data["share_role"] == "reader"
        assert data["is_owner"] is False

    async def test_reader_cannot_update_folder(self, folder_client, seed_folder_with_share):
        """Reader cannot update folder settings (owner only)."""
        folder, share = seed_folder_with_share

        _override_auth(folder_client.app, user_id=READER_USER_ID, username=READER_USERNAME)

        response = await folder_client.put(
            f"/api/folders/{folder.id}",
            json={"name": "Hacked by reader"},
        )
        assert response.status_code == 403

    async def test_writer_cannot_delete_folder(self, folder_client, seed_folder_with_writer):
        """Writer cannot delete folder (owner only)."""
        folder, share = seed_folder_with_writer

        _override_auth(folder_client.app, user_id=WRITER_USER_ID, username=WRITER_USERNAME)

        response = await folder_client.delete(f"/api/folders/{folder.id}")
        assert response.status_code == 403

    async def test_shared_folders_appear_in_list(self, folder_client, seed_folder_with_share):
        """Shared folders should appear in the shared user's folder list."""
        folder, share = seed_folder_with_share

        _override_auth(folder_client.app, user_id=READER_USER_ID, username=READER_USERNAME)

        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        body = response.json()
        assert len(body["data"]) == 1
        assert body["data"][0]["name"] == "Test Folder"
        assert body["data"][0]["share_role"] == "reader"


# ============================================================================
# Pagination / Sort / Filter Tests (GET /api/folders/{id})
# ============================================================================


class TestGetFolderPaginationSortFilter:
    """GET /api/folders/{folder_id} — pagination, sorting, and name filtering."""

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _patch_companies(company_map):
        """Return a context-manager that patches get_companies_by_ids."""
        return patch(
            "app.services.folder.get_companies_by_ids",
            new_callable=AsyncMock,
            return_value=company_map,
        )

    # ------------------------------------------------------------------
    # Pagination
    # ------------------------------------------------------------------

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_pagination_first_page(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """page=1&size=12 returns 12 items and correct pagination metadata."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"page": 1, "size": 12})

        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) == 12
        assert data["pagination"]["total"] == 15
        assert data["pagination"]["page"] == 1
        assert data["pagination"]["limit"] == 12
        assert data["pagination"]["total_pages"] == 2

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_pagination_second_page(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """page=2&size=12 returns the 3 remaining items."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"page": 2, "size": 12})

        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) == 3
        assert data["pagination"]["page"] == 2
        assert data["pagination"]["total"] == 15

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_pagination_out_of_range_returns_empty(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """Requesting a page beyond total_pages returns empty items list."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"page": 99, "size": 12})

        assert response.status_code == 200
        data = response.json()
        assert data["items"] == []
        assert data["pagination"]["total"] == 15

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_pagination_response_includes_metadata(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """Response pagination object contains all required fields."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"page": 1, "size": 5})

        assert response.status_code == 200
        pagination = response.json()["pagination"]
        assert set(pagination.keys()) >= {"total", "page", "limit", "total_pages"}
        assert pagination["total"] == 15
        assert pagination["page"] == 1
        assert pagination["limit"] == 5
        assert pagination["total_pages"] == 3

    async def test_page_without_size_returns_422(self, folder_client, seed_folder):
        """Providing page without size returns 422 (size is required to interpret the page)."""
        response = await folder_client.get(f"/api/folders/{seed_folder.id}", params={"page": 1})
        assert response.status_code == 422

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_size_without_page_defaults_to_page_1(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """size without page is valid and defaults to page=1."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"size": 5})

        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) == 5
        assert data["pagination"]["page"] == 1
        assert data["pagination"]["total"] == 15

    # ------------------------------------------------------------------
    # Sorting
    # ------------------------------------------------------------------

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_sort_by_name_asc(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """sort_by=name&sort_order=asc returns items alphabetically."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"sort_by": "name", "sort_order": "asc"},
        )

        assert response.status_code == 200
        names = [item["name"] for item in response.json()["items"]]
        assert names == sorted(names, key=str.lower)

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_sort_by_name_desc(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """sort_by=name&sort_order=desc returns items reverse-alphabetically."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"sort_by": "name", "sort_order": "desc"},
        )

        assert response.status_code == 200
        names = [item["name"] for item in response.json()["items"]]
        assert names == sorted(names, key=str.lower, reverse=True)

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_sort_by_added_at_desc(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """sort_by=added_at&sort_order=desc returns most-recently-added first."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"sort_by": "added_at", "sort_order": "desc"},
        )

        assert response.status_code == 200
        items = response.json()["items"]
        added_dates = [item["added_at"] for item in items]
        assert added_dates == sorted(added_dates, reverse=True)

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_sort_by_added_at_asc(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """sort_by=added_at&sort_order=asc returns oldest-added first."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"sort_by": "added_at", "sort_order": "asc"},
        )

        assert response.status_code == 200
        items = response.json()["items"]
        added_dates = [item["added_at"] for item in items]
        assert added_dates == sorted(added_dates)

    async def test_sort_by_invalid_value_returns_422(self, folder_client, seed_folder):
        """Unknown sort_by value is rejected."""
        response = await folder_client.get(
            f"/api/folders/{seed_folder.id}",
            params={"sort_by": "hacker_field", "sort_order": "asc"},
        )
        assert response.status_code == 422

    async def test_sort_order_invalid_value_returns_422(self, folder_client, seed_folder):
        """Unknown sort_order value is rejected."""
        response = await folder_client.get(
            f"/api/folders/{seed_folder.id}",
            params={"sort_by": "name", "sort_order": "random"},
        )
        assert response.status_code == 422

    # ------------------------------------------------------------------
    # Name filter
    # ------------------------------------------------------------------

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_name_filter_returns_matching_items(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """name=test filters items whose name contains 'test' (case-insensitive)."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"name": "test"})

        assert response.status_code == 200
        items = response.json()["items"]
        # Only 'test-Kilo', 'test-Lima', 'test-Mike' match
        assert len(items) == 3
        for item in items:
            assert "test" in item["name"].lower()

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_name_filter_case_insensitive(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """Name filter is case-insensitive."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"name": "ALPHA"})

        assert response.status_code == 200
        items = response.json()["items"]
        assert len(items) == 1
        assert items[0]["name"] == "Alpha"

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_name_filter_no_match_returns_empty(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """Name filter with no match returns empty items list."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"name": "zzz-nonexistent"})

        assert response.status_code == 200
        assert response.json()["items"] == []

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_name_filter_combined_with_pagination(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """name filter + pagination: pagination total reflects filtered count."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"name": "test", "page": 1, "size": 2},
        )

        assert response.status_code == 200
        data = response.json()
        # 3 items match 'test', page size 2 → first page has 2
        assert len(data["items"]) == 2
        assert data["pagination"]["total"] == 3
        assert data["pagination"]["total_pages"] == 2

    # ------------------------------------------------------------------
    # Backward compatibility (no pagination params)
    # ------------------------------------------------------------------

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_no_params_returns_all_items(self, mock_get_companies, folder_client, seed_folder_with_many_items):
        """Without pagination params, all items are returned and pagination is null."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}")

        assert response.status_code == 200
        data = response.json()
        assert len(data["items"]) == 15
        assert data["pagination"] is None

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_no_params_default_order_position_then_added_at(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """Without sort params, items are ordered by position asc (then added_at)."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}")

        assert response.status_code == 200
        items = response.json()["items"]
        positions = [item["position"] for item in items]
        assert positions == sorted(positions)

    # ------------------------------------------------------------------
    # Enrichment still works with pagination
    # ------------------------------------------------------------------

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_enrichment_fields_present_with_pagination(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """Company enrichment fields (name, website, owner) are present on paginated items."""
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(f"/api/folders/{folder.id}", params={"page": 1, "size": 5})

        assert response.status_code == 200
        for item in response.json()["items"]:
            assert "name" in item
            assert "website" in item
            assert "owner" in item
            assert item["name"]  # non-empty

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_enrichment_called_with_all_ids_not_just_page(
        self, mock_get_companies, folder_client, seed_folder_with_many_items
    ):
        """get_companies_by_ids is called with all folder item IDs, not just the page slice.

        This ensures the name filter and total count are computed on the full set.
        """
        folder, company_map = seed_folder_with_many_items
        mock_get_companies.return_value = company_map

        await folder_client.get(f"/api/folders/{folder.id}", params={"page": 1, "size": 5})

        assert mock_get_companies.called
        called_ids = mock_get_companies.call_args.kwargs["company_ids"]
        assert len(called_ids) == 15

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_sort_by_position_desc_nullslast(
        self, mock_get_companies, folder_client, seed_folder_with_mixed_positions
    ):
        """sort_by=position&sort_order=desc uses nullslast(): null-position items come after all positioned items.

        Note: SQLite ignores nullsfirst()/nullslast() clauses, so we only assert the nulls-last
        invariant (not the descending order of non-null positions, which is a PostgreSQL guarantee).
        """
        folder, company_map = seed_folder_with_mixed_positions
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"sort_by": "position", "sort_order": "desc"},
        )

        assert response.status_code == 200
        items = response.json()["items"]
        positions = [item["position"] for item in items]

        # All items with a position must appear before any null-position item
        first_null_index = next((i for i, p in enumerate(positions) if p is None), None)
        if first_null_index is not None:
            assert all(p is None for p in positions[first_null_index:]), (
                "All null-position items must be grouped at the end (nullslast)"
            )
            assert all(p is not None for p in positions[:first_null_index]), (
                "All positioned items must appear before null-position items"
            )

    @patch("app.services.folder.get_companies_by_ids", new_callable=AsyncMock)
    async def test_archived_with_pagination_and_name_filter(
        self, mock_get_companies, folder_client, seed_folder_with_archived_items
    ):
        """archived=true + page + name: all three features work correctly together."""
        folder, company_map = seed_folder_with_archived_items
        mock_get_companies.return_value = company_map

        response = await folder_client.get(
            f"/api/folders/{folder.id}",
            params={"archived": "true", "page": 1, "size": 5, "name": "archived"},
        )

        assert response.status_code == 200
        data = response.json()
        for item in data["items"]:
            assert item["is_deleted"] is True
            assert "archived" in item["name"].lower()
        assert data["pagination"]["page"] == 1
        assert data["pagination"]["limit"] == 5


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
