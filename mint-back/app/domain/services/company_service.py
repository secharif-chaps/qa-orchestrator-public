from typing import List, Dict, Any, Optional
from app.domain.entities.company import Company
from app.domain.entities.task import Task, TaskType, TaskStatus
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
        - Creates tasks for each data type with pending status
        - Initiates all workflows for data collection
        """
        # Create or get the company
        company = self.create_company(name, website)
        
        # Create tasks for each query type
        task_types = [
            TaskType.profile,
            TaskType.digital,
            TaskType.timeline,
            TaskType.products,
            TaskType.press,
            TaskType.csr,
            TaskType.jobs,
            TaskType.team
        ]
        
        # First create all tasks with pending status
        for task_type in task_types:
            task = Task(company_id=company.id, type=task_type)  # status will be PENDING by default
            company.tasks.append(task)
        
        # Save the company with its pending tasks
        company = self.repository.update(company)
        
        # Now start all the queries independently
        for task_type in task_types:
            try:
                await self.start_query(company.id, task_type.value)
            except Exception as e:
                print(f"Error starting query for {task_type.value}: {str(e)}")
                # Don't re-raise the exception, continue with other tasks
                continue
            
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
        print(f"\n=== Starting query for company {company_id}, type: {query_type} ===")
        
        # Get the company
        company = self.repository.get_by_id(company_id)
        if not company:
            print(f"ERROR: Company with ID {company_id} not found")
            raise ValueError(f"Company with ID {company_id} not found")
        
        print(f"Found company: {company.name}")
        
        # Convert query_type to lowercase to match database enum
        query_type = query_type.lower()
        print(f"Query type (normalized): {query_type}")
        
        # Find the corresponding task or create it if it doesn't exist
        task = next((t for t in company.tasks if t.type.value == query_type), None)
        if not task:
            print(f"No task found for {query_type}, creating new task")
            # Create a new task for this query type
            task = Task(company_id=company.id, type=TaskType(query_type))
            company.tasks.append(task)
            self.repository.update(company)
            print(f"Created new task: {task.type.value}")
        else:
            print(f"Found existing task: {task.type.value} (status: {task.status})")
        
        # Update task status to running
        task.status = TaskStatus.RUNNING
        self.repository.update(company)
        print(f"Updated task status to: {task.status}")
        
        try:
            # Trigger the n8n workflow
            print(f"\nTriggering n8n workflow for {query_type}")
            result = await self.n8n_client.trigger_workflow(company.name, company.website, query_type)
            print(f"Received n8n response: {type(result)}")
            
            # Extract the data from the n8n response
            if isinstance(result, list) and len(result) > 0:
                print("Processing list response from n8n")
                # Get the output data from the first item
                output_data = result[0].get('output', {})
                print(f"Output data keys: {list(output_data.keys()) if isinstance(output_data, dict) else 'Not a dict'}")
                
                # For timeline data, we need to extract it from the output structure
                if query_type == "timeline" and isinstance(output_data, dict):
                    timeline_data = output_data.get('timeline', {})
                    print(f"Extracted timeline data: {list(timeline_data.keys()) if isinstance(timeline_data, dict) else 'Not a dict'}")
                    if isinstance(timeline_data, dict):
                        print(f"Timeline data structure:")
                        print(f"- Has insights: {'insights' in timeline_data}")
                        print(f"- Has events: {'events' in timeline_data}")
                        print(f"- Number of events: {len(timeline_data.get('events', []))}")
                        data = timeline_data
                    else:
                        print("ERROR: Timeline data is not a dictionary")
                        raise ValueError("Invalid timeline data structure")
                else:
                    data = output_data
            else:
                print("Processing direct response from n8n")
                data = result
            
            print(f"\nData structure before update:")
            print(f"- Keys in data: {list(data.keys()) if isinstance(data, dict) else 'Not a dict'}")
            if isinstance(data, dict) and 'timeline' in data:
                print(f"- Timeline keys: {list(data['timeline'].keys())}")
            
            # Update the company data
            print("\nUpdating company data...")
            company.update_from_n8n(query_type, data)
            print("Company data updated")
            
            # Update task status to succeeded
            task.status = TaskStatus.SUCCEEDED
            self.repository.update(company)
            print(f"Task status updated to: {task.status}")
            
            return data
        except Exception as e:
            print(f"\nERROR in start_query:")
            print(f"Exception type: {type(e)}")
            print(f"Exception message: {str(e)}")
            import traceback
            print(f"Traceback:\n{traceback.format_exc()}")
            
            # Update task status to error
            task.status = TaskStatus.ERROR
            task.error = str(e)
            self.repository.update(company)
            print(f"Task status updated to: {task.status} with error: {task.error}")
            raise 