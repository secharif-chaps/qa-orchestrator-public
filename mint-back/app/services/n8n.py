import aiohttp
from typing import Any, Dict, List
from app.core.config import settings

class N8nClient:
    def __init__(self):
        self.base_url = settings.N8N_BASE_URL
        self.api_key = settings.N8N_API_KEY

    async def trigger_workflow(self, company_name: str, website: str, query_type: str) -> List[Dict[str, Any]]:
        """Trigger an n8n workflow with the given parameters"""
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{self.base_url}/webhook/{query_type}",
                json={
                    "company_name": company_name,
                    "website": website
                },
                headers={"X-N8N-API-KEY": self.api_key}
            ) as response:
                response.raise_for_status()
                return await response.json() 