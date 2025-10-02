"""
Global Chaps-e Chatbot API Endpoints
Handles context-aware conversations independent of specific resources
"""
from fastapi import APIRouter, Depends, HTTPException
from app.core.workspace import get_user_workspace, WorkspaceContext
from app.core.dependencies import get_company_service
from app.infrastructure.dify.client import DifyClient
from app.schemas.chatbot import GlobalChatRequest, ChatResponse
from app.services.company import CompanyService
import logging

router = APIRouter(prefix="/chatbot", tags=["chatbot"])
logger = logging.getLogger(__name__)


@router.post("", response_model=ChatResponse)
async def global_chat(
    chat_request: GlobalChatRequest,
    workspace_context: WorkspaceContext = Depends(get_user_workspace),
    company_service: CompanyService = Depends(get_company_service)
):
    """
    Global chat endpoint for Chaps-e AI assistant

    Supports multiple context types:
    - company: Company-specific context
    - folder: Folder-specific context (future)
    - workspace: Workspace business context

    Args:
        chat_request: Chat request with message, contexts, history, and language
        workspace_context: Current user workspace context
        company_service: Company service for fetching company data
        workspace_service: Workspace service for fetching workspace data

    Returns:
        ChatResponse with AI response
    """
    logger.info(f"Global chat request by user {workspace_context.username}")
    logger.debug(f"Message: {chat_request.message[:100]}...")
    logger.debug(f"Contexts: {list(chat_request.contexts.keys()) if chat_request.contexts else 'None'}")
    logger.debug(f"Language: {chat_request.language}")

    try:
        # Initialize Dify client
        dify_client = DifyClient()

        # Prepare contexts for Dify
        prepared_contexts = {}

        # Company context
        if chat_request.contexts and 'company' in chat_request.contexts:
            company_data = chat_request.contexts['company']
            company_id = company_data.get('id')

            if company_id:
                try:
                    # Fetch full company data from database
                    company = company_service.get_company(int(company_id))

                    # Verify workspace access
                    if company.workspace_id != workspace_context.workspace_id:
                        logger.warning(f"User attempted to access company {company_id} outside their workspace")
                        raise HTTPException(status_code=403, detail="Access denied to this company")

                    prepared_contexts['company'] = {
                        "id": company.id,
                        "name": company.name,
                        "website": company.website,
                        "profile": company.profile,
                        "digital": company.digital,
                        "timeline": company.timeline,
                        "products": company.products,
                        "jobs": company.jobs,
                        "csr": company.csr,
                        "press": company.press,
                        "team": company.team
                    }
                    logger.info(f"Added company context: {company.name}")
                except Exception as e:
                    logger.error(f"Error fetching company {company_id}: {str(e)}")
                    # Continue without company context rather than failing

        # Workspace context (use workspace_context)
        if workspace_context:
            prepared_contexts['workspace'] = {
                "id": workspace_context.workspace_id,
                "name": workspace_context.workspace.name,
                # Add Phase 2 workspace context fields when available:
                # "business_type": workspace.business_type,
                # "business_goals": workspace.business_goals,
                # "target_market": workspace.target_market,
                # "products_services": workspace.products_services,
                # "sales_strategy_notes": workspace.sales_strategy_notes
            }
            logger.info(f"Added workspace context: {workspace_context.workspace.name}")

        # Folder context (Phase 1 - basic support)
        if chat_request.contexts and 'folder' in chat_request.contexts:
            folder_data = chat_request.contexts['folder']
            prepared_contexts['folder'] = folder_data
            logger.info(f"Added folder context: {folder_data.get('name', 'Unknown')}")

        # Convert chat history to simple format
        chat_history = []
        if chat_request.chat_history:
            chat_history = [
                {"role": msg.role, "content": msg.content}
                for msg in chat_request.chat_history
            ]

        # Add language to system context
        system_context = {
            "language": chat_request.language or "fr",
            "username": workspace_context.username,
            "workspace_name": workspace_context.workspace.name
        }

        # Send message to Dify with all contexts
        response_data = await dify_client.send_global_chat_message(
            message=chat_request.message,
            contexts=prepared_contexts,
            system_context=system_context,
            chat_history=chat_history
        )

        logger.info(f"Successfully processed global chat request")

        return ChatResponse(
            response=response_data.get("output", response_data.get("answer", "No response")),
            status="success"
        )

    except HTTPException:
        # Re-raise HTTP exceptions
        raise
    except Exception as e:
        logger.error(f"Error in global chat: {str(e)}", exc_info=True)

        # Return error response in user's language
        error_message = (
            "Désolé, une erreur s'est produite. Veuillez réessayer."
            if chat_request.language == "fr"
            else "Sorry, an error occurred. Please try again."
        )

        return ChatResponse(
            response=error_message,
            status="error"
        )
