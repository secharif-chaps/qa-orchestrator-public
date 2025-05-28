from datetime import datetime
from enum import Enum
from sqlalchemy import Column, String, Integer, DateTime, ForeignKey, Enum as SQLEnum
from sqlalchemy.orm import relationship
from app.infrastructure.database.database import Base

class TaskStatus(str, Enum):
    PENDING = "pending"
    RUNNING = "running"
    SUCCEEDED = "succeeded"
    ERROR = "error"

class TaskType(str, Enum):
    profile = "profile"
    digital = "digital"
    timeline = "timeline"
    products = "products"
    jobs = "jobs"
    csr = "csr"
    press = "press"
    team = "team"

    @classmethod
    def _missing_(cls, value):
        """Handle case-insensitive enum values"""
        value = value.lower()
        for member in cls:
            if member.value == value:
                return member
        return None

class Task(Base):
    __tablename__ = "tasks"
    
    id = Column(Integer, primary_key=True, index=True)
    company_id = Column(Integer, ForeignKey("companies.id"), nullable=False)
    type = Column(SQLEnum(TaskType, values_callable=lambda obj: [e.value for e in obj], name="task_type_enum"), nullable=False)
    status = Column(SQLEnum(TaskStatus, values_callable=lambda obj: [e.value for e in obj], name="task_status_enum"), default=TaskStatus.PENDING)
    error = Column(String, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    
    # Relationship with Company
    company = relationship("Company", back_populates="tasks")
    
    def __init__(self, company_id: int, type: TaskType):
        self.company_id = company_id
        self.type = type
        self.status = TaskStatus.PENDING
        self.error = None 