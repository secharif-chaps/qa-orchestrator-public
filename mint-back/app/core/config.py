from pydantic_settings import BaseSettings
from typing import Optional
from pydantic import ConfigDict
import os


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    BACKEND_BASE_URL: str = "http://10.0.1.2"  # Used for webhook callbacks - through nginx reverse proxy
    
    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/mint_db"
    
    
    # Dify settings
    DIFY_API_KEY: str = "app-WpGZCTFDaBzCUS9M4LeoQHGa"  # Products workflow API key
    DIFY_TIMELINE_API_KEY: str = "app-qX4RISdrrif2aSPAaLVz7tto"  # Timeline workflow API key
    DIFY_URL: str = "http://10.0.1.1/v1"
    DIFY_PRODUCT_WORKFLOW_ID: str = "9d9c4884-342d-42d4-afcc-2ef15479db2f"
    DIFY_TIMELINE_WORKFLOW_ID: str = "0781c8e1-cbcf-42fc-a225-7026e761cb36"
    
    # CORS settings
    CORS_ORIGIN: str = "http://localhost:3000"
    
    # Keycloak settings
    KEYCLOAK_SERVER_URL: str = "http://10.0.1.2:8080"
    KEYCLOAK_REALM: str = "mint-dev"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: Optional[str] = None
    KEYCLOAK_ADMIN_USERNAME: str = "admin"
    KEYCLOAK_ADMIN_PASSWORD: str = "admin"
    KEYCLOAK_ADMIN_CLIENT_ID: str = "admin-cli"
    KEYCLOAK_ADMIN_CLIENT_SECRET: str = "admin-cli-secret"
    
    # JWT settings
    JWT_ALGORITHM: str = "RS256"
    JWT_AUDIENCE: str = "account"

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8")


settings = Settings() 