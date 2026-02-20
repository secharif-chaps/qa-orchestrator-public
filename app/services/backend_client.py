"""Backend client for fetching company data from the monolith (mint-server).

During the migration period, company data still lives in mint_db.
This client calls the backend API to enrich folder items with company details.
"""

import httpx
from typing import Optional
from pydantic import BaseModel

from app.core.config import settings
from app.core.internal_jwt import create_internal_token
from app.core.logging_config import get_logger

logger = get_logger(__name__)


class CompanyInfo(BaseModel):
    """Minimal company info for folder item enrichment."""
    id: int
    name: str
    website: Optional[str] = None
    is_deleted: bool = False
    owner_username: Optional[str] = None
    created_at: Optional[str] = None


def _get_backend_base_url() -> str:
    """Get the backend base URL from settings."""
    return settings.BACKEND_BASE_URL.rstrip("/")


def _create_auth_headers(
    user_id: str,
    username: str,
    org_id: str,
    org_name: str,
    roles: list[str],
    email: Optional[str] = None,
) -> dict:
    """Create internal JWT auth headers for backend calls."""
    token = create_internal_token(
        user_id=user_id,
        username=username,
        org_id=org_id,
        org_name=org_name,
        roles=roles,
        email=email,
    )
    return {"Authorization": f"Bearer {token}"}


async def get_companies_by_ids(
    company_ids: list[int],
    user_id: str,
    username: str,
    org_id: str,
    org_name: str = "",
    roles: list[str] | None = None,
    include_archived: bool = False,
) -> dict[int, CompanyInfo]:
    """Fetch company details from the backend by their IDs.

    Calls the backend's company list endpoint to get details for folder item enrichment.
    Returns a dict mapping company_id -> CompanyInfo for found companies.

    Args:
        company_ids: List of company IDs to fetch
        user_id: Keycloak user UUID for auth context
        username: Username for auth context
        org_id: Organization UUID for auth context
        org_name: Organization name for auth context
        roles: User roles for auth context
        include_archived: Whether to include archived companies

    Returns:
        Dict mapping company_id to CompanyInfo for found companies
    """
    if not company_ids:
        return {}

    headers = _create_auth_headers(
        user_id=user_id,
        username=username,
        org_id=org_id,
        org_name=org_name,
        roles=roles or [],
    )

    result: dict[int, CompanyInfo] = {}
    base_url = _get_backend_base_url()

    async with httpx.AsyncClient(
        base_url=base_url,
        timeout=httpx.Timeout(connect=5.0, read=10.0, write=5.0, pool=5.0),
    ) as client:
        for company_id in company_ids:
            try:
                response = await client.get(
                    f"/api/companies/{company_id}",
                    headers=headers,
                    params={"archived": str(include_archived).lower()},
                )

                if response.status_code == 200:
                    data = response.json()
                    result[company_id] = CompanyInfo(
                        id=data.get("id", company_id),
                        name=data.get("name", "Unknown"),
                        website=data.get("website"),
                        is_deleted=data.get("is_deleted", False),
                        owner_username=data.get("owner_username"),
                        created_at=data.get("created_at"),
                    )
                elif response.status_code == 404:
                    logger.debug(f"Company {company_id} not found on backend")
                else:
                    logger.warning(
                        f"Backend returned {response.status_code} for company {company_id}"
                    )
            except httpx.RequestError as e:
                logger.error(
                    f"Failed to fetch company {company_id} from backend: {e}"
                )

    logger.debug(
        f"Fetched {len(result)}/{len(company_ids)} companies from backend"
    )
    return result
