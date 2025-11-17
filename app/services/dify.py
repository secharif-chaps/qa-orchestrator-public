"""Dify service wrapper around official Dify Python SDK."""
import httpx
from typing import Dict, Any, Optional
from sqlalchemy.orm import Session
from dify_client import ChatClient
from app.core.config import settings
from app.core.exceptions import ExternalServiceError
from app.core.logging_config import get_logger
from app.services.workflow_config import WorkflowConfigService

logger = get_logger(__name__)


class DifyService:
    """Service layer for Dify AI workflow and chat interactions.

    This service wraps the official Dify Python SDK (dify-client) and provides
    a clean interface for workflow execution and chat interactions. It handles:
    - Workflow configuration lookup from database
    - API key management
    - Error handling and logging
    - Knowledge data injection for workflows
    - Both blocking and streaming response modes

    Attributes:
        db: SQLAlchemy database session (optional, needed for workflow config lookup)
        base_url: Dify API base URL from settings
        fallback_api_key: Default API key from settings for chat workflows
    """

    def __init__(self, db: Optional[Session] = None):
        """Initialize Dify service.

        Args:
            db: SQLAlchemy session for workflow config lookup (optional)
        """
        self.db = db
        self.base_url = settings.DIFY_URL
        self.fallback_api_key = settings.DIFY_API_KEY

    def _get_workflow_config(self, task_type: str) -> tuple[Optional[str], Optional[str]]:
        """Get API key and LLM for task type from database.

        Args:
            task_type: Type of workflow task (e.g., 'data_collection', 'profile')

        Returns:
            Tuple of (api_key, llm) or (None, None) if not found

        Raises:
            DatabaseError: If database query fails (logged and returns None)
        """
        if self.db is None:
            return None, None

        try:
            service = WorkflowConfigService(self.db)
            config = service.get_config_by_task_type(task_type)
            if config and config.api_key:
                return config.api_key, config.llm
            else:
                return None, None
        except Exception as e:
            logger.warning(f"Failed to get workflow config from database: {e}")
            return None, None

    def _get_knowledge_data(self, company_id: int) -> Dict[str, str]:
        """Retrieve knowledge data for a company from database.

        This fetches the raw knowledge data collected by the data_collection
        workflow, which includes Mistral, Claude, Wikipedia, and scraped data.

        Args:
            company_id: Company ID to fetch knowledge for

        Returns:
            Dictionary with keys: mistral, claude, wikipedia, scraped
            Each value is the raw knowledge text or empty string if not available

        Raises:
            DatabaseError: If database query fails (logged and returns empty dict)
        """
        if self.db is None:
            return {"mistral": "", "claude": "", "wikipedia": "", "scraped": ""}

        try:
            from app.models.company import Company

            company = self.db.query(Company).filter(Company.id == company_id).first()

            if company:
                knowledge = {
                    "mistral": company.raw_mistral_knowledge or "",
                    "claude": company.raw_claude_knowledge or "",
                    "wikipedia": company.raw_wikipedia_knowledge or "",
                    "scraped": company.raw_scraped_website_knowledge or "",
                }

                logger.info(
                    f"📚 Retrieved knowledge data for company {company_id}",
                    extra={
                        "company_id": company_id,
                        "mistral_chars": len(knowledge["mistral"]),
                        "claude_chars": len(knowledge["claude"]),
                        "wikipedia_chars": len(knowledge["wikipedia"]),
                        "scraped_chars": len(knowledge["scraped"]),
                    },
                )

                return knowledge
            else:
                logger.warning(f"Company {company_id} not found")
                return {"mistral": "", "claude": "", "wikipedia": "", "scraped": ""}

        except Exception as e:
            logger.error(
                f"Failed to retrieve knowledge data for company {company_id}: {e}",
                exc_info=True,
                extra={"company_id": company_id},
            )
            return {"mistral": "", "claude": "", "wikipedia": "", "scraped": ""}

    async def run_workflow(
        self,
        task_type: str,
        company_name: str,
        website: str,
        success_callback: str,
        error_callback: str,
        task_id: int,
        company_id: int,
        response_mode: str = "blocking",
        token_callback_url: Optional[str] = None,
        api_key: Optional[str] = None,
        llm: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Execute a Dify workflow using the official SDK.

        This method handles workflow execution with automatic knowledge data injection
        for non-data_collection workflows. It supports both blocking and streaming modes.

        Args:
            task_type: Type of workflow task (e.g., 'data_collection', 'profile', 'csr')
            company_name: Company name for workflow input
            website: Company website URL
            success_callback: URL to call on successful completion
            error_callback: URL to call on error (currently unused)
            task_id: Task ID for callback reference
            company_id: Company ID for callback reference
            response_mode: "blocking" (wait for completion) or "streaming" (SSE)
            token_callback_url: Optional URL for token-by-token callbacks
            api_key: Optional API key override (fetched from DB if None)
            llm: Optional LLM model override (fetched from DB if None, defaults to "mistral")

        Returns:
            Dictionary with workflow response data:
            - For blocking mode: {"status": "success", "data": {...}}
            - For streaming mode: {"status": "streaming", "task_id": ...}

        Raises:
            ExternalServiceError: If workflow execution fails
        """
        # Get API key and LLM from database if not provided
        if api_key is None or llm is None:
            db_api_key, db_llm = self._get_workflow_config(task_type)
            api_key = api_key or db_api_key
            llm = llm or db_llm

        if not api_key:
            error_msg = f"No API key found for task type: {task_type}"
            logger.error(error_msg, extra={"task_type": task_type})
            raise ExternalServiceError(
                f"No API key configured for {task_type} workflow",
                details={"task_type": task_type},
            )

        # Default LLM if not provided
        if not llm:
            llm = "mistral"

        # Prepare callback payload
        callback_payload = {
            "task_id": task_id,
            "company_id": company_id,
            "task_type": task_type,
        }

        # Build workflow inputs
        inputs = {
            "company": company_name,
            "website": website,
            "callback_webhook": success_callback,
            "task_id": str(task_id),
            "callback_payload": callback_payload,
            "llm": llm,
        }

        logger.info(
            f"🔍 URL DEBUG [{task_type}] Step 1: Initial callback_webhook set",
            extra={
                "task_type": task_type,
                "task_id": task_id,
                "callback_webhook": success_callback,
            }
        )

        # Add knowledge data for non-data_collection workflows
        if task_type != "data_collection":
            knowledge = self._get_knowledge_data(company_id)
            inputs["mistral"] = knowledge["mistral"]
            inputs["claude"] = knowledge["claude"]
            inputs["wikipedia"] = knowledge["wikipedia"]
            inputs["scraped"] = knowledge["scraped"]

            logger.info(
                f"🔍 URL DEBUG [{task_type}] Step 2: Added knowledge data (non-data_collection)",
                extra={
                    "task_type": task_type,
                    "callback_webhook": inputs["callback_webhook"],
                }
            )

        # Add data collection flags for data_collection workflow
        if task_type == "data_collection":
            inputs["mistral"] = "true"
            inputs["claude"] = "true"
            inputs["webscraping"] = "true"
            inputs["wikipedia"] = "true"
            # data_collection workflow expects "callback_url" instead of "callback_webhook"
            inputs["callback_url"] = success_callback

            logger.info(
                f"🔍 URL DEBUG [{task_type}] Step 2: Set callback_url for data_collection",
                extra={
                    "task_type": task_type,
                    "callback_url": success_callback,
                    "callback_webhook": inputs.get("callback_webhook", "NOT SET"),
                }
            )

        # Add token callback URL if provided
        if token_callback_url:
            inputs["token_callback_url"] = token_callback_url

        logger.info(
            f"🚀 Triggering Dify {task_type} workflow",
            extra={
                "task_type": task_type,
                "company_name": company_name,
                "website": website,
                "task_id": task_id,
                "company_id": company_id,
                "llm": llm,
                "response_mode": response_mode,
                "callback_url": success_callback,
            },
        )

        # Log detailed request information
        logger.info(
            "🔍 Dify API Request Details",
            extra={
                "base_url": self.base_url,
                "api_key_prefix": api_key[:10] + "..." if api_key else "None",
                "api_key_length": len(api_key) if api_key else 0,
                "inputs_keys": list(inputs.keys()),
                "user": f"company_{company_id}",
            },
        )

        # Log the final callback URLs before sending to Dify
        logger.info(
            f"🔍 URL DEBUG [{task_type}] Step 3: FINAL URLs before Dify API call",
            extra={
                "task_type": task_type,
                "task_id": task_id,
                "callback_url": inputs.get("callback_url", "NOT SET"),
                "callback_webhook": inputs.get("callback_webhook", "NOT SET"),
                "token_callback_url": inputs.get("token_callback_url", "NOT SET"),
                "all_input_keys": list(inputs.keys()),
            }
        )

        # Log the inputs being sent (excluding sensitive data)
        safe_inputs = {k: v if k not in ["callback_webhook", "callback_url", "token_callback_url"] else "***" for k, v in inputs.items()}
        logger.debug(f"📦 Workflow inputs: {safe_inputs}")

        try:
            # For workflow apps, use direct HTTP call (not CompletionClient)
            # Workflow apps use /v1/workflows/run endpoint
            workflow_url = f"{self.base_url}/workflows/run"
            headers = {
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json"
            }

            payload = {
                "inputs": inputs,
                "response_mode": response_mode,
                "user": f"company_{company_id}"
            }

            logger.info(f"📡 Using Dify Workflow URL: {workflow_url}")
            logger.debug("📡 Sending request to Dify API...")
            logger.debug(f"📦 Request payload keys: {list(payload.keys())}")

            # Execute workflow via direct HTTP call
            async with httpx.AsyncClient(timeout=300.0) as client:
                response = await client.post(
                    workflow_url,
                    json=payload,
                    headers=headers
                )

            # Log response details
            status_code = response.status_code
            logger.info(
                f"📥 Received response from Dify API - Status: {status_code}",
                extra={
                    "status_code": status_code,
                    "task_id": task_id,
                    "task_type": task_type,
                },
            )

            # Check for HTTP error responses (400, 401, 403, 500, etc.)
            if status_code not in [200, 201]:
                # Extract error details from response
                try:
                    response_body = response.json()
                    response_text = response.text
                except:
                    response_body = None
                    response_text = response.text if hasattr(response, 'text') else str(response.content)

                logger.error(
                    f"❌ Dify API returned HTTP {status_code} error",
                    extra={
                        "status_code": status_code,
                        "response_text": response_text[:1000] if response_text else "No response text",
                        "response_body": response_body,
                        "task_id": task_id,
                        "task_type": task_type,
                    },
                )

                # Map HTTP status codes to error messages
                error_messages = {
                    400: "Bad Request - Invalid request payload or parameters",
                    401: "Unauthorized - Invalid or expired API key",
                    403: "Forbidden - Insufficient permissions",
                    404: "Not Found - Workflow or endpoint not found",
                    429: "Rate Limited - Too many requests",
                    500: "Internal Server Error - Dify service error",
                    502: "Bad Gateway - Dify service unavailable",
                    503: "Service Unavailable - Dify service down",
                }

                error_msg = error_messages.get(
                    status_code, f"HTTP {status_code} error from Dify API"
                )

                # Include Dify's error message if available
                full_error_msg = f"Dify API error: {error_msg}"
                if response_text:
                    full_error_msg += f" - {response_text[:500]}"

                raise ExternalServiceError(
                    full_error_msg,
                    details={
                        "status_code": status_code,
                        "task_type": task_type,
                        "task_id": task_id,
                        "response_text": response_text,
                        "response_body": response_body,
                    },
                )

            # Handle response based on mode
            if response_mode == "streaming":
                # For streaming mode, response is SSE (Server-Sent Events) format
                # Don't parse as JSON - data will come via webhook callback
                logger.info(
                    f"✅ Workflow {task_type} started in streaming mode",
                    extra={
                        "task_id": task_id,
                        "response_content_type": response.headers.get("content-type"),
                    },
                )
                return {
                    "status": "streaming",
                    "task_id": task_id,
                    "message": f"{task_type} workflow started, will callback when complete",
                }

            # For blocking mode, parse JSON response
            if response_mode == "blocking":
                # Parse JSON response from workflow
                try:
                    response_data = response.json()
                except Exception as e:
                    logger.error(
                        "Failed to parse JSON response from Dify",
                        extra={
                            "task_id": task_id,
                            "response_text": response.text[:500],
                            "error": str(e),
                        },
                    )
                    raise ExternalServiceError(
                        "Failed to parse Dify response as JSON",
                        details={"error": str(e), "response_text": response.text[:500]},
                    ) from e

                # Extract outputs from response
                # Workflow API returns: {"workflow_run_id": "...", "task_id": "...", "data": {"outputs": {...}}}
                if "data" in response_data and "outputs" in response_data["data"]:
                    outputs = response_data["data"]["outputs"]
                    logger.info(
                        f"✅ Workflow {task_type} completed successfully",
                        extra={
                            "task_id": task_id,
                            "company_id": company_id,
                            "workflow_run_id": response_data.get("workflow_run_id"),
                        },
                    )
                    return {"status": "success", "data": outputs}
                else:
                    logger.error(
                        "❌ Unexpected workflow response structure",
                        extra={
                            "task_id": task_id,
                            "response_keys": list(response_data.keys()),
                            "response_data": str(response_data)[:500],
                        },
                    )
                    raise ExternalServiceError(
                        "Unexpected response structure from Dify API",
                        details={
                            "response_keys": list(response_data.keys()),
                            "task_type": task_type,
                            "task_id": task_id,
                        },
                    )

        except ExternalServiceError:
            # Re-raise our custom errors without wrapping
            raise
        except httpx.TimeoutException as e:
            error_msg = f"Dify {task_type} workflow request timed out"
            logger.error(
                error_msg,
                exc_info=True,
                extra={
                    "task_type": task_type,
                    "task_id": task_id,
                    "company_id": company_id,
                    "timeout": 300.0,
                },
            )
            raise ExternalServiceError(
                "Dify workflow request timed out after 5 minutes",
                details={"task_type": task_type, "task_id": task_id},
            ) from e
        except httpx.HTTPError as e:
            error_msg = f"HTTP error during Dify {task_type} workflow execution"
            error_details = {
                "task_type": task_type,
                "task_id": task_id,
                "company_id": company_id,
                "error_type": type(e).__name__,
                "error_message": str(e),
            }

            if hasattr(e, "response") and e.response is not None:
                error_details["http_status"] = e.response.status_code
                error_details["http_body"] = e.response.text[:500] if hasattr(e.response, "text") else None

            logger.error(error_msg, exc_info=True, extra=error_details)
            raise ExternalServiceError(
                f"HTTP error during {task_type} workflow execution: {str(e)}",
                details=error_details,
            ) from e
        except Exception as e:
            error_msg = f"Dify {task_type} workflow execution failed"

            # Extract additional error details if available
            error_details = {
                "task_type": task_type,
                "task_id": task_id,
                "company_id": company_id,
                "error_type": type(e).__name__,
                "error_message": str(e),
                "base_url": self.base_url,
                "api_key_configured": bool(api_key),
            }

            logger.error(
                error_msg,
                exc_info=True,
                extra=error_details,
            )

            raise ExternalServiceError(
                f"Failed to execute {task_type} workflow: {str(e)}",
                details=error_details,
            ) from e

    async def send_chat_message(
        self,
        message: str,
        company_context: Dict[str, Any],
        chat_history: Optional[list] = None,
        api_key: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Send a chat message to Dify chat workflow.

        This method sends a user message to the Dify chat API with company context.
        It uses the ChatClient from the official SDK.

        Args:
            message: User's chat message text
            company_context: Company data to provide context (will be JSON serialized)
            chat_history: Optional list of previous chat messages
            api_key: Optional API key override (uses fallback if None)

        Returns:
            Dictionary with chat response:
            {
                "response": "AI response text",
                "status": "success" | "error",
                "conversation_id": "uuid"
            }

        Raises:
            ExternalServiceError: If chat request fails
        """
        # Use fallback API key if not provided
        if api_key is None:
            api_key = self.fallback_api_key

        logger.info(
            "Sending chat message to Dify",
            extra={"message_length": len(message), "company": company_context.get("name", "Unknown")},
        )

        try:
            # Initialize chat client
            client = ChatClient(api_key=api_key)
            # Override the default base_url with our custom instance
            client.base_url = self.base_url

            # Prepare inputs
            import json

            inputs = {"company": json.dumps(company_context, ensure_ascii=False)}

            # Add chat history if provided
            if chat_history:
                inputs["chat_history"] = json.dumps(chat_history, ensure_ascii=False)

            # Send chat message
            response = client.create_chat_message(
                inputs=inputs,
                query=message,
                user="user",
                response_mode="blocking",
            )

            # Extract answer from response
            if hasattr(response, "answer"):
                return {
                    "response": response.answer,
                    "status": "success",
                    "conversation_id": getattr(response, "conversation_id", ""),
                }
            else:
                logger.warning(f"Unexpected chat response format: {response}")
                return {
                    "response": "I'm sorry, I couldn't generate a response. Please try again.",
                    "status": "error",
                }

        except Exception as e:
            error_msg = "Chat request to Dify failed"
            logger.error(error_msg, exc_info=True, extra={"error": str(e)})
            raise ExternalServiceError(
                "Failed to send chat message", details={"error": str(e)}
            ) from e

    async def send_global_chat_message(
        self,
        message: str,
        contexts: Dict[str, Any],
        system_context: Dict[str, Any],
        chat_history: Optional[list] = None,
        api_key: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Send a message to the global Chaps-e chat workflow.

        This method handles global chat with multiple context types (company, folder,
        organization) and system context (language, username, etc.).

        Args:
            message: User's chat message text
            contexts: Multiple context types (company, folder, organization)
            system_context: System-level context (language, username, organization_name)
            chat_history: Optional list of previous chat messages
            api_key: Optional API key override (uses fallback if None)

        Returns:
            Dictionary with chat response:
            {
                "output": "AI response text",
                "status": "success" | "error",
                "conversation_id": "uuid"
            }

        Raises:
            ExternalServiceError: If chat request fails
        """
        # Use fallback API key if not provided
        if api_key is None:
            api_key = self.fallback_api_key

        logger.info(
            "Sending global chat message to Dify",
            extra={
                "message_length": len(message),
                "contexts": list(contexts.keys()),
                "language": system_context.get("language", "fr"),
                "username": system_context.get("username", "unknown"),
            },
        )

        try:
            # Initialize chat client
            client = ChatClient(api_key=api_key)
            # Override the default base_url with our custom instance
            client.base_url = self.base_url

            # Prepare inputs
            import json

            # Combine all contexts
            combined_context = {
                "system": system_context,
                **contexts,
            }

            inputs = {
                "context": json.dumps(combined_context, ensure_ascii=False),
                "language": system_context.get("language", "fr"),
            }

            # Add system message if present
            if "system_message" in system_context:
                inputs["system_message"] = system_context["system_message"]
                logger.debug("Added system_message to Dify payload")

            # Add chat history if provided
            if chat_history:
                inputs["chat_history"] = json.dumps(chat_history, ensure_ascii=False)

            # Send chat message
            response = client.create_chat_message(
                inputs=inputs,
                query=message,
                user=system_context.get("username", "user"),
                response_mode="blocking",
            )

            # Extract answer from response
            if hasattr(response, "answer"):
                return {
                    "output": response.answer,
                    "status": "success",
                    "conversation_id": getattr(response, "conversation_id", ""),
                }
            else:
                logger.warning(f"Unexpected global chat response format: {response}")
                return {
                    "output": "Je suis désolé, je n'ai pas pu générer de réponse. Veuillez réessayer.",
                    "status": "error",
                }

        except Exception as e:
            error_msg = "Global chat request to Dify failed"
            logger.error(error_msg, exc_info=True, extra={"error": str(e)})
            raise ExternalServiceError(
                "Failed to send global chat message", details={"error": str(e)}
            ) from e

    async def generate_quick_actions(
        self,
        user_preferences: Dict[str, Any],
        company_data: Dict[str, Any],
        api_key: Optional[str] = None,
    ) -> Dict[str, Any]:
        """Generate quick actions using Dify chat workflow.

        This method generates personalized quick action recommendations based on
        user preferences and company data.

        Args:
            user_preferences: User's AI preferences (role, goals, desired_output, documentation)
            company_data: Full company data for context
            api_key: Optional API key override (uses fallback if None)

        Returns:
            Dictionary with actions list:
            {
                "actions": [
                    {
                        "id": "unique_id",
                        "label": "Short label",
                        "description": "Brief description",
                        "icon": "fa fa-icon-name"
                    },
                    ...
                ]
            }

        Raises:
            ExternalServiceError: If quick actions generation fails
        """
        # Use fallback API key if not provided
        if api_key is None:
            api_key = self.fallback_api_key

        logger.info(
            "Generating quick actions via Dify",
            extra={
                "company": company_data.get("name", "Unknown"),
                "role": user_preferences.get("role", "Unknown"),
            },
        )

        try:
            # Initialize chat client
            client = ChatClient(api_key=api_key)
            # Override the default base_url with our custom instance
            client.base_url = self.base_url

            # Prepare inputs
            import json

            combined_context = {
                "user_preferences": user_preferences,
                "company": company_data,
            }

            # Create explicit query with JSON format instruction
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

            inputs = {"context": json.dumps(combined_context, ensure_ascii=False)}

            # Send chat message
            response = client.create_chat_message(
                inputs=inputs,
                query=query,
                user="chapse_assist",
                response_mode="blocking",
            )

            # Extract and parse answer
            if hasattr(response, "answer"):
                answer = response.answer

                # Extract JSON from response (handle markdown code blocks)
                import re

                answer_clean = answer.strip()

                # Try to find JSON in markdown code block
                json_match = re.search(r"```json\s*(\{[\s\S]*?\})\s*```", answer_clean)
                if json_match:
                    answer_clean = json_match.group(1)
                else:
                    # Try to find JSON in generic code block
                    json_match = re.search(r"```\s*(\{[\s\S]*?\})\s*```", answer_clean)
                    if json_match:
                        answer_clean = json_match.group(1)
                    else:
                        # Try to extract JSON directly (first { to last })
                        json_match = re.search(r"\{[\s\S]*\}", answer_clean)
                        if json_match:
                            answer_clean = json_match.group(0)

                answer_clean = answer_clean.strip()
                logger.debug(f"Extracted JSON: {answer_clean[:200]}...")

                try:
                    actions_data = json.loads(answer_clean)

                    if "actions" in actions_data and isinstance(actions_data["actions"], list):
                        # Validate at least 1 action exists
                        if len(actions_data["actions"]) == 0:
                            raise ExternalServiceError("No actions generated by AI")

                        # Limit to maximum 3 actions
                        if len(actions_data["actions"]) > 3:
                            actions_data["actions"] = actions_data["actions"][:3]

                        logger.info(
                            f"✅ Generated {len(actions_data['actions'])} quick actions",
                            extra={"count": len(actions_data["actions"])},
                        )
                        return actions_data
                    else:
                        logger.error(f"Invalid actions structure: {actions_data}")
                        raise ExternalServiceError(
                            "Invalid response structure from AI",
                            details={"response": str(actions_data)},
                        )

                except json.JSONDecodeError as e:
                    logger.error(
                        f"Failed to parse AI response as JSON: {answer[:500]}",
                        exc_info=True,
                    )
                    raise ExternalServiceError(
                        "Failed to parse AI response", details={"error": str(e)}
                    ) from e
            else:
                logger.warning(f"Unexpected quick actions response format: {response}")
                raise ExternalServiceError("Unexpected response format from AI")

        except ExternalServiceError:
            raise
        except Exception as e:
            error_msg = "Quick actions generation failed"
            logger.error(error_msg, exc_info=True, extra={"error": str(e)})
            raise ExternalServiceError(
                "Failed to generate quick actions", details={"error": str(e)}
            ) from e
