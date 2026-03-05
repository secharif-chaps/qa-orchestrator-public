"""Concurrency monitoring endpoints."""
import logging

from fastapi import APIRouter, Depends

from app.core.concurrency import DifyConcurrencyManager
from app.core.dependencies import get_company_service
from app.services.company import CompanyService

logger = logging.getLogger(__name__)

router = APIRouter(
    prefix="/concurrency",
    tags=["concurrency"]
)

@router.get("/status")
async def get_concurrency_status(service: CompanyService = Depends(get_company_service)):
    """Get current concurrency status for monitoring"""
    try:
        concurrency_manager = DifyConcurrencyManager(service.db)
        status = concurrency_manager.get_status_summary()
        
        return {
            "status": "success",
            "data": status
        }
    except Exception as e:
        logger.error(f"Error getting concurrency status: {e}")
        return {
            "status": "error", 
            "message": str(e)
        }

@router.post("/cleanup")
async def cleanup_stuck_tasks(service: CompanyService = Depends(get_company_service)):
    """Manually trigger cleanup of potentially stuck tasks"""
    try:
        concurrency_manager = DifyConcurrencyManager(service.db)
        concurrency_manager.cleanup_stuck_tasks()
        
        status = concurrency_manager.get_status_summary()
        
        return {
            "status": "success",
            "message": "Stuck task cleanup completed",
            "data": status
        }
    except Exception as e:
        logger.error(f"Error during stuck task cleanup: {e}")
        return {
            "status": "error",
            "message": str(e)
        }