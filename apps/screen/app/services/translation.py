"""Translation service for multi-language support.

This service manages translations for company data fields across
multiple languages using a normalized translations table.

Key features:
- Registry of translatable fields per table
- Translation status tracking per company/language
- Integration with translation backends (Dify, external APIs)
"""

import hashlib
from dataclasses import dataclass
from typing import Any

from sqlalchemy import func
from sqlalchemy.orm import Session

from app.core.logging_config import get_logger
from app.models import (
    Translation,
    Company,
    CompanyProfile,
    CompanyDigital,
    CompanyTimeline,
    CompanyProducts,
    CompanyJobs,
    CompanyCsr,
    CompanyPress,
    CompanyOnlineService,
    CompanyTimelineEvent,
    CompanyProductItem,
    CompanyProductCategory,
    CompanyJobOffer,
    CompanyCsrInitiative,
    CompanyPressItem,
    CompanyTeamMember,
)

logger = get_logger(__name__)


# =============================================================================
# TRANSLATABLE FIELDS REGISTRY
# =============================================================================
# Defines which fields in each table are translatable.
# Format: {table_name: [field_names]}
# Translations are stored in the normalized 'translations' table.

TRANSLATABLE_FIELDS: dict[str, list[str]] = {
    # 1:1 Section Tables (record_id = company_id)
    "company_profile": [
        "insights",
        "business_line",
        "catchphrase",
    ],
    "company_digital": [
        "insights",
        "overall_strategy",
        "digital_transformation",
        "ecommerce_capabilities",
        "mobile_strategy",
        "digital_marketing_approach",
        "loyalty_program",
    ],
    "company_timeline": [
        "insights",
    ],
    "company_products": [
        "insights",
        "customer_type",
        "marketing_positioning",
    ],
    "company_jobs": [
        "insights_top_departments",
        "insights_hiring_focus",
        "insights_growth_indicators",
    ],
    "company_csr": [
        "insights",
        "responsibility",
    ],
    "company_press": [
        "insights",
    ],
    # 1:N Child Tables (record_id = item.id)
    "company_online_services": [
        "name",
        "description",
    ],
    "company_timeline_events": [
        "title",
        "description",
        "category",
        "impact",
    ],
    "company_product_items": [
        "value",  # Only for 'range' type, but we'll filter at translation time
    ],
    "company_product_categories": [
        "category_name",
        # "items",  # Array field - needs special handling
    ],
    "company_job_offers": [
        "title",
        "department",
        "description",
        "requirements",
    ],
    "company_csr_initiatives": [
        "value",
    ],
    "company_press_items": [
        "value",
    ],
    "company_team_members": [
        "position",
    ],
}

# 1:1 tables where record_id equals company_id
ONE_TO_ONE_TABLES = {
    "company_profile",
    "company_digital",
    "company_timeline",
    "company_products",
    "company_jobs",
    "company_csr",
    "company_press",
}

# Supported languages for translation API
# All languages use the normalized translations table.
SUPPORTED_LANGUAGES = [
    {"code": "fr", "name": "French"},
    {"code": "es", "name": "Spanish"},
    {"code": "de", "name": "German"},
    {"code": "pt", "name": "Portuguese"},
]

# All supported language codes
SUPPORTED_LANGUAGE_CODES = {"fr", "es", "de", "pt"}


@dataclass
class TranslationStatus:
    """Translation status for a language."""

    language_code: str
    language_name: str
    status: str  # "complete", "partial", "none"
    fields_translated: int
    fields_total: int

    @property
    def percentage(self) -> float:
        """Calculate translation percentage."""
        if self.fields_total == 0:
            return 0.0
        return round((self.fields_translated / self.fields_total) * 100, 1)


@dataclass
class FieldToTranslate:
    """A field that needs translation."""

    table_name: str
    record_id: int
    field_name: str
    source_value: str
    source_value_hash: str


class TranslationService:
    """Service for managing translations."""

    def __init__(self, db: Session):
        """Initialize translation service.

        Args:
            db: Database session
        """
        self.db = db

    def get_supported_languages(self) -> list[dict[str, str]]:
        """Get list of supported languages.

        Returns:
            List of language objects with code and name.
        """
        return SUPPORTED_LANGUAGES

    def get_company_translation_status(
        self,
        company_id: int,
    ) -> dict[str, Any]:
        """Get translation status for a company across all languages.

        Args:
            company_id: Company ID to check

        Returns:
            Dictionary with company_id and translation status per language.
        """
        # Get total translatable fields for this company
        total_fields = self._count_translatable_fields(company_id)

        # Get status for each language
        translations_status: dict[str, TranslationStatus] = {}

        for lang in SUPPORTED_LANGUAGES:
            lang_code = lang["code"]
            lang_name = lang["name"]

            # All languages use translations table
            translated = self._count_table_translations(company_id, lang_code)

            # Determine status
            if translated == 0:
                status = "none"
            elif translated >= total_fields:
                status = "complete"
            else:
                status = "partial"

            translations_status[lang_code] = TranslationStatus(
                language_code=lang_code,
                language_name=lang_name,
                status=status,
                fields_translated=translated,
                fields_total=total_fields,
            )

        return {
            "company_id": company_id,
            "translations": {
                code: {
                    "language_name": ts.language_name,
                    "status": ts.status,
                    "fields_translated": ts.fields_translated,
                    "fields_total": ts.fields_total,
                    "percentage": ts.percentage,
                }
                for code, ts in translations_status.items()
            },
        }

    def get_fields_to_translate(
        self,
        company_id: int,
        language_code: str,
    ) -> list[FieldToTranslate]:
        """Get list of fields that need translation for a company/language.

        Args:
            company_id: Company ID
            language_code: Target language code

        Returns:
            List of fields that need translation.
        """
        if language_code not in SUPPORTED_LANGUAGE_CODES:
            logger.warning(
                f"Language {language_code} not supported for translation"
            )
            return []

        fields_to_translate: list[FieldToTranslate] = []

        # Get existing translations for this company/language
        existing = self._get_existing_translations(company_id, language_code)
        existing_keys = {
            (t.table_name, t.record_id, t.field_name): t.source_value_hash
            for t in existing
        }

        # Check 1:1 section tables
        for table_name in ONE_TO_ONE_TABLES:
            fields = TRANSLATABLE_FIELDS.get(table_name, [])
            record = self._get_section_record(company_id, table_name)

            if not record:
                continue

            for field_name in fields:
                source_value = getattr(record, field_name, None)
                if not source_value:
                    continue

                source_hash = self._hash_value(source_value)
                key = (table_name, company_id, field_name)

                # Check if translation exists and source hasn't changed
                if key in existing_keys and existing_keys[key] == source_hash:
                    continue

                fields_to_translate.append(
                    FieldToTranslate(
                        table_name=table_name,
                        record_id=company_id,
                        field_name=field_name,
                        source_value=source_value,
                        source_value_hash=source_hash,
                    )
                )

        # Check 1:N child tables
        child_records = self._get_child_records(company_id)
        for table_name, records in child_records.items():
            fields = TRANSLATABLE_FIELDS.get(table_name, [])

            for record in records:
                record_id = record.id

                for field_name in fields:
                    source_value = getattr(record, field_name, None)
                    if not source_value:
                        continue

                    source_hash = self._hash_value(source_value)
                    key = (table_name, record_id, field_name)

                    # Check if translation exists and source hasn't changed
                    if key in existing_keys and existing_keys[key] == source_hash:
                        continue

                    fields_to_translate.append(
                        FieldToTranslate(
                            table_name=table_name,
                            record_id=record_id,
                            field_name=field_name,
                            source_value=source_value,
                            source_value_hash=source_hash,
                        )
                    )

        return fields_to_translate

    def save_translation(
        self,
        company_id: int,
        table_name: str,
        record_id: int,
        field_name: str,
        language_code: str,
        value: str,
        source_value_hash: str,
    ) -> Translation | None:
        """Save a translation to the database.

        All languages are saved to the translations table.

        Args:
            company_id: Company ID
            table_name: Source table name
            record_id: Source record ID
            field_name: Field name
            language_code: Target language code
            value: Translated value
            source_value_hash: Hash of source value

        Returns:
            Created or updated Translation record.
        """
        # Check if translation exists
        existing = (
            self.db.query(Translation)
            .filter(
                Translation.table_name == table_name,
                Translation.record_id == record_id,
                Translation.field_name == field_name,
                Translation.language_code == language_code,
            )
            .first()
        )

        if existing:
            existing.value = value
            existing.source_value_hash = source_value_hash
            existing.translated_at = func.now()
            self.db.commit()
            self.db.refresh(existing)
            return existing

        translation = Translation(
            company_id=company_id,
            table_name=table_name,
            record_id=record_id,
            field_name=field_name,
            language_code=language_code,
            value=value,
            source_value_hash=source_value_hash,
        )
        self.db.add(translation)
        self.db.commit()
        self.db.refresh(translation)
        return translation

    def _get_child_record_by_id(
        self,
        table_name: str,
        record_id: int,
    ) -> Any | None:
        """Get a child record by its ID.

        Args:
            table_name: Table name
            record_id: Record ID

        Returns:
            Record instance or None if not found.
        """
        model_map = {
            "company_online_services": CompanyOnlineService,
            "company_timeline_events": CompanyTimelineEvent,
            "company_product_items": CompanyProductItem,
            "company_product_categories": CompanyProductCategory,
            "company_job_offers": CompanyJobOffer,
            "company_csr_initiatives": CompanyCsrInitiative,
            "company_press_items": CompanyPressItem,
            "company_team_members": CompanyTeamMember,
        }
        model = model_map.get(table_name)
        if not model:
            return None
        return self.db.query(model).filter(model.id == record_id).first()

    def get_translation(
        self,
        table_name: str,
        record_id: int,
        field_name: str,
        language_code: str,
    ) -> str | None:
        """Get a translation for a specific field.

        Args:
            table_name: Source table name
            record_id: Source record ID
            field_name: Field name
            language_code: Target language code

        Returns:
            Translated value or None if not found.
        """
        translation = (
            self.db.query(Translation)
            .filter(
                Translation.table_name == table_name,
                Translation.record_id == record_id,
                Translation.field_name == field_name,
                Translation.language_code == language_code,
            )
            .first()
        )
        return translation.value if translation else None

    # =========================================================================
    # PRIVATE HELPER METHODS
    # =========================================================================

    def _hash_value(self, value: str) -> str:
        """Create hash of value for change detection."""
        return hashlib.sha256(value.encode()).hexdigest()[:64]

    def _count_translatable_fields(self, company_id: int) -> int:
        """Count total translatable fields for a company."""
        count = 0

        # Count 1:1 section fields
        for table_name in ONE_TO_ONE_TABLES:
            fields = TRANSLATABLE_FIELDS.get(table_name, [])
            record = self._get_section_record(company_id, table_name)
            if record:
                for field_name in fields:
                    if getattr(record, field_name, None):
                        count += 1

        # Count 1:N child fields
        child_records = self._get_child_records(company_id)
        for table_name, records in child_records.items():
            fields = TRANSLATABLE_FIELDS.get(table_name, [])
            for record in records:
                for field_name in fields:
                    if getattr(record, field_name, None):
                        count += 1

        return count

    def _count_table_translations(self, company_id: int, language_code: str) -> int:
        """Count translations from translations table for a language."""
        return (
            self.db.query(func.count(Translation.id))
            .filter(
                Translation.company_id == company_id,
                Translation.language_code == language_code,
                Translation.value.isnot(None),
            )
            .scalar()
            or 0
        )

    def _get_existing_translations(
        self, company_id: int, language_code: str
    ) -> list[Translation]:
        """Get existing translations for a company/language."""
        return (
            self.db.query(Translation)
            .filter(
                Translation.company_id == company_id,
                Translation.language_code == language_code,
            )
            .all()
        )

    def _get_section_record(self, company_id: int, table_name: str) -> Any | None:
        """Get 1:1 section record for a company."""
        model_map = {
            "company_profile": CompanyProfile,
            "company_digital": CompanyDigital,
            "company_timeline": CompanyTimeline,
            "company_products": CompanyProducts,
            "company_jobs": CompanyJobs,
            "company_csr": CompanyCsr,
            "company_press": CompanyPress,
        }
        model = model_map.get(table_name)
        if not model:
            return None
        return self.db.query(model).filter(model.company_id == company_id).first()

    def _get_child_records(self, company_id: int) -> dict[str, list[Any]]:
        """Get all 1:N child records for a company."""
        return {
            "company_online_services": (
                self.db.query(CompanyOnlineService)
                .filter(CompanyOnlineService.company_id == company_id)
                .all()
            ),
            "company_timeline_events": (
                self.db.query(CompanyTimelineEvent)
                .filter(CompanyTimelineEvent.company_id == company_id)
                .all()
            ),
            "company_product_items": (
                self.db.query(CompanyProductItem)
                .filter(CompanyProductItem.company_id == company_id)
                .all()
            ),
            "company_product_categories": (
                self.db.query(CompanyProductCategory)
                .filter(CompanyProductCategory.company_id == company_id)
                .all()
            ),
            "company_job_offers": (
                self.db.query(CompanyJobOffer)
                .filter(CompanyJobOffer.company_id == company_id)
                .all()
            ),
            "company_csr_initiatives": (
                self.db.query(CompanyCsrInitiative)
                .filter(CompanyCsrInitiative.company_id == company_id)
                .all()
            ),
            "company_press_items": (
                self.db.query(CompanyPressItem)
                .filter(CompanyPressItem.company_id == company_id)
                .all()
            ),
            "company_team_members": (
                self.db.query(CompanyTeamMember)
                .filter(CompanyTeamMember.company_id == company_id)
                .all()
            ),
        }

    def get_translations_map(
        self,
        company_id: int,
        language_code: str,
    ) -> dict[tuple[str, int, str], str]:
        """Get all translations for a company/language as a lookup map.

        All languages read from the translations table.

        Args:
            company_id: Company ID
            language_code: Target language code

        Returns:
            Dictionary mapping (table_name, record_id, field_name) to translated value.
        """
        translations = self._get_existing_translations(company_id, language_code)
        return {
            (t.table_name, t.record_id, t.field_name): t.value
            for t in translations
            if t.value
        }
