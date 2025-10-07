#!/bin/bash
# Create ChapsVision Initial Users Script
# Creates the ChapsVision team users with standard permissions (no admin access)
#
# Usage:
#   ./create_initial_users.sh                    # Interactive mode (prompts for credentials)
#   ./create_initial_users.sh --non-interactive  # Use environment-specific defaults

set -e  # Exit on any error

echo "👥 Creating ChapsVision Initial Users..."

# Check for non-interactive mode
NON_INTERACTIVE=false
if [ "$1" = "--non-interactive" ]; then
    NON_INTERACTIVE=true
    echo "🤖 Running in non-interactive mode"
fi

# Detect which docker-compose file to use
if [ -f "docker-compose.preprod.yml" ]; then
    COMPOSE_FILE="docker-compose.preprod.yml"
    DEFAULT_ADMIN_PASSWORD="admin_preprod_password"
    DEFAULT_DB_PASSWORD="postgres_preprod_password"
    echo "🔧 Using preprod configuration"
elif [ -f "docker-compose.dev.yml" ]; then
    COMPOSE_FILE="docker-compose.dev.yml"
    DEFAULT_ADMIN_PASSWORD="admin"
    DEFAULT_DB_PASSWORD="postgres"
    echo "🔧 Using development configuration"
else
    echo "❌ No docker-compose file found"
    exit 1
fi

# Set admin credentials (interactive or default)
if [ "$NON_INTERACTIVE" = true ]; then
    ADMIN_USERNAME="admin"
    ADMIN_PASSWORD="$DEFAULT_ADMIN_PASSWORD"
    DB_USERNAME="postgres"
    DB_PASSWORD="$DEFAULT_DB_PASSWORD"
    echo "✅ Using default admin credentials: $ADMIN_USERNAME / [hidden]"
    echo "✅ Using default database credentials: $DB_USERNAME / [hidden]"
else
    # Prompt for admin credentials
    echo ""
    echo "🔐 Keycloak Admin Credentials"
    echo "─────────────────────────────"
    read -p "Admin username [admin]: " ADMIN_USERNAME
    ADMIN_USERNAME=${ADMIN_USERNAME:-admin}

    read -p "Admin password [$DEFAULT_ADMIN_PASSWORD]: " -s ADMIN_PASSWORD
    echo  # New line after password input
    ADMIN_PASSWORD=${ADMIN_PASSWORD:-$DEFAULT_ADMIN_PASSWORD}

    echo "✅ Using admin credentials: $ADMIN_USERNAME / [hidden]"

    # Prompt for database credentials
    echo ""
    echo "🗄️  Database Credentials"
    echo "─────────────────────────"
    read -p "Database username [postgres]: " DB_USERNAME
    DB_USERNAME=${DB_USERNAME:-postgres}

    read -p "Database password [$DEFAULT_DB_PASSWORD]: " -s DB_PASSWORD
    echo  # New line after password input
    DB_PASSWORD=${DB_PASSWORD:-$DEFAULT_DB_PASSWORD}

    echo "✅ Using database credentials: $DB_USERNAME / [hidden]"
fi
echo ""

# Create Python script for ChapsVision user creation
cat > create_initial_users.py << 'EOF'
#!/usr/bin/env python3
"""
Create ChapsVision initial users with Keycloak and database permissions
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
ADMIN_USERNAME = os.getenv('KEYCLOAK_ADMIN_USERNAME', 'admin')
ADMIN_PASSWORD = os.getenv('KEYCLOAK_ADMIN_PASSWORD', 'admin')
DB_CONFIG = {
    'host': 'db',
    'port': 5432,
    'database': 'mint_db',
    'user': os.getenv('DB_USERNAME', 'postgres'),
    'password': os.getenv('DB_PASSWORD', 'postgres')
}

# ChapsVision users configuration - All get standard permissions (no admin)
CHAPSVISION_USERS = [
    {
        'username': 'suh',
        'password': 'Ch@psVision',
        'email': 'sophie.ulrich@chapsvision.com',
        'firstName': 'Sophie',
        'lastName': 'Ulrich',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Sophie Ulrich - Full workspace and company management access'
    },
    {
        'username': 'nmr',
        'password': 'Ch@psVision',
        'email': 'nicolas.mercier@chapsvision.com',
        'firstName': 'Nicolas',
        'lastName': 'Mercier',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Nicolas Mercier - Full workspace and company management access'
    },
    {
        'username': 'jct',
        'password': 'Ch@psVision',
        'email': 'jerome.clot@chapsvision.com',
        'firstName': 'Jerome',
        'lastName': 'Clot',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Jerome Clot - Full workspace and company management access'
    },
    {
        'username': 'ffc',
        'password': 'Ch@psVision',
        'email': 'frederic.fayard@chapsvision.com',
        'firstName': 'Frederic',
        'lastName': 'Fayard le barzic',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Frederic Fayard le barzic - Full workspace and company management access'
    },
    {
        'username': 'nmt',
        'password': 'Ch@psVision',
        'email': 'nicolas.marot@chapsvision.com',
        'firstName': 'Nicolas',
        'lastName': 'Marot',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Nicolas Marot - Full workspace and company management access'
    },
    {
        'username': 'pmb',
        'password': 'Ch@psVision',
        'email': 'pierre-mayeul.badaire@chapsvision.com',
        'firstName': 'Pierre-Mayeul',
        'lastName': 'Badaire',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Pierre-Mayeul Badaire - Full workspace and company management access'
    },
    {
        'username': 'iri',
        'password': 'Ch@psVision',
        'email': 'ibtissem.rebai@chapsvision.com',
        'firstName': 'Ibtissem',
        'lastName': 'Rebai',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Ibtissem Rebai - Full workspace and company management access'
    },
    {
        'username': 'iye',
        'password': 'Ch@psVision',
        'email': 'irene.yolande@chapsvision.com',
        'firstName': 'Irene',
        'lastName': 'Yolande',
        'realm_roles': [
            'workspace.read',
            'workspace.write', 
            'company.view',
            'company.create',
            'company.delete'
        ],
        'description': 'Irene Yolande - Full workspace and company management access'
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
                "username": ADMIN_USERNAME,
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
        
        # Link user to workspace ID 1 (ChapsVision workspace)
        workspace_id = 1
        
        # Ensure workspace with ID 1 exists
        cur.execute("""
            INSERT INTO workspaces (id, name, description, slug, created_at)
            VALUES (1, 'ChapsVision', 'Main ChapsVision workspace', 'chapsvision', NOW())
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
        # Check if workspace member already exists
        cur.execute("""
            SELECT id FROM workspace_members 
            WHERE workspace_id = %s AND user_id = %s
        """, (workspace_id, user_id))
        
        existing_member = cur.fetchone()
        if not existing_member:
            cur.execute("""
                INSERT INTO workspace_members (workspace_id, user_id, username, email, first_name, last_name, status, created_at)
                VALUES (%s, %s, %s, %s, %s, %s, 'active', NOW())
            """, (workspace_id, user_id, user_config['username'], user_config['email'], user_config['firstName'], user_config['lastName']))
            print(f"✅ Created workspace member entry for {user_config['username']}")
        else:
            print(f"✅ Workspace member already exists for {user_config['username']}")
        
        # Create database permissions (all workspace-specific permissions linked to workspace ID 1)
        permissions_to_create = []
        
        for permission in user_config['realm_roles']:
            if permission.startswith('admin.'):
                # Global permissions - ChapsVision users don't get admin permissions
                print(f"  🚫 Skipping admin permission: {permission}")
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
    print("🚀 Creating ChapsVision initial users...")
    
    # Get admin token
    print("1. Getting admin token...")
    admin_token = await get_admin_token()
    if not admin_token:
        sys.exit(1)
    
    created_users = []
    
    # Create each ChapsVision user
    for i, user_config in enumerate(CHAPSVISION_USERS, 1):
        print(f"\n{i}. Creating user: {user_config['username']}")
        print(f"   Name: {user_config['firstName']} {user_config['lastName']}")
        print(f"   Email: {user_config['email']}")
        
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
            'name': f"{user_config['firstName']} {user_config['lastName']}",
            'keycloak_id': user_id,
            'permissions': user_config['realm_roles'],
            'description': user_config['description']
        })
    
    print("\n✅ ChapsVision users creation complete!")
    print("\n📋 Created Users Summary:")
    print("=" * 80)
    
    for user in created_users:
        print(f"\nUsername: {user['username']}")
        print(f"Password: {user['password']}")
        print(f"Name: {user['name']}")
        print(f"Email: {user['email']}")
        print(f"Permissions: {', '.join(user['permissions']) if user['permissions'] else 'None'}")
        print("-" * 40)
    
    print("\n🏢 All ChapsVision users have been added to workspace 'ChapsVision' (ID: 1)")
    print("🔐 All users have full workspace and company management permissions (excluding admin access)")

if __name__ == "__main__":
    asyncio.run(main())
EOF

# Copy script to container and run it
echo "🐍 Running ChapsVision users creation script..."
docker compose -f "$COMPOSE_FILE" cp create_initial_users.py backend:/app/create_initial_users.py
docker compose -f "$COMPOSE_FILE" exec -e KEYCLOAK_ADMIN_USERNAME="$ADMIN_USERNAME" -e KEYCLOAK_ADMIN_PASSWORD="$ADMIN_PASSWORD" -e DB_USERNAME="$DB_USERNAME" -e DB_PASSWORD="$DB_PASSWORD" backend python create_initial_users.py

# Clean up
rm create_initial_users.py
docker compose -f "$COMPOSE_FILE" exec backend rm /app/create_initial_users.py

echo "✅ ChapsVision initial users creation complete!"