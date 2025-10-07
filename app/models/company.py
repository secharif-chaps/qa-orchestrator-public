from datetime import datetime
from sqlalchemy import Column, String, Integer, DateTime, JSON, ForeignKey, Boolean
from sqlalchemy.orm import relationship
from app.database import Base

class Company(Base):
    __tablename__ = "companies"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)
    owner_username = Column(String, index=True, nullable=False)  # User who searched this company
    workspace_id = Column(Integer, ForeignKey("workspaces.id"), nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    is_deleted = Column(Boolean, default=False, nullable=False)
    
    # JSON fields for complex data structures
    profile = Column(JSON, default=dict)
    digital = Column(JSON, default=dict)
    timeline = Column(JSON, default=dict)
    products = Column(JSON, default=dict)
    jobs = Column(JSON, default=dict)
    csr = Column(JSON, default=dict)
    press = Column(JSON, default=dict)
    team = Column(JSON, default=list)
    
    # Error field
    error = Column(String, nullable=True)
    
    # Relationships
    workspace = relationship("Workspace", back_populates="companies")
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan") 