"""Internationalization (i18n) for Dify error messages."""
from typing import Callable

from app.schemas.dify_errors import DifyErrorType, ParsedError

# ============================================================================
# TRANSLATION HELPERS
# ============================================================================

def _format_retry_time(seconds: int | None, lang: str = "en") -> str:
    """Format retry time in a user-friendly way."""
    if not seconds:
        return "a few moments" if lang == "en" else "quelques instants"

    if seconds < 60:
        if lang == "en":
            return f"{seconds} second{'s' if seconds > 1 else ''}"
        else:
            return f"{seconds} seconde{'s' if seconds > 1 else ''}"
    elif seconds < 3600:
        minutes = seconds // 60
        if lang == "en":
            return f"{minutes} minute{'s' if minutes > 1 else ''}"
        else:
            return f"{minutes} minute{'s' if minutes > 1 else ''}"
    else:
        hours = seconds // 3600
        if lang == "en":
            return f"{hours} hour{'s' if hours > 1 else ''}"
        else:
            return f"{hours} heure{'s' if hours > 1 else ''}"


def _format_node_info(node_id: str | None, lang: str = "en") -> str:
    """Format node information if available."""
    if not node_id:
        return ""
    return f" (node: {node_id})" if lang == "en" else f" (nœud: {node_id})"


def _format_field_info(field_name: str | None, lang: str = "en") -> str:
    """Format field information if available."""
    if not field_name:
        return ""
    return f" ({field_name})"


def _format_service_info(service_name: str | None, lang: str = "en") -> str:
    """Format service information if available."""
    if not service_name:
        return ""
    return f" ({service_name})"


# ============================================================================
# ERROR MESSAGE TEMPLATES - ENGLISH (DEFAULT)
# ============================================================================

USER_MESSAGE_TEMPLATES_EN: dict[DifyErrorType, Callable[[ParsedError], str]] = {
    DifyErrorType.RATE_LIMIT_LLM: lambda e: (
        f"The service is temporarily overloaded. "
        f"Please retry in {_format_retry_time(e.retry_after_seconds, 'en')}."
        if e.retry_after_seconds
        else "The service is temporarily overloaded. Please retry in a few minutes."
    ),

    DifyErrorType.RATE_LIMIT_API: lambda e: (
        f"Rate limit exceeded. "
        f"Please wait {_format_retry_time(e.retry_after_seconds, 'en')} before retrying."
        if e.retry_after_seconds
        else "Rate limit exceeded. Please wait a few moments."
    ),

    DifyErrorType.AUTH_INVALID_KEY: lambda e: (
        "Authentication error with external service. "
        "Please contact your administrator."
    ),

    DifyErrorType.AUTH_EXPIRED: lambda e: (
        "Authentication session has expired. "
        "Please retry the operation."
    ),

    DifyErrorType.WORKFLOW_TIMEOUT: lambda e: (
        "The processing took too long and was interrupted. "
        "Please retry with simpler data or contact support."
    ),

    DifyErrorType.WORKFLOW_NODE_ERROR: lambda e: (
        f"An error occurred during processing{_format_node_info(e.technical_details.get('node_id'), 'en')}. "
        "Please retry or contact support if the problem persists."
    ),

    DifyErrorType.WORKFLOW_VALIDATION_ERROR: lambda e: (
        "The provided data is not valid. "
        "Please check the entered information."
    ),

    DifyErrorType.DATA_INVALID_INPUT: lambda e: (
        f"The entered data is invalid{_format_field_info(e.technical_details.get('field_name'), 'en')}. "
        "Please correct and retry."
    ),

    DifyErrorType.DATA_MISSING_FIELD: lambda e: (
        f"A required field is missing{_format_field_info(e.technical_details.get('field_name'), 'en')}. "
        "Please complete the information."
    ),

    DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE: lambda e: (
        f"The external service{_format_service_info(e.technical_details.get('service_name'), 'en')} "
        "is temporarily unavailable. Please retry later."
    ),

    DifyErrorType.EXTERNAL_API_ERROR: lambda e: (
        f"Error communicating with external service"
        f"{_format_service_info(e.technical_details.get('service_name'), 'en')}. "
        "Please retry."
    ),

    DifyErrorType.NETWORK_TIMEOUT: lambda e: (
        "Connection timeout exceeded. "
        "Please check your internet connection and retry."
    ),

    DifyErrorType.NETWORK_CONNECTION_ERROR: lambda e: (
        "Unable to connect to the service. "
        "Please check your internet connection."
    ),

    DifyErrorType.UNKNOWN: lambda e: (
        "An unexpected error occurred. "
        "Please retry or contact support if the problem persists."
    ),
}


# ============================================================================
# ERROR MESSAGE TEMPLATES - FRENCH
# ============================================================================

USER_MESSAGE_TEMPLATES_FR: dict[DifyErrorType, Callable[[ParsedError], str]] = {
    DifyErrorType.RATE_LIMIT_LLM: lambda e: (
        f"Le service est temporairement saturé. "
        f"Merci de réessayer dans {_format_retry_time(e.retry_after_seconds, 'fr')}."
        if e.retry_after_seconds
        else "Le service est temporairement saturé. Merci de réessayer dans quelques minutes."
    ),

    DifyErrorType.RATE_LIMIT_API: lambda e: (
        f"Limite de requêtes atteinte. "
        f"Merci de patienter {_format_retry_time(e.retry_after_seconds, 'fr')} avant de réessayer."
        if e.retry_after_seconds
        else "Limite de requêtes atteinte. Merci de patienter quelques instants."
    ),

    DifyErrorType.AUTH_INVALID_KEY: lambda e: (
        "Erreur d'authentification avec le service externe. "
        "Veuillez contacter l'administrateur."
    ),

    DifyErrorType.AUTH_EXPIRED: lambda e: (
        "La session d'authentification a expiré. "
        "Veuillez réessayer l'opération."
    ),

    DifyErrorType.WORKFLOW_TIMEOUT: lambda e: (
        "Le traitement a pris trop de temps et a été interrompu. "
        "Veuillez réessayer avec des données plus simples ou contacter le support."
    ),

    DifyErrorType.WORKFLOW_NODE_ERROR: lambda e: (
        f"Une erreur s'est produite lors du traitement{_format_node_info(e.technical_details.get('node_id'), 'fr')}. "
        "Veuillez réessayer ou contacter le support si le problème persiste."
    ),

    DifyErrorType.WORKFLOW_VALIDATION_ERROR: lambda e: (
        "Les données fournies ne sont pas valides. "
        "Veuillez vérifier les informations saisies."
    ),

    DifyErrorType.DATA_INVALID_INPUT: lambda e: (
        f"Les données saisies sont invalides{_format_field_info(e.technical_details.get('field_name'), 'fr')}. "
        "Veuillez corriger et réessayer."
    ),

    DifyErrorType.DATA_MISSING_FIELD: lambda e: (
        f"Un champ requis est manquant{_format_field_info(e.technical_details.get('field_name'), 'fr')}. "
        "Veuillez compléter les informations."
    ),

    DifyErrorType.EXTERNAL_SERVICE_UNAVAILABLE: lambda e: (
        f"Le service externe{_format_service_info(e.technical_details.get('service_name'), 'fr')} "
        "est temporairement indisponible. Veuillez réessayer plus tard."
    ),

    DifyErrorType.EXTERNAL_API_ERROR: lambda e: (
        f"Erreur lors de la communication avec le service externe"
        f"{_format_service_info(e.technical_details.get('service_name'), 'fr')}. "
        "Veuillez réessayer."
    ),

    DifyErrorType.NETWORK_TIMEOUT: lambda e: (
        "Le délai de connexion a été dépassé. "
        "Veuillez vérifier votre connexion internet et réessayer."
    ),

    DifyErrorType.NETWORK_CONNECTION_ERROR: lambda e: (
        "Impossible de se connecter au service. "
        "Veuillez vérifier votre connexion internet."
    ),

    DifyErrorType.UNKNOWN: lambda e: (
        "Une erreur inattendue s'est produite. "
        "Veuillez réessayer ou contacter le support si le problème persiste."
    ),
}


# ============================================================================
# LANGUAGE REGISTRY
# ============================================================================

SUPPORTED_LANGUAGES = ["en", "fr"]
DEFAULT_LANGUAGE = "en"

MESSAGE_TEMPLATES_REGISTRY: dict[str, dict[DifyErrorType, Callable[[ParsedError], str]]] = {
    "en": USER_MESSAGE_TEMPLATES_EN,
    "fr": USER_MESSAGE_TEMPLATES_FR,
}


# ============================================================================
# TRANSLATION FUNCTION
# ============================================================================

def get_message_template(
    error_type: DifyErrorType,
    language: str = DEFAULT_LANGUAGE
) -> Callable[[ParsedError], str]:
    """
    Get message template for error type in specified language.

    Args:
        error_type: The categorized error type
        language: ISO 639-1 language code (en, fr)

    Returns:
        Message template function
    """
    # Normalize language code
    lang = language.lower()[:2]

    # Fall back to English if language not supported
    if lang not in SUPPORTED_LANGUAGES:
        lang = DEFAULT_LANGUAGE

    # Get templates for language
    templates = MESSAGE_TEMPLATES_REGISTRY.get(lang, USER_MESSAGE_TEMPLATES_EN)

    # Get template for error type, fall back to UNKNOWN
    return templates.get(error_type, templates[DifyErrorType.UNKNOWN])
