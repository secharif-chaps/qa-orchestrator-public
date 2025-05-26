from fastapi import APIRouter
from app.api.endpoints import company, webhooks

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(company.router)
api_router.include_router(webhooks.router) 