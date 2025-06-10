from pydantic_settings import BaseSettings
from typing import Optional
from pydantic import ConfigDict
import os


class Settings(BaseSettings):
    # API settings
    API_HOST: str = "0.0.0.0"
    API_PORT: int = 8000
    
    # Database settings
    DATABASE_URL: str = "postgresql://postgres:postgres@db:5432/mint_db"
    
    
    # N8N settings
    N8N_BASE_URL: str = "http://ec2-34-244-245-92.eu-west-1.compute.amazonaws.com:5678"
    # N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_WEBHOOK_ID: str = "57be7c18-e8b2-47aa-b9aa-f7c2696f4523"
    N8N_API_KEY: str = "n8n-api-key"
    
    # CORS settings
    CORS_ORIGIN: str = "http://localhost:3000"
    
    # Keycloak settings
    KEYCLOAK_SERVER_URL: str = "http://keycloak:8080"
    KEYCLOAK_REALM: str = "mint-dev"
    KEYCLOAK_CLIENT_ID: str = "mint-back"
    KEYCLOAK_CLIENT_SECRET: Optional[str] = None
    
    # JWT settings
    JWT_ALGORITHM: str = "RS256"
    JWT_AUDIENCE: str = "account"

    model_config = ConfigDict(env_file=".env", env_file_encoding="utf-8")


settings = Settings() 