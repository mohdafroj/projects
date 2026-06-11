from typing import Optional, List
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload
from app.models.user import Role, Permission

class RoleRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_by_name(self, name: str) -> Optional[Role]:
        query = select(Role).where(Role.name == name, Role.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_by_id(self, role_id: str) -> Optional[Role]:
        query = select(Role).where(Role.id == role_id, Role.is_deleted == False).options(selectinload(Role.permissions))
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def create(self, role: Role) -> Role:
        self.db.add(role)
        await self.db.flush()
        return role

    async def update(self, role: Role) -> Role:
        self.db.add(role)
        await self.db.flush()
        return role

    async def list(self, skip: int = 0, limit: int = 100) -> List[Role]:
        query = select(Role).where(Role.is_deleted == False).options(selectinload(Role.permissions)).offset(skip).limit(limit)
        result = await self.db.execute(query)
        return result.scalars().all()

    async def delete(self, role: Role) -> None:
        role.is_deleted = True
        self.db.add(role)
        await self.db.flush()


class PermissionRepository:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_by_name(self, name: str) -> Optional[Permission]:
        query = select(Permission).where(Permission.name == name, Permission.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_by_id(self, permission_id: str) -> Optional[Permission]:
        query = select(Permission).where(Permission.id == permission_id, Permission.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalar_one_or_none()

    async def get_by_ids(self, permission_ids: List[str]) -> List[Permission]:
        query = select(Permission).where(Permission.id.in_(permission_ids), Permission.is_deleted == False)
        result = await self.db.execute(query)
        return result.scalars().all()

    async def create(self, permission: Permission) -> Permission:
        self.db.add(permission)
        await self.db.flush()
        return permission

    async def update(self, permission: Permission) -> Permission:
        self.db.add(permission)
        await self.db.flush()
        return permission

    async def list(self, skip: int = 0, limit: int = 100) -> List[Permission]:
        query = select(Permission).where(Permission.is_deleted == False).offset(skip).limit(limit)
        result = await self.db.execute(query)
        return result.scalars().all()

    async def delete(self, permission: Permission) -> None:
        permission.is_deleted = True
        self.db.add(permission)
        await self.db.flush()
