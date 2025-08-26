#!/bin/bash
# Create Multiple Test Users Script
# Creates multiple users with different permission combinations for testing

set -e  # Exit on any error

echo "👥 Creating Multiple Test Users..."

# Detect which docker-compose file to use and set admin credentials
if [ -f "docker-compose.preprod.yml" ]; then
    COMPOSE_FILE="docker-compose.preprod.yml"
    ADMIN_PASSWORD="admin_preprod_password"
    echo "🔧 Using preprod configuration with preprod admin password"
elif [ -f "docker-compose.dev.yml" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
    ADMIN_PASSWORD="admin"
    echo "🔧 Using development configuration with default admin password"
else
    echo "❌ No docker-compose file found"
    exit 1
fi

# Create Python script for multiple user creation
cat > create_test_users.py << 'EOF'
#!/usr/bin/env python3
"""
Create multiple test users with Keycloak and database permissions
"""

import asyncio
import httpx
import json
import psycopg2
import os
from datetime import datetime
import sys

# Configuration
KEYCLOAK_URL = "http://keycloak:8080"
KEYCLOAK_REALM = "mint-dev"
ADMIN_PASSWORD = os.getenv('KEYCLOAK_ADMIN_PASSWORD', 'admin')
DB_CONFIG = {
    'host': 'db',
    'port': 5432,
    'database': 'mint_db',
    'user': 'postgres',
    'password': 'postgres'
}

# Test users configuration with different permission combinations
TEST_USERS = [
    {
        'username': 'admin',
        'password': 'admin123',
        'email': 'admin@test.com',
        'firstName': 'Admin',
        'lastName': 'User',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete',
            'admin.workspaces',
            'admin.workflows',
            'admin.costs'
        ],
        'description': 'Full admin access - can manage workspaces and all company operations'
    },
    {
        'username': 'company_manager',
        'password': 'manager123',
        'email': 'manager@test.com',
        'firstName': 'Company',
        'lastName': 'Manager',
        'realm_roles': [
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Full company management - can create and delete companies'
    },
    {
        'username': 'company_editor',
        'password': 'editor123',
        'email': 'editor@test.com',
        'firstName': 'Company',
        'lastName': 'Editor',
        'realm_roles': [
            'company.view'
        ],
        'description': 'Can view companies only - read access'
    },
    {
        'username': 'company_creator',
        'password': 'creator123',
        'email': 'creator@test.com',
        'firstName': 'Company',
        'lastName': 'Creator',
        'realm_roles': [
            'company.view',
            'company.create'
        ],
        'description': 'Can view and create companies, cannot delete existing ones'
    },
    {
        'username': 'company_viewer',
        'password': 'viewer123',
        'email': 'viewer@test.com',
        'firstName': 'Company',
        'lastName': 'Viewer',
        'realm_roles': [
            'company.view'
        ],
        'description': 'Read-only access - can only view companies and workspace content'
    },
    {
        'username': 'workspace_manager',
        'password': 'workspace123',
        'email': 'workspace@test.com',
        'firstName': 'Workspace',
        'lastName': 'Manager',
        'realm_roles': [
            'workspace.read',
            'workspace.write',
            'company.view'
        ],
        'description': 'Can manage workspace users and view companies, but cannot create/edit companies'
    },
    {
        'username': 'no_access',
        'password': 'noaccess123',
        'email': 'noaccess@test.com',
        'firstName': 'No',
        'lastName': 'Access',
        'realm_roles': [],
        'description': 'No permissions - should be redirected to 403 for most pages'
    }
]

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
                "password": ADMIN_PASSWORD
            }
        )
        
        if response.status_code == 200:
            return response.json()["access_token"]
        else:
            print(f"❌ Failed to get admin token: {response.status_code} - {response.text}")
            return None

async def delete_existing_user(client, headers, username):
    """Delete existing user if it exists"""
    try:
        # Search for user by username
        response = await client.get(
            f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users?username={username}",
            headers=headers
        )
        
        if response.status_code == 200:
            users = response.json()
            for user in users:
                if user["username"] == username:
                    user_id = user["id"]
                    print(f"🗑️ Found existing user {username}, deleting...")
                    
                    # Delete the user
                    delete_response = await client.delete(
                        f"{KEYCLOAK_URL}/admin/realms/{KEYCLOAK_REALM}/users/{user_id}",
                        headers=headers
                    )
                    
                    if delete_response.status_code == 204:
                        print(f"✅ Deleted existing user {username}")
                    else:
                        print(f"⚠️ Failed to delete user: {delete_response.status_code}")
                    break
    except Exception as e:
        print(f"⚠️ Error checking for existing user: {e}")

async def create_keycloak_user(admin_token, user_config):
    """Create user in Keycloak"""
    headers = {
        "Authorization": f"Bearer {admin_token}",
        "Content-Type": "application/json"
    }
    
    async with httpx.AsyncClient() as client:
        # First, try to delete existing user if it exists
        await delete_existing_user(client, headers, user_config['username'])
        
        # Create user
        user_data = {
            "username": user_config['username'],
            "email": user_config['email'],
            "firstName": user_config['firstName'],
            "lastName": user_config['lastName'],
            "enabled": True,
            "emailVerified": True,
            "credentials": [{
                "type": "password",
                "value": user_config['password'],
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
            print(f"✅ Created user {user_config['username']} in Keycloak with ID: {user_id}")
        else:
            print(f"❌ Failed to create user {user_config['username']}: {response.status_code} - {response.text}")
            return None
        
        # Assign realm roles
        if user_config['realm_roles']:
            await assign_realm_roles(client, headers, user_id, user_config['realm_roles'])
        else:
            print(f"  📝 No roles to assign for {user_config['username']}")
        
        return user_id

async def assign_realm_roles(client, headers, user_id, role_names):
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
    for role_name in role_names:
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

def create_database_permissions(user_id, user_config):
    """Create database permissions for the user"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cur = conn.cursor()
        
        # Link user to workspace ID 1 (create workspace if it doesn't exist)
        workspace_id = 1
        
        # Ensure workspace with ID 1 exists (same as create_initial_user.sh)
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
        
        # Create workspace member entry (link all users to workspace ID 1)
        # Check if workspace member already exists
        cur.execute("""
            SELECT id FROM workspace_members 
            WHERE workspace_id = %s AND user_id = %s
        """, (workspace_id, user_id))
        
        existing_member = cur.fetchone()
        if not existing_member:
            cur.execute("""
                INSERT INTO workspace_members (workspace_id, user_id, username, email, status, created_at)
                VALUES (%s, %s, %s, %s, 'active', NOW())
            """, (workspace_id, user_id, user_config['username'], user_config['email']))
            print(f"✅ Created workspace member entry for {user_config['username']}")
        else:
            print(f"✅ Workspace member already exists for {user_config['username']}")
        
        # Create database permissions (all workspace-specific permissions linked to workspace ID 1)
        permissions_to_create = []
        
        for permission in user_config['realm_roles']:
            if permission.startswith('admin.'):
                # Global permissions
                permissions_to_create.append((user_id, None, permission, 'system'))
            else:
                # Workspace-specific permissions (all linked to workspace ID 1)
                permissions_to_create.append((user_id, workspace_id, permission, 'system'))
        
        if permissions_to_create:
            for user_id_perm, workspace_id_perm, permission, granted_by in permissions_to_create:
                # Check if permission already exists before inserting
                cur.execute("""
                    SELECT id FROM user_workspace_permissions 
                    WHERE user_id = %s AND workspace_id IS NOT DISTINCT FROM %s AND permission = %s
                """, (user_id_perm, workspace_id_perm, permission))
                
                existing = cur.fetchone()
                if not existing:
                    cur.execute("""
                        INSERT INTO user_workspace_permissions (user_id, workspace_id, permission, granted_by, created_at)
                        VALUES (%s, %s, %s, %s, NOW())
                    """, (user_id_perm, workspace_id_perm, permission, granted_by))
                    
                    workspace_str = f"workspace {workspace_id_perm}" if workspace_id_perm else "global"
                    print(f"  📝 Created permission: {permission} ({workspace_str})")
                else:
                    workspace_str = f"workspace {workspace_id_perm}" if workspace_id_perm else "global"
                    print(f"  ✅ Permission already exists: {permission} ({workspace_str})")
        else:
            print(f"  📝 No permissions to create for {user_config['username']}")
        
        conn.commit()
        cur.close()
        conn.close()
        
        print(f"✅ Database permissions created successfully for {user_config['username']}")
        
    except Exception as e:
        print(f"❌ Database error for {user_config['username']}: {e}")

async def main():
    """Main function"""
    print("🚀 Creating multiple test users...")
    
    # Get admin token
    print("1. Getting admin token...")
    admin_token = await get_admin_token()
    if not admin_token:
        sys.exit(1)
    
    created_users = []
    
    # Create each test user
    for i, user_config in enumerate(TEST_USERS, 1):
        print(f"\n{i}. Creating user: {user_config['username']}")
        print(f"   Description: {user_config['description']}")
        
        # Create user in Keycloak
        user_id = await create_keycloak_user(admin_token, user_config)
        if not user_id:
            print(f"❌ Failed to create user {user_config['username']}, skipping...")
            continue
        
        # Create database permissions
        create_database_permissions(user_id, user_config)
        
        created_users.append({
            'username': user_config['username'],
            'password': user_config['password'],
            'email': user_config['email'],
            'keycloak_id': user_id,
            'permissions': user_config['realm_roles'],
            'description': user_config['description']
        })
    
    print("\n✅ Test users creation complete!")
    print("\n📋 Created Users Summary:")
    print("=" * 80)
    
    for user in created_users:
        print(f"\nUsername: {user['username']}")
        print(f"Password: {user['password']}")
        print(f"Email: {user['email']}")
        print(f"Description: {user['description']}")
        print(f"Permissions: {', '.join(user['permissions']) if user['permissions'] else 'None'}")
        print("-" * 40)

if __name__ == "__main__":
    asyncio.run(main())
EOF

# Copy script to container and run it
echo "🐍 Running test users creation script..."
docker compose -f "$COMPOSE_FILE" cp create_test_users.py backend:/app/create_test_users.py
docker compose -f "$COMPOSE_FILE" exec -e KEYCLOAK_ADMIN_PASSWORD="$ADMIN_PASSWORD" backend python create_test_users.py

# Clean up
rm create_test_users.py
docker compose -f "$COMPOSE_FILE" exec backend rm /app/create_test_users.py

echo "✅ Test users creation complete!"