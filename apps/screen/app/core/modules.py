"""Module configuration for organization credit system.

This module defines the cost configuration and metadata for each
billable module in the system.
"""

from enum import StrEnum


class ModuleType(StrEnum):
    """Module types for credit billing."""

    SCREEN = "screen"
    TARGET = "target"
    EXPLORE = "explore"


# Module configuration with costs and display metadata
MODULE_CONFIG = {
    "screen": {
        "cost": 35,
        "label": "Fiche entreprise",
        "label_en": "Company Card",
        "icon": "fa-solid fa-table-cells",
        "item_label": "fiche entreprise",
        "item_label_en": "company card",
        "item_label_plural": "fiches entreprises",
        "item_label_plural_en": "company cards",
        "reference_type": "company",
    },
    "target": {
        "cost": 1000,
        "label": "Veille",
        "label_en": "Watch",
        "icon": "fa-solid fa-eye",
        "item_label": "veille active",
        "item_label_en": "active watch",
        "item_label_plural": "veilles actives",
        "item_label_plural_en": "active watches",
        "reference_type": "watch",
    },
    "explore": {
        "cost": 50,
        "label": "Cartographie",
        "label_en": "Mapping",
        "icon": "fa-solid fa-diagram-project",
        "item_label": "cartographie",
        "item_label_en": "mapping",
        "item_label_plural": "cartographies",
        "item_label_plural_en": "mappings",
        "reference_type": "mapping",
    },
}


def get_module_cost(module_name: str) -> int:
    """Get the token cost for a module.

    Args:
        module_name: Module identifier (screen, target, explore)

    Returns:
        Token cost per item for the module

    Raises:
        KeyError: If module_name is not valid
    """
    return MODULE_CONFIG[module_name]["cost"]


def get_module_reference_type(module_name: str) -> str:
    """Get the reference type for a module.

    Args:
        module_name: Module identifier (screen, target, explore)

    Returns:
        Reference type string for the module

    Raises:
        KeyError: If module_name is not valid
    """
    return MODULE_CONFIG[module_name]["reference_type"]


def get_all_module_names() -> list[str]:
    """Get list of all module names.

    Returns:
        List of module name strings
    """
    return list(MODULE_CONFIG.keys())
