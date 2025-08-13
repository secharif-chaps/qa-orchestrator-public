from datetime import datetime
from sqlalchemy import Column, String, Integer, DateTime
from app.database import Base


class WorkflowConfig(Base):
    __tablename__ = "workflow_configs"
    
    id = Column(Integer, primary_key=True, index=True)
    task_type = Column(String(50), unique=True, nullable=False)
    title = Column(String(100), nullable=False)
    workflow_id = Column(String(100), nullable=True)
    api_key = Column(String(200), nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)