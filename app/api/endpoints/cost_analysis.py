"""Cost Analysis API endpoints for admin users.

All endpoints require admin.costs role for access.
"""

from datetime import datetime, date, timedelta
from typing import Optional, Dict, Any
from fastapi import APIRouter, Depends, HTTPException, Query
from fastapi_keycloak import OIDCUser
from sqlalchemy.orm import Session
from sqlalchemy import func

from app.database import get_db
from app.core.keycloak import idp
from app.models import Task, TaskStatus
from app.models.company import Company
from app.models.workspace import Workspace

router = APIRouter(prefix="/cost-analysis", tags=["cost-analysis"])


@router.get("/global", response_model=Dict[str, Any])
async def get_global_cost_analysis(
    start_date: Optional[date] = Query(None, description="Start date for analysis (inclusive)"),
    end_date: Optional[date] = Query(None, description="End date for analysis (inclusive)"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.costs"])),
    db: Session = Depends(get_db)
):
    """Get global cost analysis across all workspaces.

    Requires admin.costs role for access.
    By default, returns data for the current month.
    """
    
    # Set default date range if not provided (current month)
    if not start_date:
        today = date.today()
        start_date = date(today.year, today.month, 1)
    if not end_date:
        end_date = date.today()
    
    # Convert dates to datetime for comparison with timestamp columns
    start_datetime = datetime.combine(start_date, datetime.min.time())
    end_datetime = datetime.combine(end_date, datetime.max.time())
    
    # Query for global statistics
    global_stats = db.query(
        func.count(Task.id).label("total_tasks"),
        func.sum(Task.input_tokens).label("total_input_tokens"),
        func.sum(Task.output_tokens).label("total_output_tokens"),
        func.sum(Task.total_cost).label("total_cost"),
        func.avg(Task.total_cost).label("avg_cost_per_task")
    ).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).first()
    
    # Count unique companies with tasks
    company_count = db.query(func.count(func.distinct(Task.company_id))).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).scalar()
    
    # Count unique workspaces with tasks
    workspace_count = db.query(func.count(func.distinct(Company.workspace_id))).join(
        Task, Company.id == Task.company_id
    ).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).scalar()
    
    return {
        "period": {
            "start_date": start_date.isoformat(),
            "end_date": end_date.isoformat()
        },
        "global_summary": {
            "total_tasks": global_stats.total_tasks or 0,
            "total_companies": company_count or 0,
            "total_workspaces": workspace_count or 0,
            "total_input_tokens": global_stats.total_input_tokens or 0,
            "total_output_tokens": global_stats.total_output_tokens or 0,
            "total_cost": float(global_stats.total_cost or 0),
            "avg_cost_per_task": float(global_stats.avg_cost_per_task or 0),
            "avg_cost_per_company": float(global_stats.total_cost or 0) / company_count if company_count else 0
        }
    }


@router.get("/by-workspace", response_model=Dict[str, Any])
async def get_cost_by_workspace(
    start_date: Optional[date] = Query(None, description="Start date for analysis (inclusive)"),
    end_date: Optional[date] = Query(None, description="End date for analysis (inclusive)"),
    workspace_id: Optional[int] = Query(None, description="Filter by specific workspace ID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.costs"])),
    db: Session = Depends(get_db)
):
    """Get cost analysis broken down by workspace.

    Requires admin.costs role for access.
    By default, returns data for the current month.
    """
    
    # Set default date range if not provided (current month)
    if not start_date:
        today = date.today()
        start_date = date(today.year, today.month, 1)
    if not end_date:
        end_date = date.today()
    
    # Convert dates to datetime for comparison with timestamp columns
    start_datetime = datetime.combine(start_date, datetime.min.time())
    end_datetime = datetime.combine(end_date, datetime.max.time())
    
    # Build base query
    query = db.query(
        Workspace.id.label("workspace_id"),
        Workspace.name.label("workspace_name"),
        func.count(func.distinct(Company.id)).label("company_count"),
        func.count(Task.id).label("task_count"),
        func.sum(Task.input_tokens).label("total_input_tokens"),
        func.sum(Task.output_tokens).label("total_output_tokens"),
        func.sum(Task.total_cost).label("total_cost"),
        func.avg(Task.total_cost).label("avg_cost_per_task")
    ).join(
        Company, Workspace.id == Company.workspace_id
    ).join(
        Task, Company.id == Task.company_id
    ).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).group_by(
        Workspace.id, Workspace.name
    ).order_by(
        func.sum(Task.total_cost).desc()
    )
    
    # Apply workspace filter if provided
    if workspace_id:
        query = query.filter(Workspace.id == workspace_id)
    
    workspace_results = query.all()
    
    # Format results
    workspaces_data = []
    for ws in workspace_results:
        workspaces_data.append({
            "workspace_id": ws.workspace_id,
            "workspace_name": ws.workspace_name,
            "company_count": ws.company_count or 0,
            "task_count": ws.task_count or 0,
            "total_input_tokens": ws.total_input_tokens or 0,
            "total_output_tokens": ws.total_output_tokens or 0,
            "total_cost": float(ws.total_cost or 0),
            "avg_cost_per_task": float(ws.avg_cost_per_task or 0),
            "avg_cost_per_company": float(ws.total_cost or 0) / ws.company_count if ws.company_count else 0
        })
    
    # Calculate totals
    total_cost = sum(ws["total_cost"] for ws in workspaces_data)
    total_tasks = sum(ws["task_count"] for ws in workspaces_data)
    total_companies = sum(ws["company_count"] for ws in workspaces_data)
    
    return {
        "period": {
            "start_date": start_date.isoformat(),
            "end_date": end_date.isoformat()
        },
        "workspaces": workspaces_data,
        "summary": {
            "total_workspaces": len(workspaces_data),
            "total_cost": total_cost,
            "total_tasks": total_tasks,
            "total_companies": total_companies,
            "avg_cost_per_workspace": total_cost / len(workspaces_data) if workspaces_data else 0
        }
    }


@router.get("/by-task-type", response_model=Dict[str, Any])
async def get_cost_by_task_type(
    start_date: Optional[date] = Query(None, description="Start date for analysis (inclusive)"),
    end_date: Optional[date] = Query(None, description="End date for analysis (inclusive)"),
    workspace_id: Optional[int] = Query(None, description="Filter by specific workspace ID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.costs"])),
    db: Session = Depends(get_db)
):
    """Get cost analysis broken down by task type.

    Requires admin.costs role for access.
    By default, returns data for the current month.
    """
    
    # Set default date range if not provided (current month)
    if not start_date:
        today = date.today()
        start_date = date(today.year, today.month, 1)
    if not end_date:
        end_date = date.today()
    
    # Convert dates to datetime for comparison with timestamp columns
    start_datetime = datetime.combine(start_date, datetime.min.time())
    end_datetime = datetime.combine(end_date, datetime.max.time())
    
    # Build base query
    query = db.query(
        Task.type.label("task_type"),
        func.count(Task.id).label("task_count"),
        func.sum(Task.input_tokens).label("total_input_tokens"),
        func.sum(Task.output_tokens).label("total_output_tokens"),
        func.sum(Task.total_cost).label("total_cost"),
        func.avg(Task.total_cost).label("avg_cost_per_task"),
        func.avg(Task.input_tokens).label("avg_input_tokens"),
        func.avg(Task.output_tokens).label("avg_output_tokens")
    ).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).group_by(
        Task.type
    ).order_by(
        func.sum(Task.total_cost).desc()
    )
    
    # Apply workspace filter if provided
    if workspace_id:
        query = query.join(
            Company, Task.company_id == Company.id
        ).filter(
            Company.workspace_id == workspace_id
        )
    
    task_type_results = query.all()
    
    # Format results
    task_types_data = []
    for tt in task_type_results:
        task_types_data.append({
            "task_type": tt.task_type.value if hasattr(tt.task_type, 'value') else tt.task_type,
            "task_count": tt.task_count or 0,
            "total_input_tokens": tt.total_input_tokens or 0,
            "total_output_tokens": tt.total_output_tokens or 0,
            "total_cost": float(tt.total_cost or 0),
            "avg_cost_per_task": float(tt.avg_cost_per_task or 0),
            "avg_input_tokens": float(tt.avg_input_tokens or 0),
            "avg_output_tokens": float(tt.avg_output_tokens or 0)
        })
    
    # Calculate totals
    total_cost = sum(tt["total_cost"] for tt in task_types_data)
    total_tasks = sum(tt["task_count"] for tt in task_types_data)
    
    return {
        "period": {
            "start_date": start_date.isoformat(),
            "end_date": end_date.isoformat()
        },
        "workspace_id": workspace_id,
        "task_types": task_types_data,
        "summary": {
            "total_task_types": len(task_types_data),
            "total_cost": total_cost,
            "total_tasks": total_tasks,
            "most_expensive_type": max(task_types_data, key=lambda x: x["avg_cost_per_task"])["task_type"] if task_types_data else None,
            "most_frequent_type": max(task_types_data, key=lambda x: x["task_count"])["task_type"] if task_types_data else None
        }
    }


@router.get("/trends", response_model=Dict[str, Any])
async def get_cost_trends(
    start_date: Optional[date] = Query(None, description="Start date for analysis (inclusive)"),
    end_date: Optional[date] = Query(None, description="End date for analysis (inclusive)"),
    granularity: str = Query("daily", description="Granularity: daily, weekly, or monthly"),
    workspace_id: Optional[int] = Query(None, description="Filter by specific workspace ID"),
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.costs"])),
    db: Session = Depends(get_db)
):
    """Get cost trends over time.

    Requires admin.costs role for access.
    By default, returns daily data for the current month.
    """
    
    # Set default date range if not provided
    if not start_date:
        # For trends, default to last 30 days
        start_date = date.today() - timedelta(days=30)
    if not end_date:
        end_date = date.today()
    
    # Convert dates to datetime for comparison with timestamp columns
    start_datetime = datetime.combine(start_date, datetime.min.time())
    end_datetime = datetime.combine(end_date, datetime.max.time())
    
    # Determine date truncation based on granularity
    if granularity == "monthly":
        date_trunc = func.date_trunc('month', Task.created_at)
    elif granularity == "weekly":
        date_trunc = func.date_trunc('week', Task.created_at)
    else:  # daily
        date_trunc = func.date_trunc('day', Task.created_at)
    
    # Build base query
    query = db.query(
        date_trunc.label("period"),
        func.count(Task.id).label("task_count"),
        func.sum(Task.input_tokens).label("total_input_tokens"),
        func.sum(Task.output_tokens).label("total_output_tokens"),
        func.sum(Task.total_cost).label("total_cost")
    ).filter(
        Task.status == TaskStatus.SUCCEEDED,
        Task.created_at >= start_datetime,
        Task.created_at <= end_datetime
    ).group_by(
        date_trunc
    ).order_by(
        date_trunc
    )
    
    # Apply workspace filter if provided
    if workspace_id:
        query = query.join(
            Company, Task.company_id == Company.id
        ).filter(
            Company.workspace_id == workspace_id
        )
    
    trend_results = query.all()
    
    # Format results
    trends_data = []
    for trend in trend_results:
        trends_data.append({
            "period": trend.period.date().isoformat(),
            "task_count": trend.task_count or 0,
            "total_input_tokens": trend.total_input_tokens or 0,
            "total_output_tokens": trend.total_output_tokens or 0,
            "total_cost": float(trend.total_cost or 0)
        })
    
    # Calculate summary statistics
    if trends_data:
        total_cost = sum(t["total_cost"] for t in trends_data)
        avg_daily_cost = total_cost / len(trends_data)
        max_cost_period = max(trends_data, key=lambda x: x["total_cost"])
        min_cost_period = min(trends_data, key=lambda x: x["total_cost"])
    else:
        total_cost = avg_daily_cost = 0
        max_cost_period = min_cost_period = None
    
    return {
        "period": {
            "start_date": start_date.isoformat(),
            "end_date": end_date.isoformat()
        },
        "granularity": granularity,
        "workspace_id": workspace_id,
        "trends": trends_data,
        "summary": {
            "total_periods": len(trends_data),
            "total_cost": total_cost,
            "avg_cost_per_period": avg_daily_cost,
            "max_cost_period": max_cost_period,
            "min_cost_period": min_cost_period
        }
    }


@router.post("/refresh-materialized-views")
async def refresh_materialized_views(
    user: OIDCUser = Depends(idp.get_current_user(required_roles=["admin.costs"])),
    db: Session = Depends(get_db)
):
    """Refresh the materialized views for cost analysis.

    Requires admin.costs role for access.
    This should be called periodically to update the cached cost data.
    """
    
    try:
        # Refresh workspace cost summary view
        db.execute("REFRESH MATERIALIZED VIEW CONCURRENTLY workspace_cost_summary")
        
        # Refresh task type cost summary view
        db.execute("REFRESH MATERIALIZED VIEW CONCURRENTLY task_type_cost_summary")
        
        db.commit()
        
        return {
            "status": "success",
            "message": "Materialized views refreshed successfully",
            "timestamp": datetime.now().isoformat()
        }
    except Exception as e:
        db.rollback()
        raise HTTPException(
            status_code=500,
            detail=f"Failed to refresh materialized views: {str(e)}"
        )