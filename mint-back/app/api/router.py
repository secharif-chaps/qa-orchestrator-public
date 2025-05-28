from fastapi import APIRouter
from app.api.endpoints import company

api_router = APIRouter()

# Include routes from different modules
api_router.include_router(company.router) 