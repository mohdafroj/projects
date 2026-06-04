import asyncio
import uuid
from sqlalchemy.ext.asyncio import AsyncSession
from app.db.session import AsyncSessionLocal
from app.models.user import User, Role, Permission
from app.core.security import get_password_hash

async def seed_data():
    async with AsyncSessionLocal() as db:
        # 1. Create Permissions
        permissions_data = [
            {"name": "user:create", "description": "Create new users", "module": "user"},
            {"name": "user:view", "description": "View user details", "module": "user"},
            {"name": "user:update", "description": "Update existing users", "module": "user"},
            {"name": "user:delete", "description": "Delete users", "module": "user"},
            {"name": "role:manage", "description": "Manage roles and permissions", "module": "role"},
            {"name": "audit:view", "description": "View system audit logs", "module": "audit"},
        ]
        
        permissions = []
        for p_data in permissions_data:
            permission = Permission(**p_data)
            db.add(permission)
            permissions.append(permission)
        
        # 2. Create Roles
        admin_role = Role(
            name="Admin",
            description="Administrator with full access",
            permissions=permissions
        )
        user_role = Role(
            name="User",
            description="Standard user with limited access",
            permissions=[p for p in permissions if p.name == "user:view"]
        )
        db.add(admin_role)
        db.add(user_role)
        
        # 3. Create Super Admin User
        super_admin = User(
            email="admin@example.com",
            username="admin",
            full_name="Super Admin",
            hashed_password=get_password_hash("admin123"),
            is_active=True,
            is_verified=True,
            is_super_admin=True,
            roles=[admin_role]
        )
        db.add(super_admin)
        
        try:
            await db.commit()
            print("Database seeded successfully!")
        except Exception as e:
            await db.rollback()
            print(f"Error seeding database: {e}")

if __name__ == "__main__":
    asyncio.run(seed_data())
