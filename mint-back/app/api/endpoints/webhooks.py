from fastapi import APIRouter, Depends, HTTPException, status, Body
from typing import Dict, Any, List
from pydantic import BaseModel

from app.domain.services.company_service import CompanyService
from app.core.dependencies import get_company_service

router = APIRouter(
    prefix="/webhooks",
    tags=["webhooks"]
)

class N8nCallbackData(BaseModel):
    company_id: int
    query_type: str
    data: Dict[str, Any]

@router.post("/n8n/callback")
async def n8n_webhook_callback(
    data: List[Dict[str, Any]] = Body(...),
    service: CompanyService = Depends(get_company_service)
):
    """
    Webhook endpoint for n8n to call back with workflow results
    
    This endpoint allows n8n to send back data about a company once a workflow completes
    """
    if not data or not isinstance(data, list) or len(data) == 0:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid payload format. Expected a list with at least one item."
        )
    
    result_item = data[0]
    
    # Extract the necessary information from the callback
    company_id = result_item.get("company_id")
    query_type = result_item.get("query_type")
    output_data = result_item.get("output", {})
    
    if not company_id or not query_type:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing required fields: company_id and query_type"
        )
    
    try:
        # Update company data with workflow results
        company = service.get_company(company_id)
        if not company:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Company with ID {company_id} not found"
            )
        
        # Process the data and update company
        service.repository.update_company_data(company_id, query_type, output_data)
        
        # Set pending state to completed
        company.set_pending_state(query_type, False)
        service.update_company(company)
        
        return {
            "success": True,
            "message": f"Data for company {company_id}, query '{query_type}' successfully processed"
        }
        
    except Exception as e:
        # If there's an error, update the pending state with the error
        try:
            company = service.get_company(company_id)
            if company:
                company.set_pending_state(query_type, False, str(e))
                service.update_company(company)
        except:
            pass
            
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Error processing callback data: {str(e)}"
        ) 