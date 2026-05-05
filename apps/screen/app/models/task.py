from enum import StrEnum

from sqlalchemy import JSON, Column, DateTime, Float, ForeignKey, Integer, String
from sqlalchemy import Enum as SQLEnum
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func

from app.database import SCREEN_SCHEMA, Base


class TaskStatus(StrEnum):
    PENDING = "pending"
    RUNNING = "running"
    SUCCEEDED = "succeeded"
    ERROR = "error"


class TaskType(StrEnum):
    profile = "profile"
    digital = "digital"
    timeline = "timeline"
    products = "products"
    jobs = "jobs"
    csr = "csr"
    press = "press"
    team = "team"
    financial = "financial"
    corporate_structure = "corporate_structure"
    sanctions = "sanctions"
    patents = "patents"


class Task(Base):
    __tablename__ = "tasks"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    company_id = Column(Integer, ForeignKey(f"{SCREEN_SCHEMA}.companies.id"), nullable=False)

    # Organization-based multi-tenancy
    organization_id = Column(String, index=True, nullable=True)  # Keycloak organization UUID

    type = Column(
        SQLEnum(
            TaskType, values_callable=lambda obj: [e.value for e in obj], name="task_type_enum", schema=SCREEN_SCHEMA
        ),
        nullable=False,
    )
    status = Column(
        SQLEnum(
            TaskStatus,
            values_callable=lambda obj: [e.value for e in obj],
            name="task_status_enum",
            schema=SCREEN_SCHEMA,
        ),
        default=TaskStatus.PENDING,
    )
    error = Column(String, nullable=True)
    error_details = Column(JSON, nullable=True)  # Structured error info for frontend
    created_at = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at = Column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    # Token usage tracking
    input_tokens = Column(Integer, nullable=True)
    output_tokens = Column(Integer, nullable=True)
    total_cost = Column(Float, nullable=True)  # Cost in USD

    # Relationship with Company
    company = relationship("Company", back_populates="tasks")
