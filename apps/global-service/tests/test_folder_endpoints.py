"""Tests for folder API endpoints.

Tests cover:
- Folder CRUD (create, list, get, update, patch, delete, restore)
- Folder sharing (create, list, update, delete shares)
- User favorites (add, remove)
- Folder items (add, remove, move)
- Access control (owner-only operations, reader/writer permissions)

All tests use SQLite in-memory database and mock external dependencies
(Keycloak auth, backend_client for company enrichment, keycloak_admin_service).
"""

import pytest
from uuid import uuid4
from unittest.mock import AsyncMock, patch, MagicMock

from app.core.organization import OrganizationContext, get_user_organization
from app.models.folder import Folder, FolderItem, FolderShare, ShareRole, ItemType
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


def _override_auth(app, user_id=OWNER_USER_ID, username=OWNER_USERNAME,
                   org_id=TEST_ORG_ID, roles=None):
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
        assert response.json() == []

    async def test_list_folders_returns_owned(self, folder_client, seed_folder):
        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        data = response.json()
        assert len(data) == 1
        assert data[0]["name"] == "Test Folder"

    async def test_list_folders_excludes_deleted(self, folder_client, seed_folder, global_db_session):
        """Deleted folders should not appear in default list."""
        seed_folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/")
        assert response.status_code == 200
        assert len(response.json()) == 0

    async def test_list_folders_archived_flag(self, folder_client, seed_folder, global_db_session):
        """archived=true should only return deleted folders."""
        seed_folder.is_deleted = True
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/", params={"archived": "true"})
        assert response.status_code == 200
        data = response.json()
        assert len(data) == 1
        assert data[0]["is_deleted"] is True

    async def test_list_folders_favorites_filter(self, folder_client, seed_folder, global_db_session):
        """favorites=true should only return favorited folders."""
        fav = UserFolderFavorite(id=uuid4(), folder_id=seed_folder.id, user_id=OWNER_USER_ID)
        global_db_session.add(fav)
        await global_db_session.commit()

        response = await folder_client.get("/api/folders/", params={"favorites": "true"})
        assert response.status_code == 200
        data = response.json()
        assert len(data) == 1

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
        assert len(response.json()) == 0


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

        response = await folder_client.delete(
            f"/api/folders/{folder.id}/shares/{READER_USER_ID}"
        )
        assert response.status_code == 200
        assert "removed" in response.json()["message"].lower()

    async def test_delete_share_not_found(self, folder_client, seed_folder):
        response = await folder_client.delete(
            f"/api/folders/{seed_folder.id}/shares/nonexistent-user"
        )
        assert response.status_code == 404

    async def test_delete_share_non_owner_forbidden(self, folder_client, seed_folder_with_share):
        folder, _ = seed_folder_with_share

        _override_auth(folder_client.app, user_id=OTHER_USER_ID, username=OTHER_USERNAME)

        response = await folder_client.delete(
            f"/api/folders/{folder.id}/shares/{READER_USER_ID}"
        )
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
        data = response.json()
        assert len(data) == 1
        assert data[0]["name"] == "Test Folder"
        assert data[0]["share_role"] == "reader"


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
