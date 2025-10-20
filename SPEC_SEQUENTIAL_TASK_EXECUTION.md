# Feature Spec: Sequential Task Execution with Data Collection Prerequisite

**Status**: Draft
**Created**: 2025-10-20
**Author**: Claude Code
**Version**: 1.0

---

## 1. Overview

### 1.1 Problem Statement

Currently, when a company is created, all 9 tasks (profile, digital, csr, press, timeline, products, team, jobs, data_collection) are triggered simultaneously and execute in parallel. This approach has several issues:

1. **Redundant data collection**: Each task independently fetches similar information from multiple sources (Mistral, Claude, Wikipedia, web scraping)
2. **Inefficient resource usage**: Multiple parallel workflows performing overlapping work
3. **Lack of context sharing**: Tasks cannot benefit from knowledge gathered by other tasks
4. **No dependency management**: Tasks that could benefit from prerequisite data run without it

### 1.2 Proposed Solution

Implement a **sequential task execution system** where:

1. **data_collection runs first** as a prerequisite task
2. When data_collection completes successfully, it **triggers the remaining 8 tasks**
3. All tasks can access the collected raw knowledge data (Mistral, Claude, Wikipedia, scraped website)
4. The system is extensible for future task dependencies

### 1.3 Benefits

- **Cost reduction**: Single data collection phase instead of 9 duplicate collections
- **Improved data quality**: All tasks work from the same knowledge base
- **Better resource utilization**: Reduced API calls to external services
- **Faster overall completion**: Less redundant work means faster results
- **Extensibility**: Foundation for complex task dependency graphs

---

## 2. Current System Architecture

### 2.1 Current Flow

```
Company Created
    ↓
Create 9 Tasks (all status: PENDING)
    ↓
Queue all 9 tasks simultaneously
    ↓
All tasks run in parallel
    ↓
Each task independently collects data
    ↓
Tasks complete independently
```

### 2.2 Current Database Schema

**Companies Table**:
- `raw_mistral_knowledge` (VARCHAR) - Raw data from Mistral
- `raw_claude_knowledge` (VARCHAR) - Raw data from Claude
- `raw_wikipedia_knowledge` (VARCHAR) - Raw data from Wikipedia
- `raw_scraped_website_knowledge` (VARCHAR) - Raw data from web scraping

**Tasks Table**:
- `id` (INTEGER, PK)
- `company_id` (INTEGER, FK)
- `type` (ENUM: TaskType)
- `status` (ENUM: TaskStatus - PENDING, RUNNING, SUCCEEDED, ERROR)
- `error` (VARCHAR, nullable)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)
- `input_tokens` (INTEGER, nullable)
- `output_tokens` (INTEGER, nullable)
- `total_cost` (FLOAT, nullable)

### 2.3 Current Code Locations

**Backend**:
- Task creation: `app/services/company.py:215-292` (create_company method)
- Task execution: `app/services/company.py:355-391` (_execute_task method)
- Dify client: `app/infrastructure/dify/client.py:38-206` (trigger_workflow method)
- Webhook callback: `app/api/endpoints/webhooks.py:130-293` (dify_task_callback)
- Celery worker: `app/workers/dify_tasks.py`

**Frontend**:
- Task types: `src/types/task.ts:1-10`
- Task flow modal: `src/components/company/TasksFlowModal.vue`

---

## 3. Proposed Architecture

### 3.1 New Flow

```
Company Created
    ↓
Create 9 Tasks (all status: PENDING)
    ↓
Queue ONLY data_collection task
    ↓
data_collection runs (status: RUNNING)
    ↓
data_collection completes (status: SUCCEEDED)
    ↓
Webhook callback received
    ↓
Callback triggers remaining 8 tasks
    ↓
8 tasks run in parallel (with access to collected knowledge)
    ↓
All tasks complete
```

### 3.2 Task Dependency Model

We need to add task dependency tracking to the database:

**New: task_dependencies Table**
```sql
CREATE TABLE task_dependencies (
    id SERIAL PRIMARY KEY,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    depends_on_task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(task_id, depends_on_task_id)
);

CREATE INDEX idx_task_dependencies_task_id ON task_dependencies(task_id);
CREATE INDEX idx_task_dependencies_depends_on_task_id ON task_dependencies(depends_on_task_id);
```

**Alternative: Add to Tasks Table**
```sql
ALTER TABLE tasks ADD COLUMN depends_on_task_id INTEGER REFERENCES tasks(id);
ALTER TABLE tasks ADD COLUMN is_prerequisite BOOLEAN DEFAULT FALSE;
```

**Recommended**: Use the separate `task_dependencies` table for better flexibility (supports multiple dependencies in the future).

### 3.3 New Task Status Flow

```
PENDING → BLOCKED → RUNNING → SUCCEEDED/ERROR
            ↑
            └── Waiting for prerequisite task(s) to complete
```

Add new status: `BLOCKED` - Task is waiting for dependencies

**Updated TaskStatus Enum**:
```python
class TaskStatus(str, Enum):
    PENDING = "pending"
    BLOCKED = "blocked"  # NEW
    RUNNING = "running"
    SUCCEEDED = "succeeded"
    ERROR = "error"
```

---

## 4. Implementation Details

### 4.1 Database Changes

#### Migration: Add Task Dependencies Support

**File**: `alembic/versions/YYYYMMDD_add_task_dependencies.py`

```python
"""Add task dependencies and blocked status

Revision ID: add_task_dependencies
Revises: previous_revision
Create Date: 2025-10-20
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

def upgrade():
    # Add BLOCKED status to task_status_enum
    op.execute("ALTER TYPE task_status_enum ADD VALUE 'blocked'")

    # Create task_dependencies table
    op.create_table(
        'task_dependencies',
        sa.Column('id', sa.Integer(), primary_key=True),
        sa.Column('task_id', sa.Integer(), nullable=False),
        sa.Column('depends_on_task_id', sa.Integer(), nullable=False),
        sa.Column('created_at', sa.DateTime(), server_default=sa.text('now()'), nullable=False),
        sa.ForeignKeyConstraint(['task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.ForeignKeyConstraint(['depends_on_task_id'], ['tasks.id'], ondelete='CASCADE'),
        sa.UniqueConstraint('task_id', 'depends_on_task_id', name='uq_task_dependency')
    )

    # Create indexes
    op.create_index('idx_task_dependencies_task_id', 'task_dependencies', ['task_id'])
    op.create_index('idx_task_dependencies_depends_on_task_id', 'task_dependencies', ['depends_on_task_id'])

    # Add is_prerequisite flag to tasks table (for easier querying)
    op.add_column('tasks', sa.Column('is_prerequisite', sa.Boolean(), server_default='false', nullable=False))
    op.create_index('idx_tasks_is_prerequisite', 'tasks', ['is_prerequisite'])

def downgrade():
    # Remove index and column
    op.drop_index('idx_tasks_is_prerequisite')
    op.drop_column('tasks', 'is_prerequisite')

    # Drop indexes
    op.drop_index('idx_task_dependencies_depends_on_task_id')
    op.drop_index('idx_task_dependencies_task_id')

    # Drop table
    op.drop_table('task_dependencies')

    # Note: Cannot remove enum value in PostgreSQL without recreating the type
    # This is acceptable as 'blocked' status will simply not be used
```

### 4.2 Backend Model Changes

#### File: `app/models/task.py`

```python
from datetime import datetime
from enum import Enum
from sqlalchemy import Column, String, Integer, DateTime, ForeignKey, Enum as SQLEnum, Float, Boolean
from sqlalchemy.orm import relationship
from app.database import Base

class TaskStatus(str, Enum):
    PENDING = "pending"
    BLOCKED = "blocked"  # NEW: Waiting for dependencies
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
    type = Column(SQLEnum(TaskType, values_callable=lambda obj: [e.value for e in obj], name="task_type_enum"), nullable=False)
    status = Column(SQLEnum(TaskStatus, values_callable=lambda obj: [e.value for e in obj], name="task_status_enum"), default=TaskStatus.PENDING)
    error = Column(String, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    # Token usage tracking
    input_tokens = Column(Integer, nullable=True)
    output_tokens = Column(Integer, nullable=True)
    total_cost = Column(Float, nullable=True)

    # NEW: Prerequisite flag
    is_prerequisite = Column(Boolean, default=False, nullable=False, index=True)

    # Relationships
    company = relationship("Company", back_populates="tasks")
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

    id = Column(Integer, primary_key=True, index=True)
    task_id = Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), nullable=False, index=True)
    depends_on_task_id = Column(Integer, ForeignKey("tasks.id", ondelete="CASCADE"), nullable=False, index=True)
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)

    # Relationships
    task = relationship("Task", foreign_keys=[task_id], back_populates="dependencies")
    prerequisite_task = relationship("Task", foreign_keys=[depends_on_task_id], back_populates="dependents")
```

### 4.3 Backend Service Changes

#### File: `app/services/task_dependency_service.py` (NEW)

```python
"""
Service for managing task dependencies and execution order
"""
from typing import List, Optional
from sqlalchemy.orm import Session
from app.models.task import Task, TaskDependency, TaskStatus, TaskType
import logging

logger = logging.getLogger(__name__)

class TaskDependencyService:
    """Manages task dependencies and determines execution order"""

    def __init__(self, db: Session):
        self.db = db

    def create_dependency(self, task_id: int, depends_on_task_id: int) -> TaskDependency:
        """Create a dependency relationship between two tasks"""
        dependency = TaskDependency(
            task_id=task_id,
            depends_on_task_id=depends_on_task_id
        )
        self.db.add(dependency)
        self.db.commit()
        self.db.refresh(dependency)

        # Update task status to BLOCKED if prerequisite is not complete
        task = self.db.query(Task).filter(Task.id == task_id).first()
        prerequisite = self.db.query(Task).filter(Task.id == depends_on_task_id).first()

        if prerequisite.status != TaskStatus.SUCCEEDED:
            task.status = TaskStatus.BLOCKED
            self.db.commit()

        logger.info(f"Created dependency: Task {task_id} depends on Task {depends_on_task_id}")
        return dependency

    def get_dependencies(self, task_id: int) -> List[Task]:
        """Get all prerequisite tasks for a given task"""
        dependencies = (
            self.db.query(TaskDependency)
            .filter(TaskDependency.task_id == task_id)
            .all()
        )

        prerequisite_ids = [dep.depends_on_task_id for dep in dependencies]
        prerequisites = (
            self.db.query(Task)
            .filter(Task.id.in_(prerequisite_ids))
            .all()
        )

        return prerequisites

    def get_dependent_tasks(self, task_id: int) -> List[Task]:
        """Get all tasks that depend on this task"""
        dependencies = (
            self.db.query(TaskDependency)
            .filter(TaskDependency.depends_on_task_id == task_id)
            .all()
        )

        dependent_ids = [dep.task_id for dep in dependencies]
        dependents = (
            self.db.query(Task)
            .filter(Task.id.in_(dependent_ids))
            .all()
        )

        return dependents

    def can_task_run(self, task_id: int) -> bool:
        """Check if all prerequisites for a task are satisfied"""
        prerequisites = self.get_dependencies(task_id)

        if not prerequisites:
            return True  # No dependencies, can run

        # All prerequisites must be SUCCEEDED
        return all(prereq.status == TaskStatus.SUCCEEDED for prereq in prerequisites)

    def get_ready_tasks(self, company_id: int) -> List[Task]:
        """Get all tasks for a company that are ready to run (prerequisites satisfied)"""
        tasks = (
            self.db.query(Task)
            .filter(Task.company_id == company_id)
            .filter(Task.status.in_([TaskStatus.PENDING, TaskStatus.BLOCKED]))
            .all()
        )

        ready_tasks = []
        for task in tasks:
            if self.can_task_run(task.id):
                ready_tasks.append(task)

        return ready_tasks

    def unblock_dependent_tasks(self, completed_task_id: int) -> List[Task]:
        """
        When a task completes, check its dependents and unblock any that are ready
        Returns list of tasks that were unblocked
        """
        dependent_tasks = self.get_dependent_tasks(completed_task_id)
        unblocked_tasks = []

        for task in dependent_tasks:
            if task.status == TaskStatus.BLOCKED and self.can_task_run(task.id):
                task.status = TaskStatus.PENDING
                unblocked_tasks.append(task)
                logger.info(f"Unblocked task {task.id} ({task.type.value}) - prerequisites satisfied")

        self.db.commit()
        return unblocked_tasks
```

#### File: `app/services/company.py` (MODIFIED)

**Changes to `create_company` method**:

```python
def create_company(self, name: str, website: str, owner_username: str, workspace_id: int) -> Company:
    """Securely create a new company"""
    logger.info(f"🏭 Creating company: {name}, Owner: {owner_username}, Workspace: {workspace_id}")

    # Validation
    if not owner_username or len(owner_username) > 100:
        raise ValidationError("Invalid owner username")

    # Create company
    company = self.secure_query.safe_create_entity(
        Company,
        name=name,
        website=website,
        owner_username=owner_username,
        workspace_id=workspace_id
    )
    logger.info(f"✅ Company entity created - ID: {company.id}")

    # Define task configurations with dependency information
    task_configs = [
        {'type': 'data_collection', 'is_prerequisite': True},  # Must run first
        {'type': 'profile', 'is_prerequisite': False},
        {'type': 'digital', 'is_prerequisite': False},
        {'type': 'csr', 'is_prerequisite': False},
        {'type': 'press', 'is_prerequisite': False},
        {'type': 'timeline', 'is_prerequisite': False},
        {'type': 'products', 'is_prerequisite': False},
        {'type': 'team', 'is_prerequisite': False},
        {'type': 'jobs', 'is_prerequisite': False},
    ]

    # Create all tasks with appropriate initial status
    prerequisite_task = None
    dependent_tasks = []

    for config in task_configs:
        if config['is_prerequisite']:
            # Prerequisite task starts as PENDING (ready to run)
            task = Task(
                company_id=company.id,
                type=TaskType(config['type']),
                status=TaskStatus.PENDING,
                is_prerequisite=True
            )
            prerequisite_task = task
        else:
            # Dependent tasks start as BLOCKED (waiting for prerequisite)
            task = Task(
                company_id=company.id,
                type=TaskType(config['type']),
                status=TaskStatus.BLOCKED,
                is_prerequisite=False
            )
            dependent_tasks.append(task)

        company.tasks.append(task)

    self.db.commit()
    self.db.refresh(company)

    # Create dependency relationships
    dependency_service = TaskDependencyService(self.db)
    for dependent_task in dependent_tasks:
        dependency_service.create_dependency(
            task_id=dependent_task.id,
            depends_on_task_id=prerequisite_task.id
        )

    # Queue ONLY the prerequisite task (data_collection)
    workflow_config = self.db.query(WorkflowConfig).filter(
        WorkflowConfig.task_type == prerequisite_task.type.value
    ).first()

    if not workflow_config or not workflow_config.workflow_id or not workflow_config.api_key:
        logger.error(f"Invalid workflow configuration for {prerequisite_task.type.value}")
        prerequisite_task.status = TaskStatus.ERROR
        prerequisite_task.error = f"No workflow configuration found"
        self.db.commit()
    else:
        logger.info(f"Queueing prerequisite task {prerequisite_task.id} ({prerequisite_task.type.value})")
        execute_dify_workflow.delay(
            task_id=prerequisite_task.id,
            company_id=company.id,
            task_type=prerequisite_task.type.value,
            workflow_id=workflow_config.workflow_id,
            api_key=workflow_config.api_key,
            llm=workflow_config.llm
        )

    logger.info(f"Created company '{name}' with 1 prerequisite task and 8 dependent tasks")
    return company
```

### 4.4 Webhook Changes

#### File: `app/api/endpoints/webhooks.py` (MODIFIED)

**Update `dify_task_callback` to trigger dependent tasks**:

```python
@router.post("/dify/tasks/{task_id}/callback")
async def dify_task_callback(
    task_id: int,
    request: Request,
    service: CompanyService = Depends(get_company_service)
):
    """
    Flexible webhook endpoint for Dify callbacks
    NOW: Triggers dependent tasks when prerequisite completes
    """
    logger.info(f"🔄 Dify callback received for Task ID: {task_id}")

    try:
        # ... (existing callback processing code) ...

        # Update task and company based on result
        if success:
            task.status = TaskStatus.SUCCEEDED
            task.error = None

            if task_data:
                logger.info(f"Updating company data for task type: {task.type.value}")
                service._update_company_data(company, task.type.value, task_data)

            logger.info(f"✅ Task {task_id} completed successfully via Dify callback")

            # NEW: If this was a prerequisite task, trigger dependent tasks
            if task.is_prerequisite:
                logger.info(f"🔓 Task {task_id} is a prerequisite - checking for dependent tasks")
                dependency_service = TaskDependencyService(service.db)
                unblocked_tasks = dependency_service.unblock_dependent_tasks(task_id)

                if unblocked_tasks:
                    logger.info(f"🚀 Triggering {len(unblocked_tasks)} unblocked tasks")

                    # Queue all unblocked tasks
                    for unblocked_task in unblocked_tasks:
                        workflow_config = service.db.query(WorkflowConfig).filter(
                            WorkflowConfig.task_type == unblocked_task.type.value
                        ).first()

                        if not workflow_config or not workflow_config.workflow_id:
                            logger.error(f"No workflow config for {unblocked_task.type.value}")
                            unblocked_task.status = TaskStatus.ERROR
                            unblocked_task.error = "No workflow configuration found"
                            service.db.commit()
                            continue

                        logger.info(f"Queueing task {unblocked_task.id} ({unblocked_task.type.value})")
                        execute_dify_workflow.delay(
                            task_id=unblocked_task.id,
                            company_id=company.id,
                            task_type=unblocked_task.type.value,
                            workflow_id=workflow_config.workflow_id,
                            api_key=workflow_config.api_key,
                            llm=workflow_config.llm
                        )
                else:
                    logger.info(f"No dependent tasks to unblock for task {task_id}")
        else:
            task.status = TaskStatus.ERROR
            task.error = error_msg or "Task failed without specific error message"
            logger.error(f"❌ Task {task_id} failed via Dify callback: {task.error}")

            # NEW: If prerequisite task failed, mark dependent tasks as error
            if task.is_prerequisite:
                logger.error(f"🚫 Prerequisite task {task_id} failed - marking dependent tasks as error")
                dependency_service = TaskDependencyService(service.db)
                dependent_tasks = dependency_service.get_dependent_tasks(task_id)

                for dependent_task in dependent_tasks:
                    dependent_task.status = TaskStatus.ERROR
                    dependent_task.error = f"Prerequisite task '{task.type.value}' failed"
                    logger.error(f"Marking task {dependent_task.id} ({dependent_task.type.value}) as error")

                service.db.commit()

        # ... (rest of existing code) ...
```

### 4.5 Frontend Changes

#### File: `src/types/task.ts`

```typescript
export type TaskType =
  | 'profile'
  | 'digital'
  | 'timeline'
  | 'products'
  | 'jobs'
  | 'csr'
  | 'press'
  | 'team'
  | 'data_collection'

export type TaskStatus = 'pending' | 'blocked' | 'running' | 'succeeded' | 'error'  // Added 'blocked'

export interface TaskBase {
  type: TaskType
  status: TaskStatus
  error?: string | null
  is_prerequisite?: boolean  // NEW
}

export interface TaskCreate extends TaskBase {
  company_id: number
}

export interface TaskResponse extends TaskBase {
  id: number
  company_id: number
  created_at: string
  updated_at: string
  input_tokens?: number | null
  output_tokens?: number | null
  total_cost?: number | null
  is_prerequisite?: boolean  // NEW
}

// NEW: Interface for task dependencies
export interface TaskDependency {
  id: number
  task_id: number
  depends_on_task_id: number
  created_at: string
}
```

#### File: `src/components/company/TasksFlowModal.vue` (MODIFIED)

```vue
<script setup lang="ts">
// ... existing imports ...

// Update getStatusVariant to handle blocked status
const getStatusVariant = (status: TaskStatus | null) => {
  switch (status) {
    case 'succeeded':
      return 'success'
    case 'error':
      return 'error'
    case 'running':
      return 'warning'
    case 'blocked':  // NEW
      return 'slate'
    case 'pending':
      return 'info'
    default:
      return 'accent'
  }
}

// Update getStatusLabel to handle blocked status
const getStatusLabel = (status: TaskStatus | null): string => {
  switch (status) {
    case 'succeeded':
      return 'Terminée'
    case 'error':
      return 'Erreur'
    case 'running':
      return 'En cours'
    case 'blocked':  // NEW
      return 'En attente (bloquée)'
    case 'pending':
      return 'En attente'
    default:
      return 'Non démarrée'
  }
}

// Update progress calculations to include blocked status
const blockedCount = computed(() => {
  return tasks.value?.filter((t: TaskResponse) => t.status === 'blocked').length || 0
})

// Update the status summary section in template
</script>

<template>
  <!-- ... existing template ... -->

  <!-- Updated status summary with blocked status -->
  <div class="flex items-center justify-between mt-3 text-xs text-secondary">
    <div class="flex items-center gap-4">
      <span class="flex items-center gap-1.5">
        <div class="w-2 h-2 bg-success-500 rounded-full"></div>
        {{ completedCount }} terminées
      </span>
      <span v-if="runningCount > 0" class="flex items-center gap-1.5">
        <div class="w-2 h-2 bg-warning-500 rounded-full"></div>
        {{ runningCount }} en cours
      </span>
      <span v-if="blockedCount > 0" class="flex items-center gap-1.5">
        <div class="w-2 h-2 bg-slate-400 rounded-full"></div>
        {{ blockedCount }} bloquées
      </span>
      <span v-if="errorCount > 0" class="flex items-center gap-1.5">
        <div class="w-2 h-2 bg-error-500 rounded-full"></div>
        {{ errorCount }} en erreur
      </span>
      <span v-if="pendingCount > 0" class="flex items-center gap-1.5">
        <div class="w-2 h-2 bg-secondary rounded-full"></div>
        {{ pendingCount }} en attente
      </span>
    </div>
  </div>
</template>
```

---

## 5. API Changes

### 5.1 New Endpoints (Optional - for debugging/admin)

#### GET `/api/tasks/{task_id}/dependencies`
Get all prerequisite tasks for a given task.

**Response**:
```json
{
  "task_id": 123,
  "dependencies": [
    {
      "id": 122,
      "type": "data_collection",
      "status": "succeeded",
      "is_prerequisite": true
    }
  ]
}
```

#### GET `/api/tasks/{task_id}/dependents`
Get all tasks that depend on this task.

**Response**:
```json
{
  "task_id": 122,
  "dependents": [
    {
      "id": 123,
      "type": "profile",
      "status": "blocked"
    },
    {
      "id": 124,
      "type": "digital",
      "status": "blocked"
    }
    // ... 6 more tasks
  ]
}
```

### 5.2 Modified Endpoints

#### POST `/api/companies/` (Company Creation)
**No API contract changes**, but behavior changes:
- Only queues `data_collection` task immediately
- Other tasks remain `BLOCKED` until data_collection succeeds

---

## 6. Testing Strategy

### 6.1 Unit Tests

**File**: `tests/unit/services/test_task_dependency_service.py`

```python
import pytest
from app.services.task_dependency_service import TaskDependencyService
from app.models.task import Task, TaskStatus, TaskType

def test_create_dependency(db_session, company_fixture):
    """Test creating a dependency relationship"""
    service = TaskDependencyService(db_session)

    task1 = Task(company_id=company_fixture.id, type=TaskType.data_collection, status=TaskStatus.PENDING)
    task2 = Task(company_id=company_fixture.id, type=TaskType.profile, status=TaskStatus.PENDING)
    db_session.add_all([task1, task2])
    db_session.commit()

    dependency = service.create_dependency(task2.id, task1.id)

    assert dependency.task_id == task2.id
    assert dependency.depends_on_task_id == task1.id

    # Task 2 should be blocked since task 1 is not complete
    db_session.refresh(task2)
    assert task2.status == TaskStatus.BLOCKED

def test_can_task_run(db_session, company_fixture):
    """Test checking if a task can run based on dependencies"""
    service = TaskDependencyService(db_session)

    prereq = Task(company_id=company_fixture.id, type=TaskType.data_collection, status=TaskStatus.SUCCEEDED)
    task = Task(company_id=company_fixture.id, type=TaskType.profile, status=TaskStatus.BLOCKED)
    db_session.add_all([prereq, task])
    db_session.commit()

    service.create_dependency(task.id, prereq.id)

    assert service.can_task_run(task.id) is True

def test_unblock_dependent_tasks(db_session, company_fixture):
    """Test unblocking tasks when prerequisite completes"""
    service = TaskDependencyService(db_session)

    prereq = Task(company_id=company_fixture.id, type=TaskType.data_collection, status=TaskStatus.RUNNING)
    task1 = Task(company_id=company_fixture.id, type=TaskType.profile, status=TaskStatus.BLOCKED)
    task2 = Task(company_id=company_fixture.id, type=TaskType.digital, status=TaskStatus.BLOCKED)
    db_session.add_all([prereq, task1, task2])
    db_session.commit()

    service.create_dependency(task1.id, prereq.id)
    service.create_dependency(task2.id, prereq.id)

    # Mark prerequisite as succeeded
    prereq.status = TaskStatus.SUCCEEDED
    db_session.commit()

    unblocked = service.unblock_dependent_tasks(prereq.id)

    assert len(unblocked) == 2
    assert all(t.status == TaskStatus.PENDING for t in unblocked)
```

### 6.2 Integration Tests

**File**: `tests/integration/test_sequential_task_execution.py`

```python
import pytest
from app.services.company import CompanyService

@pytest.mark.asyncio
async def test_company_creation_queues_only_data_collection(db_session, test_user):
    """Test that company creation only queues data_collection task"""
    service = CompanyService(db_session)

    company = service.create_company(
        name="Test Company",
        website="https://test.com",
        owner_username=test_user.username,
        workspace_id=test_user.workspace_id
    )

    # Should have 9 tasks total
    assert len(company.tasks) == 9

    # data_collection should be PENDING
    data_collection_task = next(t for t in company.tasks if t.type.value == 'data_collection')
    assert data_collection_task.status == TaskStatus.PENDING
    assert data_collection_task.is_prerequisite is True

    # All other tasks should be BLOCKED
    other_tasks = [t for t in company.tasks if t.type.value != 'data_collection']
    assert all(t.status == TaskStatus.BLOCKED for t in other_tasks)
    assert all(t.is_prerequisite is False for t in other_tasks)

@pytest.mark.asyncio
async def test_data_collection_completion_triggers_other_tasks(db_session, company_with_blocked_tasks):
    """Test that completing data_collection triggers other tasks"""
    service = CompanyService(db_session)
    dependency_service = TaskDependencyService(db_session)

    # Get data_collection task
    data_collection_task = next(t for t in company_with_blocked_tasks.tasks if t.type.value == 'data_collection')

    # Simulate completion
    data_collection_task.status = TaskStatus.SUCCEEDED
    db_session.commit()

    # Unblock dependent tasks
    unblocked = dependency_service.unblock_dependent_tasks(data_collection_task.id)

    # Should unblock 8 tasks
    assert len(unblocked) == 8
    assert all(t.status == TaskStatus.PENDING for t in unblocked)
```

### 6.3 End-to-End Tests

**Manual Testing Checklist**:

1. ✅ Create new company
   - Verify only `data_collection` task starts (status: RUNNING)
   - Verify 8 other tasks have status: BLOCKED

2. ✅ Wait for data_collection to complete
   - Check backend logs for "Unblocked X tasks" message
   - Verify 8 tasks transition from BLOCKED → PENDING → RUNNING

3. ✅ Check knowledge fields populated
   - Verify `raw_mistral_knowledge` has data
   - Verify `raw_claude_knowledge` has data
   - Verify `raw_wikipedia_knowledge` has data
   - Verify `raw_scraped_website_knowledge` has data

4. ✅ Verify other tasks use collected knowledge
   - Check Dify workflow logs
   - Confirm no duplicate data collection API calls

5. ✅ Test failure scenario
   - Manually fail data_collection task
   - Verify dependent tasks are marked as ERROR
   - Verify error message indicates prerequisite failure

---

## 7. Deployment Plan

### 7.1 Phase 1: Database Migration
1. Run migration to add `task_dependencies` table
2. Add `blocked` status to enum
3. Add `is_prerequisite` column to tasks

### 7.2 Phase 2: Backend Deployment
1. Deploy new models (Task, TaskDependency)
2. Deploy TaskDependencyService
3. Deploy modified CompanyService
4. Deploy modified webhook handler

### 7.3 Phase 3: Frontend Deployment
1. Update task types to include `blocked` status
2. Update TasksFlowModal to display blocked status
3. Update status colors and labels

### 7.4 Rollback Plan
If issues arise:
1. Backend can be rolled back to previous version
2. Database migration includes downgrade
3. Existing tasks will continue to work (blocked status will be ignored)
4. Frontend displays blocked as pending (graceful degradation)

---

## 8. Monitoring & Observability

### 8.1 Metrics to Track
- Average time for data_collection to complete
- Number of tasks unblocked per data_collection completion
- Reduction in external API calls (Mistral, Claude, Wikipedia, web scraping)
- Overall company creation completion time (should decrease)
- Error rate for data_collection vs other tasks

### 8.2 Logging
Add structured logs:
- `🔓 Unblocking N tasks for company X`
- `🚀 Triggering task Y (dependent on task Z)`
- `⏳ Task A blocked, waiting for prerequisite task B`
- `✅ All dependencies satisfied for task C`

### 8.3 Alerts
- Alert if data_collection task fails (blocks all other tasks)
- Alert if tasks remain BLOCKED for > 15 minutes
- Alert if dependency chain is broken (orphaned tasks)

---

## 9. Future Enhancements

### 9.1 Complex Dependency Graphs
Current solution supports 1 prerequisite → N dependents. Future:
- Support multiple prerequisites per task
- Support task chains (A → B → C → D)
- Support conditional dependencies

### 9.2 Partial Retry
If data_collection partially fails (e.g., Wikipedia works but Mistral fails):
- Allow retrying only failed parts
- Don't block all tasks, only those requiring failed data

### 9.3 Parallel Subgroups
Instead of 1 prerequisite → 8 tasks, allow:
- Group 1: data_collection → [profile, digital, csr]
- Group 2: profile → [timeline, products]
- Group 3: parallel → [team, jobs, press]

### 9.4 Admin Dashboard
Create admin UI to:
- Visualize task dependency graph
- Manually trigger/unblock tasks
- View dependency health metrics

---

## 10. Success Criteria

### 10.1 Functional Requirements
- ✅ data_collection task runs first
- ✅ Other tasks wait until data_collection succeeds
- ✅ Webhook triggers dependent tasks automatically
- ✅ Failed prerequisite marks dependents as error
- ✅ Frontend displays blocked status correctly

### 10.2 Performance Requirements
- ✅ Reduce external API calls by ~70% (8 duplicate calls → 1 shared)
- ✅ Overall completion time reduced by 15-20%
- ✅ No increase in task failure rate
- ✅ Webhook processing time < 500ms

### 10.3 Quality Requirements
- ✅ 100% test coverage for TaskDependencyService
- ✅ Zero data loss during migration
- ✅ Backward compatible with existing tasks
- ✅ Clear error messages for blocked tasks

---

## 11. Implementation Checklist

### Backend
- [ ] Create Alembic migration for task_dependencies table
- [ ] Add `blocked` status to TaskStatus enum
- [ ] Add `is_prerequisite` field to Task model
- [ ] Create TaskDependency model
- [ ] Implement TaskDependencyService
- [ ] Modify CompanyService.create_company() to create dependencies
- [ ] Modify webhook to trigger dependent tasks
- [ ] Add unit tests for TaskDependencyService
- [ ] Add integration tests for sequential execution
- [ ] Update API documentation

### Frontend
- [ ] Add `blocked` status to TaskStatus type
- [ ] Add `is_prerequisite` to TaskResponse interface
- [ ] Update TasksFlowModal to handle blocked status
- [ ] Add blocked task count to progress display
- [ ] Add visual indicator for prerequisite tasks
- [ ] Test UI with blocked tasks

### DevOps
- [ ] Run database migration in development
- [ ] Run database migration in staging
- [ ] Verify migration rollback works
- [ ] Deploy backend to staging
- [ ] Deploy frontend to staging
- [ ] Run end-to-end tests in staging
- [ ] Deploy to production
- [ ] Monitor logs for errors
- [ ] Verify metrics in production

---

## 12. Questions & Decisions

### Q1: Should data_collection always be the prerequisite?
**Decision**: Yes, for MVP. Future versions can support configurable dependencies.

### Q2: What happens if data_collection task is manually restarted?
**Decision**: Dependent tasks remain in their current state. User must manually restart them if needed.

### Q3: Should we support retrying individual data sources (Mistral, Claude, etc.)?
**Decision**: Not in MVP. Future enhancement.

### Q4: How to handle partial data_collection success?
**Decision**: All-or-nothing for MVP. If any source fails, entire task fails.

### Q5: Should we expose dependency management in the UI?
**Decision**: Not in MVP. Admin-only feature for future.

---

## 13. Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Migration fails in production | High | Low | Test thoroughly in staging, have rollback plan |
| Webhook doesn't trigger dependent tasks | High | Medium | Add comprehensive logging, alerting |
| Deadlock in dependency chain | Medium | Low | Add timeout mechanisms, orphan task detection |
| Performance regression | Medium | Low | Monitor metrics closely, optimize queries |
| User confusion about blocked status | Low | Medium | Clear UI messaging, documentation |

---

## 14. Timeline Estimate

**Total**: ~3-4 days for full implementation

| Phase | Estimated Time | Notes |
|-------|---------------|-------|
| Database migration | 2 hours | Includes testing rollback |
| Backend models & services | 4 hours | TaskDependency, TaskDependencyService |
| Backend integration | 4 hours | Modify CompanyService, webhooks |
| Backend testing | 4 hours | Unit + integration tests |
| Frontend updates | 3 hours | Types, components, UI |
| Frontend testing | 2 hours | Manual + automated tests |
| Documentation | 2 hours | API docs, user docs |
| Deployment | 2 hours | Staging + production |
| **Total** | **23 hours** | ~3 days |

Add 25% buffer for unexpected issues: **~4 days total**

---

## Appendix A: Example Database State

### Before Migration
```
tasks table:
id | company_id | type            | status    | is_prerequisite
---|------------|-----------------|-----------|----------------
1  | 1          | profile         | pending   | NULL
2  | 1          | digital         | pending   | NULL
3  | 1          | data_collection | pending   | NULL
```

### After Migration (New Company Created)
```
tasks table:
id | company_id | type            | status    | is_prerequisite
---|------------|-----------------|-----------|----------------
10 | 2          | data_collection | pending   | true
11 | 2          | profile         | blocked   | false
12 | 2          | digital         | blocked   | false
13 | 2          | csr             | blocked   | false
14 | 2          | press           | blocked   | false
15 | 2          | timeline        | blocked   | false
16 | 2          | products        | blocked   | false
17 | 2          | team            | blocked   | false
18 | 2          | jobs            | blocked   | false

task_dependencies table:
id | task_id | depends_on_task_id | created_at
---|---------|-------------------|------------
1  | 11      | 10                | 2025-10-20 ...
2  | 12      | 10                | 2025-10-20 ...
3  | 13      | 10                | 2025-10-20 ...
4  | 14      | 10                | 2025-10-20 ...
5  | 15      | 10                | 2025-10-20 ...
6  | 16      | 10                | 2025-10-20 ...
7  | 17      | 10                | 2025-10-20 ...
8  | 18      | 10                | 2025-10-20 ...
```

### After data_collection Completes
```
tasks table (updated):
id | company_id | type            | status    | is_prerequisite
---|------------|-----------------|-----------|----------------
10 | 2          | data_collection | succeeded | true
11 | 2          | profile         | pending   | false  ← Changed
12 | 2          | digital         | pending   | false  ← Changed
13 | 2          | csr             | pending   | false  ← Changed
14 | 2          | press           | pending   | false  ← Changed
15 | 2          | timeline        | pending   | false  ← Changed
16 | 2          | products        | pending   | false  ← Changed
17 | 2          | team            | pending   | false  ← Changed
18 | 2          | jobs            | pending   | false  ← Changed
```

---

**End of Specification**
