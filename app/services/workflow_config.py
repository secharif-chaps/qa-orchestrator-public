from typing import List, Optional, Literal
from sqlalchemy.orm import Session
from app.models.workflow_config import WorkflowConfig
from pydantic import BaseModel, validator


class WorkflowConfigResponse(BaseModel):
    task_type: str
    title: str
    api_key_obfuscated: Optional[str]
    has_api_key: bool
    llm: str

    class Config:
        from_attributes = True


class WorkflowConfigUpdate(BaseModel):
    api_key: Optional[str] = None
    llm: Optional[Literal["claude", "mistral", "gpt"]] = None

    @validator('llm')
    def validate_llm(cls, v):
        if v is not None and v not in ["claude", "mistral", "gpt"]:
            raise ValueError("LLM must be one of: claude, mistral, gpt")
        return v


class WorkflowConfigService:
    def __init__(self, db: Session):
        self.db = db
    
    def obfuscate_api_key(self, api_key: Optional[str]) -> Optional[str]:
        """Obfuscate API key showing first 4 and last 4 characters"""
        if not api_key or len(api_key) < 8:
            return None
        return f"{api_key[:4]}{'*' * (len(api_key) - 8)}{api_key[-4:]}"
    
    def get_all_configs(self) -> List[WorkflowConfigResponse]:
        """Get all workflow configs with obfuscated API keys"""
        configs = self.db.query(WorkflowConfig).order_by(WorkflowConfig.task_type).all()

        return [
            WorkflowConfigResponse(
                task_type=config.task_type,
                title=config.title,
                api_key_obfuscated=self.obfuscate_api_key(config.api_key),
                has_api_key=bool(config.api_key),
                llm=config.llm
            )
            for config in configs
        ]
    
    def get_config_by_task_type(self, task_type: str) -> Optional[WorkflowConfig]:
        """Get workflow config by task type"""
        return self.db.query(WorkflowConfig).filter(
            WorkflowConfig.task_type == task_type
        ).first()
    
    def update_config(self, task_type: str, update_data: WorkflowConfigUpdate) -> Optional[WorkflowConfig]:
        """Update workflow config"""
        config = self.get_config_by_task_type(task_type)
        if not config:
            return None
        
        update_dict = update_data.dict(exclude_unset=True)
        for field, value in update_dict.items():
            setattr(config, field, value)
        
        self.db.commit()
        self.db.refresh(config)
        return config