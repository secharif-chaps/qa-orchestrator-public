from datetime import datetime
from sqlalchemy import Column, String, Integer, DateTime, JSON, Boolean
from sqlalchemy.orm import relationship
from app.database import Base

class Company(Base):
    __tablename__ = "companies"

    id = Column(Integer, primary_key=True, index=True)
    name = Column(String, index=True, nullable=False)
    website = Column(String, index=True, nullable=False)

    # Organization-based multi-tenancy via Keycloak Organizations
    organization_id = Column(String, index=True, nullable=False)  # Keycloak organization UUID

    # Owner fields - Keycloak user identification
    owner_id = Column(String, index=True, nullable=True)  # Keycloak user UUID (from JWT sub claim)
    owner_username = Column(String, index=True, nullable=True)  # Username for display (denormalized)

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

    # Raw knowledge fields from data collection task
    raw_mistral_knowledge = Column(String, nullable=True)
    raw_claude_knowledge = Column(String, nullable=True)
    raw_wikipedia_knowledge = Column(String, nullable=True)
    raw_scraped_website_knowledge = Column(String, nullable=True)

    # Error field
    error = Column(String, nullable=True)

    # Relationships
    tasks = relationship("Task", back_populates="company", cascade="all, delete-orphan") 