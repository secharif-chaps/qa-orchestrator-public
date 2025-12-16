"""Tests for FolderShare model.

These tests verify the core functionality of the FolderShare model:
1. FolderShare creation with valid data
2. Unique constraint enforcement (folder_id + user_id)
3. Role enum validation (reader/writer)
4. Cascade delete when folder is deleted
5. Relationship navigation (FolderShare -> Folder)
"""

import pytest
from sqlalchemy.exc import IntegrityError

from app.models.folder import Folder, FolderShare, ShareRole


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


class TestFolderShareCreation:
    """Test FolderShare creation with valid data."""

    def test_create_folder_share_with_reader_role(self, db_session, sample_folder):
        """Test creating a folder share with reader role."""
        share = FolderShare(
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-789",
            user_username="shareduser",
            role=ShareRole.READER
        )
        db_session.add(share)
        db_session.commit()
        db_session.refresh(share)

        assert share.id is not None
        assert share.folder_id == sample_folder.id
        assert share.user_id == "shared-user-uuid-789"
        assert share.user_username == "shareduser"
        assert share.role == ShareRole.READER
        assert share.created_at is not None

    def test_create_folder_share_with_writer_role(self, db_session, sample_folder):
        """Test creating a folder share with writer role."""
        share = FolderShare(
            folder_id=sample_folder.id,
            user_id="shared-user-uuid-789",
            user_username="shareduser",
            role=ShareRole.WRITER
        )
        db_session.add(share)
        db_session.commit()
        db_session.refresh(share)

        assert share.role == ShareRole.WRITER


class TestFolderShareUniqueConstraint:
    """Test unique constraint on (folder_id + user_id)."""

    def test_unique_constraint_prevents_duplicate_shares(self, db_session, sample_folder):
        """Test that a user cannot be shared with the same folder twice."""
        user_id = "shared-user-uuid-789"

        # Create first share
        share1 = FolderShare(
            folder_id=sample_folder.id,
            user_id=user_id,
            user_username="shareduser",
            role=ShareRole.READER
        )
        db_session.add(share1)
        db_session.commit()

        # Attempt to create duplicate share
        share2 = FolderShare(
            folder_id=sample_folder.id,
            user_id=user_id,
            user_username="shareduser",
            role=ShareRole.WRITER
        )
        db_session.add(share2)

        with pytest.raises(IntegrityError):
            db_session.commit()

    def test_same_user_can_share_different_folders(self, db_session, sample_folder):
        """Test that a user can be shared with multiple different folders."""
        user_id = "shared-user-uuid-789"

        # Create another folder
        folder2 = Folder(
            name="Test Folder 2",
            organization_id="org-uuid-123",
            owner_id="owner-uuid-456",
            owner="testuser"
        )
        db_session.add(folder2)
        db_session.commit()

        # Create share for first folder
        share1 = FolderShare(
            folder_id=sample_folder.id,
            user_id=user_id,
            user_username="shareduser",
            role=ShareRole.READER
        )
        db_session.add(share1)

        # Create share for second folder
        share2 = FolderShare(
            folder_id=folder2.id,
            user_id=user_id,
            user_username="shareduser",
            role=ShareRole.WRITER
        )
        db_session.add(share2)
        db_session.commit()

        # Both shares should exist
        assert share1.id is not None
        assert share2.id is not None


class TestFolderShareRoleEnum:
    """Test role enum validation."""

    def test_role_accepts_reader_value(self, db_session, sample_folder):
        """Test that 'reader' role value is accepted."""
        share = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-1",
            user_username="user1",
            role=ShareRole.READER
        )
        db_session.add(share)
        db_session.commit()

        assert share.role == ShareRole.READER
        assert share.role.value == "reader"

    def test_role_accepts_writer_value(self, db_session, sample_folder):
        """Test that 'writer' role value is accepted."""
        share = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-2",
            user_username="user2",
            role=ShareRole.WRITER
        )
        db_session.add(share)
        db_session.commit()

        assert share.role == ShareRole.WRITER
        assert share.role.value == "writer"


class TestFolderShareCascadeDelete:
    """Test cascade delete behavior when folder is deleted."""

    def test_shares_deleted_when_folder_deleted(self, db_session, sample_folder):
        """Test that folder shares are deleted when the folder is deleted."""
        # Create multiple shares for the folder
        share1 = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-1",
            user_username="user1",
            role=ShareRole.READER
        )
        share2 = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-2",
            user_username="user2",
            role=ShareRole.WRITER
        )
        db_session.add_all([share1, share2])
        db_session.commit()

        share1_id = share1.id
        share2_id = share2.id

        # Delete the folder
        db_session.delete(sample_folder)
        db_session.commit()

        # Verify shares are deleted
        deleted_share1 = db_session.query(FolderShare).filter(
            FolderShare.id == share1_id
        ).first()
        deleted_share2 = db_session.query(FolderShare).filter(
            FolderShare.id == share2_id
        ).first()

        assert deleted_share1 is None
        assert deleted_share2 is None


class TestFolderShareRelationship:
    """Test relationship navigation (FolderShare -> Folder)."""

    def test_share_can_navigate_to_folder(self, db_session, sample_folder):
        """Test that a share can navigate to its parent folder."""
        share = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-1",
            user_username="user1",
            role=ShareRole.READER
        )
        db_session.add(share)
        db_session.commit()
        db_session.refresh(share)

        # Navigate from share to folder
        assert share.folder is not None
        assert share.folder.id == sample_folder.id
        assert share.folder.name == "Test Folder"

    def test_folder_can_access_shares(self, db_session, sample_folder):
        """Test that a folder can access its shares collection."""
        share1 = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-1",
            user_username="user1",
            role=ShareRole.READER
        )
        share2 = FolderShare(
            folder_id=sample_folder.id,
            user_id="user-uuid-2",
            user_username="user2",
            role=ShareRole.WRITER
        )
        db_session.add_all([share1, share2])
        db_session.commit()
        db_session.refresh(sample_folder)

        # Access shares from folder
        assert len(sample_folder.shares) == 2
        share_user_ids = {share.user_id for share in sample_folder.shares}
        assert "user-uuid-1" in share_user_ids
        assert "user-uuid-2" in share_user_ids
