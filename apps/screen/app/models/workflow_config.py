from datetime import datetime

from sqlalchemy import Column, DateTime, Integer, String

from app.database import SCREEN_SCHEMA, Base


class WorkflowConfig(Base):
    __tablename__ = "workflow_configs"
    __table_args__ = {"schema": SCREEN_SCHEMA}

    id = Column(Integer, primary_key=True, index=True)
    task_type = Column(String(50), unique=True, nullable=False)
    title = Column(String(100), nullable=False)
    api_key = Column(String(200), nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
