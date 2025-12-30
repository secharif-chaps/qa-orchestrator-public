"""Company section service for normalized table data operations.

This module provides writer and reader functions for company section data
stored in normalized tables (1:1 sections and 1:N children).

Writers:
- save_profile_data, save_digital_data, save_timeline_data, etc.
- Called by _update_company_data to persist Dify callback data

Readers:
- get_profile_data, get_digital_data, get_timeline_data, etc.
- Called by _build_company_response to read data for API responses

All functions handle the transformation between Dify JSON format
and normalized database tables with SourcedValue pattern.
"""

import logging
from typing import Any

from sqlalchemy.orm import Session

from app.models.company_sections import (
    CompanyProfile,
    CompanyDigital,
    CompanyTimeline,
    CompanyProducts,
    CompanyJobs,
    CompanyCsr,
    CompanyPress,
)
from app.models.company_children import (
    CompanyOnlineService,
    CompanySocialMediaAccount,
    CompanyTimelineEvent,
    CompanyProductItem,
    CompanyProductCategory,
    CompanyJobOffer,
    CompanyCsrInitiative,
    CompanyPressItem,
    CompanyTeamMember,
    ProductItemType,
    CsrInitiativeType,
    PressItemType,
)


logger = logging.getLogger(__name__)


# =============================================================================
# HELPER FUNCTIONS
# =============================================================================


def _get_sourced_value(data: dict, key: str) -> tuple[str | None, str | None]:
    """Extract value and source from a SourcedValue structure in Dify data.

    Args:
        data: Dictionary containing the Dify data
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


def _get_string_value(data: dict, key: str) -> str | None:
    """Extract a plain string value from Dify data (for insights, etc.).

    Args:
        data: Dictionary containing the Dify data
        key: Key to extract

    Returns:
        String value or None
    """
    value = data.get(key)
    if isinstance(value, str):
        return value
    return None


# =============================================================================
# PROFILE DATA - WRITER AND READER
# =============================================================================


def save_profile_data(db: Session, company_id: int, data: dict) -> None:
    """Save profile data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing profile section
    """
    profile_data = data.get("profile", {})
    if not profile_data:
        logger.debug(f"No profile data to save for company {company_id}")
        return

    # Check for existing profile record
    existing = db.query(CompanyProfile).filter(
        CompanyProfile.company_id == company_id
    ).first()

    if existing:
        # Update existing record
        profile = existing
    else:
        # Create new record
        profile = CompanyProfile(company_id=company_id)
        db.add(profile)

    # Extract values from Dify data
    profile.insights = _get_string_value(profile_data, "insights")
    profile.insights_source = "Chaps-e"  # Insights are always from Chaps-e

    # SourcedValue fields
    profile.group_name, profile.group_name_source = _get_sourced_value(
        profile_data, "groupName"
    )
    profile.business_line, profile.business_line_source = _get_sourced_value(
        profile_data, "businessLine"
    )
    profile.catchphrase, profile.catchphrase_source = _get_sourced_value(
        profile_data, "catchphrase"
    )
    profile.establishment_year, profile.establishment_year_source = _get_sourced_value(
        profile_data, "establishmentYear"
    )
    profile.employee_count, profile.employee_count_source = _get_sourced_value(
        profile_data, "employeeCount"
    )
    profile.revenue, profile.revenue_source = _get_sourced_value(
        profile_data, "revenue"
    )
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
    profile = db.query(CompanyProfile).filter(
        CompanyProfile.company_id == company_id
    ).first()

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
    """Save digital data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing digital section
    """
    digital_data = data.get("digital", {})
    if not digital_data:
        logger.debug(f"No digital data to save for company {company_id}")
        return

    # Check for existing digital record
    existing = db.query(CompanyDigital).filter(
        CompanyDigital.company_id == company_id
    ).first()

    if existing:
        digital = existing
    else:
        digital = CompanyDigital(company_id=company_id)
        db.add(digital)

    # Insights
    digital.insights = _get_string_value(digital_data, "insights")
    digital.insights_source = "Chaps-e"

    # Digital strategy fields (nested under digitalStrategy)
    strategy = digital_data.get("digitalStrategy", {})
    if isinstance(strategy, dict):
        # Handle value wrapper if present
        if "value" in strategy:
            strategy = strategy.get("value", {})
        (
            digital.overall_strategy,
            digital.overall_strategy_source,
        ) = _get_sourced_value(strategy, "overallStrategy")
        (
            digital.digital_transformation,
            digital.digital_transformation_source,
        ) = _get_sourced_value(strategy, "digitalTransformation")
        (
            digital.ecommerce_capabilities,
            digital.ecommerce_capabilities_source,
        ) = _get_sourced_value(strategy, "eCommerceCapabilities")
        (
            digital.mobile_strategy,
            digital.mobile_strategy_source,
        ) = _get_sourced_value(strategy, "mobileStrategy")
        (
            digital.digital_marketing_approach,
            digital.digital_marketing_approach_source,
        ) = _get_sourced_value(strategy, "digitalMarketingApproach")

    # Loyalty program (top level)
    (
        digital.loyalty_program,
        digital.loyalty_program_source,
    ) = _get_sourced_value(digital_data, "loyaltyProgram")

    db.flush()

    # Save online services (1:N)
    online_services = digital_data.get("onlineServices", {})
    if isinstance(online_services, dict) and "value" in online_services:
        services_value = online_services.get("value", {})
        if isinstance(services_value, dict):
            _save_online_services(db, company_id, services_value.get("services", []))
        elif isinstance(services_value, list):
            _save_online_services(db, company_id, services_value)
    elif isinstance(online_services, list):
        _save_online_services(db, company_id, online_services)

    # Save social media accounts (1:N)
    _save_social_media_accounts(
        db, company_id, digital_data.get("socialMediaAccounts", [])
    )

    logger.info(f"Saved digital data for company {company_id}")


def _save_online_services(
    db: Session, company_id: int, services: list[dict]
) -> None:
    """Save online services to normalized table."""
    # Delete existing services
    db.query(CompanyOnlineService).filter(
        CompanyOnlineService.company_id == company_id
    ).delete()

    for service_data in services:
        if not isinstance(service_data, dict):
            continue

        # Handle both direct values and sourced values
        name = service_data.get("name")
        if isinstance(name, dict):
            name = name.get("value")
        name_source = service_data.get("name_source")

        desc = service_data.get("description")
        if isinstance(desc, dict):
            desc = desc.get("value")
        desc_source = service_data.get("description_source")

        service = CompanyOnlineService(
            company_id=company_id,
            name=name,
            name_source=name_source,
            description=desc,
            description_source=desc_source,
        )
        db.add(service)

    db.flush()


def _save_social_media_accounts(
    db: Session, company_id: int, accounts: list[dict]
) -> None:
    """Save social media accounts to normalized table."""
    # Delete existing accounts
    db.query(CompanySocialMediaAccount).filter(
        CompanySocialMediaAccount.company_id == company_id
    ).delete()

    for account_data in accounts:
        if not isinstance(account_data, dict):
            continue

        # Platform and URL might be direct values or sourced values
        platform = account_data.get("platform")
        if isinstance(platform, dict):
            platform = platform.get("value")
        platform_source = account_data.get("source")

        url = account_data.get("url")
        if isinstance(url, dict):
            url = url.get("value")

        account = CompanySocialMediaAccount(
            company_id=company_id,
            platform=platform,
            platform_source=platform_source,
            url=url,
            url_source=platform_source,  # Same source for both
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
    digital = db.query(CompanyDigital).filter(
        CompanyDigital.company_id == company_id
    ).first()

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
    services = db.query(CompanyOnlineService).filter(
        CompanyOnlineService.company_id == company_id
    ).all()

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
    accounts = db.query(CompanySocialMediaAccount).filter(
        CompanySocialMediaAccount.company_id == company_id
    ).all()

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
    """Save timeline data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing timeline section
    """
    timeline_data = data.get("timeline", {})
    if not timeline_data:
        logger.debug(f"No timeline data to save for company {company_id}")
        return

    # Check for existing timeline record
    existing = db.query(CompanyTimeline).filter(
        CompanyTimeline.company_id == company_id
    ).first()

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


def _save_timeline_events(
    db: Session, company_id: int, events: list[dict]
) -> None:
    """Save timeline events to normalized table."""
    # Delete existing events
    db.query(CompanyTimelineEvent).filter(
        CompanyTimelineEvent.company_id == company_id
    ).delete()

    for event_data in events:
        if not isinstance(event_data, dict):
            continue

        date, date_source = _get_sourced_value(event_data, "date")
        title, title_source = _get_sourced_value(event_data, "title")
        desc, desc_source = _get_sourced_value(event_data, "description")
        category, category_source = _get_sourced_value(event_data, "category")
        location, location_source = _get_sourced_value(event_data, "location")
        impact, impact_source = _get_sourced_value(event_data, "impact")

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

    db.flush()


def get_timeline_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read timeline data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.timeline interface
    """
    timeline = db.query(CompanyTimeline).filter(
        CompanyTimeline.company_id == company_id
    ).first()

    if not timeline:
        return {}

    result = {}

    if timeline.insights:
        result["insights"] = timeline.insights

    # Get timeline events
    events = db.query(CompanyTimelineEvent).filter(
        CompanyTimelineEvent.company_id == company_id
    ).all()

    if events:
        events_list = []
        for e in events:
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
        result["events"] = events_list

    return result


# =============================================================================
# PRODUCTS DATA - WRITER AND READER
# =============================================================================


def save_products_data(db: Session, company_id: int, data: dict) -> None:
    """Save products data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing products section
    """
    products_data = data.get("products", {})
    if not products_data:
        logger.debug(f"No products data to save for company {company_id}")
        return

    # Check for existing products record
    existing = db.query(CompanyProducts).filter(
        CompanyProducts.company_id == company_id
    ).first()

    if existing:
        products = existing
    else:
        products = CompanyProducts(company_id=company_id)
        db.add(products)

    # Insights
    products.insights = _get_string_value(products_data, "insights")
    products.insights_source = "Chaps-e"

    # Customer type and marketing positioning
    (
        products.customer_type,
        products.customer_type_source,
    ) = _get_sourced_value(products_data, "customerType")
    (
        products.marketing_positioning,
        products.marketing_positioning_source,
    ) = _get_sourced_value(products_data, "marketingPositioning")

    db.flush()

    # Save product items (1:N) - range, partner brands, private labels
    _save_product_items(db, company_id, products_data)

    # Save product categories (1:N)
    _save_product_categories(db, company_id, products_data.get("categories", {}))

    logger.info(f"Saved products data for company {company_id}")


def _save_product_items(db: Session, company_id: int, products_data: dict) -> None:
    """Save product items to normalized table."""
    # Delete existing items
    db.query(CompanyProductItem).filter(
        CompanyProductItem.company_id == company_id
    ).delete()

    # Save range items
    for item in products_data.get("range", []):
        if isinstance(item, dict):
            value, source = item.get("value"), item.get("source")
        else:
            value, source = item, None

        db.add(CompanyProductItem(
            company_id=company_id,
            type=ProductItemType.range,
            value=value,
            value_source=source,
        ))

    # Save partner brands
    for item in products_data.get("partnerBrands", []):
        if isinstance(item, dict):
            value, source = item.get("value"), item.get("source")
        else:
            value, source = item, None

        db.add(CompanyProductItem(
            company_id=company_id,
            type=ProductItemType.partner_brand,
            value=value,
            value_source=source,
        ))

    # Save private labels
    for item in products_data.get("privateLabels", []):
        if isinstance(item, dict):
            value, source = item.get("value"), item.get("source")
        else:
            value, source = item, None

        db.add(CompanyProductItem(
            company_id=company_id,
            type=ProductItemType.private_label,
            value=value,
            value_source=source,
        ))

    db.flush()


def _save_product_categories(
    db: Session, company_id: int, categories: dict
) -> None:
    """Save product categories to normalized table."""
    # Delete existing categories
    db.query(CompanyProductCategory).filter(
        CompanyProductCategory.company_id == company_id
    ).delete()

    if not isinstance(categories, dict):
        return

    for category_name, items in categories.items():
        if not isinstance(items, list):
            items = [items] if items else []

        db.add(CompanyProductCategory(
            company_id=company_id,
            category_name=category_name,
            items=items,
        ))

    db.flush()


def get_products_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read products data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.products interface
    """
    products = db.query(CompanyProducts).filter(
        CompanyProducts.company_id == company_id
    ).first()

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
    items = db.query(CompanyProductItem).filter(
        CompanyProductItem.company_id == company_id
    ).all()

    range_items = [
        {"value": i.value, "source": i.value_source}
        for i in items if i.type == ProductItemType.range
    ]
    partner_brands = [
        {"value": i.value, "source": i.value_source}
        for i in items if i.type == ProductItemType.partner_brand
    ]
    private_labels = [
        {"value": i.value, "source": i.value_source}
        for i in items if i.type == ProductItemType.private_label
    ]

    if range_items:
        result["range"] = range_items
    if partner_brands:
        result["partnerBrands"] = partner_brands
    if private_labels:
        result["privateLabels"] = private_labels

    # Get product categories
    categories = db.query(CompanyProductCategory).filter(
        CompanyProductCategory.company_id == company_id
    ).all()

    if categories:
        result["categories"] = {
            c.category_name: c.items or []
            for c in categories
        }

    return result


# =============================================================================
# JOBS DATA - WRITER AND READER
# =============================================================================


def save_jobs_data(db: Session, company_id: int, data: dict) -> None:
    """Save jobs data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing jobs section
    """
    jobs_data = data.get("jobs", {})
    if not jobs_data:
        logger.debug(f"No jobs data to save for company {company_id}")
        return

    # Check for existing jobs record
    existing = db.query(CompanyJobs).filter(
        CompanyJobs.company_id == company_id
    ).first()

    if existing:
        jobs = existing
    else:
        jobs = CompanyJobs(company_id=company_id)
        db.add(jobs)

    # Extract insights (nested structure)
    insights = jobs_data.get("insights", {})
    if isinstance(insights, dict):
        # Total openings
        total_openings_data = insights.get("total_openings", {})
        if isinstance(total_openings_data, dict):
            value = total_openings_data.get("value")
            if isinstance(value, (int, float)):
                jobs.insights_total_openings = int(value)
            elif isinstance(value, str) and value.isdigit():
                jobs.insights_total_openings = int(value)
            jobs.insights_total_openings_source = total_openings_data.get("source")

        # Top departments
        top_depts_data = insights.get("top_departments", {})
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
        ) = _get_sourced_value(insights, "hiring_focus")

        # Growth indicators
        (
            jobs.insights_growth_indicators,
            jobs.insights_growth_indicators_source,
        ) = _get_sourced_value(insights, "growth_indicators")

    db.flush()

    # Save job offers (1:N)
    _save_job_offers(db, company_id, jobs_data.get("offers", []))

    logger.info(f"Saved jobs data for company {company_id}")


def _save_job_offers(db: Session, company_id: int, offers: list[dict]) -> None:
    """Save job offers to normalized table."""
    # Delete existing offers
    db.query(CompanyJobOffer).filter(
        CompanyJobOffer.company_id == company_id
    ).delete()

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
    jobs = db.query(CompanyJobs).filter(
        CompanyJobs.company_id == company_id
    ).first()

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
    offers = db.query(CompanyJobOffer).filter(
        CompanyJobOffer.company_id == company_id
    ).all()

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
    """Save CSR data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing CSR section
    """
    csr_data = data.get("csr", {})
    if not csr_data:
        logger.debug(f"No CSR data to save for company {company_id}")
        return

    # Check for existing CSR record
    existing = db.query(CompanyCsr).filter(
        CompanyCsr.company_id == company_id
    ).first()

    if existing:
        csr = existing
    else:
        csr = CompanyCsr(company_id=company_id)
        db.add(csr)

    # Insights
    csr.insights = _get_string_value(csr_data, "insights")
    csr.insights_source = "Chaps-e"

    # Responsibility
    csr.responsibility, csr.responsibility_source = _get_sourced_value(
        csr_data, "responsibility"
    )

    db.flush()

    # Save CSR initiatives (1:N)
    _save_csr_initiatives(db, company_id, csr_data)

    logger.info(f"Saved CSR data for company {company_id}")


def _save_csr_initiatives(db: Session, company_id: int, csr_data: dict) -> None:
    """Save CSR initiatives to normalized table."""
    # Delete existing initiatives
    db.query(CompanyCsrInitiative).filter(
        CompanyCsrInitiative.company_id == company_id
    ).delete()

    # Map of field names to initiative types
    type_mapping = {
        "responsibility_initiatives": CsrInitiativeType.responsibility,
        "charity_actions": CsrInitiativeType.charity,
        "sustainability_programs": CsrInitiativeType.sustainability,
        "community_involvement": CsrInitiativeType.community,
        "diversity_inclusion": CsrInitiativeType.diversity,
        "ethical_practices": CsrInitiativeType.ethics,
        "awards_certifications": CsrInitiativeType.awards,
    }

    for field_name, init_type in type_mapping.items():
        items = csr_data.get(field_name, [])
        if not isinstance(items, list):
            continue

        for item in items:
            if isinstance(item, dict):
                value = item.get("value")
                source = item.get("source")
            else:
                value = item
                source = None

            if value:
                db.add(CompanyCsrInitiative(
                    company_id=company_id,
                    type=init_type,
                    value=value,
                    value_source=source,
                ))

    db.flush()


def get_csr_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read CSR data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.csr interface
    """
    csr = db.query(CompanyCsr).filter(
        CompanyCsr.company_id == company_id
    ).first()

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
    initiatives = db.query(CompanyCsrInitiative).filter(
        CompanyCsrInitiative.company_id == company_id
    ).all()

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
            result[field_name].append({
                "value": init.value,
                "source": init.value_source,
            })

    return result


# =============================================================================
# PRESS DATA - WRITER AND READER
# =============================================================================


def save_press_data(db: Session, company_id: int, data: dict) -> None:
    """Save press data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing press section
    """
    press_data = data.get("press", {})
    if not press_data:
        logger.debug(f"No press data to save for company {company_id}")
        return

    # Check for existing press record
    existing = db.query(CompanyPress).filter(
        CompanyPress.company_id == company_id
    ).first()

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
    _save_press_items(db, company_id, press_data)

    logger.info(f"Saved press data for company {company_id}")


def _save_press_items(db: Session, company_id: int, press_data: dict) -> None:
    """Save press items to normalized table."""
    # Delete existing items
    db.query(CompanyPressItem).filter(
        CompanyPressItem.company_id == company_id
    ).delete()

    # Map of field names to press types
    type_mapping = {
        "articles": PressItemType.article,
        "press_releases": PressItemType.press_release,
        "media_mentions": PressItemType.media_mention,
        "awards_recognition": PressItemType.award,
        "product_launches": PressItemType.product_launch,
        "executive_interviews": PressItemType.interview,
        "financial_news": PressItemType.financial,
        "partnership_announcements": PressItemType.partnership,
    }

    for field_name, item_type in type_mapping.items():
        items = press_data.get(field_name, [])
        if not isinstance(items, list):
            continue

        for item in items:
            if isinstance(item, dict):
                value = item.get("value")
                source = item.get("source")
            else:
                value = item
                source = None

            if value:
                db.add(CompanyPressItem(
                    company_id=company_id,
                    type=item_type,
                    value=value,
                    value_source=source,
                ))

    db.flush()


def get_press_data(db: Session, company_id: int) -> dict[str, Any]:
    """Read press data from normalized tables.

    Args:
        db: Database session
        company_id: Company ID to read data for

    Returns:
        Dictionary matching frontend Company.press interface
    """
    press = db.query(CompanyPress).filter(
        CompanyPress.company_id == company_id
    ).first()

    if not press:
        return {}

    result = {}

    if press.insights:
        result["insights"] = press.insights

    # Get press items
    items = db.query(CompanyPressItem).filter(
        CompanyPressItem.company_id == company_id
    ).all()

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
            result[field_name].append({
                "value": item.value,
                "source": item.value_source,
            })

    return result


# =============================================================================
# TEAM DATA - WRITER AND READER
# =============================================================================


def save_team_data(db: Session, company_id: int, data: dict) -> None:
    """Save team data from Dify callback to normalized tables.

    Args:
        db: Database session
        company_id: Company ID to save data for
        data: Dify callback data containing team section
    """
    team_data = data.get("team", [])
    if not team_data:
        logger.debug(f"No team data to save for company {company_id}")
        return

    # Delete existing team members
    db.query(CompanyTeamMember).filter(
        CompanyTeamMember.company_id == company_id
    ).delete()

    # Get the members list
    members = team_data
    if isinstance(team_data, dict):
        members = team_data.get("members", [])
        if not members:
            # Try teamAnalysis structure
            team_analysis = team_data.get("teamAnalysis", {})
            if isinstance(team_analysis, dict):
                members = team_analysis.get("team", [])

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

        # Extract member fields
        position, position_source = _get_sourced_value(member_data, "position")
        first_name, first_name_source = _get_sourced_value(member_data, "firstName")
        last_name, last_name_source = _get_sourced_value(member_data, "lastName")
        linkedin, linkedin_source = _get_sourced_value(member_data, "linkedinUrl")

        # Handle direct string values (not sourced)
        if not position:
            position = member_data.get("position")
        if not first_name:
            first_name = member_data.get("firstName")
        if not last_name:
            last_name = member_data.get("lastName")
        if not linkedin:
            linkedin = member_data.get("linkedinUrl")

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
    all_members = db.query(CompanyTeamMember).filter(
        CompanyTeamMember.company_id == company_id
    ).all()

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
        data: Dify callback data containing the section
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
        "team": save_team_data,
    }

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
        "team": get_team_data(db, company_id),
    }
