import pytest
from sqlalchemy.orm import Session
from app.infrastructure.database.database import get_db, SessionLocal, engine, Base

def test_get_db():
    """Test the get_db dependency function"""
    # Create a generator from get_db
    db_generator = get_db()
    
    # Get the first yielded value
    db = next(db_generator)
    
    # Verify it's a Session instance
    assert isinstance(db, Session)
    
    # Verify it's bound to our engine
    assert db.get_bind() is engine
    
    # Verify session is active before closing
    assert db.is_active
    
    # Test that the session is closed after use
    try:
        # This should raise StopIteration since the generator is done
        next(db_generator)
        pytest.fail("Expected StopIteration")
    except StopIteration:
        pass

def test_get_db_session_creation():
    """Test that get_db creates a new session each time"""
    # Get first session
    db_generator1 = get_db()
    db1 = next(db_generator1)
    
    # Get second session
    db_generator2 = get_db()
    db2 = next(db_generator2)
    
    # Verify they are different sessions
    assert db1 is not db2
    assert db1.is_active
    assert db2.is_active
    
    # Close sessions
    try:
        next(db_generator1)
    except StopIteration:
        pass
    try:
        next(db_generator2)
    except StopIteration:
        pass

def test_get_db_error_handling():
    """Test that get_db properly closes the session even if an error occurs"""
    db_generator = get_db()
    db = next(db_generator)
    
    try:
        assert db.is_active
        raise ValueError("Test error")
    except ValueError:
        pass
    finally:
        # Close the session
        try:
            next(db_generator)
        except StopIteration:
            pass

def test_get_db_session_attributes():
    """Test that the session has the correct configuration"""
    db_generator = get_db()
    db = next(db_generator)
    
    try:
        # Verify session configuration
        assert db.get_bind() is engine
        
        # Verify session is active
        assert db.is_active
        
        # Test session functionality
        db.begin()
        assert db.in_transaction()
        db.rollback()
        assert not db.in_transaction()
    finally:
        # Close the session
        try:
            next(db_generator)
        except StopIteration:
            pass 