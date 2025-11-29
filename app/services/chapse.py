"""Chapse conversation service for Dify integration with company context.

This service handles:
- Chat message streaming via Dify API
- Conversation management (list, get, delete, rename)
- Company context storage and retrieval
"""

import json
from typing import AsyncGenerator

import httpx
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.exceptions import ExternalServiceError, ResourceNotFoundError, AuthorizationError
from app.core.logging_config import get_logger
from app.models.chapse_conversation_context import ChapseConversationContext
from app.models.company import Company

logger = get_logger(__name__)

# Maximum companies allowed in context
MAX_COMPANIES_IN_CONTEXT = 3


class ChapseService:
    """Service for Chapse chatbot with Dify integration and company context.

    This service provides a clean interface for:
    - Streaming chat messages via Dify
    - Managing conversations (CRUD operations)
    - Storing and retrieving company context per conversation

    Dify handles conversation/message storage, we only store company context locally.

    Attributes:
        db: SQLAlchemy database session
        base_url: Dify API base URL
        api_key: Dify API key for chat operations
    """

    def __init__(self, db: Session):
        """Initialize Chapse service.

        Args:
            db: SQLAlchemy session for database operations
        """
        self.db = db
        self.base_url = settings.DIFY_URL
        self.api_key = settings.DIFY_API_KEY

    # =========================================================================
    # Company Context Management
    # =========================================================================

    def _validate_company_ids(self, company_ids: list[int], organization_id: str) -> list[Company]:
        """Validate company IDs belong to organization and return companies.

        Args:
            company_ids: List of company IDs to validate
            organization_id: Organization UUID to check ownership

        Returns:
            List of Company objects

        Raises:
            ValueError: If too many companies or invalid IDs
            AuthorizationError: If companies don't belong to organization
        """
        if len(company_ids) > MAX_COMPANIES_IN_CONTEXT:
            raise ValueError(f"Maximum {MAX_COMPANIES_IN_CONTEXT} companies allowed in context")

        if not company_ids:
            return []

        # Fetch companies
        companies = self.db.query(Company).filter(
            Company.id.in_(company_ids),
            Company.organization_id == organization_id
        ).all()

        # Check all requested companies were found
        found_ids = {c.id for c in companies}
        missing_ids = set(company_ids) - found_ids

        if missing_ids:
            raise AuthorizationError(
                "Some companies do not belong to your organization",
                details={"missing_ids": list(missing_ids)}
            )

        return companies

    def _build_company_context_json(self, companies: list[Company]) -> str:
        """Build JSON string of company data for Dify inputs.

        Args:
            companies: List of Company objects

        Returns:
            JSON string with company data
        """
        if not companies:
            return "[]"

        context_data = []
        for c in companies:
            # Extract siren from profile JSON if available
            profile = c.profile or {}
            siren = profile.get("siren") if isinstance(profile, dict) else None

            context_data.append({
                "id": c.id,
                "name": c.name,
                "website": c.website,
                "siren": siren,
                "profile": c.profile,
                "digital": c.digital,
                "products": c.products,
                "csr": c.csr,
            })

        return json.dumps(context_data, ensure_ascii=False)

    def get_context(self, conversation_id: str, user_id: str) -> dict:
        """Get company context for a conversation.

        Args:
            conversation_id: Dify conversation ID
            user_id: Keycloak user ID for authorization

        Returns:
            Dict with company_ids and companies data
        """
        context = self.db.query(ChapseConversationContext).filter(
            ChapseConversationContext.dify_conversation_id == conversation_id,
            ChapseConversationContext.user_id == user_id
        ).first()

        if not context:
            return {"company_ids": [], "companies": []}

        # Fetch company summaries
        companies = []
        if context.company_ids:
            company_records = self.db.query(Company).filter(
                Company.id.in_(context.company_ids)
            ).all()
            companies = [{"id": c.id, "name": c.name} for c in company_records]

        return {
            "company_ids": context.company_ids or [],
            "companies": companies
        }

    def update_context(
        self,
        conversation_id: str,
        user_id: str,
        organization_id: str,
        company_ids: list[int]
    ) -> dict:
        """Update company context for a conversation.

        Args:
            conversation_id: Dify conversation ID
            user_id: Keycloak user ID
            organization_id: Organization UUID
            company_ids: New list of company IDs

        Returns:
            Dict with updated company_ids and companies data
        """
        # Validate companies
        companies = self._validate_company_ids(company_ids, organization_id)

        # Find or create context record
        context = self.db.query(ChapseConversationContext).filter(
            ChapseConversationContext.dify_conversation_id == conversation_id
        ).first()

        if context:
            # Verify ownership
            if context.user_id != user_id:
                raise AuthorizationError("Conversation belongs to another user")
            context.company_ids = company_ids
        else:
            # Create new context
            context = ChapseConversationContext(
                dify_conversation_id=conversation_id,
                user_id=user_id,
                organization_id=organization_id,
                company_ids=company_ids
            )
            self.db.add(context)

        self.db.commit()

        return {
            "company_ids": company_ids,
            "companies": [{"id": c.id, "name": c.name} for c in companies]
        }

    def _save_context_for_new_conversation(
        self,
        conversation_id: str,
        user_id: str,
        organization_id: str,
        company_ids: list[int]
    ) -> None:
        """Save context for a newly created conversation.

        Args:
            conversation_id: Dify conversation ID from response
            user_id: Keycloak user ID
            organization_id: Organization UUID
            company_ids: List of company IDs in context
        """
        if not company_ids:
            return

        context = ChapseConversationContext(
            dify_conversation_id=conversation_id,
            user_id=user_id,
            organization_id=organization_id,
            company_ids=company_ids
        )
        self.db.add(context)
        self.db.commit()

        logger.info(
            "Saved context for new conversation",
            extra={
                "conversation_id": conversation_id,
                "company_ids": company_ids
            }
        )

    # =========================================================================
    # Chat Operations
    # =========================================================================

    async def stream_chat(
        self,
        query: str,
        user_id: str,
        organization_id: str,
        conversation_id: str | None = None,
        company_ids: list[int] | None = None
    ) -> AsyncGenerator[str, None]:
        """Stream a chat message to Dify and yield SSE events.

        Args:
            query: User's message
            user_id: Keycloak user ID (used as Dify user)
            organization_id: Organization UUID for company validation
            conversation_id: Optional existing conversation ID
            company_ids: Optional company IDs for context

        Yields:
            SSE event strings (data: {...})

        Raises:
            ExternalServiceError: If Dify request fails
            AuthorizationError: If companies don't belong to organization
        """
        # Validate and build company context
        company_context_json = "[]"
        validated_company_ids: list[int] = []

        if company_ids:
            companies = self._validate_company_ids(company_ids, organization_id)
            company_context_json = self._build_company_context_json(companies)
            validated_company_ids = company_ids

        # Build Dify request
        payload = {
            "query": query,
            "user": user_id,
            "response_mode": "streaming",
            "inputs": {
                "company_context": company_context_json
            }
        }

        # Add conversation_id if continuing existing conversation
        if conversation_id:
            payload["conversation_id"] = conversation_id
        else:
            # Auto-generate name for new conversations
            payload["auto_generate_name"] = True

        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }

        logger.info(
            "Streaming chat to Dify",
            extra={
                "user_id": user_id,
                "conversation_id": conversation_id,
                "company_ids": validated_company_ids,
                "query_length": len(query)
            }
        )

        new_conversation_id = None

        try:
            async with httpx.AsyncClient(timeout=120.0) as client:
                async with client.stream(
                    "POST",
                    f"{self.base_url}/chat-messages",
                    json=payload,
                    headers=headers
                ) as response:
                    if response.status_code != 200:
                        error_text = await response.aread()
                        logger.error(
                            "Dify API error",
                            extra={
                                "status_code": response.status_code,
                                "response": error_text.decode()[:500]
                            }
                        )
                        raise ExternalServiceError(
                            f"Dify API error: {response.status_code}",
                            details={"response": error_text.decode()[:500]}
                        )

                    async for line in response.aiter_lines():
                        if line.startswith("data:"):
                            # Parse to extract conversation_id for new conversations
                            try:
                                data = json.loads(line[5:].strip())
                                if "conversation_id" in data and not conversation_id:
                                    new_conversation_id = data["conversation_id"]
                            except json.JSONDecodeError:
                                pass

                            yield f"{line}\n"

        except httpx.TimeoutException as e:
            logger.error("Dify chat request timed out", exc_info=True)
            raise ExternalServiceError(
                "Chat request timed out",
                details={"timeout": 120.0}
            ) from e
        except httpx.HTTPError as e:
            logger.error("HTTP error during Dify chat", exc_info=True)
            raise ExternalServiceError(
                f"HTTP error: {str(e)}",
                details={"error": str(e)}
            ) from e

        # Save context for new conversation
        if new_conversation_id and validated_company_ids:
            self._save_context_for_new_conversation(
                new_conversation_id,
                user_id,
                organization_id,
                validated_company_ids
            )

    # =========================================================================
    # Conversation Management
    # =========================================================================

    async def list_conversations(
        self,
        user_id: str,
        limit: int = 20,
        last_id: str | None = None
    ) -> dict:
        """List user's conversations from Dify with context enrichment.

        Args:
            user_id: Keycloak user ID
            limit: Number of conversations to return (max 100)
            last_id: Pagination cursor

        Returns:
            Dict with data, has_more, and limit
        """
        params = {
            "user": user_id,
            "limit": min(limit, 100)
        }
        if last_id:
            params["last_id"] = last_id

        headers = {
            "Authorization": f"Bearer {self.api_key}"
        }

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.get(
                    f"{self.base_url}/conversations",
                    params=params,
                    headers=headers
                )

                if response.status_code != 200:
                    logger.error(
                        "Failed to list conversations",
                        extra={"status_code": response.status_code}
                    )
                    raise ExternalServiceError(
                        f"Failed to list conversations: {response.status_code}"
                    )

                data = response.json()

        except httpx.HTTPError as e:
            logger.error("HTTP error listing conversations", exc_info=True)
            raise ExternalServiceError(f"HTTP error: {str(e)}") from e

        # Enrich with company context
        conversations = []
        for conv in data.get("data", []):
            conv_id = conv.get("id")

            # Get context from our database
            context = self.get_context(conv_id, user_id)

            conversations.append({
                "id": conv_id,
                "name": conv.get("name", ""),
                "created_at": conv.get("created_at", 0),
                "updated_at": conv.get("updated_at", 0),
                "company_ids": context["company_ids"],
                "companies": context["companies"]
            })

        return {
            "data": conversations,
            "has_more": data.get("has_more", False),
            "limit": limit
        }

    async def get_conversation(
        self,
        conversation_id: str,
        user_id: str,
        limit: int = 50,
        first_id: str | None = None
    ) -> dict:
        """Get conversation detail with messages from Dify.

        Args:
            conversation_id: Dify conversation ID
            user_id: Keycloak user ID
            limit: Number of messages to return
            first_id: Pagination cursor for messages

        Returns:
            Dict with conversation details and messages
        """
        # Get messages from Dify
        params = {
            "user": user_id,
            "conversation_id": conversation_id,
            "limit": limit
        }
        if first_id:
            params["first_id"] = first_id

        headers = {
            "Authorization": f"Bearer {self.api_key}"
        }

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.get(
                    f"{self.base_url}/messages",
                    params=params,
                    headers=headers
                )

                if response.status_code == 404:
                    raise ResourceNotFoundError("Conversation not found")

                if response.status_code != 200:
                    logger.error(
                        "Failed to get conversation",
                        extra={"status_code": response.status_code}
                    )
                    raise ExternalServiceError(
                        f"Failed to get conversation: {response.status_code}"
                    )

                data = response.json()

        except httpx.HTTPError as e:
            logger.error("HTTP error getting conversation", exc_info=True)
            raise ExternalServiceError(f"HTTP error: {str(e)}") from e

        # Get context from our database
        context = self.get_context(conversation_id, user_id)

        # Format messages
        messages = []
        for msg in data.get("data", []):
            messages.append({
                "id": msg.get("id"),
                "query": msg.get("query", ""),
                "answer": msg.get("answer", ""),
                "created_at": msg.get("created_at", 0),
                "feedback": msg.get("feedback")
            })

        return {
            "id": conversation_id,
            "name": data.get("data", [{}])[0].get("conversation_id", "") if data.get("data") else "",
            "created_at": 0,  # Dify messages API doesn't return conversation metadata
            "updated_at": 0,
            "company_ids": context["company_ids"],
            "companies": context["companies"],
            "messages": messages,
            "has_more_messages": data.get("has_more", False)
        }

    async def delete_conversation(self, conversation_id: str, user_id: str) -> None:
        """Delete a conversation from Dify and our context table.

        Args:
            conversation_id: Dify conversation ID
            user_id: Keycloak user ID

        Raises:
            ResourceNotFoundError: If conversation not found
            AuthorizationError: If conversation belongs to another user
        """
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.delete(
                    f"{self.base_url}/conversations/{conversation_id}",
                    headers=headers,
                    json={"user": user_id}
                )

                if response.status_code == 404:
                    raise ResourceNotFoundError("Conversation not found")

                if response.status_code not in [200, 204]:
                    logger.error(
                        "Failed to delete conversation from Dify",
                        extra={"status_code": response.status_code}
                    )
                    raise ExternalServiceError(
                        f"Failed to delete conversation: {response.status_code}"
                    )

        except httpx.HTTPError as e:
            logger.error("HTTP error deleting conversation", exc_info=True)
            raise ExternalServiceError(f"HTTP error: {str(e)}") from e

        # Delete our context record
        self.db.query(ChapseConversationContext).filter(
            ChapseConversationContext.dify_conversation_id == conversation_id,
            ChapseConversationContext.user_id == user_id
        ).delete()
        self.db.commit()

        logger.info(
            "Deleted conversation",
            extra={"conversation_id": conversation_id, "user_id": user_id}
        )

    async def rename_conversation(
        self,
        conversation_id: str,
        user_id: str,
        name: str | None = None,
        auto_generate: bool = False
    ) -> dict:
        """Rename a conversation.

        Args:
            conversation_id: Dify conversation ID
            user_id: Keycloak user ID
            name: Optional manual name
            auto_generate: If True, Dify generates name from content

        Returns:
            Dict with id and new name
        """
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json"
        }

        payload = {"user": user_id}
        if auto_generate:
            payload["auto_generate"] = True
        elif name:
            payload["name"] = name

        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                response = await client.post(
                    f"{self.base_url}/conversations/{conversation_id}/name",
                    headers=headers,
                    json=payload
                )

                if response.status_code == 404:
                    raise ResourceNotFoundError("Conversation not found")

                if response.status_code != 200:
                    logger.error(
                        "Failed to rename conversation",
                        extra={"status_code": response.status_code}
                    )
                    raise ExternalServiceError(
                        f"Failed to rename conversation: {response.status_code}"
                    )

                data = response.json()

        except httpx.HTTPError as e:
            logger.error("HTTP error renaming conversation", exc_info=True)
            raise ExternalServiceError(f"HTTP error: {str(e)}") from e

        return {
            "id": conversation_id,
            "name": data.get("name", name or "")
        }
