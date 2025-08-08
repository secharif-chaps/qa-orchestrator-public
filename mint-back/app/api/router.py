from fastapi import APIRouter
from app.api.endpoints import company, tasks, auth, admin, security, workspace, webhooks, team_management, modules

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(auth.router)
api_router.include_router(company.router)
api_router.include_router(tasks.router)
api_router.include_router(admin.router)
api_router.include_router(security.router)
api_router.include_router(workspace.router)
api_router.include_router(team_management.router)
api_router.include_router(modules.router)
api_router.include_router(webhooks.router) 