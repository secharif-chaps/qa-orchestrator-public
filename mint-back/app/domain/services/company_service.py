from typing import List, Dict, Any, Optional
from app.domain.entities.company import Company
from app.domain.repositories.company_repository import CompanyRepository
from app.infrastructure.n8n.client import N8nClient

class CompanyService:
    """Service for company-related business logic"""
    
    def __init__(self, repository: CompanyRepository, n8n_client: N8nClient):
        self.repository = repository
        self.n8n_client = n8n_client
        
    def get_company(self, company_id: int) -> Optional[Company]:
        """Get a company by ID"""
        return self.repository.get_by_id(company_id)
    
    def get_company_by_name(self, name: str) -> Optional[Company]:
        """Get a company by name"""
        return self.repository.get_by_name(name)
    
    def get_all_companies(self) -> List[Company]:
        """Get all companies"""
        return self.repository.get_all()
    
    def create_company(self, name: str, website: str) -> Company:
        """Create a new company"""
        # Check if company already exists
        existing = self.repository.get_by_name(name)
        if existing:
            return existing
            
        return self.repository.create(name, website)
    
    def update_company(self, company: Company) -> Company:
        """Update an existing company"""
        return self.repository.update(company)
    
    def delete_company(self, company_id: int) -> bool:
        """Delete a company"""
        return self.repository.delete(company_id)
    
    async def initiate_company_search(self, name: str, website: str) -> Company:
        """
        Start a comprehensive search for company data
        - Creates or retrieves the company
        - Initiates all workflows for data collection
        """
        # Create or get the company
        company = self.create_company(name, website)
        
        # Define the query types to trigger
        query_types = [
            "profile",
            "digital",
            "timeline",
            "products",
            "press",
            "csr",
            "jobs",
            "team"
        ]
        
        # Trigger n8n workflows for each query type
        for query in query_types:
            await self.start_query(company.id, query)
            
        return company
    
    async def start_query(self, company_id: int, query_type: str) -> Dict[str, Any]:
        """
        Start a specific n8n workflow for a company
        
        Args:
            company_id: ID of the company
            query_type: Type of data to query (profile, team, etc.)
            
        Returns:
            Updated company data
        """
        # Get the company
        company = self.repository.get_by_id(company_id)
        if not company:
            raise ValueError(f"Company with ID {company_id} not found")
        
        # Set the pending state for this query
        company.set_pending_state(query_type, True)
        self.repository.update(company)
        
        try:
            # Trigger the n8n workflow
            result = await self.n8n_client.trigger_workflow(
                company=company.name,
                website=company.website,
                query=query_type
            )
            
            # Log the raw n8n response
            print(f"N8n response for {query_type}:", result)
            
            # Update company data with workflow results
            if result and isinstance(result, list) and len(result) > 0:
                data = result[0].get("output", {})
                print(f"Extracted data for {query_type}:", data)
                company = self.repository.update_company_data(company_id, query_type, data)
                
            # Update pending state to completed
            company.set_pending_state(query_type, False)
            self.repository.update(company)
            
            return {"success": True, "company_id": company_id, "query": query_type}
            
        except Exception as e:
            # Enhanced error logging
            import traceback
            error_details = {
                'error_message': str(e),
                'error_type': type(e).__name__,
                'traceback': traceback.format_exc()
            }
            print(f"Detailed error in start_query for {query_type}:", error_details)
            
            # Update pending state with error
            company.set_pending_state(query_type, False, str(e))
            self.repository.update(company)
            
            return {
                "success": False, 
                "error": str(e),
                "error_type": type(e).__name__,
                "company_id": company_id, 
                "query": query_type
            } 