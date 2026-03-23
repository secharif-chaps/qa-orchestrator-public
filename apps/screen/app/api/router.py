from fastapi import APIRouter

from app.api.endpoints import (
    account,
    admin,
    admin_tasks,
    ai_preferences,
    auth,
    chapse,
    company,
    cost_analysis,
    credits,
    data_sources,
    feature_flags,
    organization,
    organizations,
    security,
    tasks,
    team,
    translation,
    users,
)

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(account.router)  # User self-service account management
api_router.include_router(auth.router)
api_router.include_router(company.router)
api_router.include_router(tasks.router)
api_router.include_router(admin.router)
api_router.include_router(security.router)
api_router.include_router(users.router)  # Global user management (admin)
api_router.include_router(organizations.router)  # Keycloak Organizations admin endpoints
api_router.include_router(organization.router)  # User organization context (/current, /activities)
api_router.include_router(team.router)  # Team management (organization.manage)
# Note: modules and tokens endpoints have been migrated to global-service
# See: global-service/app/routers/tokens.py and global-service/app/routers/modules.py
api_router.include_router(cost_analysis.router)
api_router.include_router(chapse.router)
api_router.include_router(ai_preferences.router)
api_router.include_router(admin_tasks.router)  # Admin task monitoring
api_router.include_router(admin_tasks.org_router)  # Admin organizations for task monitoring
api_router.include_router(translation.router)
api_router.include_router(feature_flags.router)  # Organization feature flags management
api_router.include_router(credits.router)  # Organization credit statistics
api_router.include_router(data_sources.router)  # Organization data sources
