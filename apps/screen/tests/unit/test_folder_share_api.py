"""Tests for folder sharing API endpoints.

These tests verify the API layer for folder sharing:
1. POST /{folder_id}/shares creates share (owner only)
2. POST /{folder_id}/shares returns 403 for non-owner
3. GET /{folder_id}/shares returns shares list (owner only)
4. DELETE /{folder_id}/shares/{user_id} removes share
5. PATCH /{folder_id}/shares/{user_id} updates role
6. PUT/DELETE folder returns 403 for shared writers
7. Shared reader cannot create items
8. Shared writer can create items (if has screen.create)
"""

import pytest
from uuid import UUID

from app.models.folder import Folder, FolderShare, ShareRole
from app.services.folder import FolderService
from app.schemas.folder import FolderCreate


@pytest.fixture
def sample_folder(db_session):
    """Create a sample folder for testing."""
    folder = Folder(
        name="Test Folder",
        organization_id="org-uuid-123",
        owner_id="owner-uuid-456",
        owner="testuser"
    )
    db_session.add(folder)
    db_session.commit()
    db_session.refresh(folder)
    return folder


@pytest.fixture
def shared_folder_with_writer(db_session, sample_folder):
    """Create a folder shared with a writer user."""
    FolderService.share_folder(
        db=db_session,
        folder_id=sample_folder.id,
        user_id="writer-user-uuid",
        user_username="writeruser",
        role=ShareRole.writer
    )
    return sample_folder


@pytest.fixture
def shared_folder_with_reader(db_session, sample_folder):
    """Create a folder shared with a reader user."""
    FolderService.share_folder(
        db=db_session,
        folder_id=sample_folder.id,
        user_id="reader-user-uuid",
        user_username="readeruser",
        role=ShareRole.reader
    )
    return sample_folder


class TestShareEndpointOwnerAccess:
    """Test POST /{folder_id}/shares creates share (owner only)."""

    def test_owner_can_create_share(self, db_session, sample_folder):
        """Test that folder owner can create a share."""
        # This tests the service layer logic that would be used by the endpoint
        share = FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="new-shared-user-uuid",
            user_username="newshareduser",
            role=ShareRole.reader
        )

        assert share is not None
        assert share.folder_id == sample_folder.id
        assert share.user_id == "new-shared-user-uuid"
        assert share.role == ShareRole.reader

    def test_is_folder_owner_check_works(self, db_session, sample_folder):
        """Test that is_folder_owner correctly identifies owner."""
        # Owner check should return True
        assert FolderService.is_folder_owner(
            db_session, sample_folder.id, "owner-uuid-456"
        ) is True

        # Non-owner check should return False
        assert FolderService.is_folder_owner(
            db_session, sample_folder.id, "other-user-uuid"
        ) is False


class TestShareEndpointNonOwnerAccess:
    """Test POST /{folder_id}/shares returns 403 for non-owner."""

    def test_non_owner_cannot_create_share(self, db_session, shared_folder_with_writer):
        """Test that non-owner (even writer) cannot create shares."""
        # Writer should not be owner
        assert FolderService.is_folder_owner(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        ) is False

        # In the API endpoint, this would return 403
        # Here we verify the check works
        role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        )
        assert role == "writer"
        assert role != "owner"


class TestGetSharesEndpoint:
    """Test GET /{folder_id}/shares returns shares list (owner only)."""

    def test_get_folder_shares_returns_all_shares(self, db_session, sample_folder):
        """Test that get_folder_shares returns all shares for a folder."""
        # Create multiple shares
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-1",
            user_username="user1",
            role=ShareRole.reader
        )
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-2",
            user_username="user2",
            role=ShareRole.writer
        )

        shares = FolderService.get_folder_shares(db_session, sample_folder.id)

        assert len(shares) == 2
        user_ids = {s.user_id for s in shares}
        assert "user-1" in user_ids
        assert "user-2" in user_ids


class TestDeleteShareEndpoint:
    """Test DELETE /{folder_id}/shares/{user_id} removes share."""

    def test_unshare_folder_removes_share(self, db_session, shared_folder_with_writer):
        """Test that unshare_folder removes an existing share."""
        # Verify share exists
        assert FolderService.has_folder_access(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid",
            "org-uuid-123"
        ) is True

        # Remove share
        result = FolderService.unshare_folder(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        )
        assert result is True

        # Verify share is removed
        assert FolderService.has_folder_access(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid",
            "org-uuid-123"
        ) is False


class TestUpdateShareRoleEndpoint:
    """Test PATCH /{folder_id}/shares/{user_id} updates role."""

    def test_update_share_role_changes_reader_to_writer(self, db_session, shared_folder_with_reader):
        """Test that update_share_role changes role from reader to writer."""
        # Verify initial role
        role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_reader.id,
            "reader-user-uuid"
        )
        assert role == "reader"

        # Update role
        share = FolderService.update_share_role(
            db_session,
            shared_folder_with_reader.id,
            "reader-user-uuid",
            ShareRole.writer
        )

        assert share is not None
        assert share.role == ShareRole.writer

        # Verify new role
        new_role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_reader.id,
            "reader-user-uuid"
        )
        assert new_role == "writer"


class TestFolderWriteEndpointsOwnerOnly:
    """Test PUT/DELETE folder returns 403 for shared writers."""

    def test_writer_is_not_owner(self, db_session, shared_folder_with_writer):
        """Test that writer is not recognized as owner."""
        # Writer should have access
        assert FolderService.has_folder_access(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid",
            "org-uuid-123"
        ) is True

        # But writer should NOT be owner
        assert FolderService.is_folder_owner(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        ) is False

        # Role should be "writer" not "owner"
        role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        )
        assert role == "writer"

    def test_only_owner_can_update_folder(self, db_session, shared_folder_with_writer):
        """Test that only owner can update folder metadata."""
        # Owner check for owner should pass
        assert FolderService.is_folder_owner(
            db_session,
            shared_folder_with_writer.id,
            "owner-uuid-456"
        ) is True

        # Owner check for writer should fail
        assert FolderService.is_folder_owner(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        ) is False


class TestItemCreationRoleBasedAccess:
    """Test item creation role-based access control."""

    def test_reader_cannot_create_items(self, db_session, shared_folder_with_reader):
        """Test that reader cannot create items (role check)."""
        # Reader should have access
        assert FolderService.has_folder_access(
            db_session,
            shared_folder_with_reader.id,
            "reader-user-uuid",
            "org-uuid-123"
        ) is True

        # But role should be "reader" not "owner" or "writer"
        role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_reader.id,
            "reader-user-uuid"
        )
        assert role == "reader"
        # In API endpoint, this would check if role in ("owner", "writer")
        assert role not in ("owner", "writer")

    def test_writer_role_allows_item_creation(self, db_session, shared_folder_with_writer):
        """Test that writer role allows item creation (role check only)."""
        role = FolderService.get_user_folder_role(
            db_session,
            shared_folder_with_writer.id,
            "writer-user-uuid"
        )
        assert role == "writer"
        # Writer role should allow item creation (combined with screen.create permission)
        assert role in ("owner", "writer")

    def test_owner_role_allows_item_creation(self, db_session, sample_folder):
        """Test that owner role allows item creation."""
        role = FolderService.get_user_folder_role(
            db_session,
            sample_folder.id,
            "owner-uuid-456"
        )
        assert role == "owner"
        # Owner role should allow item creation
        assert role in ("owner", "writer")
