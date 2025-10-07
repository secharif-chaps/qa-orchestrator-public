#!/usr/bin/env python3
"""
Database initialization script
"""
import psycopg2
from psycopg2.extensions import ISOLATION_LEVEL_AUTOCOMMIT
import os
import sys
from pathlib import Path

# Add the parent directory to the sys.path
sys.path.append(str(Path(__file__).parent.parent))

from app.core.config import settings

def create_database():
    """Create the database if it doesn't exist"""
    
    # Parse the database URL
    if "postgresql://" in settings.DATABASE_URL:
        db_parts = settings.DATABASE_URL.split("/")
        db_name = db_parts[-1]
        db_connection = "/".join(db_parts[:-1]) + "/postgres"
    else:
        print("Invalid database URL format. Expected postgresql://...")
        sys.exit(1)
    
    try:
        # Connect to the default 'postgres' database
        conn = psycopg2.connect(db_connection)
        conn.set_isolation_level(ISOLATION_LEVEL_AUTOCOMMIT)
        cursor = conn.cursor()
        
        # Check if the database exists
        cursor.execute(f"SELECT 1 FROM pg_catalog.pg_database WHERE datname = '{db_name}'")
        exists = cursor.fetchone()
        
        if not exists:
            print(f"Creating database '{db_name}'...")
            cursor.execute(f"CREATE DATABASE {db_name}")
            print(f"Database '{db_name}' created successfully")
        else:
            print(f"Database '{db_name}' already exists")
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"Error creating database: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    create_database()
    
    # Run migrations
    print("Running migrations...")
    os.system("alembic upgrade head")
    print("Done!") 