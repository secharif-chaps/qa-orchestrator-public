"""Tests for FolderService sharing and access control methods.

These tests verify the core functionality of folder sharing service:
1. share_folder() creates share with correct role
2. share_folder() prevents duplicate shares
3. unshare_folder() removes share
4. get_folder_shares() returns all shares for folder
5. list_folders() returns owned and shared folders only
6. has_folder_access() returns true for owner
7. has_folder_access() returns true for shared user
8. has_folder_access() returns false for non-shared user
"""

import pytest
from uuid import UUID

from app.models.folder import Folder, FolderShare, ShareRole
from app.services.folder import FolderService


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
def second_folder(db_session):
    """Create a second folder with a different owner for testing."""
    folder = Folder(
        name="Second Folder",
        organization_id="org-uuid-123",
        owner_id="other-owner-uuid-789",
        owner="otheruser"
    )
    db_session.add(folder)
    db_session.commit()
    db_session.refresh(folder)
    return folder


@pytest.fixture
def third_folder_different_org(db_session):
    """Create a folder in a different organization."""
    folder = Folder(
        name="Different Org Folder",
        organization_id="org-uuid-other",
        owner_id="owner-uuid-456",
        owner="testuser"
    )
    db_session.add(folder)
    db_session.commit()
    db_session.refresh(folder)
    return folder


class TestShareFolder:
    """Test share_folder() method."""

    def test_share_folder_creates_share_with_reader_role(self, db_session, sample_folder):
        """Test that share_folder creates a share with correct reader role."""
        share = FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001",
            user_username="shareduser1",
            role=ShareRole.READER
        )

        assert share is not None
        assert share.folder_id == sample_folder.id
        assert share.user_id == "shared-user-uuid-001"
        assert share.user_username == "shareduser1"
        assert share.role == ShareRole.READER
        assert share.created_at is not None

    def test_share_folder_creates_share_with_writer_role(self, db_session, sample_folder):
        """Test that share_folder creates a share with correct writer role."""
        share = FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-002",
            user_username="shareduser2",
            role=ShareRole.WRITER
        )

        assert share is not None
        assert share.role == ShareRole.WRITER

    def test_share_folder_prevents_duplicate_shares(self, db_session, sample_folder):
        """Test that share_folder raises error for duplicate shares."""
        # Create first share
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001",
            user_username="shareduser1",
            role=ShareRole.READER
        )

        # Attempt to create duplicate share should raise error
        with pytest.raises(ValueError, match="already shared"):
            FolderService.share_folder(
                db=db_session,
                folder_id=sample_folder.id,
                user_id="shared-user-uuid-001",
                user_username="shareduser1",
                role=ShareRole.WRITER
            )


class TestUnshareFolder:
    """Test unshare_folder() method."""

    def test_unshare_folder_removes_share(self, db_session, sample_folder):
        """Test that unshare_folder removes an existing share."""
        # Create a share first
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001",
            user_username="shareduser1",
            role=ShareRole.READER
        )

        # Verify share exists
        shares_before = FolderService.get_folder_shares(db_session, sample_folder.id)
        assert len(shares_before) == 1

        # Remove the share
        result = FolderService.unshare_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001"
        )

        assert result is True

        # Verify share is removed
        shares_after = FolderService.get_folder_shares(db_session, sample_folder.id)
        assert len(shares_after) == 0

    def test_unshare_folder_returns_false_for_nonexistent_share(self, db_session, sample_folder):
        """Test that unshare_folder returns False when share doesn't exist."""
        result = FolderService.unshare_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="nonexistent-user-uuid"
        )

        assert result is False


class TestGetFolderShares:
    """Test get_folder_shares() method."""

    def test_get_folder_shares_returns_all_shares(self, db_session, sample_folder):
        """Test that get_folder_shares returns all shares for a folder."""
        # Create multiple shares
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-uuid-001",
            user_username="user1",
            role=ShareRole.READER
        )
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-uuid-002",
            user_username="user2",
            role=ShareRole.WRITER
        )
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-uuid-003",
            user_username="user3",
            role=ShareRole.READER
        )

        shares = FolderService.get_folder_shares(db_session, sample_folder.id)

        assert len(shares) == 3
        user_ids = {share.user_id for share in shares}
        assert "user-uuid-001" in user_ids
        assert "user-uuid-002" in user_ids
        assert "user-uuid-003" in user_ids

    def test_get_folder_shares_returns_empty_list_for_unshared_folder(self, db_session, sample_folder):
        """Test that get_folder_shares returns empty list when no shares exist."""
        shares = FolderService.get_folder_shares(db_session, sample_folder.id)
        assert shares == []


class TestListFoldersWithSharing:
    """Test list_folders() with ownership and sharing filtering."""

    def test_list_folders_returns_owned_folders(self, db_session, sample_folder, second_folder):
        """Test that list_folders returns folders owned by user."""
        folders = FolderService.list_folders(
            db=db_session,
            organization_id="org-uuid-123",
            user_id="owner-uuid-456"
        )

        # User should only see their own folder
        folder_ids = {folder.id for folder in folders}
        assert sample_folder.id in folder_ids
        assert second_folder.id not in folder_ids  # Different owner

    def test_list_folders_returns_shared_folders(self, db_session, sample_folder, second_folder):
        """Test that list_folders returns folders shared with user."""
        # Share second_folder with the owner of sample_folder
        FolderService.share_folder(
            db=db_session,
            folder_id=second_folder.id,
            user_id="owner-uuid-456",
            user_username="testuser",
            role=ShareRole.READER
        )

        folders = FolderService.list_folders(
            db=db_session,
            organization_id="org-uuid-123",
            user_id="owner-uuid-456"
        )

        # User should see both owned and shared folders
        folder_ids = {folder.id for folder in folders}
        assert sample_folder.id in folder_ids  # Owned
        assert second_folder.id in folder_ids  # Shared


class TestHasFolderAccess:
    """Test has_folder_access() method."""

    def test_has_folder_access_returns_true_for_owner(self, db_session, sample_folder):
        """Test that has_folder_access returns True for folder owner."""
        result = FolderService.has_folder_access(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="owner-uuid-456",
            organization_id="org-uuid-123"
        )

        assert result is True

    def test_has_folder_access_returns_true_for_shared_user(self, db_session, sample_folder):
        """Test that has_folder_access returns True for shared user."""
        # Share folder with another user
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001",
            user_username="shareduser",
            role=ShareRole.READER
        )

        result = FolderService.has_folder_access(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-001",
            organization_id="org-uuid-123"
        )

        assert result is True

    def test_has_folder_access_returns_false_for_non_shared_user(self, db_session, sample_folder):
        """Test that has_folder_access returns False for non-shared user."""
        result = FolderService.has_folder_access(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="random-user-uuid-999",
            organization_id="org-uuid-123"
        )

        assert result is False

    def test_has_folder_access_returns_false_for_different_organization(
        self, db_session, sample_folder, third_folder_different_org
    ):
        """Test that has_folder_access returns False for wrong organization."""
        # Even though user owns the folder, different org should return False
        result = FolderService.has_folder_access(
            db=db_session,
            folder_id=third_folder_different_org.id,
            user_id="owner-uuid-456",
            organization_id="org-uuid-123"  # Different from folder's org
        )

        assert result is False


class TestGetUserFolderRole:
    """Test get_user_folder_role() method."""

    def test_get_user_folder_role_returns_owner_for_owner(self, db_session, sample_folder):
        """Test that get_user_folder_role returns 'owner' for folder owner."""
        role = FolderService.get_user_folder_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="owner-uuid-456"
        )

        assert role == "owner"

    def test_get_user_folder_role_returns_writer_for_writer(self, db_session, sample_folder):
        """Test that get_user_folder_role returns 'writer' for writer."""
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="writer-user-uuid",
            user_username="writeruser",
            role=ShareRole.WRITER
        )

        role = FolderService.get_user_folder_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="writer-user-uuid"
        )

        assert role == "writer"

    def test_get_user_folder_role_returns_reader_for_reader(self, db_session, sample_folder):
        """Test that get_user_folder_role returns 'reader' for reader."""
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="reader-user-uuid",
            user_username="readeruser",
            role=ShareRole.READER
        )

        role = FolderService.get_user_folder_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="reader-user-uuid"
        )

        assert role == "reader"

    def test_get_user_folder_role_returns_none_for_no_access(self, db_session, sample_folder):
        """Test that get_user_folder_role returns None for non-shared user."""
        role = FolderService.get_user_folder_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="random-user-uuid"
        )

        assert role is None


class TestIsFolderOwner:
    """Test is_folder_owner() method."""

    def test_is_folder_owner_returns_true_for_owner(self, db_session, sample_folder):
        """Test that is_folder_owner returns True for folder owner."""
        result = FolderService.is_folder_owner(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="owner-uuid-456"
        )

        assert result is True

    def test_is_folder_owner_returns_false_for_shared_user(self, db_session, sample_folder):
        """Test that is_folder_owner returns False for shared user."""
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid",
            user_username="shareduser",
            role=ShareRole.WRITER
        )

        result = FolderService.is_folder_owner(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="shared-user-uuid"
        )

        assert result is False


class TestFlagFolderOrphaned:
    """Test flag_folder_orphaned() method."""

    def test_flag_folder_orphaned_sets_is_orphaned_true(self, db_session, sample_folder):
        """Test that flag_folder_orphaned sets is_orphaned to True."""
        assert sample_folder.is_orphaned is False

        result = FolderService.flag_folder_orphaned(
            db=db_session,
            folder_id=sample_folder.id
        )

        assert result is True
        db_session.refresh(sample_folder)
        assert sample_folder.is_orphaned is True

    def test_flag_folder_orphaned_returns_false_for_nonexistent_folder(self, db_session):
        """Test that flag_folder_orphaned returns False for non-existent folder."""
        from uuid import uuid4

        result = FolderService.flag_folder_orphaned(
            db=db_session,
            folder_id=uuid4()
        )

        assert result is False


class TestUpdateShareRole:
    """Test update_share_role() method."""

    def test_update_share_role_changes_role(self, db_session, sample_folder):
        """Test that update_share_role changes the share role."""
        # Create initial share as reader
        FolderService.share_folder(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-uuid-001",
            user_username="user1",
            role=ShareRole.READER
        )

        # Update to writer
        share = FolderService.update_share_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="user-uuid-001",
            role=ShareRole.WRITER
        )

        assert share is not None
        assert share.role == ShareRole.WRITER

    def test_update_share_role_returns_none_for_nonexistent_share(self, db_session, sample_folder):
        """Test that update_share_role returns None for non-existent share."""
        result = FolderService.update_share_role(
            db=db_session,
            folder_id=sample_folder.id,
            user_id="nonexistent-user-uuid",
            role=ShareRole.WRITER
        )

        assert result is None
