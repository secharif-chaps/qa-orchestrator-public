from fastapi import APIRouter
from app.api.endpoints import company, tasks, auth, admin, security, workspace, webhooks, team_management, modules, cost_analysis, folder, concurrency, chatbot, ai_preferences

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
api_router.include_router(cost_analysis.router)
api_router.include_router(folder.router, prefix="/folders", tags=["folders"])
api_router.include_router(concurrency.router)
api_router.include_router(chatbot.router)
api_router.include_router(ai_preferences.router) 