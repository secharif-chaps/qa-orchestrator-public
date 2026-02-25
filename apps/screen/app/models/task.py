from enum import Enum
from sqlalchemy import Column, String, Integer, DateTime, ForeignKey, Enum as SQLEnum, Float, Boolean, UniqueConstraint
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from app.database import Base

class TaskStatus(str, Enum):
    PENDING = "pending"
    BLOCKED = "blocked"  # Waiting for prerequisite tasks to complete
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
    data_collection = "data_collection"

class Task(Base):
    __tablename__ = "tasks"

    id = Column(Integer, primary_key=True, index=True)
    company_id = Column(Integer, ForeignKey("companies.id"), nullable=False)

    # Organization-based multi-tenancy
    organization_id = Column(String, index=True, nullable=True)  # Keycloak organization UUID

    type = Column(SQLEnum(TaskType, values_callable=lambda obj: [e.value for e in obj], name="task_type_enum"), nullable=False)
    status = Column(SQLEnum(TaskStatus, values_callable=lambda obj: [e.value for e in obj], name="task_status_enum"), default=TaskStatus.PENDING)
    error = Column(String, nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Token usage tracking (optional)
    input_tokens = Column(Integer, nullable=True)
    output_tokens = Column(Integer, nullable=True)
    total_cost = Column(Float, nullable=True)  # Cost in USD

    # Prerequisite flag - marks tasks that must complete before others can run
    is_prerequisite = Column(Boolean, default=False, nullable=False, index=True)

    # Relationship with Company
    company = relationship("Company", back_populates="tasks")

    # Task dependency relationships
    dependencies = relationship(
        "TaskDependency",
        foreign_keys="TaskDependency.task_id",
        back_populates="task",
        cascade="all, delete-orphan"
    )
    dependents = relationship(
        "TaskDependency",
        foreign_keys="TaskDependency.depends_on_task_id",
        back_populates="prerequisite_task"
    )


class TaskDependency(Base):
    __tablename__ = "task_dependencies"
    __table_args__ = (
        UniqueConstraint('task_id', 'depends_on_task_id', name='uq_task_dependency'),
    )

    id = Column(Integer, primary_key=True, index=True)
    task_id = Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), nullable=False, index=True)
    depends_on_task_id = Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), nullable=False, index=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    # Relationships
    task = relationship("Task", foreign_keys=[task_id], back_populates="dependencies")
    prerequisite_task = relationship("Task", foreign_keys=[depends_on_task_id], back_populates="dependents") 