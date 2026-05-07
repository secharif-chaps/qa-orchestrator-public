"""Centralized user-facing strings for the Stream module.

All texts displayed to end users (notification bodies, button labels, event
catalog entries, test connection payloads) are defined here so they can be
maintained in a single location and prepared for future multi-language support.
"""

# --- Notification action buttons ---

BUTTON_VIEW_COMPANY: str = "Voir la fiche entreprise"


# --- Notification field labels ---

LABEL_COMPANY: str = "Entreprise"
LABEL_RESULT: str = "Résultat"


# --- Pluralized counters used in notification payloads ---


def format_successful_collections(count: int) -> str:
    """Return a French label like ``"3 collectes réussies"`` / ``"1 collecte réussie"``."""
    plural = count != 1
    return f"{count} collecte{'s' if plural else ''} réussie{'s' if plural else ''}"


def format_errors(count: int) -> str:
    """Return a French label like ``"2 erreurs"`` / ``"1 erreur"``."""
    plural = count != 1
    return f"{count} erreur{'s' if plural else ''}"


# --- Notification timestamp formatting (Babel CLDR pattern) ---

# CLDR pattern consumed by ``babel.dates.format_datetime``.
# ``MMM`` produces the localized abbreviated month (e.g. ``avr.`` for April in fr_FR).
NOTIFICATION_DATETIME_PATTERN: str = "dd MMM yyyy 'à' HH:mm"
NOTIFICATION_DATETIME_LOCALE: str = "fr_FR"


# --- Event catalog labels (frontend display) ---

EVENT_LABEL_COMPANY_CREATED: str = "Fiche entreprise créée"
EVENT_LABEL_COMPANY_UPDATED: str = "Fiche entreprise actualisée"
EVENT_LABEL_WATCHFILE_CREATED: str = "Dossier de veille créé"
EVENT_LABEL_WATCHFILE_UPDATED: str = "Dossier de veille actualisé"
EVENT_LABEL_ALERT_TRIGGERED: str = "Alerte veille déclenchée"


# --- Source labels (module display names) ---

SOURCE_LABEL_SCREEN: str = "Screen"
SOURCE_LABEL_TARGET: str = "Target"
SOURCE_LABEL_EXPLORE: str = "Explore"


# --- Test-connection payload (channel configuration validation) ---

TEST_CONNECTION_PAYLOAD_MESSAGE: str = "This is a test event from ChapsMind Stream"
TEST_CONNECTION_SUMMARY: str = "Test connection from ChapsMind Stream service"
TEST_CONNECTION_STREAM_NAME: str = "Test Connection"
