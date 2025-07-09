import aiohttp
import logging
from typing import Any, Dict, List
from app.core.config import settings

logger = logging.getLogger(__name__)

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
    
    async def send_chat_message(self, message: str, company_context: Dict[str, Any], chat_history: List[Dict[str, str]]) -> Dict[str, Any]:
        """Send a chat message to the n8n chat workflow"""
        logger.info(f"Sending chat message to n8n workflow")
        
        try:
            async with aiohttp.ClientSession(timeout=aiohttp.ClientTimeout(total=60)) as session:
                async with session.post(
                    f"{self.base_url}/webhook/{settings.N8N_CHAT_WEBHOOK_ID}/chat",
                    json={
                        "message": message,
                        "companyContext": company_context,
                        "chatHistory": chat_history
                    }
                ) as response:
                    response.raise_for_status()
                    result = await response.json()
                    logger.info(f"Chat response received from n8n")
                    return result
        except aiohttp.ClientError as e:
            logger.error(f"Error calling n8n chat workflow: {str(e)}")
            raise
        except Exception as e:
            logger.error(f"Unexpected error in chat workflow: {str(e)}")
            raise 