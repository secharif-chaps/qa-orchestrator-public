from pydantic_settings import BaseSettings
from typing import Optional
from pydantic import ConfigDict
import os


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    BACKEND_BASE_URL: str = "http://10.0.1.2:8000"  # Used for webhook callbacks - external IP for Dify access
    
    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/mint_db"
    
    
    # N8N settings
    N8N_BASE_URL: str = "http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678"
    # N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_CHAT_WEBHOOK_ID: str = "96b9765e-6c96-4493-bf56-a65905f7a6bc"
    N8N_API_KEY: str = "n8n-api-key"
    
    # Dify settings
    DIFY_API_KEY: str = "app-WpGZCTFDaBzCUS9M4LeoQHGa"
    DIFY_URL: str = "http://10.0.1.1/v1"
    DIFY_PRODUCT_WORKFLOW_ID: str = "c62b24e8-4fb8-49e7-96f8-edc10d7cebe0"
    
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