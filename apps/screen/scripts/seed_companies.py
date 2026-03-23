#!/usr/bin/env python3
"""
Seed script to populate local database with sample companies.

Usage:
    docker compose exec backend python scripts/seed_companies.py

This script can either:
1. Load companies from a JSON fixture file (default)
2. Export companies from production to JSON (--export mode)
"""

import argparse
import json
import sys
from pathlib import Path

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlalchemy.orm import Session

from app.database import SessionLocal
from app.models.company import Company
from app.models.task import Task, TaskStatus, TaskType

# Sample companies fixture data
SAMPLE_COMPANIES = [
    {
        "name": "Anthropic",
        "website": "https://www.anthropic.com",
        "organization_id": "default-org",
        "owner_id": None,
        "owner_username": "system",
        "profile": {
            "description": "AI safety company building reliable, interpretable AI systems",
            "industry": "Artificial Intelligence",
            "founded": "2021",
            "headquarters": "San Francisco, CA",
            "employees": "500-1000",
        },
        "digital": {
            "linkedin": "https://www.linkedin.com/company/anthropic",
            "twitter": "https://twitter.com/AnthropicAI",
        },
    },
    {
        "name": "OpenAI",
        "website": "https://www.openai.com",
        "organization_id": "default-org",
        "owner_id": None,
        "owner_username": "system",
        "profile": {
            "description": "AI research and deployment company",
            "industry": "Artificial Intelligence",
            "founded": "2015",
            "headquarters": "San Francisco, CA",
            "employees": "1000-5000",
        },
        "digital": {"linkedin": "https://www.linkedin.com/company/openai", "twitter": "https://twitter.com/OpenAI"},
    },
    {
        "name": "Google DeepMind",
        "website": "https://deepmind.google",
        "organization_id": "default-org",
        "owner_id": None,
        "owner_username": "system",
        "profile": {
            "description": "AI research laboratory",
            "industry": "Artificial Intelligence",
            "founded": "2010",
            "headquarters": "London, UK",
            "employees": "1000-5000",
        },
        "digital": {
            "linkedin": "https://www.linkedin.com/company/deepmind",
            "twitter": "https://twitter.com/GoogleDeepMind",
        },
    },
    {
        "name": "Meta AI",
        "website": "https://ai.meta.com",
        "organization_id": "default-org",
        "owner_id": None,
        "owner_username": "system",
        "profile": {
            "description": "AI research division of Meta",
            "industry": "Artificial Intelligence",
            "founded": "2013",
            "headquarters": "Menlo Park, CA",
            "employees": "1000-5000",
        },
        "digital": {"linkedin": "https://www.linkedin.com/company/meta", "twitter": "https://twitter.com/AIatMeta"},
    },
    {
        "name": "Mistral AI",
        "website": "https://mistral.ai",
        "organization_id": "default-org",
        "owner_id": None,
        "owner_username": "system",
        "profile": {
            "description": "French AI startup building open-weight LLMs",
            "industry": "Artificial Intelligence",
            "founded": "2023",
            "headquarters": "Paris, France",
            "employees": "50-200",
        },
        "digital": {
            "linkedin": "https://www.linkedin.com/company/mistral-ai",
            "twitter": "https://twitter.com/MistralAI",
        },
    },
]


def get_fixture_path() -> Path:
    """Get path to fixture file."""
    return Path(__file__).parent / "fixtures" / "companies.json"


def load_fixture() -> list[dict]:
    """Load companies from fixture file or use defaults."""
    fixture_path = get_fixture_path()
    if fixture_path.exists():
        print(f"Loading companies from {fixture_path}")
        with open(fixture_path) as f:
            return json.load(f)
    else:
        print("Using default sample companies")
        return SAMPLE_COMPANIES


def save_fixture(companies: list[dict]):
    """Save companies to fixture file."""
    fixture_path = get_fixture_path()
    fixture_path.parent.mkdir(parents=True, exist_ok=True)
    with open(fixture_path, "w") as f:
        json.dump(companies, f, indent=2, default=str)
    print(f"Saved {len(companies)} companies to {fixture_path}")


def seed_companies(db: Session, companies_data: list[dict], organization_id: str = None):
    """Insert companies into database."""
    created_count = 0
    skipped_count = 0

    for company_data in companies_data:
        # Check if company already exists (by name and organization)
        org_id = organization_id or company_data.get("organization_id", "default-org")
        existing = (
            db.query(Company).filter(Company.name == company_data["name"], Company.organization_id == org_id).first()
        )

        if existing:
            print(f"  Skipping '{company_data['name']}' - already exists")
            skipped_count += 1
            continue

        # Create company
        company = Company(
            name=company_data["name"],
            website=company_data["website"],
            organization_id=org_id,
            owner_id=company_data.get("owner_id"),
            owner_username=company_data.get("owner_username", "system"),
            profile=company_data.get("profile", {}),
            digital=company_data.get("digital", {}),
            timeline=company_data.get("timeline", {}),
            products=company_data.get("products", {}),
            jobs=company_data.get("jobs", {}),
            csr=company_data.get("csr", {}),
            press=company_data.get("press", {}),
            team=company_data.get("team", []),
        )
        db.add(company)
        db.flush()  # Get company ID

        # Create initial tasks for the company
        create_initial_tasks(db, company)

        print(f"  Created '{company_data['name']}' (id={company.id})")
        created_count += 1

    db.commit()
    return created_count, skipped_count


def create_initial_tasks(db: Session, company: Company):
    """Create initial tasks for a company."""
    for task_type in TaskType:
        task = Task(
            company_id=company.id,
            organization_id=company.organization_id,
            type=task_type,
            status=TaskStatus.SUCCEEDED,  # Mark as done since we have sample data
        )
        db.add(task)


def export_from_db(db: Session, organization_id: str = None) -> list[dict]:
    """Export companies from database to fixture format."""
    query = db.query(Company).filter(Company.is_deleted is False)
    if organization_id:
        query = query.filter(Company.organization_id == organization_id)

    companies = query.all()

    result = []
    for c in companies:
        result.append(
            {
                "name": c.name,
                "website": c.website,
                "organization_id": c.organization_id,
                "owner_id": c.owner_id,
                "owner_username": c.owner_username,
                "profile": c.profile or {},
                "digital": c.digital or {},
                "timeline": c.timeline or {},
                "products": c.products or {},
                "jobs": c.jobs or {},
                "csr": c.csr or {},
                "press": c.press or {},
                "team": c.team or [],
            }
        )

    return result


def main():
    parser = argparse.ArgumentParser(description="Seed or export companies")
    parser.add_argument("--export", action="store_true", help="Export companies from database to fixture file")
    parser.add_argument(
        "--org", type=str, default=None, help="Organization ID to use (for seeding) or filter by (for export)"
    )
    parser.add_argument(
        "--clear", action="store_true", help="Clear all existing companies before seeding (WARNING: destructive)"
    )
    args = parser.parse_args()

    db = SessionLocal()

    try:
        if args.export:
            print("Exporting companies from database...")
            companies = export_from_db(db, args.org)
            if companies:
                save_fixture(companies)
            else:
                print("No companies found to export")
        else:
            print("Seeding companies into database...")

            if args.clear:
                print("WARNING: Clearing all existing companies!")
                db.query(Task).delete()
                db.query(Company).delete()
                db.commit()

            companies_data = load_fixture()
            created, skipped = seed_companies(db, companies_data, args.org)
            print(f"\nDone! Created: {created}, Skipped: {skipped}")

    finally:
        db.close()


if __name__ == "__main__":
    main()
