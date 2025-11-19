"""
Global Chaps-e Chatbot API Endpoints
Handles context-aware conversations independent of specific resources
"""
from fastapi import APIRouter, Depends, HTTPException
from app.core.organization import get_user_organization, OrganizationContext
from app.core.dependencies import get_company_service
from app.services.dify import DifyService
from app.schemas.chatbot import GlobalChatRequest, ChatResponse
from app.services.company import CompanyService
import logging

router = APIRouter(prefix="/chatbot", tags=["chatbot"])
logger = logging.getLogger(__name__)


@router.post("", response_model=ChatResponse)
async def global_chat(
    chat_request: GlobalChatRequest,
    org_context: OrganizationContext = Depends(get_user_organization),
    company_service: CompanyService = Depends(get_company_service)
):
    """
    Global chat endpoint for Chaps-e AI assistant

    Supports multiple context types:
    - company: Company-specific context
    - folder: Folder-specific context (future)
    - organization: Organization business context

    Args:
        chat_request: Chat request with message, contexts, history, and language
        org_context: Current user organization context
        company_service: Company service for fetching company data
        organization_service: Organization service for fetching organization data

    Returns:
        ChatResponse with AI response
    """
    logger.info(f"Global chat request by user {org_context.username}")
    logger.debug(f"Message: {chat_request.message[:100]}...")
    logger.debug(f"Contexts: {list(chat_request.contexts.keys()) if chat_request.contexts else 'None'}")
    logger.debug(f"Language: {chat_request.language}")

    try:
        # Initialize Dify service
        dify_service = DifyService()

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

                    # Verify organization access
                    if company.organization_id != org_context.organization_id:
                        logger.warning(f"User attempted to access company {company_id} outside their organization")
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

        # Organization context (use org_context)
        if org_context:
            prepared_contexts['organization'] = {
                "id": org_context.organization_id,
                "name": org_context.organization_name,
                # Add Phase 2 organization context fields when available:
                # "business_type": organization.business_type,
                # "business_goals": organization.business_goals,
                # "target_market": organization.target_market,
                # "products_services": organization.products_services,
                # "sales_strategy_notes": organization.sales_strategy_notes
            }
            logger.info(f"Added organization context: {org_context.organization_name}")

        # Folder context (Phase 1 - basic support)
        if chat_request.contexts and 'folder' in chat_request.contexts:
            folder_data = chat_request.contexts['folder']
            prepared_contexts['folder'] = folder_data
            logger.info(f"Added folder context: {folder_data.get('name', 'Unknown')}")

        # Assist action context (Chapse Assist feature)
        if chat_request.contexts and 'assist_action' in chat_request.contexts:
            assist_data = chat_request.contexts['assist_action']

            # Build enriched system message for Chapse Assist
            action = assist_data.get('action', {})
            user_prefs = assist_data.get('user_preferences', {})

            system_message = f"""You are Chapse Assist, an AI assistant helping a {user_prefs.get('role', 'professional')}.

USER CONTEXT:
Role: {user_prefs.get('role', 'Not specified')}
Goals: {user_prefs.get('goals', 'Not specified')}
Desired Output: {user_prefs.get('desired_output', 'Not specified')}
Documentation: {user_prefs.get('documentation', 'None provided')}

TASK: {action.get('description', action.get('label', 'Generate output'))}

Generate output according to the user's desired format and goals. Be specific, actionable, and professional. Use the company data provided in the context to personalize your response."""

            prepared_contexts['assist_action'] = assist_data
            prepared_contexts['system_message'] = system_message
            logger.info(f"Added assist_action context: {action.get('label', 'Unknown action')}")

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
            "username": org_context.username,
            "organization_name": org_context.organization.name
        }

        # Add system message to system context if present (for assist_action)
        if 'system_message' in prepared_contexts:
            system_context['system_message'] = prepared_contexts['system_message']

        # Send message to Dify with all contexts
        response_data = await dify_service.send_global_chat_message(
            message=chat_request.message,
            contexts=prepared_contexts,
            system_context=system_context,
            chat_history=chat_history
        )

        logger.info("Successfully processed global chat request")

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
