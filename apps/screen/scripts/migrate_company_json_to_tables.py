"""
Migrate existing company JSON data to normalized tables.

This script MUST be run AFTER migrations 013 + 014 (create tables)
and BEFORE migration 015 (drop JSON columns).

Usage:
    docker compose exec backend python scripts/migrate_company_json_to_tables.py

The script:
1. Reads all companies with JSON data in old columns
2. Parses JSON and inserts into normalized tables
3. Reports migration statistics
4. Does NOT delete the old JSON data (that's done by migration 015)

Safe to run multiple times - uses INSERT ... ON CONFLICT DO NOTHING
"""

import os
import sys

from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

# Add parent directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.core.config import settings
from app.core.logging_config import get_logger

logger = get_logger(__name__)


def get_db_session():
    """Create database session."""
    engine = create_engine(settings.DATABASE_URL)
    Session = sessionmaker(bind=engine)
    return Session()


def safe_get(data: dict | None, key: str, default=None):
    """Safely get nested value from dict."""
    if data is None:
        return default
    return data.get(key, default)


def extract_sourced_value(item):
    """Extract value and source from SourcedValue object or plain value."""
    if item is None:
        return None, None
    if isinstance(item, dict):
        return item.get("value"), item.get("source")
    return item, None


def migrate_profile(session, company_id: int, profile: dict) -> bool:
    """Migrate profile JSON to company_profile table."""
    if not profile:
        return False

    try:
        # Extract values with SourcedValue pattern
        insights, insights_source = extract_sourced_value(profile.get("insights"))
        group_name, group_name_source = extract_sourced_value(profile.get("groupName"))
        business_line, business_line_source = extract_sourced_value(profile.get("businessLine"))
        catchphrase, catchphrase_source = extract_sourced_value(profile.get("catchphrase"))
        establishment_year, establishment_year_source = extract_sourced_value(profile.get("establishmentYear"))
        employee_count, employee_count_source = extract_sourced_value(profile.get("employeeCount"))
        revenue, revenue_source = extract_sourced_value(profile.get("revenue"))
        ceo, ceo_source = extract_sourced_value(profile.get("ceo"))
        hq, hq_source = extract_sourced_value(profile.get("hq"))

        session.execute(
            text("""
            INSERT INTO company_profile (
                company_id, insights, insights_source, group_name, group_name_source,
                business_line, business_line_source, catchphrase, catchphrase_source,
                establishment_year, establishment_year_source, employee_count, employee_count_source,
                revenue, revenue_source, ceo, ceo_source, hq, hq_source
            ) VALUES (
                :company_id, :insights, :insights_source, :group_name, :group_name_source,
                :business_line, :business_line_source, :catchphrase, :catchphrase_source,
                :establishment_year, :establishment_year_source, :employee_count, :employee_count_source,
                :revenue, :revenue_source, :ceo, :ceo_source, :hq, :hq_source
            ) ON CONFLICT (company_id) DO NOTHING
        """),
            {
                "company_id": company_id,
                "insights": insights,
                "insights_source": insights_source,
                "group_name": group_name,
                "group_name_source": group_name_source,
                "business_line": business_line,
                "business_line_source": business_line_source,
                "catchphrase": catchphrase,
                "catchphrase_source": catchphrase_source,
                "establishment_year": establishment_year,
                "establishment_year_source": establishment_year_source,
                "employee_count": employee_count,
                "employee_count_source": employee_count_source,
                "revenue": revenue,
                "revenue_source": revenue_source,
                "ceo": ceo,
                "ceo_source": ceo_source,
                "hq": hq,
                "hq_source": hq_source,
            },
        )
        return True
    except Exception as e:
        logger.error(f"Error migrating profile for company {company_id}: {e}")
        return False


def migrate_digital(session, company_id: int, digital: dict) -> bool:
    """Migrate digital JSON to company_digital and company_online_services tables."""
    if not digital:
        return False

    try:
        # Extract main digital fields
        insights = digital.get("insights")

        # Digital strategy is nested - handle both old and new formats
        strategy_data = digital.get("digitalStrategy", {})
        strategy_source = None
        if isinstance(strategy_data, dict):
            if "value" in strategy_data:
                strategy = strategy_data.get("value", {})
                # Handle both 'source' (new) and 'sources' (old) format
                strategy_source = strategy_data.get("source") or (
                    strategy_data.get("sources", [None])[0] if strategy_data.get("sources") else None
                )
            else:
                strategy = strategy_data
        else:
            strategy = {}

        # Extract strategy fields - they may be plain strings (old) or SourcedValue (new)
        overall_strategy, os_src = extract_sourced_value(
            strategy.get("overallStrategy") if isinstance(strategy, dict) else None
        )
        overall_strategy_source = os_src or strategy_source

        digital_transformation, dt_src = extract_sourced_value(
            strategy.get("digitalTransformation") if isinstance(strategy, dict) else None
        )
        digital_transformation_source = dt_src or strategy_source

        ecommerce, ec_src = extract_sourced_value(
            strategy.get("eCommerceCapabilities") if isinstance(strategy, dict) else None
        )
        ecommerce_source = ec_src or strategy_source

        mobile, ms_src = extract_sourced_value(strategy.get("mobileStrategy") if isinstance(strategy, dict) else None)
        mobile_source = ms_src or strategy_source

        marketing, ma_src = extract_sourced_value(
            strategy.get("digitalMarketingApproach") if isinstance(strategy, dict) else None
        )
        marketing_source = ma_src or strategy_source

        loyalty_data = digital.get("loyaltyProgram") or digital.get("loyaltyPrograms")
        loyalty, loyalty_source = extract_sourced_value(loyalty_data)
        # Handle case where loyalty is a complex object (list of programs)
        if isinstance(loyalty, list | dict):
            import json

            loyalty = json.dumps(loyalty) if loyalty else None

        session.execute(
            text("""
            INSERT INTO company_digital (
                company_id, insights, overall_strategy, overall_strategy_source,
                digital_transformation, digital_transformation_source,
                ecommerce_capabilities, ecommerce_capabilities_source,
                mobile_strategy, mobile_strategy_source,
                digital_marketing_approach, digital_marketing_approach_source,
                loyalty_program, loyalty_program_source
            ) VALUES (
                :company_id, :insights, :overall_strategy, :overall_strategy_source,
                :digital_transformation, :digital_transformation_source,
                :ecommerce_capabilities, :ecommerce_capabilities_source,
                :mobile_strategy, :mobile_strategy_source,
                :digital_marketing_approach, :digital_marketing_approach_source,
                :loyalty_program, :loyalty_program_source
            ) ON CONFLICT (company_id) DO NOTHING
        """),
            {
                "company_id": company_id,
                "insights": insights,
                "overall_strategy": overall_strategy,
                "overall_strategy_source": overall_strategy_source,
                "digital_transformation": digital_transformation,
                "digital_transformation_source": digital_transformation_source,
                "ecommerce_capabilities": ecommerce,
                "ecommerce_capabilities_source": ecommerce_source,
                "mobile_strategy": mobile,
                "mobile_strategy_source": mobile_source,
                "digital_marketing_approach": marketing,
                "digital_marketing_approach_source": marketing_source,
                "loyalty_program": loyalty,
                "loyalty_program_source": loyalty_source,
            },
        )

        # Migrate online services
        # Handle both old format (sources array) and new format (source string)
        online_services = digital.get("onlineServices", {})
        if isinstance(online_services, dict) and "value" in online_services:
            value = online_services.get("value", [])
            # value can be a list of services directly or a dict with 'services' key
            if isinstance(value, list):
                services_list = value
            elif isinstance(value, dict):
                services_list = value.get("services", [])
            else:
                services_list = []
            # Handle both 'source' (new) and 'sources' (old) format
            source = online_services.get("source") or (
                online_services.get("sources", [None])[0] if online_services.get("sources") else None
            )
        else:
            services_list = []
            source = None

        for service in services_list:
            if isinstance(service, dict):
                session.execute(
                    text("""
                    INSERT INTO company_online_services (company_id, name, name_source, description, description_source)
                    VALUES (:company_id, :name, :source, :description, :source)
                """),
                    {
                        "company_id": company_id,
                        "name": service.get("name"),
                        "description": service.get("description"),
                        "source": source,
                    },
                )

        # Migrate social media accounts
        # Handle both old format (dict with value/sources) and new format (list)
        social_data = digital.get("socialMediaAccounts", [])
        if isinstance(social_data, dict) and "value" in social_data:
            social_accounts = social_data.get("value", [])
            social_source = social_data.get("source") or (
                social_data.get("sources", [None])[0] if social_data.get("sources") else None
            )
        elif isinstance(social_data, list):
            social_accounts = social_data
            social_source = None
        else:
            social_accounts = []
            social_source = None

        for account in social_accounts:
            if isinstance(account, dict):
                session.execute(
                    text("""
                    INSERT INTO company_social_media_accounts (company_id, platform, platform_source, url, url_source)
                    VALUES (:company_id, :platform, :source, :url, :source)
                """),
                    {
                        "company_id": company_id,
                        "platform": account.get("platform"),
                        "url": account.get("url"),
                        "source": account.get("source") or social_source,
                    },
                )

        return True
    except Exception as e:
        logger.error(f"Error migrating digital for company {company_id}: {e}")
        return False


def migrate_timeline(session, company_id: int, timeline: dict) -> bool:
    """Migrate timeline JSON to company_timeline and company_timeline_events tables."""
    if not timeline:
        return False

    try:
        insights = timeline.get("insights")

        session.execute(
            text("""
            INSERT INTO company_timeline (company_id, insights)
            VALUES (:company_id, :insights)
            ON CONFLICT (company_id) DO NOTHING
        """),
            {"company_id": company_id, "insights": insights},
        )

        # Migrate timeline events
        events = timeline.get("events", [])
        for event in events:
            if isinstance(event, dict):
                date, date_source = extract_sourced_value(event.get("date"))
                title, title_source = extract_sourced_value(event.get("title"))
                description, desc_source = extract_sourced_value(event.get("description"))
                category, cat_source = extract_sourced_value(event.get("category"))
                location, loc_source = extract_sourced_value(event.get("location"))
                impact, impact_source = extract_sourced_value(event.get("impact"))

                # Use event-level source as fallback
                event_source = event.get("source")

                session.execute(
                    text("""
                    INSERT INTO company_timeline_events (
                        company_id, date, date_source, title, title_source,
                        description, description_source, category, category_source,
                        location, location_source, impact, impact_source
                    ) VALUES (
                        :company_id, :date, :date_source, :title, :title_source,
                        :description, :desc_source, :category, :cat_source,
                        :location, :loc_source, :impact, :impact_source
                    )
                """),
                    {
                        "company_id": company_id,
                        "date": date,
                        "date_source": date_source or event_source,
                        "title": title,
                        "title_source": title_source or event_source,
                        "description": description,
                        "desc_source": desc_source or event_source,
                        "category": category,
                        "cat_source": cat_source or event_source,
                        "location": location,
                        "loc_source": loc_source or event_source,
                        "impact": impact,
                        "impact_source": impact_source or event_source,
                    },
                )

        return True
    except Exception as e:
        logger.error(f"Error migrating timeline for company {company_id}: {e}")
        return False


def migrate_products(session, company_id: int, products: dict) -> bool:
    """Migrate products JSON to company_products and related tables."""
    if not products:
        return False

    try:
        insights = products.get("insights")
        customer_type, customer_type_source = extract_sourced_value(products.get("customerType"))
        marketing_positioning, mp_source = extract_sourced_value(products.get("marketingPositioning"))

        session.execute(
            text("""
            INSERT INTO company_products (
                company_id, insights, customer_type, customer_type_source,
                marketing_positioning, marketing_positioning_source
            ) VALUES (
                :company_id, :insights, :customer_type, :customer_type_source,
                :marketing_positioning, :mp_source
            ) ON CONFLICT (company_id) DO NOTHING
        """),
            {
                "company_id": company_id,
                "insights": insights,
                "customer_type": customer_type,
                "customer_type_source": customer_type_source,
                "marketing_positioning": marketing_positioning,
                "mp_source": mp_source,
            },
        )

        # Migrate product items (range, partner brands, private labels)
        for item_type, key in [
            ("range", "range"),
            ("partner_brand", "partnerBrands"),
            ("private_label", "privateLabels"),
        ]:
            items = products.get(key, [])
            for item in items:
                value, source = extract_sourced_value(item)
                if value:
                    session.execute(
                        text("""
                        INSERT INTO company_product_items (company_id, type, value, value_source)
                        VALUES (:company_id, :type, :value, :source)
                    """),
                        {
                            "company_id": company_id,
                            "type": item_type,
                            "value": value,
                            "source": source,
                        },
                    )

        # Migrate product categories
        categories = products.get("categories", {})
        for cat_name, items in categories.items():
            if isinstance(items, list):
                session.execute(
                    text("""
                    INSERT INTO company_product_categories (company_id, category_name, items)
                    VALUES (:company_id, :category_name, :items)
                """),
                    {
                        "company_id": company_id,
                        "category_name": cat_name,
                        "items": items,
                    },
                )

        return True
    except Exception as e:
        logger.error(f"Error migrating products for company {company_id}: {e}")
        return False


def migrate_jobs(session, company_id: int, jobs: dict) -> bool:
    """Migrate jobs JSON to company_jobs and company_job_offers tables."""
    if not jobs:
        return False

    try:
        # Extract insights
        insights = jobs.get("insights", {})
        total_openings = None
        total_openings_source = None
        top_departments = None
        top_departments_source = None
        hiring_focus = None
        hiring_focus_source = None
        growth_indicators = None
        growth_indicators_source = None

        if isinstance(insights, dict):
            to_val, total_openings_source = extract_sourced_value(insights.get("total_openings"))
            total_openings = int(to_val) if to_val is not None else None

            td_val, top_departments_source = extract_sourced_value(insights.get("top_departments"))
            if isinstance(td_val, list):
                top_departments = ", ".join(td_val)
            else:
                top_departments = td_val

            hiring_focus, hiring_focus_source = extract_sourced_value(insights.get("hiring_focus"))
            growth_indicators, growth_indicators_source = extract_sourced_value(insights.get("growth_indicators"))

        session.execute(
            text("""
            INSERT INTO company_jobs (
                company_id, insights_total_openings, insights_total_openings_source,
                insights_top_departments, insights_top_departments_source,
                insights_hiring_focus, insights_hiring_focus_source,
                insights_growth_indicators, insights_growth_indicators_source
            ) VALUES (
                :company_id, :total_openings, :total_openings_source,
                :top_departments, :top_departments_source,
                :hiring_focus, :hiring_focus_source,
                :growth_indicators, :growth_indicators_source
            ) ON CONFLICT (company_id) DO NOTHING
        """),
            {
                "company_id": company_id,
                "total_openings": total_openings,
                "total_openings_source": total_openings_source,
                "top_departments": top_departments,
                "top_departments_source": top_departments_source,
                "hiring_focus": hiring_focus,
                "hiring_focus_source": hiring_focus_source,
                "growth_indicators": growth_indicators,
                "growth_indicators_source": growth_indicators_source,
            },
        )

        # Migrate job offers
        offers = jobs.get("offers", [])
        for offer in offers:
            if isinstance(offer, dict):
                title, title_source = extract_sourced_value(offer.get("title"))
                location, location_source = extract_sourced_value(offer.get("location"))
                department, dept_source = extract_sourced_value(offer.get("department"))
                description, desc_source = extract_sourced_value(offer.get("description"))
                requirements, req_source = extract_sourced_value(offer.get("requirements"))
                posted_date, posted_source = extract_sourced_value(offer.get("posted_date"))

                # Use offer-level source as fallback
                offer_source = offer.get("source")

                session.execute(
                    text("""
                    INSERT INTO company_job_offers (
                        company_id, title, title_source, location, location_source,
                        department, department_source, description, description_source,
                        requirements, requirements_source, posted_date, posted_date_source
                    ) VALUES (
                        :company_id, :title, :title_source, :location, :location_source,
                        :department, :dept_source, :description, :desc_source,
                        :requirements, :req_source, :posted_date, :posted_source
                    )
                """),
                    {
                        "company_id": company_id,
                        "title": title,
                        "title_source": title_source or offer_source,
                        "location": location,
                        "location_source": location_source or offer_source,
                        "department": department,
                        "dept_source": dept_source or offer_source,
                        "description": description,
                        "desc_source": desc_source or offer_source,
                        "requirements": requirements,
                        "req_source": req_source or offer_source,
                        "posted_date": posted_date,
                        "posted_source": posted_source or offer_source,
                    },
                )

        return True
    except Exception as e:
        logger.error(f"Error migrating jobs for company {company_id}: {e}")
        return False


def migrate_csr(session, company_id: int, csr: dict) -> bool:
    """Migrate CSR JSON to company_csr and company_csr_initiatives tables."""
    if not csr:
        return False

    try:
        insights = csr.get("insights")
        responsibility, responsibility_source = extract_sourced_value(csr.get("responsibility"))

        session.execute(
            text("""
            INSERT INTO company_csr (company_id, insights, responsibility, responsibility_source)
            VALUES (:company_id, :insights, :responsibility, :responsibility_source)
            ON CONFLICT (company_id) DO NOTHING
        """),
            {
                "company_id": company_id,
                "insights": insights,
                "responsibility": responsibility,
                "responsibility_source": responsibility_source,
            },
        )

        # Migrate CSR initiatives by type
        type_mappings = [
            ("responsibility", "responsibility_initiatives"),
            ("charity", "charity_actions"),
            ("sustainability", "sustainability_programs"),
            ("community", "community_involvement"),
            ("diversity", "diversity_inclusion"),
            ("ethics", "ethical_practices"),
            ("awards", "awards_certifications"),
        ]

        for init_type, key in type_mappings:
            items = csr.get(key, [])
            for item in items:
                value, source = extract_sourced_value(item)
                if value:
                    session.execute(
                        text("""
                        INSERT INTO company_csr_initiatives (company_id, type, value, value_source)
                        VALUES (:company_id, :type, :value, :source)
                    """),
                        {
                            "company_id": company_id,
                            "type": init_type,
                            "value": value,
                            "source": source,
                        },
                    )

        return True
    except Exception as e:
        logger.error(f"Error migrating CSR for company {company_id}: {e}")
        return False


def migrate_press(session, company_id: int, press: dict) -> bool:
    """Migrate press JSON to company_press and company_press_items tables."""
    if not press:
        return False

    try:
        insights = press.get("insights")

        session.execute(
            text("""
            INSERT INTO company_press (company_id, insights)
            VALUES (:company_id, :insights)
            ON CONFLICT (company_id) DO NOTHING
        """),
            {"company_id": company_id, "insights": insights},
        )

        # Migrate press items by type
        type_mappings = [
            ("article", "articles"),
            ("press_release", "press_releases"),
            ("media_mention", "media_mentions"),
            ("award", "awards_recognition"),
            ("product_launch", "product_launches"),
            ("interview", "executive_interviews"),
            ("financial", "financial_news"),
            ("partnership", "partnership_announcements"),
        ]

        for press_type, key in type_mappings:
            items = press.get(key, [])
            for item in items:
                if isinstance(item, dict):
                    value = item.get("value")
                    source = item.get("source")
                else:
                    value, source = extract_sourced_value(item)

                if value:
                    session.execute(
                        text("""
                        INSERT INTO company_press_items (company_id, type, value, value_source)
                        VALUES (:company_id, :type, :value, :source)
                    """),
                        {
                            "company_id": company_id,
                            "type": press_type,
                            "value": value,
                            "source": source,
                        },
                    )

        return True
    except Exception as e:
        logger.error(f"Error migrating press for company {company_id}: {e}")
        return False


def migrate_team(session, company_id: int, team: list, parent_id: int | None = None) -> bool:
    """Migrate team JSON to company_team_members table (recursive for hierarchy)."""
    if not team:
        return False

    try:
        for member in team:
            if not isinstance(member, dict):
                continue

            position = member.get("position")
            first_name = member.get("firstName")
            last_name = member.get("lastName")
            linkedin_url = member.get("linkedinUrl")

            result = session.execute(
                text("""
                INSERT INTO company_team_members (
                    company_id, parent_id, position, first_name, last_name, linkedin_url
                ) VALUES (
                    :company_id, :parent_id, :position, :first_name, :last_name, :linkedin_url
                ) RETURNING id
            """),
                {
                    "company_id": company_id,
                    "parent_id": parent_id,
                    "position": position,
                    "first_name": first_name,
                    "last_name": last_name,
                    "linkedin_url": linkedin_url,
                },
            )

            new_member_id = result.scalar()

            # Recursively migrate subordinates
            subordinates = member.get("subordinates", [])
            if subordinates:
                migrate_team(session, company_id, subordinates, parent_id=new_member_id)

        return True
    except Exception as e:
        logger.error(f"Error migrating team for company {company_id}: {e}")
        return False


def run_migration():
    """Main migration function."""
    session = get_db_session()

    print("=" * 60)
    print("Company JSON to Tables Data Migration")
    print("=" * 60)
    print()

    try:
        # Check if JSON columns still exist
        result = session.execute(
            text("""
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = 'companies' AND column_name = 'profile'
        """)
        )
        if not result.fetchone():
            print("ERROR: JSON columns already dropped!")
            print("This script must run BEFORE migration 015.")
            return False

        # Get all companies with JSON data
        companies = session.execute(
            text("""
            SELECT id, name, profile, digital, timeline, products, jobs, csr, press, team
            FROM companies
            WHERE profile IS NOT NULL
               OR digital IS NOT NULL
               OR timeline IS NOT NULL
               OR products IS NOT NULL
               OR jobs IS NOT NULL
               OR csr IS NOT NULL
               OR press IS NOT NULL
               OR team IS NOT NULL
        """)
        ).fetchall()

        print(f"Found {len(companies)} companies with JSON data to migrate")
        print()

        stats = {
            "total": len(companies),
            "profile": 0,
            "digital": 0,
            "timeline": 0,
            "products": 0,
            "jobs": 0,
            "csr": 0,
            "press": 0,
            "team": 0,
            "errors": 0,
        }

        for company in companies:
            company_id = company[0]
            company_name = company[1]
            profile = company[2]
            digital = company[3]
            timeline = company[4]
            products = company[5]
            jobs = company[6]
            csr = company[7]
            press = company[8]
            team = company[9]

            print(f"Migrating company {company_id}: {company_name}...")

            try:
                if migrate_profile(session, company_id, profile):
                    stats["profile"] += 1
                if migrate_digital(session, company_id, digital):
                    stats["digital"] += 1
                if migrate_timeline(session, company_id, timeline):
                    stats["timeline"] += 1
                if migrate_products(session, company_id, products):
                    stats["products"] += 1
                if migrate_jobs(session, company_id, jobs):
                    stats["jobs"] += 1
                if migrate_csr(session, company_id, csr):
                    stats["csr"] += 1
                if migrate_press(session, company_id, press):
                    stats["press"] += 1
                if migrate_team(session, company_id, team):
                    stats["team"] += 1

                session.commit()
                print("  OK")

            except Exception as e:
                logger.error(f"Error migrating company {company_id}: {e}")
                session.rollback()
                stats["errors"] += 1
                print(f"  ERROR: {e}")

        print()
        print("=" * 60)
        print("Migration Statistics")
        print("=" * 60)
        print(f"Total companies: {stats['total']}")
        print(f"Profile migrated: {stats['profile']}")
        print(f"Digital migrated: {stats['digital']}")
        print(f"Timeline migrated: {stats['timeline']}")
        print(f"Products migrated: {stats['products']}")
        print(f"Jobs migrated: {stats['jobs']}")
        print(f"CSR migrated: {stats['csr']}")
        print(f"Press migrated: {stats['press']}")
        print(f"Team migrated: {stats['team']}")
        print(f"Errors: {stats['errors']}")
        print()

        if stats["errors"] == 0:
            print("SUCCESS: All data migrated successfully!")
            print()
            print("Next steps:")
            print("1. Verify data in new tables")
            print("2. Run: alembic upgrade head  (to apply migration 015)")
            return True
        else:
            print(f"WARNING: {stats['errors']} errors occurred during migration")
            print("Review logs and fix issues before proceeding")
            return False

    except Exception as e:
        logger.error(f"Migration failed: {e}")
        session.rollback()
        print(f"FATAL ERROR: {e}")
        return False
    finally:
        session.close()


if __name__ == "__main__":
    success = run_migration()
    sys.exit(0 if success else 1)
