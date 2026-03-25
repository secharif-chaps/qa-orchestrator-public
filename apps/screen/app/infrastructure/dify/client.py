import logging
from typing import Any

import httpx
from sqlalchemy.orm import Session

from app.core.config import settings
from app.services.workflow_config import WorkflowConfigService

logger = logging.getLogger(__name__)

class DifyClient:
    """Client for interacting with Dify workflows"""
    
    def __init__(self, db: Session | None = None):
        self.db = db
        self.base_url = settings.DIFY_URL
        
        # Fallback API key if database is not available
        self.fallback_api_key = settings.DIFY_API_KEY
    
    def _get_workflow_config(self, task_type: str) -> str | None:
        """Get API key for task type from database"""
        if self.db is None:
            # No database available - workflows must be configured in database
            return None

        try:
            service = WorkflowConfigService(self.db)
            config = service.get_config_by_task_type(task_type)
            if config and config.api_key:
                return config.api_key
            else:
                # No config found in database
                return None
        except Exception as e:
            logger.warning(f"Failed to get workflow config from database: {e}")
            return None
    
    async def trigger_workflow(
        self,
        task_type: str,
        company_name: str,
        website: str,
        success_callback: str,
        error_callback: str,
        task_id: int,
        company_id: int,
        async_mode: bool = True,
        api_key: str | None = None
    ) -> dict[str, Any]:
        """
        Generic method to trigger any workflow type using Workflow Apps API

        Args:
            task_type: Type of task (products, timeline, etc.)
            company_name: Company name
            website: Company website
            success_callback: URL to call on successful completion
            error_callback: URL to call on error
            task_id: Task ID for callback reference
            company_id: Company ID for callback reference
            async_mode: If True, returns immediately (fire-and-forget)

        Returns:
            Response data from Dify (acknowledgment if async, results if sync)
        """
        # Use provided API key if available, otherwise get from database
        if api_key is None:
            api_key = self._get_workflow_config(task_type)

        if not api_key:
            error_msg = f"No API key found for task type: {task_type}"
            logger.error(error_msg)
            raise Exception(error_msg)

        headers = {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json"
        }

        # Prepare the callback payload that Dify should send back
        callback_payload_template = {
            "task_id": task_id,
            "company_id": company_id,
            "task_type": task_type
        }

        inputs = {
            "company": company_name,
            "website": website,
            "callback_webhook": success_callback,
            "task_id": str(task_id),
            "callback_payload": callback_payload_template
        }

        # For non-data_collection tasks, include knowledge data from data_collection results
        if task_type != "data_collection" and self.db is not None:
            try:
                from app.models.company import Company
                company = self.db.query(Company).filter(Company.id == company_id).first()

                if company:
                    # Add knowledge fields to inputs (these come from data_collection task)
                    inputs["mistral"] = company.raw_mistral_knowledge or ""
                    inputs["gpt"] = company.raw_gpt_knowledge or ""
                    inputs["wikipedia"] = company.raw_wikipedia_knowledge or ""
                    inputs["scraped"] = company.raw_scraped_website_knowledge or ""

                    logger.info(f"📚 Added knowledge data to {task_type} workflow inputs:")
                    logger.info(f"  - Mistral: {len(inputs['mistral'])} chars")
                    logger.info(f"  - GPT: {len(inputs['gpt'])} chars")
                    logger.info(f"  - Wikipedia: {len(inputs['wikipedia'])} chars")
                    logger.info(f"  - Scraped: {len(inputs['scraped'])} chars")
                else:
                    logger.warning(f"Company {company_id} not found - cannot add knowledge data")
            except Exception as e:
                logger.error(f"Failed to retrieve knowledge data for company {company_id}: {e}")
                # Continue without knowledge data rather than failing

        # Add string flags for data_collection task (all enabled by default)
        # Dify expects string values "true" or "false", not boolean
        if task_type == "data_collection":
            inputs["mistral"] = "true"
            inputs["gpt"] = "true"
            inputs["webscraping"] = "true"
            inputs["wikipedia"] = "true"
            # data_collection workflow expects "callback_url" instead of "callback_webhook"
            inputs["callback_url"] = success_callback

        # Workflow Apps API payload structure
        payload = {
            "inputs": inputs,
            "response_mode": "blocking",
            "user": f"company_{company_id}"
        }

        logger.info(f"Triggering Dify {task_type} workflow for {company_name} ({website}) - Task ID: {task_id}")
        logger.info(f"Dify Base URL: {self.base_url}")
        logger.info(f"Full URL: {self.base_url}/workflows/run")
        logger.info(f"Mode: {'Async (fire-and-forget)' if async_mode else 'Sync (wait for response)'}")
        logger.info(f"Success callback: {success_callback}")
        logger.info(f"Error callback: {error_callback}")

        try:
            if async_mode:
                # Fire-and-forget mode: Send request but don't wait for workflow completion
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.base_url}/workflows/run",
                        json=payload,
                        headers=headers,
                        timeout=10.0  # Just wait for acknowledgment, not completion
                    )

                    if response.status_code not in [200, 201, 202]:
                        error_msg = f"Dify {task_type} workflow trigger failed with status: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)

                    logger.info(f"✅ Dify {task_type} workflow triggered successfully for task {task_id} (async mode)")
                    return {"status": "triggered", "task_id": task_id, "message": f"{task_type} workflow started, will callback when complete"}

            else:
                # Synchronous mode: Wait for the full response (fallback option)
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.base_url}/workflows/run",
                        json=payload,
                        headers=headers,
                        timeout=300.0  # 5 minutes timeout for blocking mode
                    )

                    if response.status_code != 200:
                        error_msg = f"Dify {task_type} workflow returned non-200 status code: {response.status_code}"
                        logger.error(f"{error_msg}. Response: {response.text}")
                        raise Exception(error_msg)

                    response_data = response.json()
                    logger.info(f"Dify {task_type} workflow response received for {company_name} (sync mode)")

                    # Extract data from Workflow Apps API response
                    if "data" in response_data and "outputs" in response_data["data"]:
                        outputs = response_data["data"]["outputs"]
                        logger.info(f"Successfully received Dify {task_type} data for {company_name}")
                        return outputs
                    else:
                        logger.warning(f"Unexpected Dify {task_type} response structure: {response_data}")
                        return response_data

        except httpx.TimeoutException as e:
            if async_mode:
                # In async mode, timeout might mean Dify accepted but is still processing
                logger.warning(f"Dify {task_type} request timed out, but workflow may still be running (will callback)")
                return {"status": "timeout", "task_id": task_id, "message": f"{task_type} request timeout but workflow may be running"}
            else:
                error_msg = f"Dify {task_type} workflow request timed out"
                logger.error(error_msg)
                raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred with Dify {task_type} workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error triggering Dify {task_type} workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
    async def send_chat_message(self, message: str, company_context: dict[str, Any], chat_history: list = None) -> dict[str, Any]:
        """
        Send a chat message to the Dify chat workflow
        
        Args:
            message: User's chat message
            company_context: Company context data to provide context to the chat
            chat_history: Previous chat messages (optional)
            
        Returns:
            Response from the Dify chat workflow
        """
        # Hardcoded chat workflow credentials as provided
        chat_workflow_id = "a2a382d0-caed-43e5-8272-b3b4cba96451"
        chat_api_key = "app-jGJl5PAPQnAE0IzFAfkV3XjO"
        
        url = f"{self.base_url}/chat-messages"
        
        logger.info(f"Sending chat message to Dify workflow: {chat_workflow_id}")
        
        # Prepare the payload for Dify API
        import json
        payload = {
            "inputs": {
                "company": json.dumps(company_context, ensure_ascii=False)
            },
            "query": message,
            "response_mode": "blocking",
            "conversation_id": "",
            "user": "user"
        }
        
        # Add chat history if provided
        if chat_history:
            # Convert chat history to a stringified JSON format as well
            payload["inputs"]["chat_history"] = json.dumps(chat_history, ensure_ascii=False)
        
        headers = {
            "Authorization": f"Bearer {chat_api_key}",
            "Content-Type": "application/json"
        }
        
        try:
            async with httpx.AsyncClient(timeout=60.0) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers=headers
                )
                
                logger.info(f"Dify chat response status: {response.status_code}")
                
                if response.status_code != 200:
                    error_msg = f"Dify chat API returned non-200 status code: {response.status_code}"
                    logger.error(f"{error_msg}. Response: {response.text}")
                    raise Exception(error_msg)
                
                try:
                    response_text = response.text.strip()
                    if not response_text:
                        logger.warning("Dify chat API returned empty response")
                        return {"response": "I'm sorry, I couldn't generate a response. Please try again.", "status": "error"}
                    
                    json_response = response.json()
                    
                    if json_response is None:
                        logger.warning("Dify chat API returned null response")
                        return {"response": "I'm sorry, I couldn't generate a response. Please try again.", "status": "error"}
                    
                    # Extract the answer from Dify response format
                    if "answer" in json_response:
                        return {
                            "response": json_response["answer"],
                            "status": "success",
                            "conversation_id": json_response.get("conversation_id", "")
                        }
                    else:
                        logger.warning(f"Unexpected Dify chat response format: {json_response}")
                        return {"response": "I'm sorry, I couldn't generate a response. Please try again.", "status": "error"}
                    
                except Exception as e:
                    logger.error(f"Error processing Dify chat response: {str(e)}")
                    return {"response": "I'm sorry, there was an error processing your message. Please try again.", "status": "error"}
                
        except httpx.TimeoutException as e:
            error_msg = "Chat request to Dify timed out after 60 seconds"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred in Dify chat request: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error in Dify chat workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e

    async def send_global_chat_message(
        self,
        message: str,
        contexts: dict[str, Any],
        system_context: dict[str, Any],
        chat_history: list = None
    ) -> dict[str, Any]:
        """
        Send a chat message to the global Chaps-e chat workflow

        Args:
            message: User's chat message
            contexts: Multiple context types (company, folder, organization)
            system_context: System-level context (language, username, organization_name)
            chat_history: Previous chat messages (optional)

        Returns:
            Response from the Dify chat workflow
        """
        # Use same chat workflow for now (Phase 1)
        # In future, this could use a different workflow optimized for global chat
        chat_workflow_id = "efc315b1-219f-4d06-a8ef-5e4fc6edb341"
        chat_api_key = "app-jGJl5PAPQnAE0IzFAfkV3XjO"

        url = f"{self.base_url}/chat-messages"

        logger.info(f"Sending global chat message to Dify workflow: {chat_workflow_id}")
        logger.debug(f"Contexts: {list(contexts.keys())}")
        logger.debug(f"Language: {system_context.get('language', 'fr')}")

        # Prepare the payload for Dify API
        import json

        # Combine all contexts into a single input
        combined_context = {
            "system": system_context,
            **contexts  # Spread company, folder, organization contexts
        }

        payload = {
            "inputs": {
                "context": json.dumps(combined_context, ensure_ascii=False),
                "language": system_context.get("language", "fr")
            },
            "query": message,
            "response_mode": "blocking",
            "conversation_id": "",
            "user": system_context.get("username", "user")
        }

        # Add system message if present (for assist_action context)
        if "system_message" in system_context:
            payload["inputs"]["system_message"] = system_context["system_message"]
            logger.debug("Added system_message to Dify payload")

        # Add chat history if provided
        if chat_history:
            payload["inputs"]["chat_history"] = json.dumps(chat_history, ensure_ascii=False)

        headers = {
            "Authorization": f"Bearer {chat_api_key}",
            "Content-Type": "application/json"
        }

        try:
            async with httpx.AsyncClient(timeout=60.0) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers=headers
                )

                logger.info(f"Dify global chat response status: {response.status_code}")

                if response.status_code != 200:
                    error_msg = f"Dify chat API returned non-200 status code: {response.status_code}"
                    logger.error(f"{error_msg}. Response: {response.text}")
                    raise Exception(error_msg)

                try:
                    response_text = response.text.strip()
                    if not response_text:
                        logger.warning("Dify chat API returned empty response")
                        return {"output": "Je suis désolé, je n'ai pas pu générer de réponse. Veuillez réessayer.", "status": "error"}

                    json_response = response.json()

                    if json_response is None:
                        logger.warning("Dify chat API returned null response")
                        return {"output": "Je suis désolé, je n'ai pas pu générer de réponse. Veuillez réessayer.", "status": "error"}

                    # Extract the answer from Dify response format
                    if "answer" in json_response:
                        return {
                            "output": json_response["answer"],
                            "status": "success",
                            "conversation_id": json_response.get("conversation_id", "")
                        }
                    else:
                        logger.warning(f"Unexpected Dify chat response format: {json_response}")
                        return {"output": "Je suis désolé, je n'ai pas pu générer de réponse. Veuillez réessayer.", "status": "error"}

                except Exception as e:
                    logger.error(f"Error processing Dify chat response: {str(e)}")
                    return {"output": "Je suis désolé, une erreur s'est produite lors du traitement de votre message. Veuillez réessayer.", "status": "error"}

        except httpx.TimeoutException as e:
            error_msg = "Global chat request to Dify timed out after 60 seconds"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred in Dify global chat request: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error in Dify global chat workflow: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e

