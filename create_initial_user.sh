#!/bin/bash
# Create Initial User Script
# Creates user 'nmr' in Keycloak and syncs permissions to database

set -e  # Exit on any error

echo "👤 Creating Initial User (nmr)..."

# Create Python script for user creation
cat > create_user.py << 'EOF'
#!/usr/bin/env python3
"""
Create initial user with Keycloak and database permissions
"""

import asyncio
import httpx
import json
import psycopg2
from datetime import datetime
import sys

# Configuration
KEYCLOAK_URL = "http://keycloak:8080"
KEYCLOAK_REALM = "mint-dev"
DB_CONFIG = {
    'host': 'db',
    'port': 5432,
    'database': 'mint_db',
    'user': 'postgres',
    'password': 'postgres'
}

# User configuration
USER_CONFIG = {
    'username': 'nmr',
    'password': 'password',
    'email': 'nmercier@chapsvision.com',
    'firstName': 'Nicolas',
    'lastName': 'Mercier',
    'realm_roles': [
        'workspace.read',
        'workspace.write', 
        'company.view',
        'company.create',
        'company.delete',
        'admin.workspaces'
    ]
}

async def get_admin_token():
    """Get admin token from Keycloak"""
    async with httpx.AsyncClient() as client:
        response = await client.post(
            f"{KEYCLOAK_URL}/realms/master/protocol/openid-connect/token",
            headers={"Content-Type": "application/x-www-form-urlencoded"},
            data={
                "grant_type": "password",
                "client_id": "admin-cli",
                "username": "admin",
                "password": "admin"
            }
        )
        
        if response.status_code == 200:
            return response.json()["access_token"]
        else:
            print(f"❌ Failed to get admin token: {response.status_code} - {response.text}")
            return None

async def create_keycloak_user(admin_token):
    """Create user in Keycloak"""
    headers = {
        "Authorization": f"Bearer {admin_token}",
        "Content-Type": "application/json"
    }
    
    async with httpx.AsyncClient() as client:
        # First, try to delete existing user if it exists
        await delete_existing_user(client, headers)
        
        # Create user
        user_data = {
            "username": USER_CONFIG['username'],
            "email": USER_CONFIG['email'],
            "firstName": USER_CONFIG['firstName'],
            "lastName": USER_CONFIG['lastName'],
            "enabled": True,
            "emailVerified": True,
            "credentials": [{
                "type": "password",
                "value": USER_CONFIG['password'],
                "temporary": False
            }]
        }
        
        # Create user
        response = await client.post(
            f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users",
            headers=headers,
            json=user_data
        )
        
        if response.status_code == 201:
            # Get user ID from location header
            user_location = response.headers.get("Location")
            user_id = user_location.split("/")[-1]
            print(f"✅ Created user in Keycloak with ID: {user_id}")
        else:
            print(f"❌ Failed to create user: {response.status_code} - {response.text}")
            return None
        
        # Assign realm roles
        await assign_realm_roles(client, headers, user_id)
        
        return user_id

async def delete_existing_user(client, headers):
    """Delete existing user if it exists"""
    try:
        # Search for user by username
        response = await client.get(
            f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users?username={USER_CONFIG['username']}",
            headers=headers
        )
        
        if response.status_code == 200:
            users = response.json()
            for user in users:
                if user["username"] == USER_CONFIG['username']:
                    user_id = user["id"]
                    print(f"🗑️ Found existing user {USER_CONFIG['username']}, deleting...")
                    
                    # Delete the user
                    delete_response = await client.delete(
                        f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users/{user_id}",
                        headers=headers
                    )
                    
                    if delete_response.status_code == 204:
                        print(f"✅ Deleted existing user {USER_CONFIG['username']}")
                    else:
                        print(f"⚠️ Failed to delete user: {delete_response.status_code}")
                    break
    except Exception as e:
        print(f"⚠️ Error checking for existing user: {e}")

async def assign_realm_roles(client, headers, user_id):
    """Assign realm roles to user"""
    
    # Get available realm roles
    response = await client.get(
        f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/roles",
        headers=headers
    )
    
    if response.status_code != 200:
        print(f"❌ Failed to get realm roles: {response.status_code}")
        return
    
    available_roles = response.json()
    role_map = {role["name"]: role for role in available_roles}
    
    # Prepare roles to assign
    roles_to_assign = []
    for role_name in USER_CONFIG['realm_roles']:
        if role_name in role_map:
            roles_to_assign.append({
                "id": role_map[role_name]["id"],
                "name": role_name
            })
            print(f"  📝 Will assign role: {role_name}")
        else:
            print(f"  ⚠️  Role not found: {role_name}")
    
    # Assign roles
    if roles_to_assign:
        response = await client.post(
            f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users/{user_id}/role-mappings/realm",
            headers=headers,
            json=roles_to_assign
        )
        
        if response.status_code == 204:
            print(f"✅ Assigned {len(roles_to_assign)} realm roles")
        else:
            print(f"❌ Failed to assign roles: {response.status_code} - {response.text}")

def create_database_permissions(user_id):
    """Create database permissions for the user"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cur = conn.cursor()
        
        # Link user to workspace ID 1 (create workspace if it doesn't exist)
        workspace_id = 1
        
        # Ensure workspace with ID 1 exists
        cur.execute("""
            INSERT INTO workspaces (id, name, description, slug, created_at)
            VALUES (1, 'ChapsVision', 'Default ChapsVision workspace', 'chapsvision', NOW())
            ON CONFLICT (id) DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                slug = EXCLUDED.slug
            RETURNING id;
        """)
        
        result = cur.fetchone()
        if result:
            print(f"✅ Ensured workspace exists with ID: {workspace_id}")
        else:
            print(f"✅ Using existing workspace with ID: {workspace_id}")
        
        # Create workspace member entry
        cur.execute("""
            INSERT INTO workspace_members (workspace_id, user_id, username, email, status, created_at)
            VALUES (%s, %s, %s, %s, 'active', NOW())
            ON CONFLICT (workspace_id, user_id) DO NOTHING
        """, (workspace_id, user_id, USER_CONFIG['username'], USER_CONFIG['email']))
        
        print("✅ Created workspace member entry")
        
        # Create database permissions
        permissions_to_create = [
            # Workspace-specific permissions
            (user_id, workspace_id, 'workspace.read', 'system'),
            (user_id, workspace_id, 'workspace.write', 'system'),
            (user_id, workspace_id, 'company.view', 'system'),
            (user_id, workspace_id, 'company.create', 'system'),
            (user_id, workspace_id, 'company.delete', 'system'),
            # Global permissions
            (user_id, None, 'admin.workspaces', 'system')
        ]
        
        for user_id_perm, workspace_id_perm, permission, granted_by in permissions_to_create:
            cur.execute("""
                INSERT INTO user_workspace_permissions (user_id, workspace_id, permission, granted_by, created_at)
                VALUES (%s, %s, %s, %s, NOW())
                ON CONFLICT (user_id, workspace_id, permission) DO NOTHING
            """, (user_id_perm, workspace_id_perm, permission, granted_by))
            
            workspace_str = f"workspace {workspace_id_perm}" if workspace_id_perm else "global"
            print(f"  📝 Created permission: {permission} ({workspace_str})")
        
        conn.commit()
        cur.close()
        conn.close()
        
        print("✅ Database permissions created successfully")
        
    except Exception as e:
        print(f"❌ Database error: {e}")

async def main():
    """Main function"""
    print("🚀 Creating initial user...")
    
    # Get admin token
    print("1. Getting admin token...")
    admin_token = await get_admin_token()
    if not admin_token:
        sys.exit(1)
    
    # Create user in Keycloak
    print("2. Creating user in Keycloak...")
    user_id = await create_keycloak_user(admin_token)
    if not user_id:
        sys.exit(1)
    
    # Create database permissions
    print("3. Creating database permissions...")
    create_database_permissions(user_id)
    
    print("✅ Initial user creation complete!")
    print(f"📋 User details:")
    print(f"   Username: {USER_CONFIG['username']}")
    print(f"   Password: {USER_CONFIG['password']}")
    print(f"   Email: {USER_CONFIG['email']}")
    print(f"   Keycloak ID: {user_id}")
    print(f"   Roles: {', '.join(USER_CONFIG['realm_roles'])}")

if __name__ == "__main__":
    asyncio.run(main())
EOF

# Copy script to container and run it
echo "🐍 Running user creation script..."
docker compose -f docker-compose.dev.yml cp create_user.py backend:/app/create_user.py
docker compose -f docker-compose.dev.yml exec backend python create_user.py

# Clean up
rm create_user.py
docker compose -f docker-compose.dev.yml exec backend rm /app/create_user.py

echo "✅ Initial user creation complete!"