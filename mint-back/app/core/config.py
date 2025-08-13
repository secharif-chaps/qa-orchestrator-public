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
    
    
    # N8N settings
    N8N_BASE_URL: str = "http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678"
    # N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_CHAT_WEBHOOK_ID: str = "96b9765e-6c96-4493-bf56-a65905f7a6bc"
    N8N_API_KEY: str = "n8n-api-key"
    
    # Dify settings
    DIFY_API_KEY: str = "app-WpGZCTFDaBzCUS9M4LeoQHGa"  # Products workflow API key
    DIFY_TIMELINE_API_KEY: str = "app-qX4RISdrrif2aSPAaLVz7tto"  # Timeline workflow API key
    DIFY_URL: str = "http://10.0.1.1/v1"
    DIFY_PRODUCT_WORKFLOW_ID: str = "9d9c4884-342d-42d4-afcc-2ef15479db2f"
    DIFY_TIMELINE_WORKFLOW_ID: str = "6b95cda2-b32f-4578-9b63-520ebb527c97"
    CODE_MAX_STRING_ARRAY_LENGTH: int = 200
    CODE_MAX_OBJECT_ARRAY_LENGTH: int = 200
    
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