import httpx
import logging
from typing import Dict, Any, Optional
from sqlalchemy.orm import Session
from app.core.config import settings
from app.services.workflow_config import WorkflowConfigService

logger = logging.getLogger(__name__)

class DifyClient:
    """Client for interacting with Dify workflows"""
    
    def __init__(self, db: Optional[Session] = None):
        self.db = db
        self.base_url = settings.DIFY_URL
        
        # Fallback API key if database is not available
        self.fallback_api_key = settings.DIFY_API_KEY
    
    def _get_workflow_config(self, task_type: str) -> tuple[Optional[str], Optional[str], Optional[str]]:
        """Get workflow ID, API key and LLM for task type from database"""
        if self.db is None:
            # No database available - workflows must be configured in database
            return None, None, None
        
        try:
            service = WorkflowConfigService(self.db)
            config = service.get_config_by_task_type(task_type)
            if config and config.workflow_id and config.api_key:
                return config.workflow_id, config.api_key, config.llm
            else:
                # No config found in database
                return None, None, None
        except Exception as e:
            logger.warning(f"Failed to get workflow config from database: {e}")
            return None, None, None
    
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
        token_callback_url: str = None,
        workflow_id: Optional[str] = None,
        api_key: Optional[str] = None,
        llm: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Generic method to trigger any workflow type
        
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
        # Use provided parameters if available, otherwise get from database
        if workflow_id is None or api_key is None or llm is None:
            db_workflow_id, db_api_key, db_llm = self._get_workflow_config(task_type)
            workflow_id = workflow_id or db_workflow_id
            api_key = api_key or db_api_key
            llm = llm or db_llm
        
        if not workflow_id or not api_key:
            error_msg = f"No workflow configuration found for task type: {task_type}"
            logger.error(error_msg)
            raise Exception(error_msg)
        
        # Default LLM if not provided
        if not llm:
            llm = "mistral"
        
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
            "callback_payload": callback_payload_template,
            "llm": llm  # Add LLM parameter to the inputs
        }

        # Add string flags for data_collection task (all enabled by default)
        # Dify expects string values "true" or "false", not boolean
        if task_type == "data_collection":
            inputs["mistral"] = "true"
            inputs["claude"] = "true"
            inputs["webscraping"] = "true"
            inputs["wikipedia"] = "true"

        # Add token callback URL if provided
        if token_callback_url:
            inputs["token_callback_url"] = token_callback_url
            
        payload = {
            "inputs": inputs,
            "response_mode": "blocking",
            "user": f"company_{company_id}"
        }
        
        logger.info(f"Triggering Dify {task_type} workflow for {company_name} ({website}) - Task ID: {task_id}")
        logger.info(f"Workflow ID: {workflow_id}")
        logger.info(f"LLM: {llm}")
        logger.info(f"Dify Base URL: {self.base_url}")
        logger.info(f"Full URL: {self.base_url}/workflows/{workflow_id}/run")
        logger.info(f"Mode: {'Async (fire-and-forget)' if async_mode else 'Sync (wait for response)'}")
        logger.info(f"Success callback: {success_callback}")
        logger.info(f"Error callback: {error_callback}")
        
        try:
            if async_mode:
                # Fire-and-forget mode: Send request but don't wait for workflow completion
                async with httpx.AsyncClient() as client:
                    response = await client.post(
                        f"{self.base_url}/workflows/{workflow_id}/run",
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
                        f"{self.base_url}/workflows/{workflow_id}/run",
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
                    
                    # Extract the actual workflow output
                    if "data" in response_data and "outputs" in response_data["data"]:
                        outputs = response_data["data"]["outputs"]
                        
                        # Parse the result if it's a string
                        if "result" in outputs and isinstance(outputs["result"], str):
                            import json
                            try:
                                parsed_result = json.loads(outputs["result"])
                                logger.info(f"Successfully parsed Dify {task_type} data for {company_name}")
                                return {task_type: parsed_result}
                            except json.JSONDecodeError:
                                logger.warning(f"Could not parse {task_type} result as JSON, returning as-is")
                                return {task_type: outputs}
                        else:
                            return {task_type: outputs}
                    else:
                        logger.warning(f"Unexpected Dify {task_type} response structure: {response_data}")
                        return {task_type: response_data}
                    
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
    async def send_chat_message(self, message: str, company_context: Dict[str, Any], chat_history: list = None) -> Dict[str, Any]:
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
            error_msg = f"Chat request to Dify timed out after 60 seconds"
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
        contexts: Dict[str, Any],
        system_context: Dict[str, Any],
        chat_history: list = None
    ) -> Dict[str, Any]:
        """
        Send a chat message to the global Chaps-e chat workflow

        Args:
            message: User's chat message
            contexts: Multiple context types (company, folder, workspace)
            system_context: System-level context (language, username, workspace_name)
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
            **contexts  # Spread company, folder, workspace contexts
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
            logger.debug(f"Added system_message to Dify payload")

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
            error_msg = f"Global chat request to Dify timed out after 60 seconds"
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

    async def generate_quick_actions(
        self,
        user_preferences: Dict[str, Any],
        company_data: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Generate quick actions using Dify workflow based on user preferences and company data

        Args:
            user_preferences: User's AI preferences (role, goals, desired_output, documentation)
            company_data: Full company data

        Returns:
            Dict with 'actions' list containing id, label, description, icon
        """
        # Chapse Assist workflow credentials (to be configured in Dify)
        # TODO: Move these to workflow_configs table or environment variables
        quick_actions_workflow_id = "QUICK_ACTIONS_WORKFLOW_ID"  # Placeholder
        quick_actions_api_key = "app-jGJl5PAPQnAE0IzFAfkV3XjO"  # Using same API key for now

        url = f"{self.base_url}/chat-messages"

        logger.info(f"Generating quick actions for company: {company_data.get('name', 'Unknown')}")
        logger.debug(f"User role: {user_preferences.get('role', 'Unknown')}")

        # Prepare the payload for Dify API
        import json

        # Combine user preferences and company data into context
        combined_context = {
            "user_preferences": user_preferences,
            "company": company_data
        }

        # Create the query with explicit JSON format instruction
        query = f"""Based on the provided user preferences and company data, generate 3 quick action recommendations.

User Role: {user_preferences.get('role', 'professional')}
User Goals: {user_preferences.get('goals', 'Not specified')}

IMPORTANT: You MUST respond with ONLY a valid JSON object in this exact format. Do not include any markdown, explanations, or additional text. Only output the JSON:

{{
  "actions": [
    {{
      "id": "unique_id_1",
      "label": "Short action label",
      "description": "Brief description of what this action does",
      "icon": "fa fa-icon-name"
    }},
    {{
      "id": "unique_id_2",
      "label": "Short action label",
      "description": "Brief description",
      "icon": "fa fa-icon-name"
    }},
    {{
      "id": "unique_id_3",
      "label": "Short action label",
      "description": "Brief description",
      "icon": "fa fa-icon-name"
    }}
  ]
}}

Use Font Awesome 5 icons (fa fa-envelope, fa fa-file-alt, fa fa-search, fa fa-users, fa fa-chart-line, etc.).
Make actions specific, actionable, and relevant to the user's role and company context."""

        payload = {
            "inputs": {
                "context": json.dumps(combined_context, ensure_ascii=False)
            },
            "query": query,
            "response_mode": "blocking",
            "conversation_id": "",
            "user": "chapse_assist"
        }

        headers = {
            "Authorization": f"Bearer {quick_actions_api_key}",
            "Content-Type": "application/json"
        }

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    url,
                    json=payload,
                    headers=headers
                )

                logger.info(f"Dify quick actions response status: {response.status_code}")

                if response.status_code != 200:
                    error_msg = f"Dify quick actions API returned non-200 status code: {response.status_code}"
                    logger.error(f"{error_msg}. Response: {response.text}")
                    raise Exception(error_msg)

                try:
                    response_text = response.text.strip()
                    if not response_text:
                        logger.warning("Dify quick actions API returned empty response")
                        raise Exception("Empty response from AI")

                    json_response = response.json()

                    if json_response is None:
                        logger.warning("Dify quick actions API returned null response")
                        raise Exception("Null response from AI")

                    # Extract the answer from Dify response format
                    if "answer" in json_response:
                        answer = json_response["answer"]

                        # Parse the JSON from the answer
                        try:
                            # Enhanced JSON extraction logic
                            answer_clean = answer.strip()

                            # Try to extract JSON from markdown code blocks
                            import re

                            # Try to find JSON in markdown code block
                            json_match = re.search(r'```json\s*(\{[\s\S]*?\})\s*```', answer_clean)
                            if json_match:
                                answer_clean = json_match.group(1)
                            else:
                                # Try to find JSON in generic code block
                                json_match = re.search(r'```\s*(\{[\s\S]*?\})\s*```', answer_clean)
                                if json_match:
                                    answer_clean = json_match.group(1)
                                else:
                                    # Try to extract JSON directly (look for first { to last })
                                    json_match = re.search(r'\{[\s\S]*\}', answer_clean)
                                    if json_match:
                                        answer_clean = json_match.group(0)

                            answer_clean = answer_clean.strip()

                            logger.debug(f"Extracted JSON string: {answer_clean[:200]}...")

                            actions_data = json.loads(answer_clean)

                            if "actions" in actions_data and isinstance(actions_data["actions"], list):
                                # Validate that we have at least 1 action
                                if len(actions_data["actions"]) == 0:
                                    logger.error("No actions in response")
                                    raise Exception("No actions generated by AI")

                                # Limit to maximum 3 actions
                                if len(actions_data["actions"]) > 3:
                                    actions_data["actions"] = actions_data["actions"][:3]

                                logger.info(f"Successfully generated {len(actions_data['actions'])} quick actions")
                                return actions_data
                            else:
                                logger.error(f"Invalid actions structure in response: {actions_data}")
                                raise Exception("Invalid response structure from AI")

                        except json.JSONDecodeError as e:
                            logger.error(f"Failed to parse AI response as JSON. Response: {answer[:500]}")
                            logger.error(f"JSON decode error: {str(e)}")
                            raise Exception(f"Failed to parse AI response: {str(e)}")
                    else:
                        logger.warning(f"Unexpected Dify quick actions response format: {json_response}")
                        raise Exception("Unexpected response format from AI")

                except Exception as e:
                    logger.error(f"Error processing Dify quick actions response: {str(e)}")
                    raise

        except httpx.TimeoutException as e:
            error_msg = f"Quick actions request to Dify timed out after 30 seconds"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error occurred in Dify quick actions request: {str(e)}"
            logger.error(error_msg)
            raise Exception(error_msg) from e
        except Exception as e:
            error_msg = f"Error generating quick actions: {str(e)}"
            logger.error(error_msg)
            raise