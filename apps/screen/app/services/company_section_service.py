"""Company section service for normalized table data operations.

This module provides writer and reader functions for company section data
stored in normalized tables (1:1 sections and 1:N children).

Writers:
- save_profile_data, save_digital_data, save_timeline_data, etc.
- Called by _update_company_data to persist agent output data

Readers:
- get_profile_data, get_digital_data, get_timeline_data, etc.
- Called by _build_company_response to read data for API responses

All functions handle the transformation between agent JSON output
and normalized database tables with SourcedValue pattern.
"""

import json
import logging
from datetime import date as _Date
from typing import Any

from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.models.company_children import (
    CompanyCorporateEntity,
    CompanyCsrInitiative,
    CompanyJobOffer,
    CompanyOnlineService,
    CompanyPressItem,
    CompanyProductCategory,
    CompanyProductItem,
    CompanySanctionItem,
    CompanySocialMediaAccount,
    CompanyTeamMember,
    CompanyTimelineEvent,
    CorporateRelationshipType,
    CsrInitiativeType,
    PressItemType,
    ProductItemType,
    RiskLevel,
    SanctionType,
)
from app.models.company_financial import CompanyFinancial, CompanyFinancialMetric, CompanyFundingRound
from app.models.company_patents import CompanyPatentItem, CompanyPatents
from app.models.company_sections import (
    CompanyCsr,
    CompanyDigital,
    CompanyJobs,
    CompanyPress,
    CompanyProducts,
    CompanyProfile,
    CompanySanctions,
    CompanyTimeline,
)

logger = logging.getLogger(__name__)


# =============================================================================
# HELPER FUNCTIONS
# =============================================================================


def _get_sourced_value(data: dict, key: str) -> tuple[str | None, str | None]:
    """Extract value and source from a SourcedValue structure in agent data.

    Args:
        data: Dictionary containing the agent data
        key: Key to extract (e.g., "groupName")

    Returns:
        Tuple of (value, source) - both may be None if not present
    """
    field_data = data.get(key)
    if field_data is None:
        return None, None
    if isinstance(field_data, dict):
        return field_data.get("value"), field_data.get("source")
    # If it's a string, treat it as the value with no source
    if isinstance(field_data, str):
        return field_data, None
    return None, None


def _get_string_value(data: dict | str | None, key: str) -> str | None:
    """Extract a plain string value from agent data (for insights, etc.).

    Args:
        data: Dictionary containing the agent data, or a string, or None
        key: Key to extract

    Returns:
        String value or None
    """
    if not isinstance(data, dict):
        return None
    value = data.get(key)
    if isinstance(value, str):
        return value
    return None


def _parse_date(value: str | None) -> _Date | None:
    """Parse an ISO 8601 date string (YYYY-MM-DD) to a date object.

    Args:
        value: Date string in ISO 8601 format, or None

    Returns:
        date object if parsing succeeds, None otherwise
    """
    if not value:
        return None
    try:
        return _Date.fromisoformat(value)
    except (ValueError, TypeError):
        logger.warning(f"Could not parse date: {value}")
        return None


# =============================================================================
# PROFILE DATA - WRITER AND READER
# =============================================================================


def save_profile_data(db: Session, company_id: int, data: dict) -> None:
    """Save profile data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "profile")
    """
    # Data comes at root level from agent, not nested under "profile"
    profile_data = data
    if not profile_data:
        logger.debug(f"No profile data to save for company {company_id}")
        return

    # Check for existing profile record
    existing = db.query(CompanyProfile).filter(CompanyProfile.company_id == company_id).first()

    if existing:
        # Update existing record
        profile = existing
    else:
        # Create new record
        profile = CompanyProfile(company_id=company_id)
        db.add(profile)

    # Extract values from agent data
    profile.insights = _get_string_value(profile_data, "insights")
    profile.insights_source = "Chaps-e"  # Insights are always from Chaps-e

    # SourcedValue fields
    profile.group_name, profile.group_name_source = _get_sourced_value(profile_data, "groupName")
    profile.business_line, profile.business_line_source = _get_sourced_value(profile_data, "businessLine")
    profile.catchphrase, profile.catchphrase_source = _get_sourced_value(profile_data, "catchphrase")
    profile.establishment_year, profile.establishment_year_source = _get_sourced_value(
        profile_data, "establishmentYear"
    )
    profile.employee_count, profile.employee_count_source = _get_sourced_value(profile_data, "employeeCount")
    profile.revenue, profile.revenue_source = _get_sourced_value(profile_data, "revenue")
    profile.ceo, profile.ceo_source = _get_sourced_value(profile_data, "ceo")
    profile.hq, profile.hq_source = _get_sourced_value(profile_data, "hq")

    db.flush()
    logger.info(f"Saved profile data for company {company_id}")


def get_profile_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read profile data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.profile interface
    """
    profile = db.query(CompanyProfile).filter(CompanyProfile.company_id == company_id).first()

    if not profile:
        return {}

    result = {}

    if profile.insights:
        result["insights"] = profile.insights

    if profile.group_name:
        result["groupName"] = {
            "value": profile.group_name,
            "source": profile.group_name_source,
        }

    if profile.business_line:
        result["businessLine"] = {
            "value": profile.business_line,
            "source": profile.business_line_source,
        }

    if profile.catchphrase:
        result["catchphrase"] = {
            "value": profile.catchphrase,
            "source": profile.catchphrase_source,
        }

    if profile.establishment_year:
        result["establishmentYear"] = {
            "value": profile.establishment_year,
            "source": profile.establishment_year_source,
        }

    if profile.employee_count:
        result["employeeCount"] = {
            "value": profile.employee_count,
            "source": profile.employee_count_source,
        }

    if profile.revenue:
        result["revenue"] = {
            "value": profile.revenue,
            "source": profile.revenue_source,
        }

    if profile.ceo:
        result["ceo"] = {
            "value": profile.ceo,
            "source": profile.ceo_source,
        }

    if profile.hq:
        result["hq"] = {
            "value": profile.hq,
            "source": profile.hq_source,
        }

    return result


# =============================================================================
# DIGITAL DATA - WRITER AND READER
# =============================================================================


def save_digital_data(db: Session, company_id: int, data: dict) -> None:
    """Save digital data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "digital")
    """
    # Data comes at root level from agent, not nested under "digital"
    digital_data = data
    if not digital_data:
        logger.debug(f"No digital data to save for company {company_id}")
        return

    # Check for existing digital record
    existing = db.query(CompanyDigital).filter(CompanyDigital.company_id == company_id).first()

    if existing:
        digital = existing
    else:
        digital = CompanyDigital(company_id=company_id)
        db.add(digital)

    # Insights
    digital.insights = _get_string_value(digital_data, "insights")
    digital.insights_source = "Chaps-e"

    # Digital strategy fields (nested under digital_strategy - snake_case)
    strategy = digital_data.get("digital_strategy", {})
    if isinstance(strategy, dict):
        # Handle value wrapper if present
        if "value" in strategy:
            strategy = strategy.get("value", {})
        (
            digital.overall_strategy,
            digital.overall_strategy_source,
        ) = _get_sourced_value(strategy, "overall_strategy")
        (
            digital.digital_transformation,
            digital.digital_transformation_source,
        ) = _get_sourced_value(strategy, "digital_transformation")
        (
            digital.ecommerce_capabilities,
            digital.ecommerce_capabilities_source,
        ) = _get_sourced_value(strategy, "e_commerce_capabilities")
        (
            digital.mobile_strategy,
            digital.mobile_strategy_source,
        ) = _get_sourced_value(strategy, "mobile_strategy")
        (
            digital.digital_marketing_approach,
            digital.digital_marketing_approach_source,
        ) = _get_sourced_value(strategy, "digital_marketing_approach")

    # Loyalty program - now an array, extract first program's description
    loyalty_programs = digital_data.get("loyalty_programs", [])
    if isinstance(loyalty_programs, list) and len(loyalty_programs) > 0:
        first_program = loyalty_programs[0]
        if isinstance(first_program, dict):
            # Get name and description from SourcedValue pattern
            name_data = first_program.get("name", {})
            desc_data = first_program.get("description", {})
            name = name_data.get("value", "") if isinstance(name_data, dict) else ""
            desc = desc_data.get("value", "") if isinstance(desc_data, dict) else ""
            source = name_data.get("source", "") if isinstance(name_data, dict) else ""
            digital.loyalty_program = f"{name}: {desc}" if name else desc
            digital.loyalty_program_source = source

    db.flush()

    # Save online services (1:N) - snake_case key
    try:
        online_services = digital_data.get("online_services", [])
        if isinstance(online_services, list):
            with db.begin_nested():
                _save_online_services(db, company_id, online_services)
    except SQLAlchemyError:
        logger.error(f"Failed to save online_services for company {company_id}", exc_info=True)

    # Save social media accounts (1:N) - snake_case key
    try:
        with db.begin_nested():
            _save_social_media_accounts(db, company_id, digital_data.get("social_media_accounts", []))
    except SQLAlchemyError:
        logger.error(f"Failed to save social_media_accounts for company {company_id}", exc_info=True)

    logger.info(f"Saved digital data for company {company_id}")


def _save_online_services(db: Session, company_id: int, services: list[dict]) -> None:
    """Save online services to normalized table."""
    # Delete existing services
    db.query(CompanyOnlineService).filter(CompanyOnlineService.company_id == company_id).delete()

    for service_data in services:
        if not isinstance(service_data, dict):
            continue

        # Extract name and source from SourcedValue pattern
        name_data = service_data.get("name")
        name = None
        name_source = None
        if isinstance(name_data, dict):
            name = name_data.get("value")
            name_source = name_data.get("source")
        elif isinstance(name_data, str):
            name = name_data

        # Extract description and source from SourcedValue pattern
        desc_data = service_data.get("description")
        desc = None
        desc_source = None
        if isinstance(desc_data, dict):
            desc = desc_data.get("value")
            desc_source = desc_data.get("source")
        elif isinstance(desc_data, str):
            desc = desc_data

        # Use top-level source as fallback when name/desc don't have their own source
        if not name_source:
            name_source = service_data.get("source")
        if not desc_source:
            desc_source = service_data.get("source")

        service = CompanyOnlineService(
            company_id=company_id,
            name=name,
            name_source=name_source,
            description=desc,
            description_source=desc_source,
        )
        db.add(service)

    db.flush()


def _save_social_media_accounts(db: Session, company_id: int, accounts: list[dict]) -> None:
    """Save social media accounts to normalized table."""
    # Delete existing accounts
    db.query(CompanySocialMediaAccount).filter(CompanySocialMediaAccount.company_id == company_id).delete()

    for account_data in accounts:
        if not isinstance(account_data, dict):
            continue

        # Extract platform and source from SourcedValue pattern
        platform_data = account_data.get("platform")
        platform = None
        platform_source = None
        if isinstance(platform_data, dict):
            platform = platform_data.get("value")
            platform_source = platform_data.get("source")
        elif isinstance(platform_data, str):
            platform = platform_data

        # Extract URL and source from SourcedValue pattern
        url_data = account_data.get("url")
        url = None
        url_source = None
        if isinstance(url_data, dict):
            url = url_data.get("value")
            url_source = url_data.get("source")
        elif isinstance(url_data, str):
            url = url_data

        # Use top-level source as fallback
        if not platform_source:
            platform_source = account_data.get("source")
        if not url_source:
            url_source = account_data.get("source")

        account = CompanySocialMediaAccount(
            company_id=company_id,
            platform=platform,
            platform_source=platform_source,
            url=url,
            url_source=url_source,
        )
        db.add(account)

    db.flush()


def get_digital_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read digital data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.digital interface
    """
    digital = db.query(CompanyDigital).filter(CompanyDigital.company_id == company_id).first()

    if not digital:
        return {}

    result = {}

    if digital.insights:
        result["insights"] = digital.insights

    # Build digital strategy object
    digital_strategy = {}
    if digital.overall_strategy:
        digital_strategy["overallStrategy"] = {
            "value": digital.overall_strategy,
            "source": digital.overall_strategy_source,
        }
    if digital.digital_transformation:
        digital_strategy["digitalTransformation"] = {
            "value": digital.digital_transformation,
            "source": digital.digital_transformation_source,
        }
    if digital.ecommerce_capabilities:
        digital_strategy["eCommerceCapabilities"] = {
            "value": digital.ecommerce_capabilities,
            "source": digital.ecommerce_capabilities_source,
        }
    if digital.mobile_strategy:
        digital_strategy["mobileStrategy"] = {
            "value": digital.mobile_strategy,
            "source": digital.mobile_strategy_source,
        }
    if digital.digital_marketing_approach:
        digital_strategy["digitalMarketingApproach"] = {
            "value": digital.digital_marketing_approach,
            "source": digital.digital_marketing_approach_source,
        }

    if digital_strategy:
        result["digitalStrategy"] = {
            "value": digital_strategy,
            "source": "Chaps-e",
        }

    # Get online services
    services = db.query(CompanyOnlineService).filter(CompanyOnlineService.company_id == company_id).all()

    if services:
        services_list = [
            {
                "name": s.name,
                "description": s.description,
            }
            for s in services
        ]
        result["onlineServices"] = {
            "value": {"services": services_list},
            "source": "Chaps-e",
        }

    # Get social media accounts
    accounts = db.query(CompanySocialMediaAccount).filter(CompanySocialMediaAccount.company_id == company_id).all()

    if accounts:
        result["socialMediaAccounts"] = [
            {
                "platform": a.platform,
                "url": a.url,
                "source": a.platform_source,
            }
            for a in accounts
        ]

    if digital.loyalty_program:
        result["loyaltyProgram"] = {
            "value": digital.loyalty_program,
            "source": digital.loyalty_program_source,
        }

    return result


# =============================================================================
# TIMELINE DATA - WRITER AND READER
# =============================================================================


def save_timeline_data(db: Session, company_id: int, data: dict) -> None:
    """Save timeline data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "timeline")
    """
    # Data comes at root level from agent, not nested under "timeline"
    timeline_data = data
    if not timeline_data:
        logger.debug(f"No timeline data to save for company {company_id}")
        return

    # Check for existing timeline record
    existing = db.query(CompanyTimeline).filter(CompanyTimeline.company_id == company_id).first()

    if existing:
        timeline = existing
    else:
        timeline = CompanyTimeline(company_id=company_id)
        db.add(timeline)

    # Insights
    timeline.insights = _get_string_value(timeline_data, "insights")
    timeline.insights_source = "Chaps-e"

    db.flush()

    # Save timeline events (1:N)
    _save_timeline_events(db, company_id, timeline_data.get("events", []))

    logger.info(f"Saved timeline data for company {company_id}")


def _save_timeline_events(db: Session, company_id: int, events: list[dict]) -> None:
    """Save timeline events to normalized table.

    Events without any meaningful content (no title, description, or impact)
    are dropped: the LangGraph timeline agent occasionally emits events with
    only a date, which would render as empty cards in the UI (TAR-1625).
    """
    # Delete existing events
    db.query(CompanyTimelineEvent).filter(CompanyTimelineEvent.company_id == company_id).delete()

    skipped = 0

    for event_data in events:
        if not isinstance(event_data, dict):
            continue

        date, date_source = _get_sourced_value(event_data, "date")
        title, title_source = _get_sourced_value(event_data, "title")
        desc, desc_source = _get_sourced_value(event_data, "description")
        category, category_source = _get_sourced_value(event_data, "category")
        location, location_source = _get_sourced_value(event_data, "location")
        impact, impact_source = _get_sourced_value(event_data, "impact")

        if not (title or desc or impact):
            skipped += 1
            continue

        event = CompanyTimelineEvent(
            company_id=company_id,
            date=date,
            date_source=date_source,
            title=title,
            title_source=title_source,
            description=desc,
            description_source=desc_source,
            category=category,
            category_source=category_source,
            location=location,
            location_source=location_source,
            impact=impact,
            impact_source=impact_source,
        )
        db.add(event)

    if skipped:
        logger.info(f"Filtered {skipped} empty timeline event(s) for company {company_id}")

    db.flush()


def get_timeline_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read timeline data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.timeline interface
    """
    timeline = db.query(CompanyTimeline).filter(CompanyTimeline.company_id == company_id).first()

    if not timeline:
        return {}

    result = {}

    if timeline.insights:
        result["insights"] = timeline.insights

    # Get timeline events
    events = db.query(CompanyTimelineEvent).filter(CompanyTimelineEvent.company_id == company_id).all()

    if events:
        events_list = []
        for e in events:
            # Skip events with no meaningful content; protects against legacy
            # rows persisted before TAR-1625's write-side filter was in place.
            if not (e.title or e.description or e.impact):
                continue
            event_dict = {}
            if e.date:
                event_dict["date"] = {"value": e.date, "source": e.date_source}
            if e.title:
                event_dict["title"] = {"value": e.title, "source": e.title_source}
            if e.description:
                event_dict["description"] = {"value": e.description, "source": e.description_source}
            if e.category:
                event_dict["category"] = {"value": e.category, "source": e.category_source}
            if e.location:
                event_dict["location"] = {"value": e.location, "source": e.location_source}
            if e.impact:
                event_dict["impact"] = {"value": e.impact, "source": e.impact_source}
            events_list.append(event_dict)
        if events_list:
            result["events"] = events_list

    return result


# =============================================================================
# PRODUCTS DATA - WRITER AND READER
# =============================================================================


def save_products_data(db: Session, company_id: int, data: dict) -> None:
    """Save products data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "products")
    """
    # Data comes at root level from agent, not nested under "products"
    products_data = data
    if not products_data:
        logger.debug(f"No products data to save for company {company_id}")
        return

    # Check for existing products record
    existing = db.query(CompanyProducts).filter(CompanyProducts.company_id == company_id).first()

    if existing:
        products = existing
    else:
        products = CompanyProducts(company_id=company_id)
        db.add(products)

    # Insights
    products.insights = _get_string_value(products_data, "insights")
    products.insights_source = "Chaps-e"

    # Customer type and marketing positioning (snake_case keys)
    (
        products.customer_type,
        products.customer_type_source,
    ) = _get_sourced_value(products_data, "customer_type")
    (
        products.marketing_positioning,
        products.marketing_positioning_source,
    ) = _get_sourced_value(products_data, "marketing_positioning")

    db.flush()

    # Save product items (1:N) - range, partner brands, private labels
    try:
        with db.begin_nested():
            _save_product_items(db, company_id, products_data)
    except SQLAlchemyError:
        logger.error(f"Failed to save product_items for company {company_id}", exc_info=True)

    # Save product categories (1:N)
    try:
        with db.begin_nested():
            _save_product_categories(db, company_id, products_data.get("categories", []))
    except SQLAlchemyError:
        logger.error(f"Failed to save product_categories for company {company_id}", exc_info=True)

    logger.info(f"Saved products data for company {company_id}")


def _save_product_items(db: Session, company_id: int, products_data: dict) -> None:
    """Save product items to normalized table."""
    # Delete existing items
    db.query(CompanyProductItem).filter(CompanyProductItem.company_id == company_id).delete()

    def extract_item_value(item: dict | str) -> tuple[str | None, str | None]:
        """Extract value and source from product item (handles nested name/value pattern)."""
        if isinstance(item, str):
            return item, None
        if isinstance(item, dict):
            # Check for nested name SourcedValue pattern: { name: { value, source } }
            name_data = item.get("name")
            if isinstance(name_data, dict):
                return name_data.get("value"), name_data.get("source")
            # Fallback to direct value/source pattern
            return item.get("value"), item.get("source")
        return None, None

    # Save range items
    for item in products_data.get("range", []):
        value, source = extract_item_value(item)
        if value:
            db.add(
                CompanyProductItem(
                    company_id=company_id,
                    type=ProductItemType.range,
                    value=value,
                    value_source=source,
                )
            )

    # Save partner brands (snake_case key)
    for item in products_data.get("partner_brands", []):
        value, source = extract_item_value(item)
        if value:
            db.add(
                CompanyProductItem(
                    company_id=company_id,
                    type=ProductItemType.partner_brand,
                    value=value,
                    value_source=source,
                )
            )

    # Save private labels (snake_case key)
    for item in products_data.get("private_labels", []):
        value, source = extract_item_value(item)
        if value:
            db.add(
                CompanyProductItem(
                    company_id=company_id,
                    type=ProductItemType.private_label,
                    value=value,
                    value_source=source,
                )
            )

    db.flush()


def _save_product_categories(db: Session, company_id: int, categories: dict | list) -> None:
    """Save product categories to normalized table.

    Supports two formats:
    1. List format: [{"name": "Category", "items": ["item1", "item2"]}]
    2. Legacy dict format: {"Category": ["item1", "item2"]}
    """
    # Delete existing categories
    db.query(CompanyProductCategory).filter(CompanyProductCategory.company_id == company_id).delete()

    if isinstance(categories, list):
        # New list format from schema
        for cat in categories:
            if not isinstance(cat, dict):
                continue
            category_name = cat.get("name")
            items = cat.get("items", [])
            if not isinstance(items, list):
                items = [items] if items else []
            if category_name:
                db.add(
                    CompanyProductCategory(
                        company_id=company_id,
                        category_name=category_name,
                        items=items,
                    )
                )
    elif isinstance(categories, dict):
        # Legacy dict format
        for category_name, items in categories.items():
            if not isinstance(items, list):
                items = [items] if items else []
            db.add(
                CompanyProductCategory(
                    company_id=company_id,
                    category_name=category_name,
                    items=items,
                )
            )

    db.flush()


def get_products_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read products data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.products interface
    """
    products = db.query(CompanyProducts).filter(CompanyProducts.company_id == company_id).first()

    if not products:
        return {}

    result = {}

    if products.insights:
        result["insights"] = products.insights

    if products.customer_type:
        result["customerType"] = {
            "value": products.customer_type,
            "source": products.customer_type_source,
        }

    if products.marketing_positioning:
        result["marketingPositioning"] = {
            "value": products.marketing_positioning,
            "source": products.marketing_positioning_source,
        }

    # Get product items
    items = db.query(CompanyProductItem).filter(CompanyProductItem.company_id == company_id).all()

    range_items = [{"value": i.value, "source": i.value_source} for i in items if i.type == ProductItemType.range]
    partner_brands = [
        {"value": i.value, "source": i.value_source} for i in items if i.type == ProductItemType.partner_brand
    ]
    private_labels = [
        {"value": i.value, "source": i.value_source} for i in items if i.type == ProductItemType.private_label
    ]

    if range_items:
        result["range"] = range_items
    if partner_brands:
        result["partnerBrands"] = partner_brands
    if private_labels:
        result["privateLabels"] = private_labels

    # Get product categories
    categories = db.query(CompanyProductCategory).filter(CompanyProductCategory.company_id == company_id).all()

    if categories:
        result["categories"] = {c.category_name: c.items or [] for c in categories}

    return result


# =============================================================================
# JOBS DATA - WRITER AND READER
# =============================================================================


def save_jobs_data(db: Session, company_id: int, data: dict) -> None:
    """Save jobs data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "jobs")
    """
    # Data comes at root level from agent, not nested under "jobs"
    jobs_data = data
    if not jobs_data:
        logger.debug(f"No jobs data to save for company {company_id}")
        return

    # Check for existing jobs record
    existing = db.query(CompanyJobs).filter(CompanyJobs.company_id == company_id).first()

    if existing:
        jobs = existing
    else:
        jobs = CompanyJobs(company_id=company_id)
        db.add(jobs)

    # Extract insights_data (structured data from agent prompt's insights_data field)
    insights_data = jobs_data.get("insights_data", {})
    if isinstance(insights_data, dict):
        # Total openings
        total_openings_data = insights_data.get("total_openings", {})
        if isinstance(total_openings_data, dict):
            value = total_openings_data.get("value")
            if isinstance(value, int | float) or isinstance(value, str) and value.isdigit():
                jobs.insights_total_openings = int(value)
            jobs.insights_total_openings_source = total_openings_data.get("source")

        # Top departments
        top_depts_data = insights_data.get("top_departments", {})
        if isinstance(top_depts_data, dict):
            value = top_depts_data.get("value")
            if isinstance(value, list):
                jobs.insights_top_departments = ", ".join(str(v) for v in value)
            else:
                jobs.insights_top_departments = str(value) if value else None
            jobs.insights_top_departments_source = top_depts_data.get("source")

        # Hiring focus
        (
            jobs.insights_hiring_focus,
            jobs.insights_hiring_focus_source,
        ) = _get_sourced_value(insights_data, "hiring_focus")

        # Growth indicators
        (
            jobs.insights_growth_indicators,
            jobs.insights_growth_indicators_source,
        ) = _get_sourced_value(insights_data, "growth_indicators")

    db.flush()

    # Save job offers (1:N)
    _save_job_offers(db, company_id, jobs_data.get("offers", []))

    logger.info(f"Saved jobs data for company {company_id}")


def _save_job_offers(db: Session, company_id: int, offers: list[dict]) -> None:
    """Save job offers to normalized table."""
    # Delete existing offers
    db.query(CompanyJobOffer).filter(CompanyJobOffer.company_id == company_id).delete()

    for offer_data in offers:
        if not isinstance(offer_data, dict):
            continue

        title, title_source = _get_sourced_value(offer_data, "title")
        location, location_source = _get_sourced_value(offer_data, "location")
        dept, dept_source = _get_sourced_value(offer_data, "department")
        desc, desc_source = _get_sourced_value(offer_data, "description")
        reqs, reqs_source = _get_sourced_value(offer_data, "requirements")
        date, date_source = _get_sourced_value(offer_data, "posted_date")

        offer = CompanyJobOffer(
            company_id=company_id,
            title=title,
            title_source=title_source,
            location=location,
            location_source=location_source,
            department=dept,
            department_source=dept_source,
            description=desc,
            description_source=desc_source,
            requirements=reqs,
            requirements_source=reqs_source,
            posted_date=date,
            posted_date_source=date_source,
        )
        db.add(offer)

    db.flush()


def get_jobs_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read jobs data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.jobs interface
    """
    jobs = db.query(CompanyJobs).filter(CompanyJobs.company_id == company_id).first()

    if not jobs:
        return {}

    result = {}

    # Build insights object
    insights = {}
    if jobs.insights_total_openings is not None:
        insights["total_openings"] = {
            "value": jobs.insights_total_openings,
            "source": jobs.insights_total_openings_source,
        }
    if jobs.insights_top_departments:
        insights["top_departments"] = {
            "value": jobs.insights_top_departments,
            "source": jobs.insights_top_departments_source,
        }
    if jobs.insights_hiring_focus:
        insights["hiring_focus"] = {
            "value": jobs.insights_hiring_focus,
            "source": jobs.insights_hiring_focus_source,
        }
    if jobs.insights_growth_indicators:
        insights["growth_indicators"] = {
            "value": jobs.insights_growth_indicators,
            "source": jobs.insights_growth_indicators_source,
        }

    if insights:
        result["insights"] = insights

    # Get job offers
    offers = db.query(CompanyJobOffer).filter(CompanyJobOffer.company_id == company_id).all()

    if offers:
        offers_list = []
        for o in offers:
            offer_dict = {}
            if o.title:
                offer_dict["title"] = {"value": o.title, "source": o.title_source}
            if o.location:
                offer_dict["location"] = {"value": o.location, "source": o.location_source}
            if o.department:
                offer_dict["department"] = {"value": o.department, "source": o.department_source}
            if o.description:
                offer_dict["description"] = {"value": o.description, "source": o.description_source}
            if o.requirements:
                offer_dict["requirements"] = {"value": o.requirements, "source": o.requirements_source}
            if o.posted_date:
                offer_dict["posted_date"] = {"value": o.posted_date, "source": o.posted_date_source}
            offers_list.append(offer_dict)
        result["offers"] = offers_list

    return result


# =============================================================================
# CSR DATA - WRITER AND READER
# =============================================================================


def save_csr_data(db: Session, company_id: int, data: dict) -> None:
    """Save CSR data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "csr")
    """
    # Data comes at root level from agent, not nested under "csr"
    csr_data = data
    if not csr_data:
        logger.debug(f"No CSR data to save for company {company_id}")
        return

    # Check for existing CSR record
    existing = db.query(CompanyCsr).filter(CompanyCsr.company_id == company_id).first()

    if existing:
        csr = existing
    else:
        csr = CompanyCsr(company_id=company_id)
        db.add(csr)

    # Insights
    csr.insights = _get_string_value(csr_data, "insights")
    csr.insights_source = "Chaps-e"

    # Responsibility
    csr.responsibility, csr.responsibility_source = _get_sourced_value(csr_data, "responsibility")

    db.flush()

    # Save CSR initiatives (1:N) - from unified items array
    try:
        with db.begin_nested():
            _save_csr_initiatives(db, company_id, csr_data)
    except SQLAlchemyError:
        logger.error(f"Failed to save csr_initiatives for company {company_id}", exc_info=True)

    logger.info(f"Saved CSR data for company {company_id}")


def _save_csr_initiatives(db: Session, company_id: int, csr_data: dict) -> None:
    """Save CSR initiatives to normalized table."""
    # Delete existing initiatives
    db.query(CompanyCsrInitiative).filter(CompanyCsrInitiative.company_id == company_id).delete()

    # Map of type strings to enum values
    type_mapping = {
        "responsibility": CsrInitiativeType.responsibility,
        "charity": CsrInitiativeType.charity,
        "sustainability": CsrInitiativeType.sustainability,
        "community": CsrInitiativeType.community,
        "diversity": CsrInitiativeType.diversity,
        "ethics": CsrInitiativeType.ethics,
        "awards": CsrInitiativeType.awards,
    }

    # Read from unified items array with type field (key matches CsrAgentOutput.items)
    initiatives = csr_data.get("items", [])
    if not isinstance(initiatives, list):
        return

    for item in initiatives:
        if not isinstance(item, dict):
            continue

        # Get type and map to enum
        type_str = item.get("type", "")
        init_type = type_mapping.get(type_str)
        if not init_type:
            logger.warning(f"Unknown CSR initiative type: {type_str}")
            continue

        value = item.get("value")
        source = item.get("source")

        if value:
            db.add(
                CompanyCsrInitiative(
                    company_id=company_id,
                    type=init_type,
                    value=value,
                    value_source=source,
                    title=item.get("title"),
                    date=_parse_date(item.get("date")),
                )
            )

    db.flush()


def get_csr_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read CSR data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.csr interface
    """
    csr = db.query(CompanyCsr).filter(CompanyCsr.company_id == company_id).first()

    if not csr:
        return {}

    result = {}

    if csr.insights:
        result["insights"] = csr.insights

    if csr.responsibility:
        result["responsibility"] = {
            "value": csr.responsibility,
            "source": csr.responsibility_source,
        }

    # Get CSR initiatives
    initiatives = db.query(CompanyCsrInitiative).filter(CompanyCsrInitiative.company_id == company_id).all()

    # Group by type
    type_to_field = {
        CsrInitiativeType.responsibility: "responsibility_initiatives",
        CsrInitiativeType.charity: "charity_actions",
        CsrInitiativeType.sustainability: "sustainability_programs",
        CsrInitiativeType.community: "community_involvement",
        CsrInitiativeType.diversity: "diversity_inclusion",
        CsrInitiativeType.ethics: "ethical_practices",
        CsrInitiativeType.awards: "awards_certifications",
    }

    for init in initiatives:
        field_name = type_to_field.get(init.type)
        if field_name:
            if field_name not in result:
                result[field_name] = []
            result[field_name].append(
                {
                    "value": init.value,
                    "source": init.value_source,
                    "title": init.title,
                    "date": init.date.isoformat() if init.date else None,
                }
            )

    return result


# =============================================================================
# PRESS DATA - WRITER AND READER
# =============================================================================


def save_press_data(db: Session, company_id: int, data: dict) -> None:
    """Save press data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data (at root level, not nested under "press")
    """
    # Data comes at root level from agent, not nested under "press"
    press_data = data
    if not press_data:
        logger.debug(f"No press data to save for company {company_id}")
        return

    # Validate data is a dict
    if not isinstance(press_data, dict):
        logger.warning(f"Press data for company {company_id} is not a dict: {type(press_data)}")
        return

    # Check for existing press record
    existing = db.query(CompanyPress).filter(CompanyPress.company_id == company_id).first()

    if existing:
        press = existing
    else:
        press = CompanyPress(company_id=company_id)
        db.add(press)

    # Insights
    press.insights = _get_string_value(press_data, "insights")
    press.insights_source = "Chaps-e"

    db.flush()

    # Save press items (1:N)
    try:
        with db.begin_nested():
            _save_press_items(db, company_id, press_data)
    except SQLAlchemyError:
        logger.error(f"Failed to save press_items for company {company_id}", exc_info=True)

    logger.info(f"Saved press data for company {company_id}")


def _save_press_items(db: Session, company_id: int, press_data: dict) -> None:
    """Save press items to normalized table.

    Supports two formats:
    1. Unified items list: {"items": [{"type": "article", "value": "...", "source": "..."}]}
    2. Legacy type-named keys: {"articles": [...], "press_releases": [...]}
    """
    # Delete existing items
    db.query(CompanyPressItem).filter(CompanyPressItem.company_id == company_id).delete()

    # Type string to enum mapping
    type_str_mapping = {t.value: t for t in PressItemType}

    # Try unified items list first (new schema format)
    items = press_data.get("items", [])
    if isinstance(items, list) and items:
        for item in items:
            if not isinstance(item, dict):
                continue

            type_str = item.get("type", "")
            item_type = type_str_mapping.get(type_str)
            if not item_type:
                logger.warning(f"Unknown press item type: {type_str}")
                continue

            value = item.get("value")
            source = item.get("source")

            if value:
                db.add(
                    CompanyPressItem(
                        company_id=company_id,
                        type=item_type,
                        value=value,
                        value_source=source,
                        title=item.get("title"),
                        date=_parse_date(item.get("date")),
                    )
                )
    else:
        # Fallback: legacy type-named keys
        legacy_type_mapping = {
            "articles": PressItemType.article,
            "press_releases": PressItemType.press_release,
            "media_mentions": PressItemType.media_mention,
            "awards_recognition": PressItemType.award,
            "product_launches": PressItemType.product_launch,
            "executive_interviews": PressItemType.interview,
            "financial_news": PressItemType.financial,
            "partnership_announcements": PressItemType.partnership,
        }

        for field_name, item_type in legacy_type_mapping.items():
            legacy_items = press_data.get(field_name, [])
            if not isinstance(legacy_items, list):
                continue

            for item in legacy_items:
                if isinstance(item, dict):
                    value = item.get("value")
                    source = item.get("source")
                else:
                    value = item
                    source = None

                if value:
                    db.add(
                        CompanyPressItem(
                            company_id=company_id,
                            type=item_type,
                            value=value,
                            value_source=source,
                        )
                    )

    db.flush()


def get_press_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read press data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.press interface
    """
    press = db.query(CompanyPress).filter(CompanyPress.company_id == company_id).first()

    if not press:
        return {}

    result = {}

    if press.insights:
        result["insights"] = press.insights

    # Get press items
    items = db.query(CompanyPressItem).filter(CompanyPressItem.company_id == company_id).all()

    # Group by type
    type_to_field = {
        PressItemType.article: "articles",
        PressItemType.press_release: "press_releases",
        PressItemType.media_mention: "media_mentions",
        PressItemType.award: "awards_recognition",
        PressItemType.product_launch: "product_launches",
        PressItemType.interview: "executive_interviews",
        PressItemType.financial: "financial_news",
        PressItemType.partnership: "partnership_announcements",
    }

    for item in items:
        field_name = type_to_field.get(item.type)
        if field_name:
            if field_name not in result:
                result[field_name] = []
            result[field_name].append(
                {
                    "value": item.value,
                    "source": item.value_source,
                    "title": item.title,
                    "date": item.date.isoformat() if item.date else None,
                }
            )

    return result


# =============================================================================
# TEAM DATA - WRITER AND READER
# =============================================================================


def save_team_data(db: Session, company_id: int, data: dict) -> None:
    """Save team data from agent callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: agent output data containing team section
    """
    # Check for team data in multiple formats:
    # 1. Direct "team" key: {"team": [...]}
    # 2. teamAnalysis structure: {"teamAnalysis": {"team": [...]}}
    members = []

    if "team" in data:
        team_data = data.get("team", [])
        if isinstance(team_data, list):
            members = team_data
        elif isinstance(team_data, dict):
            members = team_data.get("members", []) or team_data.get("team", [])

    if not members and "teamAnalysis" in data:
        team_analysis = data.get("teamAnalysis", {})
        if isinstance(team_analysis, dict):
            members = team_analysis.get("team", [])

    if not members:
        logger.debug(f"No team data to save for company {company_id}")
        return

    # Delete existing team members
    db.query(CompanyTeamMember).filter(CompanyTeamMember.company_id == company_id).delete()

    if not isinstance(members, list):
        members = []

    # Save team members recursively (handling hierarchy)
    _save_team_members_recursive(db, company_id, members, parent_id=None)

    logger.info(f"Saved team data for company {company_id}")


def _save_team_members_recursive(
    db: Session,
    company_id: int,
    members: list[dict],
    parent_id: int | None,
) -> None:
    """Recursively save team members with hierarchy."""
    for member_data in members:
        if not isinstance(member_data, dict):
            continue

        # Extract member fields using snake_case keys (agent output format)
        position, position_source = _get_sourced_value(member_data, "position")
        first_name, first_name_source = _get_sourced_value(member_data, "first_name")
        last_name, last_name_source = _get_sourced_value(member_data, "last_name")
        linkedin, linkedin_source = _get_sourced_value(member_data, "linkedin_url")

        # Handle direct string values (not sourced) - fallback
        if not position:
            position = member_data.get("position")
        if not first_name:
            first_name = member_data.get("first_name")
        if not last_name:
            last_name = member_data.get("last_name")
        if not linkedin:
            linkedin = member_data.get("linkedin_url")

        member = CompanyTeamMember(
            company_id=company_id,
            parent_id=parent_id,
            position=position,
            position_source=position_source,
            first_name=first_name,
            first_name_source=first_name_source,
            last_name=last_name,
            last_name_source=last_name_source,
            linkedin_url=linkedin,
            linkedin_url_source=linkedin_source,
        )
        db.add(member)
        db.flush()  # Get the member's ID

        # Recursively save subordinates
        subordinates = member_data.get("subordinates", [])
        if subordinates:
            _save_team_members_recursive(db, company_id, subordinates, member.id)


def get_team_data(db: Session, company_id: int) -> list[dict[str, Any]]:
    """Read team data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        List of team members (root-level members with subordinates) matching
        frontend Company.team interface
    """
    # Get all team members for this company
    all_members = db.query(CompanyTeamMember).filter(CompanyTeamMember.company_id == company_id).all()

    if not all_members:
        return []

    # Build the response tree (root members have parent_id = None)
    root_members = [m for m in all_members if m.parent_id is None]

    def build_member_dict(member: CompanyTeamMember) -> dict[str, Any]:
        """Build team member dict recursively."""
        # Find subordinates
        subordinates = [m for m in all_members if m.parent_id == member.id]

        result = {}
        if member.position:
            result["position"] = member.position
        if member.first_name:
            result["firstName"] = member.first_name
        if member.last_name:
            result["lastName"] = member.last_name
        if member.linkedin_url:
            result["linkedinUrl"] = member.linkedin_url
        if subordinates:
            result["subordinates"] = [build_member_dict(s) for s in subordinates]

        return result

    return [build_member_dict(m) for m in root_members]


# =============================================================================
# CORPORATE STRUCTURE SECTION
# =============================================================================

# Valid relationship type strings for mapping
_CORPORATE_TYPE_MAP: dict[str, CorporateRelationshipType] = {
    "parent": CorporateRelationshipType.parent,
    "subsidiary": CorporateRelationshipType.subsidiary,
    "affiliate": CorporateRelationshipType.affiliate,
    "branch": CorporateRelationshipType.branch,
    "regional_entity": CorporateRelationshipType.regional_entity,
}


def save_corporate_structure_data(db: Session, company_id: int, data: dict) -> None:
    """Save corporate structure entities from agent output.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Agent output with 'entities' list
    """
    # Clear existing data
    db.query(CompanyCorporateEntity).filter(CompanyCorporateEntity.company_id == company_id).delete()

    entities = data.get("entities", [])
    if not isinstance(entities, list):
        entities = []

    for entity_data in entities:
        if not isinstance(entity_data, dict):
            continue

        # Extract name (required)
        name, name_source = _get_sourced_value(entity_data, "name")
        if not name:
            continue

        # Extract country (optional)
        country, country_source = _get_sourced_value(entity_data, "country")

        # Map relationship type
        entity_type_str = entity_data.get("type", "")
        entity_type = _CORPORATE_TYPE_MAP.get(entity_type_str)
        if not entity_type:
            continue

        db.add(
            CompanyCorporateEntity(
                company_id=company_id,
                type=entity_type,
                name=name,
                name_source=name_source,
                country=country,
                country_source=country_source,
                source=entity_data.get("source", "worldcheck"),
                wc_reference_id=entity_data.get("wc_reference_id"),
                match_strength=entity_data.get("match_strength"),
            )
        )

    db.flush()


def get_corporate_structure_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read corporate structure entities grouped by relationship type.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary with keys: parents, subsidiaries, affiliates, branches, regional_entities
    """
    entities = db.query(CompanyCorporateEntity).filter(CompanyCorporateEntity.company_id == company_id).all()

    result: dict[str, list[dict[str, Any]]] = {
        "parents": [],
        "subsidiaries": [],
        "affiliates": [],
        "branches": [],
        "regional_entities": [],
    }

    # Map enum values to result keys
    type_to_key = {
        CorporateRelationshipType.parent: "parents",
        CorporateRelationshipType.subsidiary: "subsidiaries",
        CorporateRelationshipType.affiliate: "affiliates",
        CorporateRelationshipType.branch: "branches",
        CorporateRelationshipType.regional_entity: "regional_entities",
    }

    for entity in entities:
        key = type_to_key.get(entity.type)
        if not key:
            continue

        entity_dict: dict[str, Any] = {"name": entity.name}
        if entity.country:
            entity_dict["country"] = entity.country
        if entity.name_source:
            entity_dict["source"] = entity.name_source

        result[key].append(entity_dict)

    return result


# =============================================================================
# FINANCIAL DATA - WRITER AND READER
# =============================================================================

# Keyword mapping for normalising LLM-generated metric names to canonical keys
# used by the frontend METRIC_MAP in FinancialHistory.vue.
# Each entry: (list of substrings to match, canonical key)
_METRIC_NAME_KEYWORDS: list[tuple[list[str], str]] = [
    (["revenue", "turnover", "chiffre d'affaires", "cifra de negocio", "umsatz"], "revenue"),
    (["ebitda"], "ebitda"),
    (["net income", "net profit", "résultat net", "bénéfice net", "net earnings", "résultat après"], "netIncome"),
    (["free cash flow", "fcf", "cash flow libre", "free cashflow"], "freeCashFlow"),
]


def _normalize_metric_name(raw: str) -> tuple[str, str | None]:
    """Normalise a LLM-generated metric name to a canonical frontend key.

    Returns (normalized_name, description) where description is the original
    raw name when normalisation actually renamed it (so the original label is
    not lost), or None when no renaming occurred.
    """
    lower = raw.lower()
    for keywords, canonical in _METRIC_NAME_KEYWORDS:
        if any(kw in lower for kw in keywords):
            desc = raw if raw != canonical else None
            return canonical, desc
    return raw, None


# Map: camelCase key in agent data -> (snake_case value column, snake_case source column)
_FINANCIAL_SOURCED_FIELDS: dict[str, tuple[str, str]] = {
    "companyType": ("company_type", "company_type_source"),
    "tickerSymbol": ("ticker_symbol", "ticker_symbol_source"),
    "stockExchange": ("stock_exchange", "stock_exchange_source"),
    "currency": ("currency", "currency_source"),
    "fiscalYearEnd": ("fiscal_year_end", "fiscal_year_end_source"),
    "revenue": ("revenue", "revenue_source"),
    "revenueGrowth": ("revenue_growth", "revenue_growth_source"),
    "grossMargin": ("gross_margin", "gross_margin_source"),
    "ebitdaMargin": ("ebitda_margin", "ebitda_margin_source"),
    "netMargin": ("net_margin", "net_margin_source"),
    "marketCap": ("market_cap", "market_cap_source"),
    "enterpriseValue": ("enterprise_value", "enterprise_value_source"),
    "peRatio": ("pe_ratio", "pe_ratio_source"),
    "evEbitda": ("ev_ebitda", "ev_ebitda_source"),
    "evRevenue": ("ev_revenue", "ev_revenue_source"),
    "employeeCount": ("employee_count", "employee_count_source"),
    "totalFunding": ("total_funding", "total_funding_source"),
    "lastValuation": ("last_valuation", "last_valuation_source"),
    "debtToEquity": ("debt_to_equity", "debt_to_equity_source"),
    "freeCashFlow": ("free_cash_flow", "free_cash_flow_source"),
}


def save_financial_data(db: Session, company_id: int, data: dict) -> None:
    """Save financial data from agent callback to normalized tables."""
    # Get or create
    existing = db.query(CompanyFinancial).filter(CompanyFinancial.company_id == company_id).first()
    if existing:
        financial = existing
    else:
        financial = CompanyFinancial(company_id=company_id)
        db.add(financial)

    # Extract insights
    financial.insights = _get_string_value(data, "insights")
    financial.insights_source = "Chaps-e"

    # Extract 20 SourcedValue pairs (using _get_sourced_value helper)
    # Also encode optional `context` into the source column via || delimiter
    for camel_key, (value_col, source_col) in _FINANCIAL_SOURCED_FIELDS.items():
        value, source = _get_sourced_value(data, camel_key)
        field_data = data.get(camel_key)
        context = field_data.get("context") if isinstance(field_data, dict) else None
        encoded_source = f"{source}||{context}" if source and context else source
        setattr(financial, value_col, value)
        setattr(financial, source_col, encoded_source)

    db.flush()

    # Save 1:N children: metrics
    _save_financial_metrics(db, company_id, data.get("metrics") or [])
    # Save 1:N children: funding rounds
    _save_funding_rounds(db, company_id, data.get("fundingRounds") or [])

    logger.info("Saved financial data for company %s", company_id)


def _parse_iso_date(value: str | None) -> _Date | None:
    """Parse an ISO date string from the LLM, returning None on malformed input."""
    if not value:
        return None
    try:
        return _Date.fromisoformat(value)
    except (ValueError, TypeError):
        logger.warning("LLM returned unparseable date %r — storing null", value)
        return None


def _save_financial_metrics(db: Session, company_id: int, metrics: list[dict]) -> None:
    """Save financial metrics to normalized table (full replace)."""
    db.query(CompanyFinancialMetric).filter(CompanyFinancialMetric.company_id == company_id).delete()

    for metric_data in metrics:
        raw_name = metric_data.get("metricName") or metric_data.get("metric_name")
        if not raw_name:
            logger.warning("Metric without name skipped for company %s", company_id)
            continue

        # Normalise the metric name to a canonical key where possible so the
        # frontend METRIC_MAP in FinancialHistory.vue can route it correctly.
        # Preserve the original name as description when renaming occurs.
        metric_name, normalized_desc = _normalize_metric_name(raw_name)
        # Prefer explicit LLM context; fall back to the normalisation note
        context = metric_data.get("context") or normalized_desc

        metric = CompanyFinancialMetric(
            company_id=company_id,
            metric_name=metric_name,
            period=metric_data.get("period"),
            period_normalized=_parse_iso_date(metric_data.get("periodNormalized")),
            value=metric_data.get("value"),
            unit=metric_data.get("unit"),
            source=metric_data.get("source"),
            context=context,
        )
        db.add(metric)

    db.flush()


def _save_funding_rounds(db: Session, company_id: int, rounds: list[dict]) -> None:
    """Save funding rounds to normalized table (full replace)."""
    db.query(CompanyFundingRound).filter(CompanyFundingRound.company_id == company_id).delete()

    for round_data in rounds:
        funding_round = CompanyFundingRound(
            company_id=company_id,
            round_type=round_data.get("roundType") or round_data.get("round_type"),
            amount=round_data.get("amount"),
            date=round_data.get("date"),
            date_normalized=_parse_iso_date(round_data.get("dateNormalized")),
            lead_investor=round_data.get("leadInvestor") or round_data.get("lead_investor"),
            valuation=round_data.get("valuation"),
            source=round_data.get("source"),
        )
        db.add(funding_round)

    db.flush()


def get_financial_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read financial data from normalized tables for API response."""
    financial = db.query(CompanyFinancial).filter(CompanyFinancial.company_id == company_id).first()

    if not financial:
        return {}

    result: dict[str, Any] = {}

    # Add insights
    if financial.insights:
        result["insights"] = {
            "value": financial.insights,
            "source": financial.insights_source or "Chaps-e",
        }

    # Add SourcedValue fields — decode context encoded via || delimiter in source column
    for camel_key, (value_attr, source_attr) in _FINANCIAL_SOURCED_FIELDS.items():
        value = getattr(financial, value_attr)
        if value is not None:
            raw_source = getattr(financial, source_attr)
            if raw_source and "||" in raw_source:
                source_url, context = raw_source.split("||", 1)
            else:
                source_url, context = raw_source, None
            entry: dict[str, Any] = {"value": value, "source": source_url}
            if context:
                entry["context"] = context
            result[camel_key] = entry

    # Add 1:N: financial metrics — ordered chronologically, nulls last
    metrics = (
        db.query(CompanyFinancialMetric)
        .filter(CompanyFinancialMetric.company_id == company_id)
        .order_by(CompanyFinancialMetric.period_normalized.asc().nulls_last())
        .all()
    )
    if metrics:
        result["metrics"] = [
            {
                "metricName": m.metric_name,
                "period": m.period,
                "periodNormalized": m.period_normalized.isoformat() if m.period_normalized else None,
                "value": m.value,
                "unit": m.unit,
                "source": m.source,
                "context": m.context,
            }
            for m in metrics
        ]

    # Add 1:N: funding rounds — ordered chronologically, nulls last
    rounds = (
        db.query(CompanyFundingRound)
        .filter(CompanyFundingRound.company_id == company_id)
        .order_by(CompanyFundingRound.date_normalized.asc().nulls_last())
        .all()
    )
    if rounds:
        result["fundingRounds"] = [
            {
                "roundType": r.round_type,
                "amount": r.amount,
                "date": r.date,
                "dateNormalized": r.date_normalized.isoformat() if r.date_normalized else None,
                "leadInvestor": r.lead_investor,
                "valuation": r.valuation,
                "source": r.source,
            }
            for r in rounds
        ]

    return result


# =============================================================================
# SANCTIONS DATA - WRITER AND READER
# =============================================================================

# Map string values to SanctionType enum
_SANCTION_TYPE_MAP: dict[str, SanctionType] = {e.value: e for e in SanctionType}

# Map string values to RiskLevel enum
_RISK_LEVEL_MAP: dict[str, RiskLevel] = {e.value: e for e in RiskLevel}


def save_sanctions_data(db: Session, company_id: int, data: dict) -> None:
    """Save sanctions data from agent output to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Agent output with overall risk, insights, and items list
    """
    # Clear existing sanctions data
    db.query(CompanySanctionItem).filter(CompanySanctionItem.company_id == company_id).delete()
    db.query(CompanySanctions).filter(CompanySanctions.company_id == company_id).delete()

    # Save 1:1 sanctions summary
    items = data.get("items", [])
    if not isinstance(items, list):
        items = []

    sanctions = CompanySanctions(
        company_id=company_id,
        insights=_get_string_value(data, "insights"),
        insights_source="worldcheck",
        overall_risk_level=_get_string_value(data, "overall_risk_level"),
        overall_risk_justification=_get_string_value(data, "overall_risk_justification"),
        total_sanctions_count=len(items),
    )
    db.add(sanctions)

    # Save 1:N sanction items
    for item_data in items:
        if not isinstance(item_data, dict):
            continue

        entity_name = item_data.get("entity_name")
        if not entity_name:
            continue

        # Map sanction_type string to enum
        sanction_type_str = item_data.get("sanction_type", "")
        sanction_type = _SANCTION_TYPE_MAP.get(sanction_type_str)

        # Map risk_level string to enum
        risk_level_str = item_data.get("risk_level", "")
        risk_level = _RISK_LEVEL_MAP.get(risk_level_str)

        # Extract weblinks - preserve full objects as JSON strings in the TEXT[] array
        raw_weblinks = item_data.get("weblinks")
        weblinks = None
        if isinstance(raw_weblinks, list):
            extracted = []
            for w in raw_weblinks:
                if isinstance(w, str):
                    extracted.append(w)
                elif isinstance(w, dict) and w.get("uri"):
                    extracted.append(json.dumps(w))
            weblinks = extracted if extracted else None

        db.add(
            CompanySanctionItem(
                company_id=company_id,
                entity_name=entity_name,
                country=item_data.get("country"),
                sanction_nature=item_data.get("sanction_nature"),
                description=item_data.get("description"),
                source_code=item_data.get("source_code"),
                sanction_type=sanction_type,
                date=item_data.get("date"),
                weblinks=weblinks,
                is_onu_eu_ofac=bool(item_data.get("is_onu_eu_ofac", False)),
                risk_level=risk_level,
                risk_justification=item_data.get("risk_justification"),
            )
        )

    db.flush()
    logger.info(f"Saved sanctions data for company {company_id} ({len(items)} items)")


def get_sanctions_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read sanctions data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary with sanctions summary and items list
    """
    sanctions = db.query(CompanySanctions).filter(CompanySanctions.company_id == company_id).first()

    items = db.query(CompanySanctionItem).filter(CompanySanctionItem.company_id == company_id).all()

    if not sanctions and not items:
        return {}

    result: dict[str, Any] = {}

    if sanctions:
        if sanctions.insights:
            result["insights"] = sanctions.insights
        if sanctions.overall_risk_level:
            result["overall_risk_level"] = sanctions.overall_risk_level
        if sanctions.overall_risk_justification:
            result["overall_risk_justification"] = sanctions.overall_risk_justification
        result["total_sanctions_count"] = sanctions.total_sanctions_count or 0

    if items:
        items_list = []
        for item in items:
            item_dict: dict[str, Any] = {
                "entity_name": item.entity_name,
            }
            if item.country:
                item_dict["country"] = item.country
            if item.sanction_nature:
                item_dict["sanction_nature"] = item.sanction_nature
            if item.description:
                item_dict["description"] = item.description
            if item.source_code:
                item_dict["source_code"] = item.source_code
            if item.sanction_type:
                item_dict["sanction_type"] = item.sanction_type.value
            if item.date:
                item_dict["date"] = item.date
            if item.weblinks:
                # Parse JSON strings back to objects, keep plain URIs as-is
                parsed_weblinks = []
                for w in item.weblinks:
                    try:
                        parsed = json.loads(w)
                        if isinstance(parsed, dict):
                            # Only include non-null fields
                            cleaned = {k: v for k, v in parsed.items() if v is not None}
                            parsed_weblinks.append(cleaned)
                        else:
                            parsed_weblinks.append({"uri": w})
                    except (json.JSONDecodeError, TypeError):
                        parsed_weblinks.append({"uri": w})
                item_dict["weblinks"] = parsed_weblinks
            item_dict["is_onu_eu_ofac"] = item.is_onu_eu_ofac
            if item.risk_level:
                item_dict["risk_level"] = item.risk_level.value
            if item.risk_justification:
                item_dict["risk_justification"] = item.risk_justification
            items_list.append(item_dict)
        result["items"] = items_list
    else:
        result["items"] = []

    return result


# =============================================================================
# PATENTS DATA - WRITER AND READER
# =============================================================================


def save_patents_data(db: Session, company_id: int, data: dict) -> None:
    """Persist the patents section + item rows produced by the patents agent.

    The section row (``CompanyPatents``) is upserted; item rows
    (``CompanyPatentItem``) are fully replaced (delete-then-insert),
    consistent with the ``_save_financial_metrics`` pattern. Both
    operations run inside the caller's transaction — no commit here.

    Args:
        db: Database session.
        company_id: Target company.
        data: Agent output matching the shape emitted by
            ``run_patents_agent`` (insights, total_patents_count,
            top_cpc_domains, filing_trend, patents[]).
    """
    existing = db.query(CompanyPatents).filter(CompanyPatents.company_id == company_id).first()
    if existing is None:
        section = CompanyPatents(company_id=company_id)
        db.add(section)
    else:
        section = existing

    section.insights = _get_string_value(data, "insights")
    section.total_patents_count = int(data.get("total_patents_count") or 0)
    section.top_cpc_domains = data.get("top_cpc_domains") or []
    section.filing_trend = data.get("filing_trend") or {}

    db.flush()

    _save_patent_items(db, company_id, data.get("patents") or [])

    logger.info("Saved patents data for company %s", company_id)


def _save_patent_items(db: Session, company_id: int, items: list[dict]) -> None:
    """Replace the company's patent items with the supplied list."""
    db.query(CompanyPatentItem).filter(CompanyPatentItem.company_id == company_id).delete()

    seen: set[str] = set()
    for item in items:
        patent_number = item.get("patent_number") or item.get("doc_id")
        if not patent_number or patent_number in seen:
            # The unique constraint enforces this at the DB level; skipping
            # here keeps the flush from tripping on an accidental duplicate
            # in agent output.
            continue
        seen.add(patent_number)

        db.add(
            CompanyPatentItem(
                company_id=company_id,
                patent_number=patent_number,
                title=item.get("title"),
                abstract=item.get("abstract"),
                inventors=list(item.get("inventors") or []),
                applicants=list(item.get("applicants") or []),
                publication_date=_parse_patent_publication_date(item.get("publication_date")),
                cpc_codes=list(item.get("cpc_codes") or []),
                is_key_patent=bool(item.get("is_key_patent")),
            )
        )

    db.flush()


def _parse_patent_publication_date(raw: Any) -> Any:
    """Accept ISO strings ("YYYY-MM-DD") and pass-through ``date`` objects."""
    from datetime import date, datetime

    if raw is None or isinstance(raw, date):
        return raw
    if isinstance(raw, str) and raw:
        try:
            return datetime.strptime(raw, "%Y-%m-%d").date()
        except ValueError:
            return None
    return None


def get_patents_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read back the patents section + items as a single dict.

    Returns an empty dict when no section row exists. Items are ordered
    by ``publication_date`` descending with key patents floated to the
    top of ties so the frontend can render them prominently without
    extra sorting.
    """
    section = db.query(CompanyPatents).filter(CompanyPatents.company_id == company_id).first()
    if section is None:
        return {}

    items = (
        db.query(CompanyPatentItem)
        .filter(CompanyPatentItem.company_id == company_id)
        .order_by(
            CompanyPatentItem.is_key_patent.desc(),
            CompanyPatentItem.publication_date.desc().nullslast(),
        )
        .all()
    )

    return {
        "insights": section.insights or "",
        "total_patents_count": section.total_patents_count or 0,
        "top_cpc_domains": section.top_cpc_domains or [],
        "filing_trend": section.filing_trend or {},
        "patents": [_serialize_patent_item(item) for item in items],
    }


def _serialize_patent_item(item: CompanyPatentItem) -> dict[str, Any]:
    """Convert a ``CompanyPatentItem`` row into its JSON-ready dict."""
    return {
        "patent_number": item.patent_number,
        "title": item.title,
        "abstract": item.abstract,
        "inventors": item.inventors or [],
        "applicants": item.applicants or [],
        "publication_date": item.publication_date.isoformat() if item.publication_date else None,
        "cpc_codes": item.cpc_codes or [],
        "is_key_patent": bool(item.is_key_patent),
    }


# =============================================================================
# MAIN DISPATCHER FUNCTION
# =============================================================================


def write_section_data(
    db: Session,
    company_id: int,
    query_type: str,
    data: dict[str, Any],
) -> None:
    """Write company section data to normalized tables.

    This function dispatches to the appropriate writer based on query_type.
    Called by CompanyService._update_company_data().

    Args:
        db: Database session
        company_id: Company ID to save data for
        query_type: Type of query (profile, digital, timeline, etc.)
        data: agent output data containing the section
    """
    # Map query types to writer functions
    writers = {
        "profile": save_profile_data,
        "digital": save_digital_data,
        "timeline": save_timeline_data,
        "products": save_products_data,
        "jobs": save_jobs_data,
        "csr": save_csr_data,
        "press": save_press_data,
        "financial": save_financial_data,
        "team": save_team_data,
        "corporate_structure": save_corporate_structure_data,
        "sanctions": save_sanctions_data,
        "patents": save_patents_data,
    }

    # Log child list sizes for diagnostics
    child_list_keys = [k for k, v in data.items() if isinstance(v, list)]
    logger.info(
        f"write_section_data: {query_type} for company {company_id}",
        extra={"data_keys": list(data.keys()), "list_fields": {k: len(data[k]) for k in child_list_keys}},
    )

    writer = writers.get(query_type)
    if writer:
        writer(db, company_id, data)
    else:
        logger.warning(f"No writer found for query_type: {query_type}")


def read_all_section_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read all company section data from normalized tables.

    Returns a dictionary with all section data for building CompanyResponse.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary with keys: profile, digital, timeline, products, jobs, csr, press, team
    """
    return {
        "profile": get_profile_data(db, company_id),
        "digital": get_digital_data(db, company_id),
        "timeline": get_timeline_data(db, company_id),
        "products": get_products_data(db, company_id),
        "jobs": get_jobs_data(db, company_id),
        "csr": get_csr_data(db, company_id),
        "press": get_press_data(db, company_id),
        "financial": get_financial_data(db, company_id),
        "team": get_team_data(db, company_id),
        "corporate_structure": get_corporate_structure_data(db, company_id),
        "sanctions": get_sanctions_data(db, company_id),
    }


# =============================================================================
# TRANSLATION APPLICATION
# =============================================================================
# Field mapping from table/field to section path in API response
# Format: (table_name, field_name) -> (section_key, response_field_name, is_value_object)

FIELD_TO_RESPONSE_MAP: dict[tuple[str, str], tuple[str, str, bool]] = {
    # Profile section - company_id as record_id
    ("company_profile", "insights"): ("profile", "insights", False),
    ("company_profile", "business_line"): ("profile", "businessLine", True),
    ("company_profile", "catchphrase"): ("profile", "catchphrase", True),
    # Digital section - company_id as record_id
    ("company_digital", "insights"): ("digital", "insights", False),
    ("company_digital", "overall_strategy"): ("digital", "overallStrategy", True),
    ("company_digital", "digital_transformation"): ("digital", "digitalTransformation", True),
    ("company_digital", "ecommerce_capabilities"): ("digital", "ecommerceCapabilities", True),
    ("company_digital", "mobile_strategy"): ("digital", "mobileStrategy", True),
    ("company_digital", "digital_marketing_approach"): ("digital", "digitalMarketingApproach", True),
    ("company_digital", "loyalty_program"): ("digital", "loyaltyProgram", True),
    # Timeline section - company_id as record_id
    ("company_timeline", "insights"): ("timeline", "insights", False),
    # Products section - company_id as record_id
    ("company_products", "insights"): ("products", "insights", False),
    ("company_products", "customer_type"): ("products", "customerType", True),
    ("company_products", "marketing_positioning"): ("products", "marketingPositioning", True),
    # Jobs section insights - handled specially in apply_translations_to_section_data
    # because they are nested inside jobs.insights.{field}.value
    # CSR section - company_id as record_id
    ("company_csr", "insights"): ("csr", "insights", False),
    ("company_csr", "responsibility"): ("csr", "responsibility", True),
    # Press section - company_id as record_id
    ("company_press", "insights"): ("press", "insights", False),
}


def apply_translations_to_section_data(
    section_data: dict[str, Any],
    translations_map: dict[tuple[str, int, str], str],
    company_id: int,
) -> dict[str, Any]:
    """Apply translations to section data.

    Modifies section_data in place by replacing field values with translations
    where available.

    Args:
        section_data: Section data dictionary from read_all_section_data
        translations_map: Translation lookup map from TranslationService.get_translations_map
        company_id: Company ID (used as record_id for 1:1 tables)

    Returns:
        Modified section_data with translations applied
    """
    if not translations_map:
        return section_data

    # Apply translations for 1:1 section tables
    for (table_name, field_name), (section_key, response_field, is_value_obj) in FIELD_TO_RESPONSE_MAP.items():
        key = (table_name, company_id, field_name)
        if key not in translations_map:
            continue

        translated_value = translations_map[key]
        section = section_data.get(section_key, {})

        if not section:
            continue

        if is_value_obj:
            # Field is a value object like {"value": "...", "source": "..."}
            if response_field in section and isinstance(section[response_field], dict):
                section[response_field]["value"] = translated_value
        else:
            # Field is a simple string
            if response_field in section:
                section[response_field] = translated_value

    # Apply translations for 1:N child records
    # Timeline events
    if "timeline" in section_data and "events" in section_data["timeline"]:
        events = section_data["timeline"]["events"]
        if isinstance(events, list):
            for event in events:
                if isinstance(event, dict):
                    event_id = event.get("id")
                    if event_id:
                        for field in ["title", "description", "category", "impact"]:
                            key = ("company_timeline_events", event_id, field)
                            if key in translations_map:
                                event[field] = translations_map[key]

    # Online services (in digital section)
    # Structure: digital.onlineServices.value.services = [...]
    if "digital" in section_data and "onlineServices" in section_data["digital"]:
        online_services_obj = section_data["digital"]["onlineServices"]
        if isinstance(online_services_obj, dict) and "value" in online_services_obj:
            services_value = online_services_obj["value"]
            if isinstance(services_value, dict) and "services" in services_value:
                for service in services_value["services"]:
                    if isinstance(service, dict):
                        service_id = service.get("id")
                        if service_id:
                            for field in ["name", "description"]:
                                key = ("company_online_services", service_id, field)
                                if key in translations_map:
                                    service[field] = translations_map[key]

    # Job offers
    if "jobs" in section_data and "offers" in section_data["jobs"]:
        offers = section_data["jobs"]["offers"]
        if isinstance(offers, list):
            for offer in offers:
                if isinstance(offer, dict):
                    offer_id = offer.get("id")
                    if offer_id:
                        for field in ["title", "department", "description", "requirements"]:
                            key = ("company_job_offers", offer_id, field)
                            if key in translations_map:
                                offer[field] = translations_map[key]

    # Jobs insights (nested inside jobs.insights.{field}.value)
    if "jobs" in section_data and "insights" in section_data["jobs"]:
        jobs_insights = section_data["jobs"]["insights"]
        if isinstance(jobs_insights, dict):
            # Map database field names to JSON field names
            insights_field_map = {
                "insights_top_departments": "top_departments",
                "insights_hiring_focus": "hiring_focus",
                "insights_growth_indicators": "growth_indicators",
            }
            for db_field, json_field in insights_field_map.items():
                key = ("company_jobs", company_id, db_field)
                if key in translations_map and json_field in jobs_insights:
                    insight_obj = jobs_insights[json_field]
                    if isinstance(insight_obj, dict) and "value" in insight_obj:
                        insight_obj["value"] = translations_map[key]

    # Team members
    # Note: Team members structure doesn't currently include 'id' field
    # Translation for team members requires updating get_team_data to include id
    if "team" in section_data:
        for member in section_data.get("team", []):
            if isinstance(member, dict):
                member_id = member.get("id")
                if member_id:
                    key = ("company_team_members", member_id, "position")
                    if key in translations_map:
                        member["position"] = translations_map[key]

    # Product categories
    # Note: categories is currently {category_name: items_list} dict, not a list
    # Translation for categories requires updating the data structure
    if "products" in section_data and "categories" in section_data["products"]:
        categories = section_data["products"]["categories"]
        if isinstance(categories, list):
            for category in categories:
                if isinstance(category, dict):
                    category_id = category.get("id")
                    if category_id:
                        key = ("company_product_categories", category_id, "category_name")
                        if key in translations_map:
                            category["categoryName"] = translations_map[key]

    # CSR initiatives
    # Note: CSR structure uses keys like 'responsibility_initiatives', 'charity_actions', etc.
    # not 'environmental', 'social', 'governance'
    if "csr" in section_data:
        csr_keys = [
            "responsibility_initiatives",
            "charity_actions",
            "sustainability_programs",
            "community_involvement",
            "diversity_inclusion",
            "ethical_practices",
            "awards_certifications",
        ]
        for init_type in csr_keys:
            if init_type in section_data["csr"]:
                items = section_data["csr"][init_type]
                if isinstance(items, list):
                    for item in items:
                        if isinstance(item, dict):
                            item_id = item.get("id")
                            if item_id:
                                key = ("company_csr_initiatives", item_id, "value")
                                if key in translations_map:
                                    item["value"] = translations_map[key]

    # Press items
    # Note: Press structure uses keys like 'mentions', 'achievements', etc.
    if "press" in section_data:
        press_keys = ["mentions", "achievements", "partnerships", "innovations", "financial_news"]
        for press_type in press_keys:
            if press_type in section_data["press"]:
                items = section_data["press"][press_type]
                if isinstance(items, list):
                    for item in items:
                        if isinstance(item, dict):
                            item_id = item.get("id")
                            if item_id:
                                key = ("company_press_items", item_id, "value")
                                if key in translations_map:
                                    item["value"] = translations_map[key]

    return section_data
