from datetime import datetime
from sqlalchemy import Column, String, Integer, DateTime, JSON
from sqlalchemy.orm import relationship
from app.database import Base

class Company(Base):
    __tablename__ = "companies"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
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
    
    # Relationship with tasks
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan") 