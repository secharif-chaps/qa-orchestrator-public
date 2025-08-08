from typing import Optional, List
from sqlalchemy.orm import Session
from sqlalchemy.exc import IntegrityError
from fastapi import HTTPException, status
from app.models.workspace import WorkspaceModule, ModuleName, Workspace
from app.schemas.module import TokenError
import logging

logger = logging.getLogger(__name__)


class InsufficientTokensException(HTTPException):
    def __init__(self, module: ModuleName, current_tokens: int, required_tokens: int = 1):
        error_detail = TokenError(
            message=f"Not enough tokens for {module.value} operation",
            current_tokens=current_tokens,
            required_tokens=required_tokens,
            module=module
        )
        super().__init__(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=error_detail.model_dump()
        )


class TokenManager:
    def __init__(self, db: Session):
        self.db = db

    def get_or_create_module(self, workspace_id: int, module_name: ModuleName) -> WorkspaceModule:
        """Get or create a workspace module configuration"""
        module = self.db.query(WorkspaceModule).filter(
            WorkspaceModule.workspace_id == workspace_id,
            WorkspaceModule.module_name == module_name
        ).first()
        
        if not module:
            module = WorkspaceModule(
                workspace_id=workspace_id,
                module_name=module_name,
                enabled=False,
                token_count=0
            )
            self.db.add(module)
            try:
                self.db.commit()
            except IntegrityError:
                self.db.rollback()
                # Handle race condition - another process might have created it
                module = self.db.query(WorkspaceModule).filter(
                    WorkspaceModule.workspace_id == workspace_id,
                    WorkspaceModule.module_name == module_name
                ).first()
                if not module:
                    raise
            self.db.refresh(module)
        
        return module

    def get_module_tokens(self, workspace_id: int, module_name: ModuleName) -> Optional[WorkspaceModule]:
        """Get current token count for a module"""
        return self.db.query(WorkspaceModule).filter(
            WorkspaceModule.workspace_id == workspace_id,
            WorkspaceModule.module_name == module_name
        ).first()

    def get_all_workspace_modules(self, workspace_id: int) -> List[WorkspaceModule]:
        """Get all modules for a workspace"""
        # First ensure all modules exist for this workspace
        for module_name in ModuleName:
            self.get_or_create_module(workspace_id, module_name)
        
        return self.db.query(WorkspaceModule).filter(
            WorkspaceModule.workspace_id == workspace_id
        ).all()

    def update_module_config(self, workspace_id: int, module_name: ModuleName, 
                           enabled: Optional[bool] = None, token_count: Optional[int] = None) -> WorkspaceModule:
        """Update module configuration"""
        module = self.get_or_create_module(workspace_id, module_name)
        
        if enabled is not None:
            module.enabled = enabled
        if token_count is not None:
            module.token_count = max(0, token_count)  # Ensure non-negative
        
        self.db.commit()
        self.db.refresh(module)
        return module

    def add_tokens(self, workspace_id: int, module_name: ModuleName, tokens: int) -> WorkspaceModule:
        """Add tokens to a module"""
        if tokens <= 0:
            raise ValueError("Token count must be positive")
        
        module = self.get_or_create_module(workspace_id, module_name)
        module.token_count += tokens
        
        self.db.commit()
        self.db.refresh(module)
        return module

    def consume_tokens(self, workspace_id: int, module_name: ModuleName, tokens: int = 1) -> WorkspaceModule:
        """
        Consume tokens from a module with immediate deduction and rollback capability
        Returns the updated module or raises InsufficientTokensException
        """
        if tokens <= 0:
            raise ValueError("Token consumption must be positive")
        
        # Lock the row for update to prevent race conditions
        module = self.db.query(WorkspaceModule).filter(
            WorkspaceModule.workspace_id == workspace_id,
            WorkspaceModule.module_name == module_name
        ).with_for_update().first()
        
        if not module:
            module = self.get_or_create_module(workspace_id, module_name)
            # Re-query with lock after creation
            module = self.db.query(WorkspaceModule).filter(
                WorkspaceModule.workspace_id == workspace_id,
                WorkspaceModule.module_name == module_name
            ).with_for_update().first()
        
        # Check if module is enabled
        if not module.enabled:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Module {module_name.value} is not enabled for this workspace"
            )
        
        # Check if enough tokens are available
        if module.token_count < tokens:
            raise InsufficientTokensException(
                module=module_name,
                current_tokens=module.token_count,
                required_tokens=tokens
            )
        
        # Immediately deduct tokens
        module.token_count -= tokens
        self.db.commit()
        
        logger.info(f"Consumed {tokens} tokens from {module_name.value} in workspace {workspace_id}. "
                   f"Remaining: {module.token_count}")
        
        self.db.refresh(module)
        return module

    def rollback_tokens(self, workspace_id: int, module_name: ModuleName, tokens: int = 1) -> WorkspaceModule:
        """
        Rollback tokens to a module (e.g., when an operation fails after token consumption)
        """
        if tokens <= 0:
            raise ValueError("Token rollback must be positive")
        
        module = self.db.query(WorkspaceModule).filter(
            WorkspaceModule.workspace_id == workspace_id,
            WorkspaceModule.module_name == module_name
        ).with_for_update().first()
        
        if module:
            module.token_count += tokens
            self.db.commit()
            self.db.refresh(module)
            
            logger.info(f"Rolled back {tokens} tokens to {module_name.value} in workspace {workspace_id}. "
                       f"Total: {module.token_count}")
        
        return module

    def validate_workspace_access(self, workspace_id: int) -> bool:
        """Validate that the workspace exists"""
        workspace = self.db.query(Workspace).filter(Workspace.id == workspace_id).first()
        return workspace is not None